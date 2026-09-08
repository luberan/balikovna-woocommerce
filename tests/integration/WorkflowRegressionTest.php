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

	public function test_pickup_outage_uses_one_upstream_attempt_during_cooldown(): void {
		$original = $GLOBALS['wp_filter']['balikovna_wc_points_directory'] ?? null;
		remove_all_filters( 'balikovna_wc_points_directory' );
		$key = 'balikovna_wc_points_balikovny_v1';
		delete_transient( $key );
		delete_transient( $key . '_retry_after' );
		delete_option( $key . '_refresh_lock' );
		update_option( $key . '_stale', array( 'updated' => time() - 8 * DAY_IN_SECONDS, 'directory' => array( 'B10000' => Points::sanitize( array( 'id' => 'B10000', 'name' => 'Cached', 'type' => 'BALIKOVNY' ) ) ) ), false );
		$calls = 0;
		$offline = function ( $response, $args, $url ) use ( &$calls ) {
			if ( 0 === strpos( $url, Points::API_URL ) ) { ++$calls; return new WP_Error( 'offline', 'Fixture outage' ); }
			return $response;
		};
		add_filter( 'pre_http_request', $offline, 10, 3 );
		try {
			for ( $attempt = 0; $attempt < 3; ++$attempt ) {
				$this->assertSame( 'Cached', Points::validate( array( 'id' => 'B10000' ), 'balikovna' )['name'] );
			}
			$this->assertSame( 1, $calls );
			$this->assertFalse( get_option( $key . '_refresh_lock' ) );
		} finally {
			remove_filter( 'pre_http_request', $offline, 10 );
			delete_option( $key . '_stale' );
			delete_transient( $key . '_retry_after' );
			if ( $original ) { $GLOBALS['wp_filter']['balikovna_wc_points_directory'] = $original; }
		}
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
		$this->assertSame( $zone_settings, get_option( $setting_name ) );
		$this->assertSame( 'BA1234567890A', Order::get_shipments( new WC_Order( $order->get_id() ) )[0]['trackingNumber'] );
	}
}