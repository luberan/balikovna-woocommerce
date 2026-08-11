<?php
/**
 * Společná shipping metoda pro služby České pošty (Balíkovna, Balík Do ruky, Balík Na poštu).
 *
 * Konkrétní služba je určena podtřídou (statické pole `$service_id`).
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

abstract class Shipping_Method_Base extends \WC_Shipping_Method {

	/** @var string Service ID musí nastavit podtřída (např. `balikovna`, `cp_do_ruky`, `cp_na_postu`). */
	protected static $service_id = '';

	/** @var array Cache konfigurace služby. */
	protected $service = array();

	/** @var string Fixní cena dopravy (z instance settings). */
	protected $cost = '';

	/** @var string Typ ceny: `flat` nebo `weight`. */
	protected $cost_type = 'flat';

	/** @var string Váhová tabulka (řádky `max_kg|cena`). */
	protected $weight_table = '';

	/** @var string Práh košíku pro dopravu zdarma (prázdné = vypnuto). */
	protected $free_shipping_min = '';

	/** @var string Kódy služeb ČP pro Podání Online (sloupec I v CSV, např. "7+45+S+41"). */
	protected $service_codes = '';

	/** @var string Typ zásilky/prefix pro Podání Online. */
	protected $parcel_type = '';

	/** @var string Efektivní smluvní hmotnostní limit. */
	protected $max_weight_kg = '';

	public function __construct( $instance_id = 0 ) {
		$this->id          = static::$service_id;
		$this->instance_id = absint( $instance_id );
		$this->service     = (array) Services::get( static::$service_id );

		$this->method_title       = $this->service['label'] ?? static::$service_id;
		$this->method_description = $this->service['description'] ?? '';
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);

		$this->init();
	}

	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->title             = $this->get_option( 'title', $this->method_title );
		$this->tax_status        = $this->get_option( 'tax_status', 'taxable' );
		$this->cost              = $this->get_option( 'cost', '79' );
		$this->cost_type         = $this->get_option( 'cost_type', 'flat' );
		$this->weight_table      = $this->get_option( 'weight_table', "5|79\n10|119\n15|159" );
		$this->free_shipping_min = $this->get_option( 'free_shipping_min', '' );
		$this->service_codes     = $this->get_option( 'service_codes', '' );
		$this->parcel_type       = $this->get_option( 'parcel_type', $this->service['service_code'] ?? '' );
		$this->max_weight_kg     = $this->get_option( 'max_weight_kg', $this->service['max_weight_kg'] ?? '' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title'             => array(
				'title'       => __( 'Název', 'balikovna-wc' ),
				'type'        => 'text',
				'description' => __( 'Název zobrazený zákazníkovi v košíku.', 'balikovna-wc' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'tax_status'        => array(
				'title'   => __( 'DPH', 'balikovna-wc' ),
				'type'    => 'select',
				'options' => array(
					'taxable' => __( 'Zdaněno', 'balikovna-wc' ),
					'none'    => __( 'Nezdaněno', 'balikovna-wc' ),
				),
				'default' => 'taxable',
			),
			'cost_type'         => array(
				'title'   => __( 'Typ ceny', 'balikovna-wc' ),
				'type'    => 'select',
				'options' => array(
					'flat'   => __( 'Fixní cena', 'balikovna-wc' ),
					'weight' => __( 'Podle hmotnosti (tabulka)', 'balikovna-wc' ),
				),
				'default' => 'flat',
			),
			'cost'              => array(
				'title'       => __( 'Fixní cena', 'balikovna-wc' ),
				'type'        => 'price',
				'description' => __( 'Použije se při typu „Fixní cena".', 'balikovna-wc' ),
				'default'     => '79',
				'desc_tip'    => true,
			),
			'weight_table'      => array(
				'title'       => __( 'Tabulka hmotností', 'balikovna-wc' ),
				'type'        => 'textarea',
				'description' => __( 'Jeden řádek = "max_hmotnost_kg|cena". Příklad: 5|79 znamená do 5 kg cena 79. Nad nejvyšším limitem se doprava nenabídne.', 'balikovna-wc' ),
				'default'     => "5|79\n10|119\n15|159",
				'css'         => 'min-height:120px;',
				'desc_tip'    => true,
			),
			'free_shipping_min' => array(
				'title'       => __( 'Zdarma od částky', 'balikovna-wc' ),
				'type'        => 'price',
				'description' => __( 'Mezisoučet košíku (bez dopravy), od kterého je doprava zdarma. Prázdné = vypnuto.', 'balikovna-wc' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'service_codes'     => array(
				'title'       => __( 'Kódy služeb ČP (CSV sloupec I)', 'balikovna-wc' ),
				'type'        => 'text',
				'description' => __( 'Při exportu pro Podání Online se použije jako hodnota sloupce „Služby“. Formát dle označení služeb ve vaší smlouvě s ČP, např. <code>7+45+S+41</code>. Prázdné = nevyplnit.', 'balikovna-wc' ),
				'default'     => '',
			),
		);

		if ( ! empty( $this->service['service_code_options'] ) ) {
			$options = array();
			foreach ( $this->service['service_code_options'] as $code ) {
				$options[ $code ] = $code;
			}
			$this->instance_form_fields['parcel_type'] = array(
				'title'       => __( 'Typ zásilky ČP', 'balikovna-wc' ),
				'type'        => 'select',
				'description' => __( 'Prefix produktu pro Podání Online. Zvolte DR, DV nebo DE podle smlouvy s Českou poštou.', 'balikovna-wc' ),
				'options'     => $options,
				'default'     => $this->service['service_code'],
				'desc_tip'    => true,
			);
		}

		if ( ! empty( $this->service['contract_max_weight_kg'] ) ) {
			$standard = (string) $this->service['max_weight_kg'];
			$contract = (string) $this->service['contract_max_weight_kg'];

			$this->instance_form_fields['max_weight_kg'] = array(
				'title'       => __( 'Smluvní limit hmotnosti', 'balikovna-wc' ),
				'type'        => 'select',
				'description' => __( 'Limit 50 kg zvolte pouze v případě, že jej máte sjednaný ve smlouvě s Českou poštou.', 'balikovna-wc' ),
				'options'     => array(
					// translators: %s: standard weight limit in kilograms.
					$standard => sprintf( __( 'Standardně %s kg', 'balikovna-wc' ), $standard ),
					// translators: %s: contracted weight limit in kilograms.
					$contract => sprintf( __( 'Smluvně %s kg', 'balikovna-wc' ), $contract ),
				),
				'default'     => $standard,
				'desc_tip'    => true,
			);
		}
	}

	public function calculate_shipping( $package = array() ) {
		$cost = $this->calculate_cost( $package );

		if ( null === $cost ) {
			return;
		}

		$this->add_rate(
			array(
				'id'        => $this->get_rate_id(),
				'label'     => $this->title,
				'cost'      => $cost,
				'package'   => $package,
				'meta_data' => array(
					'balikovna_service_codes' => $this->service_codes,
					'balikovna_parcel_type'   => $this->parcel_type,
				),
			)
		);
	}

	protected function calculate_cost( $package ) {
		$metrics = $this->package_metrics( $package );
		if ( ! $this->package_is_eligible( $package, $metrics ) ) {
			return null;
		}

		if ( '' !== trim( (string) $this->free_shipping_min ) ) {
			$threshold = (float) $this->free_shipping_min;
			$subtotal  = $this->cart_contents_total( $package );
			if ( $threshold > 0 && $subtotal >= $threshold ) {
				return 0.0;
			}
		}

		if ( 'weight' === $this->cost_type ) {
			if ( empty( $metrics['weightComplete'] ) ) {
				return null;
			}
			return $this->resolve_weight_cost( $metrics['weightKg'] );
		}

		return max( 0.0, (float) $this->cost );
	}

	protected function cart_contents_total( $package ) {
		$contents = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : array();
		if ( function_exists( 'WC' ) && WC()->cart && is_callable( array( WC()->cart, 'get_cart' ) ) ) {
			$cart_contents = WC()->cart->get_cart();
			if ( is_array( $cart_contents ) && $cart_contents ) {
				$contents = $cart_contents;
			}
		}

		$subtotal = 0.0;
		foreach ( $contents as $item ) {
			$subtotal += (float) ( $item['line_total'] ?? 0 ) + (float) ( $item['line_tax'] ?? 0 );
		}

		return (float) apply_filters( 'balikovna_wc_free_shipping_subtotal', $subtotal, $package, $this->id );
	}

	protected function package_is_eligible( $package, array $metrics ) {
		$allowed_countries = isset( $this->service['countries'] ) ? (array) $this->service['countries'] : array();
		$country           = isset( $package['destination']['country'] )
			? strtoupper( (string) $package['destination']['country'] )
			: '';
		if ( $allowed_countries && ! in_array( $country, $allowed_countries, true ) ) {
			return false;
		}

		if ( isset( $this->service['max_weight_kg'] ) ) {
			$max_weight = '' !== trim( (string) $this->max_weight_kg )
				? (float) $this->max_weight_kg
				: (float) $this->service['max_weight_kg'];
			if ( empty( $metrics['weightComplete'] ) || $metrics['weightKg'] > $max_weight ) {
				return false;
			}
		}

		if ( isset( $this->service['max_dimensions_cm'] ) ) {
			if ( empty( $metrics['dimensionsComplete'] ) ) {
				return false;
			}
			$limits     = array_map( 'floatval', (array) $this->service['max_dimensions_cm'] );
			$dimensions = array_map( 'floatval', (array) $metrics['dimensionsCm'] );
			rsort( $limits, SORT_NUMERIC );
			rsort( $dimensions, SORT_NUMERIC );
			foreach ( $limits as $index => $limit ) {
				if ( ! isset( $dimensions[ $index ] ) || $dimensions[ $index ] > $limit ) {
					return false;
				}
			}
			if ( $metrics['volumeCm3'] > array_product( $limits ) ) {
				return false;
			}
		}

		if ( isset( $this->service['max_dimensions_sum_cm'] ) && array_sum( $metrics['dimensionsCm'] ) > (float) $this->service['max_dimensions_sum_cm'] ) {
			return false;
		}

		return true;
	}

	protected function package_metrics( $package ) {
		$metrics  = array(
			'weightKg'           => 0.0,
			'weightComplete'     => true,
			'dimensionsCm'       => array( 0.0, 0.0, 0.0 ),
			'dimensionsComplete' => true,
			'volumeCm3'          => 0.0,
		);
		$contents = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : array();
		if ( ! $contents ) {
			$metrics['weightComplete']     = false;
			$metrics['dimensionsComplete'] = false;
		}

		foreach ( $contents as $item ) {
			$product  = isset( $item['data'] ) ? $item['data'] : null;
			$quantity = isset( $item['quantity'] ) ? max( 0, (int) $item['quantity'] ) : 0;
			if ( ! $product || 0 === $quantity ) {
				$metrics['weightComplete']     = false;
				$metrics['dimensionsComplete'] = false;
				continue;
			}

			$weight = is_callable( array( $product, 'get_weight' ) ) ? (string) $product->get_weight() : '';
			if ( '' === $weight || (float) $weight <= 0 ) {
				$metrics['weightComplete'] = false;
			} else {
				$metrics['weightKg'] += wc_get_weight( (float) $weight, 'kg' ) * $quantity;
			}

			$dimensions = array();
			foreach ( array( 'get_length', 'get_width', 'get_height' ) as $getter ) {
				$value = is_callable( array( $product, $getter ) ) ? (string) $product->{$getter}() : '';
				if ( '' === $value || (float) $value <= 0 ) {
					$metrics['dimensionsComplete'] = false;
					$dimensions                    = array();
					break;
				}
				$dimensions[] = wc_get_dimension( (float) $value, 'cm' );
			}
			if ( 3 === count( $dimensions ) ) {
				rsort( $dimensions, SORT_NUMERIC );
				foreach ( $dimensions as $index => $dimension ) {
					$metrics['dimensionsCm'][ $index ] = max( $metrics['dimensionsCm'][ $index ], $dimension );
				}
				$metrics['volumeCm3'] += array_product( $dimensions ) * $quantity;
			}
		}

		$filtered = apply_filters( 'balikovna_wc_package_metrics', $metrics, $package, $this->id );
		return is_array( $filtered ) ? array_merge( $metrics, $filtered ) : $metrics;
	}

	protected function resolve_weight_cost( $weight_kg ) {
		$rows = $this->parse_weight_table( $this->weight_table );
		if ( is_wp_error( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( (float) $weight_kg <= $row['max'] ) {
				return $row['cost'];
			}
		}

		return null;
	}

	/**
	 * Validate and normalize the weight table when instance settings are saved.
	 */
	public function validate_textarea_field( $key, $value ) {
		if ( 'weight_table' !== $key ) {
			return parent::validate_textarea_field( $key, $value );
		}

		$rows = $this->parse_weight_table( $value );
		if ( is_wp_error( $rows ) ) {
			if ( class_exists( '\WC_Admin_Settings' ) ) {
				\WC_Admin_Settings::add_error( $rows->get_error_message() );
			}
			return (string) $this->get_option( 'weight_table', $this->weight_table );
		}

		$normalized = array();
		foreach ( $rows as $row ) {
			$normalized[] = wc_format_decimal( $row['max'], 3 ) . '|' . wc_format_decimal( $row['cost'], wc_get_price_decimals() );
		}
		return implode( "\n", $normalized );
	}

	/**
	 * @param string $table Raw table.
	 * @return array|\WP_Error
	 */
	protected function parse_weight_table( $table ) {
		$lines = preg_split( '/\r?\n/', trim( (string) $table ) );
		$lines = array_values( array_filter( array_map( 'trim', (array) $lines ), 'strlen' ) );
		if ( ! $lines ) {
			return new \WP_Error( 'balikovna_empty_weight_table', __( 'Tabulka hmotností nesmí být prázdná.', 'balikovna-wc' ) );
		}

		$rows = array();
		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( 2 !== count( $parts ) || ! preg_match( '/^\d+(?:[.,]\d+)?$/', $parts[0] ) || ! preg_match( '/^\d+(?:[.,]\d+)?$/', $parts[1] ) ) {
				return new \WP_Error( 'balikovna_invalid_weight_row', __( 'Každý řádek tabulky musí mít formát „kladná hmotnost|nezáporná cena“.', 'balikovna-wc' ) );
			}

			$max  = (float) str_replace( ',', '.', $parts[0] );
			$cost = (float) str_replace( ',', '.', $parts[1] );
			if ( $max <= 0 || isset( $rows[ (string) $max ] ) ) {
				return new \WP_Error( 'balikovna_invalid_weight_limit', __( 'Hmotnostní limity musí být kladné a nesmí se opakovat.', 'balikovna-wc' ) );
			}
			$rows[ (string) $max ] = array(
				'max'  => $max,
				'cost' => $cost,
			);
		}

		usort(
			$rows,
			function ( $left, $right ) {
				return $left['max'] <=> $right['max'];
			}
		);
		return $rows;
	}
}
