<?php
/**
 * Periodic per-shipment status synchronization.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Shipment_Synchronizer {

	const LOCK_OPTION        = 'balikovna_wc_tracking_sync_lock';
	const DIAGNOSTICS_OPTION = 'balikovna_wc_tracking_diagnostics';
	const LOCK_TTL           = 5 * MINUTE_IN_SECONDS;

	private $client;
	private $dictionary;
	private $orders;
	private $mapper;
	private $logger;
	private $clock;
	private $lock_token = '';

	public function __construct(
		Napi_Client $client,
		Status_Dictionary $dictionary,
		Eligible_Orders $orders,
		Order_Status_Mapper $mapper,
		Tracking_Logger $logger,
		$clock = null
	) {
		$this->client     = $client;
		$this->dictionary = $dictionary;
		$this->orders     = $orders;
		$this->mapper     = $mapper;
		$this->logger     = $logger;
		$this->clock      = is_callable( $clock ) ? $clock : 'time';
	}

	/**
	 * Run one bounded batch. Used by Action Scheduler and the manual admin action.
	 *
	 * @return array<string,mixed>|Napi_Error
	 */
	public function run_batch() {
		$settings = Tracking_Settings::get( $this->dictionary->get() );
		if ( empty( $settings['enabled'] ) || ! Tracking_Settings::is_configured( $settings ) ) {
			return array(
				'orders'    => 0,
				'shipments' => 0,
				'skipped'   => true,
			);
		}

		$lock_token = $this->acquire_lock();
		if ( false === $lock_token ) {
			return new Napi_Error(
				'synchronization_locked',
				__( 'Synchronizace stavu zásilek již probíhá.', 'balikovna-wc' ),
				0,
				false,
				true
			);
		}

		try {
			$this->lock_token  = $lock_token;
			$dictionary_result = $this->dictionary->refresh();
			$this->refresh_lock();
			$dictionary_must_stop = $dictionary_result instanceof Napi_Error
				&& ( ! $this->dictionary->get() || in_array( $dictionary_result->get_code(), array( 'authentication_failed', 'rate_limited' ), true ) );
			if ( $dictionary_must_stop ) {
				$this->record_global_error( $dictionary_result );
				return $dictionary_result;
			}
			$settings               = Tracking_Settings::get( $this->dictionary->get() );
			$settings['batch_size'] = max(
				1,
				min(
					Tracking_Settings::MAX_BATCH_SIZE,
					(int) apply_filters( 'balikovna_wc_tracking_batch_size', $settings['batch_size'], $settings )
				)
			);

			$orders    = $this->orders->find( $settings );
			$processed = 0;
			$checked   = 0;
			foreach ( $orders as $order ) {
				$this->refresh_lock();
				$result   = $this->sync_order( $order, $settings );
				$checked += (int) $result['checked'];
				++$processed;
				if ( $result['global_error'] instanceof Napi_Error ) {
					$this->record_global_error( $result['global_error'] );
					return $result['global_error'];
				}
			}

			if ( $checked > 0 ) {
				$this->record_success();
			}
			return array(
				'orders'    => $processed,
				'shipments' => $checked,
				'skipped'   => false,
			);
		} finally {
			$this->release_lock( $lock_token );
			$this->lock_token = '';
		}
	}

	/**
	 * Synchronize every eligible Czech Post parcel in one order.
	 *
	 * @return array{checked:int,global_error:Napi_Error|null}
	 */
	public function sync_order( \WC_Order $order, array $settings ) {
		if ( ! $this->orders->is_order_eligible( $order, $settings ) ) {
			return array(
				'checked'      => 0,
				'global_error' => null,
			);
		}

		$checked      = 0;
		$had_error    = false;
		$mapping_work = false;
		foreach ( Order::get_shipments( $order ) as $shipment ) {
			$item            = $shipment['item'];
			$tracking_number = $shipment['trackingNumber'];
			if ( ! Napi_Client::is_valid_parcel_id( $tracking_number ) ) {
				continue;
			}

			$stored_tracking = (string) $item->get_meta( Order::META_STATUS_TRACKING_NUMBER, true );
			$stored_code     = (string) $item->get_meta( Order::META_STATUS_CODE, true );
			if ( '' !== $stored_code && $stored_tracking !== $tracking_number ) {
				Order::clear_tracking_status( $item );
				$stored_code = '';
			}

			$eligible = (bool) apply_filters( 'balikovna_wc_tracking_shipment_eligible', true, $shipment, $order, $settings );
			if ( $eligible && Tracking_Settings::should_poll( $stored_code, $settings ) ) {
				$this->refresh_lock();
				$item->update_meta_data( Order::META_STATUS_ATTEMPTED_AT, $this->now() );
				$item->update_meta_data( Order::META_STATUS_TRACKING_NUMBER, $tracking_number );
				$result = $this->client->status_info( $tracking_number );
				$this->refresh_lock();
				if ( $result instanceof Napi_Error ) {
					$item->save();
					$this->logger->api_error( $result, $order->get_id(), $item->get_id() );
					if ( $result->is_global() ) {
						return array(
							'checked'      => $checked,
							'global_error' => $result,
						);
					}
					$had_error = true;
					continue;
				}

				++$checked;
				$old_code  = $stored_code;
				$old_label = (string) $item->get_meta( Order::META_STATUS_LABEL, true );
				$label     = $result->get_label();
				$known     = $this->dictionary->get();
				if ( isset( $known[ $result->get_code() ]['name'] ) ) {
					$label = (string) $known[ $result->get_code() ]['name'];
				}
				$item->update_meta_data( Order::META_STATUS_CODE, $result->get_code() );
				$item->update_meta_data( Order::META_STATUS_LABEL, $label );
				$item->update_meta_data( Order::META_STATUS_EVENT_AT, $result->get_event_at() );
				$item->update_meta_data( Order::META_STATUS_CHECKED_AT, $this->now() );
				$item->update_meta_data( Order::META_STATUS_TRACKING_NUMBER, $tracking_number );
				$item->save();
				$this->dictionary->remember( $result );

				if ( $old_code !== $result->get_code() ) {
					do_action( 'balikovna_wc_shipment_status_changed', $order, $item, $result, $old_code, $old_label );
				}
				$stored_code = $result->get_code();
			}

			if ( $eligible && ! empty( $settings['auto_order_status'] ) && '' !== $stored_code && $this->orders->needs_mapping_evaluation( $item, $stored_code, $settings ) ) {
				$mapping_work = true;
			}
		}

		if ( ! $had_error && $mapping_work && ! empty( $settings['auto_order_status'] ) ) {
			$shipments = Order::get_shipments( $order );
			$mapped    = $this->mapper->apply( $order, $shipments, $settings );
			if ( false !== $mapped ) {
				$this->mark_mapping_evaluated( $order, $shipments, $settings );
			}
		}

		return array(
			'checked'      => $checked,
			'global_error' => null,
		);
	}

	private function mark_mapping_evaluated( \WC_Order $order, array $shipments, array $settings ) {
		foreach ( $shipments as $shipment ) {
			if ( empty( $shipment['trackingNumber'] ) ) {
				continue;
			}
			$eligible = (bool) apply_filters( 'balikovna_wc_tracking_shipment_eligible', true, $shipment, $order, $settings );
			if ( ! $eligible ) {
				continue;
			}
			$item = $shipment['item'];
			$code = (string) $item->get_meta( Order::META_STATUS_CODE, true );
			if ( '' === $code ) {
				continue;
			}
			$item->update_meta_data( Order::META_STATUS_EVALUATED_CODE, $code );
			$item->update_meta_data( Order::META_STATUS_MAPPING_REVISION, (int) ( $settings['mapping_revision'] ?? 1 ) );
			$item->save();
		}
	}

	private function acquire_lock() {
		$now      = $this->now();
		$existing = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $existing ) && isset( $existing['expires'] ) && (int) $existing['expires'] <= $now ) {
			delete_option( self::LOCK_OPTION );
		}
		$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'balikovna-', true );
		$added = add_option(
			self::LOCK_OPTION,
			array(
				'token'   => $token,
				'expires' => $now + self::LOCK_TTL,
			),
			'',
			false
		);
		return $added ? $token : false;
	}

	private function refresh_lock() {
		if ( '' === $this->lock_token ) {
			return;
		}
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( ! is_array( $lock ) || ! isset( $lock['token'] ) || ! hash_equals( (string) $lock['token'], $this->lock_token ) ) {
			return;
		}
		$lock['expires'] = $this->now() + self::LOCK_TTL;
		update_option( self::LOCK_OPTION, $lock, false );
	}

	private function release_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	private function record_success() {
		$diagnostics                    = $this->diagnostics();
		$diagnostics['last_success_at'] = $this->now();
		$diagnostics['last_error']      = array();
		update_option( self::DIAGNOSTICS_OPTION, $diagnostics, false );
	}

	private function record_global_error( Napi_Error $error ) {
		$diagnostics               = $this->diagnostics();
		$diagnostics['last_error'] = array(
			'at'      => $this->now(),
			'code'    => $error->get_code(),
			'message' => $error->get_message(),
		);
		update_option( self::DIAGNOSTICS_OPTION, $diagnostics, false );
	}

	public function diagnostics() {
		$diagnostics = get_option( self::DIAGNOSTICS_OPTION, array() );
		return is_array( $diagnostics ) ? $diagnostics : array();
	}

	private function now() {
		return (int) call_user_func( $this->clock );
	}
}
