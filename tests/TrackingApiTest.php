<?php

use Balikovna_WC\Napi_Authentication;
use Balikovna_WC\Napi_Client;
use Balikovna_WC\Napi_Error;
use Balikovna_WC\Napi_Transport_Interface;
use Balikovna_WC\Shipment_Status;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Napi_Transport implements Napi_Transport_Interface {
	public $requests = array();
	private $responses;

	public function __construct( array $responses ) {
		$this->responses = $responses;
	}

	public function request( $method, $url, array $args ) {
		$this->requests[] = compact( 'method', 'url', 'args' );
		return array_shift( $this->responses );
	}
}

final class TrackingApiTest extends TestCase {
	private function client( array $responses, &$transport = null ) {
		$transport = new Balikovna_Test_Napi_Transport( $responses );
		$auth      = new Napi_Authentication(
			'public-api-token',
			'test-secret',
			function () {
				return 1593561601;
			},
			function () {
				return '74b03f83-04d5-4c60-9ed9-12b6657b4db7';
			}
		);
		return new Napi_Client( $auth, $transport, 'sandbox' );
	}

	private function response( $code, $body ) {
		return array(
			'response' => array( 'code' => $code ),
			'body'     => $body,
		);
	}

	public function test_bodyless_get_signature_matches_fixed_vector(): void {
		$auth = new Napi_Authentication(
			'public-api-token',
			'test-secret',
			function () {
				return 1593561601;
			},
			function () {
				return '74b03f83-04d5-4c60-9ed9-12b6657b4db7';
			}
		);

		$headers = $auth->headers();

		$this->assertSame( 'public-api-token', $headers['Api-Token'] );
		$this->assertSame( '1593561601', $headers['Authorization-Timestamp'] );
		$this->assertSame(
			'CP-HMAC-SHA256 nonce="74b03f83-04d5-4c60-9ed9-12b6657b4db7", signature="w448YJQ2qFtSfmCEKCigwa+xbSodGl3f7LZOpZgJnxo="',
			$headers['Authorization']
		);
		$this->assertArrayNotHasKey( 'Authorization-Content-SHA256', $headers );
		$this->assertSame(
			'Failure [redacted] [redacted] [redacted] parcel [redacted]',
			$auth->redact(
				'Failure public-api-token test-secret w448YJQ2qFtSfmCEKCigwa+xbSodGl3f7LZOpZgJnxo= parcel ABC123456',
				array( 'ABC123456' )
			)
		);
	}

	public function test_successful_status_info_is_parsed(): void {
		$client = $this->client(
			array(
				$this->response(
					200,
					json_encode(
						array(
							'idParcel'    => 'BA1234567890A',
							'parcelStatus' => array(
								'statusID'         => '44',
								'reasonID'         => '01',
								'datetime'         => '2026-08-12T10:00:00+02:00',
								'statusDescription' => 'V přepravě',
							),
						)
					)
				),
			),
			$transport
		);

		$status = $client->status_info( 'ba 1234567890 a' );

		$this->assertInstanceOf( Shipment_Status::class, $status );
		$this->assertSame( '44/01', $status->get_code() );
		$this->assertSame( 'V přepravě', $status->get_label() );
		$this->assertSame( '2026-08-12T10:00:00+02:00', $status->get_event_at() );
		$this->assertStringEndsWith( '/parcelStatuses/current/idParcel/BA1234567890A', $transport->requests[0]['url'] );
		$this->assertSame( 'GET', $transport->requests[0]['method'] );
	}

	public function test_malformed_status_response_is_rejected(): void {
		$client = $this->client(
			array( $this->response( 200, '{"idParcel":"BA1234567890A","parcelStatus":{"reasonID":"00"}}' ) )
		);

		$result = $client->status_info( 'BA1234567890A' );

		$this->assertInstanceOf( Napi_Error::class, $result );
		$this->assertSame( 'malformed_response', $result->get_code() );
		$this->assertTrue( $result->is_transient() );
	}

	public function test_invalid_json_is_rejected(): void {
		$client = $this->client( array( $this->response( 200, '{not-json' ) ) );

		$result = $client->status_info( 'BA1234567890A' );

		$this->assertInstanceOf( Napi_Error::class, $result );
		$this->assertSame( 'invalid_json', $result->get_code() );
	}

	public function test_unknown_carrier_status_is_preserved_without_guessed_semantics(): void {
		$client = $this->client(
			array(
				$this->response(
					200,
					'{"idParcel":"BA1234567890A","parcelStatus":{"statusID":"NEW","reasonID":"X 1","statusDescription":"Nový stav"}}'
				)
			)
		);

		$status = $client->status_info( 'BA1234567890A' );

		$this->assertInstanceOf( Shipment_Status::class, $status );
		$this->assertSame( 'NEW/X%201', $status->get_code() );
		$this->assertSame( Shipment_Status::SEMANTIC_UNKNOWN, Shipment_Status::semantic_for_label( $status->get_label() ) );
	}

	public function test_statuses_overview_uses_cis_and_preserves_case_sensitive_codes(): void {
		$client = $this->client(
			array(
				$this->response(
					200,
					'{"statusesList":[{"status":"9I","reason":"00","name":"DORUČENO"},{"status":"9i","reason":"  ","name":"Jiný stav"}]}'
				)
			),
			$transport
		);

		$dictionary = $client->statuses_overview();

		$this->assertIsArray( $dictionary );
		$this->assertArrayHasKey( '9I/00', $dictionary );
		$this->assertArrayHasKey( '9i/%20%20', $dictionary );
		$this->assertStringStartsWith( Napi_Client::CIS_SANDBOX, $transport->requests[0]['url'] );
	}

	public function test_authentication_error_is_global_for_single_object_and_error_array(): void {
		foreach (
			array(
				'{"code":-1,"status":"401","message":"Invalid credentials"}',
				'[{"code":-1,"status":"401","message":"Invalid signature"}]',
			) as $body
		) {
			$client = $this->client( array( $this->response( 401, $body ) ) );
			$result = $client->status_info( 'BA1234567890A' );

			$this->assertInstanceOf( Napi_Error::class, $result );
			$this->assertSame( 'authentication_failed', $result->get_code() );
			$this->assertTrue( $result->is_global() );
		}
	}

	public function test_http_server_error_is_transient_and_stops_the_batch(): void {
		$client = $this->client(
			array( $this->response( 500, '[{"code":-5,"status":"500","message":"Temporary failure"}]' ) )
		);

		$result = $client->status_info( 'BA1234567890A' );

		$this->assertInstanceOf( Napi_Error::class, $result );
		$this->assertSame( 'service_unavailable', $result->get_code() );
		$this->assertTrue( $result->is_transient() );
		$this->assertTrue( $result->is_global() );
	}

	public function test_api_error_redacts_exact_nonstandard_parcel_identifier(): void {
		$client = $this->client(
			array( $this->response( 404, '{"message":"Parcel ABC123456 not found"}' ) )
		);

		$result = $client->status_info( 'ABC123456' );

		$this->assertInstanceOf( Napi_Error::class, $result );
		$this->assertSame( 'Parcel [redacted] not found', $result->get_message() );
	}
}