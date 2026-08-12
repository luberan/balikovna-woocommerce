<?php

use Balikovna_WC\Order;
use Balikovna_WC\Points;
use Balikovna_WC\Shipping_Method_Base;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Shipping_Method extends Shipping_Method_Base {
	public function __construct() {}

	public function resolve( $table, $weight ) {
		$this->weight_table = $table;
		return $this->resolve_weight_cost( $weight );
	}

	public function cost_for( $package, $service_id = 'balikovna', $free_shipping_min = '', $max_weight_kg = '', $cost_type = 'flat' ) {
		$this->id                = $service_id;
		$this->service           = Balikovna_WC\Services::get( $service_id );
		$this->cost              = '79';
		$this->cost_type         = $cost_type;
		$this->free_shipping_min = $free_shipping_min;
		$this->max_weight_kg     = $max_weight_kg;
		return $this->calculate_cost( $package );
	}
}

final class Balikovna_Test_Product {
	private $weight;
	private $dimensions;

	public function __construct( $weight, array $dimensions ) {
		$this->weight     = $weight;
		$this->dimensions = $dimensions;
	}

	public function get_weight() { return $this->weight; }
	public function get_length() { return $this->dimensions[0]; }
	public function get_width() { return $this->dimensions[1]; }
	public function get_height() { return $this->dimensions[2]; }
}

final class Balikovna_Test_Cart {
	private $contents;

	public function __construct( array $contents ) {
		$this->contents = $contents;
	}

	public function get_cart() {
		return $this->contents;
	}
}

