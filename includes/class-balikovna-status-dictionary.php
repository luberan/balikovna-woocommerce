<?php
/**
 * Cached Česká pošta aggregated-status dictionary.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Status_Dictionary {

	const OPTION_NAME = 'balikovna_wc_tracking_status_dictionary';
	const CACHE_TTL   = DAY_IN_SECONDS;

	private $client;

	public function __construct( Napi_Client $client ) {
		$this->client = $client;
	}

	public function get() {
		$cache = $this->cache();
		return isset( $cache['statuses'] ) && is_array( $cache['statuses'] ) ? $cache['statuses'] : array();
	}

	public function get_updated_at() {
		$cache = $this->cache();
		return isset( $cache['updated_at'] ) ? (int) $cache['updated_at'] : 0;
	}

	public function get_last_error() {
		$cache = $this->cache();
		return isset( $cache['last_error'] ) && is_array( $cache['last_error'] ) ? $cache['last_error'] : array();
	}

	public function is_stale() {
		$updated_at = $this->get_updated_at();
		$ttl        = max( HOUR_IN_SECONDS, (int) apply_filters( 'balikovna_wc_status_dictionary_ttl', self::CACHE_TTL ) );
		return 0 === $updated_at || $updated_at + $ttl < time();
	}

	/**
	 * Refresh statusesOverview without discarding a previous successful cache.
	 *
	 * @return array|Napi_Error
	 */
	public function refresh( $force = false ) {
		if ( ! $force && ! $this->is_stale() ) {
			return $this->get();
		}

		$previous = $this->get();
		$result   = $this->client->statuses_overview();
		if ( $result instanceof Napi_Error ) {
			$cache               = $this->cache();
			$cache['last_error'] = array(
				'at'      => time(),
				'code'    => $result->get_code(),
				'message' => $result->get_message(),
			);
			update_option( self::OPTION_NAME, $cache, false );
			return $result;
		}
		Tracking_Settings::initialize_status_defaults( $result );
		Tracking_Settings::reconcile_status_dictionary( $result, $previous );
		foreach ( $previous as $code => $row ) {
			if ( ! isset( $result[ $code ] ) && ! empty( $row['observed'] ) ) {
				$result[ $code ] = $row;
			}
		}

		$cache = array(
			'updated_at' => time(),
			'statuses'   => $result,
			'last_error' => array(),
		);
		update_option( self::OPTION_NAME, $cache, false );
		return $result;
	}

	/**
	 * Preserve an unknown status observed from statusInfo for display/configuration.
	 */
	public function remember( Shipment_Status $status ) {
		$cache      = $this->cache();
		$dictionary = isset( $cache['statuses'] ) && is_array( $cache['statuses'] ) ? $cache['statuses'] : array();
		$code       = $status->get_code();
		if ( isset( $dictionary[ $code ] ) ) {
			return;
		}

		$dictionary[ $code ] = array(
			'code'     => $code,
			'status'   => $status->get_status_id(),
			'reason'   => $status->get_reason_id(),
			'name'     => $status->get_label() ? $status->get_label() : $code,
			'observed' => true,
		);
		$cache['statuses']   = $dictionary;
		update_option( self::OPTION_NAME, $cache, false );
	}

	private function cache() {
		$cache = get_option( self::OPTION_NAME, array() );
		return is_array( $cache ) ? $cache : array();
	}
}
