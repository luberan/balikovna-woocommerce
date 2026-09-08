<?php
/**
 * Authenticated credential storage with optional server-side configuration.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Credentials {

	const PREFIX = 'balikovna:v1:';
	const FIELDS = array(
		'api_token'  => 'BALIKOVNA_WC_API_TOKEN',
		'secret_key' => 'BALIKOVNA_WC_SECRET_KEY',
	);

	public static function external( $field ) {
		return isset( self::FIELDS[ $field ] ) ? self::configured( self::FIELDS[ $field ] ) : null;
	}

	public static function encrypt( $value, $field ) {
		$value = (string) $value;
		if ( '' === $value ) {
			return '';
		}
		$key = self::key();
		if ( is_wp_error( $key ) || ! function_exists( 'openssl_encrypt' ) ) {
			return self::error();
		}
		$nonce = random_bytes( 12 );
		$tag   = '';
		$bytes = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, self::PREFIX . $field, 16 );
		return false === $bytes ? self::error() : self::PREFIX . base64_encode( $nonce . $tag . $bytes );
	}

	public static function decrypt( $value, $field ) {
		$value = (string) $value;
		if ( 0 !== strpos( $value, self::PREFIX ) ) {
			return $value;
		}
		$key   = self::key();
		$bytes = base64_decode( substr( $value, strlen( self::PREFIX ) ), true );
		if ( is_wp_error( $key ) || ! function_exists( 'openssl_decrypt' ) || false === $bytes || strlen( $bytes ) <= 28 ) {
			return self::error();
		}
		$value = openssl_decrypt( substr( $bytes, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $bytes, 0, 12 ), substr( $bytes, 12, 16 ), self::PREFIX . $field );
		return false === $value ? self::error() : $value;
	}

	public static function protect( array $settings ) {
		foreach ( self::FIELDS as $field => $name ) {
			$value = null !== self::external( $field ) ? '' : self::encrypt( $settings[ $field ] ?? '', $field );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
			$settings[ $field ] = $value;
		}
		return $settings;
	}

	public static function configuration_error() {
		return ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) || is_wp_error( self::key() ) ? self::error() : null;
	}

	private static function key() {
		$configured = self::configured( 'BALIKOVNA_WC_ENCRYPTION_KEY' );
		if ( null !== $configured ) {
			$key = base64_decode( $configured, true );
			return is_string( $key ) && 32 === strlen( $key ) ? $key : self::error();
		}
		if ( ! defined( 'AUTH_KEY' ) || ! defined( 'AUTH_SALT' ) || strlen( AUTH_KEY ) < 32 || strlen( AUTH_SALT ) < 32 ) {
			return self::error();
		}
		return hash_hkdf( 'sha256', AUTH_KEY . AUTH_SALT, 32, 'balikovna-wc-credentials-v1' );
	}

	private static function configured( $name ) {
		if ( defined( $name ) ) {
			return trim( (string) constant( $name ) );
		}
		$value = getenv( $name );
		return false === $value ? null : trim( $value );
	}

	private static function error() {
		return new \WP_Error( 'balikovna_credentials_unavailable', __( 'Přihlašovací údaje nelze bezpečně uložit nebo přečíst. Ověřte OpenSSL a šifrovací klíč; po změně klíče zadejte údaje znovu.', 'balikovna-wc' ) );
	}
}
