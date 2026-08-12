<?php

use Balikovna_WC\Status_Dictionary;
use Balikovna_WC\Tracking;
use Balikovna_WC\Tracking_Admin;
use Balikovna_WC\Tracking_Scheduler;
use Balikovna_WC\Tracking_Settings;
use PHPUnit\Framework\TestCase;

final class TrackingAdminLifecycleTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['balikovna_test_actions']           = array();
		$GLOBALS['balikovna_test_did_actions']       = array();
		$GLOBALS['balikovna_test_scheduled_actions'] = array();
		$GLOBALS['balikovna_test_enqueued_scripts']  = array();
		$GLOBALS['balikovna_test_options']           = array();
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
		$this->assertStringContainsString( 'public-token', $output );
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

	public function test_new_code_in_existing_group_inherits_visible_mapping(): void {
		$dictionary = array(
			'91/00' => array( 'code' => '91/00', 'status' => '91', 'reason' => '00', 'name' => 'DORUČENO' ),
			'91/99' => array( 'code' => '91/99', 'status' => '91', 'reason' => '99', 'name' => 'DORUČENO' ),
		);
		$settings = Tracking_Settings::defaults( $dictionary );
		unset( $settings['status_mappings']['91/99'] );
		$GLOBALS['balikovna_test_options'][ Tracking_Settings::OPTION_NAME ] = $settings;
		$GLOBALS['balikovna_test_options'][ Status_Dictionary::OPTION_NAME ] = array(
			'updated_at' => time(),
			'statuses'   => $dictionary,
			'last_error' => array(),
		);

		ob_start();
		( new Tracking_Admin( new Tracking() ) )->render_panel();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/mapping_groups[^>]+>.*?<option value="wc-completed" selected="selected">/s', $output );
	}
}