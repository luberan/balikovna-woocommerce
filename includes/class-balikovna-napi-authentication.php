<?php
/**
 * Česká pošta nAPI CP-HMAC-SHA256 authentication.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Napi_Authentication {

	private $api_token;
	private $secret_key;
	private $clock;
	private $nonce_factory;
	private $last_signature = '';

	public function __construct( $api_token, $secret_key, $clock = null, $nonce_factory = null ) {
		$this->api_token     = trim( (string) $api_token );
		$this->secret_key    = trim( (string) $secret_key );
		$this->clock         = is_callable( $clock ) ? $clock : 'time';
		$this->nonce_factory = is_callable( $nonce_factory ) ? $nonce_factory : 'wp_generate_uuid4';
	}

	public function is_configured() {
		return '' !== $this->api_token && '' !== $this->secret_key;
	}

	/**
	 * Build authentication headers for the exact request body bytes.
	 *
	 * A null body is a bodyless request and therefore has an empty content-hash
	 * component in the official string-to-sign.
	 *
	 * @param string|null $body Request body or null.
	 * @return array<string,string>
	 */
	public function headers( $body = null ) {
		$timestamp            = (string) call_user_func( $this->clock );
		$nonce                = (string) call_user_func( $this->nonce_factory );
		$signature            = $this->signature( $timestamp, $nonce, $body );
		$this->last_signature = $signature;
		$headers              = array(
			'Api-Token'               => $this->api_token,
			'Authorization-Timestamp' => $timestamp,
			'Authorization'           => 'CP-HMAC-SHA256 nonce="' . $nonce . '", signature="' . $signature . '"',
		);

		if ( null !== $body ) {
			$headers['Authorization-Content-SHA256'] = hash( 'sha256', (string) $body );
		}

		return $headers;
	}

	public function redact( $message, array $sensitive_values = array() ) {
		$message          = (string) $message;
		$sensitive_values = array_merge( array( $this->api_token, $this->secret_key, $this->last_signature ), $sensitive_values );
		foreach ( $sensitive_values as $sensitive_value ) {
			if ( '' !== $sensitive_value ) {
				$message = str_replace( $sensitive_value, '[redacted]', $message );
			}
		}
		return is_string( $message ) ? $message : '';
	}

	public function signature( $timestamp, $nonce, $body = null ) {
		$content_hash   = null === $body ? '' : hash( 'sha256', (string) $body );
		$string_to_sign = $content_hash . ';' . (string) $timestamp . ';' . (string) $nonce;
		// The official specs call secretKey "Base64 format" but neither require
		// decoding nor publish a complete vector. Preserve the supplied text as
		// the HMAC key until Česká pošta documents the key bytes unambiguously.
		return base64_encode( hash_hmac( 'sha256', $string_to_sign, $this->secret_key, true ) );
	}
}
