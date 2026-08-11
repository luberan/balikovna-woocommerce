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
}

final class ShippingAndOrderTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['balikovna_test_wc']->session = new Balikovna_Test_Session();
	}

	public function test_weight_table_is_fail_closed(): void {
		$method = new Balikovna_Test_Shipping_Method();
		$this->assertSame( 119.0, $method->resolve( "5|79\n10|119\n15|159", 8 ) );
		$this->assertNull( $method->resolve( "5|79\n10|119\n15|159", 16 ) );
		$this->assertNull( $method->resolve( '', 1 ) );
		$this->assertNull( $method->resolve( 'invalid', 1 ) );
		$this->assertNull( $method->resolve( '5|-10', 1 ) );
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
