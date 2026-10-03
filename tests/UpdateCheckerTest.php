<?php

use Balikovna_WC\Update_Checker;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-balikovna-update-checker.php';

final class UpdateCheckerTest extends TestCase {
	private function offer( $version, $notes ) {
		$strategies = Update_Checker::filter_strategies(
			array(
				'branch'         => function () {
					throw new RuntimeException( 'Branches are never used for updates.' );
				},
				'latest_release' => function () use ( $version, $notes ) {
					return (object) array(
						'version'   => $version,
						'changelog' => $notes,
					);
				},
			)
		);
		$this->assertSame( array( 'latest_release' ), array_keys( $strategies ) );
		return call_user_func( $strategies['latest_release'] );
	}

	private function info( $version, $changelog = null ) {
		$info = (object) array(
			'version'  => $version,
			'sections' => array(),
		);
		if ( null !== $changelog ) {
			$info->sections['changelog'] = $changelog;
		}
		return Update_Checker::filter_info( $info );
	}

	/**
	 * Release notes as Plugin Update Checker renders a release-please body.
	 */
	private function release_notes( $version ) {
		$commit = 'https://github.com/luberan/balikovna-woocommerce/commit/3f18758173f24c3240cf4c41137ed79822453c5b';
		return '<h2><a href="https://github.com/luberan/balikovna-woocommerce/compare/v9.0.0...v' . $version . '">' . $version . "</a> (2026-10-03)</h2>\n"
			. "<h3>🐛 Opravy chyb</h3>\n<ul>\n<li>keep the <code>queue</code> moving (<a href=\"" . $commit . "\">3f18758</a>)</li>\n</ul>\n"
			. "<h3>🛠 Build</h3>\n<ul>\n<li>keep nested entries (<a href=\"" . $commit . "\">3f18758</a>)</li>\n</ul>";
	}

	public function test_offered_release_notes_are_shown_above_the_tag_history_in_its_format(): void {
		$this->offer( '9.1.0', $this->release_notes( '9.1.0' ) );

		$changelog = $this->info( '9.1.0', "<h4>9.0.0</h4>\n<ul><li>Older</li></ul>" )->sections['changelog'];

		$this->assertStringStartsWith( "<h4>9.1.0</h4>\n<ul>\n<li>🐛 Opravy chyb: keep the <code>queue</code> moving</li>\n<li>🛠 Build: keep nested entries</li>\n</ul>\n<h4>9.0.0</h4>", $changelog );
		$this->assertDoesNotMatchRegularExpression( '#<h[1-3][\s>]#', $changelog, 'Headings above h4 clear the dialog sidebar.' );
		$this->assertStringNotContainsString( '/commit/', $changelog );
	}

	public function test_notes_in_an_unknown_format_keep_their_content_without_large_headings(): void {
		$this->offer( '9.4.0', '<h2>9.4.0</h2><p>Plain <strong>notes</strong></p>' );

		$changelog = $this->info( '9.4.0', '<h4>9.3.0</h4>' )->sections['changelog'];

		$this->assertSame( "<h4>9.4.0</h4><p>Plain <strong>notes</strong></p>\n<h4>9.3.0</h4>", $changelog );
	}

	public function test_history_that_already_contains_the_offered_version_is_kept(): void {
		$this->offer( '9.2.0', '<h2><a href="https://example.test/compare">9.2.0</a> (2026-10-03)</h2>' );
		$history = "<h4>9.2.0</h4>\n<ul><li>Synced</li></ul>";

		$this->assertSame( $history, $this->info( '9.2.0', $history )->sections['changelog'] );
	}

	public function test_missing_remote_history_falls_back_to_the_installed_readme(): void {
		$notes     = '<h2>9.3.0</h2><ul><li>Release body only</li></ul>';
		$converted = "<h4>9.3.0</h4>\n<ul>\n<li>Release body only</li>\n</ul>";
		$this->offer( '9.3.0', $notes );

		foreach ( array( null, $notes, 'There is no changelog available.' ) as $remote ) {
			$changelog = $this->info( '9.3.0', $remote )->sections['changelog'];
			$this->assertStringStartsWith( $converted, $changelog );
			$this->assertMatchesRegularExpression( '#^\s*<h4>\d+\.\d+\.\d+</h4>\s*<ul>#', substr( $changelog, strlen( $converted ) ), 'Installed readme history follows the release notes.' );
			$this->assertSame( 1, substr_count( $changelog, 'Release body only' ) );
		}
	}

	public function test_unrelated_info_is_returned_unchanged(): void {
		$this->assertSame( 'not-an-object', Update_Checker::filter_info( 'not-an-object' ) );
		$this->assertSame( '<h4>9.0.0</h4>', $this->info( '9.9.9', '<h4>9.0.0</h4>' )->sections['changelog'], 'Notes of another version are never attached.' );
	}
}
