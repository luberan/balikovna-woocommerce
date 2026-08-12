<?php
/**
 * Action Scheduler integration for shipment tracking.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Tracking_Scheduler {

	const HOOK     = 'balikovna_wc_sync_shipment_statuses';
	const GROUP    = 'balikovna-woocommerce';
	const INTERVAL = 30 * MINUTE_IN_SECONDS;

	private $callback;

	public function __construct( $callback ) {
		$this->callback = $callback;
	}

	public function init() {
		add_action( self::HOOK, $this->callback );
		add_action( 'action_scheduler_init', array( $this, 'ensure_scheduled' ) );
		if ( did_action( 'action_scheduler_init' ) ) {
			$this->ensure_scheduled();
		}
	}

	public function ensure_scheduled() {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		$scheduled = function_exists( 'as_has_scheduled_action' )
			? as_has_scheduled_action( self::HOOK, array(), self::GROUP )
			: ( function_exists( 'as_next_scheduled_action' ) && as_next_scheduled_action( self::HOOK, array(), self::GROUP ) );
		if ( $scheduled ) {
			return;
		}

		$interval = max(
			15 * MINUTE_IN_SECONDS,
			(int) apply_filters( 'balikovna_wc_tracking_interval', self::INTERVAL )
		);
		as_schedule_recurring_action(
			time() + $interval,
			$interval,
			self::HOOK,
			array(),
			self::GROUP,
			true
		);
	}

	public static function next_scheduled() {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return 0;
		}
		$next = as_next_scheduled_action( self::HOOK, array(), self::GROUP );
		return is_numeric( $next ) && (int) $next > 1 ? (int) $next : 0;
	}

	public static function unschedule() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			self::load_action_scheduler();
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
	}

	private static function load_action_scheduler() {
		$paths = array();
		if ( defined( 'WC_ABSPATH' ) ) {
			$paths[] = WC_ABSPATH . 'packages/action-scheduler/action-scheduler.php';
		}
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$paths[] = WP_PLUGIN_DIR . '/woocommerce/packages/action-scheduler/action-scheduler.php';
			$paths[] = WP_PLUGIN_DIR . '/woocommerce/vendor/woocommerce/action-scheduler/action-scheduler.php';
		}
		foreach ( array_unique( $paths ) as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				break;
			}
		}
	}
}
