<?php

use Balikovna_WC\Order;
use Balikovna_WC\Order_Status_Mapper;
use Balikovna_WC\Order_Write_Guard;
use Balikovna_WC\Tracking_Settings;
use PHPUnit\Framework\TestCase;

final class OrderWritesTest extends TestCase {
	private $created = array();

	protected function tearDown(): void {
		foreach ( $this->created as $order_id ) {
			$order = new WC_Order( $order_id );
			$order->delete( true );
		}
	}

	private function order() {
		$order = wc_create_order( array( 'status' => 'processing' ) );
		$this->created[] = $order->get_id();
		$item = new WC_Order_Item_Shipping();
		$item->set_method_id( 'balikovna' );
		$item->set_instance_id( 4 );
		foreach ( array( Order::META_TRACKING_NUMBER => 'BA1234567890A', Order::META_STATUS_TRACKING_NUMBER => 'BA1234567890A', Order::META_STATUS_CODE => '91/00', Order::META_STATUS_LABEL => 'DORUCENO' ) as $key => $value ) {
			$item->update_meta_data( $key, $value );
		}
		$order->add_item( $item );
		$order->save();
		return new WC_Order( $order->get_id() );
	}

	private function settings() {
		return array_merge( Tracking_Settings::defaults(), array( 'auto_order_status' => true, 'status_mappings' => array( '91/00' => 'wc-completed' ) ) );
	}

	private function database_status( $order_id ) {
		global $wpdb;
		$hpos = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT %i FROM %i WHERE %i = %d', $hpos ? 'status' : 'post_status', $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts, $hpos ? 'id' : 'ID', $order_id ) );
	}

	private function customer_order() {
		$order = $this->order();
		$order->set_billing_email( 'integration-customer@example.test' );
		$order->save();
		return new WC_Order( $order->get_id() );
	}

