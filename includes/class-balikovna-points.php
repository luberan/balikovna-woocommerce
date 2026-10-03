<?php
/**
 * Validation and canonicalization of Czech Post pickup points.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-balikovna-option-lock.php';
require_once __DIR__ . '/class-balikovna-tracking-scheduler.php';

class Points {

	const API_URL     = 'https://b2c.cpost.cz/locations/api/points';
	const RETRY_DELAY = 5 * MINUTE_IN_SECONDS;
	const LOCK_TTL    = 2 * MINUTE_IN_SECONDS;
	const TYPES       = array( 'BALIKOVNY', 'POST_OFFICE' );

	public static function init() {
		add_action( Tracking_Scheduler::POINTS_HOOK, array( __CLASS__, 'refresh_from_action' ) );
		add_action( 'admin_init', array( __CLASS__, 'maintain' ) );
	}

	/**
	 * Validate a client selection and replace it with canonical widget data.
	 *
	 * @param array  $point      Client supplied point.
	 * @param string $service_id Shipping service ID.
	 * @return array|\WP_Error
	 */
	public static function validate( array $point, $service_id ) {
		$service = Services::get( $service_id );
		$type    = $service && ! empty( $service['pickup'] ) ? (string) $service['pickup'] : '';
		$id      = isset( $point['id'] ) && is_scalar( $point['id'] ) ? strtoupper( sanitize_text_field( (string) $point['id'] ) ) : '';

		$filtered = apply_filters( 'balikovna_wc_point_validation_result', null, $point, $service_id, $type );
		if ( is_wp_error( $filtered ) ) {
			return $filtered;
		}
		if ( is_array( $filtered ) ) {
			return self::sanitize( $filtered );
		}

		$pattern = self::id_pattern( $type );
		if ( ! $pattern || ! preg_match( $pattern, $id ) ) {
			return new \WP_Error(
				'balikovna_invalid_point_id',
				__( 'Vybrané výdejní místo nemá platné ID pro zvolenou dopravu.', 'balikovna-wc' )
			);
		}

		$canonical = self::find( $type, $id );
		if ( is_wp_error( $canonical ) ) {
			return $canonical;
		}
		if ( null === $canonical ) {
			return new \WP_Error(
				'balikovna_unknown_point',
				__( 'Vybrané výdejní místo již není dostupné. Zvolte prosím jiné.', 'balikovna-wc' )
			);
		}

		return apply_filters( 'balikovna_wc_validated_point', $canonical, $point, $service_id );
	}

	/**
	 * Check whether an already validated point still matches a service type.
	 *
	 * @param array  $point      Point data.
	 * @param string $service_id Shipping service ID.
	 * @return bool
	 */
	public static function matches_service( array $point, $service_id ) {
		$service = Services::get( $service_id );
		$type    = $service && ! empty( $service['pickup'] ) ? (string) $service['pickup'] : '';
		$id      = isset( $point['id'] ) && is_scalar( $point['id'] ) ? strtoupper( (string) $point['id'] ) : '';
		$pattern = self::id_pattern( $type );

		return $pattern && preg_match( $pattern, $id ) && isset( $point['type'] ) && $type === $point['type'];
	}

	/**
	 * Sanitize point fields and enforce storage limits.
	 *
	 * @param array $point Point data.
	 * @return array
	 */
	public static function sanitize( array $point ) {
		$limits = array(
			'id'      => 16,
			'name'    => 160,
			'street'  => 200,
			'city'    => 120,
			'zip'     => 16,
			'country' => 2,
			'type'    => 32,
			'subtype' => 32,
			'lat'     => 32,
			'lng'     => 32,
		);
		$out    = array();

		foreach ( $limits as $key => $limit ) {
			$value       = isset( $point[ $key ] ) && is_scalar( $point[ $key ] ) ? sanitize_text_field( (string) $point[ $key ] ) : '';
			$out[ $key ] = self::limit( $value, $limit );
		}

		$out['id']   = strtoupper( $out['id'] );
		$out['type'] = strtoupper( $out['type'] );

		return $out;
	}

	/**
	 * Report the stored canonical directory for diagnostics and contract checks.
	 *
	 * @param string $type Widget point type.
	 * @return array{state:string,count:int,updated:int}
	 */
	public static function directory_status( $type ) {
		$meta = self::meta( $type );
		return array(
			'state'   => self::state( $type ),
			'count'   => (int) ( $meta['count'] ?? 0 ),
			'updated' => (int) ( $meta['updated'] ?? 0 ),
		);
	}

	/**
	 * Refresh one directory from a background Action Scheduler job.
	 *
	 * @param string $type Widget point type.
	 */
	public static function refresh_from_action( $type ) {
		if ( in_array( $type, self::TYPES, true ) ) {
			self::refresh( $type );
		}
	}

	/**
	 * Schedule refreshes from the administration so customers rarely wait for a download.
	 */
	public static function maintain() {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$in_use = null;
		foreach ( self::TYPES as $type ) {
			$state = self::state( $type );
			if ( 'missing' === $state ) {
				$in_use = null === $in_use ? self::types_in_use() : $in_use;
				if ( ! in_array( $type, $in_use, true ) ) {
					continue;
				}
			}
			if ( 'fresh' !== $state ) {
				self::schedule_refresh( $type );
			}
		}
	}

	/**
	 * Download the widget directory and store it in small per-prefix shards.
	 *
	 * @param string $type Widget point type.
	 * @return true|\WP_Error
	 */
	public static function refresh( $type ) {
		$key = self::key( $type );
		if ( ! self::id_pattern( $type ) || (int) get_transient( $key . '_retry_after' ) > time() ) {
			return self::unavailable();
		}
		$lock  = $key . '_refresh_lock';
		$token = Option_Lock::acquire( $lock, time(), self::LOCK_TTL );
		if ( false === $token ) {
			return self::unavailable();
		}
		try {
			wp_cache_delete( $key, 'options' );
			if ( 'fresh' === self::state( $type ) ) {
				return true;
			}
			// A fatal error during the download must not turn every request into another attempt.
			set_transient( $key . '_retry_after', time() + self::RETRY_DELAY, self::RETRY_DELAY );
			wp_raise_memory_limit( 'balikovna_wc_points' );
			$response = wp_safe_remote_get(
				add_query_arg( 'type[]', $type, apply_filters( 'balikovna_wc_points_api_url', self::API_URL, $type ) ),
				array(
					'timeout'             => 15,
					'redirection'         => 0,
					'limit_response_size' => 8 * MB_IN_BYTES,
					'user-agent'          => 'Balikovna-WooCommerce/' . BALIKOVNA_WC_VERSION,
				)
			);
			if ( ! Option_Lock::refresh( $lock, $token, time(), self::LOCK_TTL ) || is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return self::unavailable();
			}
			$body = wp_remote_retrieve_body( $response );
			unset( $response );
			$rows = json_decode( $body, true );
			unset( $body );
			$shards = is_array( $rows ) ? self::build_shards( $rows, $type ) : array();
			unset( $rows );
			if ( ! $shards || ! Option_Lock::refresh( $lock, $token, time(), self::LOCK_TTL ) ) {
				return self::unavailable();
			}
			self::store( $type, $shards );
			delete_transient( $key . '_retry_after' );
			return true;
		} finally {
			Option_Lock::release( $lock, $token );
		}
	}

	/**
	 * Find one canonical point; only its small shard is loaded.
	 *
	 * @param string $type Widget point type.
	 * @param string $id   Normalized point ID.
	 * @return array|null|\WP_Error
	 */
	private static function find( $type, $id ) {
		$provided = apply_filters( 'balikovna_wc_points_directory', null, $type );
		if ( is_array( $provided ) ) {
			return isset( $provided[ $id ] ) && is_array( $provided[ $id ] ) ? $provided[ $id ] : null;
		}

		$state = self::state( $type );
		if ( 'missing' === $state ) {
			$refreshed = self::refresh( $type );
			if ( is_wp_error( $refreshed ) ) {
				return $refreshed;
			}
		} elseif ( 'stale' === $state ) {
			self::schedule_refresh( $type );
		}

		$shard = get_option( self::shard_option( $type, substr( $id, 1, 2 ) ), array() );
		return is_array( $shard ) && isset( $shard[ $id ] ) && is_array( $shard[ $id ] ) ? $shard[ $id ] : null;
	}

	/**
	 * @param string $type Widget point type.
	 * @return string fresh|stale|missing
	 */
	private static function state( $type ) {
		$meta    = self::meta( $type );
		$updated = (int) ( $meta['updated'] ?? 0 );
		if ( $updated < 1 || empty( $meta['count'] ) ) {
			return 'missing';
		}
		$ttl       = max( HOUR_IN_SECONDS, (int) apply_filters( 'balikovna_wc_points_cache_ttl', 7 * DAY_IN_SECONDS, $type ) );
		$max_stale = max( $ttl, (int) apply_filters( 'balikovna_wc_points_max_stale_age', 30 * DAY_IN_SECONDS, $type ) );
		$age       = time() - $updated;
		if ( $age >= $max_stale ) {
			return 'missing';
		}
		return $age >= 0 && $age < $ttl ? 'fresh' : 'stale';
	}

	private static function meta( $type ) {
		$meta = get_option( self::key( $type ), array() );
		return is_array( $meta ) ? $meta : array();
	}

	private static function schedule_refresh( $type ) {
		if ( function_exists( 'as_schedule_single_action' ) && (int) get_transient( self::key( $type ) . '_retry_after' ) <= time() ) {
			as_schedule_single_action( time(), Tracking_Scheduler::POINTS_HOOK, array( $type ), Tracking_Scheduler::GROUP, true );
		}
	}

	/**
	 * Pickup types used by an enabled shipping zone method.
	 *
	 * @return string[]
	 */
	private static function types_in_use() {
		global $wpdb;
		$methods = (array) $wpdb->get_col( "SELECT DISTINCT method_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE is_enabled = 1" );
		$types   = array();
		foreach ( Services::all() as $service_id => $service ) {
			if ( ! empty( $service['pickup'] ) && in_array( (string) $service_id, $methods, true ) ) {
				$types[] = (string) $service['pickup'];
			}
		}
		return array_values( array_unique( $types ) );
	}

	private static function store( $type, array $shards ) {
		$previous = self::meta( $type );
		$count    = 0;
		foreach ( $shards as $shard => $points ) {
			update_option( self::shard_option( $type, (string) $shard ), $points, false );
			$count += count( $points );
		}
		foreach ( (array) ( $previous['shards'] ?? array() ) as $shard ) {
			if ( ! isset( $shards[ (string) $shard ] ) ) {
				delete_option( self::shard_option( $type, (string) $shard ) );
			}
		}
		update_option(
			self::key( $type ),
			array(
				'updated' => time(),
				'count'   => $count,
				'shards'  => array_map( 'strval', array_keys( $shards ) ),
			),
			false
		);

		// Single-blob caches used before sharding.
		$legacy = 'balikovna_wc_points_' . self::slug( $type ) . '_v1';
		delete_transient( $legacy );
		delete_transient( $legacy . '_retry_after' );
		delete_option( $legacy . '_stale' );
		delete_option( $legacy . '_refresh_lock' );
	}

	/**
	 * Convert raw widget rows into canonical points grouped by the first two ID digits.
	 *
	 * Rows are consumed while building, so the decoded response is released early.
	 *
	 * @param array  $rows Raw API rows; emptied by this method.
	 * @param string $type Expected point type.
	 * @return array<string,array<string,array>>
	 */
	private static function build_shards( array &$rows, $type ) {
		$shards  = array();
		$pattern = self::id_pattern( $type );

		while ( $rows ) {
			$row = array_pop( $rows );
			if ( ! is_array( $row ) || ( $row['type'] ?? '' ) !== $type ) {
				continue;
			}
			$id = isset( $row['id'] ) && is_scalar( $row['id'] ) ? strtoupper( sanitize_text_field( (string) $row['id'] ) ) : '';
			if ( ! $pattern || ! preg_match( $pattern, $id ) ) {
				continue;
			}

			$address  = isset( $row['address'] ) && is_scalar( $row['address'] ) ? sanitize_text_field( (string) $row['address'] ) : '';
			$street   = trim( (string) strtok( $address, ',' ) );
			$city     = isset( $row['municipality_name'] ) && is_scalar( $row['municipality_name'] ) ? (string) $row['municipality_name'] : '';
			$district = isset( $row['municipality_district_name'] ) && is_scalar( $row['municipality_district_name'] ) ? (string) $row['municipality_district_name'] : '';
			if ( $district && $district !== $city ) {
				$city .= ' - ' . $district;
			}

			$shards[ substr( $id, 1, 2 ) ][ $id ] = self::sanitize(
				array(
					'id'      => $id,
					'name'    => $row['name'] ?? '',
					'street'  => $street,
					'city'    => $city,
					'zip'     => $row['zip'] ?? '',
					'country' => $row['country'] ?? 'CZ',
					'type'    => $type,
					'subtype' => $row['subtype'] ?? '',
					'lat'     => $row['coor_y_wgs84'] ?? '',
					'lng'     => $row['coor_x_wgs84'] ?? '',
				)
			);
		}

		ksort( $shards, SORT_STRING );
		foreach ( $shards as $shard => $points ) {
			ksort( $points, SORT_STRING );
			$shards[ $shard ] = $points;
		}
		return $shards;
	}

	private static function key( $type ) {
		return 'balikovna_wc_points_' . self::slug( $type ) . '_v2';
	}

	private static function slug( $type ) {
		return strtolower( sanitize_key( $type ) );
	}

	private static function shard_option( $type, $shard ) {
		return self::key( $type ) . '_' . $shard;
	}

	private static function unavailable() {
		return new \WP_Error(
			'balikovna_points_unavailable',
			__( 'Seznam výdejních míst se nyní nepodařilo ověřit. Zkuste výběr prosím znovu.', 'balikovna-wc' )
		);
	}

	private static function id_pattern( $type ) {
		if ( 'BALIKOVNY' === $type ) {
			return '/^B\d{5}$/';
		}
		if ( 'POST_OFFICE' === $type ) {
			return '/^P\d{5}$/';
		}
		return null;
	}

	private static function limit( $value, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length, 'UTF-8' );
		}
		return substr( $value, 0, $length );
	}
}
