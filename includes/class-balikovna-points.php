<?php
/**
 * Validation and canonicalization of Czech Post pickup points.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Points {

	const API_URL = 'https://b2c.cpost.cz/locations/api/points';

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
		$id      = isset( $point['id'] ) ? strtoupper( sanitize_text_field( (string) $point['id'] ) ) : '';

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

		$directory = self::get_directory( $type );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		if ( ! isset( $directory[ $id ] ) ) {
			return new \WP_Error(
				'balikovna_unknown_point',
				__( 'Vybrané výdejní místo již není dostupné. Zvolte prosím jiné.', 'balikovna-wc' )
			);
		}

		return apply_filters( 'balikovna_wc_validated_point', $directory[ $id ], $point, $service_id );
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
		$id      = isset( $point['id'] ) ? strtoupper( (string) $point['id'] ) : '';
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
			$value       = isset( $point[ $key ] ) ? sanitize_text_field( (string) $point[ $key ] ) : '';
			$out[ $key ] = self::limit( $value, $limit );
		}

		$out['id']   = strtoupper( $out['id'] );
		$out['type'] = strtoupper( $out['type'] );

		return $out;
	}

	/**
	 * Load a compact canonical point directory from cache or the widget API.
	 *
	 * @param string $type Widget point type.
	 * @return array|\WP_Error
	 */
	private static function get_directory( $type ) {
		$provided = apply_filters( 'balikovna_wc_points_directory', null, $type );
		if ( is_array( $provided ) ) {
			return $provided;
		}

		$cache_key = 'balikovna_wc_points_' . strtolower( sanitize_key( $type ) ) . '_v1';
		$ttl       = max( HOUR_IN_SECONDS, (int) apply_filters( 'balikovna_wc_points_cache_ttl', 7 * DAY_IN_SECONDS, $type ) );
		$max_stale = max(
			$ttl,
			(int) apply_filters( 'balikovna_wc_points_max_stale_age', 30 * DAY_IN_SECONDS, $type )
		);
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && $cached ) {
			return $cached;
		}
		$stale         = get_option( $cache_key . '_stale', array() );
		$stale_updated = isset( $stale['updated'] ) ? (int) $stale['updated'] : 0;
		$stale_valid   = $stale_updated > 0
			&& $stale_updated <= time()
			&& isset( $stale['directory'] )
			&& is_array( $stale['directory'] )
			&& $stale['directory'];
		if ( $stale_valid && $stale_updated + $ttl > time() ) {
			return $stale['directory'];
		}

		$url      = apply_filters( 'balikovna_wc_points_api_url', self::API_URL, $type );
		$response = wp_safe_remote_get(
			add_query_arg( 'type[]', $type, $url ),
			array(
				'timeout'             => 15,
				'redirection'         => 0,
				'limit_response_size' => 8 * MB_IN_BYTES,
				'user-agent'          => 'Balikovna-WooCommerce/' . BALIKOVNA_WC_VERSION,
			)
		);

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$rows = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $rows ) ) {
				$directory = self::build_directory( $rows, $type );
				if ( $directory ) {
					set_transient( $cache_key, $directory, $ttl );
					update_option(
						$cache_key . '_stale',
						array(
							'updated'   => time(),
							'directory' => $directory,
						),
						false
					);
					return $directory;
				}
			}
		}

		if ( $stale_valid && $stale_updated + $max_stale > time() ) {
			return $stale['directory'];
		}

		return new \WP_Error(
			'balikovna_points_unavailable',
			__( 'Seznam výdejních míst se nyní nepodařilo ověřit. Zkuste výběr prosím znovu.', 'balikovna-wc' )
		);
	}

	/**
	 * Convert raw widget rows into a point-ID keyed directory.
	 *
	 * @param array  $rows Raw API rows.
	 * @param string $type Expected point type.
	 * @return array
	 */
	private static function build_directory( array $rows, $type ) {
		$directory = array();
		$pattern   = self::id_pattern( $type );

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ( $row['type'] ?? '' ) !== $type ) {
				continue;
			}
			$id = isset( $row['id'] ) ? strtoupper( sanitize_text_field( (string) $row['id'] ) ) : '';
			if ( ! $pattern || ! preg_match( $pattern, $id ) ) {
				continue;
			}

			$address  = isset( $row['address'] ) ? sanitize_text_field( (string) $row['address'] ) : '';
			$street   = trim( (string) strtok( $address, ',' ) );
			$city     = isset( $row['municipality_name'] ) ? (string) $row['municipality_name'] : '';
			$district = isset( $row['municipality_district_name'] ) ? (string) $row['municipality_district_name'] : '';
			if ( $district && $district !== $city ) {
				$city .= ' - ' . $district;
			}

			$directory[ $id ] = self::sanitize(
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

		return $directory;
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
