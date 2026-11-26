<?php
/**
 * Personal data: privacy policy text, export and erasure of shipment data.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Privacy {

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'add_policy_content' ) );
		add_filter( 'woocommerce_privacy_export_order_personal_data', array( __CLASS__, 'export_order_data' ), 10, 2 );
		add_action( 'woocommerce_privacy_remove_order_personal_data', array( __CLASS__, 'remove_order_data' ) );
	}

	/**
	 * Suggest a paragraph for the privacy policy of the shop.
	 */
	public static function add_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$paragraphs = array(
			__( 'Při výběru výdejního místa se v pokladně načítá mapa České pošty z adresy b2c.cpost.cz. Česká pošta při tom zpracovává technické údaje o vašem zařízení a, pokud to povolíte, také vaši polohu.', 'balikovna-wc' ),
			__( 'K objednávce ukládáme vybrané výdejní místo, podací číslo zásilky a její poslední stav. Podací číslo předáváme České poště, když zjišťujeme stav zásilky.', 'balikovna-wc' ),
		);
		wp_add_privacy_policy_content(
			'Balíkovna for WooCommerce',
			wp_kses_post( '<p>' . implode( '</p><p>', array_map( 'esc_html', $paragraphs ) ) . '</p>' )
		);
	}

	/**
	 * Add pickup points and tracking numbers to the personal data export of an order.
	 *
	 * @param mixed $personal_data Name/value pairs collected by WooCommerce.
	 * @param mixed $order         Exported order.
	 * @return mixed
	 */
	public static function export_order_data( $personal_data, $order ) {
		if ( ! is_array( $personal_data ) || ! $order instanceof \WC_Order ) {
			return $personal_data;
		}
		foreach ( Order::get_shipments( $order ) as $shipment ) {
			if ( ! empty( $shipment['point']['id'] ) ) {
				$personal_data[] = array(
					'name'  => __( 'Výdejní místo', 'balikovna-wc' ),
					'value' => str_replace( "\n", ', ', Order::format_point( $shipment['point'] ) ),
				);
			}
			if ( '' !== $shipment['trackingNumber'] ) {
				$personal_data[] = array(
					'name'  => __( 'Podací číslo', 'balikovna-wc' ),
					'value' => $shipment['trackingNumber'],
				);
			}
		}
		return $personal_data;
	}

	/**
	 * Remove the pickup point and the parcel tracking data when WooCommerce anonymizes an order.
	 *
	 * The service, weight and contents value stay: they do not identify the customer.
	 *
	 * @param mixed $order Anonymized order.
	 */
	public static function remove_order_data( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		foreach ( Order::get_shipments( $order ) as $shipment ) {
			$item = $shipment['item'];
			$item->delete_meta_data( Order::META_KEY );
			$item->delete_meta_data( Order::META_TRACKING_NUMBER );
			Order::clear_tracking_status( $item );
			$item->save();
		}
		foreach ( array( Order::META_KEY, '_balikovna_point_id', '_balikovna_point_name', '_balikovna_points' ) as $meta_key ) {
			$order->delete_meta_data( $meta_key );
		}
		$order->save();
	}
}
