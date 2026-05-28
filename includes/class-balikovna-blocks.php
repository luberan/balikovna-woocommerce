<?php
/**
 * Block-based checkout integration (Cart/Checkout blocks).
 *
 * Registers a Store API endpoint extension that exposes/accepts the chosen
 * Balíkovna point, plus enqueues a frontend block-integration script that
 * renders the picker button under the shipping method.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;

defined( 'ABSPATH' ) || exit;

class Blocks {

	const NS = 'balikovna-wc';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register' ) );
	}

	public function register() {
		if ( ! class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
			return;
		}

		// Extend cart schema so blocks checkout can read/write the point.
		try {
			$extend = StoreApi::container()->get( ExtendSchema::class );
			$extend->register_endpoint_data(
				array(
					'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
					'namespace'       => self::NS,
					'data_callback'   => array( $this, 'data_callback' ),
					'schema_callback' => array( $this, 'schema_callback' ),
					'schema_type'     => ARRAY_A,
				)
			);
		} catch ( \Throwable $e ) {
			// Bail silently if Store API not fully available.
		}

		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array( $this, 'update_order_from_request' ),
			10,
			2
		);

		// Frontend script registration for block integration.
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_script' ) );
	}

	public function data_callback() {
		$point = Checkout::get_session_point();
		return array(
			'point'     => $point,
			'widgetUrl' => Plugin::widget_url(),
		);
	}

	public function schema_callback() {
		$point_schema = array(
			'type'       => 'object',
			'properties' => array(
				'id'      => array( 'type' => 'string' ),
				'name'    => array( 'type' => 'string' ),
				'street'  => array( 'type' => 'string' ),
				'city'    => array( 'type' => 'string' ),
				'zip'     => array( 'type' => 'string' ),
				'country' => array( 'type' => 'string' ),
			),
		);
		return array(
			'point'     => $point_schema,
			'widgetUrl' => array( 'type' => 'string' ),
		);
	}

	public function update_order_from_request( $order, $request ) {
		$ext = $request->get_param( 'extensions' );
		if ( ! is_array( $ext ) || empty( $ext[ self::NS ] ) ) {
			return;
		}
		$payload = $ext[ self::NS ];
		if ( empty( $payload['point'] ) || ! is_array( $payload['point'] ) ) {
			return;
		}

		$pickup_ids = Services::pickup_ids();
		$applies_sid = null;
		foreach ( $order->get_shipping_methods() as $m ) {
			foreach ( $pickup_ids as $sid ) {
				if ( 0 === strpos( (string) $m->get_method_id(), $sid ) ) {
					$applies_sid = $sid;
					break 2;
				}
			}
		}
		if ( ! $applies_sid ) {
			return;
		}

		$point = Checkout::sanitize_point( $payload['point'] );
		if ( empty( $point['id'] ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'balikovna_required',
				__( 'Prosím zvolte výdejní místo.', 'balikovna-wc' ),
				400
			);
		}
		Order::save_point_to_order( $order, $point, $applies_sid );
		WC()->session->set( 'balikovna_point', $point );
	}

	public function enqueue_block_script() {
		if ( is_admin() ) {
			return;
		}
		$handle = 'balikovna-wc-blocks';
		wp_register_script(
			$handle,
			BALIKOVNA_WC_URL . 'assets/js/checkout-block.js',
			array( 'wp-element', 'wp-i18n', 'wp-data', 'wp-plugins', 'wc-blocks-checkout' ),
			BALIKOVNA_WC_VERSION,
			true
		);
		wp_localize_script(
			$handle,
			'BalikovnaWCBlock',
			array(
				'services' => $this->services_js(),
				'debug'    => Plugin::is_debug(),
				'i18n'     => array(
					'choose'   => __( 'Vybrat výdejní místo', 'balikovna-wc' ),
					'change'   => __( 'Změnit výdejní místo', 'balikovna-wc' ),
					'selected' => __( 'Zvolené místo:', 'balikovna-wc' ),
					'required' => __( 'Prosím zvolte výdejní místo.', 'balikovna-wc' ),
					'title'    => __( 'Výběr výdejního místa', 'balikovna-wc' ),
					'close'    => __( 'Zavřít', 'balikovna-wc' ),
				),
			)
		);
		wp_enqueue_script( $handle );
	}

	private function services_js() {
		$out = array();
		foreach ( Services::all() as $sid => $cfg ) {
			if ( ! empty( $cfg['pickup'] ) ) {
				$out[ $sid ] = array(
					'pickup'    => $cfg['pickup'],
					'widgetUrl' => Plugin::widget_url( $cfg['pickup'] ),
					'label'     => $cfg['label'],
				);
			}
		}
		return $out;
	}
}
