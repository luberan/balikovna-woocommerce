<?php

use Balikovna_WC\Blocks;
use Balikovna_WC\Checkout;
use Balikovna_WC\Napi_Authentication;
use Balikovna_WC\Napi_Client;
use Balikovna_WC\Napi_Transport_Interface;
use Balikovna_WC\Order;
use Balikovna_WC\Points;
use Balikovna_WC\Shipment_Status;
use Balikovna_WC\Status_Dictionary;
use Balikovna_WC\Tracking;
use Balikovna_WC\Tracking_Admin;
use Balikovna_WC\Tracking_Settings;
use PHPUnit\Framework\TestCase;

/**
 * Request and API values may be arrays where strings are expected. They must be
 * rejected as invalid input instead of raising "Array to string conversion".
 */
final class InputTypesTest extends TestCase {
	protected function setUp(): void {
		remove_all_filters();
		$_POST                                 = array();
		$GLOBALS['wpdb']                       = new Balikovna_Test_Lock_Database();
		$GLOBALS['balikovna_test_options']     = array();
		$GLOBALS['balikovna_test_transients']  = array();
		$GLOBALS['balikovna_test_points_http'] = null;
		$GLOBALS['balikovna_test_wc']->session = new Balikovna_Test_Session();
		\WC_Admin_Settings::$errors            = array();
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4' ) );
		add_filter(
			'balikovna_wc_points_directory',
			function ( $directory, $type ) {
				return 'BALIKOVNY' === $type
					? array( 'B10000' => Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'type' => 'BALIKOVNY' ) ) )
					: null;
			},
			10,
			2
		);
	}

	protected function tearDown(): void {
		$_POST                                 = array();
		$GLOBALS['balikovna_test_points_http'] = null;
		remove_all_filters();
	}

	private function ajax( array $post ) {
		$_POST = array_merge( array( 'nonce' => 'valid-nonce' ), $post );
		try {
			Checkout::instance()->ajax_set_point();
		} catch ( Balikovna_Test_Json_Response $response ) {
			return $response;
		}
		$this->fail( 'The AJAX handler must send a JSON response.' );
	}

	public function test_ajax_pickup_selection_rejects_nested_values(): void {
		$response = $this->ajax( array( 'package_key' => array( '0' ), 'rate_id' => 'balikovna:4', 'point' => array( 'id' => 'B10000' ) ) );
		$this->assertFalse( $response->success );
		$this->assertSame( 409, $response->status );

		$response = $this->ajax( array( 'package_key' => '0', 'rate_id' => 'balikovna:4', 'point' => array( 'id' => array( 'B10000' ) ) ) );
		$this->assertFalse( $response->success );
		$this->assertSame( 400, $response->status );
		$this->assertSame( array(), Checkout::get_session_selections( false ) );

		$response = $this->ajax( array( 'package_key' => '0', 'rate_id' => 'balikovna:4', 'point' => array( 'id' => 'B10000', 'phone' => array( '+420777123456' ) ) ) );
		$this->assertTrue( $response->success );
		$this->assertSame( 'B10000', $response->data['point']['id'] );
		$this->assertArrayNotHasKey( 'phone', $response->data, 'A nested phone value is ignored.' );
	}

	public function test_store_api_pickup_update_rejects_nested_values(): void {
		$blocks = new Blocks();
		foreach (
			array(
				array( array( 'packageKey' => array( '0' ), 'rateId' => 'balikovna:4' ), 409 ),
				array( array( 'packageKey' => '0', 'rateId' => array( 'balikovna:4' ) ), 409 ),
				array( array( 'packageKey' => '0', 'rateId' => 'balikovna:4', 'point' => array( 'id' => array( 'B10000' ) ) ), 400 ),
			) as $case
		) {
			list( $payload, $status ) = $case;
			try {
				$blocks->update_cart_from_request( $payload );
				$this->fail( 'Invalid Store API data must be rejected.' );
			} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $error ) {
				$this->assertSame( $status, $error->getCode() );
			}
		}

		$blocks->update_cart_from_request( array( 'packageKey' => '0', 'rateId' => 'balikovna:4', 'point' => array( 'id' => 'B10000', 'phone' => array( '+420777123456' ) ) ) );
		$selection = Checkout::get_session_selection( '0', 'balikovna:4' );
		$this->assertSame( 'B10000', $selection['point']['id'] );
		$this->assertArrayNotHasKey( 'phone', $selection );
	}

	public function test_classic_checkout_validation_treats_nested_contact_values_as_missing(): void {
		$errors = new WP_Error();

		Checkout::instance()->validate_selection(
			array(
				'shipping_method' => array( 'balikovna:4', array( 'cp_na_postu:7' ) ),
				'billing_email'   => array( 'customer@example.test' ),
				'billing_phone'   => array( '+420777123456' ),
			),
			$errors
		);

		$this->assertSame( array( 'balikovna_point_required', 'balikovna_email_required', 'balikovna_phone_required' ), $errors->get_error_codes() );
		$this->assertSame( '', Checkout::service_id_from_rate( array( 'balikovna:4' ) ) );
		$this->assertNull( Checkout::normalize_package_key( array( '0' ) ) );
	}

	public function test_admin_tracking_number_must_be_a_single_value(): void {
		$item  = new WC_Order_Item_Shipping( 'balikovna_plus', '4', array( Order::META_TRACKING_NUMBER => 'DR1111111111C' ), 10 );
		$_POST = array(
			Order::TRACKING_NONCE_NAME   => 'valid-nonce',
			'balikovna_tracking_numbers' => array( '10' => array( 'DR1234567890E' ) ),
		);

		Order::instance()->save_tracking_numbers( 123, new WC_Order( array( $item ) ) );

		$this->assertSame( 'DR1111111111C', $item->get_meta( Order::META_TRACKING_NUMBER ) );
		$this->assertSame( 0, $item->save_count );
		$this->assertSame( '', Order::sanitize_tracking_number( array( 'DR1234567890E' ) ) );
	}

	public function test_tracking_settings_ignore_nested_values(): void {
		$dictionary            = array( '91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ) );
		$existing              = Tracking_Settings::defaults( $dictionary );
		$existing['api_token'] = 'stored-token';
		$existing['secret_key'] = 'stored-secret';

		$sanitized = Tracking_Settings::sanitize(
			array(
				'api_token'       => array( 'injected-token' ),
				'secret_key'      => array( 'injected-secret' ),
				'order_statuses'  => array( array( 'wc-processing' ), 'wc-completed' ),
				'poll_statuses'   => array( array( '91/00' ) ),
				'status_mappings' => array( '91/00' => array( 'wc-completed' ) ),
			),
			$existing,
			$dictionary
		);

		$this->assertSame( 'stored-token', $sanitized['api_token'] );
		$this->assertSame( 'stored-secret', $sanitized['secret_key'] );
		$this->assertSame( array( 'wc-completed' ), $sanitized['order_statuses'] );
		$this->assertSame( array(), $sanitized['poll_statuses'] );
		$this->assertSame( array(), $sanitized['status_mappings'] );
	}

	public function test_admin_status_mapping_ignores_nested_group_targets(): void {
		$dictionary = array( '91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ) );
		$settings   = Tracking_Settings::defaults( $dictionary );
		$settings['status_mappings'] = array();
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array( 'updated_at' => time(), 'statuses' => $dictionary, 'last_error' => array() );
		$group_key = array_key_first( Tracking_Settings::status_groups( $dictionary ) );
		$_POST     = array(
			'_wpnonce'           => 'valid-nonce',
			'balikovna_tracking' => array(
				'order_statuses' => array( 'wc-processing' ),
				'mapping_groups' => array( $group_key => array( 'wc-completed' ) ),
			),
		);

		( new Tracking_Admin( new Tracking() ) )->save();

		$this->assertSame( array(), Tracking_Settings::get()['status_mappings'] );
	}

	public function test_pickup_directory_rows_with_nested_values_are_skipped_or_cleaned(): void {
		remove_all_filters();
		$GLOBALS['balikovna_test_points_http'] = function () {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode(
					array(
						array( 'id' => array( 'B10001' ), 'type' => 'BALIKOVNY', 'name' => 'Nested ID' ),
						array( 'id' => 'B10000', 'type' => 'BALIKOVNY', 'name' => array( 'Nested' ), 'address' => array( 'x' ), 'municipality_name' => array( 'Praha' ), 'zip' => '10000' ),
					)
				),
			);
		};

		$point = Points::validate( array( 'id' => 'B10000' ), 'balikovna' );

		$this->assertIsArray( $point );
		$this->assertSame( '', $point['name'] );
		$this->assertSame( '', $point['city'] );
		$this->assertSame( '10000', $point['zip'] );
		$this->assertSame( 'balikovna_unknown_point', Points::validate( array( 'id' => 'B10001' ), 'balikovna' )->get_error_code() );
		$this->assertFalse( Points::matches_service( array( 'id' => array( 'B10000' ), 'type' => 'BALIKOVNY' ), 'balikovna' ) );
	}

	public function test_napi_values_with_unexpected_types_are_rejected(): void {
		$transport = new class() implements Napi_Transport_Interface {
			public function request( $method, $url, array $args ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '{"statusesList":[{"status":["91"],"reason":"00","name":"DORUČENO"},{"status":"91","reason":"00","name":{"cs":"DORUČENO"}},{"status":"44","reason":"00","name":"V PŘEPRAVĚ"},{"status":"95","reason":["00"],"name":"VRACÍ SE"}]}',
				);
			}
		};
		$client = new Napi_Client( new Napi_Authentication( 'token', 'secret' ), $transport, 'sandbox' );

		// A malformed reason is treated like a missing one, a malformed status or name skips the row.
		$this->assertSame( array( '44/00', '95/' ), array_keys( $client->statuses_overview() ) );

		$missing_id = Shipment_Status::from_status_info( array( 'parcelStatus' => array( 'statusID' => array( '91' ), 'reasonID' => '00' ) ) );
		$this->assertInstanceOf( WP_Error::class, $missing_id );

		$status = Shipment_Status::from_status_info(
			array(
				'idParcel'     => array( 'BA1234567890A' ),
				'parcelStatus' => array( 'statusID' => '91', 'reasonID' => array( '00' ), 'statusDescription' => array( 'DORUČENO' ), 'datetime' => array( '2026-10-03' ) ),
			)
		);
		$this->assertInstanceOf( Shipment_Status::class, $status );
		$this->assertSame( '', $status->get_parcel_id() );
		$this->assertSame( '91/', $status->get_code() );
		$this->assertSame( '', $status->get_label() );
		$this->assertSame( '', $status->get_event_at() );
		$this->assertSame( '', Shipment_Status::normalize_label( array( 'DORUČENO' ) ) );
	}
}
