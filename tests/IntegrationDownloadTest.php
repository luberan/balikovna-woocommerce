<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/.github/scripts/integration-download.php';

final class IntegrationDownloadTest extends TestCase {
	private $directory;

	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/balikovna-download-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->directory );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->directory . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->directory );
	}

	/**
	 * A transfer that fails with the given HTTP statuses and then delivers the archive.
	 */
	private function transfer( array $statuses, array &$attempts ) {
		return function ( $url, $path ) use ( &$statuses, &$attempts ) {
			$attempts[] = $url;
			if ( $statuses ) {
				$status = array_shift( $statuses );
				// A failed transfer leaves an empty or partial file behind.
				file_put_contents( $path, 'partial' );
				return array( false, 'The requested URL returned error: ' . $status, CURLE_HTTP_RETURNED_ERROR, $status );
			}
			file_put_contents( $path, 'archive' );
			return array( true, '', 0, 200 );
		};
	}

	public function test_server_errors_throttling_and_dropped_connections_are_transient(): void {
		foreach ( array( 500, 502, 503, 504, 429, 408 ) as $status ) {
			$this->assertTrue( integration_download_is_transient( CURLE_HTTP_RETURNED_ERROR, $status ), (string) $status );
		}
		foreach ( array( 400, 401, 403, 404, 410 ) as $status ) {
			$this->assertFalse( integration_download_is_transient( CURLE_HTTP_RETURNED_ERROR, $status ), (string) $status );
		}
		foreach ( array( CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_OPERATION_TIMEOUTED, CURLE_PARTIAL_FILE, CURLE_GOT_NOTHING, CURLE_RECV_ERROR ) as $errno ) {
			$this->assertTrue( integration_download_is_transient( $errno, 0 ), (string) $errno );
		}
		$this->assertFalse( integration_download_is_transient( CURLE_UNSUPPORTED_PROTOCOL, 0 ) );
		$this->assertFalse( integration_download_is_transient( CURLE_TOO_MANY_REDIRECTS, 0 ) );
	}

	public function test_gateway_timeout_is_repeated_until_the_archive_arrives(): void {
		$target   = $this->directory . '/plugin.zip';
		$attempts = array();
		$waits    = array();

		integration_download(
			'https://example.test/plugin.zip',
			$target,
			$this->transfer( array( 504, 503 ), $attempts ),
			function ( $seconds, $reason ) use ( &$waits ) {
				$waits[] = array( $seconds, $reason );
			}
		);

		$this->assertSame( 'archive', file_get_contents( $target ) );
		$this->assertCount( 3, $attempts );
		$this->assertSame( array( 2, 5 ), array_column( $waits, 0 ) );
		$this->assertStringContainsString( '504', $waits[0][1] );
		$this->assertFileDoesNotExist( $target . '.part' );
	}

	public function test_persistent_server_error_fails_after_four_attempts(): void {
		$target   = $this->directory . '/plugin.zip';
		$attempts = array();
		$waits    = array();

		try {
			integration_download(
				'https://example.test/plugin.zip',
				$target,
				$this->transfer( array( 504, 504, 504, 504, 504 ), $attempts ),
				function ( $seconds ) use ( &$waits ) {
					$waits[] = $seconds;
				}
			);
			$this->fail( 'A download that keeps failing must stop the setup.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( 'Fixture download failed: The requested URL returned error: 504', $error->getMessage() );
		}

		$this->assertCount( 4, $attempts );
		$this->assertSame( array( 2, 5, 15 ), $waits );
		$this->assertFileDoesNotExist( $target );
		$this->assertFileDoesNotExist( $target . '.part' );
	}

	public function test_missing_archive_fails_at_once(): void {
		$target   = $this->directory . '/plugin.zip';
		$attempts = array();

		try {
			integration_download(
				'https://example.test/plugin.zip',
				$target,
				$this->transfer( array( 404 ), $attempts ),
				function () {
					$this->fail( 'A missing archive is not worth waiting for.' );
				}
			);
			$this->fail( 'A missing archive must stop the setup.' );
		} catch ( RuntimeException $error ) {
			$this->assertStringContainsString( '404', $error->getMessage() );
		}

		$this->assertCount( 1, $attempts );
		$this->assertFileDoesNotExist( $target . '.part' );
	}

	public function test_existing_archive_is_not_downloaded_again(): void {
		$target = $this->directory . '/plugin.zip';
		file_put_contents( $target, 'cached' );

		integration_download(
			'https://example.test/plugin.zip',
			$target,
			function () {
				$this->fail( 'A present archive is reused.' );
			}
		);

		$this->assertSame( 'cached', file_get_contents( $target ) );
	}

	public function test_setup_script_uses_the_shared_download_helper(): void {
		$setup = file_get_contents( dirname( __DIR__ ) . '/.github/scripts/setup-integration.php' );

		$this->assertStringContainsString( "require_once __DIR__ . '/integration-download.php';", $setup );
		$this->assertStringNotContainsString( 'function integration_download(', $setup );
		$this->assertSame( 3, substr_count( $setup, 'integration_download( ' ) );
	}
}
