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
	 * `pickup` určuje, zda si zákazník musí vybrat výdejní místo (a jaký typ – BALIKOVNY / POST_OFFICE).
	 * Hodnota se předává jako `type` parametr do widgetu b2c.cpost.cz/locations.
	 * `service_code` = kód služby pro CSV Podání Online (orientačně; finální upřesnit dle aktuální šablony PO).
	 *
	 * @return array<string,array>
	 */
	public static function all() {
		return apply_filters(
			'balikovna_wc_services',
			array(
				'balikovna'           => array(
					'label'             => __( 'Balík do Balíkovny', 'balikovna-wc' ),
					'description'       => __( 'Doručení na výdejní místo Balíkovna.', 'balikovna-wc' ),
					'pickup'            => 'BALIKOVNY',
					'service_code'      => 'NB', // Na Balíkovnu (dle CSV šablony Podání Online).
					'logo'              => 'logo-balikovna.svg',
					'countries'         => array( 'CZ' ),
					'max_weight_kg'     => 15,
					'max_dimensions_cm' => array( 50, 50, 50 ),
					'requires_email'    => true,
					'requires_phone'    => true,
				),
				'balikovna_na_adresu' => array(
					'label'             => __( 'Balík na adresu (Balíkovna)', 'balikovna-wc' ),
					'description'       => __( 'Doručení na adresu zákazníka v rámci produktu Balíkovna.', 'balikovna-wc' ),
					'pickup'            => false,
					'service_code'      => 'NA', // Na Adresu (Balíkovna).
					'logo'              => 'logo-balikovna.svg',
					'countries'         => array( 'CZ' ),
					'max_weight_kg'     => 15,
					'max_dimensions_cm' => array( 50, 50, 50 ),
					'requires_email'    => true,
					'requires_phone'    => true,
				),
				'balikovna_plus'      => array(
					'label'                  => __( 'Balíkovna plus', 'balikovna-wc' ),
					'description'            => __( 'Doručení větších a těžších zásilek na adresu.', 'balikovna-wc' ),
					'pickup'                 => false,
					'service_code'           => 'DR',
					'service_code_options'   => array( 'DR', 'DV', 'DE' ),
					'logo'                   => 'logo-balikovna.svg',
					'countries'              => array( 'CZ' ),
					'max_weight_kg'          => 31.5,
					'contract_max_weight_kg' => 50,
					'max_dimensions_cm'      => array( 200, 200, 200 ),
					'max_dimensions_sum_cm'  => 300,
					'requires_email'         => true,
					'requires_phone'         => true,
				),
				'cp_do_ruky'          => array(
					'label'        => __( 'Balík Do ruky', 'balikovna-wc' ),
					'description'  => __( 'Doručení balíku na adresu zákazníka (Česká pošta).', 'balikovna-wc' ),
					'pickup'       => false,
					'service_code' => 'DR', // Do Ruky.
					'logo'         => 'logo-cp.svg',
					'countries'    => array( 'CZ' ),
				),
				'cp_na_postu'         => array(
					'label'        => __( 'Balík Na poštu', 'balikovna-wc' ),
					'description'  => __( 'Doručení na vybranou pobočku České pošty.', 'balikovna-wc' ),
					'pickup'       => 'POST_OFFICE',
					'service_code' => 'NP', // Na Poštu.
					'logo'         => 'logo-cp.svg',
					'countries'    => array( 'CZ' ),
				),
			)
		);
	}

	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Validate recipient contact requirements for selected services.
	 *
	 * @param string[] $service_ids Selected service IDs.
	 * @param string   $email Recipient email.
	 * @param string   $phone Recipient phone.
	 * @return array<string,string>
	 */
	public static function recipient_contact_errors( array $service_ids, $email, $phone ) {
		$requires_email = false;
		$requires_phone = false;
		foreach ( array_unique( $service_ids ) as $service_id ) {
			$service        = self::get( $service_id );
			$requires_email = $requires_email || ! empty( $service['requires_email'] );
			$requires_phone = $requires_phone || ! empty( $service['requires_phone'] );
		}

		$errors = array();
		if ( $requires_email && ! is_email( $email ) ) {
			$errors['balikovna_email_required'] = __( 'Pro dopravu Balíkovna zadejte platnou e-mailovou adresu.', 'balikovna-wc' );
		}

		$valid_phone = self::is_valid_recipient_phone( $phone, $service_ids );
		if ( $requires_phone && ! $valid_phone ) {
			$errors['balikovna_phone_required'] = __( 'Pro dopravu Balíkovna zadejte české mobilní číslo včetně předvolby +420.', 'balikovna-wc' );
		}

		return (array) apply_filters( 'balikovna_wc_recipient_contact_errors', $errors, $service_ids, $email, $phone );
	}

	public static function normalize_recipient_phone( $phone ) {
		return preg_replace( '/[\s().-]+/', '', trim( (string) $phone ) );
	}

	public static function is_valid_recipient_phone( $phone, array $service_ids = array() ) {
		$normalized_phone = self::normalize_recipient_phone( $phone );
		$valid_phone      = 1 === preg_match( '/^(?:\+420|00420)[67]\d{8}$/', $normalized_phone );
		return (bool) apply_filters(
			'balikovna_wc_valid_recipient_phone',
			$valid_phone,
			$normalized_phone,
			$service_ids
		);
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
