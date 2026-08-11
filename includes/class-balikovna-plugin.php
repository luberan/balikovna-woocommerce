<?php
/**
 * Main plugin bootstrap.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-services.php';
require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-points.php';
require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-checkout.php';
require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-order.php';
require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-export.php';
require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-blocks.php';

class Plugin {

	const WIDGET_MESSAGE_ID = 'pickerResult';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'woocommerce_shipping_init', array( $this, 'load_shipping_methods' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_methods' ) );

		Checkout::instance()->init();
		Order::instance()->init();
		Export::instance()->init();
		Blocks::instance()->init();
	}

	public function load_shipping_methods() {
		require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-shipping-methods.php';
	}

	public function register_shipping_methods( $methods ) {
		$methods['balikovna']           = __NAMESPACE__ . '\\Shipping_Method_Balikovna';
		$methods['balikovna_na_adresu'] = __NAMESPACE__ . '\\Shipping_Method_BalikovnaNaAdresu';
		$methods['cp_do_ruky']          = __NAMESPACE__ . '\\Shipping_Method_DoRuky';
		$methods['cp_na_postu']         = __NAMESPACE__ . '\\Shipping_Method_NaPostu';
		return $methods;
	}

	/**
	 * Vrátí URL widgetu pro výběr výdejního místa.
	 *
	 * Parametry dle oficiálního manuálu „Balíkovna – widget – implementace" (Česká pošta):
	 *  - type=BALIKOVNY|POST_OFFICE : omezí seznam míst (jinak se načítají oba a hrozí chybný výběr).
	 *  - skipLocation=false   : povolí funkci „Moje poloha" (doporučeno).
	 *  - phone=true           : zobrazí ve widgetu pole pro telefon. Manuál doporučuje pouze pokud
	 *                           e-shop nemá telefon povinný. Vrácené číslo se použije jako fallback
	 *                           billing telefonu. Default lze změnit filtrem `balikovna_wc_widget_phone`.
	 *
	 * @param string $type BALIKOVNY|POST_OFFICE
	 */
	public static function widget_url( $type = 'BALIKOVNY' ) {
		// Legacy hodnoty mapujeme na aktuální tokeny widgetu.
		if ( 'POSTY' === $type ) {
			$type = 'POST_OFFICE';
		}
		$show_phone = (bool) apply_filters( 'balikovna_wc_widget_phone', false );

		$args = array(
			'type'         => $type,
			'skipLocation' => 'false',
			'messageId'    => self::WIDGET_MESSAGE_ID,
		);
		if ( $show_phone ) {
			$args['phone'] = 'true';
		}

		$url = add_query_arg( $args, 'https://b2c.cpost.cz/locations/' );

		return apply_filters( 'balikovna_wc_widget_url', $url, $type );
	}

	/**
	 * Je zapnutý diagnostický mód? (WP_DEBUG nebo filtr).
	 */
	public static function is_debug() {
		return (bool) apply_filters( 'balikovna_wc_debug', defined( 'WP_DEBUG' ) && WP_DEBUG );
	}
}
