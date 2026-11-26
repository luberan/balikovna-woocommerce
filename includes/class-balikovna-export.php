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

	/**
	 * Unicode code points of Windows-1250 bytes 0x80-0xFF, four hex digits each;
	 * 0000 marks an unassigned byte.
	 */
	const CP1250_HIGH = '20AC0000201A0000201E202620202021'
		. '0000203001602039015A0164017D0179'
		. '000020182019201C201D202220132014'
		. '000021220161203A015B0165017E017A'
		. '00A002C702D8014100A4010400A600A7'
		. '00A800A9015E00AB00AC00AD00AE017B'
		. '00B000B102DB014200B400B500B600B7'
		. '00B80105015F00BB013D02DD013E017C'
		. '015400C100C2010200C40139010600C7'
		. '010C00C9011800CB011A00CD00CE010E'
		. '01100143014700D300D4015000D600D7'
		. '0158016E00DA017000DC00DD016200DF'
		. '015500E100E2010300E4013A010700E7'
		. '010D00E9011900EB011B00ED00EE010F'
		. '01110144014800F300F4015100F600F7'
		. '0159016F00FA017100FC00FD016302D9';

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
		$cod_total      = $is_cod ? round( (float) $order->get_total() - (float) $order->get_total_refunded() ) : 0.0;
		$variable       = $this->variable_symbol( $order );
		$default_subj   = (string) apply_filters( 'balikovna_wc_default_subject', 'F' );
		$address_type   = $this->recipient_address_type( $order );
		$company        = $order->{ 'get_' . $address_type . '_company' }();
		$last_name      = $order->{ 'get_' . $address_type . '_last_name' }();
		$first_name     = $order->{ 'get_' . $address_type . '_first_name' }();
		$recipient_a    = $company ? $company : $last_name;
		$recipient_b    = $company ? '' : $first_name;
		$subject        = $company ? 'P' : $default_subj;
		$phone          = Services::normalize_recipient_phone( $order->get_billing_phone() );
		$single_package = 1 === count( $order->get_shipping_methods() );
		$signature      = Order::contents_signature( $order );
		$rows           = array();
		if ( '' === trim( $recipient_a ) ) {
			return $this->order_error( $order, __( 'nemá vyplněného příjemce nebo firmu.', 'balikovna-wc' ) );
		}
		if ( $is_cod && $cod_total <= 0 ) {
			return $this->order_error( $order, __( 'nemá po odečtení refundací kladnou částku dobírky.', 'balikovna-wc' ) );
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
				: $order->{ 'get_' . $address_type . '_country' }();
			if ( ! empty( $service['countries'] ) && ! in_array( strtoupper( $country ), $service['countries'], true ) ) {
				return $this->order_error( $order, __( 'má nepodporovanou zemi doručení.', 'balikovna-wc' ) );
			}

			list( $street_col, $zip_col, $city_col ) = $this->destination_columns( $order, $point, $service_code );
			if ( '' === trim( $street_col ) || '' === trim( $zip_col ) || ( 'NB' !== $service_code && '' === trim( $city_col ) ) ) {
				return $this->order_error( $order, __( 'nemá úplnou adresu příjemce.', 'balikovna-wc' ) );
			}

			$stored_signature = (string) $shipment['item']->get_meta( Order::META_CONTENTS_SIGNATURE, true );
			$snapshot_current = '' === $stored_signature || hash_equals( $stored_signature, $signature );
			$weight           = $snapshot_current && '' !== $shipment['weightKg']
				? $shipment['weightKg']
				: ( $single_package ? $this->calc_weight( $order, 6 ) : '' );
			if ( ! is_numeric( $weight ) || ! is_finite( (float) $weight ) || (float) $weight <= 0 ) {
				return $this->order_error( $order, __( 'nemá spolehlivě určenou hmotnost každé zásilky.', 'balikovna-wc' ) );
			}
			if ( $shipment['maxWeightKg'] > 0 && (float) $weight > $shipment['maxWeightKg'] ) {
				return $this->order_error( $order, __( 'překračuje hmotnostní limit zvolené služby nebo uložený smluvní limit zásilky.', 'balikovna-wc' ) );
			}
			$weight = wc_format_decimal( $weight, 2 );
			if ( (float) $weight <= 0 ) {
				return $this->order_error( $order, __( 'nemá spolehlivě určenou hmotnost každé zásilky.', 'balikovna-wc' ) );
			}

			$contents_value = $snapshot_current && '' !== $shipment['contentsValue']
				? wc_format_decimal( $shipment['contentsValue'], 2 )
				: ( $single_package ? $this->calc_contents_value( $order ) : '' );
			if ( '' === $contents_value ) {
				return $this->order_error( $order, __( 'nemá uloženou hodnotu obsahu každé zásilky.', 'balikovna-wc' ) );
			}

			$cod_amount = $is_cod && 0 === $shipment_index
				? wc_format_decimal( $cod_total, 0 )
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
				$variable,                      // J
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
	 * Variable symbol for column J: at most 10 digits.
	 *
	 * Order numbers changed by other plugins may contain letters or be longer,
	 * so the numeric order ID is used instead.
	 *
	 * @return string
	 */
	protected function variable_symbol( \WC_Order $order ) {
		$number = (string) $order->get_order_number();
		return preg_match( '/^\d{1,10}$/D', $number ) ? $number : (string) absint( $order->get_id() );
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
			// Stored points always carry every key, so an empty value has to fall back as well.
			$name = (string) ( $point['name'] ?? '' );
			return array(
				! empty( $point['street'] ) ? $point['street'] : $name,
				! empty( $point['zip'] ) ? $point['zip'] : preg_replace( '/^P/', '', $point['id'] ?? '' ),
				! empty( $point['city'] ) ? $point['city'] : $name,
			);
		}

		$address_type = $this->recipient_address_type( $order );
		$street1      = $order->{ 'get_' . $address_type . '_address_1' }();
		$street2      = $order->{ 'get_' . $address_type . '_address_2' }();
		return array(
			trim( $street1 . ' ' . $street2 ),
			$order->{ 'get_' . $address_type . '_postcode' }(),
			$order->{ 'get_' . $address_type . '_city' }(),
		);
	}

	private function recipient_address_type( \WC_Order $order ) {
		foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'city' ) as $field ) {
			if ( '' !== trim( (string) $order->{ 'get_shipping_' . $field }() ) ) {
				return 'shipping';
			}
		}
		return 'billing';
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
		if ( isset( $row[10] ) && preg_match( '/^\+[1-9][0-9]{7,14}$/D', (string) $row[10] ) ) {
			$row[10] = '00' . substr( (string) $row[10], 1 );
		}
		$converted = array_map(
			function ( $v ) {
				return $this->escape_csv_value( $this->to_cp1250( (string) $v ) );
			},
			$row
		);
		// PHP 8.4+ deprecuje spoléhání na výchozí hodnotu parametru $escape.
		// Prázdný řetězec navíc vypne proprietární "\\" escapování → RFC 4180.
		fputcsv( $handle, $converted, ';', '"', '' );
	}

	/**
	 * Convert UTF-8 text to Windows-1250, the encoding declared for the CSV.
	 *
	 * A failed conversion must never pass the UTF-8 bytes through: Podání Online
	 * would import them as broken diacritics without reporting an error.
	 *
	 * @param string $value UTF-8 text.
	 * @return string
	 */
	protected function to_cp1250( $value ) {
		$value = (string) $value;
		if ( ! preg_match( '/[^\x00-\x7F]/', $value ) ) {
			return $value;
		}
		$converted = $this->iconv_cp1250( $value );
		return false === $converted ? $this->cp1250_fallback( $value ) : $converted;
	}

	/**
	 * @param string $value UTF-8 text.
	 * @return string|false
	 */
	protected function iconv_cp1250( $value ) {
		if ( ! function_exists( 'iconv' ) ) {
			return false;
		}
		// musl libc rejects the //TRANSLIT and //IGNORE suffixes with a PHP notice.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Restored in the finally block.
		set_error_handler(
			static function () {
				return true;
			}
		);
		try {
			foreach ( array( 'Windows-1250//TRANSLIT//IGNORE', 'Windows-1250' ) as $target ) {
				$converted = iconv( 'UTF-8', $target, $value );
				if ( false !== $converted ) {
					return $converted;
				}
			}
		} finally {
			restore_error_handler();
		}
		return false;
	}

	/**
	 * Convert without iconv: mapped characters keep their Windows-1250 byte,
	 * the rest lose their accents or become a question mark.
	 *
	 * @param string $value UTF-8 text.
	 * @return string
	 */
	protected function cp1250_fallback( $value ) {
		static $map = null;
		if ( null === $map ) {
			$map = array();
			foreach ( str_split( self::CP1250_HIGH, 4 ) as $index => $hex ) {
				$code_point = hexdec( $hex );
				if ( $code_point ) {
					$map[ $code_point ] = chr( 0x80 + $index );
				}
			}
		}

		$converted = preg_replace_callback(
			'/[^\x00-\x7F]/u',
			static function ( $found ) use ( $map ) {
				$code_point = self::code_point( $found[0] );
				if ( isset( $map[ $code_point ] ) ) {
					return $map[ $code_point ];
				}
				$ascii = function_exists( 'remove_accents' ) ? (string) remove_accents( $found[0] ) : '';
				return '' !== $ascii && ! preg_match( '/[^\x00-\x7F]/', $ascii ) ? $ascii : '?';
			},
			(string) $value
		);
		// Invalid UTF-8 makes the pattern fail.
		return is_string( $converted ) ? $converted : (string) preg_replace( '/[^\x00-\x7F]/', '?', (string) $value );
	}

	/**
	 * @param string $character One UTF-8 encoded character.
	 * @return int Unicode code point.
	 */
	private static function code_point( $character ) {
		$bytes = array_values( (array) unpack( 'C*', $character ) );
		switch ( count( $bytes ) ) {
			case 2:
				return ( ( $bytes[0] & 0x1F ) << 6 ) | ( $bytes[1] & 0x3F );
			case 3:
				return ( ( $bytes[0] & 0x0F ) << 12 ) | ( ( $bytes[1] & 0x3F ) << 6 ) | ( $bytes[2] & 0x3F );
			case 4:
				return ( ( $bytes[0] & 0x07 ) << 18 ) | ( ( $bytes[1] & 0x3F ) << 12 ) | ( ( $bytes[2] & 0x3F ) << 6 ) | ( $bytes[3] & 0x3F );
		}
		return (int) ( $bytes[0] ?? 0 );
	}

	protected function calc_weight( \WC_Order $order, $decimals = 2 ) {
		$weight = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( 'no' === $item->get_meta( Order::META_REQUIRES_SHIPPING, true )
				|| ( $product && is_callable( array( $product, 'needs_shipping' ) ) && ! $product->needs_shipping() ) ) {
				continue;
			}
			$snapshot    = $item->get_meta( Order::META_UNIT_WEIGHT, true );
			$unit_weight = '' !== (string) $snapshot
				? (float) $snapshot
				: ( $product && is_callable( array( $product, 'get_weight' ) ) ? wc_get_weight( (float) $product->get_weight(), 'kg' ) : 0.0 );
			if ( $unit_weight <= 0 || ! is_finite( $unit_weight ) ) {
				return '';
			}
			$weight += $unit_weight * (float) $item->get_quantity();
		}
		return $weight > 0 && is_finite( $weight ) ? wc_format_decimal( $weight, $decimals ) : '';
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
