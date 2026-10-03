<?php

use PHPUnit\Framework\TestCase;

final class ChangelogScriptsTest extends TestCase {
	private $path;

	protected function setUp(): void {
		$this->path = tempnam( sys_get_temp_dir(), 'balikovna-changelog' );
	}

	protected function tearDown(): void {
		if ( is_file( $this->path ) ) {
			unlink( $this->path );
		}
	}

	private function normalize( $changelog ) {
		file_put_contents( $this->path, $changelog );
		$process = proc_open(
			array( PHP_BINARY, dirname( __DIR__ ) . '/.github/scripts/normalize-changelog.php', $this->path ),
			array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output );
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
}
