<?php

use Balikovna_WC\Cleanup;
use Balikovna_WC\Tracking_Scheduler;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-balikovna-cleanup.php';

final class CleanupTest extends TestCase {
	protected function setUp(): void {
		$this->reset_sites();
	}

	protected function tearDown(): void {
		$this->reset_sites();
	}

	private function reset_sites() {
		$GLOBALS['balikovna_test_sites']        = array();
		$GLOBALS['balikovna_test_blog_id']      = 1;
		$GLOBALS['balikovna_test_blog_stack']   = array();
		$GLOBALS['balikovna_test_site_queries'] = array();
		$GLOBALS['balikovna_test_cleared_cron'] = array();
		$GLOBALS['balikovna_test_options']      = array();
		$GLOBALS['balikovna_test_scheduled_actions'] = array();
	}

	private function action( $hook, array $args = array(), $group = Tracking_Scheduler::GROUP ) {
		return array( 'hook' => $hook, 'group' => $group, 'args' => $args, 'timestamp' => 1786521600 );
	}

	/**
	 * Three network sites; WooCommerce never ran on the third one.
	 */
	private function network() {
		$schema = array( 'schema-ActionScheduler_StoreSchema' => '7.0.1' );
		$GLOBALS['balikovna_test_sites'] = array(
			1 => array( 'options' => $schema + array( 'balikovna_wc_tracking_settings' => array( 'enabled' => true ) ), 'actions' => array( $this->action( Tracking_Scheduler::HOOK ), $this->action( 'other_plugin_hook', array(), '' ) ), 'cron' => array() ),
			2 => array( 'options' => $schema + array( 'balikovna_wc_tracking_settings' => array( 'enabled' => true ) ), 'actions' => array( $this->action( Tracking_Scheduler::CONTINUATION_HOOK ), $this->action( Tracking_Scheduler::POINTS_HOOK, array( 'BALIKOVNY' ) ) ), 'cron' => array() ),
			3 => array( 'options' => array( 'balikovna_wc_tracking_settings' => array( 'enabled' => true ) ), 'actions' => array( $this->action( Tracking_Scheduler::HOOK ) ), 'cron' => array() ),
		);
		balikovna_test_load_blog( 1 );
	}

	private function sites() {
		balikovna_test_store_blog();
		return $GLOBALS['balikovna_test_sites'];
	}

	public function test_network_deactivation_stops_scheduled_work_on_every_site(): void {
		$this->network();

		Cleanup::deactivate( true );

		$sites = $this->sites();
		$this->assertSame( array( $this->action( 'other_plugin_hook', array(), '' ) ), $sites[1]['actions'] );
		$this->assertSame( array(), $sites[2]['actions'] );
		$this->assertSame( array( $this->action( Tracking_Scheduler::HOOK ) ), $sites[3]['actions'], 'Action Scheduler is not queried on a site without its tables.' );
		foreach ( $sites as $site_id => $site ) {
			$this->assertSame( array( Cleanup::UPDATE_CRON_HOOK ), $site['cron'], 'Update checks stop on site ' . $site_id );
			$this->assertSame( array( 'enabled' => true ), $site['options']['balikovna_wc_tracking_settings'], 'Deactivation keeps settings.' );
		}
		$this->assertSame( 1, get_current_blog_id() );
		$this->assertSame( array(), $GLOBALS['balikovna_test_blog_stack'] );
	}

	public function test_site_deactivation_and_woocommerce_deactivation_keep_their_scope(): void {
		$this->network();

		Cleanup::deactivate( false );
		$sites = $this->sites();
		$this->assertSame( array( $this->action( 'other_plugin_hook', array(), '' ) ), $sites[1]['actions'] );
		$this->assertCount( 2, $sites[2]['actions'], 'Deactivating one site leaves the other sites alone.' );
		$this->assertSame( array(), $GLOBALS['balikovna_test_site_queries'] );

		Cleanup::woocommerce_deactivated( true );
		$sites = $this->sites();
		$this->assertSame( array(), $sites[2]['actions'] );
		$this->assertSame( array(), $sites[2]['cron'], 'Update checks continue while only WooCommerce is deactivated.' );
	}

