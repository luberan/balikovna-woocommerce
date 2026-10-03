<?php
/**
 * WooCommerce Blocks integration for the pickup picker.
 *
 * Loaded only when WooCommerce Blocks provides IntegrationInterface.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined( 'ABSPATH' ) || exit;

class Blocks_Integration implements IntegrationInterface {

	const HANDLE = 'balikovna-wc-blocks';

	public function get_name() {
		return Blocks::NS;
	}

	public function initialize() {
		wp_register_script(
			self::HANDLE,
			BALIKOVNA_WC_URL . 'assets/js/checkout-block.js',
			array( 'wp-element', 'wp-data', 'wp-plugins', 'wc-blocks-checkout', 'wc-settings' ),
			BALIKOVNA_WC_VERSION,
			true
		);
	}

	public function get_script_handles() {
		return array( self::HANDLE );
	}

	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Data exposed to the script as wcSettings `balikovna-wc_data`.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'services' => Checkout::picker_services(),
			'debug'    => Plugin::is_debug(),
			'i18n'     => Checkout::picker_i18n(),
		);
	}
}
