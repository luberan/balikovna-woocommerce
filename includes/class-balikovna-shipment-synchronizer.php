<?php
/**
 * Periodic per-shipment status synchronization.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-balikovna-option-lock.php';

class Shipment_Synchronizer {

	const LOCK_OPTION        = 'balikovna_wc_tracking_sync_lock';
	const DIAGNOSTICS_OPTION = 'balikovna_wc_tracking_diagnostics';
	const LOCK_TTL           = 5 * MINUTE_IN_SECONDS;
	const PENDING_OPTION     = 'balikovna_wc_tracking_pending_batch';
	const BATCH_SECONDS      = 25;
	const REQUEST_RESERVE    = 17;
	const MAX_REQUESTS       = 10;

	private $client;
	private $dictionary;
	private $orders;
	private $mapper;
	private $logger;
	private $clock;
	private $guard;
	private $lock_token = '';
	private $deadline   = 0;
	private $requests   = 0;
	private $pending    = array();
	private $paused     = false;
	private $lock_lost  = false;

	public function __construct(
		Napi_Client $client,
		Status_Dictionary $dictionary,
		Eligible_Orders $orders,
		Order_Status_Mapper $mapper,
		Tracking_Logger $logger,
		$clock = null,
		?Order_Write_Guard $guard = null
	) {
		$this->client     = $client;
		$this->dictionary = $dictionary;
		$this->orders     = $orders;
		$this->mapper     = $mapper;
		$this->logger     = $logger;
		$this->clock      = is_callable( $clock ) ? $clock : 'time';
		$this->guard      = null === $guard ? new Order_Write_Guard() : $guard;
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
			$this->deadline    = $this->now() + self::BATCH_SECONDS;
			$this->requests    = 0;
			$this->paused      = false;
			$this->lock_lost   = false;
			$this->pending     = get_option( self::PENDING_OPTION, array() );
			$dictionary_error  = $this->dictionary->get_last_error();
			$resume_dictionary = ! empty( $this->pending['orders'] ) && $this->dictionary->get()
				&& ! in_array( $dictionary_error['code'] ?? '', array( 'authentication_failed', 'rate_limited' ), true );
			$dictionary_result = $resume_dictionary ? $this->dictionary->get() : $this->dictionary->refresh();
			if ( ! $this->refresh_lock() ) {
				return $this->lost_lock_error();
			}
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

			$orders = array();
			if ( empty( $this->pending['orders'] ) ) {
				$orders        = $this->orders->find( $settings );
				$this->pending = array(
					'orders'    => array_map(
						function ( $order ) {
							return $order->get_id();
						},
						$orders
					),
					'done'      => array(),
					'had_error' => false,
				);
				$this->save_pending();
			}
			$loaded = array();
			foreach ( $orders as $order ) {
				$loaded[ $order->get_id() ] = $order;
			}
			$processed = 0;
			$checked   = 0;
			while ( ! empty( $this->pending['orders'] ) ) {
				if ( ! $this->can_continue() ) {
					break;
				}
				$order_id = reset( $this->pending['orders'] );
				$order    = $loaded[ $order_id ] ?? wc_get_order( $order_id );
				if ( ! $order instanceof \WC_Order ) {
					$this->finish_pending_order();
					continue;
				}
				$result   = $this->sync_order( $order, $settings );
				$checked += (int) $result['checked'];
				if ( $this->paused ) {
					break;
				}
				if ( $result['global_error'] instanceof Napi_Error ) {
					$this->pending['had_error'] = true;
					$this->save_pending();
					$this->record_global_error( $result['global_error'] );
					return $result['global_error'];
				}
				$this->finish_pending_order();
				++$processed;
			}

			if ( $this->lock_lost ) {
				return $this->lost_lock_error();
			}
			if ( ! empty( $this->pending['orders'] ) ) {
				Tracking_Scheduler::schedule_continuation();
			}
			if ( $checked > 0 ) {
				$this->record_success();
			}
			return array(
				'orders'    => $processed,
				'shipments' => $checked,
				'skipped'   => false,
				'pending'   => ! empty( $this->pending['orders'] ),
			);
		} finally {
			$this->release_lock( $lock_token );
			$this->lock_token = '';
			$this->deadline   = 0;
			$this->pending    = array();
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
		$had_error    = ! empty( $this->pending['had_error'] );
		$mapping_work = false;
		foreach ( Order::get_shipments( $order ) as $shipment ) {
			$snapshot        = $this->guard->snapshot( $order );
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

			$eligible     = (bool) apply_filters( 'balikovna_wc_tracking_shipment_eligible', true, $shipment, $order, $settings );
			$progress_key = $item->get_id() . ':' . $tracking_number;
			if ( $eligible && empty( $this->pending['done'][ $progress_key ] ) && Tracking_Settings::should_poll( $stored_code, $settings ) ) {
				if ( ! $this->can_continue( true ) ) {
					return array(
						'checked'      => $checked,
						'global_error' => null,
					);
				}
				++$this->requests;
				$attempted_at = $this->now();
				$result       = $this->client->status_info( $tracking_number );
				if ( ! $this->refresh_lock() ) {
					$this->lock_lost = true;
					$this->paused    = true;
					return array(
						'checked'      => $checked,
						'global_error' => null,
					);
				}
				$old_code  = $stored_code;
				$old_label = (string) $item->get_meta( Order::META_STATUS_LABEL, true );
				$known     = $this->dictionary->get();
				$saved     = $this->guard->run(
					$order,
					function () use ( $item, $result, $known, $tracking_number, $attempted_at ) {
						$item->update_meta_data( Order::META_STATUS_ATTEMPTED_AT, $attempted_at );
						$item->update_meta_data( Order::META_STATUS_TRACKING_NUMBER, $tracking_number );
						if ( $result instanceof Shipment_Status ) {
							$label = $known[ $result->get_code() ]['name'] ?? $result->get_label();
							$item->update_meta_data( Order::META_STATUS_CODE, $result->get_code() );
							$item->update_meta_data( Order::META_STATUS_LABEL, $label );
							$item->update_meta_data( Order::META_STATUS_EVENT_AT, $result->get_event_at() );
							$item->update_meta_data( Order::META_STATUS_CHECKED_AT, $this->now() );
						}
						return (bool) $item->save();
					},
					$snapshot
				);
				if ( ! $saved ) {
					$had_error = true;
					$this->logger->api_error( $this->write_conflict(), $order->get_id(), $item->get_id() );
					$this->checkpoint_shipment( $progress_key, true );
					continue;
				}
				if ( $result instanceof Napi_Error ) {
					$this->logger->api_error( $result, $order->get_id(), $item->get_id() );
					if ( $result->is_global() ) {
						return array(
							'checked'      => $checked,
							'global_error' => $result,
						);
					}
					$had_error = true;
					$this->checkpoint_shipment( $progress_key, true );
					continue;
				}

				++$checked;
				$this->dictionary->remember( $result );
				$this->checkpoint_shipment( $progress_key, false );

				if ( $old_code !== $result->get_code() ) {
					do_action( 'balikovna_wc_shipment_status_changed', $order, $item, $result, $old_code, $old_label );
				}
				$stored_code = $result->get_code();
			}

			if ( $eligible && ! empty( $settings['auto_order_status'] ) && '' !== $stored_code && $this->orders->needs_mapping_evaluation( $item, $stored_code, $settings ) ) {
				$mapping_work = true;
			}
		}

		if ( ! $this->can_continue() ) {
			return array(
				'checked'      => $checked,
				'global_error' => null,
			);
		}
		if ( ! $had_error && $mapping_work && ! empty( $settings['auto_order_status'] ) ) {
			$shipments = Order::get_shipments( $order );
			$mapped    = $this->mapper->apply( $order, $shipments, $settings );
			if ( false !== $mapped ) {
				$mapped = $this->guard->run(
					$order,
					function () use ( $order, $shipments, $settings ) {
						$this->mark_mapping_evaluated( $order, $shipments, $settings );
						return true;
					}
				);
			}
			if ( false === $mapped ) {
				$this->logger->api_error( $this->write_conflict(), $order->get_id() );
			}
		}

		return array(
			'checked'      => $checked,
			'global_error' => null,
		);
	}

	private function write_conflict() {
		return new Napi_Error(
			'order_write_deferred',
			__( 'Zápis byl odložen: objednávka nebo zásilka se změnila, případně nelze získat databázový zámek InnoDB.', 'balikovna-wc' ),
			0,
			false,
			true
		);
	}

	private function can_continue( $request = false ) {
		if ( ! $this->deadline ) {
			return true;
		}
		if ( ! $this->refresh_lock() ) {
			$this->lock_lost = true;
		}
		$this->paused = $this->lock_lost || $this->now() + ( $request ? self::REQUEST_RESERVE : 0 ) >= $this->deadline
			|| ( $request && $this->requests >= self::MAX_REQUESTS );
		return ! $this->paused;
	}

	private function save_pending() {
		if ( ! $this->refresh_lock() ) {
			$this->lock_lost = true;
			$this->paused    = true;
			return;
		}
		update_option( self::PENDING_OPTION, $this->pending, false );
	}

	private function checkpoint_shipment( $key, $error ) {
		if ( ! $this->deadline ) {
			return;
		}
		$this->pending['done'][ $key ] = true;
		$this->pending['had_error']    = ! empty( $this->pending['had_error'] ) || $error;
		$this->save_pending();
	}

	private function finish_pending_order() {
		array_shift( $this->pending['orders'] );
		$this->pending['done']      = array();
		$this->pending['had_error'] = false;
		$this->save_pending();
	}

	private function lost_lock_error() {
		return new Napi_Error( 'synchronization_lock_lost', __( 'Synchronizace stavu zásilek již probíhá.', 'balikovna-wc' ), 0, false, true );
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
		return Option_Lock::acquire( self::LOCK_OPTION, $this->now(), self::LOCK_TTL );
	}

	private function refresh_lock() {
		if ( '' === $this->lock_token ) {
			return true;
		}
		return Option_Lock::refresh( self::LOCK_OPTION, $this->lock_token, $this->now(), self::LOCK_TTL );
	}

	private function release_lock( $token ) {
		Option_Lock::release( self::LOCK_OPTION, $token );
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
