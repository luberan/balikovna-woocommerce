<?php
/**
 * Parsed Czech Post shipment status.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Shipment_Status {

	const SEMANTIC_DATA      = 'data';
	const SEMANTIC_ACCEPTED  = 'accepted';
	const SEMANTIC_TRANSIT   = 'transit';
	const SEMANTIC_PICKUP    = 'pickup';
	const SEMANTIC_DELIVERED = 'delivered';
	const SEMANTIC_RETURNING = 'returning';
	const SEMANTIC_RETURNED  = 'returned';
	const SEMANTIC_UNKNOWN   = 'unknown';

	private $parcel_id;
	private $status_id;
	private $reason_id;
	private $code;
	private $label;
	private $event_at;

	private function __construct( $parcel_id, $status_id, $reason_id, $label, $event_at ) {
		$this->parcel_id = $parcel_id;
		$this->status_id = $status_id;
		$this->reason_id = $reason_id;
		$this->code      = self::code( $status_id, $reason_id );
		$this->label     = $label;
		$this->event_at  = $event_at;
	}

	/**
	 * Parse a statusInfo response.
	 *
	 * @param array $data Decoded response.
	 * @return self|\WP_Error
	 */
	public static function from_status_info( array $data ) {
		if ( ! isset( $data['parcelStatus'] ) || ! is_array( $data['parcelStatus'] ) ) {
			return new \WP_Error(
				'balikovna_napi_malformed_status',
				__( 'Česká pošta vrátila neúplný stav zásilky.', 'balikovna-wc' )
			);
		}

		$raw       = $data['parcelStatus'];
		$status_id = self::clean_identifier( $raw['statusID'] ?? '', true );
		$reason_id = self::clean_identifier( $raw['reasonID'] ?? '', false );
		if ( '' === $status_id ) {
			return new \WP_Error(
				'balikovna_napi_malformed_status',
				__( 'Česká pošta vrátila stav zásilky bez identifikátoru.', 'balikovna-wc' )
			);
		}

		$event_at = self::clean_datetime( $raw['datetime'] ?? ( $raw['date'] ?? '' ) );
		$status   = new self(
			self::clean_identifier( $data['idParcel'] ?? '', true ),
			$status_id,
			$reason_id,
			self::limit( sanitize_text_field( (string) ( $raw['statusDescription'] ?? '' ) ), 240 ),
			$event_at
		);

		$filtered = apply_filters( 'balikovna_wc_parsed_carrier_status', $status, $data );
		return $filtered instanceof self ? $filtered : $status;
	}

	public static function code( $status_id, $reason_id ) {
		return rawurlencode( (string) $status_id ) . '/' . rawurlencode( (string) $reason_id );
	}

	public static function semantic_for_label( $label ) {
		$label = self::normalize_label( $label );

		$semantics = array(
			'PREDANA DATA'        => self::SEMANTIC_DATA,
			'ZPRACOVANA DATA'     => self::SEMANTIC_DATA,
			'PODANO'              => self::SEMANTIC_ACCEPTED,
			'V PREPRAVE'          => self::SEMANTIC_TRANSIT,
			'ULOZENO'             => self::SEMANTIC_PICKUP,
			'DORUCENO'            => self::SEMANTIC_DELIVERED,
			'VRACI SE'            => self::SEMANTIC_RETURNING,
			'VRACENO ODESILATELI' => self::SEMANTIC_RETURNED,
		);

		return isset( $semantics[ $label ] ) ? $semantics[ $label ] : self::SEMANTIC_UNKNOWN;
	}

	/**
	 * Normalize an aggregate status label for exact semantic/group comparison.
	 */
	public static function normalize_label( $label ) {
		$label = trim( sanitize_text_field( (string) $label ) );
		if ( function_exists( 'remove_accents' ) ) {
			$label = remove_accents( $label );
		} else {
			$label = strtr(
				$label,
				array(
					'Á' => 'A',
					'Č' => 'C',
					'Ď' => 'D',
					'É' => 'E',
					'Ě' => 'E',
					'Í' => 'I',
					'Ň' => 'N',
					'Ó' => 'O',
					'Ř' => 'R',
					'Š' => 'S',
					'Ť' => 'T',
					'Ú' => 'U',
					'Ů' => 'U',
					'Ý' => 'Y',
					'Ž' => 'Z',
					'á' => 'a',
					'č' => 'c',
					'ď' => 'd',
					'é' => 'e',
					'ě' => 'e',
					'í' => 'i',
					'ň' => 'n',
					'ó' => 'o',
					'ř' => 'r',
					'š' => 's',
					'ť' => 't',
					'ú' => 'u',
					'ů' => 'u',
					'ý' => 'y',
					'ž' => 'z',
				)
			);
		}

		$label = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $label, 'UTF-8' ) : strtoupper( $label );
		$label = preg_replace( '/[!.,;:]+$/u', '', $label );
		$label = is_string( $label ) ? preg_replace( '/\s+/u', ' ', trim( $label ) ) : '';
		return is_string( $label ) ? $label : '';
	}

	public function get_parcel_id() {
		return $this->parcel_id;
	}

	public function get_status_id() {
		return $this->status_id;
	}

	public function get_reason_id() {
		return $this->reason_id;
	}

	public function get_code() {
		return $this->code;
	}

	public function get_label() {
		return $this->label;
	}

	public function get_event_at() {
		return $this->event_at;
	}

	private static function clean_identifier( $value, $trim ) {
		$value = preg_replace( '/[\x00-\x1F\x7F]/u', '', (string) $value );
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = $trim ? trim( $value ) : $value;
		return self::limit( $value, 32 );
	}

	private static function clean_datetime( $value ) {
		$value = self::limit( sanitize_text_field( (string) $value ), 64 );
		return '' !== $value && false !== strtotime( $value ) ? $value : '';
	}

	private static function limit( $value, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $length, 'UTF-8' );
		}
		return substr( $value, 0, $length );
	}
}
