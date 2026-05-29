<?php
/**
 * Classic (shortcode) checkout integration.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Checkout {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'render_picker' ) );

		// Prepend service logo to shipping method label in cart/checkout.
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'add_logo_to_label' ), 10, 2 );

		// Persist selection into session via AJAX.
		add_action( 'wc_ajax_balikovna_set_point', array( $this, 'ajax_set_point' ) );
		add_action( 'wp_ajax_balikovna_set_point', array( $this, 'ajax_set_point' ) );
		add_action( 'wp_ajax_nopriv_balikovna_set_point', array( $this, 'ajax_set_point' ) );

		// Validate selection on order submit.
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_selection' ) );

		// Save to order.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_to_order' ), 10, 2 );

		// Cleanup after order created.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'clear_session' ), 99 );
	}

	public function enqueue() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! is_checkout() && ! is_cart() ) {
			return;
		}

		wp_enqueue_style(
			'balikovna-wc',
			BALIKOVNA_WC_URL . 'assets/css/checkout.css',
			array(),
			BALIKOVNA_WC_VERSION
		);

		wp_enqueue_script(
			'balikovna-wc',
			BALIKOVNA_WC_URL . 'assets/js/checkout.js',
			array( 'jquery', 'wc-checkout' ),
			BALIKOVNA_WC_VERSION,
			true
		);

		$services_js = array();
		foreach ( Services::all() as $sid => $cfg ) {
			if ( ! empty( $cfg['pickup'] ) ) {
				$services_js[ $sid ] = array(
					'pickup'    => $cfg['pickup'],
					'widgetUrl' => Plugin::widget_url( $cfg['pickup'] ),
					'label'     => $cfg['label'],
				);
			}
		}

		wp_localize_script(
			'balikovna-wc',
			'BalikovnaWC',
			array(
				'ajaxUrl'  => \WC_AJAX::get_endpoint( 'balikovna_set_point' ),
				'nonce'    => wp_create_nonce( 'balikovna_wc' ),
				'services' => $services_js,
				'selected' => self::get_session_point(),
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
	}

	/**
	 * Přidá logo služby ČP před popisek shipping metody v košíku/checkoutu (klasický).
	 *
	 * @param string                $label
	 * @param \WC_Shipping_Rate     $method
	 * @return string
	 */
	public function add_logo_to_label( $label, $method ) {
		if ( ! $method instanceof \WC_Shipping_Rate ) {
			return $label;
		}
		$service = Services::get( $method->get_method_id() );
		if ( ! $service || empty( $service['logo'] ) ) {
			return $label;
		}
		$src = BALIKOVNA_WC_URL . 'assets/img/' . $service['logo'];
		$img = sprintf(
			'<img src="%s" alt="%s" class="balikovna-method-logo" style="height:18px;width:auto;vertical-align:middle;margin-right:6px;" />',
			esc_url( $src ),
			esc_attr( $service['label'] )
		);
		return $img . $label;
	}

	public function render_picker() {
		?>
		<tr class="balikovna-row" style="display:none;">
			<th><?php esc_html_e( 'Výdejní místo', 'balikovna-wc' ); ?></th>
			<td data-title="<?php esc_attr_e( 'Výdejní místo', 'balikovna-wc' ); ?>">
				<div id="balikovna-picker-wrap">
					<button type="button" class="button" id="balikovna-open"><?php esc_html_e( 'Vybrat výdejní místo', 'balikovna-wc' ); ?></button>
					<div id="balikovna-selected" class="balikovna-selected" aria-live="polite"></div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Vrátí ID zvolené shipping metody (bez instance suffixu), pokud patří mezi naše služby s pickupem.
	 */
	public static function chosen_pickup_service() {
		$chosen = WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : array();
		if ( ! is_array( $chosen ) ) {
			return null;
		}
		$pickup_ids = Services::pickup_ids();
		foreach ( $chosen as $rate ) {
			foreach ( $pickup_ids as $sid ) {
				if ( 0 === strpos( (string) $rate, $sid ) ) {
					return $sid;
				}
			}
		}
		return null;
	}

	public function ajax_set_point() {
		check_ajax_referer( 'balikovna_wc', 'nonce' );

		$point = isset( $_POST['point'] ) ? wc_clean( wp_unslash( $_POST['point'] ) ) : array();
		if ( ! is_array( $point ) ) {
			$point = array();
		}

		$sanitized = self::sanitize_point( $point );

		if ( empty( $sanitized['id'] ) || empty( $sanitized['name'] ) ) {
			WC()->session->set( 'balikovna_point', null );
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}

		WC()->session->set( 'balikovna_point', $sanitized );
		wp_send_json_success( $sanitized );
	}

	public function validate_selection() {
		if ( ! self::chosen_pickup_service() ) {
			return;
		}
		$point = self::get_session_point();
		if ( empty( $point['id'] ) ) {
			wc_add_notice( __( 'Prosím zvolte výdejní místo.', 'balikovna-wc' ), 'error' );
		}
	}

	public function save_to_order( $order, $data ) {
		$sid = self::chosen_pickup_service();
		if ( ! $sid ) {
			return;
		}
		$point = self::get_session_point();
		if ( empty( $point['id'] ) ) {
			return;
		}
		Order::save_point_to_order( $order, $point, $sid );
	}

	public function clear_session() {
		if ( WC()->session ) {
			WC()->session->set( 'balikovna_point', null );
		}
	}

	public static function get_session_point() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array();
		}
		$p = WC()->session->get( 'balikovna_point' );
		return is_array( $p ) ? $p : array();
	}

	public static function sanitize_point( array $point ) {
		$keys = array( 'id', 'name', 'street', 'city', 'zip', 'country', 'type', 'subtype', 'lat', 'lng' );
		$out  = array();
		foreach ( $keys as $k ) {
			$out[ $k ] = isset( $point[ $k ] ) ? sanitize_text_field( (string) $point[ $k ] ) : '';
		}
		return $out;
	}
}
