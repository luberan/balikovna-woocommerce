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
	private $supported = null;

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

	/**
	 * Change the order status atomically with the snapshot check.
	 *
	 * The transaction is committed as soon as WooCommerce has persisted the
	 * order, i.e. before status-transition hooks, notes and e-mails run, so
	 * their side effects never hold row locks and cannot be rolled back.
	 *
	 * @return bool|false
	 */
	public function update_status( \WC_Order $order, $status, ?array $expected = null ) {
		return $this->run(
			$order,
			function () use ( $order, $status ) {
				return $order->update_status( $status );
			},
			$expected,
			'woocommerce_after_order_object_save'
		);
	}

	/**
	 * Whether the order tables support the transactional row locks used by run().
	 */
	public function is_supported() {
		if ( null !== $this->supported ) {
			return $this->supported;
		}
		$database        = $this->database();
		$previous_errors = $database->suppress_errors( true );
		$supported       = true;
		foreach ( $this->tables( $database ) as $table ) {
			$engine    = $database->get_var(
				$database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table )
			);
			$supported = $supported && 'innodb' === strtolower( (string) $engine );
		}
		// Emulated information_schema (e.g. SQLite) reports InnoDB without supporting its locks.
		$supported       = $supported && (int) $database->get_var( 'SELECT @@SESSION.innodb_lock_wait_timeout' ) > 0;
		$this->supported = $supported;
		$database->suppress_errors( $previous_errors );
		return $supported;
	}

	/**
	 * Run a write under row locks after verifying the expected order snapshot.
	 *
	 * @param \WC_Order  $order         Order to protect.
	 * @param callable   $write         Writer; returning false rolls the transaction back.
	 * @param array|null $expected      Snapshot taken before slow work.
	 * @param string     $commit_action Optional action fired for this order after its data is persisted;
	 *                                  the transaction is committed there instead of after the writer.
	 * @return mixed|false Writer result or false when the write was not committed.
	 */
	public function run( \WC_Order $order, callable $write, ?array $expected = null, $commit_action = '' ) {
		$database = $this->database();
		$expected = null === $expected ? $this->snapshot( $order ) : $expected;
		$tables   = $this->tables( $database );
		if ( ! $order->get_id() || isset( $expected['items'][0] ) || ! $this->is_supported() ) {
			return false;
		}

		$previous_errors  = $database->suppress_errors( true );
		$previous_timeout = (int) $database->get_var( 'SELECT @@SESSION.innodb_lock_wait_timeout' );
		$started          = false;
		$early_commit     = null;
		try {
			if ( $previous_timeout < 1 || false === $database->query( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' ) ) {
				return false;
			}
			if ( false === $database->query( 'SET SESSION innodb_lock_wait_timeout = 2' ) || false === $database->query( 'START TRANSACTION' ) ) {
				return false;
			}
			$started = true;
			$hpos    = $tables['order'] !== $database->posts;
			$status  = $database->get_var(
				$database->prepare( 'SELECT %i FROM %i WHERE %i = %d FOR UPDATE', $hpos ? 'status' : 'post_status', $tables['order'], $hpos ? 'id' : 'ID', $order->get_id() )
			);
			if ( $database->last_error || null === $status || (string) $status !== $expected['status'] ) {
				return false;
			}
			$item_ids = $database->get_col(
				$database->prepare( 'SELECT order_item_id FROM %i WHERE order_id = %d AND order_item_type = %s ORDER BY order_item_id FOR UPDATE', $tables['items'], $order->get_id(), 'shipping' )
			);
			if ( $database->last_error || array_map( 'intval', $item_ids ) !== array_keys( $expected['items'] ) ) {
				return false;
			}
			foreach ( $item_ids as $item_id ) {
				$rows = $database->get_results(
					$database->prepare( 'SELECT meta_key, meta_value FROM %i WHERE order_item_id = %d ORDER BY meta_id FOR UPDATE', $tables['meta'], $item_id ),
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
			if ( '' !== (string) $commit_action ) {
				$early_commit = function ( $saved ) use ( $database, $order, &$started ) {
					if ( $started && is_object( $saved ) && is_callable( array( $saved, 'get_id' ) )
						&& (int) $saved->get_id() === (int) $order->get_id() && false !== $database->query( 'COMMIT' ) ) {
						$started = false;
					}
				};
				add_action( $commit_action, $early_commit, PHP_INT_MIN );
			}
			$result = $write();
			if ( ! $started ) {
				return $result;
			}
			if ( false === $result || false === $database->query( 'COMMIT' ) ) {
				return false;
			}
			$started = false;
			return $result;
		} finally {
			if ( $early_commit ) {
				remove_action( $commit_action, $early_commit, PHP_INT_MIN );
			}
			if ( $started ) {
				$database->query( 'ROLLBACK' );
				$this->forget_cached_order( $order );
			}
			if ( $previous_timeout > 0 ) {
				$database->query( $database->prepare( 'SET SESSION innodb_lock_wait_timeout = %d', $previous_timeout ) );
			}
			$database->suppress_errors( $previous_errors );
		}
	}

	private function database() {
		global $wpdb;
		return $this->database ? $this->database : $wpdb;
	}

	private function tables( $database ) {
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		return array(
			'order' => $hpos ? $database->prefix . 'wc_orders' : $database->posts,
			'items' => $database->prefix . 'woocommerce_order_items',
			'meta'  => $database->prefix . 'woocommerce_order_itemmeta',
		);
	}

	/**
	 * Drop caches that hooks may have primed with rolled-back data.
	 */
	private function forget_cached_order( \WC_Order $order ) {
		$order_id = (int) $order->get_id();
		if ( function_exists( 'clean_post_cache' ) ) {
			clean_post_cache( $order_id );
		}
		if ( function_exists( 'wc_get_container' ) && class_exists( '\Automattic\WooCommerce\Caches\OrderCache' ) ) {
			try {
				wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class )->remove( $order_id );
			} catch ( \Throwable $error ) {
				unset( $error );
			}
		}
		wp_cache_delete( 'order-items-' . $order_id, 'orders' );
		foreach ( $order->get_shipping_methods() as $item ) {
			wp_cache_delete( 'item-' . $item->get_id(), 'order-items' );
			wp_cache_delete( $item->get_id(), 'order_item_meta' );
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
