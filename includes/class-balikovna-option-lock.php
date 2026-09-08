<?php
/**
 * Token-owned option locks with atomic renewal and release.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Option_Lock {

	public static function acquire( $name, $now, $ttl ) {
		$existing = get_option( $name, array() );
		if ( is_array( $existing ) && isset( $existing['expires'] ) && (int) $existing['expires'] <= $now && ! self::compare( $name, $existing ) ) {
			return false;
		}
		$token = wp_generate_uuid4();
		return add_option(
			$name,
			array(
				'token'   => $token,
				'expires' => $now + $ttl,
			),
			'',
			false
		) ? $token : false;
	}

	public static function refresh( $name, $token, $now, $ttl ) {
		$lock = get_option( $name, array() );
		if ( ! is_array( $lock ) || ! isset( $lock['token'] ) || ! hash_equals( (string) $lock['token'], (string) $token ) ) {
			return false;
		}
		$renewed            = $lock;
		$renewed['expires'] = max( $now + $ttl, (int) $lock['expires'] + 1 );
		return self::compare( $name, $lock, $renewed );
	}

	public static function release( $name, $token ) {
		$lock = get_option( $name, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			self::compare( $name, $lock );
		}
	}

	public static function replace( $name, array $expected, array $replacement ) {
		return self::compare( $name, $expected, $replacement );
	}

	private static function compare( $name, array $expected, $replacement = null ) {
		global $wpdb;
		if ( null === $replacement ) {
			$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s", $name, maybe_serialize( $expected ) ) );
		} else {
			$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = %s", maybe_serialize( $replacement ), $name, maybe_serialize( $expected ) ) );
		}
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === $result;
	}
}
