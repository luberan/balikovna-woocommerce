<?php

use Balikovna_WC\Blocks;
use Balikovna_WC\Eligible_Orders;
use Balikovna_WC\Export;
use Balikovna_WC\Order;
use Balikovna_WC\Points;
use Balikovna_WC\Tracking_Settings;
use PHPUnit\Framework\TestCase;

final class WorkflowRegressionTest extends TestCase {
	private $created = array();
	private $products = array();

	protected function tearDown(): void {
		foreach ( $this->created as $order_id ) { ( new WC_Order( $order_id ) )->delete( true ); }
		foreach ( $this->products as $product_id ) { wc_get_product( $product_id )->delete( true ); }
		delete_option( Eligible_Orders::CURSOR_OPTION );
	}

	private function order( $method = 'balikovna_na_adresu', $tracking = '' ) {
		$order = wc_create_order( array( 'status' => 'processing' ) );
		$this->created[] = $order->get_id();
		$order->set_address( array( 'first_name' => 'Integration', 'last_name' => 'Customer', 'address_1' => 'Testovaci 1', 'city' => 'Praha', 'postcode' => '10000', 'country' => 'CZ', 'email' => 'integration@example.test', 'phone' => '+420700000001' ), 'billing' );
		$item = new WC_Order_Item_Shipping();
		$item->set_method_id( $method );
		$item->set_instance_id( 4 );
		$item->update_meta_data( Order::META_TRACKING_NUMBER, $tracking );
		$order->add_item( $item );
		$order->save();
		return $order;
	}

	public function test_existing_legacy_order_can_be_paid_without_cart_selection(): void {
		$order = $this->order( 'balikovna' );
		Order::save_point_to_order( $order, array( 'id' => 'B10000', 'name' => 'Legacy point' ), 'balikovna' );
		$order->save();
		( new Blocks() )->update_order_from_request( $order, new WP_REST_Request( 'POST', '/wc/store/v1/checkout/' . $order->get_id() ) );
		$this->assertSame( 'B10000', Order::get_point( $order )['id'] );
	}

	public function test_selector_keeps_the_remainder_of_a_partially_consumed_page(): void {
		register_post_status( 'wc-bwc-test', array( 'label' => 'Integration', 'public' => false, 'exclude_from_search' => false, 'internal' => false ) );
		$status_filter = function ( $statuses ) { $statuses['wc-bwc-test'] = 'Integration'; return $statuses; };
		add_filter( 'wc_order_statuses', $status_filter );
		try {
			$orders = array();
			foreach ( array( true, false, true, true ) as $tracked ) {
				$order = $this->order( 'balikovna', $tracked ? 'BA1234567890A' : '' );
				$order->set_status( 'bwc-test' );
				$order->set_date_created( time() - 100 + count( $orders ) );
				$order->save();
				$orders[] = $order;
			}
			$settings = array_merge( Tracking_Settings::defaults(), array( 'batch_size' => 2, 'order_statuses' => array( 'wc-bwc-test' ) ) );
			$selector = new Eligible_Orders();
			$first = $selector->find( $settings );
			$second = $selector->find( $settings );
			$this->assertSame( array( $orders[0]->get_id(), $orders[2]->get_id() ), array_map( function ( $order ) { return $order->get_id(); }, $first ) );
			$this->assertContains( $orders[3]->get_id(), array_map( function ( $order ) { return $order->get_id(); }, $second ) );
		} finally {
			remove_filter( 'wc_order_statuses', $status_filter );
			unset( $GLOBALS['wp_post_statuses']['wc-bwc-test'] );
		}
	}

