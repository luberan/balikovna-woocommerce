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
		$this->assertMatchesRegularExpression( '/^ \* Requires Plugins: woocommerce\r?$/m', $plugin );
		$this->assertMatchesRegularExpression( '/^Tested up to: 7\.1\r?$/m', $readme );
		$this->assertStringContainsString( '| WordPress | 6.9 | 7.1 |', file_get_contents( $this->rootPath( 'README.md' ) ) );
		$this->assertStringContainsString( 'WC requires at least: 10.8', $plugin );
		$this->assertStringContainsString( 'WC tested up to: 11.1', $plugin );
		$this->assertStringContainsString( 'WC requires at least: 10.8', $readme );
		$this->assertStringContainsString( 'WC tested up to: 11.1', $readme );
		$this->assertStringContainsString( '| WooCommerce | 10.8 | 11.1.0 |', file_get_contents( $this->rootPath( 'README.md' ) ) );
		$versions = json_decode( file_get_contents( $this->rootPath( 'tests/versions.json' ) ), true );
		$composer = json_decode( file_get_contents( $this->rootPath( 'composer.json' ) ), true );
		$this->assertSame( '7.1', $versions['wordpress'] );
		$this->assertSame( $versions['woocommerce'], $composer['require-dev']['php-stubs/woocommerce-stubs'] );
		$this->assertSame( $versions['wordpress'] . '.0', $composer['require-dev']['php-stubs/wordpress-stubs'] );
	}

	public function test_updater_requires_the_release_asset(): void {
		$plugin  = file_get_contents( $this->rootPath( 'balikovna-woocommerce.php' ) );
		$updater = file_get_contents( $this->rootPath( 'includes/class-balikovna-update-checker.php' ) );
		$this->assertStringContainsString( 'Update_Checker::init()', $plugin );
		$this->assertStringContainsString( 'Api::REQUIRE_RELEASE_ASSETS', $updater );
		$this->assertStringContainsString( "\$checker->getUniqueName( 'vcs_update_detection_strategies' )", $updater );
		$this->assertStringContainsString( "array_intersect_key( (array) \$strategies, array( 'latest_release' => true ) )", $updater );
	}

	public function test_updater_never_falls_back_to_source_archives(): void {
		require_once $this->rootPath( 'includes/lib/plugin-update-checker/plugin-update-checker.php' );
		require_once $this->rootPath( 'includes/class-balikovna-update-checker.php' );
		$filter_name = 'balikovna_test_release_strategies';
		remove_all_filters( $filter_name );
		add_filter( $filter_name, array( Balikovna_WC\Update_Checker::class, 'filter_strategies' ) );
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
		$this->assertStringContainsString( "woocommerce_blocks_checkout_block_registration', array( \$this, 'register_integration' )", $php );
		$this->assertStringContainsString( "woocommerce_blocks_cart_block_registration', array( \$this, 'register_integration' )", $php );
		$this->assertStringNotContainsString( 'has_block(', $php );
		$this->assertStringContainsString( "wc.wcSettings.getSetting( NS + '_data', null )", $js );
		$this->assertStringNotContainsString( 'BalikovnaWCBlock', $js );
		$checkout = file_get_contents( $this->rootPath( 'includes/class-balikovna-checkout.php' ) );
		$this->assertStringNotContainsString( 'has_block(', $checkout );
		$this->assertStringContainsString( "'woocommerce_before_checkout_form', array( \$this, 'enqueue_classic_script' )", $checkout );
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

	public function test_deactivation_hooks_stop_scheduled_work_across_the_network(): void {
		$plugin = file_get_contents( $this->rootPath( 'balikovna-woocommerce.php' ) );

		$this->assertStringContainsString( "'deactivate_woocommerce/woocommerce.php'", $plugin );
		$this->assertStringContainsString( 'Cleanup::woocommerce_deactivated( (bool) $network_wide )', $plugin );
		$this->assertStringContainsString( 'Cleanup::deactivate( (bool) $network_wide )', $plugin );
	}

	public function test_php_compatibility_check_targets_the_minimum_php_version(): void {
		$ruleset = simplexml_load_file( $this->rootPath( 'phpcs.xml.dist' ) );
		$configs = array();
		foreach ( $ruleset->config as $config ) {
			$configs[ (string) $config['name'] ] = (string) $config['value'];
		}

		// PHPCS ignores config elements nested inside a rule.
		$this->assertSame( '7.4-', $configs['testVersion'] ?? null );
		$this->assertCount( 0, $ruleset->xpath( '/ruleset/exclude-pattern' ), 'The bundled library stays in the PHP compatibility scan.' );
		$this->assertCount( 0, $ruleset->xpath( '/ruleset/rule[@ref="PHPCompatibilityWP"]/exclude-pattern' ) );
	}

	public function test_workflows_are_valid_and_actions_are_immutable(): void {
		foreach ( array( '.github/workflows/ci.yml', '.github/workflows/release-please.yml', '.github/workflows/points-contract.yml', '.github/workflows/dependabot-automerge.yml' ) as $path ) {
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

	public function test_changelog_does_not_repeat_entries(): void {
		$changelog = file_get_contents( $this->rootPath( 'CHANGELOG.md' ) );
		// Nested commits list several entries with one commit link; each entry appears once.
		preg_match_all( '~^\* .*/commit/[0-9a-f]{7,40}.*$~m', $changelog, $matches );
		$entries = array_map( 'trim', $matches[0] );
		$this->assertNotEmpty( $entries );
		$this->assertSame( count( array_unique( $entries ) ), count( $entries ) );
	}

	public function test_ci_runs_real_target_versions_and_bundled_dependency_checks(): void {
		$ci = Yaml::parse( file_get_contents( $this->rootPath( '.github/workflows/ci.yml' ) ) );
		$integration = $ci['jobs']['integration'];
		$this->assertSame( array( '7.4', '8.5' ), $integration['strategy']['matrix']['php'] );
		$this->assertSame( array( 'cpt', 'hpos' ), $integration['strategy']['matrix']['storage'] );
		$this->assertSame( 'mariadb:11.4.9', $integration['services']['database']['image'] );
		$commands = implode( "\n", array_column( $integration['steps'], 'run' ) );
		$this->assertStringContainsString( 'setup-integration.php', $commands );
		$this->assertStringContainsString( 'phpunit.integration.xml.dist', $commands );
		$this->assertStringContainsString( 'npm run test:e2e', $commands );
		$this->assertStringContainsString( 'vendor-parsedown.php --check', implode( "\n", array_column( $ci['jobs']['quality']['steps'], 'run' ) ) );
	}

	public function test_dependabot_updates_all_ecosystems_daily_without_grouping_major_updates(): void {
		$config = Yaml::parse( file_get_contents( $this->rootPath( '.github/dependabot.yml' ) ) );
		$this->assertSame( 2, $config['version'] );
		$this->assertSame( array( 'github-actions', 'composer', 'npm' ), array_column( $config['updates'], 'package-ecosystem' ) );
		foreach ( $config['updates'] as $update ) {
			$this->assertSame( '/', $update['directory'] );
			$this->assertSame( 'daily', $update['schedule']['interval'] );
			$this->assertSame( 'Europe/Prague', $update['schedule']['timezone'] );
			foreach ( $update['groups'] as $group ) {
				$this->assertSame( array( 'minor', 'patch' ), $group['update-types'] );
			}
		}
	}

	public function test_required_ci_gate_checks_all_jobs_even_after_failure(): void {
		$ci = Yaml::parse( file_get_contents( $this->rootPath( '.github/workflows/ci.yml' ) ) );
		$gate = $ci['jobs']['ci-result'];
		$this->assertArrayHasKey( 'workflow_dispatch', $ci['on'] );
		$this->assertSame( 'CI result', $gate['name'] );
		$this->assertSame( 'always()', $gate['if'] );
		$this->assertSame( array( 'php-lint', 'quality', 'integration' ), $gate['needs'] );
		$this->assertSame(
			array(
				'PHP_LINT' => '${{ needs.php-lint.result }}',
				'QUALITY' => '${{ needs.quality.result }}',
				'INTEGRATION' => '${{ needs.integration.result }}',
			),
			$gate['steps'][0]['env']
		);
		$this->assertStringContainsString( 'exit 1', $gate['steps'][0]['run'] );
	}

	public function test_privileged_dependabot_workflow_only_runs_trusted_base_code(): void {
		$workflow = Yaml::parse( file_get_contents( $this->rootPath( '.github/workflows/dependabot-automerge.yml' ) ) );
		$this->assertArrayHasKey( 'pull_request_target', $workflow['on'] );
		$job = $workflow['jobs']['auto-merge'];
		$this->assertStringContainsString( "github.event.pull_request.user.login == 'dependabot[bot]'", $job['if'] );
		$this->assertStringContainsString( 'github.event.pull_request.head.repo.full_name == github.repository', $job['if'] );
		$this->assertSame( '${{ github.event.pull_request.base.sha }}', $job['steps'][0]['with']['ref'] );
		$this->assertFalse( $job['steps'][0]['with']['persist-credentials'] );
		$this->assertStringContainsString( '--disable', $job['steps'][1]['run'] );
		$this->assertArrayNotHasKey( 'if', $job['steps'][1] );
		$this->assertSame( "github.actor == 'dependabot[bot]'", $job['steps'][2]['if'] );
		$this->assertTrue( $workflow['concurrency']['cancel-in-progress'] );
	}

	public function test_release_changelog_and_ci_are_prepared_before_manual_merge(): void {
		$release = Yaml::parse( file_get_contents( $this->rootPath( '.github/workflows/release-please.yml' ) ) );
		$this->assertNotEmpty( $release['on']['schedule'] );
		$this->assertFalse( $release['concurrency']['cancel-in-progress'] );
		$this->assertSame( 'write', $release['jobs']['release-please']['permissions']['actions'] );
		$commands = implode( "\n", array_column( $release['jobs']['release-please']['steps'], 'run' ) );
		$this->assertStringContainsString( 'changelog-to-readme.php CHANGELOG.md readme.txt', $commands );
		$this->assertStringContainsString( 'git push origin "HEAD:$RELEASE_BRANCH"', $commands );
		$this->assertStringContainsString( 'gh workflow run ci.yml', $commands );
		$this->assertStringNotContainsString( '[skip ci]', $commands );
		$this->assertStringNotContainsString( 'gh pr merge', $commands );
		$this->assertStringNotContainsString( 'git push', implode( "\n", array_column( $release['jobs']['build-zip']['steps'], 'run' ) ) );
	}

	public function test_directory_incidents_are_serialized_and_report_both_failure_and_recovery(): void {
		$workflow = Yaml::parse( file_get_contents( $this->rootPath( '.github/workflows/points-contract.yml' ) ) );
		$this->assertFalse( $workflow['concurrency']['cancel-in-progress'] );
		$report = $workflow['jobs']['report'];
		$this->assertSame( 'directory', $report['needs'] );
		$this->assertStringContainsString( "needs.directory.result == 'success'", $report['if'] );
		$this->assertStringContainsString( "needs.directory.result == 'failure'", $report['if'] );
		$this->assertSame( 'write', $report['permissions']['issues'] );
		$this->assertArrayNotHasKey( 'issues', $workflow['permissions'] );
	}
}