	public function test_status_hooks_and_emails_run_after_the_guarded_commit(): void {
		global $wpdb;
		$order      = $this->customer_order();
		$observed   = array();
		$connection = new PDO( 'mysql:host=' . ( getenv( 'BALIKOVNA_TEST_DB_HOST' ) ?: '127.0.0.1' ) . ';port=' . ( getenv( 'BALIKOVNA_TEST_DB_PORT' ) ?: '3306' ) . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
		$connection->exec( 'SET SESSION innodb_lock_wait_timeout = 1' );
		$hpos  = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$table = $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts;
		$id    = $hpos ? 'id' : 'ID';
		$sql   = $wpdb->prepare( 'UPDATE %i SET %i = %i WHERE %i = %d', $table, $id, $id, $id, $order->get_id() );
		$probe = function () use ( $wpdb, $connection, $sql, &$observed ) {
			$observed['hook']      = (string) $wpdb->get_var( 'SELECT @@SESSION.in_transaction' );
			$observed['competing'] = $connection->exec( $sql );
		};
		$mail  = function ( $result ) use ( $wpdb, &$observed ) {
			$observed['mail'][] = (string) $wpdb->get_var( 'SELECT @@SESSION.in_transaction' );
			return $result;
		};
		add_action( 'woocommerce_order_status_completed', $probe, 1 );
		add_filter( 'pre_wp_mail', $mail, 5 );
		try {
			$this->assertSame( 'wc-completed', ( new Order_Status_Mapper() )->apply( $order, Order::get_shipments( $order ), $this->settings() ) );
		} finally {
			remove_action( 'woocommerce_order_status_completed', $probe, 1 );
			remove_filter( 'pre_wp_mail', $mail, 5 );
		}
		$this->assertSame( '0', $observed['hook'], 'Status hooks must not run inside the guard transaction.' );
		$this->assertSame( 0, $observed['competing'], 'A concurrent writer is not blocked by status hooks.' );
		$this->assertNotEmpty( $observed['mail'] );
		$this->assertSame( array( '0' ), array_unique( $observed['mail'] ) );
		$this->assertSame( 'wc-completed', $this->database_status( $order->get_id() ) );
	}

	public function test_failing_status_hook_cannot_roll_back_or_repeat_the_completion(): void {
		$order = $this->customer_order();
		$mails = 0;
		$count = function ( $result ) use ( &$mails ) {
			++$mails;
			return $result;
		};
		$throw = function () {
			throw new TypeError( 'Simulated third-party hook failure' );
		};
		add_filter( 'pre_wp_mail', $count, 5 );
		add_action( 'woocommerce_order_status_completed', $throw, 100 );
		try {
			for ( $attempt = 0; $attempt < 2; ++$attempt ) {
				$fresh = new WC_Order( $order->get_id() );
				try {
					( new Order_Status_Mapper() )->apply( $fresh, Order::get_shipments( $fresh ), $this->settings() );
				} catch ( TypeError $error ) {
					$this->assertSame( 'Simulated third-party hook failure', $error->getMessage() );
				}
			}
		} finally {
			remove_filter( 'pre_wp_mail', $count, 5 );
			remove_action( 'woocommerce_order_status_completed', $throw, 100 );
		}
		$this->assertSame( 'wc-completed', $this->database_status( $order->get_id() ) );
		$this->assertSame( 'completed', wc_get_order( $order->get_id() )->get_status() );
		$this->assertSame( 1, $mails, 'The customer receives the completion e-mail once.' );
	}

	public function test_runtime_versions_and_bootstrap_have_no_plugin_diagnostics(): void {
		$versions = json_decode( file_get_contents( dirname( __DIR__ ) . '/versions.json' ), true );
		$this->assertSame( $versions['wordpress'], get_bloginfo( 'version' ) );
		$this->assertSame( $versions['woocommerce'], WC_VERSION );
		$this->assertSame( 'hpos' === getenv( 'BALIKOVNA_TEST_STORAGE' ), Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() );
		$this->assertSame( array(), $GLOBALS['balikovna_integration_errors'] );
	}

	public function test_mapper_updates_the_current_order_and_emits_one_transition(): void {
		$order = $this->order();
		$count = 0;
		$listener = function () use ( &$count ) { ++$count; };
		add_action( 'woocommerce_order_status_completed', $listener );
		try {
			$mapper = new Order_Status_Mapper();
			$this->assertSame( 'wc-completed', $mapper->apply( $order, Order::get_shipments( $order ), $this->settings() ) );
			$this->assertSame( '', $mapper->apply( $order, Order::get_shipments( $order ), $this->settings() ) );
			$this->assertSame( 'completed', ( new WC_Order( $order->get_id() ) )->get_status() );
			$this->assertSame( 1, $count );
		} finally {
			remove_action( 'woocommerce_order_status_completed', $listener );
		}
	}

	public function test_stale_object_cannot_overwrite_committed_manual_cancellation(): void {
		$loaded = $this->order();
		$shipments = Order::get_shipments( $loaded );
		$concurrent = new WC_Order( $loaded->get_id() );
		$concurrent->update_status( 'cancelled' );
		$this->assertFalse( ( new Order_Status_Mapper() )->apply( $loaded, $shipments, $this->settings() ) );
		$this->assertSame( 'cancelled', ( new WC_Order( $loaded->get_id() ) )->get_status() );
	}

	public function test_changed_tracking_number_blocks_stale_mapping(): void {
		$loaded = $this->order();
		$shipments = Order::get_shipments( $loaded );
		$changed = new WC_Order_Item_Shipping( $shipments[0]['item']->get_id() );
		$changed->update_meta_data( Order::META_TRACKING_NUMBER, 'BA9999999999A' );
		$changed->save();
		$this->assertFalse( ( new Order_Status_Mapper() )->apply( $loaded, $shipments, $this->settings() ) );
		$this->assertSame( 'processing', ( new WC_Order( $loaded->get_id() ) )->get_status() );
	}

	public function test_guard_never_commits_or_rolls_back_an_outer_transaction(): void {
		global $wpdb;
		$order = $this->order();
		$wpdb->query( 'START TRANSACTION' );
		try {
			$this->assertFalse( ( new Order_Write_Guard() )->run( $order, function () { $this->fail( 'Must not enter an existing transaction.' ); } ) );
			$this->assertSame( '1', (string) $wpdb->get_var( 'SELECT @@SESSION.in_transaction' ) );
		} finally {
			$wpdb->query( 'ROLLBACK' );
		}
	}

	public function test_innodb_lock_blocks_a_competing_connection_during_the_write(): void {
		global $wpdb;
		$order = $this->order();
		$connection = new PDO( 'mysql:host=' . ( getenv( 'BALIKOVNA_TEST_DB_HOST' ) ?: '127.0.0.1' ) . ';port=' . ( getenv( 'BALIKOVNA_TEST_DB_PORT' ) ?: '3306' ) . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
		$connection->exec( 'SET SESSION innodb_lock_wait_timeout = 1' );
		$hpos = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$sql = $wpdb->prepare( 'UPDATE %i SET %i = %s WHERE %i = %d', $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts, $hpos ? 'status' : 'post_status', 'wc-cancelled', $hpos ? 'id' : 'ID', $order->get_id() );
		$result = ( new Order_Write_Guard() )->run( $order, function () use ( $connection, $sql, $order ) {
			try {
				$connection->exec( $sql );
				$this->fail( 'Competing writer acquired a row that must be locked.' );
			} catch ( PDOException $error ) {
				$this->assertSame( 1205, (int) $error->errorInfo[1] );
			}
			return $order->update_status( 'completed' );
		} );
		$this->assertTrue( $result );
		$this->assertSame( 'completed', ( new WC_Order( $order->get_id() ) )->get_status() );
	}

	public function test_full_synchronizer_saves_current_responses_and_discards_changed_tracking(): void {
		foreach ( array( false, true ) as $concurrent_change ) {
			$order = $this->order();
			$item = array_values( $order->get_shipping_methods() )[0];
			Order::clear_tracking_status( $item );
			$item->save();
			$transport = new class( $item->get_id(), $concurrent_change ) implements \Balikovna_WC\Napi_Transport_Interface {
				private $item_id;
				private $change;
				public function __construct( $item_id, $change ) { $this->item_id = $item_id; $this->change = $change; }
				public function request( $method, $url, array $args ) {
					if ( $this->change ) {
						$updated = new WC_Order_Item_Shipping( $this->item_id );
						$updated->update_meta_data( Order::META_TRACKING_NUMBER, 'BA9999999999A' );
						$updated->save();
					}
					return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'idParcel' => 'BA1234567890A', 'parcelStatus' => array( 'statusID' => '91', 'reasonID' => '00', 'statusDescription' => 'DORUCENO', 'datetime' => gmdate( 'c' ) ) ) ) );
				}
			};
			$client = new \Balikovna_WC\Napi_Client( new \Balikovna_WC\Napi_Authentication( 'fixture-token', 'fixture-secret' ), $transport );
			$sync = new \Balikovna_WC\Shipment_Synchronizer( $client, new \Balikovna_WC\Status_Dictionary( $client ), new \Balikovna_WC\Eligible_Orders(), new Order_Status_Mapper(), new \Balikovna_WC\Tracking_Logger() );
			$result = $sync->sync_order( $order, $this->settings() );
			$fresh = new WC_Order( $order->get_id() );
			$fresh_item = array_values( $fresh->get_shipping_methods() )[0];
			$this->assertSame( $concurrent_change ? 0 : 1, $result['checked'] );
			$this->assertSame( $concurrent_change ? 'processing' : 'completed', $fresh->get_status() );
			$this->assertSame( $concurrent_change ? '' : '91/00', $fresh_item->get_meta( Order::META_STATUS_CODE ) );
			$this->assertSame( $concurrent_change ? 'BA9999999999A' : 'BA1234567890A', $fresh_item->get_meta( Order::META_TRACKING_NUMBER ) );
		}
	}
}