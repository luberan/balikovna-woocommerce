<?php
/**
 * Shipment tracking subsystem coordinator.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Tracking {

	private static $instance = null;

	private $scheduler;
	private $admin;
	private $initialized = false;
	private $maintained  = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		if ( did_action( 'init' ) ) {
			$this->initialize();
			return;
		}
		add_action( 'init', array( $this, 'initialize' ), 20 );
	}

	/**
	 * Register hooks only; settings are read in admin, cron and synchronization contexts.
	 */
	public function initialize() {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		$this->scheduler = new Tracking_Scheduler( array( $this, 'run_synchronization' ), array( $this, 'should_schedule' ) );
		$this->scheduler->init();

		$this->admin = new Tracking_Admin( $this );
		$this->admin->init();

		add_action( 'admin_init', array( $this, 'maintain_admin' ) );
	}

	public function maintain_admin() {
		if ( ! wp_doing_ajax() ) {
			$this->maintain();
		}
	}

	/**
	 * Migrate stored credentials and reconcile the cached carrier dictionary.
	 */
	public function maintain() {
		if ( $this->maintained ) {
			return;
		}
		$this->maintained = true;
		Tracking_Settings::migrate_credentials();
		$cached_dictionary = $this->dictionary()->get();
		Tracking_Settings::reconcile_status_dictionary( $cached_dictionary, $cached_dictionary );
	}

	public function should_schedule() {
		$settings = Tracking_Settings::get();
		return ! empty( $settings['enabled'] ) && Tracking_Settings::is_configured( $settings );
	}

	public function refresh_schedule() {
		if ( $this->scheduler ) {
			$this->scheduler->ensure_scheduled();
		}
	}

	public function client( ?array $settings = null ) {
		$settings = null === $settings ? Tracking_Settings::get() : $settings;
		return new Napi_Client(
			new Napi_Authentication(
				$settings['api_token'] ?? '',
				$settings['secret_key'] ?? ''
			),
			new WordPress_Napi_Transport(),
			$settings['environment'] ?? 'production'
		);
	}

	public function dictionary( ?array $settings = null ) {
		return new Status_Dictionary( $this->client( $settings ) );
	}

	public function synchronizer( ?array $settings = null ) {
		$client = $this->client( $settings );
		return new Shipment_Synchronizer(
			$client,
			new Status_Dictionary( $client ),
			new Eligible_Orders(),
			new Order_Status_Mapper(),
			new Tracking_Logger()
		);
	}

	public function run_synchronization() {
		$this->maintain();
		return $this->synchronizer()->run_batch();
	}
}
