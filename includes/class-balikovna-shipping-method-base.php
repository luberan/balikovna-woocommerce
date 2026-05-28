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

	public function __construct( $instance_id = 0 ) {
		$this->id           = static::$service_id;
		$this->instance_id  = absint( $instance_id );
		$this->service      = (array) Services::get( static::$service_id );

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
				'description' => __( 'Jeden řádek = "max_hmotnost_kg|cena". Příklad: 5|79 znamená do 5 kg cena 79.', 'balikovna-wc' ),
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
		);
	}

	public function calculate_shipping( $package = array() ) {
		$cost = $this->calculate_cost( $package );

		if ( null === $cost ) {
			return;
		}

		$this->add_rate(
			array(
				'id'      => $this->get_rate_id(),
				'label'   => $this->title,
				'cost'    => $cost,
				'package' => $package,
			)
		);
	}

	protected function calculate_cost( $package ) {
		if ( '' !== trim( (string) $this->free_shipping_min ) ) {
			$threshold = (float) $this->free_shipping_min;
			$subtotal  = 0.0;
			foreach ( $package['contents'] as $item ) {
				$subtotal += (float) $item['line_total'] + (float) $item['line_tax'];
			}
			if ( $subtotal >= $threshold ) {
				return 0.0;
			}
		}

		if ( 'weight' === $this->cost_type ) {
			$weight = 0.0;
			foreach ( $package['contents'] as $item ) {
				if ( $item['data'] && $item['data']->get_weight() ) {
					$weight += (float) $item['data']->get_weight() * (int) $item['quantity'];
				}
			}
			return $this->resolve_weight_cost( wc_get_weight( $weight, 'kg' ) );
		}

		return (float) $this->cost;
	}

	protected function resolve_weight_cost( $weight_kg ) {
		$rows     = array_filter( array_map( 'trim', explode( "\n", (string) $this->weight_table ) ) );
		$best     = null;
		$best_max = INF;
		$max_cost = 0.0;
		foreach ( $rows as $row ) {
			$parts = array_map( 'trim', explode( '|', $row ) );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			$max  = (float) str_replace( ',', '.', $parts[0] );
			$cost = (float) str_replace( ',', '.', $parts[1] );
			$max_cost = max( $max_cost, $cost );
			if ( $weight_kg <= $max && $max < $best_max ) {
				$best     = $cost;
				$best_max = $max;
			}
		}
		return (float) ( null !== $best ? $best : $max_cost );
	}
}
