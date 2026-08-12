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

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		$this->scheduler = new Tracking_Scheduler( array( $this, 'run_synchronization' ) );
		$this->scheduler->init();

		$this->admin = new Tracking_Admin( $this );
		$this->admin->init();
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
		return $this->synchronizer()->run_batch();
	}
}
