<?php

use Balikovna_WC\Eligible_Orders;
use Balikovna_WC\Napi_Authentication;
use Balikovna_WC\Napi_Client;
use Balikovna_WC\Napi_Error;
use Balikovna_WC\Napi_Transport_Interface;
use Balikovna_WC\Order;
use Balikovna_WC\Order_Status_Mapper;
use Balikovna_WC\Shipment_Synchronizer;
use Balikovna_WC\Status_Dictionary;
use Balikovna_WC\Tracking_Logger;
use Balikovna_WC\Tracking_Settings;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Sync_Transport implements Napi_Transport_Interface {
	public $requests = array();
	public $after_request;
	private $responses;

	public function __construct( array $responses ) {
		$this->responses = $responses;
	}

	public function request( $method, $url, array $args ) {
		$this->requests[] = compact( 'method', 'url', 'args' );
		if ( $this->after_request ) {
			call_user_func( $this->after_request );
		}
		return array_shift( $this->responses );
	}
}

final class Balikovna_Test_Tracking_Logger extends Tracking_Logger {
	public $errors = array();

	public function api_error( Napi_Error $error, $order_id = 0, $item_id = 0 ) {
		$this->errors[] = array( $error->get_code(), $order_id, $item_id );
	}
}

final class TrackingSynchronizationTest extends TestCase {
	private $dictionary;

	protected function setUp(): void {
		$GLOBALS['wpdb'] = new Balikovna_Test_Lock_Database();
		$GLOBALS['balikovna_test_orders'] = array();
		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$GLOBALS['balikovna_test_options']     = array();
		$GLOBALS['balikovna_test_filters']     = array();
		$GLOBALS['balikovna_test_actions']     = array();
		$GLOBALS['balikovna_test_did_actions'] = array();
		$GLOBALS['balikovna_test_order_query'] = null;
		$GLOBALS['balikovna_test_order_statuses'] = array(
			'wc-processing'   => 'Zpracovává se',
			'wc-completed'    => 'Dokončeno',
			'wc-cancelled'    => 'Zrušeno',
			'wc-refunded'     => 'Vráceno',
			'wc-failed'       => 'Selhalo',
			'wc-shipped'      => 'Odesláno',
			'wc-ready-pickup' => 'Připraveno k vyzvednutí',
			'wc-reviewing'    => 'Ruční kontrola',
		);
		$this->dictionary = array(
			'21/00' => array( 'code' => '21/00', 'status' => '21', 'reason' => '00', 'name' => 'PODÁNO' ),
			'44/01' => array( 'code' => '44/01', 'status' => '44', 'reason' => '01', 'name' => 'V PŘEPRAVĚ' ),
			'81/00' => array( 'code' => '81/00', 'status' => '81', 'reason' => '00', 'name' => 'ULOŽENO' ),
			'91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ),
		);
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array(
			'updated_at' => time(),
			'statuses'   => $this->dictionary,
			'last_error' => array(),
		);
	}

	private function settings( array $overrides = array() ) {
		return array_merge(
			Tracking_Settings::defaults( $this->dictionary ),
			array(
				'enabled'           => true,
				'api_token'         => 'api-token',
				'secret_key'        => 'secret-key',
				'auto_order_status' => true,
			),
			$overrides
		);
	}

