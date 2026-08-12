<?php

use Balikovna_WC\Napi_Authentication;
use Balikovna_WC\Napi_Client;
use Balikovna_WC\Napi_Transport_Interface;
use Balikovna_WC\Shipment_Status;
use Balikovna_WC\Status_Dictionary;
use Balikovna_WC\Tracking_Settings;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Dictionary_Transport implements Napi_Transport_Interface {
	public $calls = 0;
	private $responses;

	public function __construct( array $responses ) {
		$this->responses = $responses;
	}

	public function request( $method, $url, array $args ) {
		++$this->calls;
		return array_shift( $this->responses );
	}
}

final class TrackingSettingsTest extends TestCase {
	private $dictionary;

	protected function setUp(): void {
		$GLOBALS['balikovna_test_options'] = array();
		$GLOBALS['balikovna_test_order_statuses'] = array(
			'wc-processing'   => 'Zpracovává se',
			'wc-completed'    => 'Dokončeno',
			'wc-cancelled'    => 'Zrušeno',
			'wc-refunded'     => 'Vráceno',
			'wc-failed'       => 'Selhalo',
			'wc-shipped'      => 'Odesláno',
			'wc-ready-pickup' => 'Připraveno k vyzvednutí',
			'wc-reviewing'    => 'Ruční kontrola',
		);
		$this->dictionary = array(
			'13/00' => array( 'code' => '13/00', 'status' => '13', 'reason' => '00', 'name' => 'PŘEDANÁ DATA' ),
			'21/00' => array( 'code' => '21/00', 'status' => '21', 'reason' => '00', 'name' => 'PODÁNO' ),
			'44/01' => array( 'code' => '44/01', 'status' => '44', 'reason' => '01', 'name' => 'V PŘEPRAVĚ' ),
			'81/00' => array( 'code' => '81/00', 'status' => '81', 'reason' => '00', 'name' => 'ULOŽENO' ),
			'91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ),
			'95/00' => array( 'code' => '95/00', 'status' => '95', 'reason' => '00', 'name' => 'VRACÍ SE' ),
			'31/00' => array( 'code' => '31/00', 'status' => '31', 'reason' => '00', 'name' => 'VRÁCENO ODESILATELI' ),
		);
	}

	public function test_defaults_discover_custom_order_statuses_and_map_semantically(): void {
		$settings = Tracking_Settings::defaults( $this->dictionary );

		$this->assertSame( array( 'wc-processing', 'wc-shipped', 'wc-ready-pickup' ), $settings['order_statuses'] );
		$this->assertSame( 'wc-shipped', $settings['status_mappings']['21/00'] );
		$this->assertSame( 'wc-shipped', $settings['status_mappings']['44/01'] );
		$this->assertSame( 'wc-ready-pickup', $settings['status_mappings']['81/00'] );
		$this->assertSame( 'wc-completed', $settings['status_mappings']['91/00'] );
		$this->assertArrayNotHasKey( '13/00', $settings['status_mappings'] );
		$this->assertArrayNotHasKey( '95/00', $settings['status_mappings'] );
		$this->assertContains( '95/00', $settings['poll_statuses'] );
		$this->assertNotContains( '91/00', $settings['poll_statuses'] );
		$this->assertNotContains( '31/00', $settings['poll_statuses'] );
		$this->assertArrayHasKey( 'wc-reviewing', Tracking_Settings::order_statuses() );
		$this->assertSame( Shipment_Status::SEMANTIC_ACCEPTED, Shipment_Status::semantic_for_label( 'PODANO' ) );
		$this->assertSame( Shipment_Status::SEMANTIC_DELIVERED, Shipment_Status::semantic_for_label( 'DORUCENO' ) );
	}

