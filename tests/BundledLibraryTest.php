<?php

use PHPUnit\Framework\TestCase;

final class BundledLibraryTest extends TestCase {
	public function test_scoped_parser_cannot_inject_attributes_or_active_markup(): void {
		require_once dirname( __DIR__ ) . '/includes/lib/plugin-update-checker/vendor/Parsedown.php';
		$parser = new Balikovna_WC\Vendor\Parsedown();
		$parser->setSafeMode( true );
		$this->assertSame( '1.8.0', $parser::version );
		foreach ( array( '![Audit" data-audit="injected](https://example.test/image.png)', '[link](javascript:alert(1))', '<script>alert(1)</script>' ) as $markdown ) {
			$html = $parser->text( $markdown );
			$document = new DOMDocument();
			$document->loadHTML( $html );
			$xpath = new DOMXPath( $document );
			$this->assertSame( 0, $xpath->query( '//*[@data-audit or @onerror] | //script | //a[starts-with(@href,"javascript:")]' )->length, $html );
		}
	}

	public function test_bundled_parser_and_license_match_locked_source(): void {
		$root = dirname( __DIR__ );
		$path = $root . '/includes/lib/plugin-update-checker/vendor/';
		$manifest = json_decode( file_get_contents( $path . 'parsedown-manifest.json' ), true );
		$lock = json_decode( file_get_contents( $root . '/composer.lock' ), true );
		$package = array_values( array_filter( $lock['packages-dev'], function ( $candidate ) { return 'erusev/parsedown' === $candidate['name']; } ) )[0];
		$this->assertSame( $package['version'], $manifest['version'] );
		$this->assertSame( $package['source']['reference'], $manifest['reference'] );
		$this->assertSame( $manifest['upstream_sha256'], hash_file( 'sha256', $root . '/vendor/erusev/parsedown/Parsedown.php' ) );
		$this->assertSame( $manifest['scoped_sha256'], hash_file( 'sha256', $path . 'ParsedownModern.php' ) );
		$this->assertSame( file_get_contents( $root . '/vendor/erusev/parsedown/LICENSE.txt' ), file_get_contents( $path . 'parsedown-license.txt' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_updater_ignores_an_already_loaded_foreign_global_parser(): void {
		if ( ! class_exists( 'Parsedown', false ) ) {
			eval( 'class Parsedown { public static function instance() { throw new RuntimeException("Foreign parser used"); } }' );
		}
		require_once dirname( __DIR__ ) . '/includes/lib/plugin-update-checker/plugin-update-checker.php';
		$api = new class extends \YahnisElsts\PluginUpdateChecker\v5p7\Vcs\GitHubApi {
			public function __construct() {}
			protected function findChangelogName( $directory = null ) { return 'CHANGELOG.md'; }
			public function getRemoteFile( $path, $ref = 'master' ) { return '![Audit" data-audit="injected](https://example.test/image.png)'; }
		};
		$html = $api->getRemoteChangelog( 'main', dirname( __DIR__ ) );
		$document = new DOMDocument();
		$document->loadHTML( $html );
		$this->assertSame( 0, ( new DOMXPath( $document ) )->query( '//*[@data-audit]' )->length );
	}
}