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
		$this->assertMatchesRegularExpression( '/^Tested up to: 7\.1\r?$/m', $readme );
		$this->assertStringContainsString( '| WordPress | 6.9 | 7.1 |', file_get_contents( $this->rootPath( 'README.md' ) ) );
		$this->assertStringContainsString( 'WC requires at least: 10.8', $plugin );
		$this->assertStringContainsString( 'WC tested up to: 11.0', $plugin );
		$this->assertStringContainsString( 'WC requires at least: 10.8', $readme );
		$this->assertStringContainsString( 'WC tested up to: 11.0', $readme );
	}

	public function test_updater_requires_the_release_asset(): void {
		$plugin = file_get_contents( $this->rootPath( 'balikovna-woocommerce.php' ) );
		$this->assertStringContainsString( 'Api::REQUIRE_RELEASE_ASSETS', $plugin );
		$this->assertStringContainsString( "\$checker->getUniqueName( 'vcs_update_detection_strategies' )", $plugin );
		$this->assertStringContainsString( "array_intersect_key( \$strategies, array( 'latest_release' => true ) )", $plugin );
	}

	public function test_updater_never_falls_back_to_source_archives(): void {
		require_once $this->rootPath( 'includes/lib/plugin-update-checker/plugin-update-checker.php' );
		$plugin = file_get_contents( $this->rootPath( 'balikovna-woocommerce.php' ) );
		$this->assertSame( 1, preg_match( '/function \( \$strategies \) \{[^}]+\}/', $plugin, $matches ) );
		$filter = eval( 'return ' . $matches[0] . ';' );
		$filter_name = 'balikovna_test_release_strategies';
		remove_all_filters( $filter_name );
		add_filter( $filter_name, $filter );
		$api = new class() extends \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\GitHubApi {
			public $release;
			public $requests = array();
			public function __construct() {}
			protected function api( $url, $queryParams = array() ) {
				$this->requests[] = $url;
				if ( '/repos/:user/:repo/releases/latest' !== $url ) {
					throw new RuntimeException( 'Unexpected source archive fallback: ' . $url );
				}
				return $this->release;
			}
		};
		$api->enableReleaseAssets( '/^balikovna-woocommerce\.zip$/i', \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS );
		$api->setStrategyFilterName( $filter_name );
		$api->release = (object) array(
			'tag_name' => 'v9.0.0', 'zipball_url' => 'https://example.test/source.zip',
			'created_at' => '2026-09-07T00:00:00Z', 'assets' => array(),
		);
		$this->assertNull( $api->chooseReference( 'main' ) );
		$asset = (object) array( 'name' => 'other.zip', 'browser_download_url' => 'https://example.test/balikovna-woocommerce.zip', 'download_count' => 0 );
		$api->release->assets = array( $asset );
		$this->assertNull( $api->chooseReference( 'main' ) );
		$asset->name = 'balikovna-woocommerce.zip';
		$this->assertSame( $asset->browser_download_url, $api->chooseReference( 'main' )->downloadUrl );
		$api->release = new WP_Error( 'unavailable', 'Release unavailable' );
		$this->assertNull( $api->chooseReference( 'main' ) );
		$this->assertCount( 4, $api->requests );
		remove_all_filters( $filter_name );
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

	public function test_ci_checks_all_first_party_javascript(): void {
		$ci = file_get_contents( $this->rootPath( '.github/workflows/ci.yml' ) );

		$this->assertStringContainsString( 'find assets/js -type f -name "*.js" -print0', $ci );
		$this->assertStringContainsString( 'xargs -0 -n1 node --check', $ci );
		$this->assertStringNotContainsString( 'node --check assets/js/checkout.js', $ci );
	}

	public function test_classic_checkout_uses_stored_row_error_reference(): void {
		$js = file_get_contents( $this->rootPath( 'assets/js/checkout.js' ) );

		$this->assertStringContainsString( '$rowError: $rowError', $js );
		$this->assertStringContainsString( "active.\$rowError.prop('hidden', true).empty()", $js );
		$this->assertStringContainsString( 'active.$rowError[0]', $js );
		$this->assertStringNotContainsString( 'active.rowError', $js );
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

	public function test_tracking_scheduler_cleans_up_when_woocommerce_is_deactivated(): void {
		$plugin = file_get_contents( $this->rootPath( 'balikovna-woocommerce.php' ) );

		$this->assertStringContainsString( "'deactivate_woocommerce/woocommerce.php'", $plugin );
		$this->assertStringContainsString( 'Tracking_Scheduler::unschedule()', $plugin );
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
