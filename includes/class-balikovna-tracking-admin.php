<?php
/**
 * WooCommerce administration for shipment-status tracking.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Tracking_Admin {

	const SECTION = 'balikovna_tracking';
	const FIELD   = 'balikovna_tracking';

	private $tracking;

	public function __construct( Tracking $tracking ) {
		$this->tracking = $tracking;
	}

	public function init() {
		add_filter( 'woocommerce_get_sections_shipping', array( $this, 'add_section' ) );
		add_filter( 'woocommerce_get_settings_shipping', array( $this, 'settings_fields' ), 10, 2 );
		add_action( 'woocommerce_admin_field_balikovna_tracking_panel', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_update_options_shipping', array( $this, 'maybe_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function maybe_save() {
		global $current_section;

		if ( self::SECTION === $current_section ) {
			$this->save();
		}
	}

	public function add_section( $sections ) {
		$sections[ self::SECTION ] = __( 'Sledování stavu zásilek', 'balikovna-wc' );
		return $sections;
	}

	public function settings_fields( $settings, $current_section ) {
		if ( self::SECTION !== $current_section ) {
			return $settings;
		}

		return array(
			array(
				'title' => __( 'Sledování stavu zásilek', 'balikovna-wc' ),
				'type'  => 'title',
				'id'    => 'balikovna_tracking_settings',
				'desc'  => __( 'Pravidelná synchronizace aktuálních agregovaných stavů zásilek přes oficiální Česká pošta B2B nAPI.', 'balikovna-wc' ),
			),
			array(
				'type'      => 'balikovna_tracking_panel',
				'id'        => 'balikovna_tracking_panel',
				'is_option' => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'balikovna_tracking_settings',
			),
		);
	}

	public function enqueue_assets() {
		// Reading the current admin route does not perform an action.
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'wc-settings' !== $page || 'shipping' !== $tab || self::SECTION !== $section ) {
			return;
		}
		wp_enqueue_script(
			'balikovna-tracking-admin',
			BALIKOVNA_WC_URL . 'assets/js/tracking-admin.js',
			array(),
			BALIKOVNA_WC_VERSION,
			true
		);
	}

	public function render_panel() {
		$dictionary_repository = $this->tracking->dictionary();
		$dictionary            = $dictionary_repository->get();
		$settings              = Tracking_Settings::get( $dictionary );
		$groups                = Tracking_Settings::status_groups( $dictionary );
		$order_statuses        = Tracking_Settings::order_statuses();

		echo '<tr><td colspan="2">';
		echo '<input type="hidden" name="save" value="1">';
		$this->render_connection_fields( $settings );
		$this->render_synchronization_fields( $settings, $order_statuses );
		$this->render_carrier_fields( $settings, $groups, $order_statuses );
		$this->render_diagnostics( $settings, $dictionary_repository );
		echo '</td></tr>';
	}

	public function save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'woocommerce-settings' ) ) {
			return;
		}

		$posted                = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] )
			? map_deep( wp_unslash( $_POST[ self::FIELD ] ), 'sanitize_text_field' )
			: array();
		$existing              = Tracking_Settings::get();
		$dictionary_repository = $this->tracking->dictionary( $existing );
		$dictionary            = $dictionary_repository->get();
		$posted                = $this->expand_status_groups( $posted, $dictionary );
		$settings              = Tracking_Settings::sanitize( $posted, $existing, $dictionary );
		update_option( Tracking_Settings::OPTION_NAME, $settings, false );

		$action                = isset( $_POST['balikovna_tracking_action'] )
			? sanitize_key( wp_unslash( $_POST['balikovna_tracking_action'] ) )
			: 'save';
		$dictionary_repository = $this->tracking->dictionary( $settings );
		if ( 'test_connection' === $action ) {
			$this->test_connection( $dictionary_repository, $settings );
			return;
		}
		if ( 'synchronize_now' === $action ) {
			$this->synchronize_now( $settings );
			return;
		}

		if ( Tracking_Settings::is_configured( $settings ) && $dictionary_repository->is_stale() ) {
			$result = $dictionary_repository->refresh();
			if ( $result instanceof Napi_Error ) {
				\WC_Admin_Settings::add_error(
					sprintf(
						/* translators: %s: sanitized API error. */
						__( 'Nastavení bylo uloženo, ale číselník stavů se nepodařilo obnovit: %s', 'balikovna-wc' ),
						$result->get_message()
					)
				);
			}
		}
	}

	private function render_connection_fields( array $settings ) {
		$secret_configured = '' !== (string) ( $settings['secret_key'] ?? '' );
		echo '<h3>' . esc_html__( 'Připojení Česká pošta nAPI', 'balikovna-wc' ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->checkbox_row(
			'enabled',
			__( 'Povolit sledování přes API', 'balikovna-wc' ),
			! empty( $settings['enabled'] ),
			__( 'Bez úplných přihlašovacích údajů se naplánovaná synchronizace bezpečně přeskočí.', 'balikovna-wc' )
		);
		echo '<tr><th scope="row"><label for="balikovna-api-token">' . esc_html__( 'Api-Token', 'balikovna-wc' ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="balikovna-api-token" name="' . esc_attr( self::FIELD ) . '[api_token]" value="' . esc_attr( $settings['api_token'] ?? '' ) . '" maxlength="160" autocomplete="off">';
		echo '<p class="description">' . esc_html__( 'Veřejná část API klíče vygenerovaná v uživatelské aplikaci Pošta Online.', 'balikovna-wc' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="balikovna-secret-key">' . esc_html__( 'secretKey', 'balikovna-wc' ) . '</label></th><td>';
		echo '<input type="password" class="regular-text" id="balikovna-secret-key" name="' . esc_attr( self::FIELD ) . '[secret_key]" value="" maxlength="512" autocomplete="new-password" placeholder="' . esc_attr( $secret_configured ? __( 'Uloženo - prázdné pole hodnotu nezmění', 'balikovna-wc' ) : '' ) . '">';
		echo '<p class="description">' . esc_html__( 'Tajný klíč se používá pouze k podpisu požadavků a nikdy se neposílá ani nezobrazuje.', 'balikovna-wc' ) . '</p>';
		if ( $secret_configured ) {
			echo '<label><input type="checkbox" name="' . esc_attr( self::FIELD ) . '[clear_secret]" value="1"> ' . esc_html__( 'Odstranit uložený secretKey', 'balikovna-wc' ) . '</label>';
		}
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="balikovna-environment">' . esc_html__( 'Prostředí', 'balikovna-wc' ) . '</label></th><td>';
		echo '<select id="balikovna-environment" name="' . esc_attr( self::FIELD ) . '[environment]">';
		echo '<option value="production"' . selected( 'production', $settings['environment'] ?? 'production', false ) . '>' . esc_html__( 'Produkční', 'balikovna-wc' ) . '</option>';
		echo '<option value="sandbox"' . selected( 'sandbox', $settings['environment'] ?? 'production', false ) . '>' . esc_html__( 'Testovací', 'balikovna-wc' ) . '</option>';
		echo '</select></td></tr>';
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button" name="balikovna_tracking_action" value="test_connection">' . esc_html__( 'Otestovat připojení', 'balikovna-wc' ) . '</button></p>';
	}

	private function render_synchronization_fields( array $settings, array $order_statuses ) {
		echo '<h3>' . esc_html__( 'Výběr objednávek', 'balikovna-wc' ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';
		$this->number_row(
			'batch_size',
			__( 'Počet synchronizovaných objednávek během jednoho volání cronu', 'balikovna-wc' ),
			(int) $settings['batch_size'],
			1,
			Tracking_Settings::MAX_BATCH_SIZE
		);
		$this->number_row(
			'tracking_days',
			__( 'Počet dnů, kdy bude stav objednávky kontrolován', 'balikovna-wc' ),
			(int) $settings['tracking_days'],
			1,
			Tracking_Settings::MAX_TRACKING_DAYS
		);
		echo '<tr><th scope="row">' . esc_html__( 'Stavy objednávek, u kterých bude cron kontrolovat stav zásilky', 'balikovna-wc' ) . '</th><td><fieldset>';
		foreach ( $order_statuses as $status => $label ) {
			echo '<label><input type="checkbox" name="' . esc_attr( self::FIELD ) . '[order_statuses][]" value="' . esc_attr( $status ) . '"' . checked( in_array( $status, (array) $settings['order_statuses'], true ), true, false ) . '> ' . esc_html( $label ) . '</label><br>';
		}
		echo '</fieldset></td></tr></tbody></table>';
	}

	private function render_carrier_fields( array $settings, array $groups, array $order_statuses ) {
		echo '<h3>' . esc_html__( 'Stavy zásilek', 'balikovna-wc' ) . '</h3>';
		if ( ! $groups ) {
			echo '<p>' . esc_html__( 'Číselník stavů zatím není k dispozici. Uložte přihlašovací údaje a otestujte připojení.', 'balikovna-wc' ) . '</p>';
		} else {
			echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Stavy zásilek, které budou kontrolovány', 'balikovna-wc' ) . '</legend>';
			echo '<p><strong>' . esc_html__( 'Stavy zásilek, které budou kontrolovány', 'balikovna-wc' ) . '</strong></p>';
			foreach ( $groups as $group_key => $group ) {
				$checked = true;
				foreach ( $group['codes'] as $code ) {
					$checked = $checked && Tracking_Settings::should_poll( $code, $settings );
				}
				echo '<label><input type="checkbox" name="' . esc_attr( self::FIELD ) . '[poll_groups][]" value="' . esc_attr( $group_key ) . '"' . checked( $checked, true, false ) . '> ' . esc_html( $this->group_label( $group ) ) . '</label><br>';
			}
			echo '</fieldset>';
		}

		$this->checkbox_row(
			'auto_order_status',
			__( 'Povolit změny stavů objednávek', 'balikovna-wc' ),
			! empty( $settings['auto_order_status'] ),
			__( 'Změny probíhají standardně přes WooCommerce a mohou spustit existující e-maily navázané na změnu stavu.', 'balikovna-wc' ),
			false,
			'balikovna-auto-order-status'
		);

		echo '<div id="balikovna-status-mapping"' . ( empty( $settings['auto_order_status'] ) ? ' hidden' : '' ) . '>';
		echo '<h3>' . esc_html__( 'Česká pošta status → WooCommerce status', 'balikovna-wc' ) . '</h3>';
		if ( $groups ) {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Česká pošta status', 'balikovna-wc' ) . '</th><th>' . esc_html__( 'Stav objednávky', 'balikovna-wc' ) . '</th></tr></thead><tbody>';
			foreach ( $groups as $group_key => $group ) {
				$current = $this->group_mapping( $group['codes'], $settings );
				echo '<tr><td>' . esc_html( $this->group_label( $group ) ) . '</td><td><select name="' . esc_attr( self::FIELD ) . '[mapping_groups][' . esc_attr( $group_key ) . ']">';
				echo '<option value="">' . esc_html__( 'Stav objednávky / beze změny', 'balikovna-wc' ) . '</option>';
				foreach ( $order_statuses as $status => $label ) {
					echo '<option value="' . esc_attr( $status ) . '"' . selected( $status, $current, false ) . '>' . esc_html( $label ) . '</option>';
				}
				echo '</select></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}

	private function render_diagnostics( array $settings, Status_Dictionary $dictionary ) {
		$diagnostics = get_option( Shipment_Synchronizer::DIAGNOSTICS_OPTION, array() );
		$diagnostics = is_array( $diagnostics ) ? $diagnostics : array();
		$next_run    = Tracking_Scheduler::next_scheduled();
		$last_error  = isset( $diagnostics['last_error'] ) && is_array( $diagnostics['last_error'] ) ? $diagnostics['last_error'] : array();
		$dict_error  = $dictionary->get_last_error();

		echo '<h3>' . esc_html__( 'Provozní informace', 'balikovna-wc' ) . '</h3>';
		echo '<table class="widefat striped"><tbody>';
		$this->diagnostic_row( __( 'API nakonfigurováno', 'balikovna-wc' ), Tracking_Settings::is_configured( $settings ) ? __( 'Ano', 'balikovna-wc' ) : __( 'Ne', 'balikovna-wc' ) );
		$this->diagnostic_row( __( 'Číselník stavů aktualizován', 'balikovna-wc' ), $this->format_timestamp( $dictionary->get_updated_at() ) );
		$this->diagnostic_row( __( 'Poslední úspěšná synchronizace', 'balikovna-wc' ), $this->format_timestamp( (int) ( $diagnostics['last_success_at'] ?? 0 ) ) );
		$this->diagnostic_row( __( 'Příští naplánovaná synchronizace', 'balikovna-wc' ), $this->format_timestamp( $next_run ) );
		if ( $last_error ) {
			$this->diagnostic_row( __( 'Poslední globální chyba', 'balikovna-wc' ), (string) ( $last_error['message'] ?? '' ) );
		}
		if ( $dict_error && $dictionary->get() ) {
			$this->diagnostic_row( __( 'Upozornění číselníku', 'balikovna-wc' ), __( 'Poslední obnovení selhalo; používá se dříve uložený číselník.', 'balikovna-wc' ) );
		}
		echo '</tbody></table>';
		echo '<p><button type="submit" class="button" name="balikovna_tracking_action" value="synchronize_now">' . esc_html__( 'Synchronizovat nyní', 'balikovna-wc' ) . '</button></p>';
	}

	private function expand_status_groups( array $posted, array $dictionary ) {
		$groups                    = Tracking_Settings::status_groups( $dictionary );
		$selected                  = isset( $posted['poll_groups'] ) ? array_map( 'sanitize_key', (array) $posted['poll_groups'] ) : array();
		$mappings                  = isset( $posted['mapping_groups'] ) && is_array( $posted['mapping_groups'] ) ? $posted['mapping_groups'] : array();
		$posted['poll_statuses']   = array();
		$posted['status_mappings'] = array();
		foreach ( $groups as $group_key => $group ) {
			if ( in_array( $group_key, $selected, true ) ) {
				$posted['poll_statuses'] = array_merge( $posted['poll_statuses'], $group['codes'] );
			}
			$target = isset( $mappings[ $group_key ] ) ? (string) $mappings[ $group_key ] : '';
			if ( '' === $target ) {
				continue;
			}
			foreach ( $group['codes'] as $code ) {
				$posted['status_mappings'][ $code ] = $target;
			}
		}
		return $posted;
	}

	private function test_connection( Status_Dictionary $dictionary, array $settings ) {
		if ( ! Tracking_Settings::is_configured( $settings ) ) {
			\WC_Admin_Settings::add_error( __( 'Nejprve vyplňte Api-Token a secretKey.', 'balikovna-wc' ) );
			return;
		}
		$result = $dictionary->refresh( true );
		if ( $result instanceof Napi_Error ) {
			\WC_Admin_Settings::add_error(
				sprintf(
					/* translators: %s: sanitized API error. */
					__( 'Připojení k Česká pošta nAPI selhalo: %s', 'balikovna-wc' ),
					$result->get_message()
				)
			);
			return;
		}
		\WC_Admin_Settings::add_message(
			sprintf(
				/* translators: %d: number of carrier status codes. */
				__( 'Připojení je funkční. Načteno stavů: %d.', 'balikovna-wc' ),
				count( $result )
			)
		);
	}

	private function synchronize_now( array $settings ) {
		$result = $this->tracking->synchronizer( $settings )->run_batch();
		if ( $result instanceof Napi_Error ) {
			\WC_Admin_Settings::add_error(
				sprintf(
					/* translators: %s: sanitized synchronization error. */
					__( 'Synchronizace se nepodařila: %s', 'balikovna-wc' ),
					$result->get_message()
				)
			);
			return;
		}
		if ( ! empty( $result['skipped'] ) ) {
			\WC_Admin_Settings::add_error( __( 'Synchronizace je vypnutá nebo nejsou vyplněné přihlašovací údaje.', 'balikovna-wc' ) );
			return;
		}
		\WC_Admin_Settings::add_message(
			sprintf(
				/* translators: 1: number of orders, 2: number of parcel lookups. */
				__( 'Synchronizace dokončena. Objednávky: %1$d, zásilky: %2$d.', 'balikovna-wc' ),
				(int) $result['orders'],
				(int) $result['shipments']
			)
		);
	}

	private function checkbox_row( $key, $label, $checked, $description, $table = true, $id = '' ) {
		if ( $table ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		}
		$id = $id ? $id : 'balikovna-' . $key;
		echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::FIELD ) . '[' . esc_attr( $key ) . ']" value="1"' . checked( $checked, true, false ) . '> ' . esc_html( $label ) . '</label>';
		if ( $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		if ( $table ) {
			echo '</td></tr>';
		}
	}

	private function number_row( $key, $label, $value, $minimum, $maximum ) {
		echo '<tr><th scope="row"><label for="balikovna-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="number" class="small-text" id="balikovna-' . esc_attr( $key ) . '" name="' . esc_attr( self::FIELD ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $value ) . '" min="' . esc_attr( (string) $minimum ) . '" max="' . esc_attr( (string) $maximum ) . '" step="1">';
		echo '</td></tr>';
	}

	private function diagnostic_row( $label, $value ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $value ? $value : '—' ) . '</td></tr>';
	}

	private function group_label( array $group ) {
		$count = count( $group['codes'] );
		if ( 1 === $count ) {
			return $group['name'] . ' (' . $group['codes'][0] . ')';
		}
		return sprintf(
			/* translators: 1: aggregate carrier status label, 2: number of API status/reason codes. */
			__( '%1$s (%2$d kódů API)', 'balikovna-wc' ),
			$group['name'],
			$count
		);
	}

	private function group_mapping( array $codes, array $settings ) {
		$targets = array();
		foreach ( $codes as $code ) {
			$target = Tracking_Settings::mapping_for( $code, $settings );
			if ( '' !== $target ) {
				$targets[] = $target;
			}
		}
		$targets = array_values( array_unique( $targets ) );
		return 1 === count( $targets ) ? $targets[0] : '';
	}

	private function format_timestamp( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}
		$format  = function_exists( 'wc_date_format' ) ? wc_date_format() : get_option( 'date_format', 'j. n. Y' );
		$format .= ' ' . ( function_exists( 'wc_time_format' ) ? wc_time_format() : get_option( 'time_format', 'H:i' ) );
		return wp_date( $format, (int) $timestamp, wp_timezone() );
	}
}