	public function test_selector_isolates_an_order_that_woocommerce_cannot_load(): void {
		register_post_status( 'wc-bwc-test', array( 'label' => 'Integration', 'public' => false, 'exclude_from_search' => false, 'internal' => false ) );
		$status_filter = function ( $statuses ) { $statuses['wc-bwc-test'] = 'Integration'; return $statuses; };
		$broken_id     = 0;
		$class_filter  = function ( $classname, $order_type, $order_id ) use ( &$broken_id ) {
			if ( (int) $order_id === $broken_id ) {
				throw new TypeError( 'Corrupted order data' );
			}
			return $classname;
		};
		add_filter( 'wc_order_statuses', $status_filter );
		try {
			$orders = array();
			foreach ( array( 0, 1, 2 ) as $index ) {
				$order = $this->order( 'balikovna', 'BA1234567890A' );
				$order->set_status( 'bwc-test' );
				$order->set_date_created( time() - 100 + $index );
				$order->save();
				$orders[] = $order;
			}
			$broken_id = $orders[1]->get_id();
			// Orders created in this request are cached; WooCommerce must resolve their classes again.
			wp_cache_flush();
			add_filter( 'woocommerce_order_class', $class_filter, 10, 3 );
			$settings = array_merge( Tracking_Settings::defaults(), array( 'batch_size' => 3, 'order_statuses' => array( 'wc-bwc-test' ) ) );
			$selector = new Eligible_Orders();

			$found = $selector->find( $settings );

			$this->assertSame( array( $orders[0]->get_id(), $orders[2]->get_id() ), array_map( function ( $order ) { return $order->get_id(); }, $found ) );
			$errors = $selector->take_errors();
			$this->assertSame( array( $broken_id ), array_keys( $errors ) );
			$this->assertInstanceOf( TypeError::class, $errors[ $broken_id ] );
		} finally {
			remove_filter( 'woocommerce_order_class', $class_filter, 10 );
			remove_filter( 'wc_order_statuses', $status_filter );
			unset( $GLOBALS['wp_post_statuses']['wc-bwc-test'] );
		}
	}

	public function test_export_refuses_partial_weight_and_rechecks_updated_order_limits(): void {
		$order = $this->order();
		$contents = array();
		foreach ( array( '2', '' ) as $weight ) {
			$product = new WC_Product_Simple();
			$product->set_name( 'Weight fixture' );
			$product->set_regular_price( '100' );
			$product->set_weight( $weight );
			$product->save();
			$this->products[] = $product->get_id();
			$order->add_product( $product, 1 );
			$contents[] = array( 'data' => $product, 'quantity' => 1, 'line_total' => 100 );
		}
		$shipping = array_values( $order->get_shipping_methods() )[0];
		Order::instance()->add_shipping_item_metadata( $shipping, 0, array( 'contents' => $contents ), $order );
		$order->save();
		$export = new class extends Export { public function rows( $order ) { return $this->prepare_order_rows( $order ); } };
		$this->assertSame( '', $shipping->get_meta( Order::META_PACKAGE_WEIGHT ) );
		$this->assertInstanceOf( WP_Error::class, $export->rows( $order ) );
		$shipping->update_meta_data( Order::META_PACKAGE_WEIGHT, '20' );
		$shipping->save();
		$this->assertInstanceOf( WP_Error::class, $export->rows( $order ) );
	}