	public function test_network_uninstall_cleans_every_site_without_querying_missing_tables(): void {
		$this->network();

		Cleanup::uninstall();

		$sites = $this->sites();
		foreach ( $sites as $site_id => $site ) {
			$this->assertArrayNotHasKey( 'balikovna_wc_tracking_settings', $site['options'], 'Settings removed on site ' . $site_id );
		}
		$this->assertSame( array( $this->action( Tracking_Scheduler::HOOK ) ), $sites[3]['actions'] );
		$this->assertSame( array(), $sites[2]['actions'] );
	}

	public function test_single_site_deactivation_ignores_the_network_flag(): void {
		$GLOBALS['balikovna_test_scheduled_actions'] = array( $this->action( Tracking_Scheduler::HOOK ) );

		Cleanup::deactivate( true );

		$this->assertSame( array(), $GLOBALS['balikovna_test_scheduled_actions'] );
		$this->assertSame( array( Cleanup::UPDATE_CRON_HOOK ), $GLOBALS['balikovna_test_cleared_cron'] );
		$this->assertSame( array(), $GLOBALS['balikovna_test_site_queries'] );
	}
	public function test_uninstall_removes_secrets_caches_and_jobs_but_keeps_order_history(): void {
		$GLOBALS['balikovna_test_options'] = array(
			'balikovna_wc_tracking_settings' => array( 'secret_key' => 'sensitive' ),
			'balikovna_wc_tracking_status_dictionary' => array( 'cached' ),
			'balikovna_wc_points_balikovny_v1_stale' => array( 'cached' ),
			'balikovna_wc_points_balikovny_v2' => array( 'updated' => time(), 'count' => 1, 'shards' => array( '10' ) ),
			'balikovna_wc_points_balikovny_v2_10' => array( 'B10000' => array( 'id' => 'B10000' ) ),
			'balikovna_wc_points_post_office_v2_07' => array( 'P07000' => array( 'id' => 'P07000' ) ),
			'balikovna_wc_tracking_pending_batch' => array( 'orders' => array( 1 ) ),
			'balikovna_wc_tracking_failed_orders' => array( 1 => array( 'count' => 1 ) ),
			'woocommerce_balikovna_1_settings' => array( 'cost' => '79' ),
			'unrelated_option' => 'preserve',
		);
		$GLOBALS['balikovna_test_transients'] = array(
			'balikovna_wc_points_balikovny_v1'             => array( 'cached' ),
			'balikovna_wc_points_balikovny_v2_retry_after' => time() + 60,
		);
		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$order = new WC_Order( array(), array( '_balikovna_point' => array( 'id' => 'B10000' ) ) );
		( new Tracking_Scheduler( function () {} ) )->ensure_scheduled();
		Tracking_Scheduler::schedule_continuation();
		as_schedule_single_action( time(), Tracking_Scheduler::POINTS_HOOK, array( 'BALIKOVNY' ), Tracking_Scheduler::GROUP, true );
		Cleanup::site();
		$this->assertFalse( get_option( 'balikovna_wc_tracking_settings' ) );
		$this->assertFalse( get_option( 'balikovna_wc_points_balikovny_v1_stale' ) );
		$this->assertFalse( get_transient( 'balikovna_wc_points_balikovny_v1' ) );
		$this->assertFalse( get_transient( 'balikovna_wc_points_balikovny_v2_retry_after' ) );
		foreach ( array( 'balikovna_wc_points_balikovny_v2', 'balikovna_wc_points_balikovny_v2_10', 'balikovna_wc_points_post_office_v2_07', 'balikovna_wc_tracking_failed_orders' ) as $name ) {
			$this->assertFalse( get_option( $name ), $name );
		}
		$this->assertSame( array(), $GLOBALS['balikovna_test_scheduled_actions'] );
		$this->assertContains( 'puc_cron_check_updates-balikovna-woocommerce', $GLOBALS['balikovna_test_cleared_cron'] );
		$this->assertSame( 'preserve', get_option( 'unrelated_option' ) );
		$this->assertSame( '79', get_option( 'woocommerce_balikovna_1_settings' )['cost'] );
		$this->assertSame( 'B10000', $order->get_meta( '_balikovna_point' )['id'] );
		Cleanup::site();
		$this->assertSame( 'preserve', get_option( 'unrelated_option' ) );
	}
}