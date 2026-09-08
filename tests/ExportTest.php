<?php

use Balikovna_WC\Export;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Export extends Export {
	public function encodeRow( array $row ) {
		$stream = fopen( 'php://memory', 'w+' );
		$this->fputcsv_cp1250( $stream, $row );
		rewind( $stream );
		return stream_get_contents( $stream );
	}

	public function destination( WC_Order $order, array $point, $service_code ) {
		return $this->destination_columns( $order, $point, $service_code );
	}

	public function weight( WC_Order $order ) {
		return $this->calc_weight( $order );
	}

	public function rows( WC_Order $order ) {
		return $this->prepare_order_rows( $order );
	}
}

final class ExportTest extends TestCase {
	protected function setUp(): void {
		remove_all_filters();
	}

	public function test_formula_prefixes_are_escaped_after_transliteration(): void {
		$export = new Balikovna_Test_Export();
		$ascii_values = str_getcsv( trim( $export->encodeRow( array( '=1+1', '+1+1', '-1+1', '@SUM(1,1)' ) ) ), ';', '"', '' );
		foreach ( $ascii_values as $value ) {
			$this->assertStringStartsWith( "'", $value );
		}

		$unicode_values = str_getcsv(
			trim( $export->encodeRow( array( '＝HYPERLINK("https://example.test")', '＋1+1', '－1+1', '＠SUM(1,1)', '−1+1' ) ) ),
			';',
			'"',
			''
		);
		foreach ( $unicode_values as $value ) {
			$this->assertDoesNotMatchRegularExpression( '/^[=+\-@\t\r\n]/', $value );
		}
	}

	public function test_csv_phone_is_international_without_an_apostrophe(): void {
		$export = new Balikovna_Test_Export();
		$rows = $export->rows( $this->address_order() );
		$csv = str_getcsv( trim( $export->encodeRow( $rows[0] ) ), ';', '"', '' );
		$this->assertSame( '00420777123456', $csv[10] );
		$this->assertSame( '+420777123456', $rows[0][10] );
	}

	public function test_csv_phone_normalization_does_not_allow_formulas(): void {
		$export = new Balikovna_Test_Export();
		foreach ( array( '+1+1', '=1+1', '+420777123456' . "\n", '+420777123456;=1+1', '@SUM(1,1)' ) as $phone ) {
			$row = array_fill( 0, 15, '' );
			$row[10] = $phone;
			$csv = str_getcsv( trim( $export->encodeRow( $row ) ), ';', '"', '' );
			$this->assertSame( "'" . $phone, $csv[10] );
		}
		$row[0] = '+420777123456';
		$row[10] = '00420777123456';
		$csv = str_getcsv( trim( $export->encodeRow( $row ) ), ';', '"', '' );
		$this->assertSame( "'+420777123456", $csv[0] );
		$this->assertSame( '00420777123456', $csv[10] );
	}

	public function test_post_office_destination_uses_selected_office(): void {
		$export = new Balikovna_Test_Export();
		$order  = new WC_Order( array(), array(), array( 'shipping_address_1' => 'Customer street', 'shipping_postcode' => '60200', 'shipping_city' => 'Brno' ) );
		$point  = array( 'id' => 'P10003', 'name' => 'Depo Praha 701', 'street' => 'Sazecska 7', 'zip' => '10003', 'city' => 'Praha' );
		$this->assertSame( array( 'Sazecska 7', '10003', 'Praha' ), $export->destination( $order, $point, 'NP' ) );
		$this->assertSame( array( 'Balíkova', 'B10000', '' ), $export->destination( $order, array( 'id' => 'B10000' ), 'NB' ) );
		$this->assertSame( array( 'Customer street', '60200', 'Brno' ), $export->destination( $order, array(), 'DR' ) );
	}

	public function test_weight_uses_order_snapshot_when_product_no_longer_exists(): void {
		$export = new Balikovna_Test_Export();
		$order  = new WC_Order(
			array(),
			array(),
			array(),
			array( new WC_Order_Item_Product( array( Balikovna_WC\Order::META_UNIT_WEIGHT => '2.500000' ), 2, null ) )
		);
		$this->assertSame( '5.00', $export->weight( $order ) );
	}

