<?php
/**
 * Plugin Name: Balíkovna for WooCommerce
 * Plugin URI:  https://github.com/luberan/balikovna-woocommerce
 * Description: Integrace Balíkovny, Balíkovny plus a dalších služeb České pošty do WooCommerce. Výdejní místa, CSV, Track & Trace a synchronizace stavů.
 * x-release-please-start-version
 * Version:     1.29.0
 * x-release-please-end
 * Author:      Lukáš Beran
 * Author URI:  https://www.lukasberan.cz/
 * License:     GPL-3.0-or-later
 * Text Domain: balikovna-wc
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.9
 * Requires Plugins: woocommerce
 * WC requires at least: 10.8
 * WC tested up to: 11.1
 *
 * @package Balikovna_WC
 */

defined( 'ABSPATH' ) || exit;

define( 'BALIKOVNA_WC_VERSION', '1.29.0' ); // x-release-please-version
define( 'BALIKOVNA_WC_MIN_WC_VERSION', '10.8' );
define( 'BALIKOVNA_WC_FILE', __FILE__ );
define( 'BALIKOVNA_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'BALIKOVNA_WC_URL', plugin_dir_url( __FILE__ ) );

register_deactivation_hook(
	__FILE__,
	function ( $network_wide = false ) {
		require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-cleanup.php';
		\Balikovna_WC\Cleanup::deactivate( (bool) $network_wide );
	}
);

add_action(
	'deactivate_woocommerce/woocommerce.php',
	function ( $network_wide = false ) {
		require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-cleanup.php';
		\Balikovna_WC\Cleanup::woocommerce_deactivated( (bool) $network_wide );
	},
	1
);

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

// Auto-updates from GitHub Releases via Plugin Update Checker
// (YahnisElsts/plugin-update-checker, MIT). Stahuje vždy release asset
// `balikovna-woocommerce.zip` (vyrobený workflowem release-please),
// ne auto-generated "Source code (zip)".
// Až na `init`: vytvoření checkeru může naplánovat cron a filtry
// `cron_schedules` jiných pluginů (WooCommerce) přitom načítají překlady.
add_action(
	'init',
	function () {
		require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-update-checker.php';
		\Balikovna_WC\Update_Checker::init();
	},
	5
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, BALIKOVNA_WC_MIN_WC_VERSION, '<' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html(
						sprintf(
							/* translators: %s: minimum WooCommerce version. */
							__( 'Balíkovna for WooCommerce vyžaduje aktivní WooCommerce %s nebo novější.', 'balikovna-wc' ),
							BALIKOVNA_WC_MIN_WC_VERSION
						)
					);
					echo '</p></div>';
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
