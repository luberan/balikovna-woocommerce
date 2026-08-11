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
	private $registered      = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register();
			return;
		}
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register' ) );
	}

	public function register() {
		if ( $this->registered || ! class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
			return;
		}
		$this->registered = true;

		// Extend cart schema so blocks checkout can read/write the point.
		try {
			$extend = StoreApi::container()->get( ExtendSchema::class );
			foreach (
				array(
					\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
					\Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
				) as $endpoint
			) {
				$extend->register_endpoint_data(
					array(
						'endpoint'        => $endpoint,
						'namespace'       => self::NS,
						'data_callback'   => array( $this, 'data_callback' ),
						'schema_callback' => array( $this, 'schema_callback' ),
						'schema_type'     => ARRAY_A,
					)
				);
			}

			$extend->register_update_callback(
				array(
					'namespace' => self::NS,
					'callback'  => array( $this, 'update_cart_from_request' ),
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
		return array(
			'selections' => array_values( Checkout::get_session_selections() ),
			'widgetUrl'  => Plugin::widget_url(),
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
			'selections' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'packageKey' => array( 'type' => 'string' ),
						'rateId'     => array( 'type' => 'string' ),
						'serviceId'  => array( 'type' => 'string' ),
						'point'      => $point_schema,
					),
				),
			),
			'widgetUrl'  => array( 'type' => 'string' ),
		);
	}

	public function update_cart_from_request( $data ) {
		$payload     = is_array( $data ) ? $data : array();
		$package_key = isset( $payload['packageKey'] ) ? Checkout::normalize_package_key( $payload['packageKey'] ) : null;
		$rate_id     = isset( $payload['rateId'] ) ? sanitize_text_field( (string) $payload['rateId'] ) : '';
		$chosen      = Checkout::chosen_pickup_services();
		if ( null === $package_key || ! isset( $chosen[ $package_key ] ) || $rate_id !== $chosen[ $package_key ]['rateId'] ) {
			throw new RouteException(
				'balikovna_stale_shipping_rate',
				esc_html__( 'Zvolená doprava se změnila. Vyberte místo znovu.', 'balikovna-wc' ),
				409
			);
		}

		$point = isset( $payload['point'] ) && is_array( $payload['point'] ) ? $payload['point'] : array();
		$point = Points::validate( $point, $chosen[ $package_key ]['serviceId'] );
		if ( is_wp_error( $point ) ) {
			throw new RouteException( sanitize_key( $point->get_error_code() ), esc_html( $point->get_error_message() ), 400 );
		}

		$selection                  = $chosen[ $package_key ];
		$selection['point']         = $point;
		$selections                 = Checkout::get_session_selections( false );
		$selections[ $package_key ] = $selection;
		Checkout::set_session_selections( $selections );
	}

	public function update_order_from_request( $order, $request ) {
		Order::sync_shipping_points( $order );

		// Validace probíhá jen na finálním odeslání objednávky (POST /checkout),
		// ne při průběžných aktualizacích košíku — jinak by panel zůstal viset
		// v načítacím stavu. Na finálním submitu odmítni objednávku bez místa.
		if ( ! $this->is_final_checkout_request( $request ) ) {
			return;
		}

		foreach ( Order::get_shipments( $order, false ) as $shipment ) {
			if ( ! empty( $shipment['service']['pickup'] ) && empty( $shipment['point']['id'] ) ) {
				throw new RouteException(
					'balikovna_point_required',
					esc_html__( 'Prosím zvolte výdejní místo pro každý balík.', 'balikovna-wc' ),
					400
				);
			}
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
		if ( ! function_exists( 'is_checkout' ) || ( ! is_checkout() && ! is_cart() ) ) {
			return;
		}
		if ( function_exists( 'has_block' ) && ! has_block( 'woocommerce/checkout' ) && ! has_block( 'woocommerce/cart' ) ) {
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
					'choose'    => __( 'Vybrat výdejní místo', 'balikovna-wc' ),
					'change'    => __( 'Změnit výdejní místo', 'balikovna-wc' ),
					'selected'  => __( 'Zvolené místo:', 'balikovna-wc' ),
					'required'  => __( 'Prosím zvolte výdejní místo.', 'balikovna-wc' ),
					'title'     => __( 'Výběr výdejního místa', 'balikovna-wc' ),
					'close'     => __( 'Zavřít', 'balikovna-wc' ),
					'saving'    => __( 'Ukládám výdejní místo…', 'balikovna-wc' ),
					'saveError' => __( 'Výdejní místo se nepodařilo uložit. Zkuste to prosím znovu.', 'balikovna-wc' ),
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
