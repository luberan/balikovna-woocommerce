<?php

use Balikovna_WC\Order;
use Balikovna_WC\Points;
use Balikovna_WC\Privacy;
use PHPUnit\Framework\TestCase;

final class PrivacyTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['balikovna_test_filters'] = array();
		$GLOBALS['balikovna_test_actions'] = array();
	}

	private function order() {
		$point  = Points::sanitize(
			array(
				'id'     => 'B10000',
				'name'   => 'Balíkovna Praha',
				'street' => 'Sazečská 7',
				'city'   => 'Praha',
				'zip'    => '10003',
				'type'   => 'BALIKOVNY',
			)
		);
		$pickup = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array(
				Order::META_KEY                    => $point,
				Order::META_TRACKING_NUMBER        => 'BA1234567890A',
				Order::META_STATUS_TRACKING_NUMBER => 'BA1234567890A',
				Order::META_STATUS_CODE            => '91/00',
				Order::META_STATUS_LABEL           => 'DORUČENO',
				Order::META_PACKAGE_WEIGHT         => '2.000000',
			),
			10
		);
		$other  = new WC_Order_Item_Shipping( 'flat_rate', '9', array( 'carrier_reference' => 'kept' ), 11 );
		$order  = new WC_Order( array( $pickup, $other ) );
		Order::sync_order_summary( $order, false );
		return array( $order, $pickup, $other );
	}

	public function test_hooks_are_registered(): void {
		Privacy::init();

		$this->assertArrayHasKey( 'admin_init', $GLOBALS['balikovna_test_actions'] );
		$this->assertArrayHasKey( 'woocommerce_privacy_remove_order_personal_data', $GLOBALS['balikovna_test_actions'] );
		$this->assertArrayHasKey( 'woocommerce_privacy_export_order_personal_data', $GLOBALS['balikovna_test_filters'] );
	}

	public function test_privacy_policy_text_names_the_third_party_map_and_stored_data(): void {
		$GLOBALS['balikovna_test_privacy_policy'] = array();

		Privacy::add_policy_content();

		$text = $GLOBALS['balikovna_test_privacy_policy']['Balíkovna for WooCommerce'] ?? '';
		$this->assertStringContainsString( 'b2c.cpost.cz', $text );
		$this->assertStringContainsString( 'podací číslo', $text );
		$this->assertStringStartsWith( '<p>', $text );
	}

	public function test_export_adds_the_pickup_point_and_tracking_number(): void {
		list( $order ) = $this->order();
		$existing      = array(
			array(
				'name'  => 'Order Number',
				'value' => '1',
			),
		);

		$exported = Privacy::export_order_data( $existing, $order );

		$this->assertSame( $existing[0], $exported[0] );
		$this->assertSame( array( 'Výdejní místo', 'Podací číslo' ), array_column( array_slice( $exported, 1 ), 'name' ) );
		$this->assertSame( 'Balíkovna Praha (#B10000), Sazečská 7, 10003 Praha', $exported[1]['value'] );
		$this->assertSame( 'BA1234567890A', $exported[2]['value'] );
		$this->assertSame( $existing, Privacy::export_order_data( $existing, new stdClass() ) );
		$this->assertSame( 'unexpected', Privacy::export_order_data( 'unexpected', $order ) );
	}

	public function test_erasure_removes_the_point_and_tracking_but_keeps_other_shipment_data(): void {
		list( $order, $pickup, $other ) = $this->order();
		$this->assertSame( 'B10000', $order->get_meta( '_balikovna_point_id' ) );

		Privacy::remove_order_data( $order );
		Privacy::remove_order_data( new stdClass() );

		foreach ( array( Order::META_KEY, Order::META_TRACKING_NUMBER, Order::META_STATUS_TRACKING_NUMBER, Order::META_STATUS_CODE, Order::META_STATUS_LABEL ) as $meta_key ) {
			$this->assertSame( '', $pickup->get_meta( $meta_key ), $meta_key );
		}
		$this->assertSame( '2.000000', $pickup->get_meta( Order::META_PACKAGE_WEIGHT ), 'Data that does not identify the customer stays.' );
		$this->assertSame( 1, $pickup->save_count );
		foreach ( array( Order::META_KEY, '_balikovna_point_id', '_balikovna_point_name', '_balikovna_points' ) as $meta_key ) {
			$this->assertSame( '', $order->get_meta( $meta_key ), $meta_key );
		}
		$this->assertSame( 'kept', $other->get_meta( 'carrier_reference' ), 'Shipments of other carriers are not touched.' );
		$this->assertSame( 0, $other->save_count );
		$this->assertSame( array(), Privacy::export_order_data( array(), $order ) );
	}
}
