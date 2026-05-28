<?php
/**
 * Konfigurace dostupných služeb České pošty.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Services {

	/**
	 * Vrátí seznam podporovaných služeb.
	 *
	 * Klíče slouží jako WooCommerce shipping-method ID (`balikovna`, `cp_do_ruky`, `cp_na_postu`).
	 * `pickup` určuje, zda si zákazník musí vybrat výdejní místo (a jaký typ – BALIKOVNY / POSTY).
	 * `service_code` = kód služby pro CSV Podání Online (orientačně; finální upřesnit dle aktuální šablony PO).
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		return apply_filters(
			'balikovna_wc_services',
			array(
				'balikovna'   => array(
					'label'        => __( 'Balíkovna', 'balikovna-wc' ),
					'description'  => __( 'Doručení na výdejní místo Balíkovna (Česká pošta).', 'balikovna-wc' ),
					'pickup'       => 'BALIKOVNY',
					'service_code' => 'NP',
					'logo'         => 'logo-balikovna.svg',
				),
				'cp_do_ruky'  => array(
					'label'        => __( 'Balík Do ruky', 'balikovna-wc' ),
					'description'  => __( 'Doručení balíku na adresu zákazníka (Česká pošta).', 'balikovna-wc' ),
					'pickup'       => false,
					'service_code' => 'DR',
					'logo'         => 'logo-cp.svg',
				),
				'cp_na_postu' => array(
					'label'        => __( 'Balík Na poštu', 'balikovna-wc' ),
					'description'  => __( 'Doručení na vybranou pobočku České pošty.', 'balikovna-wc' ),
					'pickup'       => 'POSTY',
					'service_code' => 'NB',
					'logo'         => 'logo-cp.svg',
				),
			)
		);
	}

	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Vrátí všechny shipping-method ID, které vyžadují výběr výdejního místa.
	 *
	 * @return string[]
	 */
	public static function pickup_ids() {
		$ids = array();
		foreach ( self::all() as $id => $cfg ) {
			if ( ! empty( $cfg['pickup'] ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}
}
