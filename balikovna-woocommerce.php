<?php
/**
 * Plugin Name: Balíkovna for WooCommerce
 * Plugin URI:  https://github.com/luberan/balikovna-woocommerce
 * Description: Integrace České pošty - Balíkovna do WooCommerce. Výběr výdejního místa v košíku, uložení k objednávce, zobrazení v adminu a emailech, CSV export pro Podání Online.
 * Version:     1.26.5 <!-- x-release-please-version -->
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

define( 'BALIKOVNA_WC_VERSION', '1.26.5' ); // x-release-please-version
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

// Auto-updates from GitHub Releases via Plugin Update Checker
// (YahnisElsts/plugin-update-checker, MIT). Stahuje vždy release asset
// `balikovna-woocommerce.zip` (vyrobený workflowem release-please),
// ne auto-generated "Source code (zip)".
add_action(
	'plugins_loaded',
	function () {
		$puc = BALIKOVNA_WC_PATH . 'includes/lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $puc ) ) {
			return;
		}
		require_once $puc;
		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}
		try {
			$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
				'https://github.com/luberan/balikovna-woocommerce/',
				BALIKOVNA_WC_FILE,
				'balikovna-woocommerce'
			);
			$checker->setBranch( 'main' );
			$api = $checker->getVcsApi();
			if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
				$api->enableReleaseAssets( '/^balikovna-woocommerce\.zip$/i' );
			}

			// PUC by default uses the GitHub release body (just notes for the
			// single released version) as the changelog shown in "View version
			// details". Prefer the full == Changelog == section from the
			// readme.txt that ships with the installed ZIP — it contains the
			// complete history rendered by the release workflow.
			add_filter(
				'puc_request_info_result-balikovna-woocommerce',
				function ( $pluginInfo ) {
					if ( ! is_object( $pluginInfo ) ) {
						return $pluginInfo;
					}
					$readme_path = BALIKOVNA_WC_PATH . 'readme.txt';
					if ( ! is_readable( $readme_path ) ) {
						return $pluginInfo;
					}
					$readme = file_get_contents( $readme_path );
					if ( ! $readme || ! preg_match( '/^==\s*Changelog\s*==\s*$(.*?)(?=^==\s|\z)/sm', $readme, $m ) ) {
						return $pluginInfo;
					}
					$body = trim( $m[1] );
					// Convert the WP readme syntax (`= 1.2.3 =` / `* item`) into
					// simple HTML for the modal.
					$body = preg_replace( '/^=\s*([^=]+?)\s*=\s*$/m', '<h4>$1</h4>', $body );
					$body = preg_replace_callback(
						'/(?:^\*\s+.+(?:\r?\n|$))+/m',
						function ( $block ) {
							$items = preg_replace( '/^\*\s+(.+)$/m', '<li>$1</li>', rtrim( $block[0] ) );
							return "<ul>{$items}</ul>\n";
						},
						$body
					);
					if ( ! isset( $pluginInfo->sections ) || ! is_array( $pluginInfo->sections ) ) {
						$pluginInfo->sections = array();
					}
					$pluginInfo->sections['changelog'] = $body;
					return $pluginInfo;
				}
			);
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Balíkovna] update checker init failed: ' . $e->getMessage() );
			}
		}
	},
	5
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
