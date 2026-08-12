<?php
/**
 * Conservative carrier-to-WooCommerce order-status mapping.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Order_Status_Mapper {

	private static $progression = array(
		'wc-shipped'      => 1,
		'wc-ready-pickup' => 2,
		'wc-completed'    => 3,
	);

	/**
	 * Apply one safe transition, if the complete parcel set has an unambiguous target.
	 *
	 * @return string|false Target wc-* status, empty when unchanged, false on write failure.
	 */
	public function apply( \WC_Order $order, array $shipments, array $settings ) {
		if ( empty( $settings['auto_order_status'] ) ) {
			return '';
		}

		$target  = $this->resolve_target( $order, $shipments, $settings );
		$current = Tracking_Settings::normalize_order_status( $order->get_status() );
		if ( '' === $target || $target === $current || in_array( $current, array( 'wc-cancelled', 'wc-refunded', 'wc-failed' ), true ) ) {
			return '';
		}
		if ( 'wc-completed' === $current && 'wc-completed' !== $target ) {
			return '';
		}

		$current_rank = isset( self::$progression[ $current ] ) ? self::$progression[ $current ] : 0;
		$target_rank  = isset( self::$progression[ $target ] ) ? self::$progression[ $target ] : 0;
		if ( $current_rank && $target_rank && $target_rank <= $current_rank ) {
			return '';
		}

		$updated = $order->update_status( substr( $target, 3 ) );
		if ( false === $updated ) {
			return false;
		}
		do_action( 'balikovna_wc_order_status_mapped', $order, $current, $target, $shipments );
		return $target;
	}

	public function resolve_target( \WC_Order $order, array $shipments, array $settings ) {
		$entries = array();
		foreach ( $shipments as $shipment ) {
			if ( empty( $shipment['trackingNumber'] ) ) {
				continue;
			}
			$item = $shipment['item'];
			$code = (string) $item->get_meta( Order::META_STATUS_CODE, true );
			if ( '' === $code ) {
				return '';
			}

			$target = Tracking_Settings::mapping_for( $code, $settings );
			$target = (string) apply_filters( 'balikovna_wc_carrier_status_mapping', $target, $code, $order, $shipment, $settings );
			$target = Tracking_Settings::normalize_order_status( $target );
			if ( ! isset( Tracking_Settings::order_statuses()[ $target ] ) ) {
				$target = '';
			}
			$semantic = Shipment_Status::semantic_for_label( $item->get_meta( Order::META_STATUS_LABEL, true ) );
			if ( 'wc-completed' === $target && Shipment_Status::SEMANTIC_DELIVERED !== $semantic ) {
				return '';
			}
			if ( '' === $target && Shipment_Status::SEMANTIC_DELIVERED !== $semantic ) {
				return '';
			}

			$entries[] = array(
				'target' => $target,
				'rank'   => isset( self::$progression[ $target ] )
					? self::$progression[ $target ]
					: ( Shipment_Status::SEMANTIC_DELIVERED === $semantic ? 3 : 0 ),
			);
		}

		if ( ! $entries ) {
			return '';
		}
		$mapped = array_values( array_filter( array_column( $entries, 'target' ) ) );
		if ( ! $mapped ) {
			return '';
		}
		if ( 1 === count( $entries ) ) {
			return $mapped[0];
		}

		$custom_targets = array_values(
			array_filter(
				$mapped,
				function ( $target ) {
					return ! isset( self::$progression[ $target ] );
				}
			)
		);
		if ( $custom_targets ) {
			$unique = array_values( array_unique( $mapped ) );
			return count( $mapped ) === count( $entries ) && 1 === count( $unique ) ? $unique[0] : '';
		}

		$ranks = array_column( $entries, 'rank' );
		if ( in_array( 0, $ranks, true ) ) {
			return '';
		}
		$rank = min( $ranks );
		return (string) array_search( $rank, self::$progression, true );
	}
}
