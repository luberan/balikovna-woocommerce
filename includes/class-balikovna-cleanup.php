<?php
/**
 * Remove credentials and operational caches without deleting order history.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-balikovna-tracking-scheduler.php';

class Cleanup {

	// Plugin Update Checker 5 names its cron hook puc_cron_check_updates-{slug}.
	const UPDATE_CRON_HOOK = 'puc_cron_check_updates-balikovna-woocommerce';

	/**
	 * Stop scheduled work when the plugin is deactivated.
	 *
	 * @param bool $network_wide Whether the plugin is deactivated for every site of a network.
	 */
	public static function deactivate( $network_wide = false ) {
		self::for_sites(
			$network_wide,
			function () {
				self::unschedule();
				wp_clear_scheduled_hook( self::UPDATE_CRON_HOOK );
			}
		);
	}

	/**
	 * Stop synchronization when WooCommerce, which provides Action Scheduler, is deactivated.
	 *
	 * @param bool $network_wide Whether WooCommerce is deactivated for every site of a network.
	 */
	public static function woocommerce_deactivated( $network_wide = false ) {
		self::for_sites(
			$network_wide,
			function () {
				self::unschedule();
			}
		);
	}

	public static function site() {
		self::unschedule();
		wp_clear_scheduled_hook( self::UPDATE_CRON_HOOK );
		foreach (
			array(
				'balikovna_wc_tracking_settings',
				'balikovna_wc_tracking_status_dictionary',
				'balikovna_wc_tracking_sync_lock',
				'balikovna_wc_tracking_diagnostics',
				'balikovna_wc_tracking_pending_batch',
				'balikovna_wc_tracking_failed_orders',
				'balikovna_wc_tracking_order_page',
				'external_updates-balikovna-woocommerce',
			) as $name
		) {
			delete_option( $name );
		}
		foreach ( array( 'balikovny', 'post_office' ) as $type ) {
			foreach ( array( 'v1', 'v2' ) as $version ) {
				$key = 'balikovna_wc_points_' . $type . '_' . $version;
				delete_transient( $key );
				delete_transient( $key . '_retry_after' );
				delete_option( $key );
				delete_option( $key . '_stale' );
				delete_option( $key . '_refresh_lock' );
			}
			for ( $shard = 0; $shard < 100; ++$shard ) {
				delete_option( sprintf( 'balikovna_wc_points_%s_v2_%02d', $type, $shard ) );
			}
		}
	}

	public static function uninstall() {
		delete_site_option( 'external_updates-balikovna-woocommerce' );
		self::for_sites(
			true,
			function () {
				self::site();
			}
		);
	}

	private static function unschedule() {
		// Sites where WooCommerce never ran have no Action Scheduler tables to query.
		if ( is_multisite() && false === get_option( 'schema-ActionScheduler_StoreSchema', false ) ) {
			return;
		}
		Tracking_Scheduler::unschedule();
	}

	/**
	 * Run a callback for the current site, or for every site when acting network-wide.
	 *
	 * @param bool     $network_wide Whether to visit every site of a multisite network.
	 * @param callable $callback     Work for one site.
	 */
	private static function for_sites( $network_wide, callable $callback ) {
		if ( ! $network_wide || ! is_multisite() ) {
			$callback();
			return;
		}
		$offset = 0;
		do {
			$sites = get_sites(
				array(
					'fields' => 'ids',
					'number' => 100,
					'offset' => $offset,
				)
			);
			if ( ! is_array( $sites ) ) {
				break;
			}
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				try {
					$callback();
				} finally {
					restore_current_blog();
				}
			}
			$offset += count( $sites );
		} while ( 100 === count( $sites ) );
	}
}