	public function test_partial_weight_is_never_snapshotted_or_exported_as_complete(): void {
		$known = new class { public function get_weight() { return '2'; } };
		$unknown = new class { public function get_weight() { return ''; } };
		$shipping = new WC_Order_Item_Shipping( 'balikovna_na_adresu', '4' );
		$lines = array( new WC_Order_Item_Product( array(), 1, $known, 100 ), new WC_Order_Item_Product( array(), 1, $unknown, 100 ) );
		$order = $this->address_order( array(), array( $shipping ), $lines );
		$package = array( 'contents' => array( array( 'data' => $known, 'quantity' => 1, 'line_total' => 100 ), array( 'data' => $unknown, 'quantity' => 1, 'line_total' => 100 ) ) );
		Balikovna_WC\Order::instance()->add_shipping_item_metadata( $shipping, 0, $package, $order );
		Balikovna_WC\Order::instance()->snapshot_line_item_weight( $lines[1], 'unknown', array( 'data' => $unknown ), $order );
		$this->assertSame( '', $shipping->get_meta( Balikovna_WC\Order::META_PACKAGE_WEIGHT ) );
		$this->assertSame( '', $lines[1]->get_meta( Balikovna_WC\Order::META_UNIT_WEIGHT ) );
		$this->assertSame( '', ( new Balikovna_Test_Export() )->weight( $order ) );
		$this->assertInstanceOf( WP_Error::class, ( new Balikovna_Test_Export() )->rows( $order ) );
	}

	public function test_unknown_deleted_product_fails_but_virtual_snapshot_does_not_add_weight(): void {
		$known = new WC_Order_Item_Product( array( Balikovna_WC\Order::META_UNIT_WEIGHT => '2' ), 1 );
		$unknown = new WC_Order_Item_Product( array(), 1 );
		$this->assertSame( '', ( new Balikovna_Test_Export() )->weight( $this->address_order( array(), array(), array( $known, $unknown ) ) ) );
		$virtual = new WC_Order_Item_Product( array( Balikovna_WC\Order::META_REQUIRES_SHIPPING => 'no' ), 1 );
		$this->assertSame( '2.00', ( new Balikovna_Test_Export() )->weight( $this->address_order( array(), array(), array( $known, $virtual ) ) ) );
	}

	public function test_export_rechecks_service_limit_before_rounding_and_uses_contract_snapshot(): void {
		$export = new Balikovna_Test_Export();
		foreach ( array( '20', '15.004', 'INF' ) as $weight ) {
			$item = new WC_Order_Item_Shipping( 'balikovna_na_adresu', '4', array( Balikovna_WC\Order::META_PACKAGE_WEIGHT => $weight, Balikovna_WC\Order::META_PACKAGE_VALUE => '100' ) );
			$this->assertInstanceOf( WP_Error::class, $export->rows( $this->address_order( array(), array( $item ) ) ), $weight );
		}
		$item = new WC_Order_Item_Shipping( 'balikovna_plus', '4', array( Balikovna_WC\Order::META_PACKAGE_WEIGHT => '40', Balikovna_WC\Order::META_PACKAGE_VALUE => '100', Balikovna_WC\Order::META_MAX_WEIGHT => '50' ) );
		$order = $this->address_order( array(), array( $item ) );
		$this->assertSame( '40.00', $export->rows( $order )[0][5] );
		$item->delete_meta_data( Balikovna_WC\Order::META_MAX_WEIGHT );
		$this->assertInstanceOf( WP_Error::class, $export->rows( $order ) );
		$item->update_meta_data( Balikovna_WC\Order::META_MAX_WEIGHT, '500' );
		$item->update_meta_data( Balikovna_WC\Order::META_PACKAGE_WEIGHT, '60' );
		$this->assertInstanceOf( WP_Error::class, $export->rows( $order ) );
	}

	private function address_order( array $address = array(), array $shipping_items = array(), array $products = array() ) {
		if ( ! $shipping_items ) {
			$shipping_items = array( new WC_Order_Item_Shipping( 'balikovna_na_adresu', '4', array(
				Balikovna_WC\Order::META_PACKAGE_WEIGHT => '2',
				Balikovna_WC\Order::META_PACKAGE_VALUE => '100',
			) ) );
		}
		return new WC_Order( $shipping_items, array(), array_merge( array(
			'shipping_first_name' => 'Recipient',
			'shipping_last_name' => 'Customer',
			'shipping_address_1' => 'Delivery street 1',
			'shipping_postcode' => '10000',
			'shipping_city' => 'Praha',
			'shipping_country' => 'CZ',
			'billing_first_name' => 'Buyer',
			'billing_last_name' => 'Person',
			'billing_company' => 'Buyer Company',
			'billing_address_1' => 'Billing street 2',
			'billing_address_2' => 'Building 99',
			'billing_postcode' => '60200',
			'billing_city' => 'Brno',
			'billing_country' => 'CZ',
			'billing_email' => 'buyer@example.test',
			'billing_phone' => '+420777123456',
		), $address ), $products );
	}

