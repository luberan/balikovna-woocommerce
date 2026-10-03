<?php
/**
 * Updates from GitHub releases through Plugin Update Checker.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Update_Checker {

	const REPOSITORY = 'https://github.com/luberan/balikovna-woocommerce/';
	const SLUG       = 'balikovna-woocommerce';
	const ASSET      = '/^balikovna-woocommerce\.zip$/i';

	/**
	 * Release notes keyed by the version offered by GitHub.
	 *
	 * @var array<string,string>
	 */
	private static $release_notes = array();

	public static function init() {
		$library = BALIKOVNA_WC_PATH . 'includes/lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $library ) ) {
			return;
		}
		require_once $library;
		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}
		try {
			$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( self::REPOSITORY, BALIKOVNA_WC_FILE, self::SLUG );
			$checker->setBranch( 'main' );
			add_filter( $checker->getUniqueName( 'vcs_update_detection_strategies' ), array( __CLASS__, 'filter_strategies' ) );
			$api = $checker->getVcsApi();
			if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
				$api->enableReleaseAssets( self::ASSET, \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS );
			}
			add_filter( $checker->getUniqueName( 'request_info_result' ), array( __CLASS__, 'filter_info' ) );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[Balíkovna] update checker init failed: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Use only published releases with the built ZIP and keep their notes.
	 *
	 * @param array $strategies Update detection strategies.
	 * @return array
	 */
	public static function filter_strategies( $strategies ) {
		$strategies = array_intersect_key( (array) $strategies, array( 'latest_release' => true ) );
		if ( isset( $strategies['latest_release'] ) && is_callable( $strategies['latest_release'] ) ) {
			$find                         = $strategies['latest_release'];
			$strategies['latest_release'] = function () use ( $find ) {
				$reference = call_user_func( $find );
				if ( is_object( $reference ) && ! empty( $reference->version ) && ! empty( $reference->changelog ) ) {
					self::$release_notes[ (string) $reference->version ] = (string) $reference->changelog;
				}
				return $reference;
			};
		}
		return $strategies;
	}

	/**
	 * Show the offered release notes above the full changelog history.
	 *
	 * The readme.txt of a release tag is synchronized only after tagging, so
	 * neither it nor the installed readme contains the version being offered.
	 *
	 * @param object $plugin_info Plugin Update Checker info.
	 * @return object
	 */
	public static function filter_info( $plugin_info ) {
		if ( ! is_object( $plugin_info ) ) {
			return $plugin_info;
		}
		$sections = isset( $plugin_info->sections ) && is_array( $plugin_info->sections ) ? $plugin_info->sections : array();
		$version  = isset( $plugin_info->version ) ? (string) $plugin_info->version : '';
		$notes    = '' !== $version && isset( self::$release_notes[ $version ] ) ? self::$release_notes[ $version ] : '';
		$history  = isset( $sections['changelog'] ) ? trim( (string) $sections['changelog'] ) : '';
		// Plugin Update Checker's own placeholder for a missing changelog.
		$missing = __( 'There is no changelog available.', 'plugin-update-checker' );
		if ( '' === $history || trim( $notes ) === $history || $missing === $history ) {
			$history = self::local_changelog();
		}
		if ( '' !== $notes && ! self::mentions_version( $history, $version ) ) {
			$history = self::notes_as_history( $notes, $version ) . "\n" . $history;
		}
		if ( '' !== $history ) {
			$sections['changelog'] = $history;
		}
		$plugin_info->sections = $sections;
		return $plugin_info;
	}

	/**
	 * Convert the == Changelog == section of the installed readme.txt to HTML.
	 */
	public static function local_changelog() {
		$readme_path = BALIKOVNA_WC_PATH . 'readme.txt';
		if ( ! is_readable( $readme_path ) ) {
			return '';
		}
		$readme = file_get_contents( $readme_path );
		if ( ! $readme || ! preg_match( '/^==\s*Changelog\s*==\s*$(.*?)(?=^==\s|\z)/sm', $readme, $m ) ) {
			return '';
		}
		$body = trim( $m[1] );
		$body = preg_replace( '/^=\s*([^=]+?)\s*=\s*$/m', '<h4>$1</h4>', $body );
		$body = preg_replace_callback(
			'/(?:^\*\s+.+(?:\r?\n|$))+/m',
			function ( $block ) {
				$items = preg_replace( '/^\*\s+(.+)$/m', '<li>$1</li>', rtrim( $block[0] ) );
				return "<ul>{$items}</ul>\n";
			},
			$body
		);
		return (string) $body;
	}

	private static function mentions_version( $html, $version ) {
		return '' !== $version
			&& 1 === preg_match( '#<h[1-6][^>]*>\s*(?:<a[^>]*>\s*)?v?' . preg_quote( $version, '#' ) . '(?![0-9A-Za-z.-])#', (string) $html );
	}

	/**
	 * Render release-please notes like the readme.txt history.
	 *
	 * The notes start with an h2 that the WordPress dialog pushes below its
	 * sidebar, so they become one h4 version heading with "Section: entry"
	 * items without commit links, as in the readme changelog.
	 *
	 * @param string $notes   Release notes HTML from Plugin Update Checker.
	 * @param string $version Offered version.
	 * @return string
	 */
	private static function notes_as_history( $notes, $version ) {
		$items = array();
		$title = '';
		foreach ( preg_split( '#(<h[1-6][^>]*>.*?</h[1-6]>)#is', $notes, -1, PREG_SPLIT_DELIM_CAPTURE ) as $part ) {
			if ( preg_match( '#^<h([1-6])[^>]*>(.*?)</h\1>$#is', $part, $heading ) ) {
				// The h2 names the release, lower headings its sections.
				$title = (int) $heading[1] > 2 ? trim( wp_strip_all_tags( $heading[2] ) ) : '';
				continue;
			}
			preg_match_all( '#<li[^>]*>(.*?)</li>#is', $part, $entries );
			foreach ( $entries[1] as $entry ) {
				$entry   = trim( preg_replace( '#\s*\(<a\b[^>]*>[0-9a-f]{7,40}</a>\)\s*$#i', '', $entry ) );
				$items[] = '<li>' . ( '' !== $title ? esc_html( $title ) . ': ' : '' ) . $entry . '</li>';
			}
		}
		if ( ! $items ) {
			// Unknown format: keep the notes, but without headings that clear the sidebar.
			return (string) preg_replace( '#<(/?)h[1-3](?=[\s>])#i', '<$1h4', $notes );
		}
		return '<h4>' . esc_html( $version ) . "</h4>\n<ul>\n" . implode( "\n", $items ) . "\n</ul>";
	}
}
