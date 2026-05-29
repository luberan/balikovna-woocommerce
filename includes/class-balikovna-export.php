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

		// Hlavičky odpovídají importní šabloně Podání Online (sloupce A-O)
		// dle podpory ČP (podporaobchodu@cpost.cz, 2026-05).
		$headers = apply_filters(
			'balikovna_wc_export_headers',
			array(
				'Příjmení/Firma',     // A
				'Jméno',              // B
				'Ulice + č.p.',       // C  (pro NB: literál "Balíkova")
				'PSČ',                // D  (pro NB: ID balíkovny)
				'Město',              // E  (pro NB: prázdné)
				'Hmotnost (kg)',      // F
				'Udaná cena',         // G
				'Cena dobírky',       // H
				'Služby',             // I  (např. "7+45+S+41")
				'Variabilní symbol',  // J
				'Telefon',            // K
				'E-mail',             // L
				'Typ zásilky',        // M  (NB / DR / NP)
				'Subjekt',            // N  (F = fyzická, P = právnická)
				'Počet VK',           // O  (počet balíků)
			)
		);

		$this->fputcsv_cp1250( $out, $headers );

		$cod_methods   = (array) apply_filters( 'balikovna_wc_cod_methods', array( 'cod' ) );
		$default_subj  = (string) apply_filters( 'balikovna_wc_default_subject', 'F' );

		foreach ( $order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}

			// Zjisti, která ČP služba je u objednávky použita.
			$service_id    = (string) $order->get_meta( '_balikovna_service' );
			$service_codes = '';
			foreach ( $order->get_shipping_methods() as $m ) {
				if ( ! $service_id && Services::get( $m->get_method_id() ) ) {
					$service_id = $m->get_method_id();
				}
				$codes = $m->get_meta( 'balikovna_service_codes' );
				if ( $codes ) {
					$service_codes = (string) $codes;
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

			$service_code = (string) ( $service['service_code'] ?? '' );
			$is_cod       = in_array( $order->get_payment_method(), $cod_methods, true );

			// Adresa: pro Balíkovnu (NB) speciální formát dle ČP,
			// pro ostatní služby standardní adresa zákazníka.
			if ( 'NB' === $service_code ) {
				$street_col = 'Balíkova';
				$zip_col    = $point['id'] ?? '';
				$city_col   = '';
			} else {
				$street1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
				$street2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
				$street_col = trim( $street1 . ' ' . $street2 );
				$zip_col    = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
				$city_col   = $order->get_shipping_city() ?: $order->get_billing_city();
			}

			$row = array(
				$order->get_shipping_last_name() ?: $order->get_billing_last_name(), // A
				$order->get_shipping_first_name() ?: $order->get_billing_first_name(), // B
				$street_col,                                                          // C
				$zip_col,                                                             // D
				$city_col,                                                            // E
				$this->calc_weight( $order ),                                         // F
				wc_format_decimal( $order->get_total(), 2 ),                          // G  Udaná cena
				$is_cod ? wc_format_decimal( $order->get_total(), 2 ) : '',           // H  Dobírka
				$service_codes,                                                       // I  Služby (z nastavení shipping metody)
				$order->get_order_number(),                                           // J  Variabilní symbol
				$order->get_billing_phone(),                                          // K
				$order->get_billing_email(),                                          // L
				$service_code,                                                        // M
				$default_subj,                                                        // N
				1,                                                                    // O  Počet VK
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
