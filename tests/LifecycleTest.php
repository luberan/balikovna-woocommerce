<?php

use Balikovna_WC\Blocks;
use Balikovna_WC\Checkout;
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
}
