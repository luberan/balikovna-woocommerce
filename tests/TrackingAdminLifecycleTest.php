<?php

use Balikovna_WC\Napi_Authentication;
use Balikovna_WC\Napi_Client;
use Balikovna_WC\Napi_Transport_Interface;
use Balikovna_WC\Status_Dictionary;
use Balikovna_WC\Tracking;
use Balikovna_WC\Tracking_Admin;
use Balikovna_WC\Tracking_Scheduler;
use Balikovna_WC\Tracking_Settings;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Admin_Napi_Transport implements Napi_Transport_Interface {
	public function request( $method, $url, array $args ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"statusesList":[{"status":"91","reason":"00","name":"DORUČENO"}]}',
		);
	}
}

final class TrackingAdminLifecycleTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['balikovna_test_actions']           = array();
		$GLOBALS['balikovna_test_did_actions']       = array();
		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$GLOBALS['balikovna_test_enqueued_scripts']  = array();
		$GLOBALS['balikovna_test_options']           = array();
		\WC_Admin_Settings::$errors                  = array();
		\WC_Admin_Settings::$messages                = array();
		$_POST                                       = array();
		$GLOBALS['balikovna_test_order_statuses']    = array(
			'wc-processing'   => 'Zpracovává se',
			'wc-completed'    => 'Dokončeno',
			'wc-shipped'      => 'Odesláno',
			'wc-ready-pickup' => 'Připraveno k vyzvednutí',
			'wc-reviewing'    => 'Ruční kontrola',
		);
	}

	public function test_recurring_action_is_idempotent_and_deactivation_unschedules_it(): void {
		$scheduler = new Tracking_Scheduler(
			function () {
				return null;
			}
		);

		$scheduler->ensure_scheduled();
		$scheduler->ensure_scheduled();

		$this->assertCount( 1, $GLOBALS['balikovna_test_scheduled_actions'] );
		$action = $GLOBALS['balikovna_test_scheduled_actions'][0];
		$this->assertSame( Tracking_Scheduler::HOOK, $action['hook'] );
		$this->assertSame( Tracking_Scheduler::GROUP, $action['group'] );
		$this->assertSame( 30 * MINUTE_IN_SECONDS, $action['interval'] );
		$this->assertTrue( $action['unique'] );
		$this->assertSame( $action['timestamp'], Tracking_Scheduler::next_scheduled() );

		Tracking_Scheduler::unschedule();

		$this->assertSame( array(), $GLOBALS['balikovna_test_scheduled_actions'] );
	}

	public function test_scheduler_registers_after_action_scheduler_is_initialized(): void {
		$GLOBALS['balikovna_test_did_actions']['action_scheduler_init'] = 1;
		$scheduler = new Tracking_Scheduler(
			function () {
				return null;
			}
		);

		$scheduler->init();

		$this->assertArrayHasKey( Tracking_Scheduler::HOOK, $GLOBALS['balikovna_test_actions'] );
		$this->assertArrayHasKey( 'action_scheduler_init', $GLOBALS['balikovna_test_actions'] );
		$this->assertCount( 1, $GLOBALS['balikovna_test_scheduled_actions'] );
	}

	public function test_settings_panel_contains_custom_status_and_never_renders_secret(): void {
		$dictionary = array(
			'21/00' => array( 'code' => '21/00', 'status' => '21', 'reason' => '00', 'name' => 'PODÁNO' ),
			'91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ),
		);
		$settings = Tracking_Settings::defaults( $dictionary );
		$settings['api_token']  = 'public-token';
		$settings['secret_key'] = 'never-render-this-secret';
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array(
			'updated_at' => time(),
			'statuses'   => $dictionary,
			'last_error' => array(),
		);
		$admin = new Tracking_Admin( new Tracking() );

		ob_start();
		$admin->render_panel();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Ruční kontrola', $output );
		$this->assertStringContainsString( 'name="balikovna_tracking[mapping_groups]', $output );
		$this->assertStringContainsString( 'value="wc-reviewing"', $output );
		$this->assertStringContainsString( 'type="password"', $output );
		$this->assertStringContainsString( 'type="hidden" name="save"', $output );
		$this->assertStringNotContainsString( 'never-render-this-secret', $output );
		$this->assertStringNotContainsString( 'public-token', $output );
		$this->assertStringContainsString( 'name="balikovna_tracking[clear_api_token]"', $output );
		$this->assertStringContainsString( 'name="balikovna_tracking[clear_secret]"', $output );
	}

	public function test_admin_section_uses_requested_name(): void {
		$admin    = new Tracking_Admin( new Tracking() );
		$sections = $admin->add_section( array() );
		$fields   = $admin->settings_fields( array(), Tracking_Admin::SECTION );
		$admin->init();

		$this->assertSame( 'Sledování stavu zásilek', $sections[ Tracking_Admin::SECTION ] );
		$this->assertSame( 'Sledování stavu zásilek', $fields[0]['title'] );
		$this->assertFalse( $fields[1]['is_option'] );
		$this->assertArrayHasKey( 'woocommerce_update_options_shipping', $GLOBALS['balikovna_test_actions'] );
		$this->assertSame( 'maybe_save', $GLOBALS['balikovna_test_actions']['woocommerce_update_options_shipping'][0][0][1] );
	}

	public function test_mixed_group_is_rendered_as_mixed_instead_of_single_mapping(): void {
		$dictionary = array(
			'91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ),
			'91/99' => array( 'code' => '91/99', 'status' => '91', 'reason' => '99', 'name' => 'DORUČENO' ),
		);
		$settings = Tracking_Settings::defaults( $dictionary );
		unset( $settings['status_mappings']['91/99'] );
		$settings['poll_statuses'] = array( '91/99' );
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array(
			'updated_at' => time(),
			'statuses'   => $dictionary,
			'last_error' => array(),
		);

		ob_start();
		( new Tracking_Admin( new Tracking() ) )->render_panel();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/mapping_groups[^>]+>.*?<option value="__mixed__" selected="selected">Různé \/ částečné nastavení<\/option>/s', $output );
		$this->assertStringContainsString( 'data-balikovna-mixed-polling', $output );
	}

	public function test_saving_mixed_group_preserves_values_and_explicit_choice_unifies_codes(): void {
		$dictionary = array(
			'91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ),
			'91/99' => array( 'code' => '91/99', 'status' => '91', 'reason' => '99', 'name' => 'DORUČENO' ),
		);
		$settings = Tracking_Settings::defaults( $dictionary );
		$settings['api_token']       = 'stored-token';
		$settings['secret_key']      = 'stored-secret';
		$settings['status_mappings'] = array( '91/00' => 'wc-completed' );
		$settings['poll_statuses']   = array( '91/99' );
		$settings['mapping_revision'] = 7;
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array( 'updated_at' => time(), 'statuses' => $dictionary, 'last_error' => array() );
		$group_key = array_key_first( Tracking_Settings::status_groups( $dictionary ) );
		$admin     = new Tracking_Admin( new Tracking() );
		$_POST = array(
			'_wpnonce' => 'valid-nonce',
			'balikovna_tracking' => array(
				'api_token'         => '',
				'secret_key'        => '',
				'order_statuses'    => array( 'wc-processing' ),
				'mapping_groups'    => array( $group_key => Tracking_Admin::MIXED ),
				'mixed_poll_groups' => array( $group_key ),
			),
		);

		$admin->save();
		$preserved = Tracking_Settings::get();

		$_POST['balikovna_tracking']['mapping_groups'][ $group_key ] = 'wc-completed';
		$_POST['balikovna_tracking']['poll_groups'] = array( $group_key );
		unset( $_POST['balikovna_tracking']['mixed_poll_groups'] );
		$admin->save();
		$unified = Tracking_Settings::get();

		$this->assertSame( array( '91/00' => 'wc-completed' ), $preserved['status_mappings'] );
		$this->assertSame( array( '91/99' ), $preserved['poll_statuses'] );
		$this->assertSame( 'stored-token', $preserved['api_token'] );
		$this->assertSame( 'stored-secret', $preserved['secret_key'] );
		$this->assertSame( 7, $preserved['mapping_revision'] );
		$this->assertSame( 'wc-completed', $unified['status_mappings']['91/00'] );
		$this->assertSame( 'wc-completed', $unified['status_mappings']['91/99'] );
		$this->assertContains( '91/00', $unified['poll_statuses'] );
		$this->assertContains( '91/99', $unified['poll_statuses'] );
		$this->assertSame( 8, $unified['mapping_revision'] );
	}

	public function test_connection_success_reports_cis_dictionary_scope_without_credentials(): void {
		$repository = new Status_Dictionary(
			new Napi_Client(
				new Napi_Authentication( 'sensitive-token', 'sensitive-secret' ),
				new Balikovna_Test_Admin_Napi_Transport()
			)
		);
		$admin  = new Tracking_Admin( new Tracking() );
		$method = new ReflectionMethod( $admin, 'test_connection' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		$method->invoke( $admin, $repository, array( 'api_token' => 'sensitive-token', 'secret_key' => 'sensitive-secret' ) );
		$message = implode( ' ', \WC_Admin_Settings::$messages );

		$this->assertStringContainsString( 'nAPI CIS', $message );
		$this->assertStringContainsString( 'číselník stavů', $message );
		$this->assertStringNotContainsString( 'ZSK', $message );
		$this->assertStringNotContainsString( 'sensitive-token', $message );
		$this->assertStringNotContainsString( 'sensitive-secret', $message );
	}

	public function test_admin_save_does_not_mark_observed_unknown_code_as_officially_known(): void {
		$dictionary = array(
			'91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ),
			'91/99' => array( 'code' => '91/99', 'status' => '91', 'reason' => '99', 'name' => 'DORUČENO', 'observed' => true ),
		);
		$settings = Tracking_Settings::defaults( array( '91/00' => $dictionary['91/00'] ) );
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array( 'updated_at' => time(), 'statuses' => $dictionary, 'last_error' => array() );
		$group_key = array_key_first( Tracking_Settings::status_groups( $dictionary ) );
		$_POST = array(
			'_wpnonce' => 'valid-nonce',
			'balikovna_tracking' => array(
				'order_statuses'    => array( 'wc-processing' ),
				'mapping_groups'    => array( $group_key => Tracking_Admin::MIXED ),
				'mixed_poll_groups' => array( $group_key ),
			),
		);

		( new Tracking_Admin( new Tracking() ) )->save();
		$saved = Tracking_Settings::get();

		$this->assertNotContains( '91/99', $saved['known_status_codes'] );
		$this->assertTrue( Tracking_Settings::should_poll( '91/99', $saved ) );
	}
}