	public function test_semantic_labels_normalize_case_accents_whitespace_and_terminal_punctuation(): void {
		$cases = array(
			'PŘEDANÁ DATA'          => Shipment_Status::SEMANTIC_DATA,
			' zpracovana   data! '  => Shipment_Status::SEMANTIC_DATA,
			'Podáno!!!'             => Shipment_Status::SEMANTIC_ACCEPTED,
			' v   přepravě... '     => Shipment_Status::SEMANTIC_TRANSIT,
			'ULOŽENO'               => Shipment_Status::SEMANTIC_PICKUP,
			'ulozeno!'              => Shipment_Status::SEMANTIC_PICKUP,
			'Doručeno!'             => Shipment_Status::SEMANTIC_DELIVERED,
			' vrací   se; '         => Shipment_Status::SEMANTIC_RETURNING,
			'vraceno odesilateli:'  => Shipment_Status::SEMANTIC_RETURNED,
		);

		foreach ( $cases as $label => $semantic ) {
			$this->assertSame( $semantic, Shipment_Status::semantic_for_label( $label ), $label );
		}
		$this->assertSame( Shipment_Status::SEMANTIC_UNKNOWN, Shipment_Status::semantic_for_label( 'NEDORUČENO!' ) );
		$this->assertSame( Shipment_Status::SEMANTIC_UNKNOWN, Shipment_Status::semantic_for_label( 'DORUČENO ČÁSTEČNĚ' ) );
	}

	public function test_missing_custom_logistics_statuses_are_never_created_or_mapped(): void {
		unset(
			$GLOBALS['balikovna_test_order_statuses']['wc-shipped'],
			$GLOBALS['balikovna_test_order_statuses']['wc-ready-pickup']
		);

		$settings = Tracking_Settings::defaults( $this->dictionary );

		$this->assertSame( array( 'wc-processing' ), $settings['order_statuses'] );
		$this->assertArrayNotHasKey( '21/00', $settings['status_mappings'] );
		$this->assertArrayNotHasKey( '81/00', $settings['status_mappings'] );
		$this->assertSame( 'wc-completed', $settings['status_mappings']['91/00'] );
	}

	public function test_settings_sanitization_bounds_values_and_retains_blank_secret(): void {
		$existing = Tracking_Settings::defaults( $this->dictionary );
		$existing['api_token'] = 'stored-token';
		$existing['secret_key'] = 'stored-secret';
		$sanitized = Tracking_Settings::sanitize(
			array(
				'enabled'           => '1',
				'api_token'         => " token\nvalue ",
				'secret_key'        => '',
				'environment'       => 'arbitrary-host',
				'batch_size'        => '999999',
				'tracking_days'     => '0',
				'order_statuses'    => array( 'processing', 'wc-reviewing', 'wc-injected' ),
				'poll_statuses'     => array( '44/01', 'NEW/00' ),
				'auto_order_status' => '1',
				'status_mappings'   => array(
					'44/01' => 'reviewing',
					'91/00' => 'wc-injected',
					'NEW/00' => 'wc-completed',
				),
			),
			$existing,
			$this->dictionary
		);

		$this->assertTrue( $sanitized['enabled'] );
		$this->assertSame( 'tokenvalue', $sanitized['api_token'] );
		$this->assertSame( 'stored-secret', $sanitized['secret_key'] );
		$this->assertSame( 'production', $sanitized['environment'] );
		$this->assertSame( Tracking_Settings::MAX_BATCH_SIZE, $sanitized['batch_size'] );
		$this->assertSame( 1, $sanitized['tracking_days'] );
		$this->assertSame( array( 'wc-processing', 'wc-reviewing' ), $sanitized['order_statuses'] );
		$this->assertSame( array( '44/01' ), $sanitized['poll_statuses'] );
		$this->assertSame( array( '44/01' => 'wc-reviewing' ), $sanitized['status_mappings'] );
		$this->assertGreaterThan( $existing['mapping_revision'], $sanitized['mapping_revision'] );
	}

	public function test_blank_credentials_preserve_replacements_and_explicit_clearing(): void {
		$existing = Tracking_Settings::defaults( $this->dictionary );
		$existing['api_token'] = 'stored-token';
		$existing['secret_key'] = 'stored-secret';

		$preserved = Tracking_Settings::sanitize( array( 'api_token' => '', 'secret_key' => '' ), $existing, $this->dictionary );
		$replaced  = Tracking_Settings::sanitize( array( 'api_token' => 'new-token', 'secret_key' => 'new-secret' ), $existing, $this->dictionary );
		$cleared   = Tracking_Settings::sanitize( array( 'clear_api_token' => '1', 'clear_secret' => '1' ), $existing, $this->dictionary );

		$this->assertSame( 'stored-token', $preserved['api_token'] );
		$this->assertSame( 'stored-secret', $preserved['secret_key'] );
		$this->assertSame( 'new-token', $replaced['api_token'] );
		$this->assertSame( 'new-secret', $replaced['secret_key'] );
		$this->assertSame( '', $cleared['api_token'] );
		$this->assertSame( '', $cleared['secret_key'] );
	}

