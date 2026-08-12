<?php
/**
 * Sanitized WooCommerce tracking logger.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Tracking_Logger {

	const SOURCE = 'balikovna-woocommerce-tracking';

	public function api_error( Napi_Error $error, $order_id = 0, $item_id = 0 ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		$logger = wc_get_logger();
		if ( ! $logger || ! is_callable( array( $logger, 'warning' ) ) ) {
			return;
		}
		$logger->warning(
			sprintf(
				/* translators: 1: sanitized API error code, 2: sanitized API error message. */
				__( 'Synchronizace stavu zásilky selhala (%1$s): %2$s', 'balikovna-wc' ),
				$error->get_code(),
				$error->get_message()
			),
			array(
				'source'           => self::SOURCE,
				'order_id'         => absint( $order_id ),
				'shipping_item_id' => absint( $item_id ),
			)
		);
	}
}
