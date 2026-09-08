<?php
/**
 * Short, optimistic order writes protected by InnoDB row locks.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Order_Write_Guard {

	private $database;
	private $engines = array();

	public function __construct( $database = null ) {
		$this->database = $database;
	}

	public function snapshot( \WC_Order $order ) {
		$items = array();
		foreach ( $order->get_shipping_methods() as $item ) {
			$values                = $this->empty_item();
			$values['method_id']   = (string) $item->get_method_id();
			$values['instance_id'] = (string) $item->get_instance_id();
			foreach ( $values as $key => $value ) {
				if ( 'method_id' !== $key && 'instance_id' !== $key ) {
					$values[ $key ] = (string) $item->get_meta( $key, true );
				}
			}
			$items[ (int) $item->get_id() ] = $values;
		}
		ksort( $items, SORT_NUMERIC );
		return array(
			'status' => Tracking_Settings::normalize_order_status( $order->get_status() ),
			'items'  => $items,
		);
	}

	public function run( \WC_Order $order, callable $write, ?array $expected = null ) {
		global $wpdb;
		$database = $this->database ? $this->database : $wpdb;
		$expected = null === $expected ? $this->snapshot( $order ) : $expected;
		$hpos     = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$table    = $hpos ? $database->prefix . 'wc_orders' : $database->posts;
		$items    = $database->prefix . 'woocommerce_order_items';
		$meta     = $database->prefix . 'woocommerce_order_itemmeta';
		if ( ! $order->get_id() || isset( $expected['items'][0] ) ) {
			return false;
		}

		foreach ( array( $table, $items, $meta ) as $required_table ) {
			if ( ! isset( $this->engines[ $required_table ] ) ) {
				$this->engines[ $required_table ] = $database->get_var(
					$database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $required_table )
				);
			}
			if ( 'innodb' !== strtolower( (string) $this->engines[ $required_table ] ) ) {
				return false;
			}
		}

		$previous_errors  = $database->suppress_errors( true );
		$previous_timeout = (int) $database->get_var( 'SELECT @@SESSION.innodb_lock_wait_timeout' );
		$started          = false;
		try {
			if ( $previous_timeout < 1 || false === $database->query( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ) ) {
				return false;
			}
			if ( false === $database->query( 'SET SESSION innodb_lock_wait_timeout = 2' ) || false === $database->query( 'START TRANSACTION' ) ) {
				return false;
			}
			$started = true;
			$status  = $database->get_var(
				$database->prepare( 'SELECT %i FROM %i WHERE %i = %d FOR UPDATE', $hpos ? 'status' : 'post_status', $table, $hpos ? 'id' : 'ID', $order->get_id() )
			);
			if ( $database->last_error || null === $status || (string) $status !== $expected['status'] ) {
				return false;
			}
			$item_ids = $database->get_col(
				$database->prepare( 'SELECT order_item_id FROM %i WHERE order_id = %d AND order_item_type = %s ORDER BY order_item_id FOR UPDATE', $items, $order->get_id(), 'shipping' )
			);
			if ( $database->last_error || array_map( 'intval', $item_ids ) !== array_keys( $expected['items'] ) ) {
				return false;
			}
			foreach ( $item_ids as $item_id ) {
				$rows = $database->get_results(
					$database->prepare( 'SELECT meta_key, meta_value FROM %i WHERE order_item_id = %d ORDER BY meta_id FOR UPDATE', $meta, $item_id ),
					ARRAY_A
				);
				if ( $database->last_error || ! is_array( $rows ) ) {
					return false;
				}
				$actual = $this->empty_item();
				$seen   = array();
				foreach ( $rows as $row ) {
					$key = $row['meta_key'];
					if ( array_key_exists( $key, $actual ) && ! isset( $seen[ $key ] ) ) {
						$actual[ $key ] = (string) $row['meta_value'];
						$seen[ $key ]   = true;
					}
				}
				if ( $actual !== $expected['items'][ $item_id ] ) {
					return false;
				}
			}
			$database->suppress_errors( $previous_errors );
			$result = $write();
			if ( false === $result || false === $database->query( 'COMMIT' ) ) {
				return false;
			}
			$started = false;
			return $result;
		} finally {
			if ( $started ) {
				$database->query( 'ROLLBACK' );
			}
			if ( $previous_timeout > 0 ) {
				$database->query( $database->prepare( 'SET SESSION innodb_lock_wait_timeout = %d', $previous_timeout ) );
			}
			$database->suppress_errors( $previous_errors );
		}
	}

	private function empty_item() {
		return array_fill_keys(
			array(
				'method_id',
				'instance_id',
				Order::META_TRACKING_NUMBER,
				Order::META_STATUS_TRACKING_NUMBER,
				Order::META_STATUS_CODE,
				Order::META_STATUS_LABEL,
				Order::META_STATUS_EVENT_AT,
			),
			''
		);
	}
}