	public function test_unknown_new_status_continues_polling_without_mapping(): void {
		$settings = Tracking_Settings::defaults( $this->dictionary );

		$this->assertTrue( Tracking_Settings::should_poll( 'NEW/00', $settings ) );
		$this->assertSame( '', Tracking_Settings::mapping_for( 'NEW/00', $settings ) );
		$this->assertFalse( Tracking_Settings::should_poll( '91/00', $settings ) );
	}

	public function test_sanitization_without_dictionary_preserves_existing_carrier_configuration(): void {
		$existing = Tracking_Settings::defaults( $this->dictionary );
		$existing['secret_key'] = 'stored-secret';

		$sanitized = Tracking_Settings::sanitize(
			array(
				'enabled'        => '1',
				'api_token'      => 'token',
				'order_statuses' => array( 'wc-processing' ),
			),
			$existing,
			array()
		);

		$this->assertSame( $existing['poll_statuses'], $sanitized['poll_statuses'] );
		$this->assertSame( $existing['status_mappings'], $sanitized['status_mappings'] );
		$this->assertSame( $existing['known_status_codes'], $sanitized['known_status_codes'] );
		$this->assertTrue( $sanitized['status_defaults_initialized'] );
	}

	public function test_successful_dictionary_change_does_not_delete_hidden_configuration(): void {
		$existing = Tracking_Settings::defaults( $this->dictionary );
		$existing['poll_statuses'][]           = 'OLD/00';
		$existing['known_status_codes'][]      = 'OLD/00';
		$existing['status_mappings']['OLD/00'] = 'wc-reviewing';

		$sanitized = Tracking_Settings::sanitize(
			array(
				'api_token'         => 'token',
				'order_statuses'    => array( 'wc-processing' ),
				'poll_statuses'     => array( '44/01' ),
				'status_mappings'   => array( '44/01' => 'wc-shipped' ),
			),
			$existing,
			$this->dictionary
		);

		$this->assertContains( 'OLD/00', $sanitized['poll_statuses'] );
		$this->assertContains( 'OLD/00', $sanitized['known_status_codes'] );
		$this->assertSame( 'wc-reviewing', $sanitized['status_mappings']['OLD/00'] );
	}

