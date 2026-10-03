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

	public function test_offered_release_notes_are_shown_above_the_tag_history(): void {
		$this->offer( '9.1.0', '<h2>9.1.0 (2026-10-03)</h2><ul><li>New fix</li></ul>' );

		$changelog = $this->info( '9.1.0', "<h4>9.0.0</h4>\n<ul><li>Older</li></ul>" )->sections['changelog'];

		$this->assertStringStartsWith( '<h2>9.1.0 (2026-10-03)</h2>', $changelog );
		$this->assertStringContainsString( '<h4>9.0.0</h4>', $changelog );
	}

	public function test_history_that_already_contains_the_offered_version_is_kept(): void {
		$this->offer( '9.2.0', '<h2><a href="https://example.test/compare">9.2.0</a> (2026-10-03)</h2>' );
		$history = "<h4>9.2.0</h4>\n<ul><li>Synced</li></ul>";

		$this->assertSame( $history, $this->info( '9.2.0', $history )->sections['changelog'] );
	}

	public function test_missing_remote_history_falls_back_to_the_installed_readme(): void {
		$notes = '<h2>9.3.0</h2><ul><li>Release body only</li></ul>';
		$this->offer( '9.3.0', $notes );

		foreach ( array( null, $notes, 'There is no changelog available.' ) as $remote ) {
			$changelog = $this->info( '9.3.0', $remote )->sections['changelog'];
			$this->assertStringStartsWith( $notes, $changelog );
			$this->assertMatchesRegularExpression( '#<h4>\d+\.\d+\.\d+</h4>#', $changelog, 'Installed readme history follows the release notes.' );
			$this->assertSame( 1, substr_count( $changelog, 'Release body only' ) );
		}
	}

	public function test_unrelated_info_is_returned_unchanged(): void {
		$this->assertSame( 'not-an-object', Update_Checker::filter_info( 'not-an-object' ) );
		$this->assertSame( '<h4>9.0.0</h4>', $this->info( '9.9.9', '<h4>9.0.0</h4>' )->sections['changelog'], 'Notes of another version are never attached.' );
	}
}