	private function response( $status, $reason, $label, $parcel_id = 'BA1234567890A' ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode(
				array(
					'idParcel'    => $parcel_id,
					'parcelStatus' => array(
						'statusID'         => $status,
						'reasonID'         => $reason,
						'datetime'         => '2026-08-12T10:00:00+02:00',
						'statusDescription' => $label,
					),
				)
			),
		);
	}

	private function synchronizer( array $responses, &$transport = null, &$logger = null, $clock = null ) {
		$transport = new Balikovna_Test_Sync_Transport( $responses );
		$client    = new Napi_Client(
			new Napi_Authentication(
				'api-token',
				'secret-key',
				function () {
					return 1786521600;
				},
				function () {
					return '00000000-0000-4000-8000-000000000001';
				}
			),
			$transport
		);
		$logger = new Balikovna_Test_Tracking_Logger();
		$clock = is_callable( $clock ) ? $clock : function () {
			return 1786521600;
		};
		return new Shipment_Synchronizer(
			$client,
			new Status_Dictionary( $client ),
			new Eligible_Orders( $clock ),
			new Order_Status_Mapper(),
			$logger,
			$clock
		);
	}

	private function item( $tracking_number, array $status = array(), $id = 10, $method = 'balikovna' ) {
		$meta = array_merge(
			array( Order::META_TRACKING_NUMBER => $tracking_number ),
			$status
		);
		return new WC_Order_Item_Shipping( $method, '4', $meta, $id );
	}

	private function order( array $items, $status = 'processing', $created_at = null, $id = 100 ) {
		return new WC_Order(
			$items,
			array(),
			array(),
			array(),
			array(
				'id'           => $id,
				'status'       => $status,
				'date_created' => null === $created_at ? 1786521600 - HOUR_IN_SECONDS : $created_at,
			)
		);
	}

	public function test_status_unchanged_does_not_repeat_order_transition(): void {
		$item = $this->item(
			'BA1234567890A',
			array(
				Order::META_STATUS_CODE             => '44/01',
				Order::META_STATUS_LABEL            => 'V PŘEPRAVĚ',
				Order::META_STATUS_TRACKING_NUMBER  => 'BA1234567890A',
				Order::META_STATUS_EVALUATED_CODE   => '44/01',
				Order::META_STATUS_MAPPING_REVISION => 1,
			)
		);
		$order = $this->order( array( $item ), 'shipped' );
		$sync  = $this->synchronizer( array( $this->response( '44', '01', 'V přepravě' ) ), $transport );

		$result = $sync->sync_order( $order, $this->settings() );

		$this->assertSame( 1, $result['checked'] );
		$this->assertSame( array(), $order->status_updates );
		$this->assertCount( 1, $transport->requests );
		$this->assertSame( 1786521600, $item->get_meta( Order::META_STATUS_CHECKED_AT ) );
	}

	public function test_missing_tracking_blocks_mapping_until_every_parcel_is_delivered(): void {
		$delivered = array( Order::META_STATUS_CODE => '91/00', Order::META_STATUS_LABEL => 'DORUČENO' );
		$first = $this->item( 'BA1234567890A', $delivered );
		$second = $this->item( '', $delivered, 11 );
		$order = $this->order( array( $first, $second ) );
		$mapper = new Order_Status_Mapper();
		$settings = $this->settings();

		$this->assertSame( '', $mapper->apply( $order, Order::get_shipments( $order ), $settings ) );
		$this->assertSame( '', $mapper->apply( $order, array_reverse( Order::get_shipments( $order ) ), $settings ) );
		$this->assertSame( array(), $order->status_updates );

		$second->update_meta_data( Order::META_TRACKING_NUMBER, 'BA1234567891A' );
		$second->delete_meta_data( Order::META_STATUS_CODE );
		$this->assertSame( '', $mapper->apply( $order, Order::get_shipments( $order ), $settings ) );

		$second->update_meta_data( Order::META_STATUS_CODE, '91/00' );
		$this->assertSame( 'wc-completed', $mapper->apply( $order, Order::get_shipments( $order ), $settings ) );
		$this->assertSame( '', $mapper->apply( $order, Order::get_shipments( $order ), $settings ) );
		$this->assertSame( array( 'completed' ), $order->status_updates );
	}

	public function test_sync_does_not_complete_order_with_an_untracked_parcel(): void {
		$order = $this->order( array( $this->item( 'BA1234567890A' ), $this->item( '', array(), 11 ) ), 'processing', time() );
		$sync = $this->synchronizer( array( $this->response( '91', '00', 'DORUČENO' ) ), $transport );

		$result = $sync->sync_order( $order, $this->settings() );

		$this->assertSame( 1, $result['checked'] );
		$this->assertCount( 1, $transport->requests );
		$this->assertSame( array(), $order->status_updates );
	}

	public function test_normal_status_flow_transitions_once_per_woocommerce_target(): void {
		$item  = $this->item( 'BA1234567890A' );
		$order = $this->order( array( $item ) );
		$sync  = $this->synchronizer(
			array(
				$this->response( '21', '00', 'PODÁNO' ),
				$this->response( '44', '01', 'V PŘEPRAVĚ' ),
				$this->response( '81', '00', 'ULOŽENO' ),
				$this->response( '91', '00', 'DORUČENO' ),
			)
		);
		$settings = $this->settings();

		$sync->sync_order( $order, $settings );
		$sync->sync_order( $order, $settings );
		$sync->sync_order( $order, $settings );
		$sync->sync_order( $order, $settings );

		$this->assertSame( array( 'shipped', 'ready-pickup', 'completed' ), $order->status_updates );
		$this->assertSame( '91/00', $item->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( '91/00', $item->get_meta( Order::META_STATUS_EVALUATED_CODE ) );
	}

	public function test_mapping_disabled_still_synchronizes_shipment_status(): void {
		$item  = $this->item( 'BA1234567890A' );
		$order = $this->order( array( $item ) );
		$sync  = $this->synchronizer( array( $this->response( '21', '00', 'PODÁNO' ) ) );

		$result = $sync->sync_order( $order, $this->settings( array( 'auto_order_status' => false ) ) );

		$this->assertSame( 1, $result['checked'] );
		$this->assertSame( '21/00', $item->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( array(), $order->status_updates );
	}

	public function test_unselected_or_old_order_and_missing_tracking_number_are_skipped(): void {
		$settings = $this->settings();
		$cases    = array(
			$this->order( array( $this->item( 'BA1234567890A' ) ), 'pending' ),
			$this->order( array( $this->item( 'BA1234567890A' ) ), 'processing', 1786521600 - ( 15 * DAY_IN_SECONDS ) ),
			$this->order( array( $this->item( '' ) ) ),
		);
		$sync = $this->synchronizer( array(), $transport );

		foreach ( $cases as $order ) {
			$result = $sync->sync_order( $order, $settings );
			$this->assertSame( 0, $result['checked'] );
		}
		$this->assertCount( 0, $transport->requests );
	}

	public function test_one_delivered_and_one_transit_parcel_does_not_complete_order(): void {
		$delivered = $this->item( 'BA1234567890A', array(), 10, 'balikovna' );
		$transit   = $this->item( 'DR1234567890E', array(), 11, 'cp_do_ruky' );
		$order     = $this->order( array( $delivered, $transit ) );
		$sync      = $this->synchronizer(
			array(
				$this->response( '91', '00', 'DORUČENO' ),
				$this->response( '44', '01', 'V PŘEPRAVĚ', 'DR1234567890E' ),
			)
		);

		$sync->sync_order( $order, $this->settings() );

		$this->assertSame( array( 'shipped' ), $order->status_updates );
		$this->assertSame( '91/00', $delivered->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( '44/01', $transit->get_meta( Order::META_STATUS_CODE ) );
	}

	public function test_all_parcels_delivered_complete_order_once(): void {
		$first  = $this->item( 'BA1234567890A', array(), 10 );
		$second = $this->item( 'DR1234567890E', array(), 11, 'cp_do_ruky' );
		$order  = $this->order( array( $first, $second ), 'ready-pickup' );
		$sync   = $this->synchronizer(
			array(
				$this->response( '91', '00', 'DORUČENO' ),
				$this->response( '91', '00', 'DORUČENO', 'DR1234567890E' ),
			)
		);

		$sync->sync_order( $order, $this->settings() );

		$this->assertSame( array( 'completed' ), $order->status_updates );
	}

	public function test_ambiguous_custom_multi_parcel_mappings_do_not_change_order(): void {
		$first  = $this->item( 'BA1234567890A' );
		$second = $this->item( 'DR1234567890E', array(), 11, 'cp_do_ruky' );
		$order  = $this->order( array( $first, $second ) );
		$sync   = $this->synchronizer(
			array(
				$this->response( '21', '00', 'PODÁNO' ),
				$this->response( '44', '01', 'V PŘEPRAVĚ', 'DR1234567890E' ),
			)
		);
		$settings = $this->settings();
		$settings['status_mappings']['21/00'] = 'wc-reviewing';

		$sync->sync_order( $order, $settings );

		$this->assertSame( array(), $order->status_updates );
	}

	public function test_custom_mapping_cannot_complete_an_undelivered_parcel(): void {
		$item     = $this->item( 'BA1234567890A' );
		$order    = $this->order( array( $item ) );
		$sync     = $this->synchronizer( array( $this->response( '44', '01', 'V PŘEPRAVĚ' ) ) );
		$settings = $this->settings();
		$settings['status_mappings']['44/01'] = 'wc-completed';

		$sync->sync_order( $order, $settings );

		$this->assertSame( array(), $order->status_updates );
	}

	public function test_ineligible_cached_shipment_cannot_trigger_mapping(): void {
		$item = $this->item(
			'BA1234567890A',
			array(
				Order::META_STATUS_CODE            => '21/00',
				Order::META_STATUS_LABEL           => 'PODÁNO',
				Order::META_STATUS_TRACKING_NUMBER => 'BA1234567890A',
			)
		);
		$order = $this->order( array( $item ) );
		add_filter(
			'balikovna_wc_tracking_shipment_eligible',
			function () {
				return false;
			}
		);
		$sync = $this->synchronizer( array(), $transport );

		$sync->sync_order( $order, $this->settings() );

		$this->assertCount( 0, $transport->requests );
		$this->assertSame( array(), $order->status_updates );
		$this->assertSame( '', $item->get_meta( Order::META_STATUS_EVALUATED_CODE ) );
	}

	public function test_failed_woocommerce_status_write_remains_retryable(): void {
		$item                       = $this->item( 'BA1234567890A' );
		$order                      = $this->order( array( $item ) );
		$order->status_update_result = false;
		$sync                       = $this->synchronizer( array( $this->response( '21', '00', 'PODÁNO' ) ) );

		$sync->sync_order( $order, $this->settings() );

		$this->assertSame( 'processing', $order->get_status() );
		$this->assertSame( array(), $order->status_updates );
		$this->assertSame( '', $item->get_meta( Order::META_STATUS_EVALUATED_CODE ) );
	}

	public function test_authentication_failure_stops_remaining_parcel_calls(): void {
		$first  = $this->item( 'BA1234567890A' );
		$second = $this->item( 'DR1234567890E', array(), 11, 'cp_do_ruky' );
		$order  = $this->order( array( $first, $second ) );
		$sync   = $this->synchronizer(
			array(
				array(
					'response' => array( 'code' => 401 ),
					'body'     => '{"code":-1,"status":"401","message":"Invalid signature"}',
				),
			),
			$transport,
			$logger
		);

		$result = $sync->sync_order( $order, $this->settings() );

		$this->assertInstanceOf( Napi_Error::class, $result['global_error'] );
		$this->assertCount( 1, $transport->requests );
		$this->assertSame( array( array( 'authentication_failed', 100, 10 ) ), $logger->errors );
		$this->assertSame( '', $second->get_meta( Order::META_STATUS_ATTEMPTED_AT ) );
		$this->assertSame( array(), $order->status_updates );
	}

	public function test_foreign_parcel_response_preserves_status_and_does_not_complete_order(): void {
		$item = $this->item( 'BA1234567890A', array(
			Order::META_STATUS_CODE => '44/01',
			Order::META_STATUS_LABEL => 'V PŘEPRAVĚ',
			Order::META_STATUS_TRACKING_NUMBER => 'BA1234567890A',
		) );
		$order = $this->order( array( $item ), 'shipped', time() );
		$sync = $this->synchronizer( array( $this->response( '91', '00', 'DORUČENO', 'DR9999999999E' ) ), $transport, $logger );

		$sync->sync_order( $order, $this->settings() );

		$this->assertCount( 1, $transport->requests );
		$this->assertSame( array( array( 'parcel_id_mismatch', 100, 10 ) ), $logger->errors );
		$this->assertSame( '44/01', $item->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( 'V PŘEPRAVĚ', $item->get_meta( Order::META_STATUS_LABEL ) );
		$this->assertSame( 'BA1234567890A', $item->get_meta( Order::META_STATUS_TRACKING_NUMBER ) );
		$this->assertSame( array(), $order->status_updates );
	}

	public function test_transient_error_preserves_last_known_status_and_order_state(): void {
		$item = $this->item(
			'BA1234567890A',
			array(
				Order::META_STATUS_CODE            => '44/01',
				Order::META_STATUS_LABEL           => 'V PŘEPRAVĚ',
				Order::META_STATUS_TRACKING_NUMBER => 'BA1234567890A',
			)
		);
		$order = $this->order( array( $item ), 'shipped' );
		$sync  = $this->synchronizer(
			array(
				array(
					'response' => array( 'code' => 500 ),
					'body'     => '[{"code":-5,"status":"500","message":"Temporary failure"}]',
				),
			)
		);

		$sync->sync_order( $order, $this->settings() );

		$this->assertSame( '44/01', $item->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( 'V PŘEPRAVĚ', $item->get_meta( Order::META_STATUS_LABEL ) );
		$this->assertSame( array(), $order->status_updates );
	}

	public function test_hpos_safe_selector_rotates_pages_without_starvation(): void {
		$first  = $this->order( array( $this->item( 'BA1234567890A', array(), 10 ) ), 'processing', null, 101 );
		$second = $this->order( array( $this->item( 'DR1234567890E', array(), 11, 'cp_do_ruky' ) ), 'processing', null, 102 );
		$queries = array();
		$GLOBALS['balikovna_test_order_query'] = function ( $args ) use ( $first, $second, &$queries ) {
			$queries[] = $args;
			return (object) array(
				'orders'        => 1 === $args['page'] ? array( $first ) : array( $second ),
				'total'         => 2,
				'max_num_pages' => 2,
			);
		};
		$settings               = $this->settings( array( 'batch_size' => 1 ) );
		$repository             = new Eligible_Orders( function () { return 1786521600; } );

		$first_batch  = $repository->find( $settings );
		$second_batch = $repository->find( $settings );

		$this->assertSame( array( 101 ), array_map( function ( $order ) { return $order->get_id(); }, $first_batch ) );
		$this->assertSame( array( 102 ), array_map( function ( $order ) { return $order->get_id(); }, $second_batch ) );
		$this->assertSame( array( 1, 2 ), array_column( $queries, 'page' ) );
		$this->assertSame( $settings['order_statuses'], $queries[0]['status'] );
		$this->assertStringStartsWith( '>', $queries[0]['date_created'] );
		$this->assertSame( true, $queries[0]['paginate'] );
		$this->assertSame( 'objects', $queries[0]['return'] );
	}

	public function test_order_age_filter_and_query_share_the_injected_clock(): void {
		$settings = $this->settings( array( 'tracking_days' => 14 ) );
		foreach ( array( 1577836800, 1786521600, 2208988800 ) as $now ) {
			$repository = new Eligible_Orders( function () use ( &$now ) { return $now; } );
			$cutoff = $now - 14 * DAY_IN_SECONDS;
			$recent = $this->order( array( $this->item( 'BA1234567890A' ) ), 'processing', $cutoff + 1, 101 );
			$old = $this->order( array( $this->item( 'DR1234567890E' ) ), 'processing', $cutoff - 1, 102 );
			$queries = array();
			$GLOBALS['balikovna_test_order_query'] = function ( $args ) use ( &$queries, $recent, $old ) {
				$queries[] = $args;
				return (object) array( 'orders' => array( $recent, $old ), 'max_num_pages' => 1 );
			};

			$this->assertTrue( $repository->is_order_eligible( $recent, $settings ) );
			$this->assertFalse( $repository->is_order_eligible( $old, $settings ) );
			$this->assertSame( array( $recent ), $repository->find( $settings ) );
			$this->assertSame( '>' . $cutoff, $queries[0]['date_created'] );

			$now += 2;
			$this->assertFalse( $repository->is_order_eligible( $recent, $settings ) );
			$this->assertSame( array(), $repository->find( $settings ) );
			$this->assertSame( '>' . ( $cutoff + 2 ), $queries[1]['date_created'] );
		}
	}

	public function test_order_age_defaults_to_the_system_clock(): void {
		$repository = new Eligible_Orders();
		$settings = $this->settings( array( 'tracking_days' => 14 ) );
		$this->assertTrue( $repository->is_order_eligible( $this->order( array(), 'processing', time() - HOUR_IN_SECONDS ), $settings ) );
		$this->assertFalse( $repository->is_order_eligible( $this->order( array(), 'processing', time() - 15 * DAY_IN_SECONDS ), $settings ) );
	}

	public function test_selector_rechecks_new_tracking_number_after_terminal_old_status(): void {
		$item = $this->item(
			'BA1234567890A',
			array(
				Order::META_STATUS_CODE             => '91/00',
				Order::META_STATUS_LABEL            => 'DORUČENO',
				Order::META_STATUS_TRACKING_NUMBER  => 'BA0000000000A',
				Order::META_STATUS_EVALUATED_CODE   => '91/00',
				Order::META_STATUS_MAPPING_REVISION => 1,
			)
		);
		$order = $this->order( array( $item ) );

		$this->assertTrue( ( new Eligible_Orders() )->has_work( $order, $this->settings() ) );
	}

	public function test_api_incompatible_legacy_tracking_number_is_not_endless_work(): void {
		$order = $this->order( array( $this->item( 'ABCDEFGHIJKLMNOP' ) ) );

		$this->assertFalse( ( new Eligible_Orders() )->has_work( $order, $this->settings() ) );
	}

	public function test_lock_heartbeat_extends_expiry_during_a_long_batch(): void {
		$now  = 1000;
		$sync = $this->synchronizer(
			array(),
			$transport,
			$logger,
			function () use ( &$now ) {
				return $now;
			}
		);
		$acquire = new ReflectionMethod( $sync, 'acquire_lock' );
		$refresh = new ReflectionMethod( $sync, 'refresh_lock' );
		$release = new ReflectionMethod( $sync, 'release_lock' );
		$token_property = new ReflectionProperty( $sync, 'lock_token' );
		if ( PHP_VERSION_ID < 80100 ) {
			$acquire->setAccessible( true );
			$refresh->setAccessible( true );
			$release->setAccessible( true );
			$token_property->setAccessible( true );
		}

		$token = $acquire->invoke( $sync );
		$token_property->setValue( $sync, $token );
		$now += Shipment_Synchronizer::LOCK_TTL - 1;
		$refresh->invoke( $sync );

		$this->assertSame( $now + Shipment_Synchronizer::LOCK_TTL, get_option( Shipment_Synchronizer::LOCK_OPTION )['expires'] );
		$release->invoke( $sync, $token );
		$this->assertSame( array(), get_option( Shipment_Synchronizer::LOCK_OPTION, array() ) );
	}

	private function private_method( $object, $name ) {
		$method = new ReflectionMethod( $object, $name );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}
		return $method;
	}

	public function test_stale_lock_operations_never_delete_or_overwrite_a_new_owner(): void {
		$sync = $this->synchronizer( array(), $transport, $logger, function () { return 1000; } );
		$old = array( 'token' => 'old-owner', 'expires' => 999 );
		$new = array( 'token' => 'new-owner', 'expires' => 2000 );
		foreach ( array( 'acquire_lock', 'release_lock', 'refresh_lock' ) as $operation ) {
			update_option( Shipment_Synchronizer::LOCK_OPTION, $old );
			$property = new ReflectionProperty( $sync, 'lock_token' );
			if ( PHP_VERSION_ID < 80100 ) { $property->setAccessible( true ); }
			$property->setValue( $sync, 'old-owner' );
			$GLOBALS['wpdb']->before_query = function () use ( $new ) {
				update_option( Shipment_Synchronizer::LOCK_OPTION, $new );
			};
			$result = $this->private_method( $sync, $operation )->invokeArgs( $sync, 'release_lock' === $operation ? array( 'old-owner' ) : array() );
			$this->assertSame( $new, get_option( Shipment_Synchronizer::LOCK_OPTION ), $operation );
			if ( 'release_lock' !== $operation ) { $this->assertFalse( $result ); }
		}
		update_option( Shipment_Synchronizer::LOCK_OPTION, $old );
		$this->assertNotFalse( $this->private_method( $sync, 'acquire_lock' )->invoke( $sync ) );
		$this->assertFalse( $this->private_method( $sync, 'acquire_lock' )->invoke( $sync ) );
	}

	private function prepare_batch( array $orders ) {
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $this->settings();
		foreach ( $orders as $order ) {
			$GLOBALS['balikovna_test_orders'][ $order->get_id() ] = $order;
		}
		$GLOBALS['balikovna_test_order_query'] = function () use ( $orders ) {
			return (object) array( 'orders' => $orders, 'max_num_pages' => 1 );
		};
	}

	public function test_slow_batch_resumes_inside_order_and_preserves_remaining_orders(): void {
		$now = time();
		$first = $this->order( array( $this->item( 'BA1234567890A' ), $this->item( 'DR1234567890E', array(), 11 ) ), 'processing', $now, 100 );
		$second = $this->order( array( $this->item( 'BA1234567891A', array(), 12 ) ), 'processing', $now, 101 );
		$this->prepare_batch( array( $first, $second ) );
		foreach ( array( 'BA1234567890A', 'DR1234567890E', 'BA1234567891A' ) as $index => $parcel ) {
			$sync = $this->synchronizer( array( $this->response( '91', '00', 'DORUČENO', $parcel ) ), $transport, $logger, function () use ( &$now ) { return $now; } );
			$transport->after_request = function () use ( &$now ) { $now += 15; };
			$result = $sync->run_batch();
			$this->assertSame( 1, $result['shipments'] );
			$this->assertCount( 1, $transport->requests );
			$this->assertStringEndsWith( '/' . $parcel, $transport->requests[0]['url'] );
			$this->assertSame( $index < 2, $result['pending'] );
			if ( 0 === $index ) {
				$this->assertSame( array( 100, 101 ), get_option( Shipment_Synchronizer::PENDING_OPTION )['orders'] );
				$this->assertSame( array(), $first->status_updates );
				$this->assertSame( Balikovna_WC\Tracking_Scheduler::CONTINUATION_HOOK, $GLOBALS['balikovna_test_scheduled_actions'][0]['hook'] );
				$GLOBALS['balikovna_test_order_query'] = function () { throw new RuntimeException( 'Must resume before selecting another page' ); };
			}
		}
		$this->assertSame( array( 'completed' ), $first->status_updates );
		$this->assertSame( array( 'completed' ), $second->status_updates );
		$this->assertSame( array(), get_option( Shipment_Synchronizer::PENDING_OPTION )['orders'] );
	}

	public function test_fast_batch_limits_requests_and_resumes_without_repolling(): void {
		$items = array();
		$responses = array();
		for ( $index = 0; $index < 11; ++$index ) {
			$parcel = sprintf( 'BA%010dA', $index );
			$items[] = $this->item( $parcel, array(), $index + 10 );
			$responses[] = $this->response( '44', '01', 'V PŘEPRAVĚ', $parcel );
		}
		$order = $this->order( $items, 'processing', time() );
		$this->prepare_batch( array( $order ) );
		$sync = $this->synchronizer( $responses, $transport );
		$first = $sync->run_batch();
		$this->assertSame( 10, $first['shipments'] );
		$this->assertTrue( $first['pending'] );
		$this->assertSame( array(), $order->status_updates );
		$second = $sync->run_batch();
		$this->assertSame( 1, $second['shipments'] );
		$this->assertFalse( $second['pending'] );
		$this->assertCount( 11, $transport->requests );
		$this->assertSame( array( 'shipped' ), $order->status_updates );
	}

	public function test_dictionary_refresh_consumes_the_same_time_budget(): void {
		$now = time();
		$order = $this->order( array( $this->item( 'BA1234567890A' ) ), 'processing', $now );
		$this->prepare_batch( array( $order ) );
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ]['updated_at'] = 1;
		$sync = $this->synchronizer(
			array( array( 'response' => array( 'code' => 200 ), 'body' => '{"statusesList":[{"status":"44","reason":"01","name":"V PREPRAVE"}]}' ) ),
			$transport, $logger, function () use ( &$now ) { return $now; }
		);
		$transport->after_request = function () use ( &$now ) { $now += 15; };
		$result = $sync->run_batch();
		$this->assertSame( 0, $result['shipments'] );
		$this->assertTrue( $result['pending'] );
		$this->assertCount( 1, $transport->requests );
		$this->assertStringEndsWith( '/statusesOverview', $transport->requests[0]['url'] );
		$this->assertSame( array( 100 ), get_option( Shipment_Synchronizer::PENDING_OPTION )['orders'] );
	}

	public function test_slow_cis_outage_does_not_starve_resumed_parcels(): void {
		$now = time();
		$order = $this->order( array( $this->item( 'BA1234567890A' ) ), 'processing', $now );
		$this->prepare_batch( array( $order ) );
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ]['updated_at'] = 1;
		$sync = $this->synchronizer(
			array( array( 'response' => array( 'code' => 500 ), 'body' => '{}' ), $this->response( '44', '01', 'V PŘEPRAVĚ' ) ),
			$transport, $logger, function () use ( &$now ) { return $now; }
		);
		$transport->after_request = function () use ( &$now ) { $now += 15; };
		$this->assertTrue( $sync->run_batch()['pending'] );
		$result = $sync->run_batch();
		$this->assertFalse( $result['pending'] );
		$this->assertSame( 1, $result['shipments'] );
		$this->assertCount( 2, $transport->requests );
		$this->assertStringEndsWith( '/idParcel/BA1234567890A', $transport->requests[1]['url'] );
	}

	public function test_lost_lock_discards_response_and_preserves_new_owner(): void {
		$item = $this->item( 'BA1234567890A' );
		$order = $this->order( array( $item ), 'processing', time() );
		$this->prepare_batch( array( $order ) );
		$sync = $this->synchronizer( array( $this->response( '91', '00', 'DORUČENO' ) ), $transport );
		$new = array( 'token' => 'new-owner', 'expires' => time() + 1000 );
		$transport->after_request = function () use ( $new ) { update_option( Shipment_Synchronizer::LOCK_OPTION, $new ); };
		$result = $sync->run_batch();
		$this->assertInstanceOf( Napi_Error::class, $result );
		$this->assertSame( 'synchronization_lock_lost', $result->get_code() );
		$this->assertSame( $new, get_option( Shipment_Synchronizer::LOCK_OPTION ) );
		$this->assertSame( '', $item->get_meta( Order::META_STATUS_CODE ) );
		$this->assertSame( array(), $order->status_updates );
	}

	public function test_stale_dictionary_survives_cis_outage_and_zsk_sync_continues(): void {
		$item  = $this->item( 'BA1234567890A' );
		$order = $this->order( array( $item ) );
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ]['updated_at'] = 1;
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $this->settings();
		$GLOBALS['balikovna_test_order_query'] = function () use ( $order ) {
			return (object) array(
				'orders'        => array( $order ),
				'total'         => 1,
				'max_num_pages' => 1,
			);
		};
		$sync = $this->synchronizer(
			array(
				array(
					'response' => array( 'code' => 500 ),
					'body'     => '[{"code":-5,"status":"500","message":"CIS unavailable"}]',
				),
				$this->response( '44', '01', 'V PŘEPRAVĚ' ),
			),
			$transport
		);

		$result = $sync->run_batch();

		$this->assertSame( 1, $result['shipments'] );
		$this->assertCount( 2, $transport->requests );
		$this->assertSame( '44/01', $item->get_meta( Order::META_STATUS_CODE ) );
	}
}