	public function test_shipping_recipient_never_inherits_billing_company_or_address_line(): void {
		$rows = ( new Balikovna_Test_Export() )->rows( $this->address_order() );

		$this->assertIsArray( $rows );
		$this->assertSame( array( 'Customer', 'Recipient', 'Delivery street 1', '10000', 'Praha' ), array_slice( $rows[0], 0, 5 ) );
		$this->assertSame( 'F', $rows[0][13] );
	}

	public function test_missing_shipping_address_uses_billing_as_a_complete_address(): void {
		$order = $this->address_order( array(
			'shipping_first_name' => '', 'shipping_last_name' => '', 'shipping_address_1' => '',
			'shipping_postcode' => '', 'shipping_city' => '',
		) );
		$rows = ( new Balikovna_Test_Export() )->rows( $order );

		$this->assertIsArray( $rows );
		$this->assertSame( array( 'Buyer Company', '', 'Billing street 2 Building 99', '60200', 'Brno' ), array_slice( $rows[0], 0, 5 ) );
		$this->assertSame( 'P', $rows[0][13] );
	}

	public function test_partial_shipping_address_does_not_borrow_required_billing_fields(): void {
		$export = new Balikovna_Test_Export();
		foreach ( array( 'shipping_address_1', 'shipping_postcode', 'shipping_city', 'shipping_last_name', 'shipping_country' ) as $missing_field ) {
			$result = $export->rows( $this->address_order( array( $missing_field => '' ) ) );
			$this->assertInstanceOf( WP_Error::class, $result, $missing_field );
		}
	}

	public function test_changed_contents_recalculates_single_shipment_before_export(): void {
		$order = $this->address_order( array(), array(), array( new WC_Order_Item_Product( array( Balikovna_WC\Order::META_UNIT_WEIGHT => '2' ), 1, null, 100, 0 ) ) );
		$shipping = $order->get_shipping_methods();
		$shipping[0]->update_meta_data( Balikovna_WC\Order::META_CONTENTS_SIGNATURE, Balikovna_WC\Order::contents_signature( $order ) );
		$changed = $this->address_order( array(), $shipping, array( new WC_Order_Item_Product( array( Balikovna_WC\Order::META_UNIT_WEIGHT => '2' ), 3, null, 300, 0 ) ) );

		$rows = ( new Balikovna_Test_Export() )->rows( $changed );

		$this->assertIsArray( $rows );
		$this->assertSame( '6.00', $rows[0][5] );
		$this->assertSame( '300.00', $rows[0][6] );
	}

	public function test_changed_multi_package_contents_cannot_export_stale_or_whole_order_values(): void {
		foreach ( array( 'balikovna_na_adresu', 'flat_rate' ) as $second_method ) {
			$order = $this->address_order();
			$shipping = $order->get_shipping_methods();
			$shipping[0]->update_meta_data( Balikovna_WC\Order::META_CONTENTS_SIGNATURE, Balikovna_WC\Order::contents_signature( $order ) );
			$shipping[] = new WC_Order_Item_Shipping( $second_method, '5', array(
				Balikovna_WC\Order::META_PACKAGE_WEIGHT => '1',
				Balikovna_WC\Order::META_PACKAGE_VALUE => '50',
			) );
			$changed = $this->address_order( array(), $shipping, array( new WC_Order_Item_Product( array( Balikovna_WC\Order::META_UNIT_WEIGHT => '2' ), 3, null, 300, 0 ) ) );

			$this->assertInstanceOf( WP_Error::class, ( new Balikovna_Test_Export() )->rows( $changed ) );
		}
	}

	public function test_rows_use_per_shipment_value_and_company_subject(): void {
		$point = Balikovna_WC\Points::sanitize(
			array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' )
		);
		$first = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array(
				Balikovna_WC\Order::META_KEY            => $point,
				Balikovna_WC\Order::META_PACKAGE_WEIGHT => '2.5',
				Balikovna_WC\Order::META_PACKAGE_VALUE  => '200',
				Balikovna_WC\Order::META_DATA_VERSION   => Balikovna_WC\Order::DATA_VERSION,
			)
		);
		$second = new WC_Order_Item_Shipping(
			'balikovna_na_adresu',
			'5',
			array(
				Balikovna_WC\Order::META_PACKAGE_WEIGHT => '1.5',
				Balikovna_WC\Order::META_PACKAGE_VALUE  => '300',
				Balikovna_WC\Order::META_DATA_VERSION   => Balikovna_WC\Order::DATA_VERSION,
			)
		);
		$order = new WC_Order(
			array( $first, $second ),
			array(),
			array(
				'shipping_address_1' => 'Customer street 1',
				'shipping_postcode'  => '60200',
				'shipping_city'      => 'Brno',
				'shipping_country'   => 'CZ',
				'shipping_company'   => 'Example s.r.o.',
				'billing_email'      => 'office@example.test',
				'billing_phone'      => '+420 777 123 456',
				'payment_method'     => 'cod',
				'currency'           => 'CZK',
				'total'              => 549.60,
				'order_number'       => '123',
			)
		);

