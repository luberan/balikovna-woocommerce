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
		add_action( 'admin_notices', array( $this, 'credential_notice' ) );
	}

	/**
	 * Warn when tracking is enabled but its stored credentials cannot be read.
	 *
	 * This happens after the encryption key or the WordPress salts change. The
	 * recurring synchronization is then unscheduled, which nothing else reports
	 * outside the tracking settings screen.
	 */
	public function credential_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$stored = get_option( Tracking_Settings::OPTION_NAME, array() );
		if ( ! is_array( $stored ) || empty( $stored['enabled'] ) ) {
			return;
		}
		$error = Tracking_Settings::credential_error();
		if ( ! $error ) {
			return;
		}
		$url = admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . Tracking_Admin::SECTION );
		echo '<div class="notice notice-error"><p><strong>'
			. esc_html__( 'Balíkovna: sledování stavu zásilek je pozastavené.', 'balikovna-wc' )
			. '</strong> ' . esc_html( $error->get_error_message() )
			. ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Otevřít nastavení sledování', 'balikovna-wc' ) . '</a></p></div>';
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
