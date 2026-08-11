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

		$cod_methods  = (array) apply_filters( 'balikovna_wc_cod_methods', array( 'cod' ) );
		$default_subj = (string) apply_filters( 'balikovna_wc_default_subject', 'F' );

		foreach ( $order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}

			$is_cod         = in_array( $order->get_payment_method(), $cod_methods, true );
			$shipment_index = 0;
			foreach ( Order::get_shipments( $order ) as $shipment ) {
				$service_id   = $shipment['serviceId'];
				$service      = $shipment['service'];
				$point        = $shipment['point'];
				$service_code = (string) ( $service['service_code'] ?? '' );
				if ( ! empty( $service['pickup'] ) && empty( $point['id'] ) ) {
					continue;
				}

				list( $street_col, $zip_col, $city_col ) = $this->destination_columns( $order, $point, $service_code );

				$weight     = '' !== $shipment['weightKg']
					? wc_format_decimal( $shipment['weightKg'], 2 )
					: $this->calc_weight( $order );
				$last_name  = $order->get_shipping_last_name();
				$first_name = $order->get_shipping_first_name();
				$row        = array(
					$last_name ? $last_name : $order->get_billing_last_name(),             // A
					$first_name ? $first_name : $order->get_billing_first_name(),          // B
					$street_col,                                                          // C
					$zip_col,                                                             // D
					$city_col,                                                            // E
					$weight,                                                              // F
					wc_format_decimal( $order->get_total(), 2 ),                          // G  Udaná cena
					$is_cod && 0 === $shipment_index ? wc_format_decimal( $order->get_total(), 2 ) : '', // H  Dobírka jen na první zásilce.
					$shipment['serviceCodes'],                                            // I  Služby konkrétní shipping metody.
					$order->get_order_number(),                                           // J  Variabilní symbol
					$order->get_billing_phone(),                                          // K
					$order->get_billing_email(),                                          // L
					$service_code,                                                        // M
					$default_subj,                                                        // N
					1,                                                                    // O  Jedna zásilka na shipping item.
				);

				$row = apply_filters( 'balikovna_wc_export_row', $row, $order, $point, $service_id, $shipment['item'] );
				$this->fputcsv_cp1250( $out, $row );
				++$shipment_index;
			}
		}

		fclose( $out );
	}

	/**
	 * Map an order shipment to Podání Online destination columns C-E.
	 *
	 * @return string[]
	 */
	protected function destination_columns( \WC_Order $order, array $point, $service_code ) {
		if ( 'NB' === $service_code ) {
			return array( 'Balíkova', $point['id'] ?? '', '' );
		}
		if ( 'NP' === $service_code ) {
			return array(
				$point['street'] ?? ( $point['name'] ?? '' ),
				! empty( $point['zip'] ) ? $point['zip'] : preg_replace( '/^P/', '', $point['id'] ?? '' ),
				$point['city'] ?? ( $point['name'] ?? '' ),
			);
		}

		$street1  = $order->get_shipping_address_1();
		$street2  = $order->get_shipping_address_2();
		$postcode = $order->get_shipping_postcode();
		$city     = $order->get_shipping_city();
		return array(
			trim( ( $street1 ? $street1 : $order->get_billing_address_1() ) . ' ' . ( $street2 ? $street2 : $order->get_billing_address_2() ) ),
			$postcode ? $postcode : $order->get_billing_postcode(),
			$city ? $city : $order->get_billing_city(),
		);
	}

	/**
	 * Neutralizuje CSV formula injection: hodnoty začínající =, +, -, @, tab, CR nebo LF
	 * mohou být v Excelu/Sheetech interpretovány jako vzorce. Prefixujeme apostrofem.
	 *
	 * @param string $v
	 * @return string
	 */
	protected function escape_csv_value( $v ) {
		if ( '' !== $v && preg_match( '/^[=+\-@\t\r\n]/', $v ) ) {
			return "'" . $v;
		}
		return $v;
	}

	protected function fputcsv_cp1250( $handle, array $row ) {
		$converted = array_map(
			function ( $v ) {
				$v = (string) $v;
				if ( function_exists( 'iconv' ) ) {
					$c = iconv( 'UTF-8', 'Windows-1250//TRANSLIT//IGNORE', $v );
					if ( false !== $c ) {
						$v = $c;
					}
				}
				return $this->escape_csv_value( $v );
			},
			$row
		);
		// PHP 8.4+ deprecuje spoléhání na výchozí hodnotu parametru $escape.
		// Prázdný řetězec navíc vypne proprietární "\\" escapování → RFC 4180.
		fputcsv( $handle, $converted, ';', '"', '' );
	}

	protected function calc_weight( \WC_Order $order ) {
		$w = 0.0;
		foreach ( $order->get_items() as $item ) {
			/** @var \WC_Order_Item_Product $item */
			$snapshot = $item->get_meta( Order::META_UNIT_WEIGHT, true );
			if ( '' !== (string) $snapshot ) {
				$w += (float) $snapshot * (int) $item->get_quantity();
				continue;
			}
			$product = $item->get_product();
			if ( $product && '' !== (string) $product->get_weight() ) {
				$w += wc_get_weight( (float) $product->get_weight(), 'kg' ) * (int) $item->get_quantity();
			}
		}
		return wc_format_decimal( $w, 2 );
	}
}
