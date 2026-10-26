<?php

use PHPUnit\Framework\TestCase;

final class ChangelogScriptsTest extends TestCase {
	private $path;
	private $readmePath;

	protected function setUp(): void {
		$this->path = tempnam( sys_get_temp_dir(), 'balikovna-changelog' );
		$this->readmePath = tempnam( sys_get_temp_dir(), 'balikovna-readme' );
	}

	protected function tearDown(): void {
		foreach ( array( $this->path, $this->readmePath ) as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
	}

	private function runScript( $script, array $arguments ): void {
		$process = proc_open(
			array_merge( array( PHP_BINARY, dirname( __DIR__ ) . '/.github/scripts/' . $script ), $arguments ),
			array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output );
	}

	private function normalize( $changelog ) {
		file_put_contents( $this->path, $changelog );
		$this->runScript( 'normalize-changelog.php', array( $this->path ) );
		return file_get_contents( $this->path );
	}

	private function entry( $text, $hash ) {
		return '* ' . $text . ' ([' . substr( $hash, 0, 7 ) . '](https://github.com/luberan/balikovna-woocommerce/commit/' . $hash . "))\n";
	}

	public function test_nested_commit_entries_are_kept_and_release_loop_duplicates_removed(): void {
		$nested = str_repeat( 'a', 40 );
		$looped = str_repeat( 'b', 40 );

		$normalized = $this->normalize(
			"# Changelog\n\n"
			. "## [1.2.0](https://example.test/compare/v1.1.1...v1.2.0) (2026-10-03)\n\n"
			. "### Features\n\n" . $this->entry( 'add filters', $nested ) . "\n"
			. "### Fixes\n\n" . $this->entry( 'fix the lock', $nested ) . $this->entry( 'fix the queue', $nested ) . "\n"
			. "## [1.1.1](https://example.test/compare/v1.1.0...v1.1.1) (2026-10-02)\n\n"
			. "### Fixes\n\n" . $this->entry( 'fix a loop', $looped ) . "\n"
			. "## [1.1.0](https://example.test/compare/v1.0.0...v1.1.0) (2026-10-01)\n\n"
			. "### Fixes\n\n" . $this->entry( 'fix a loop', $looped )
		);

		foreach ( array( 'add filters', 'fix the lock', 'fix the queue' ) as $text ) {
			$this->assertStringContainsString( '* ' . $text . ' ', $normalized, 'Nested commits share one hash.' );
		}
		$this->assertSame( 1, substr_count( $normalized, '* fix a loop ' ) );
		$this->assertStringNotContainsString( '## [1.1.1]', $normalized, 'A release-loop section without own entries is dropped.' );
		$this->assertMatchesRegularExpression( '/^## \[1\.1\.0\].*\* fix a loop /ms', $normalized, 'A repeated entry stays in its oldest release.' );
	}

	public function test_release_pr_readme_generation_preserves_metadata_and_is_idempotent(): void {
		$this->normalize(
			"# Changelog\n\n## [2.0.0](https://example.test/compare/v1.0.0...v2.0.0) (2026-10-26)\n\n"
			. "### Fixes\n\n" . $this->entry( 'fix checkout', str_repeat( 'a', 40 ) )
			. "\n## [1.0.0](https://example.test/v1.0.0) (2026-10-01)\n\n"
			. "### Features\n\n" . $this->entry( 'initial release', str_repeat( 'b', 40 ) )
		);
		file_put_contents(
			$this->readmePath,
			"=== Plugin ===\nStable tag: 2.0.0\n\n== Description ==\nKeep description.\n\n"
			. "== Changelog ==\n= 0.1.0 =\n* Stale entry.\n\n== Upgrade Notice ==\nKeep notice.\n"
		);
		$this->runScript( 'changelog-to-readme.php', array( $this->path, $this->readmePath ) );
		$readme = file_get_contents( $this->readmePath );
		$this->assertStringContainsString( "Stable tag: 2.0.0\n", $readme );
		$this->assertStringContainsString( "== Description ==\nKeep description.", $readme );
		$this->assertStringContainsString( "== Upgrade Notice ==\nKeep notice.", $readme );
		$this->assertStringContainsString( "= 2.0.0 =\n* Fixes: fix checkout", $readme );
		$this->assertStringContainsString( "= 1.0.0 =\n* Features: initial release", $readme );
		$this->assertStringNotContainsString( 'Stale entry', $readme );
		$this->assertStringNotContainsString( '/commit/', $readme );
		$this->runScript( 'changelog-to-readme.php', array( $this->path, $this->readmePath ) );
		$this->assertSame( $readme, file_get_contents( $this->readmePath ) );
	}
}