		$rows = ( new Balikovna_Test_Export() )->rows( $order );

		$this->assertIsArray( $rows );
		$this->assertSame( '200.00', $rows[0][6] );
		$this->assertSame( '300.00', $rows[1][6] );
		$this->assertSame( '550', $rows[0][7] );
		$this->assertSame( '', $rows[1][7] );
		$this->assertSame( 'Example s.r.o.', $rows[0][0] );
		$this->assertSame( '', $rows[0][1] );
		$this->assertSame( '+420777123456', $rows[0][10] );
		$this->assertSame( 'P', $rows[0][13] );
	}

	public function test_missing_pickup_point_fails_instead_of_skipping_row(): void {
		$item = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array(
				Balikovna_WC\Order::META_PACKAGE_WEIGHT => '2',
				Balikovna_WC\Order::META_PACKAGE_VALUE  => '200',
				Balikovna_WC\Order::META_DATA_VERSION   => Balikovna_WC\Order::DATA_VERSION,
			)
		);
		$order = new WC_Order(
			array( $item ),
			array(),
			array(
				'shipping_first_name' => 'Jan',
				'shipping_last_name'  => 'Novak',
				'billing_email'       => 'customer@example.test',
				'billing_phone'       => '+420777123456',
				'currency'            => 'CZK',
				'order_number'        => '124',
			)
		);

		$result = ( new Balikovna_Test_Export() )->rows( $order );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'balikovna_export_invalid_order', $result->get_error_code() );
		$this->assertStringContainsString( 'výdejní místo', $result->get_error_message() );
	}

	public function test_legacy_single_shipment_value_excludes_shipping_and_fees(): void {
		$item = new WC_Order_Item_Shipping(
			'balikovna_na_adresu',
			'4',
			array( Balikovna_WC\Order::META_PACKAGE_WEIGHT => '2' )
		);
		$order = new WC_Order(
			array( $item ),
			array(),
			array(
				'shipping_address_1' => 'Customer street 1',
				'shipping_postcode'  => '60200',
				'shipping_city'      => 'Brno',
				'shipping_country'   => 'CZ',
				'shipping_first_name' => 'Jan',
				'shipping_last_name' => 'Novak',
				'billing_email'      => 'customer@example.test',
				'billing_phone'      => '+420777123456',
				'currency'           => 'CZK',
				'total'              => 180,
				'order_number'       => '125',
			),
			array( new WC_Order_Item_Product( array(), 1, null, 100, 21 ) )
		);

		$rows = ( new Balikovna_Test_Export() )->rows( $order );

		$this->assertIsArray( $rows );
		$this->assertSame( '121.00', $rows[0][6] );
	}

	public function test_non_czk_order_is_rejected(): void {
		$item = new WC_Order_Item_Shipping( 'cp_do_ruky', '4' );
		$order = new WC_Order(
			array( $item ),
			array(),
			array( 'currency' => 'EUR', 'order_number' => '126' )
		);

		$result = ( new Balikovna_Test_Export() )->rows( $order );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertStringContainsString( 'CZK', $result->get_error_message() );
	}

	public function test_balikovna_plus_uses_snapshotted_contract_parcel_type(): void {
		$item = new WC_Order_Item_Shipping(
			'balikovna_plus',
			'4',
			array(
				Balikovna_WC\Order::META_PACKAGE_WEIGHT => '20',
				Balikovna_WC\Order::META_PACKAGE_VALUE  => '1000',
				Balikovna_WC\Order::META_PARCEL_TYPE    => 'DE',
				Balikovna_WC\Order::META_DATA_VERSION   => Balikovna_WC\Order::DATA_VERSION,
			)
		);
		$order = new WC_Order(
			array( $item ),
			array(),
			array(
				'shipping_address_1' => 'Customer street 1',
				'shipping_postcode'  => '60200',
				'shipping_city'      => 'Brno',
				'shipping_country'   => 'CZ',
				'shipping_first_name' => 'Jan',
				'shipping_last_name' => 'Novak',
				'billing_email'      => 'customer@example.test',
				'billing_phone'      => '+420777123456',
				'currency'           => 'CZK',
				'order_number'       => '127',
			)
		);

		$rows = ( new Balikovna_Test_Export() )->rows( $order );

		$this->assertIsArray( $rows );
		$this->assertSame( 'DE', $rows[0][12] );
	}
}
