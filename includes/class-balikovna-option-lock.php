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
		global $wpdb;
		$existing = get_option( $name, false );
		if ( false !== $existing ) {
			$expired = ! is_array( $existing ) || ! isset( $existing['expires'] ) || (int) $existing['expires'] <= $now;
			if ( ! $expired || ! self::compare( $name, $existing ) ) {
				return false;
			}
		}
		$token = wp_generate_uuid4();
		// add_option() upserts, so two processes could both believe they own the lock.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
				$name,
				maybe_serialize(
					array(
						'token'   => $token,
						'expires' => $now + $ttl,
					)
				)
			)
		);
		self::forget( $name );
		return 1 === $inserted ? $token : false;
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

	private static function compare( $name, $expected, $replacement = null ) {
		global $wpdb;
		if ( null === $replacement ) {
			$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s", $name, maybe_serialize( $expected ) ) );
		} else {
			$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = %s", maybe_serialize( $replacement ), $name, maybe_serialize( $expected ) ) );
		}
		self::forget( $name );
		return 1 === $result;
	}

	private static function forget( $name ) {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