	public function test_pickup_outage_uses_one_upstream_attempt_and_stale_data_refreshes_in_background(): void {
		$original = $GLOBALS['wp_filter']['balikovna_wc_points_directory'] ?? null;
		remove_all_filters( 'balikovna_wc_points_directory' );
		$key = 'balikovna_wc_points_balikovny_v2';
		$this->clear_points( $key );
		$calls    = 0;
		$response = new WP_Error( 'offline', 'Fixture outage' );
		$http     = function ( $pre, $args, $url ) use ( &$calls, &$response ) {
			if ( 0 === strpos( $url, Points::API_URL ) ) {
				++$calls;
				return $response;
			}
			return $pre;
		};
		add_filter( 'pre_http_request', $http, 10, 3 );
		try {
			for ( $attempt = 0; $attempt < 3; ++$attempt ) {
				$this->assertInstanceOf( WP_Error::class, Points::validate( array( 'id' => 'B10000' ), 'balikovna' ) );
			}
			$this->assertSame( 1, $calls, 'A cold outage is attempted once per cooldown.' );
			$this->assertFalse( get_option( $key . '_refresh_lock' ) );

			delete_transient( $key . '_retry_after' );
			$response = array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( array( 'id' => 'B10000', 'name' => 'Fresh', 'type' => 'BALIKOVNY' ), array( 'id' => 'B60200', 'name' => 'Brno', 'type' => 'BALIKOVNY' ) ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
			$this->assertSame( 'Fresh', Points::validate( array( 'id' => 'B10000' ), 'balikovna' )['name'] );
			$this->assertSame( array( 'B10000' ), array_keys( get_option( $key . '_10' ) ) );
			$this->assertSame( array( 'B60200' ), array_keys( get_option( $key . '_60' ) ) );
			$this->assertSame( 2, Points::directory_status( 'BALIKOVNY' )['count'] );

			$meta            = get_option( $key );
			$meta['updated'] = time() - 8 * DAY_IN_SECONDS;
			update_option( $key, $meta, false );
			$this->assertSame( 'Brno', Points::validate( array( 'id' => 'B60200' ), 'balikovna' )['name'] );
			$this->assertSame( 2, $calls, 'Stale data is served without a synchronous download.' );
			$this->assertNotFalse( as_has_scheduled_action( Balikovna_WC\Tracking_Scheduler::POINTS_HOOK, array( 'BALIKOVNY' ), Balikovna_WC\Tracking_Scheduler::GROUP ) );
		} finally {
			remove_filter( 'pre_http_request', $http, 10 );
			as_unschedule_all_actions( Balikovna_WC\Tracking_Scheduler::POINTS_HOOK );
			$this->clear_points( $key );
			if ( $original ) { $GLOBALS['wp_filter']['balikovna_wc_points_directory'] = $original; }
		}
	}

	private function clear_points( $key ) {
		foreach ( array( '', '_10', '_60', '_refresh_lock' ) as $suffix ) {
			delete_option( $key . $suffix );
		}
		delete_transient( $key . '_retry_after' );
	}

	public function test_option_lock_insert_race_on_the_real_database(): void {
		global $wpdb;
		$name = 'balikovna_test_lock_' . strtolower( wp_generate_password( 8, false ) );
		$this->assertFalse( get_option( $name ) );
		$owner = maybe_serialize( array( 'token' => 'process-b', 'expires' => time() + 60 ) );
		$connection = new PDO( 'mysql:host=' . ( getenv( 'BALIKOVNA_TEST_DB_HOST' ) ?: '127.0.0.1' ) . ';port=' . ( getenv( 'BALIKOVNA_TEST_DB_PORT' ) ?: '3306' ) . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
		try {
			// Another process inserts the lock after this request cached that it does not exist.
			$connection->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (?, ?, 'off')" )->execute( array( $name, $owner ) );
			$this->assertFalse( Balikovna_WC\Option_Lock::acquire( $name, time(), 60 ) );
			$this->assertSame( $owner, $connection->query( 'SELECT option_value FROM ' . $wpdb->options . ' WHERE option_name = ' . $connection->quote( $name ) )->fetchColumn() );
		} finally {
			$connection->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = ?" )->execute( array( $name ) );
		}
	}

	public function test_zone_modal_reports_an_invalid_weight_table(): void {
		$zone = new WC_Shipping_Zone();
		$zone->set_zone_name( 'Balikovna weight table regression' );
		$zone->save();
		try {
			$instance_id             = $zone->add_shipping_method( 'balikovna' );
			$_REQUEST['instance_id'] = $instance_id;
			$method                  = WC_Shipping_Zones::get_shipping_method( $instance_id );
			$method->set_post_data(
				array(
					'woocommerce_balikovna_title'        => 'Balikovna',
					'woocommerce_balikovna_tax_status'   => 'taxable',
					'woocommerce_balikovna_cost_type'    => 'weight',
					'woocommerce_balikovna_cost'         => '79',
					'woocommerce_balikovna_weight_table' => "2|59\n5,5|kč 89",
				)
			);
			$method->process_admin_options();
			$errors = $method->get_errors();
			$this->assertCount( 1, $errors );
			$this->assertStringContainsString( 'kladná hmotnost|nezáporná cena', $errors[0] );
			$this->assertSame( "5|79\n10|119\n15|159", get_option( $method->get_instance_option_key() )['weight_table'] );
		} finally {
			unset( $_REQUEST['instance_id'] );
			$zone->delete();
		}
	}

	public function test_block_picker_loads_wherever_the_checkout_block_renders(): void {
		if ( ! WC()->cart ) {
			wc_load_cart();
		}
		$style_enqueued = null;
		$probe          = function ( $content ) use ( &$style_enqueued ) {
			$style_enqueued = wp_style_is( 'balikovna-wc', 'enqueued' );
			return $content;
		};
		add_filter( 'render_block_woocommerce/checkout', $probe, 20 );
		try {
			// Rendered outside post content, as from a customised template or a synced pattern.
			$this->assertFalse( has_block( 'woocommerce/checkout' ) );
			render_block(
				array(
					'blockName'    => 'woocommerce/checkout',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			);
		} finally {
			remove_filter( 'render_block_woocommerce/checkout', $probe, 20 );
		}
		$scripts = wp_scripts();
		$this->assertStringEndsWith( 'assets/js/checkout-block.js', $scripts->registered[ Balikovna_WC\Blocks_Integration::HANDLE ]->src );
		$this->assertContains( Balikovna_WC\Blocks_Integration::HANDLE, $scripts->registered['wc-checkout-block-frontend']->deps );
		$this->assertTrue( $style_enqueued );
	}

	public function test_uninstall_removes_credentials_without_deleting_orders_or_zone_settings(): void {
		$order = $this->order( 'balikovna', 'BA1234567890A' );
		Tracking_Settings::save( array_merge( Tracking_Settings::defaults(), array( 'api_token' => 'cleanup-test-token', 'secret_key' => 'cleanup-test-secret' ) ) );
		$fixture = json_decode( file_get_contents( getenv( 'BALIKOVNA_TEST_SITE' ) . '/fixture-data.json' ), true );
		$setting_name = 'woocommerce_balikovna_' . explode( ':', $fixture['rate_id'] )[1] . '_settings';
		$zone_settings = get_option( $setting_name );
		require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-cleanup.php';
		Balikovna_WC\Cleanup::uninstall();
		$this->assertFalse( get_option( Tracking_Settings::OPTION_NAME ) );
		$this->assertFalse( wp_next_scheduled( Balikovna_WC\Cleanup::UPDATE_CRON_HOOK ), 'The update checker cron event is removed.' );
		$this->assertSame( $zone_settings, get_option( $setting_name ) );
		$this->assertSame( 'BA1234567890A', Order::get_shipments( new WC_Order( $order->get_id() ) )[0]['trackingNumber'] );
	}

	public function test_first_request_after_cleanup_schedules_update_checks_without_early_translations(): void {
		require_once BALIKOVNA_WC_PATH . 'includes/class-balikovna-cleanup.php';
		wp_clear_scheduled_hook( Balikovna_WC\Cleanup::UPDATE_CRON_HOOK );
		$script = tempnam( sys_get_temp_dir(), 'balikovna-boot' );
		file_put_contents(
			$script,
			<<<'PHP'
<?php
$GLOBALS['balikovna_notices'] = array();
$GLOBALS['wp_filter']['doing_it_wrong_run'][10][] = array(
	'accepted_args' => 2,
	'function'      => function ( $function, $message ) {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
			if ( 0 === strpos( $frame['class'] ?? '', 'Balikovna_WC\\' ) || false !== strpos( str_replace( '\\', '/', $frame['file'] ?? '' ), '/plugins/balikovna-woocommerce/' ) ) {
				$GLOBALS['balikovna_notices'][] = $function . ': ' . strip_tags( $message );
				return;
			}
		}
	},
);
require getenv( 'BALIKOVNA_TEST_SITE' ) . '/wp-load.php';
echo json_encode( array( 'notices' => $GLOBALS['balikovna_notices'], 'scheduled' => false !== wp_next_scheduled( 'puc_cron_check_updates-balikovna-woocommerce' ) ) );
PHP
		);
		$command = array_merge( array( PHP_BINARY ), json_decode( getenv( 'BALIKOVNA_PHP_ARGS' ) ?: '[]', true ), array( $script ) );
		$process = proc_open( $command, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		$output  = stream_get_contents( $pipes[1] );
		$errors  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$status = proc_close( $process );
		unlink( $script );

		$this->assertSame( 0, $status, $errors );
		$report = json_decode( (string) substr( $output, (int) strrpos( $output, '{"notices"' ) ), true );
		$this->assertIsArray( $report, $output . $errors );
		$this->assertTrue( $report['scheduled'], 'The fresh request scheduled the update checker cron event.' );
		$this->assertSame( array(), $report['notices'] );
	}
}