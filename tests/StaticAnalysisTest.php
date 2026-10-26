<?php

use PHPUnit\Framework\TestCase;

final class StaticAnalysisTest extends TestCase {
	public function test_configured_analysis_rejects_a_real_argument_type_error(): void {
		$path = tempnam( sys_get_temp_dir(), 'balikovna-phpstan-' );
		try {
			file_put_contents( $path, "<?php\nstrlen( array() );\n" );
			$root = dirname( __DIR__ );
			$process = proc_open(
				array( PHP_BINARY, $root . '/vendor/phpstan/phpstan/phpstan', 'analyse', '--configuration=' . $root . '/phpstan.neon.dist', '--no-progress', '--error-format=json', '--memory-limit=1G', $path ),
				array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
				$pipes,
				$root
			);
			$this->assertIsResource( $process );
			$output = stream_get_contents( $pipes[1] );
			$errors = stream_get_contents( $pipes[2] );
			fclose( $pipes[1] );
			fclose( $pipes[2] );
			$this->assertSame( 1, proc_close( $process ), $errors . $output );
			$result = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
			$identifiers = array();
			foreach ( $result['files'] as $file ) {
				foreach ( $file['messages'] as $message ) {
					$identifiers[] = $message['identifier'];
				}
			}
			$this->assertContains( 'argument.type', $identifiers, $errors . $output );
		} finally {
			unlink( $path );
		}
	}
}
