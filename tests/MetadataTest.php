<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class MetadataTest extends TestCase {
	private function rootPath( $path ) {
		return dirname( __DIR__ ) . '/' . $path;
	}

	public function test_versions_are_clean_and_consistent(): void {
		$plugin = file_get_contents( $this->rootPath( 'balikovna-woocommerce.php' ) );
		$readme = file_get_contents( $this->rootPath( 'readme.txt' ) );
		preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $plugin, $header );
		preg_match( "/BALIKOVNA_WC_VERSION',\s*'([^']+)'/", $plugin, $constant );
		preg_match( '/^Stable tag:\s*(.+)$/mi', $readme, $stable );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', trim( $header[1] ) );
		$this->assertSame( trim( $header[1] ), $constant[1] );
		$this->assertSame( trim( $header[1] ), trim( $stable[1] ) );
		$this->assertStringContainsString( 'Requires at least: 6.9', $plugin );
		$this->assertStringContainsString( 'Requires at least: 6.9', $readme );
	}

	public function test_updater_requires_the_release_asset(): void {
		$plugin = file_get_contents( $this->rootPath( 'balikovna-woocommerce.php' ) );
		$this->assertStringContainsString( 'Api::REQUIRE_RELEASE_ASSETS', $plugin );
	}

	public function test_blocks_bridge_has_cart_schema_and_update_callback(): void {
		$php = file_get_contents( $this->rootPath( 'includes/class-balikovna-blocks.php' ) );
		$js  = file_get_contents( $this->rootPath( 'assets/js/checkout-block.js' ) );
		$this->assertStringContainsString( 'CartSchema::IDENTIFIER', $php );
		$this->assertStringContainsString( 'register_update_callback', $php );
		$this->assertStringContainsString( "did_action( 'woocommerce_blocks_loaded' )", $php );
		$this->assertStringContainsString( 'packageKey', $js );
		$this->assertStringContainsString( 'event.source !== activeModal.iframe.contentWindow', $js );
		$this->assertStringContainsString( "[ 'pickerResult', 'pickResult' ].indexOf( data.message )", $js );
		$this->assertStringContainsString( "phone: String( data.phone || '' ).trim()", $js );
		$this->assertStringContainsString( 'setPageInert( wrap )', $js );
		$this->assertStringContainsString( 'activeModal === modal', $js );
		$this->assertStringContainsString( 'activeModal.saving && ! force', $js );
		$this->assertStringContainsString( "! has_block( 'woocommerce/checkout' )", $php );
		$checkout = file_get_contents( $this->rootPath( 'includes/class-balikovna-checkout.php' ) );
		$this->assertStringContainsString( 'woocommerce_after_checkout_validation', $checkout );
		$this->assertStringContainsString( 'woocommerce_cart_emptied', $checkout );
		$this->assertStringNotContainsString( 'woocommerce_checkout_order_processed', $checkout );
		$classic_js = file_get_contents( $this->rootPath( 'assets/js/checkout.js' ) );
		$plugin     = file_get_contents( $this->rootPath( 'includes/class-balikovna-plugin.php' ) );
		$this->assertStringContainsString( "['pickerResult', 'pickResult'].indexOf(data.message)", $classic_js );
		$this->assertStringContainsString( "phone: String(data.phone || '').trim()", $classic_js );
		$this->assertStringContainsString( "'messageId'    => self::WIDGET_MESSAGE_ID", $plugin );
		$this->assertStringContainsString( 'setPageInert($wrap[0])', $classic_js );
		$this->assertStringContainsString( 'modal === active', $classic_js );
	}

	public function test_blocks_schema_exposes_selection_phone(): void {
		$schema = ( new Balikovna_WC\Blocks() )->schema_callback();
		$this->assertSame(
			array( 'type' => 'string' ),
			$schema['selections']['items']['properties']['phone']
		);
	}

	public function test_plus_and_manual_tracking_are_registered(): void {
		$plugin = file_get_contents( $this->rootPath( 'includes/class-balikovna-plugin.php' ) );
		$order  = file_get_contents( $this->rootPath( 'includes/class-balikovna-order.php' ) );
		$this->assertStringContainsString( "methods['balikovna_plus']", $plugin );
		$this->assertStringContainsString( "woocommerce_process_shop_order_meta', array( \$this, 'save_tracking_numbers' ), 35", $order );
		$this->assertStringContainsString( "'parcelNumbers'", $order );
	}

	public function test_workflows_are_valid_and_actions_are_immutable(): void {
		foreach ( array( '.github/workflows/ci.yml', '.github/workflows/release-please.yml' ) as $path ) {
			$contents = file_get_contents( $this->rootPath( $path ) );
			$this->assertIsArray( Yaml::parse( $contents ) );
			preg_match_all( '/uses:\s*[^@\s]+@([^\s#]+)/', $contents, $matches );
			foreach ( $matches[1] as $reference ) {
				$this->assertMatchesRegularExpression( '/^[0-9a-f]{40}$/', $reference );
			}
			$this->assertStringNotContainsString( 'RELEASE_PLEASE_TOKEN', $contents );
			$this->assertStringNotContainsString( '--clobber', $contents );
		}
		$release = file_get_contents( $this->rootPath( '.github/workflows/release-please.yml' ) );
		$this->assertStringContainsString( 'path: source', $release );
		$this->assertStringContainsString( 'path: tooling', $release );
		$this->assertStringContainsString( 'build-plugin.php source', $release );
		$ci = file_get_contents( $this->rootPath( '.github/workflows/ci.yml' ) );
		$this->assertStringNotContainsString( 'diff -u readme.txt', $ci );
	}

	public function test_translation_template_is_not_empty(): void {
		$pot = file_get_contents( $this->rootPath( 'languages/balikovna-wc.pot' ) );
		$this->assertSame( 1, preg_match( '/^msgid "(?!")/m', $pot ) );
	}

	public function test_changelog_does_not_repeat_commit_links(): void {
		$changelog = file_get_contents( $this->rootPath( 'CHANGELOG.md' ) );
		preg_match_all( '~/commit/([0-9a-f]{7,40})~', $changelog, $matches );
		$this->assertSame( count( array_unique( $matches[1] ) ), count( $matches[1] ) );
	}
}
