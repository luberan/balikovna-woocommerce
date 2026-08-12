<?php
/**
 * HPOS-safe selection of orders eligible for shipment-status synchronization.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Eligible_Orders {

	const CURSOR_OPTION = 'balikovna_wc_tracking_order_page';

	/**
	 * Return at most the configured number of eligible orders.
	 *
	 * A persistent page cursor rotates through the broad WooCommerce result set,
	 * so a permanently active oldest parcel cannot starve newer orders.
	 *
	 * @return array<int,\WC_Order>
	 */
	public function find( array $settings ) {
		$statuses = isset( $settings['order_statuses'] ) ? (array) $settings['order_statuses'] : array();
		if ( ! $statuses || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$limit          = max( 1, min( Tracking_Settings::MAX_BATCH_SIZE, (int) ( $settings['batch_size'] ?? Tracking_Settings::DEFAULT_BATCH_SIZE ) ) );
		$page           = max( 1, (int) get_option( self::CURSOR_OPTION, 1 ) );
		$max_scan_pages = max( 1, min( 100, (int) apply_filters( 'balikovna_wc_tracking_scan_pages', 10, $settings ) ) );
		$orders         = array();
		$seen_order_ids = array();
		$scanned        = 0;
		$max_pages      = 0;

		while ( count( $orders ) < $limit && $scanned < $max_scan_pages ) {
			$result = wc_get_orders(
				array(
					'status'       => array_values( $statuses ),
					'date_created' => '>' . $this->cutoff_timestamp( $settings ),
					'limit'        => $limit,
					'page'         => $page,
					'paginate'     => true,
					'orderby'      => 'date',
					'order'        => 'ASC',
					'return'       => 'objects',
				)
			);

			$page_orders = is_object( $result ) && isset( $result->orders ) && is_array( $result->orders )
				? $result->orders
				: ( is_array( $result ) ? $result : array() );
			$max_pages   = is_object( $result ) && isset( $result->max_num_pages )
				? max( 0, (int) $result->max_num_pages )
				: ( $page_orders ? $page : 0 );

			if ( ! $page_orders && $page > 1 ) {
				$page = 1;
				++$scanned;
				continue;
			}

			foreach ( $page_orders as $order ) {
				$order_id = $order instanceof \WC_Order ? (int) $order->get_id() : 0;
				if ( $order_id && isset( $seen_order_ids[ $order_id ] ) ) {
					continue;
				}
				if ( $order_id ) {
					$seen_order_ids[ $order_id ] = true;
				}
				if ( $order instanceof \WC_Order && $this->is_order_eligible( $order, $settings ) && $this->has_work( $order, $settings ) ) {
					$orders[] = $order;
				}
			}

			++$scanned;
			++$page;
			if ( $max_pages > 0 && $page > $max_pages ) {
				$page = 1;
			}
			if ( 0 === $max_pages ) {
				break;
			}
			if ( $scanned >= $max_pages ) {
				break;
			}
		}

		update_option( self::CURSOR_OPTION, $page, false );
		return array_slice( $orders, 0, $limit );
	}

	public function is_order_eligible( \WC_Order $order, array $settings ) {
		$status = Tracking_Settings::normalize_order_status( $order->get_status() );
		if ( ! in_array( $status, (array) ( $settings['order_statuses'] ?? array() ), true ) ) {
			return false;
		}

		$date_created = $order->get_date_created();
		if ( ! $date_created || ! is_callable( array( $date_created, 'getTimestamp' ) ) ) {
			return false;
		}
		return (int) $date_created->getTimestamp() >= $this->cutoff_timestamp( $settings );
	}

	public function has_work( \WC_Order $order, array $settings ) {
		foreach ( Order::get_shipments( $order ) as $shipment ) {
			if ( ! Napi_Client::is_valid_parcel_id( $shipment['trackingNumber'] ) ) {
				continue;
			}
			$item            = $shipment['item'];
			$code            = (string) $item->get_meta( Order::META_STATUS_CODE, true );
			$status_tracking = (string) $item->get_meta( Order::META_STATUS_TRACKING_NUMBER, true );
			$eligible        = (bool) apply_filters( 'balikovna_wc_tracking_shipment_eligible', true, $shipment, $order, $settings );
			if ( $eligible && '' !== $code && $status_tracking !== $shipment['trackingNumber'] ) {
				return true;
			}
			if ( $eligible && Tracking_Settings::should_poll( $code, $settings ) ) {
				return true;
			}
			if ( $eligible && ! empty( $settings['auto_order_status'] ) && '' !== $code && $this->needs_mapping_evaluation( $item, $code, $settings ) ) {
				return true;
			}
		}
		return false;
	}

	public function needs_mapping_evaluation( \WC_Order_Item_Shipping $item, $code, array $settings ) {
		return (string) $item->get_meta( Order::META_STATUS_EVALUATED_CODE, true ) !== (string) $code
			|| (int) $item->get_meta( Order::META_STATUS_MAPPING_REVISION, true ) !== (int) ( $settings['mapping_revision'] ?? 1 );
	}

	private function cutoff_timestamp( array $settings ) {
		$days = max( 1, min( Tracking_Settings::MAX_TRACKING_DAYS, (int) ( $settings['tracking_days'] ?? Tracking_Settings::DEFAULT_TRACKING_DAYS ) ) );
		return time() - ( $days * DAY_IN_SECONDS );
	}
}
