<?php

use Balikovna_WC\Credentials;
use Balikovna_WC\Tracking_Settings;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['balikovna_test_options'] = array();
		$GLOBALS['wpdb'] = new Balikovna_Test_Lock_Database();
	}

	protected function tearDown(): void {
		foreach ( array( 'BALIKOVNA_WC_API_TOKEN', 'BALIKOVNA_WC_SECRET_KEY', 'BALIKOVNA_WC_ENCRYPTION_KEY' ) as $name ) { putenv( $name ); }
	}

	public function test_credentials_are_encrypted_randomized_and_bound_to_the_field(): void {
		$settings = array_merge( Tracking_Settings::defaults(), array( 'api_token' => 'private-token', 'secret_key' => 'private-secret' ) );
		$this->assertTrue( Tracking_Settings::save( $settings ) );
		$first = get_option( Tracking_Settings::OPTION_NAME );
		$this->assertStringNotContainsString( 'private', json_encode( $first ) );
		$this->assertSame( $settings, Tracking_Settings::get() );
		$this->assertInstanceOf( WP_Error::class, Credentials::decrypt( $first['secret_key'], 'api_token' ) );
		Tracking_Settings::save( $settings );
		$this->assertNotSame( $first['secret_key'], get_option( Tracking_Settings::OPTION_NAME )['secret_key'] );
	}

	public function test_migration_preserves_settings_without_overwriting_a_concurrent_save(): void {
		$legacy = array_merge( Tracking_Settings::defaults(), array( 'api_token' => 'old-token', 'secret_key' => 'old-secret' ) );
		update_option( Tracking_Settings::OPTION_NAME, $legacy );
		Tracking_Settings::migrate_credentials();
		$this->assertSame( $legacy, Tracking_Settings::get() );
		$this->assertStringStartsWith( Credentials::PREFIX, get_option( Tracking_Settings::OPTION_NAME )['api_token'] );
		update_option( Tracking_Settings::OPTION_NAME, $legacy );
		$new = array_merge( $legacy, array( 'api_token' => 'concurrent-token' ) );
		$GLOBALS['wpdb']->before_query = function () use ( $new ) { update_option( Tracking_Settings::OPTION_NAME, $new ); };
		Tracking_Settings::migrate_credentials();
		$this->assertSame( $new, get_option( Tracking_Settings::OPTION_NAME ) );
	}

	public function test_external_credentials_override_storage_and_are_never_persisted(): void {
		putenv( 'BALIKOVNA_WC_API_TOKEN=external-token' );
		putenv( 'BALIKOVNA_WC_SECRET_KEY=external-secret' );
		$settings = Tracking_Settings::get();
		$this->assertSame( 'external-secret', $settings['secret_key'] );
		Tracking_Settings::save( $settings );
		$this->assertSame( '', get_option( Tracking_Settings::OPTION_NAME )['secret_key'] );
		$this->assertSame( 'external-token', Tracking_Settings::get()['api_token'] );
		ob_start();
		( new Balikovna_WC\Tracking_Admin( new Balikovna_WC\Tracking() ) )->render_panel();
		$html = ob_get_clean();
		$this->assertMatchesRegularExpression( '/id="balikovna-api-token"[^>]* disabled/', $html );
		$this->assertMatchesRegularExpression( '/id="balikovna-secret-key"[^>]* disabled/', $html );
		$this->assertStringNotContainsString( 'external-secret', $html );
	}

	public function test_wrong_key_fails_closed_and_invalid_key_cannot_store_plaintext(): void {
		Tracking_Settings::save( array( 'api_token' => 'secret-token', 'secret_key' => 'secret-value' ) );
		$stored = get_option( Tracking_Settings::OPTION_NAME );
		putenv( 'BALIKOVNA_WC_ENCRYPTION_KEY=' . base64_encode( str_repeat( 'different-key-', 3 ) ) );
		$this->assertFalse( Tracking_Settings::is_configured( Tracking_Settings::get() ) );
		$this->assertInstanceOf( WP_Error::class, Tracking_Settings::save( array( 'api_token' => 'must-not-persist' ) ) );
		$this->assertSame( $stored, get_option( Tracking_Settings::OPTION_NAME ) );
	}

	public function test_key_rotation_keeps_unreadable_ciphertext_until_explicit_replacement(): void {
		Tracking_Settings::save( array( 'api_token' => 'secret-token', 'secret_key' => 'secret-value' ) );
		$stored = get_option( Tracking_Settings::OPTION_NAME );
		putenv( 'BALIKOVNA_WC_ENCRYPTION_KEY=' . base64_encode( str_repeat( 'x', 32 ) ) );
		$this->assertInstanceOf( WP_Error::class, Tracking_Settings::credential_error() );
		ob_start();
		( new Balikovna_WC\Tracking_Admin( new Balikovna_WC\Tracking() ) )->render_panel();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'name="balikovna_tracking[clear_secret]"', $html );
		$this->assertStringNotContainsString( $stored['secret_key'], $html );
		Tracking_Settings::save( Tracking_Settings::get() );
		$this->assertSame( $stored['api_token'], get_option( Tracking_Settings::OPTION_NAME )['api_token'] );
		$this->assertSame( $stored['secret_key'], get_option( Tracking_Settings::OPTION_NAME )['secret_key'] );
		Tracking_Settings::save( array( 'api_token' => 'replacement-token', 'secret_key' => '' ), array( 'secret_key' ) );
		$this->assertSame( 'replacement-token', Tracking_Settings::get()['api_token'] );
		$this->assertSame( '', get_option( Tracking_Settings::OPTION_NAME )['secret_key'] );
		$this->assertNull( Tracking_Settings::credential_error() );
	}
}