final class ShippingAndOrderTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['balikovna_test_wc']->session = new Balikovna_Test_Session();
		$GLOBALS['balikovna_test_wc']->cart    = null;
		$_POST = array();
	}

	public function test_weight_table_is_fail_closed(): void {
		$method = new Balikovna_Test_Shipping_Method();
		$this->assertSame( 119.0, $method->resolve( "5|79\n10|119\n15|159", 8 ) );
		$this->assertNull( $method->resolve( "5|79\n10|119\n15|159", 16 ) );
		$this->assertNull( $method->resolve( '', 1 ) );
		$this->assertNull( $method->resolve( 'invalid', 1 ) );
		$this->assertNull( $method->resolve( '5|-10', 1 ) );
	}

	public function test_balikovna_limits_apply_before_flat_and_free_pricing(): void {
		$method  = new Balikovna_Test_Shipping_Method();
		$package = function ( $product, $country = 'CZ', $quantity = 1 ) {
			return array(
				'destination' => array( 'country' => $country ),
				'contents'    => array(
					array(
						'data'       => $product,
						'quantity'   => $quantity,
						'line_total' => 2000,
						'line_tax'   => 0,
					),
				),
			);
		};

		$this->assertSame( 79.0, $method->cost_for( $package( new Balikovna_Test_Product( 10, array( 40, 30, 20 ) ) ) ) );
		$this->assertNull( $method->cost_for( $package( new Balikovna_Test_Product( 16, array( 40, 30, 20 ) ) ) ) );
		$this->assertNull( $method->cost_for( $package( new Balikovna_Test_Product( 16, array( 40, 30, 20 ) ) ), 'balikovna', '1000' ) );
		$this->assertNull( $method->cost_for( $package( new Balikovna_Test_Product( 10, array( 51, 30, 20 ) ) ) ) );
		$this->assertSame( 79.0, $method->cost_for( $package( new Balikovna_Test_Product( '', array( 40, 30, 20 ) ) ) ) );
		$this->assertNull( $method->cost_for( $package( new Balikovna_Test_Product( '', array( 40, 30, 20 ) ) ), 'balikovna', '', '', 'weight' ) );
		$this->assertSame( 79.0, $method->cost_for( $package( new Balikovna_Test_Product( 10, array( '', 30, 20 ) ) ) ) );
		$this->assertSame( 79.0, $method->cost_for( $package( new Balikovna_Test_Product( 10, array( '', 30, 20 ) ) ), 'balikovna_na_adresu' ) );
		$this->assertNull( $method->cost_for( $package( new Balikovna_Test_Product( 10, array( 51, '', 20 ) ) ) ) );
		$this->assertNull( $method->cost_for( $package( new Balikovna_Test_Product( 10, array( 40, 30, 20 ) ), 'SK' ) ) );
	}

	public function test_strict_metrics_filter_rejects_incomplete_balikovna_products(): void {
		add_filter(
			'balikovna_wc_require_complete_package_metrics',
			function () {
				return true;
			}
		);
		$method  = new Balikovna_Test_Shipping_Method();
		$package = array(
			'destination' => array( 'country' => 'CZ' ),
			'contents'    => array(
				array(
					'data'       => new Balikovna_Test_Product( 10, array( 40, '', 20 ) ),
					'quantity'   => 1,
					'line_total' => 1000,
					'line_tax'   => 210,
				),
			),
		);

		try {
			$this->assertNull( $method->cost_for( $package, 'balikovna' ) );
			$this->assertNull( $method->cost_for( $package, 'balikovna_na_adresu' ) );
			$this->assertNull( $method->cost_for( $package, 'balikovna_plus' ) );
		} finally {
			remove_all_filters( 'balikovna_wc_require_complete_package_metrics' );
		}
	}

	public function test_incomplete_item_cannot_hide_known_limit_violation_in_mixed_package(): void {
		$method  = new Balikovna_Test_Shipping_Method();
		$package = array(
			'destination' => array( 'country' => 'CZ' ),
			'contents'    => array(
				array(
					'data'       => new Balikovna_Test_Product( 5, array( 40, 30, 20 ) ),
					'quantity'   => 2,
					'line_total' => 1000,
					'line_tax'   => 210,
				),
				array(
					'data'       => new Balikovna_Test_Product( '', array( 51, '', 20 ) ),
					'quantity'   => 1,
					'line_total' => 500,
					'line_tax'   => 105,
				),
			),
		);

		$this->assertNull( $method->cost_for( $package, 'balikovna' ) );
		$this->assertNull( $method->cost_for( $package, 'balikovna_na_adresu' ) );
	}

	public function test_free_shipping_threshold_uses_the_whole_cart_across_packages(): void {
		$method  = new Balikovna_Test_Shipping_Method();
		$product = new Balikovna_Test_Product( 5, array( 20, 15, 10 ) );
		$item    = array(
			'data'       => $product,
			'quantity'   => 1,
			'line_total' => 600,
			'line_tax'   => 0,
		);
		$package = array(
			'destination' => array( 'country' => 'CZ' ),
			'contents'    => array( $item ),
		);

		$this->assertSame( 79.0, $method->cost_for( $package, 'balikovna', '1000' ) );
		$GLOBALS['balikovna_test_wc']->cart = new Balikovna_Test_Cart( array( $item, $item ) );
		$this->assertSame( 0.0, $method->cost_for( $package, 'balikovna', '1000' ) );
	}

	public function test_balikovna_plus_enforces_standard_contract_and_dimension_limits(): void {
		$method  = new Balikovna_Test_Shipping_Method();
		$package = function ( $weight, array $dimensions ) {
			return array(
				'destination' => array( 'country' => 'CZ' ),
				'contents'    => array(
					array(
						'data'       => new Balikovna_Test_Product( $weight, $dimensions ),
						'quantity'   => 1,
						'line_total' => 1000,
						'line_tax'   => 210,
					),
				),
			);
		};

		$this->assertSame( 79.0, $method->cost_for( $package( 31.5, array( 150, 80, 60 ) ), 'balikovna_plus' ) );
		$this->assertNull( $method->cost_for( $package( 32, array( 150, 80, 60 ) ), 'balikovna_plus' ) );
		$this->assertSame( 79.0, $method->cost_for( $package( 50, array( 150, 80, 60 ) ), 'balikovna_plus', '', '50' ) );
		$this->assertNull( $method->cost_for( $package( 50.1, array( 150, 80, 60 ) ), 'balikovna_plus', '', '50' ) );
		$this->assertNull( $method->cost_for( $package( 20, array( 180, 80, 50 ) ), 'balikovna_plus' ) );
		$this->assertNull( $method->cost_for( $package( 20, array( 201, 50, 40 ) ), 'balikovna_plus' ) );
	}

	public function test_balikovna_requires_valid_recipient_contact(): void {
		$valid = Balikovna_WC\Services::recipient_contact_errors(
			array( 'balikovna' ),
			'customer@example.test',
			'+420 777 123 456'
		);
		$invalid = Balikovna_WC\Services::recipient_contact_errors(
			array( 'balikovna_na_adresu' ),
			'not-an-email',
			'777123456'
		);

		$this->assertSame( array(), $valid );
		$this->assertArrayHasKey( 'balikovna_email_required', $invalid );
		$this->assertArrayHasKey( 'balikovna_phone_required', $invalid );
	}

	public function test_each_shipping_item_retains_its_own_point(): void {
		$balikovna = Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' ) );
		$postOffice = Points::sanitize( array( 'id' => 'P10003', 'name' => 'Depo Praha', 'type' => 'POST_OFFICE' ) );
		$order = new WC_Order(
			array(
				new WC_Order_Item_Shipping( 'balikovna', '4', array( Order::META_KEY => $balikovna, Order::META_PACKAGE_KEY => '0' ) ),
				new WC_Order_Item_Shipping( 'cp_na_postu', '7', array( Order::META_KEY => $postOffice, Order::META_PACKAGE_KEY => '1' ) ),
			)
		);

		$shipments = Order::get_shipments( $order );
		$this->assertCount( 2, $shipments );
		$this->assertSame( 'B10000', $shipments[0]['point']['id'] );
		$this->assertSame( 'P10003', $shipments[1]['point']['id'] );
	}

	public function test_shipping_item_snapshots_package_contents_value_without_shipping(): void {
		$item    = new WC_Order_Item_Shipping( 'balikovna_na_adresu', '4' );
		$package = array(
			'contents' => array(
				array(
					'data'       => new Balikovna_Test_Product( 2, array( 20, 15, 10 ) ),
					'quantity'   => 2,
					'line_total' => 200,
					'line_tax'   => 42,
				),
			),
		);

		Order::instance()->add_shipping_item_metadata( $item, '0', $package, new WC_Order() );

		$this->assertSame( '242.00', $item->get_meta( Order::META_PACKAGE_VALUE ) );
		$this->assertSame( '4.000000', $item->get_meta( Order::META_PACKAGE_WEIGHT ) );
	}

	public function test_stale_order_meta_is_ignored_for_unrelated_shipping_item(): void {
		$order = new WC_Order(
			array( new WC_Order_Item_Shipping( 'flat_rate', '2' ) ),
			array(
				Order::META_KEY       => array( 'id' => 'B10000', 'name' => 'Old point' ),
				'_balikovna_service' => 'balikovna',
			)
		);
		$this->assertSame( array(), Order::get_shipments( $order ) );
	}

	public function test_stale_plus_parcel_type_is_removed_after_shipping_method_change(): void {
		$item = new WC_Order_Item_Shipping(
			'cp_do_ruky',
			'4',
			array(
				Order::META_PARCEL_TYPE    => 'DE',
				Order::META_TRACKING_NUMBER => 'DR1234567890E',
			)
		);

		Order::instance()->remove_stale_shipping_metadata( $item, null );

		$this->assertSame( '', $item->get_meta( Order::META_PARCEL_TYPE ) );
		$this->assertSame( 'DR1234567890E', $item->get_meta( Order::META_TRACKING_NUMBER ) );
		$this->assertSame( 'DR', Order::get_shipments( new WC_Order( array( $item ) ) )[0]['parcelType'] );
	}

	public function test_legacy_point_is_preserved_when_summary_is_first_synchronized(): void {
		$order = new WC_Order(
			array( new WC_Order_Item_Shipping( 'balikovna', '4' ) ),
			array(
				Order::META_KEY       => array( 'id' => 'B10000', 'name' => 'Legacy point' ),
				'_balikovna_service' => 'balikovna',
			)
		);
		Order::sync_order_summary( $order );
		$this->assertSame( 'B10000', $order->get_meta( '_balikovna_point_id' ) );
	}

	public function test_plain_text_email_does_not_emit_html_entities(): void {
		$point = Points::sanitize( array( 'id' => 'B10000', 'name' => 'A & B', 'type' => 'BALIKOVNY' ) );
		$order = new WC_Order( array( new WC_Order_Item_Shipping( 'balikovna', '4', array( Order::META_KEY => $point ) ) ) );
		ob_start();
		Order::instance()->email_after_order_table( $order, false, true, null );
		$output = ob_get_clean();
		$this->assertStringContainsString( 'A & B', $output );
		$this->assertStringNotContainsString( '&amp;', $output );
	}

	public function test_manual_tracking_is_saved_per_shipping_item(): void {
		$first  = new WC_Order_Item_Shipping(
			'balikovna_plus',
			'4',
			array(
				Order::META_TRACKING_NUMBER => 'DR0000000000A',
				Order::META_STATUS_CODE     => '44/00',
				Order::META_STATUS_LABEL    => 'V přepravě',
			),
			10
		);
		$second = new WC_Order_Item_Shipping( 'cp_do_ruky', '5', array( Order::META_TRACKING_NUMBER => 'DR1111111111C' ), 11 );
		$order  = new WC_Order( array( $first, $second ) );
		$_POST  = array(
			Order::TRACKING_NONCE_NAME => 'valid-nonce',
			'balikovna_tracking_numbers' => array(
				'10' => ' dr 1234567890 e ',
			),
		);

		Order::instance()->save_tracking_numbers( 123, $order );

		$this->assertSame( 'DR1234567890E', $first->get_meta( Order::META_TRACKING_NUMBER ) );
		$this->assertSame( '', $first->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( '', $first->get_meta( Order::META_STATUS_LABEL ) );
		$this->assertSame( 'DR1111111111C', $second->get_meta( Order::META_TRACKING_NUMBER ) );
	}

	public function test_removing_tracking_number_clears_stale_status(): void {
		$item = new WC_Order_Item_Shipping(
			'cp_na_postu',
			'7',
			array(
				Order::META_TRACKING_NUMBER => 'NP1234567890A',
				Order::META_STATUS_CODE     => '81/00',
				Order::META_STATUS_LABEL    => 'Uloženo',
			),
			10
		);
		$_POST = array(
			Order::TRACKING_NONCE_NAME => 'valid-nonce',
			'balikovna_tracking_numbers' => array( '10' => '' ),
		);

		Order::instance()->save_tracking_numbers( 123, new WC_Order( array( $item ) ) );

		$this->assertSame( '', $item->get_meta( Order::META_TRACKING_NUMBER ) );
		$this->assertSame( '', $item->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( '', $item->get_meta( Order::META_STATUS_LABEL ) );
	}

	public function test_shipping_item_crud_change_clears_status_from_previous_tracking_number(): void {
		$item = new WC_Order_Item_Shipping(
			'cp_do_ruky',
			'7',
			array(
				Order::META_TRACKING_NUMBER        => 'DR1234567890E',
				Order::META_STATUS_TRACKING_NUMBER => 'DR0000000000A',
				Order::META_STATUS_CODE            => '91/00',
				Order::META_STATUS_LABEL           => 'DORUČENO',
			),
			10
		);

		Order::instance()->remove_stale_shipping_metadata( $item, null );

		$this->assertSame( 'DR1234567890E', $item->get_meta( Order::META_TRACKING_NUMBER ) );
		$this->assertSame( '', $item->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( '', $item->get_meta( Order::META_STATUS_LABEL ) );
	}

	public function test_shipping_item_crud_change_clears_failed_attempt_metadata(): void {
		$item = new WC_Order_Item_Shipping(
			'cp_do_ruky',
			'7',
			array(
				Order::META_TRACKING_NUMBER        => 'DR1234567890E',
				Order::META_STATUS_TRACKING_NUMBER => 'DR0000000000A',
				Order::META_STATUS_ATTEMPTED_AT    => 1786521600,
			),
			10
		);

		Order::instance()->remove_stale_shipping_metadata( $item, null );

		$this->assertSame( '', $item->get_meta( Order::META_STATUS_TRACKING_NUMBER ) );
		$this->assertSame( '', $item->get_meta( Order::META_STATUS_ATTEMPTED_AT ) );
	}

	public function test_invalid_manual_tracking_does_not_replace_existing_number(): void {
		$item  = new WC_Order_Item_Shipping( 'balikovna_plus', '4', array( Order::META_TRACKING_NUMBER => 'DR1111111111C' ), 10 );
		$order = new WC_Order( array( $item ) );
		$_POST = array(
			Order::TRACKING_NONCE_NAME => 'valid-nonce',
			'balikovna_tracking_numbers' => array( '10' => 'https://example.test/?parcel=1' ),
		);

		Order::instance()->save_tracking_numbers( 123, $order );

		$this->assertSame( 'DR1111111111C', $item->get_meta( Order::META_TRACKING_NUMBER ) );
	}

	public function test_plain_text_email_contains_manual_tracking_link_for_address_service(): void {
		$item  = new WC_Order_Item_Shipping( 'balikovna_plus', '4', array( Order::META_TRACKING_NUMBER => 'DR1234567890E' ) );
		$order = new WC_Order( array( $item ) );

		ob_start();
		Order::instance()->email_after_order_table( $order, false, true, null );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'DR1234567890E', $output );
		$this->assertStringContainsString( 'parcelNumbers=DR1234567890E', $output );
	}

	public function test_order_admin_displays_per_shipment_status_and_localized_times(): void {
		$item = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array(
				Order::META_TRACKING_NUMBER  => 'BA1234567890A',
				Order::META_STATUS_CODE      => '44/01',
				Order::META_STATUS_LABEL     => 'V PŘEPRAVĚ',
				Order::META_STATUS_EVENT_AT  => '2026-08-12T10:00:00+02:00',
				Order::META_STATUS_CHECKED_AT => 1786521600,
			),
			10
		);

		ob_start();
		Order::instance()->admin_after_shipping( new WC_Order( array( $item ) ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'BA1234567890A', $output );
		$this->assertStringContainsString( 'Stav zásilky', $output );
		$this->assertStringContainsString( 'V PŘEPRAVĚ', $output );
		$this->assertStringContainsString( 'Čas poslední změny', $output );
		$this->assertStringContainsString( '12. 8. 2026 10:00', $output );
		$this->assertStringContainsString( 'Naposledy zkontrolováno', $output );
		$this->assertStringContainsString( 'Sledovat zásilku', $output );
	}

	public function test_modern_summary_never_fills_a_second_shipping_item(): void {
		$point = Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' ) );
		$order = new WC_Order(
			array(
				new WC_Order_Item_Shipping( 'balikovna', '4', array( Order::META_KEY => $point, Order::META_DATA_VERSION => Order::DATA_VERSION ) ),
				new WC_Order_Item_Shipping( 'balikovna', '5', array( Order::META_DATA_VERSION => Order::DATA_VERSION ) ),
			),
			array( Order::META_KEY => $point, '_balikovna_service' => 'balikovna' )
		);
		Order::sync_order_summary( $order, false );
		$shipments = Order::get_shipments( $order );
		$this->assertSame( 'B10000', $shipments[0]['point']['id'] );
		$this->assertSame( array(), $shipments[1]['point'] );
		$this->assertSame( '', $order->get_meta( Order::META_KEY ) );
	}

	public function test_live_sync_without_selection_removes_item_and_summary_point(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4' ) );
		$point = Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' ) );
		$item = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array(
				Order::META_KEY          => $point,
				Order::META_PACKAGE_KEY  => '0',
				Order::META_RATE_ID      => 'balikovna:4',
				Order::META_DATA_VERSION => Order::DATA_VERSION,
			)
		);
		$order = new WC_Order( array( $item ), array( Order::META_KEY => $point, '_balikovna_service' => 'balikovna' ) );
		Order::sync_shipping_points( $order );
		$this->assertSame( '', $item->get_meta( Order::META_KEY ) );
		$this->assertSame( '', $order->get_meta( Order::META_KEY ) );
		$this->assertSame( array(), Order::get_shipments( $order )[0]['point'] );
	}

	public function test_same_rate_id_never_reuses_the_second_package_selection(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4', 'balikovna:4' ) );
		$point = Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' ) );
		Balikovna_WC\Checkout::set_session_selections(
			array(
				'1' => array( 'packageKey' => '1', 'rateId' => 'balikovna:4', 'serviceId' => 'balikovna', 'point' => $point ),
			)
		);
		$first = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array( Order::META_PACKAGE_KEY => '0', Order::META_RATE_ID => 'balikovna:4', Order::META_DATA_VERSION => Order::DATA_VERSION )
		);
		$second = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array( Order::META_PACKAGE_KEY => '1', Order::META_RATE_ID => 'balikovna:4', Order::META_DATA_VERSION => Order::DATA_VERSION )
		);
		$order = new WC_Order( array( $first, $second ) );
		Order::sync_shipping_points( $order );

		$this->assertSame( '0', $first->get_meta( Order::META_PACKAGE_KEY ) );
		$this->assertSame( '', $first->get_meta( Order::META_KEY ) );
		$this->assertSame( '1', $second->get_meta( Order::META_PACKAGE_KEY ) );
		$this->assertSame( 'B10000', $second->get_meta( Order::META_KEY )['id'] );
	}

	public function test_duplicate_explicit_package_key_is_not_remapped_to_another_package(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4', 'balikovna:4' ) );
		$point = Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' ) );
		Balikovna_WC\Checkout::set_session_selections(
			array(
				'1' => array( 'packageKey' => '1', 'rateId' => 'balikovna:4', 'serviceId' => 'balikovna', 'point' => $point ),
			)
		);
		$first = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array( Order::META_PACKAGE_KEY => '0', Order::META_RATE_ID => 'balikovna:4', Order::META_DATA_VERSION => Order::DATA_VERSION )
		);
		$duplicate = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array( Order::META_PACKAGE_KEY => '0', Order::META_RATE_ID => 'balikovna:4', Order::META_DATA_VERSION => Order::DATA_VERSION )
		);
		$order = new WC_Order( array( $first, $duplicate ) );
		Order::sync_shipping_points( $order );

		$this->assertSame( '0', $first->get_meta( Order::META_PACKAGE_KEY ) );
		$this->assertSame( '', $first->get_meta( Order::META_KEY ) );
		$this->assertSame( '', $duplicate->get_meta( Order::META_PACKAGE_KEY ) );
		$this->assertSame( '', $duplicate->get_meta( Order::META_KEY ) );
	}
}
