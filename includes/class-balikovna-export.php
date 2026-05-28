<?php
/**
 * CSV export pro Podání Online (Česká pošta).
 *
 * Formát vychází ze struktury importního CSV pro Podání Online (oddělovač středník,
 * kódování Windows-1250). Hlavičky lze v praxi přizpůsobit dle aktuální šablony PO -
 * proto je výstup filtrovatelný přes `balikovna_wc_export_row`.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Export {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		// Bulk action in orders list (HPOS).
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_bulk' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'handle_bulk_hpos' ), 10, 3 );

		// Legacy.
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_bulk' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'handle_bulk_hpos' ), 10, 3 );
	}

	public function register_bulk( $actions ) {
		$actions['balikovna_export'] = __( 'Export Balíkovna (CSV Podání Online)', 'balikovna-wc' );
		return $actions;
	}

	public function handle_bulk_hpos( $redirect, $action, $ids ) {
		if ( 'balikovna_export' !== $action ) {
			return $redirect;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect;
		}
		$this->stream_csv( array_map( 'absint', (array) $ids ) );
		exit;
	}

	protected function stream_csv( array $order_ids ) {
		$filename = 'balikovna-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=windows-1250' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );

		$headers = apply_filters(
			'balikovna_wc_export_headers',
			array(
				'Příjmení/Firma',
				'Jméno',
				'Ulice',
				'Č.p.',
				'Obec',
				'PSČ',
				'Stát',
				'Telefon',
				'E-mail',
				'Hmotnost (kg)',
				'Cena dobírky',
				'Měna',
				'Reference odesílatele',
				'Kód služby',
				'Kód výdejního místa',
				'Název výdejního místa',
				'Poznámka',
			)
		);

		$this->fputcsv_cp1250( $out, $headers );

		foreach ( $order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}

			// Zjisti, která ČP služba je u objednávky použita.
			$service_id = (string) $order->get_meta( '_balikovna_service' );
			if ( ! $service_id ) {
				// Fallback: zkus odvodit z shipping_method.
				foreach ( $order->get_shipping_methods() as $m ) {
					if ( Services::get( $m->get_method_id() ) ) {
						$service_id = $m->get_method_id();
						break;
					}
				}
			}
			$service = $service_id ? Services::get( $service_id ) : null;
			if ( ! $service ) {
				continue;
			}

			$point = Order::get_point( $order );
			// U služeb s pickup vyžaduj zvolené místo, u Do ruky může být prázdné.
			if ( ! empty( $service['pickup'] ) && empty( $point['id'] ) ) {
				continue;
			}

			$is_cod = in_array( $order->get_payment_method(), apply_filters( 'balikovna_wc_cod_methods', array( 'cod' ) ), true );

			$row = array(
				$order->get_shipping_last_name() ?: $order->get_billing_last_name(),
				$order->get_shipping_first_name() ?: $order->get_billing_first_name(),
				$order->get_shipping_address_1() ?: $order->get_billing_address_1(),
				$order->get_shipping_address_2() ?: $order->get_billing_address_2(),
				$order->get_shipping_city() ?: $order->get_billing_city(),
				$order->get_shipping_postcode() ?: $order->get_billing_postcode(),
				$order->get_shipping_country() ?: $order->get_billing_country() ?: 'CZ',
				$order->get_billing_phone(),
				$order->get_billing_email(),
				$this->calc_weight( $order ),
				$is_cod ? wc_format_decimal( $order->get_total(), 2 ) : '',
				$order->get_currency(),
				$order->get_order_number(),
				$service['service_code'] ?? '',
				$point['id'] ?? '',
				$point['name'] ?? '',
				$order->get_customer_note(),
			);

			$row = apply_filters( 'balikovna_wc_export_row', $row, $order, $point, $service_id );

			$this->fputcsv_cp1250( $out, $row );
		}

		fclose( $out );
	}

	protected function fputcsv_cp1250( $handle, array $row ) {
		$converted = array_map(
			function ( $v ) {
				$v = (string) $v;
				if ( function_exists( 'iconv' ) ) {
					$c = @iconv( 'UTF-8', 'Windows-1250//TRANSLIT//IGNORE', $v );
					if ( false !== $c ) {
						return $c;
					}
				}
				return $v;
			},
			$row
		);
		fputcsv( $handle, $converted, ';', '"' );
	}

	protected function calc_weight( \WC_Order $order ) {
		$w = 0.0;
		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$product = $item->get_product();
			if ( $product && $product->get_weight() ) {
				$w += (float) $product->get_weight() * (int) $item->get_quantity();
			}
		}
		return wc_format_decimal( wc_get_weight( $w, 'kg' ), 2 );
	}
}
