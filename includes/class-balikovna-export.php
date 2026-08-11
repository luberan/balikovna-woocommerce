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
		$rows = $this->prepare_rows( $order_ids );
		if ( is_wp_error( $rows ) ) {
			wp_die(
				esc_html( $rows->get_error_message() ),
				esc_html__( 'Export Balíkovna se nepodařil', 'balikovna-wc' ),
				array(
					'response'  => 400,
					'back_link' => true,
				)
			);
		}

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
		foreach ( $rows as $row ) {
			$this->fputcsv_cp1250( $out, $row );
		}

		fclose( $out );
	}

	/**
	 * Build all rows before sending download headers, so invalid orders cannot
	 * produce a partial CSV file.
	 *
	 * @return array|\WP_Error
	 */
	protected function prepare_rows( array $order_ids ) {
		$rows   = array();
		$errors = array();
		foreach ( $order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				// translators: %d: WooCommerce order ID.
				$errors[] = sprintf( __( 'Objednávka #%d neexistuje.', 'balikovna-wc' ), $id );
				continue;
			}
			$order_rows = $this->prepare_order_rows( $order );
			if ( is_wp_error( $order_rows ) ) {
				$errors[] = $order_rows->get_error_message();
				continue;
			}
			$rows = array_merge( $rows, $order_rows );
		}

		if ( $errors ) {
			return new \WP_Error( 'balikovna_export_invalid_orders', implode( ' ', $errors ) );
		}
		if ( ! $rows ) {
			return new \WP_Error(
				'balikovna_export_empty',
				__( 'Vybrané objednávky neobsahují žádnou zásilku České pošty.', 'balikovna-wc' )
			);
		}
		return $rows;
	}

	/**
	 * @return array|\WP_Error
	 */
	protected function prepare_order_rows( \WC_Order $order ) {
		$shipments = Order::get_shipments( $order );
		if ( ! $shipments ) {
			return $this->order_error( $order, __( 'neobsahuje žádnou zásilku České pošty.', 'balikovna-wc' ) );
		}

		$service_ids    = array_column( $shipments, 'serviceId' );
		$contact_errors = Services::recipient_contact_errors(
			$service_ids,
			$order->get_billing_email(),
			$order->get_billing_phone()
		);
		if ( $contact_errors ) {
			return $this->order_error( $order, reset( $contact_errors ) );
		}

		$currency = strtoupper( (string) $order->get_currency() );
		if ( 'CZK' !== $currency ) {
			return $this->order_error( $order, __( 'musí být vedena v měně CZK.', 'balikovna-wc' ) );
		}

		$cod_methods    = (array) apply_filters( 'balikovna_wc_cod_methods', array( 'cod' ) );
		$is_cod         = in_array( $order->get_payment_method(), $cod_methods, true );
		$default_subj   = (string) apply_filters( 'balikovna_wc_default_subject', 'F' );
		$company        = $order->get_shipping_company() ? $order->get_shipping_company() : $order->get_billing_company();
		$last_name      = $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $order->get_billing_last_name();
		$first_name     = $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $order->get_billing_first_name();
		$recipient_a    = $company ? $company : $last_name;
		$recipient_b    = $company ? '' : $first_name;
		$subject        = $company ? 'P' : $default_subj;
		$phone          = Services::normalize_recipient_phone( $order->get_billing_phone() );
		$shipment_count = count( $shipments );
		$rows           = array();
		if ( '' === trim( $recipient_a ) ) {
			return $this->order_error( $order, __( 'nemá vyplněného příjemce nebo firmu.', 'balikovna-wc' ) );
		}

		foreach ( $shipments as $shipment_index => $shipment ) {
			$service_id   = $shipment['serviceId'];
			$service      = $shipment['service'];
			$point        = $shipment['point'];
			$service_code = (string) $shipment['parcelType'];
			if ( '' === $service_code ) {
				return $this->order_error( $order, __( 'nemá nastavený typ zásilky pro Podání Online.', 'balikovna-wc' ) );
			}
			if ( ! empty( $service['pickup'] ) && empty( $point['id'] ) ) {
				return $this->order_error( $order, __( 'nemá u všech zásilek zvolené výdejní místo.', 'balikovna-wc' ) );
			}

			$country = ! empty( $service['pickup'] )
				? ( ! empty( $point['country'] ) ? (string) $point['country'] : 'CZ' )
				: ( $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country() );
			if ( ! empty( $service['countries'] ) && ! in_array( strtoupper( $country ), $service['countries'], true ) ) {
				return $this->order_error( $order, __( 'má nepodporovanou zemi doručení.', 'balikovna-wc' ) );
			}

			list( $street_col, $zip_col, $city_col ) = $this->destination_columns( $order, $point, $service_code );
			if ( '' === trim( $street_col ) || '' === trim( $zip_col ) || ( 'NB' !== $service_code && '' === trim( $city_col ) ) ) {
				return $this->order_error( $order, __( 'nemá úplnou adresu příjemce.', 'balikovna-wc' ) );
			}

			$weight = '' !== $shipment['weightKg']
				? wc_format_decimal( $shipment['weightKg'], 2 )
				: ( 1 === $shipment_count ? $this->calc_weight( $order ) : '' );
			if ( (float) $weight <= 0 ) {
				return $this->order_error( $order, __( 'nemá spolehlivě určenou hmotnost každé zásilky.', 'balikovna-wc' ) );
			}

			$contents_value = '' !== $shipment['contentsValue']
				? wc_format_decimal( $shipment['contentsValue'], 2 )
				: ( 1 === $shipment_count ? $this->calc_contents_value( $order ) : '' );
			if ( '' === $contents_value ) {
				return $this->order_error( $order, __( 'nemá uloženou hodnotu obsahu každé zásilky.', 'balikovna-wc' ) );
			}

			$cod_amount = $is_cod && 0 === $shipment_index
				? wc_format_decimal( round( (float) $order->get_total() ), 0 )
				: '';
			$row        = array(
				$recipient_a,                   // A
				$recipient_b,                   // B
				$street_col,                    // C
				$zip_col,                       // D
				$city_col,                      // E
				$weight,                        // F
				$contents_value,                // G
				$cod_amount,                    // H
				$shipment['serviceCodes'],      // I
				$order->get_order_number(),     // J
				$phone,                         // K
				$order->get_billing_email(),    // L
				$service_code,                  // M
				$subject,                       // N
				1,                              // O
			);
			$rows[]     = apply_filters( 'balikovna_wc_export_row', $row, $order, $point, $service_id, $shipment['item'] );
		}

		return $rows;
	}

	protected function order_error( \WC_Order $order, $message ) {
		return new \WP_Error(
			'balikovna_export_invalid_order',
			sprintf(
				// translators: 1: WooCommerce order number, 2: export validation error.
				__( 'Objednávku %1$s nelze exportovat: %2$s', 'balikovna-wc' ),
				$order->get_order_number(),
				$message
			)
		);
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

	protected function calc_contents_value( \WC_Order $order ) {
		$value = 0.0;
		$found = false;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$value += (float) $item->get_total() + (float) $item->get_total_tax();
			$found  = true;
		}
		return $found ? wc_format_decimal( max( 0.0, $value ), 2 ) : '';
	}
}
