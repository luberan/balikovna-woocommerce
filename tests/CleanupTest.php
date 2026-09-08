<?php

use Balikovna_WC\Cleanup;
use Balikovna_WC\Tracking_Scheduler;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-balikovna-cleanup.php';

final class CleanupTest extends TestCase {
	public function test_uninstall_removes_secrets_caches_and_jobs_but_keeps_order_history(): void {
		$GLOBALS['balikovna_test_options'] = array(
			'balikovna_wc_tracking_settings' => array( 'secret_key' => 'sensitive' ),
			'balikovna_wc_tracking_status_dictionary' => array( 'cached' ),
			'balikovna_wc_points_balikovny_v1_stale' => array( 'cached' ),
			'balikovna_wc_tracking_pending_batch' => array( 'orders' => array( 1 ) ),
			'woocommerce_balikovna_1_settings' => array( 'cost' => '79' ),
			'unrelated_option' => 'preserve',
		);
		$GLOBALS['balikovna_test_transients'] = array( 'balikovna_wc_points_balikovny_v1' => array( 'cached' ) );
		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$order = new WC_Order( array(), array( '_balikovna_point' => array( 'id' => 'B10000' ) ) );
		( new Tracking_Scheduler( function () {} ) )->ensure_scheduled();
		Tracking_Scheduler::schedule_continuation();
		Cleanup::site();
		$this->assertFalse( get_option( 'balikovna_wc_tracking_settings' ) );
		$this->assertFalse( get_option( 'balikovna_wc_points_balikovny_v1_stale' ) );
		$this->assertFalse( get_transient( 'balikovna_wc_points_balikovny_v1' ) );
		$this->assertSame( array(), $GLOBALS['balikovna_test_scheduled_actions'] );
		$this->assertSame( 'preserve', get_option( 'unrelated_option' ) );
		$this->assertSame( '79', get_option( 'woocommerce_balikovna_1_settings' )['cost'] );
		$this->assertSame( 'B10000', $order->get_meta( '_balikovna_point' )['id'] );
		Cleanup::site();
		$this->assertSame( 'preserve', get_option( 'unrelated_option' ) );
	}
}