<?php

use Balikovna_WC\Blocks;
use Balikovna_WC\Checkout;
use Balikovna_WC\Order;
use Balikovna_WC\Points;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Blocks extends Blocks {
	public $register_called = false;

	public function register() {
		$this->register_called = true;
	}
}

final class LifecycleTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['balikovna_test_actions']     = array();
		$GLOBALS['balikovna_test_did_actions'] = array();
		$GLOBALS['balikovna_test_wc']->session = new Balikovna_Test_Session();
	}

	public function test_blocks_register_immediately_when_loaded_hook_already_ran(): void {
		$GLOBALS['balikovna_test_did_actions']['woocommerce_blocks_loaded'] = 1;
		$blocks = new Balikovna_Test_Blocks();
		$blocks->init();
		$this->assertTrue( $blocks->register_called );
	}

	public function test_checkout_selection_survives_until_cart_is_emptied(): void {
		WC()->session->set( Checkout::SESSION_KEY, array( 'kept-for-retry' ) );
		Checkout::instance()->init();
		$this->assertSame( array( 'kept-for-retry' ), WC()->session->get( Checkout::SESSION_KEY ) );
		$this->assertArrayNotHasKey( 'woocommerce_checkout_order_processed', $GLOBALS['balikovna_test_actions'] );
		$this->assertArrayHasKey( 'woocommerce_cart_emptied', $GLOBALS['balikovna_test_actions'] );

		foreach ( $GLOBALS['balikovna_test_actions']['woocommerce_cart_emptied'] as $action ) {
			call_user_func( $action[0] );
		}
		$this->assertNull( WC()->session->get( Checkout::SESSION_KEY ) );
	}

	public function test_pay_for_order_keeps_existing_shipping_point_without_cart_session(): void {
		$point = Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' ) );
		$item  = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array(
				Order::META_KEY          => $point,
				Order::META_PACKAGE_KEY  => '0',
				Order::META_RATE_ID      => 'balikovna:4',
				Order::META_DATA_VERSION => Order::DATA_VERSION,
			)
		);
		$order = new WC_Order(
			array( $item ),
			array(),
			array( 'billing_email' => 'customer@example.test', 'billing_phone' => '+420777123456' )
		);
		$request = new class() {
			public function get_method() {
				return 'POST';
			}

			public function get_route() {
				return '/wc/store/v1/checkout/123';
			}
		};

		( new Blocks() )->update_order_from_request( $order, $request );

		$this->assertSame( 'B10000', $item->get_meta( Order::META_KEY )['id'] );
		$this->assertSame( '0', $item->get_meta( Order::META_PACKAGE_KEY ) );
	}

	public function test_blocks_checkout_applies_widget_phone_to_order(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4' ) );
		$point = Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' ) );
		Checkout::set_session_selections(
			array(
				'0' => Checkout::with_recipient_phone(
					array(
						'packageKey' => '0',
						'rateId'     => 'balikovna:4',
						'serviceId'  => 'balikovna',
						'point'      => $point,
					),
					'+420777123456'
				),
			)
		);
		$item = new WC_Order_Item_Shipping(
			'balikovna',
			'4',
			array(
				Order::META_PACKAGE_KEY  => '0',
				Order::META_RATE_ID      => 'balikovna:4',
				Order::META_DATA_VERSION => Order::DATA_VERSION,
			)
		);
		$order = new WC_Order( array( $item ), array(), array( 'billing_email' => 'customer@example.test' ) );
		$request = new class() {
			public function get_method() {
				return 'POST';
			}

			public function get_route() {
				return '/wc/store/v1/checkout';
			}
		};

		( new Blocks() )->update_order_from_request( $order, $request );

		$this->assertSame( 'B10000', $item->get_meta( Order::META_KEY )['id'] );
		$this->assertSame( '+420777123456', $order->get_billing_phone() );
	}
}
