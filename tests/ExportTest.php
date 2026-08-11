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
