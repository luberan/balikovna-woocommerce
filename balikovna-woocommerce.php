<?php
/**
 * Plugin Name: Balíkovna for WooCommerce
 * Plugin URI:  https://github.com/luberan/balikovna-woocommerce
 * Description: Integrace České pošty - Balíkovna do WooCommerce. Výběr výdejního místa v košíku, uložení k objednávce, zobrazení v adminu a emailech, CSV export pro Podání Online.
 * Version:     1.1.0 <!-- x-release-please-version -->
 * Author:      Lukáš Beran
 * Author URI:  https://www.lukasberan.cz/
 * License:     GPL-3.0-or-later
 * Text Domain: balikovna-wc
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * WC requires at least: 10.8
 * WC tested up to: 10.8
 *
 * @package Balikovna_WC
 */

defined( 'ABSPATH' ) || exit;

define( 'BALIKOVNA_WC_VERSION', '1.1.0' ); // x-release-please-version
define( 'BALIKOVNA_WC_FILE', __FILE__ );
define( 'BALIKOVNA_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BALIKOVNA_WC_URL', plugin_dir_url( __FILE__ ) );

// HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Balíkovna for WooCommerce vyžaduje aktivní WooCommerce.', 'balikovna-wc' ) . '</p></div>';
				}
			);
			return;
		}

		require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-plugin.php';
		Balikovna_WC\Plugin::instance()->init();
	}
);

// WordPress 6.7+ vyžaduje načtení textdomény nejdříve na hooku `init`.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'balikovna-wc', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);
