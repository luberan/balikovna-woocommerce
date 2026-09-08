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

	public static function site() {
		Tracking_Scheduler::unschedule();
		wp_clear_scheduled_hook( 'check_plugin_updates-balikovna-woocommerce' );
		foreach (
			array(
				'balikovna_wc_tracking_settings',
				'balikovna_wc_tracking_status_dictionary',
				'balikovna_wc_tracking_sync_lock',
				'balikovna_wc_tracking_diagnostics',
				'balikovna_wc_tracking_pending_batch',
				'balikovna_wc_tracking_order_page',
				'external_updates-balikovna-woocommerce',
			) as $name
		) {
			delete_option( $name );
		}
		foreach ( array( 'balikovny', 'post_office' ) as $type ) {
			$key = 'balikovna_wc_points_' . $type . '_v1';
			delete_transient( $key );
			delete_transient( $key . '_retry_after' );
			delete_option( $key . '_stale' );
			delete_option( $key . '_refresh_lock' );
		}
	}

	public static function uninstall() {
		delete_site_option( 'external_updates-balikovna-woocommerce' );
		if ( ! is_multisite() ) {
			self::site();
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
					self::site();
				} finally {
					restore_current_blog();
				}
			}
			$offset += count( $sites );
		} while ( 100 === count( $sites ) );
	}
}
