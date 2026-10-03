<?php

use Balikovna_WC\Checkout;
use Balikovna_WC\Option_Lock;
use Balikovna_WC\Points;
use Balikovna_WC\Tracking_Scheduler;
use PHPUnit\Framework\TestCase;

final class PointsAndCheckoutTest extends TestCase {
	protected function setUp(): void {
		remove_all_filters();
		$GLOBALS['wpdb'] = new Balikovna_Test_Lock_Database();
		$GLOBALS['balikovna_test_options'] = array();
		$GLOBALS['balikovna_test_transients'] = array();
		$GLOBALS['balikovna_test_points_http'] = null;
		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$GLOBALS['balikovna_test_memory_contexts'] = array();
		$GLOBALS['balikovna_test_doing_ajax'] = false;
		$GLOBALS['balikovna_test_wc']->session = new Balikovna_Test_Session();
		add_filter(
			'balikovna_wc_points_directory',
			function ( $directory, $type ) {
				$points = array(
					'BALIKOVNY'   => array(
						'B10000' => Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'street' => 'Cernokostelecka 1', 'city' => 'Praha', 'zip' => '10000', 'country' => 'CZ', 'type' => 'BALIKOVNY' ) ),
					),
					'POST_OFFICE' => array(
						'P10003' => Points::sanitize( array( 'id' => 'P10003', 'name' => 'Depo Praha 701', 'street' => 'Sazecska 7', 'city' => 'Praha', 'zip' => '10003', 'country' => 'CZ', 'type' => 'POST_OFFICE' ) ),
					),
				);
				return $points[ $type ] ?? array();
			},
			10,
			2
		);
	}

	protected function tearDown(): void {
		$GLOBALS['balikovna_test_doing_ajax']  = false;
		$GLOBALS['balikovna_test_points_http'] = null;
	}

	public function test_server_replaces_forged_point_fields_with_canonical_data(): void {
		$result = Points::validate( array( 'id' => 'B10000', 'name' => 'Forged', 'street' => 'Forged' ), 'balikovna' );
		$this->assertIsArray( $result );
		$this->assertSame( 'Praha 10', $result['name'] );
		$this->assertSame( 'BALIKOVNY', $result['type'] );
	}

	public function test_service_type_and_unknown_ids_are_rejected(): void {
		$this->assertSame( 'balikovna_invalid_point_id', Points::validate( array( 'id' => 'P10003' ), 'balikovna' )->get_error_code() );
		$this->assertSame( 'balikovna_unknown_point', Points::validate( array( 'id' => 'B99999' ), 'balikovna' )->get_error_code() );
	}

	public function test_session_keeps_separate_exact_rate_selections_per_package(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4', 'cp_na_postu:7' ) );
		Checkout::set_session_selections(
			array(
				'0' => array( 'packageKey' => '0', 'rateId' => 'balikovna:4', 'serviceId' => 'balikovna', 'point' => Points::validate( array( 'id' => 'B10000' ), 'balikovna' ) ),
				'1' => array( 'packageKey' => '1', 'rateId' => 'cp_na_postu:7', 'serviceId' => 'cp_na_postu', 'point' => Points::validate( array( 'id' => 'P10003' ), 'cp_na_postu' ) ),
			)
		);
		$this->assertCount( 2, Checkout::get_session_selections() );
		$this->assertSame( 'P10003', Checkout::get_session_selection( '1', 'cp_na_postu:7' )['point']['id'] );

		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna_na_adresu:4', 'cp_na_postu:8' ) );
		$this->assertSame( array(), Checkout::get_session_selections() );
	}

	public function test_guest_nonce_action_is_woocommerce_session_scoped(): void {
		$this->assertStringStartsWith( 'woocommerce', Checkout::NONCE_ACTION );
	}

	public function test_final_posted_pickup_rate_is_validated_even_when_session_had_flat_rate(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate:2' ) );
		$errors = new WP_Error();
		Checkout::instance()->validate_selection( array( 'shipping_method' => array( 'balikovna:4' ) ), $errors );
		$this->assertTrue( $errors->has_errors() );
		$this->assertSame( 'balikovna_point_required', $errors->get_error_code() );
	}

	public function test_widget_phone_is_used_when_classic_checkout_phone_is_empty(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4' ) );
		$selection = Checkout::with_recipient_phone(
			array(
				'packageKey' => '0',
				'rateId'     => 'balikovna:4',
				'serviceId'  => 'balikovna',
				'point'      => Points::validate( array( 'id' => 'B10000' ), 'balikovna' ),
			),
			'+420 777 123 456'
		);
		Checkout::set_session_selections( array( '0' => $selection ) );
		$errors = new WP_Error();
		$data   = array(
			'shipping_method' => array( 'balikovna:4' ),
			'billing_email'   => 'customer@example.test',
			'billing_phone'   => '',
		);

		Checkout::instance()->validate_selection( $data, $errors );
		$order = new WC_Order();
		Checkout::instance()->save_to_order( $order, $data );

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( '+420777123456', Checkout::get_session_selection( '0', 'balikovna:4' )['phone'] );
		$this->assertSame( '+420777123456', $order->get_billing_phone() );
	}

	public function test_checkout_phone_wins_and_conflicting_widget_phones_fail_closed(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4', 'balikovna:5' ) );
		$point = Points::validate( array( 'id' => 'B10000' ), 'balikovna' );
		Checkout::set_session_selections(
			array(
				'0' => Checkout::with_recipient_phone(
					array( 'packageKey' => '0', 'rateId' => 'balikovna:4', 'serviceId' => 'balikovna', 'point' => $point ),
					'+420777123456'
				),
				'1' => Checkout::with_recipient_phone(
					array( 'packageKey' => '1', 'rateId' => 'balikovna:5', 'serviceId' => 'balikovna', 'point' => $point ),
					'+420777654321'
				),
			)
		);

		$this->assertSame(
			'',
			Checkout::recipient_phone_with_session_fallback( '', array( 'balikovna' ) )
		);
		$this->assertSame(
			'+420606123456',
			Checkout::recipient_phone_with_session_fallback( '+420 606 123 456', array( 'balikovna' ) )
		);

		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4' ) );
		$this->assertSame(
			'+420777123456',
			Checkout::recipient_phone_with_session_fallback( '', array( 'balikovna' ) )
		);
	}

	public function test_fresh_sharded_directory_avoids_a_network_dependency(): void {
		remove_all_filters();
		$this->seed_directory( 'BALIKOVNY', HOUR_IN_SECONDS, array( 'B10000' => 'Cached point' ) );
		$calls = array();
		$this->http( $calls, new WP_Error( 'offline', 'offline' ) );

		$this->assertSame( 'Cached point', Points::validate( array( 'id' => 'B10000' ), 'balikovna' )['name'] );
		$this->assertSame( 'balikovna_unknown_point', Points::validate( array( 'id' => 'B10001' ), 'balikovna' )->get_error_code() );
		$this->assertSame( array(), $calls );
		$this->assertSame( array(), $GLOBALS['balikovna_test_scheduled_actions'] );
	}

	public function test_stale_directory_is_served_while_refresh_runs_in_background(): void {
		remove_all_filters();
		$this->seed_directory( 'BALIKOVNY', 8 * DAY_IN_SECONDS, array( 'B10000' => 'Cached' ) );
		$calls = array();
		$this->http( $calls, new WP_Error( 'offline', 'offline' ) );

		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$this->assertSame( 'Cached', Points::validate( array( 'id' => 'B10000' ), 'balikovna' )['name'] );
		}
		$this->assertSame( array(), $calls, 'Customer requests never download a stale directory.' );
		$this->assertCount( 1, $GLOBALS['balikovna_test_scheduled_actions'] );
		$this->assertSame( Tracking_Scheduler::POINTS_HOOK, $GLOBALS['balikovna_test_scheduled_actions'][0]['hook'] );
		$this->assertSame( array( 'BALIKOVNY' ), $GLOBALS['balikovna_test_scheduled_actions'][0]['args'] );

		Points::refresh_from_action( 'BALIKOVNY' );
		$this->assertCount( 1, $calls );
		$this->assertSame( array( 'balikovna_wc_points' ), $GLOBALS['balikovna_test_memory_contexts'] );
		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$this->assertSame( 'Cached', Points::validate( array( 'id' => 'B10000' ), 'balikovna' )['name'] );
		$this->assertSame( array(), $GLOBALS['balikovna_test_scheduled_actions'], 'The outage cooldown suppresses rescheduling.' );
		Points::refresh_from_action( 'BALIKOVNY' );
		$this->assertCount( 1, $calls, 'The outage cooldown suppresses another download.' );
	}

	public function test_cold_outage_uses_one_upstream_attempt_and_respects_the_lock(): void {
		remove_all_filters();
		$key   = 'balikovna_wc_points_balikovny_v2';
		$calls = array();
		$this->http( $calls, new WP_Error( 'offline', 'offline' ) );

		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$this->assertSame( 'balikovna_points_unavailable', Points::validate( array( 'id' => 'B10000' ), 'balikovna' )->get_error_code() );
		}
		$this->assertCount( 1, $calls );
		$this->assertFalse( get_option( $key . '_refresh_lock' ) );

		delete_transient( $key . '_retry_after' );
		$token = Option_Lock::acquire( $key . '_refresh_lock', time(), MINUTE_IN_SECONDS );
		$this->assertInstanceOf( WP_Error::class, Points::validate( array( 'id' => 'B10000' ), 'balikovna' ) );
		$this->assertCount( 1, $calls );
		Option_Lock::release( $key . '_refresh_lock', $token );
		Points::validate( array( 'id' => 'B10000' ), 'balikovna' );
		$this->assertCount( 2, $calls );
	}

	public function test_refresh_sets_cooldown_before_download_so_a_crash_is_not_retried(): void {
		remove_all_filters();
		$calls = array();
		$this->http(
			$calls,
			function () {
				throw new Error( 'Allowed memory size exhausted' );
			}
		);

		try {
			Points::validate( array( 'id' => 'B10000' ), 'balikovna' );
			$this->fail( 'The simulated fatal error must propagate.' );
		} catch ( Error $error ) {
			$this->assertSame( 'Allowed memory size exhausted', $error->getMessage() );
		}
		$this->assertGreaterThan( time(), get_transient( 'balikovna_wc_points_balikovny_v2_retry_after' ) );
		$this->assertInstanceOf( WP_Error::class, Points::validate( array( 'id' => 'B10000' ), 'balikovna' ) );
		$this->assertCount( 1, $calls );
	}

	public function test_successful_refresh_stores_small_shards_and_removes_legacy_cache(): void {
		remove_all_filters();
		$key = 'balikovna_wc_points_balikovny_v2';
		update_option( 'balikovna_wc_points_balikovny_v1_stale', array( 'updated' => time(), 'directory' => array() ) );
		set_transient( 'balikovna_wc_points_balikovny_v1', array( 'legacy' ), DAY_IN_SECONDS );
		$rows  = array(
			array( 'id' => 'B10000', 'name' => 'Fresh point', 'type' => 'BALIKOVNY', 'address' => 'Ulice 1, Praha', 'municipality_name' => 'Praha', 'municipality_district_name' => 'Strašnice', 'coor_x_wgs84' => '14.49', 'coor_y_wgs84' => '50.07' ),
			array( 'id' => 'B10001', 'name' => 'Neighbour', 'type' => 'BALIKOVNY' ),
			array( 'id' => 'B60200', 'name' => 'Brno', 'type' => 'BALIKOVNY' ),
			array( 'id' => 'P10003', 'name' => 'Other type', 'type' => 'POST_OFFICE' ),
		);
		$calls = array();
		$this->http( $calls, array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $rows ) ) );

		$point = Points::validate( array( 'id' => 'B10000' ), 'balikovna' );

		$this->assertSame( 'Fresh point', $point['name'] );
		$this->assertSame( 'Ulice 1', $point['street'] );
		$this->assertSame( 'Praha - Strašnice', $point['city'] );
		$this->assertSame( '50.07', $point['lat'] );
		$this->assertSame( '14.49', $point['lng'] );
		$this->assertSame( array( 'B10000', 'B10001' ), array_keys( get_option( $key . '_10' ) ) );
		$this->assertSame( array( 'B60200' ), array_keys( get_option( $key . '_60' ) ) );
		$this->assertSame( array( '10', '60' ), get_option( $key )['shards'] );
		$this->assertSame( array( 'state' => 'fresh', 'count' => 3 ), array_intersect_key( Points::directory_status( 'BALIKOVNY' ), array( 'state' => 1, 'count' => 1 ) ) );
		$this->assertFalse( get_option( $key . '_refresh_lock' ) );
		$this->assertFalse( get_transient( $key . '_retry_after' ) );
		$this->assertFalse( get_option( 'balikovna_wc_points_balikovny_v1_stale' ) );
		$this->assertFalse( get_transient( 'balikovna_wc_points_balikovny_v1' ) );

		update_option( $key, array_merge( get_option( $key ), array( 'updated' => time() - 8 * DAY_IN_SECONDS ) ) );
		$this->http( $calls, array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( $rows[2] ) ) ) );
		Points::refresh_from_action( 'BALIKOVNY' );
		$this->assertFalse( get_option( $key . '_10' ), 'Emptied shards are removed.' );
		$this->assertSame( 'balikovna_unknown_point', Points::validate( array( 'id' => 'B10000' ), 'balikovna' )->get_error_code() );
		$this->assertSame( 'Brno', Points::validate( array( 'id' => 'B60200' ), 'balikovna' )['name'] );
	}

	public function test_expired_directory_is_rejected_after_network_failure(): void {
		remove_all_filters();
		add_filter(
			'balikovna_wc_points_max_stale_age',
			function () {
				return 14 * DAY_IN_SECONDS;
			}
		);
		$this->seed_directory( 'BALIKOVNY', 15 * DAY_IN_SECONDS, array( 'B10000' => 'Expired point' ) );
		$calls = array();
		$this->http( $calls, new WP_Error( 'offline', 'offline' ) );

		$result = Points::validate( array( 'id' => 'B10000' ), 'balikovna' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'balikovna_points_unavailable', $result->get_error_code() );
		$this->assertCount( 1, $calls );
	}

	public function test_admin_maintenance_schedules_only_used_or_stale_directories(): void {
		remove_all_filters();
		$GLOBALS['wpdb']->zone_methods = array( 'balikovna', 'flat_rate' );
		Points::maintain();
		$this->assertSame( array( array( 'BALIKOVNY' ) ), array_column( $GLOBALS['balikovna_test_scheduled_actions'], 'args' ) );

		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$this->seed_directory( 'BALIKOVNY', HOUR_IN_SECONDS, array( 'B10000' => 'Fresh' ) );
		$this->seed_directory( 'POST_OFFICE', 8 * DAY_IN_SECONDS, array( 'P10003' => 'Stale' ) );
		Points::maintain();
		$this->assertSame( array( array( 'POST_OFFICE' ) ), array_column( $GLOBALS['balikovna_test_scheduled_actions'], 'args' ) );

		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$GLOBALS['balikovna_test_doing_ajax']        = true;
		Points::maintain();
		$this->assertSame( array(), $GLOBALS['balikovna_test_scheduled_actions'] );
		$calls = array();
		$this->http( $calls, new WP_Error( 'offline', 'offline' ) );
		Points::refresh_from_action( 'UNKNOWN' );
		$this->assertSame( array(), $calls );
	}

	private function seed_directory( $type, $age, array $points ) {
		$key    = 'balikovna_wc_points_' . strtolower( $type ) . '_v2';
		$shards = array();
		foreach ( $points as $id => $name ) {
			$shards[ substr( $id, 1, 2 ) ][ $id ] = Points::sanitize( array( 'id' => $id, 'name' => $name, 'type' => $type ) );
		}
		foreach ( $shards as $shard => $entries ) {
			update_option( $key . '_' . $shard, $entries );
		}
		update_option(
			$key,
			array(
				'updated' => time() - $age,
				'count'   => count( $points ),
				'shards'  => array_map( 'strval', array_keys( $shards ) ),
			)
		);
	}

	private function http( array &$calls, $response ) {
		$GLOBALS['balikovna_test_points_http'] = function ( $url ) use ( &$calls, $response ) {
			$calls[] = $url;
			return is_callable( $response ) ? $response() : $response;
		};
	}
}
