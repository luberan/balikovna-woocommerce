<?php

use PHPUnit\Framework\TestCase;

final class BuildPluginTest extends TestCase {
	private $root;

	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/balikovna-package-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->root );
		$this->write( 'balikovna-woocommerce.php', "<?php\n/*\nVersion: 1.2.3 <!-- x-release-please-version -->\n*/\ndefine( 'BALIKOVNA_WC_VERSION', '1.2.3' );\n" );
		$this->write( 'readme.txt', "=== Fixture ===\nStable tag: 1.2.3 <!-- x-release-please-version -->\n\n== Changelog ==\n* Stale entry.\n" );
		$this->write( 'CHANGELOG.md', "# Changelog\n\n## [1.2.3](https://example.test/v1.2.3) (2026-10-26)\n\n### Fixes\n\n* Packaged fix\n" );
		foreach ( array( 'uninstall.php', 'LICENSE', 'README.md', 'assets/js/fixture.js', 'languages/fixture.pot' ) as $path ) {
			$this->write( $path, 'Fixture: ' . $path );
		}
		$vendor = 'includes/lib/plugin-update-checker/vendor/';
		$this->write( $vendor . 'Parsedown.php', "<?php\n" );
		$this->write( $vendor . 'ParsedownModern.php', "<?php\n// Scoped parser fixture.\n" );
		$this->write( $vendor . 'parsedown-license.txt', 'Fixture parser license' );
		$this->write( $vendor . 'parsedown-manifest.json', json_encode( array( 'version' => '1.8.0', 'scoped_sha256' => hash_file( 'sha256', $this->root . '/' . $vendor . 'ParsedownModern.php' ) ) ) );
		foreach ( array( '.git/config', '.github/workflows/private.yml', 'vendor/development.php', 'node_modules/development.js', 'tests/development.php', 'build/phpstan/cache.php' ) as $path ) {
			$this->write( $path, 'Development-only fixture' );
		}
	}

	protected function tearDown(): void {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $entry ) {
			if ( $entry->isDir() ) {
				rmdir( $entry->getPathname() );
			} else {
				unlink( $entry->getPathname() );
			}
		}
		rmdir( $this->root );
	}

	private function write( $path, $contents ): void {
		$path = $this->root . '/' . $path;
		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0777, true );
		}
		file_put_contents( $path, $contents );
	}

	private function build(): array {
		$process = proc_open(
			array( PHP_BINARY, dirname( __DIR__ ) . '/.github/scripts/build-plugin.php', $this->root ),
			array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		$this->assertIsResource( $process );
		$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		return array( proc_close( $process ), $output );
	}

	public function test_distribution_zip_contains_the_complete_normalized_release_and_no_development_files(): void {
		$source_readme = file_get_contents( $this->root . '/readme.txt' );
		$source_changelog = file_get_contents( $this->root . '/CHANGELOG.md' );
		list( $status, $output ) = $this->build();
		$this->assertSame( 0, $status, $output );
		$archive = new ZipArchive();
		$this->assertTrue( $archive->open( $this->root . '/build/balikovna-woocommerce.zip' ) );
		try {
			for ( $index = 0; $index < $archive->numFiles; ++$index ) {
				$this->assertStringStartsWith( 'balikovna-woocommerce/', $archive->getNameIndex( $index ) );
			}
			$readme = $archive->getFromName( 'balikovna-woocommerce/readme.txt' );
			$this->assertStringContainsString( "Stable tag: 1.2.3\n", $readme );
			$this->assertStringContainsString( "= 1.2.3 =\n* Fixes: Packaged fix", $readme );
			$this->assertStringNotContainsString( 'Stale entry', $readme );
			$this->assertStringNotContainsString( 'x-release-please-version', $archive->getFromName( 'balikovna-woocommerce/balikovna-woocommerce.php' ) );
			$this->assertSame( 'Fixture: LICENSE', $archive->getFromName( 'balikovna-woocommerce/LICENSE' ) );
			$this->assertSame( 'Fixture parser license', $archive->getFromName( 'balikovna-woocommerce/includes/lib/plugin-update-checker/vendor/parsedown-license.txt' ) );
			foreach ( array( '.git/config', '.github/workflows/private.yml', 'vendor/development.php', 'node_modules/development.js', 'tests/development.php', 'build/phpstan/cache.php' ) as $path ) {
				$this->assertFalse( $archive->getFromName( 'balikovna-woocommerce/' . $path ), $path );
			}
		} finally {
			$archive->close();
		}
		$this->assertSame( $source_readme, file_get_contents( $this->root . '/readme.txt' ) );
		$this->assertSame( $source_changelog, file_get_contents( $this->root . '/CHANGELOG.md' ) );
		$this->assertSame( array(), glob( $this->root . '/build/changelog-*' ) );
	}

	public function test_rebuilding_removes_obsolete_files_from_the_staging_directory_and_zip(): void {
		list( $status, $output ) = $this->build();
		$this->assertSame( 0, $status, $output );
		$this->write( 'build/balikovna-woocommerce/obsolete.php', 'Obsolete fixture' );
		list( $status, $output ) = $this->build();
		$this->assertSame( 0, $status, $output );
		$this->assertFileDoesNotExist( $this->root . '/build/balikovna-woocommerce/obsolete.php' );
		$archive = new ZipArchive();
		$this->assertTrue( $archive->open( $this->root . '/build/balikovna-woocommerce.zip' ) );
		$this->assertFalse( $archive->getFromName( 'balikovna-woocommerce/obsolete.php' ) );
		$archive->close();
	}

	public function test_inconsistent_versions_do_not_produce_a_distribution_zip(): void {
		$this->write( 'readme.txt', "Stable tag: 9.0.0\n" );
		list( $status, $output ) = $this->build();
		$this->assertSame( 1, $status, $output );
		$this->assertStringContainsString( 'versions do not match', $output );
		$this->assertFileDoesNotExist( $this->root . '/build/balikovna-woocommerce.zip' );
	}
}
