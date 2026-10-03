<?php

use Balikovna_WC\Blocks;
use Balikovna_WC\Blocks_Integration;
use Balikovna_WC\Checkout;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-balikovna-plugin.php';

final class Balikovna_Test_Integration_Registry {
	public $registered = array();

	public function register( $integration ) {
		$this->registered[ $integration->get_name() ] = $integration;
		return true;
	}

	public function is_registered( $name ) {
		return isset( $this->registered[ $name ] );
	}
}

final class ScriptLoadingTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['balikovna_test_actions']            = array();
		$GLOBALS['balikovna_test_enqueued_scripts']   = array();
		$GLOBALS['balikovna_test_registered_scripts'] = array();
		$GLOBALS['balikovna_test_localized_scripts']  = array();
		$GLOBALS['balikovna_test_enqueued_styles']    = array();
		$GLOBALS['balikovna_test_is_checkout']        = false;
		$GLOBALS['balikovna_test_is_cart']            = false;
		$GLOBALS['balikovna_test_wc']->session        = new Balikovna_Test_Session();
	}

	protected function tearDown(): void {
		$GLOBALS['balikovna_test_is_checkout'] = false;
		$GLOBALS['balikovna_test_is_cart']     = false;
	}

	public function test_block_picker_is_registered_through_the_woocommerce_integration_api(): void {
		$registry = new Balikovna_Test_Integration_Registry();
		$blocks   = new Blocks();

		$blocks->register_integration( $registry );
		$blocks->register_integration( $registry );

		$this->assertSame( array( Blocks::NS ), array_keys( $registry->registered ) );
		$integration = $registry->registered[ Blocks::NS ];
		$this->assertInstanceOf( Blocks_Integration::class, $integration );
		$integration->initialize();
		$script = $GLOBALS['balikovna_test_registered_scripts'][ Blocks_Integration::HANDLE ];
		$this->assertStringEndsWith( 'assets/js/checkout-block.js', $script['src'] );
		$this->assertContains( 'wc-blocks-checkout', $script['dependencies'] );
		$this->assertContains( 'wc-settings', $script['dependencies'] );
		$this->assertSame( array( Blocks_Integration::HANDLE ), $integration->get_script_handles() );
		$this->assertSame( array(), $integration->get_editor_script_handles() );
		$data = $integration->get_script_data();
		$this->assertSame( array( 'balikovna', 'cp_na_postu' ), array_keys( $data['services'] ) );
		$this->assertStringContainsString( 'type=BALIKOVNY', $data['services']['balikovna']['widgetUrl'] );
		$this->assertSame( Checkout::picker_i18n(), $data['i18n'] );
		$this->assertSame( array(), $GLOBALS['balikovna_test_enqueued_scripts'], 'WooCommerce enqueues the handle when the block renders.' );
		$this->assertSame( '<div>block</div>', $blocks->enqueue_style( '<div>block</div>' ) );
		$this->assertArrayHasKey( 'balikovna-wc', $GLOBALS['balikovna_test_enqueued_styles'] );
	}

	public function test_classic_picker_loads_only_with_the_classic_checkout_form(): void {
		$checkout = Checkout::instance();
		$checkout->init();
		$this->assertArrayHasKey( 'woocommerce_before_checkout_form', $GLOBALS['balikovna_test_actions'] );

		$GLOBALS['balikovna_test_is_cart'] = true;
		$checkout->enqueue();
		$this->assertArrayHasKey( 'balikovna-wc', $GLOBALS['balikovna_test_enqueued_styles'] );
		$this->assertSame( array(), $GLOBALS['balikovna_test_enqueued_scripts'], 'The cart page never loads the classic checkout script.' );

		$GLOBALS['balikovna_test_is_cart']     = false;
		$GLOBALS['balikovna_test_is_checkout'] = true;
		$checkout->enqueue();
		$this->assertSame( array(), $GLOBALS['balikovna_test_enqueued_scripts'], 'A checkout page alone does not reveal whether it is the classic form.' );

		do_action( 'woocommerce_before_checkout_form' );
		$this->assertSame( array( 'jquery', 'wc-checkout' ), $GLOBALS['balikovna_test_enqueued_scripts']['balikovna-wc']['dependencies'] );
		$config = $GLOBALS['balikovna_test_localized_scripts']['balikovna-wc']['BalikovnaWC'];
		$this->assertSame( '/?wc-ajax=balikovna_set_point', $config['ajaxUrl'] );
		$this->assertSame( Checkout::picker_services(), $config['services'] );
		$this->assertSame( Checkout::picker_i18n(), $config['i18n'] );
	}
}
