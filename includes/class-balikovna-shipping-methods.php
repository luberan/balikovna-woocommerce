<?php
/**
 * Konkrétní shipping metody pro služby České pošty.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-shipping-method-base.php';

class Shipping_Method_Balikovna extends Shipping_Method_Base {
	protected static $service_id = 'balikovna';
}

class Shipping_Method_BalikovnaNaAdresu extends Shipping_Method_Base {
	protected static $service_id = 'balikovna_na_adresu';
}

class Shipping_Method_DoRuky extends Shipping_Method_Base {
	protected static $service_id = 'cp_do_ruky';
}

class Shipping_Method_NaPostu extends Shipping_Method_Base {
	protected static $service_id = 'cp_na_postu';
}

/** Zpětná kompatibilita – starý alias. */
class_alias( __NAMESPACE__ . '\\Shipping_Method_Balikovna', __NAMESPACE__ . '\\Shipping_Method' );
