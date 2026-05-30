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

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

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
			// Prázdný výběr serializuj jako objekt {}, ne pole [] — odpovídá schématu 'object'.
			'point'     => empty( $point ) ? (object) array() : $point,
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
				'type'    => array( 'type' => 'string' ),
				'subtype' => array( 'type' => 'string' ),
				'lat'     => array( 'type' => 'string' ),
				'lng'     => array( 'type' => 'string' ),
			),
		);
		return array(
			'point'     => $point_schema,
			'widgetUrl' => array( 'type' => 'string' ),
		);
	}

	public function update_order_from_request( $order, $request ) {
		// Zjisti, zda objednávka používá službu vyžadující výběr výdejního místa.
		$pickup_ids  = Services::pickup_ids();
		$applies_sid = null;
		foreach ( $order->get_shipping_methods() as $m ) {
			// get_method_id() vrací čisté method_id bez instance — porovnávej přesně.
			if ( in_array( (string) $m->get_method_id(), $pickup_ids, true ) ) {
				$applies_sid = (string) $m->get_method_id();
				break;
			}
		}
		if ( ! $applies_sid ) {
			return;
		}

		// Ulož bod z requestu (pokud je validní).
		$ext     = $request->get_param( 'extensions' );
		$payload = ( is_array( $ext ) && ! empty( $ext[ self::NS ] ) ) ? $ext[ self::NS ] : array();
		if ( ! empty( $payload['point'] ) && is_array( $payload['point'] ) ) {
			$point = Checkout::sanitize_point( $payload['point'] );
			if ( ! empty( $point['id'] ) ) {
				Order::save_point_to_order( $order, $point, $applies_sid );
				if ( WC()->session ) {
					WC()->session->set( 'balikovna_point', $point );
				}
			}
		}

		// Validace probíhá jen na finálním odeslání objednávky (POST /checkout),
		// ne při průběžných aktualizacích košíku — jinak by panel zůstal viset
		// v načítacím stavu. Na finálním submitu odmítni objednávku bez místa.
		if ( ! $this->is_final_checkout_request( $request ) ) {
			return;
		}

		$saved = Order::get_point( $order );
		if ( empty( $saved['id'] ) ) {
			throw new RouteException(
				'balikovna_point_required',
				esc_html__( 'Prosím zvolte výdejní místo.', 'balikovna-wc' ),
				400
			);
		}
	}

	/**
	 * Rozliší finální odeslání objednávky (POST /checkout) od průběžných
	 * aktualizací draftu, aby se validační výjimka nevyhazovala předčasně.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool
	 */
	protected function is_final_checkout_request( $request ) {
		return is_object( $request )
			&& method_exists( $request, 'get_method' )
			&& 'POST' === strtoupper( (string) $request->get_method() );
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