	public function test_dictionary_refresh_failure_keeps_last_successful_cache(): void {
		$transport = new Balikovna_Test_Dictionary_Transport(
			array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode( array( 'statusesList' => array_values( $this->dictionary ) ) ),
				),
				array(
					'response' => array( 'code' => 500 ),
					'body'     => '[{"code":-5,"status":"500","message":"Temporary failure"}]',
				),
			)
		);
		$auth       = new Napi_Authentication( 'token', 'secret', function () { return time(); }, function () { return '00000000-0000-4000-8000-000000000000'; } );
		$repository = new Status_Dictionary( new Napi_Client( $auth, $transport ) );

		$first  = $repository->refresh( true );
		$second = $repository->refresh( true );

		$this->assertIsArray( $first );
		$this->assertInstanceOf( Balikovna_WC\Napi_Error::class, $second );
		$this->assertSame( $first, $repository->get() );
		$this->assertSame( 'service_unavailable', $repository->get_last_error()['code'] );
		$this->assertTrue( Tracking_Settings::get()['status_defaults_initialized'] );
	}

	public function test_refresh_reconciles_unambiguous_new_reason_code_once(): void {
		$previous = array(
			'91/00' => $this->dictionary['91/00'],
		);
		$current = $previous + array(
			'91/99' => array( 'code' => '91/99', 'status' => '91', 'reason' => '99', 'name' => 'DORUCENO!' ),
		);
		$settings = Tracking_Settings::defaults( $previous );
		$settings['mapping_revision'] = 7;
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array(
			'updated_at' => 1,
			'statuses'   => $previous,
			'last_error' => array(),
		);
		$transport = new Balikovna_Test_Dictionary_Transport(
			array(
				array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'statusesList' => array_values( $current ) ) ) ),
				array( 'response' => array( 'code' => 200 ), 'body' => json_encode( array( 'statusesList' => array_values( $current ) ) ) ),
			)
		);
		$repository = new Status_Dictionary( new Napi_Client( new Napi_Authentication( 'token', 'secret' ), $transport ) );

		$repository->refresh( true );
		$reconciled = Tracking_Settings::get();
		$repository->refresh( true );
		$idempotent = Tracking_Settings::get();

		$this->assertSame( 'wc-completed', $reconciled['status_mappings']['91/99'] );
		$this->assertNotContains( '91/99', $reconciled['poll_statuses'] );
		$this->assertSame( 8, $reconciled['mapping_revision'] );
		$this->assertSame( $reconciled, $idempotent );
		$this->assertSame( 'wc-completed', $reconciled['status_mappings']['91/00'] );
	}

	public function test_reconciliation_preserves_conflicts_and_defaults_mixed_polling_to_continue(): void {
		$previous = array(
			'91/00' => $this->dictionary['91/00'],
			'91/01' => array( 'code' => '91/01', 'status' => '91', 'reason' => '01', 'name' => 'DORUČENO' ),
		);
		$current = $previous + array(
			'91/99'  => array( 'code' => '91/99', 'status' => '91', 'reason' => '99', 'name' => 'Doručeno!' ),
			'NEW/00' => array( 'code' => 'NEW/00', 'status' => 'NEW', 'reason' => '00', 'name' => 'ZCELA NOVÝ STAV' ),
		);
		$settings = Tracking_Settings::defaults( $previous );
		$settings['status_mappings'] = array( '91/00' => 'wc-completed' );
		$settings['poll_statuses']   = array( '91/01' );
		$settings['mapping_revision'] = 4;
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;

		Tracking_Settings::reconcile_status_dictionary( $current, $previous );
		$reconciled = Tracking_Settings::get();

		$this->assertArrayNotHasKey( '91/99', $reconciled['status_mappings'] );
		$this->assertArrayNotHasKey( 'NEW/00', $reconciled['status_mappings'] );
		$this->assertContains( '91/99', $reconciled['poll_statuses'] );
		$this->assertContains( 'NEW/00', $reconciled['poll_statuses'] );
		$this->assertSame( 4, $reconciled['mapping_revision'] );
		$this->assertSame( 'wc-completed', $reconciled['status_mappings']['91/00'] );
		$this->assertArrayNotHasKey( '91/01', $reconciled['status_mappings'] );
	}

	public function test_reconciliation_inherits_unanimous_continue_polling(): void {
		$previous = array(
			'44/01' => $this->dictionary['44/01'],
			'44/02' => array( 'code' => '44/02', 'status' => '44', 'reason' => '02', 'name' => 'V PŘEPRAVĚ' ),
		);
		$current = $previous + array(
			'44/99' => array( 'code' => '44/99', 'status' => '44', 'reason' => '99', 'name' => '  v   přepravě!!! ' ),
		);
		$settings = Tracking_Settings::defaults( $previous );
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;

		Tracking_Settings::reconcile_status_dictionary( $current, $previous );
		$reconciled = Tracking_Settings::get();

		$this->assertContains( '44/99', $reconciled['poll_statuses'] );
		$this->assertSame( 'wc-shipped', $reconciled['status_mappings']['44/99'] );
	}

	public function test_reconciliation_inherits_persisted_custom_target_before_registration(): void {
		$previous = array(
			'91/00' => $this->dictionary['91/00'],
		);
		$current = $previous + array(
			'91/99' => array( 'code' => '91/99', 'status' => '91', 'reason' => '99', 'name' => 'DORUČENO' ),
		);
		$settings = Tracking_Settings::defaults( $previous );
		$settings['status_mappings'] = array( '91/00' => 'wc-reviewing' );
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;
		unset( $GLOBALS['balikovna_test_order_statuses']['wc-reviewing'] );

		Tracking_Settings::reconcile_status_dictionary( $current, $previous );
		$reconciled = Tracking_Settings::get();

		$this->assertSame( 'wc-reviewing', $reconciled['status_mappings']['91/99'] );
	}

	public function test_reconciliation_ignores_locally_observed_unofficial_rows(): void {
		$previous = array(
			'91/00' => $this->dictionary['91/00'],
		);
		$current = $previous + array(
			'91/99' => array(
				'code'     => '91/99',
				'status'   => '91',
				'reason'   => '99',
				'name'     => 'DORUČENO',
				'observed' => true,
			),
		);
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = Tracking_Settings::defaults( $previous );

		Tracking_Settings::reconcile_status_dictionary( $current, $previous );
		$settings = Tracking_Settings::get();

		$this->assertNotContains( '91/99', $settings['known_status_codes'] );
		$this->assertArrayNotHasKey( '91/99', $settings['status_mappings'] );
		$this->assertTrue( Tracking_Settings::should_poll( '91/99', $settings ) );
	}

	public function test_v128_observed_known_code_is_cleaned_then_reconciled_when_official(): void {
		$previous = array(
			'91/00' => $this->dictionary['91/00'],
			'91/99' => array(
				'code'     => '91/99',
				'status'   => '91',
				'reason'   => '99',
				'name'     => 'DORUČENO',
				'observed' => true,
			),
		);
		$official = array(
			'91/00' => $this->dictionary['91/00'],
			'91/99' => array( 'code' => '91/99', 'status' => '91', 'reason' => '99', 'name' => 'DORUČENO' ),
		);
		$settings = Tracking_Settings::defaults( array( '91/00' => $this->dictionary['91/00'] ) );
		$settings['known_status_codes'][] = '91/99';
		$settings['poll_statuses'][]      = '91/99';
		$settings['mapping_revision']     = 7;
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;

		Tracking_Settings::reconcile_status_dictionary( $previous, $previous );
		$cleaned = Tracking_Settings::get();
		Tracking_Settings::reconcile_status_dictionary( $official, $previous );
		$reconciled = Tracking_Settings::get();

		$this->assertNotContains( '91/99', $cleaned['known_status_codes'] );
		$this->assertSame( 7, $cleaned['mapping_revision'] );
		$this->assertContains( '91/99', $reconciled['known_status_codes'] );
		$this->assertSame( 'wc-completed', $reconciled['status_mappings']['91/99'] );
		$this->assertNotContains( '91/99', $reconciled['poll_statuses'] );
		$this->assertSame( 8, $reconciled['mapping_revision'] );
	}

	public function test_mapping_revision_ignores_mapping_key_order(): void {
		$existing = Tracking_Settings::defaults( $this->dictionary );
		$existing['status_mappings'] = array(
			'21/00' => 'wc-shipped',
			'91/00' => 'wc-completed',
		);
		$existing['mapping_revision'] = 7;
		$input = array(
			'order_statuses'  => array( 'wc-processing' ),
			'status_mappings' => array(
				'91/00' => 'wc-completed',
				'21/00' => 'wc-shipped',
			),
		);

		$sanitized = Tracking_Settings::sanitize( $input, $existing, $this->dictionary );

		$this->assertSame( 7, $sanitized['mapping_revision'] );
		$this->assertSame( array( '21/00', '91/00' ), array_keys( $sanitized['status_mappings'] ) );
	}

	public function test_unchanged_v128_option_payload_is_preserved_exactly(): void {
		$settings = Tracking_Settings::defaults( $this->dictionary );
		$settings['enabled']           = true;
		$settings['api_token']         = 'stored-token';
		$settings['secret_key']        = 'stored-secret';
		$settings['environment']       = 'sandbox';
		$settings['batch_size']        = 73;
		$settings['tracking_days']     = 21;
		$settings['auto_order_status'] = true;
		$settings['mapping_revision']  = 9;
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;

		$result = Tracking_Settings::reconcile_status_dictionary( $this->dictionary, $this->dictionary );

		$this->assertFalse( $result );
		$this->assertSame( $settings, get_option( Tracking_Settings::OPTION_NAME ) );
	}

	public function test_observed_unknown_status_is_added_without_changing_known_snapshot(): void {
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array(
			'updated_at' => time(),
			'statuses'   => $this->dictionary,
			'last_error' => array(),
		);
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = Tracking_Settings::defaults( $this->dictionary );
		$transport = new Balikovna_Test_Dictionary_Transport( array() );
		$auth       = new Napi_Authentication( 'token', 'secret' );
		$repository = new Status_Dictionary( new Napi_Client( $auth, $transport ) );
		$status     = Shipment_Status::from_status_info(
			array(
				'idParcel'    => 'BA1234567890A',
				'parcelStatus' => array( 'statusID' => 'NEW', 'reasonID' => '01', 'statusDescription' => 'Nový stav' ),
			)
		);

		$repository->remember( $status );

		$this->assertArrayHasKey( 'NEW/01', $repository->get() );
		$this->assertTrue( Tracking_Settings::should_poll( 'NEW/01', Tracking_Settings::get() ) );
	}
}