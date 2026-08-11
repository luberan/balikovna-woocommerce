<?php
/**
 * Classic (shortcode) checkout integration.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Checkout {
	const SESSION_KEY  = 'balikovna_points';
	const NONCE_ACTION = 'woocommerce_balikovna_wc';

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

		// Validate the final posted shipping methods after WooCommerce updates its session.
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_selection' ), 10, 2 );

		// Save to order.
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_to_order' ), 10, 2 );

		// Keep the selection for failed payment retries; successful checkout empties the cart.
		add_action( 'woocommerce_cart_emptied', array( $this, 'clear_session' ) );
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

		$is_block_page = function_exists( 'has_block' )
			&& ( has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ) );
		if ( $is_block_page ) {
			return;
		}

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
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'services' => $services_js,
				'selected' => array_values( self::get_session_selections() ),
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
				<div id="balikovna-pickers"></div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Vrátí pickup služby zvolené pro jednotlivé shipping balíky.
	 *
	 * @return array<string,array>
	 */
	public static function chosen_pickup_services() {
		return self::pickup_services_from_rates( self::chosen_shipping_rates() );
	}

	/**
	 * Return normalized package-keyed shipping rate IDs from the session.
	 *
	 * @return array<string,string>
	 */
	public static function chosen_shipping_rates() {
		$chosen = WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : array();
		$result = array();
		foreach ( is_array( $chosen ) ? $chosen : array() as $package_key => $rate_id ) {
			$package_key = self::normalize_package_key( $package_key );
			if ( null !== $package_key && is_string( $rate_id ) ) {
				$result[ $package_key ] = sanitize_text_field( $rate_id );
			}
		}
		return $result;
	}

	/**
	 * Convert package-keyed rate IDs to pickup service contexts.
	 *
	 * @param array $chosen Package-keyed shipping rate IDs.
	 * @return array<string,array>
	 */
	public static function pickup_services_from_rates( array $chosen ) {
		$result     = array();
		$pickup_ids = Services::pickup_ids();
		foreach ( $chosen as $package_key => $rate ) {
			$normalized_key = self::normalize_package_key( $package_key );
			if ( null === $normalized_key || ! is_string( $rate ) ) {
				continue;
			}
			// Rate ID má tvar `method_id:instance_id` — porovnávej přesně method_id,
			// ne prefix (jinak `balikovna` chybně matchne `balikovna_na_adresu`).
			$method_id = self::service_id_from_rate( $rate );
			if ( in_array( $method_id, $pickup_ids, true ) ) {
				$result[ $normalized_key ] = array(
					'packageKey' => $normalized_key,
					'rateId'     => sanitize_text_field( $rate ),
					'serviceId'  => $method_id,
				);
			}
		}
		return $result;
	}

	/**
	 * Backwards-compatible helper returning the first selected pickup service.
	 */
	public static function chosen_pickup_service() {
		$chosen = self::chosen_pickup_services();
		$first  = reset( $chosen );
		return $first ? $first['serviceId'] : null;
	}

	public function ajax_set_point() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			wp_send_json_error( array( 'message' => 'no_session' ) );
		}

		$package_key = isset( $_POST['package_key'] ) ? self::normalize_package_key( sanitize_text_field( wp_unslash( $_POST['package_key'] ) ) ) : null;
		$rate_id     = isset( $_POST['rate_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rate_id'] ) ) : '';
		$chosen      = self::chosen_pickup_services();
		if ( null === $package_key || ! isset( $chosen[ $package_key ] ) || $rate_id !== $chosen[ $package_key ]['rateId'] ) {
			wp_send_json_error( array( 'message' => __( 'Zvolená doprava se změnila. Vyberte místo znovu.', 'balikovna-wc' ) ), 409 );
		}

		$point = isset( $_POST['point'] ) && is_array( $_POST['point'] ) ? map_deep( wp_unslash( $_POST['point'] ), 'sanitize_text_field' ) : array();
		if ( ! is_array( $point ) ) {
			$point = array();
		}

		$validated = Points::validate( $point, $chosen[ $package_key ]['serviceId'] );
		if ( is_wp_error( $validated ) ) {
			wp_send_json_error( array( 'message' => $validated->get_error_message() ), 400 );
		}

		$selection                  = $chosen[ $package_key ];
		$selection['point']         = $validated;
		$selections                 = self::get_session_selections( false );
		$selections[ $package_key ] = $selection;
		self::set_session_selections( $selections );

		wp_send_json_success( $selection );
	}

	public function validate_selection( $data, $errors ) {
		$rates      = isset( $data['shipping_method'] ) && is_array( $data['shipping_method'] ) ? $data['shipping_method'] : array();
		$chosen     = self::pickup_services_from_rates( $rates );
		$selections = self::get_session_selections( false );
		foreach ( $chosen as $package_key => $service ) {
			if ( empty( $selections[ $package_key ]['point']['id'] ) || $service['rateId'] !== $selections[ $package_key ]['rateId'] ) {
				$errors->add( 'balikovna_point_required', __( 'Prosím zvolte výdejní místo pro každý balík.', 'balikovna-wc' ) );
				break;
			}
		}
	}

	public function save_to_order( $order, $data ) {
		Order::sync_order_summary( $order, false );
	}

	public function clear_session() {
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY, null );
			WC()->session->set( 'balikovna_point', null );
		}
	}

	public static function get_session_point() {
		$selections = self::get_session_selections();
		$first      = reset( $selections );
		return $first && isset( $first['point'] ) ? $first['point'] : array();
	}

	public static function sanitize_point( array $point ) {
		return Points::sanitize( $point );
	}

	/**
	 * Return validated session selections, optionally pruning stale rates.
	 *
	 * @param bool $prune Whether to remove selections for no longer chosen rates.
	 * @return array<string,array>
	 */
	public static function get_session_selections( $prune = true ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array();
		}

		$stored = WC()->session->get( self::SESSION_KEY );
		$stored = is_array( $stored ) ? $stored : array();
		$valid  = array();
		foreach ( $stored as $package_key => $selection ) {
			$normalized_key = self::normalize_package_key( $package_key );
			if ( null === $normalized_key || ! is_array( $selection ) || empty( $selection['point'] ) || ! is_array( $selection['point'] ) ) {
				continue;
			}

			$rate_id    = isset( $selection['rateId'] ) ? sanitize_text_field( (string) $selection['rateId'] ) : '';
			$service_id = self::service_id_from_rate( $rate_id );
			$point      = Points::sanitize( $selection['point'] );
			if ( ! Services::get( $service_id ) || ! Points::matches_service( $point, $service_id ) ) {
				continue;
			}

			$valid[ $normalized_key ] = array(
				'packageKey' => $normalized_key,
				'rateId'     => $rate_id,
				'serviceId'  => $service_id,
				'point'      => $point,
			);
		}

		if ( $prune ) {
			$chosen = self::chosen_pickup_services();
			foreach ( $valid as $package_key => $selection ) {
				if ( ! isset( $chosen[ $package_key ] ) || $chosen[ $package_key ]['rateId'] !== $selection['rateId'] ) {
					unset( $valid[ $package_key ] );
				}
			}
		}

		if ( $stored !== $valid ) {
			self::set_session_selections( $valid );
		}
		return $valid;
	}

	public static function get_session_selection( $package_key, $rate_id ) {
		$package_key = self::normalize_package_key( $package_key );
		$selections  = self::get_session_selections();
		if ( null === $package_key || ! isset( $selections[ $package_key ] ) || $rate_id !== $selections[ $package_key ]['rateId'] ) {
			return array();
		}
		return $selections[ $package_key ];
	}

	public static function set_session_selections( array $selections ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $selections ? $selections : null );
			WC()->session->set( 'balikovna_point', null );
		}
	}

	public static function normalize_package_key( $package_key ) {
		$package_key = (string) $package_key;
		return preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $package_key ) ? $package_key : null;
	}

	public static function service_id_from_rate( $rate_id ) {
		$parts = explode( ':', (string) $rate_id, 2 );
		return sanitize_key( $parts[0] );
	}
}
