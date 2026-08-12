<?php
/**
 * Read-only Česká pošta B2B-ZSK/CIS nAPI client.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

interface Napi_Transport_Interface {
	public function request( $method, $url, array $args );
}

class WordPress_Napi_Transport implements Napi_Transport_Interface {
	public function request( $method, $url, array $args ) {
		$args['method'] = $method;
		return wp_remote_request( $url, $args );
	}
}

class Napi_Error {

	private $code;
	private $message;
	private $http_status;
	private $global_error;
	private $transient;

	public function __construct( $code, $message, $http_status = 0, $is_global = false, $transient = false ) {
		$this->code         = sanitize_key( (string) $code );
		$this->message      = self::limit( sanitize_text_field( (string) $message ), 240 );
		$this->http_status  = (int) $http_status;
		$this->global_error = (bool) $is_global;
		$this->transient    = (bool) $transient;
	}

	public function get_code() {
		return $this->code;
	}

	public function get_message() {
		return $this->message;
	}

	public function get_http_status() {
		return $this->http_status;
	}

	public function is_global() {
		return $this->global_error;
	}

	public function is_transient() {
		return $this->transient;
	}

	private static function limit( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length, 'UTF-8' ) : substr( $value, 0, $length );
	}
}

class Napi_Client {

	const ZSK_PRODUCTION = 'https://b2b.postaonline.cz:444/restservices/ZSKService/v1';
	const ZSK_SANDBOX    = 'https://b2b-test.postaonline.cz:444/restservices/ZSKService/v1';
	const CIS_PRODUCTION = 'https://b2b.postaonline.cz:444/restservices/CISService/v1';
	const CIS_SANDBOX    = 'https://b2b-test.postaonline.cz:444/restservices/CISService/v1';

	private $authentication;
	private $transport;
	private $environment;
	private $sensitive_values = array();

	public function __construct( Napi_Authentication $authentication, Napi_Transport_Interface $transport, $environment = 'production' ) {
		$this->authentication = $authentication;
		$this->transport      = $transport;
		$this->environment    = 'sandbox' === $environment ? 'sandbox' : 'production';
	}

	/**
	 * Fetch the current aggregated status for one parcel.
	 *
	 * @return Shipment_Status|Napi_Error
	 */
	public function status_info( $tracking_number ) {
		$tracking_number = Order::sanitize_tracking_number( $tracking_number );
		if ( ! self::is_valid_parcel_id( $tracking_number ) ) {
			return new Napi_Error(
				'invalid_parcel_id',
				__( 'Podací číslo neodpovídá formátu operace statusInfo.', 'balikovna-wc' )
			);
		}

		$this->sensitive_values = array( $tracking_number );
		$response               = $this->get( 'zsk', '/parcelStatuses/current/idParcel/' . rawurlencode( $tracking_number ) );
		$this->sensitive_values = array();
		if ( $response instanceof Napi_Error ) {
			return $response;
		}

		$status = Shipment_Status::from_status_info( $response );
		if ( is_wp_error( $status ) ) {
			return new Napi_Error( 'malformed_response', $status->get_error_message(), 200, false, true );
		}
		return $status;
	}

	public static function is_valid_parcel_id( $tracking_number ) {
		$tracking_number = Order::sanitize_tracking_number( $tracking_number );
		return '' !== $tracking_number && strlen( $tracking_number ) <= 13;
	}

	/**
	 * Fetch the aggregated-status dictionary from CISService.
	 *
	 * @return array<string,array<string,string>>|Napi_Error
	 */
	public function statuses_overview() {
		$response = $this->get( 'cis', '/statusesOverview' );
		if ( $response instanceof Napi_Error ) {
			return $response;
		}
		if ( ! isset( $response['statusesList'] ) || ! is_array( $response['statusesList'] ) ) {
			return new Napi_Error(
				'malformed_response',
				__( 'Česká pošta vrátila neplatný číselník stavů.', 'balikovna-wc' ),
				200,
				false,
				true
			);
		}

		$dictionary = array();
		foreach ( $response['statusesList'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = isset( $row['status'] ) ? (string) $row['status'] : '';
			$reason = isset( $row['reason'] ) ? (string) $row['reason'] : '';
			$name   = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			$status = preg_replace( '/[\x00-\x1F\x7F]/u', '', $status );
			$reason = preg_replace( '/[\x00-\x1F\x7F]/u', '', $reason );
			if ( ! is_string( $status ) || '' === trim( $status ) || ! is_string( $reason ) || '' === $name ) {
				continue;
			}
			$status              = $this->limit( trim( $status ), 32 );
			$reason              = $this->limit( $reason, 32 );
			$code                = Shipment_Status::code( $status, $reason );
			$dictionary[ $code ] = array(
				'code'   => $code,
				'status' => $status,
				'reason' => $reason,
				'name'   => $this->limit( $name, 240 ),
			);
		}

		if ( ! $dictionary ) {
			return new Napi_Error(
				'malformed_response',
				__( 'Číselník stavů České pošty neobsahuje žádné použitelné položky.', 'balikovna-wc' ),
				200,
				false,
				true
			);
		}
		return $dictionary;
	}

	/**
	 * @return array|Napi_Error
	 */
	private function get( $service, $path ) {
		if ( ! $this->authentication->is_configured() ) {
			return new Napi_Error(
				'not_configured',
				__( 'Přístup k Česká pošta nAPI není nakonfigurován.', 'balikovna-wc' ),
				0,
				true,
				false
			);
		}

		$base_url = $this->base_url( $service );
		$response = $this->transport->request(
			'GET',
			$base_url . $path,
			array(
				'timeout'             => 15,
				'redirection'         => 0,
				'limit_response_size' => 2 * MB_IN_BYTES,
				'headers'             => array_merge(
					$this->authentication->headers(),
					array( 'Accept' => 'application/json' )
				),
				'user-agent'          => 'Balikovna-WooCommerce/' . BALIKOVNA_WC_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new Napi_Error(
				'network_error',
				__( 'K rozhraní Česká pošta nAPI se nyní nelze připojit.', 'balikovna-wc' ),
				0,
				true,
				true
			);
		}

		$http_status = (int) wp_remote_retrieve_response_code( $response );
		$body        = (string) wp_remote_retrieve_body( $response );
		if ( $http_status < 200 || $http_status >= 300 ) {
			return $this->http_error( $http_status, $body );
		}

		$decoded = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			return new Napi_Error(
				'invalid_json',
				__( 'Česká pošta vrátila neplatnou JSON odpověď.', 'balikovna-wc' ),
				$http_status,
				false,
				true
			);
		}
		return $decoded;
	}

	private function base_url( $service ) {
		if ( 'sandbox' === $this->environment ) {
			return 'cis' === $service ? self::CIS_SANDBOX : self::ZSK_SANDBOX;
		}
		return 'cis' === $service ? self::CIS_PRODUCTION : self::ZSK_PRODUCTION;
	}

	private function http_error( $http_status, $body ) {
		$message = $this->error_message( $body );
		if ( 401 === $http_status || 403 === $http_status ) {
			return new Napi_Error(
				'authentication_failed',
				__( 'Česká pošta odmítla přihlašovací údaje nAPI.', 'balikovna-wc' ),
				$http_status,
				true,
				false
			);
		}
		if ( 429 === $http_status ) {
			return new Napi_Error(
				'rate_limited',
				$message ? $message : __( 'Česká pošta dočasně omezila počet požadavků.', 'balikovna-wc' ),
				$http_status,
				true,
				true
			);
		}
		if ( 404 === $http_status ) {
			return new Napi_Error(
				'parcel_not_found',
				$message ? $message : __( 'Česká pošta podací číslo nenalezla.', 'balikovna-wc' ),
				$http_status,
				false,
				false
			);
		}
		if ( $http_status >= 500 ) {
			return new Napi_Error(
				'service_unavailable',
				$message ? $message : __( 'Služba Česká pošta nAPI je dočasně nedostupná.', 'balikovna-wc' ),
				$http_status,
				true,
				true
			);
		}

		return new Napi_Error(
			'api_error',
			$message ? $message : __( 'Česká pošta odmítla požadavek na stav zásilky.', 'balikovna-wc' ),
			$http_status,
			false,
			false
		);
	}

	private function error_message( $body ) {
		$decoded = json_decode( (string) $body, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		if ( isset( $decoded['message'] ) ) {
			return $this->safe_error_message( $decoded['message'] );
		}
		foreach ( $decoded as $error ) {
			if ( is_array( $error ) && isset( $error['message'] ) ) {
				return $this->safe_error_message( $error['message'] );
			}
		}
		return '';
	}

	private function safe_error_message( $message ) {
		$message = $this->authentication->redact( sanitize_text_field( (string) $message ), $this->sensitive_values );
		return $this->limit( $message, 240 );
	}

	private function limit( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length, 'UTF-8' ) : substr( $value, 0, $length );
	}
}
