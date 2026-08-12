<?php
/**
 * Shipment tracking settings and defaults.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Tracking_Settings {

	const OPTION_NAME           = 'balikovna_wc_tracking_settings';
	const DEFAULT_BATCH_SIZE    = 100;
	const DEFAULT_TRACKING_DAYS = 14;
	const MAX_BATCH_SIZE        = 500;
	const MAX_TRACKING_DAYS     = 365;

	public static function get( array $dictionary = array() ) {
		$stored = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $stored ) ) {
			return self::defaults( $dictionary );
		}

		return array_merge( self::defaults(), $stored );
	}

	public static function defaults( array $dictionary = array() ) {
		return array(
			'enabled'                     => false,
			'api_token'                   => '',
			'secret_key'                  => '',
			'environment'                 => 'production',
			'batch_size'                  => self::DEFAULT_BATCH_SIZE,
			'tracking_days'               => self::DEFAULT_TRACKING_DAYS,
			'order_statuses'              => self::default_order_statuses(),
			'poll_statuses'               => self::default_poll_statuses( $dictionary ),
			'known_status_codes'          => array_keys( $dictionary ),
			'auto_order_status'           => false,
			'status_mappings'             => self::default_status_mappings( $dictionary ),
			'mapping_revision'            => 1,
			'status_defaults_initialized' => ! empty( $dictionary ),
		);
	}

	/**
	 * Initialize carrier defaults exactly once, after the first dictionary fetch.
	 */
	public static function initialize_status_defaults( array $dictionary ) {
		if ( ! $dictionary ) {
			return;
		}
		$stored = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $stored ) ) {
			update_option( self::OPTION_NAME, self::defaults( $dictionary ), false );
			return;
		}
		if ( ! empty( $stored['status_defaults_initialized'] ) ) {
			return;
		}

		$stored['poll_statuses']               = self::default_poll_statuses( $dictionary );
		$stored['known_status_codes']          = array_keys( $dictionary );
		$stored['status_mappings']             = self::default_status_mappings( $dictionary );
		$stored['status_defaults_initialized'] = true;
		$stored['mapping_revision']            = max( 1, (int) ( $stored['mapping_revision'] ?? 0 ) ) + 1;
		update_option( self::OPTION_NAME, array_merge( self::defaults(), $stored ), false );
	}

	/**
	 * Reconcile newly published official codes without changing existing choices.
	 *
	 * The CIS schema exposes no stable aggregate parent ID, so normalized `name`
	 * is used only to find conservative inheritance candidates. Persisted keys
	 * remain the stable status/reason code.
	 */
	public static function reconcile_status_dictionary( array $dictionary, array $previous_dictionary = array() ) {
		if ( ! $dictionary ) {
			return false;
		}

		$stored = get_option( self::OPTION_NAME, null );
		if ( ! is_array( $stored ) || empty( $stored['status_defaults_initialized'] ) ) {
			self::initialize_status_defaults( $dictionary );
			return true;
		}

		$settings       = array_merge( self::defaults(), $stored );
		$known_codes    = array_values( array_unique( array_map( 'strval', (array) $settings['known_status_codes'] ) ) );
		$poll_statuses  = array_values( array_unique( array_map( 'strval', (array) $settings['poll_statuses'] ) ) );
		$mappings       = is_array( $settings['status_mappings'] ) ? $settings['status_mappings'] : array();
		$all_rows       = array();
		$observed_codes = array();

		foreach ( array( $previous_dictionary, $dictionary ) as $source ) {
			foreach ( $source as $code => $row ) {
				if ( is_array( $row ) && ! empty( $row['observed'] ) ) {
					$observed_codes[] = (string) $code;
				} elseif ( is_array( $row ) ) {
					$all_rows[ (string) $code ] = $row;
				}
			}
		}

		$original_mappings = $mappings;
		$clean_known_codes = array_values( array_diff( $known_codes, $observed_codes ) );
		$changed           = $clean_known_codes !== $known_codes;
		$known_codes       = $clean_known_codes;
		foreach ( $dictionary as $code => $row ) {
			$code = (string) $code;
			if ( ! is_array( $row ) || ! empty( $row['observed'] ) || in_array( $code, $known_codes, true ) ) {
				continue;
			}

			$group_key         = self::status_group_key( $row, $code );
			$known_group_codes = array();
			foreach ( $known_codes as $known_code ) {
				if ( isset( $all_rows[ $known_code ] ) && self::status_group_key( $all_rows[ $known_code ], $known_code ) === $group_key ) {
					$known_group_codes[] = $known_code;
				}
			}

			$mapping_values = array();
			$poll_values    = array();
			foreach ( $known_group_codes as $known_code ) {
				$mapping_values[] = isset( $mappings[ $known_code ] )
					? self::normalize_order_status( $mappings[ $known_code ] )
					: '';
				$poll_values[]    = in_array( $known_code, $poll_statuses, true );
			}
			$mapping_values = array_values( array_unique( $mapping_values ) );
			$poll_values    = array_values( array_unique( $poll_values, SORT_REGULAR ) );

			if ( ! array_key_exists( $code, $mappings ) && 1 === count( $mapping_values ) && '' !== $mapping_values[0] ) {
				$mappings[ $code ] = $mapping_values[0];

				$settings['status_mappings'] = $mappings;
			}

			$continue_polling = ! $known_group_codes || 1 !== count( $poll_values ) || true === $poll_values[0];
			if ( $continue_polling && ! in_array( $code, $poll_statuses, true ) ) {
				$poll_statuses[] = $code;
			} elseif ( ! $continue_polling ) {
				$poll_statuses = array_values( array_diff( $poll_statuses, array( $code ) ) );
			}
			$known_codes[] = $code;
			$changed       = true;
		}

		if ( ! $changed ) {
			return false;
		}

		if ( ! self::mappings_equal( $mappings, $original_mappings ) ) {
			$settings['mapping_revision'] = max( 1, (int) $settings['mapping_revision'] ) + 1;
		}
		$settings['known_status_codes'] = array_values( array_unique( $known_codes ) );
		$settings['poll_statuses']      = array_values( array_unique( $poll_statuses ) );
		$settings['status_mappings']    = $mappings;
		update_option( self::OPTION_NAME, $settings, false );
		return true;
	}

	/**
	 * Sanitize a complete settings form payload.
	 */
	public static function sanitize( array $input, array $existing, array $dictionary ) {
		$order_statuses = self::order_statuses();
		$allowed_codes  = array_fill_keys( array_keys( $dictionary ), true );
		$official_codes = array();
		$observed_codes = array();
		foreach ( $dictionary as $code => $row ) {
			if ( is_array( $row ) && ! empty( $row['observed'] ) ) {
				$observed_codes[] = (string) $code;
			} else {
				$official_codes[] = (string) $code;
			}
		}
		$batch_size    = isset( $input['batch_size'] ) ? absint( $input['batch_size'] ) : self::DEFAULT_BATCH_SIZE;
		$tracking_days = isset( $input['tracking_days'] ) ? absint( $input['tracking_days'] ) : self::DEFAULT_TRACKING_DAYS;
		$api_token     = isset( $existing['api_token'] ) ? (string) $existing['api_token'] : '';
		if ( ! empty( $input['clear_api_token'] ) ) {
			$api_token = '';
		} elseif ( isset( $input['api_token'] ) && '' !== trim( (string) $input['api_token'] ) ) {
			$api_token = self::limit( sanitize_text_field( (string) $input['api_token'] ), 160 );
		}
		$secret_key = isset( $existing['secret_key'] ) ? (string) $existing['secret_key'] : '';
		if ( ! empty( $input['clear_secret'] ) ) {
			$secret_key = '';
		} elseif ( isset( $input['secret_key'] ) && '' !== trim( (string) $input['secret_key'] ) ) {
			$secret_key = self::limit( sanitize_text_field( (string) $input['secret_key'] ), 512 );
		}

		$selected_orders = array();
		foreach ( isset( $input['order_statuses'] ) ? (array) $input['order_statuses'] : array() as $status ) {
			$status = self::normalize_order_status( $status );
			if ( isset( $order_statuses[ $status ] ) ) {
				$selected_orders[] = $status;
			}
		}

		if ( $dictionary ) {
			$poll_statuses = array();
			foreach ( isset( $input['poll_statuses'] ) ? (array) $input['poll_statuses'] : array() as $code ) {
				$code = (string) $code;
				if ( isset( $allowed_codes[ $code ] ) ) {
					$poll_statuses[] = $code;
				}
			}

			$mappings = array();
			foreach ( isset( $input['status_mappings'] ) ? (array) $input['status_mappings'] : array() as $code => $target ) {
				$code   = (string) $code;
				$target = self::normalize_order_status( $target );
				if ( isset( $allowed_codes[ $code ] ) && isset( $order_statuses[ $target ] ) ) {
					$mappings[ $code ] = $target;
				}
			}
			foreach ( (array) ( $existing['poll_statuses'] ?? array() ) as $code ) {
				if ( ! isset( $allowed_codes[ $code ] ) ) {
					$poll_statuses[] = (string) $code;
				}
			}
			foreach ( (array) ( $existing['status_mappings'] ?? array() ) as $code => $target ) {
				$target = self::normalize_order_status( $target );
				if ( ! isset( $allowed_codes[ $code ] ) && isset( $order_statuses[ $target ] ) ) {
					$mappings[ (string) $code ] = $target;
				}
			}
			$known_status_codes          = array_merge(
				array_diff( (array) ( $existing['known_status_codes'] ?? array() ), $observed_codes ),
				$official_codes
			);
			$status_defaults_initialized = true;
		} else {
			$poll_statuses               = isset( $existing['poll_statuses'] ) ? (array) $existing['poll_statuses'] : array();
			$mappings                    = isset( $existing['status_mappings'] ) ? (array) $existing['status_mappings'] : array();
			$known_status_codes          = isset( $existing['known_status_codes'] ) ? (array) $existing['known_status_codes'] : array();
			$status_defaults_initialized = ! empty( $existing['status_defaults_initialized'] );
		}

		$auto_order_status = ! empty( $input['auto_order_status'] );
		$old_mappings      = isset( $existing['status_mappings'] ) && is_array( $existing['status_mappings'] )
			? $existing['status_mappings']
			: array();
		$mapping_revision  = max( 1, (int) ( $existing['mapping_revision'] ?? 1 ) );
		if ( ! empty( $existing['auto_order_status'] ) !== $auto_order_status || ! self::mappings_equal( $mappings, $old_mappings ) ) {
			++$mapping_revision;
		}
		ksort( $mappings, SORT_STRING );

		return array(
			'enabled'                     => ! empty( $input['enabled'] ),
			'api_token'                   => $api_token,
			'secret_key'                  => $secret_key,
			'environment'                 => isset( $input['environment'] ) && 'sandbox' === $input['environment'] ? 'sandbox' : 'production',
			'batch_size'                  => max( 1, min( self::MAX_BATCH_SIZE, $batch_size ) ),
			'tracking_days'               => max( 1, min( self::MAX_TRACKING_DAYS, $tracking_days ) ),
			'order_statuses'              => array_values( array_unique( $selected_orders ) ),
			'poll_statuses'               => array_values( array_unique( $poll_statuses ) ),
			'known_status_codes'          => array_values( array_unique( $known_status_codes ) ),
			'auto_order_status'           => $auto_order_status,
			'status_mappings'             => $mappings,
			'mapping_revision'            => $mapping_revision,
			'status_defaults_initialized' => $status_defaults_initialized,
		);
	}

	public static function order_statuses() {
		$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		return is_array( $statuses ) ? $statuses : array();
	}

	public static function is_configured( array $settings ) {
		return '' !== trim( (string) ( $settings['api_token'] ?? '' ) )
			&& '' !== trim( (string) ( $settings['secret_key'] ?? '' ) );
	}

	public static function should_poll( $code, array $settings ) {
		if ( '' === (string) $code ) {
			return true;
		}
		if ( in_array( (string) $code, (array) ( $settings['poll_statuses'] ?? array() ), true ) ) {
			return true;
		}
		return ! in_array( (string) $code, (array) ( $settings['known_status_codes'] ?? array() ), true );
	}

	public static function mapping_for( $code, array $settings ) {
		$mappings = isset( $settings['status_mappings'] ) && is_array( $settings['status_mappings'] )
			? $settings['status_mappings']
			: array();
		$target   = isset( $mappings[ $code ] ) ? self::normalize_order_status( $mappings[ $code ] ) : '';
		return isset( self::order_statuses()[ $target ] ) ? $target : '';
	}

	/**
	 * Group low-level status/reason pairs into the official aggregated labels.
	 */
	public static function status_groups( array $dictionary ) {
		$groups = array();
		foreach ( $dictionary as $code => $row ) {
			$name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			if ( '' === $name ) {
				$name = (string) $code;
			}
			$key = sha1( self::status_group_key( $row, $code ) );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'name'  => $name,
					'codes' => array(),
				);
			}
			$groups[ $key ]['codes'][] = (string) $code;
		}
		uasort(
			$groups,
			function ( $left, $right ) {
				return strnatcasecmp( $left['name'], $right['name'] );
			}
		);
		return $groups;
	}

	public static function status_group_key( array $row, $fallback_code = '' ) {
		$name = Shipment_Status::normalize_label( $row['name'] ?? '' );
		return '' !== $name ? 'name:' . $name : 'code:' . (string) $fallback_code;
	}

	public static function normalize_order_status( $status ) {
		$status = sanitize_key( (string) $status );
		if ( '' === $status ) {
			return '';
		}
		return 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status;
	}

	private static function mappings_equal( array $left, array $right ) {
		ksort( $left, SORT_STRING );
		ksort( $right, SORT_STRING );
		return $left === $right;
	}

	private static function default_order_statuses() {
		$available   = self::order_statuses();
		$recommended = array( 'wc-processing', 'wc-shipped', 'wc-ready-pickup' );
		return array_values( array_intersect( $recommended, array_keys( $available ) ) );
	}

	private static function default_poll_statuses( array $dictionary ) {
		$poll = array();
		foreach ( $dictionary as $code => $row ) {
			$semantic = Shipment_Status::semantic_for_label( $row['name'] ?? '' );
			if ( ! in_array( $semantic, array( Shipment_Status::SEMANTIC_DELIVERED, Shipment_Status::SEMANTIC_RETURNED ), true ) ) {
				$poll[] = (string) $code;
			}
		}
		return $poll;
	}

	private static function default_status_mappings( array $dictionary ) {
		$available = self::order_statuses();
		$mappings  = array();
		foreach ( $dictionary as $code => $row ) {
			$semantic = Shipment_Status::semantic_for_label( $row['name'] ?? '' );
			$target   = '';
			if ( in_array( $semantic, array( Shipment_Status::SEMANTIC_ACCEPTED, Shipment_Status::SEMANTIC_TRANSIT ), true ) && isset( $available['wc-shipped'] ) ) {
				$target = 'wc-shipped';
			} elseif ( Shipment_Status::SEMANTIC_PICKUP === $semantic && isset( $available['wc-ready-pickup'] ) ) {
				$target = 'wc-ready-pickup';
			} elseif ( Shipment_Status::SEMANTIC_DELIVERED === $semantic && isset( $available['wc-completed'] ) ) {
				$target = 'wc-completed';
			}
			if ( $target ) {
				$mappings[ (string) $code ] = $target;
			}
		}
		return $mappings;
	}

	private static function limit( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length, 'UTF-8' ) : substr( $value, 0, $length );
	}
}
