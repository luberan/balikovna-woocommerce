<?php
/**
 * Download of integration fixture archives with retries of transient failures.
 */

declare( strict_types=1 );

/**
 * Whether a failed transfer may succeed when it is repeated.
 *
 * A server or gateway error, throttling and a dropped connection may pass on
 * the next attempt; a missing file or a refused request will not.
 *
 * @param int $errno  cURL error number.
 * @param int $status HTTP status of the last response, 0 without a response.
 */
function integration_download_is_transient( int $errno, int $status ): bool {
	if ( CURLE_HTTP_RETURNED_ERROR === $errno ) {
		return $status >= 500 || 429 === $status || 408 === $status;
	}
	return in_array(
		$errno,
		array(
			CURLE_COULDNT_RESOLVE_HOST,
			CURLE_COULDNT_CONNECT,
			CURLE_OPERATION_TIMEOUTED,
			CURLE_PARTIAL_FILE,
			CURLE_GOT_NOTHING,
			CURLE_SEND_ERROR,
			CURLE_RECV_ERROR,
			CURLE_SSL_CONNECT_ERROR,
		),
		true
	);
}

/**
 * Perform one HTTPS transfer into a file.
 *
 * @return array{0:bool,1:string,2:int,3:int} Success, error message, cURL error number and HTTP status.
 */
function integration_transfer( string $url, string $path ): array {
	$stream = fopen( $path, 'wb' );
	$curl   = curl_init( $url );
	curl_setopt_array(
		$curl,
		array(
			CURLOPT_FILE            => $stream,
			CURLOPT_FOLLOWLOCATION  => true,
			CURLOPT_MAXREDIRS       => 3,
			CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
			CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
			CURLOPT_TIMEOUT         => 180,
			CURLOPT_FAILONERROR     => true,
			CURLOPT_USERAGENT       => 'Balikovna-Integration-Tests',
		)
	);
	$success = (bool) curl_exec( $curl );
	$result  = array( $success, curl_error( $curl ), curl_errno( $curl ), (int) curl_getinfo( $curl, CURLINFO_RESPONSE_CODE ) );
	fclose( $stream );
	return $result;
}

/**
 * Report a failed attempt and wait before the next one.
 */
function integration_wait( int $seconds, string $reason ): void {
	fwrite( STDERR, sprintf( "Fixture download failed (%s); retrying in %d s.\n", $reason, $seconds ) );
	sleep( $seconds );
}

/**
 * Download a fixture archive unless it is already present.
 *
 * Transient failures are repeated three times, after 2, 5 and 15 seconds, so a
 * momentary error of the download host does not fail the whole CI job.
 *
 * @param callable|null $transfer One transfer, see integration_transfer(); replaced in tests.
 * @param callable|null $wait     Pause between attempts, see integration_wait(); replaced in tests.
 */
function integration_download( string $url, string $target, ?callable $transfer = null, ?callable $wait = null ): void {
	if ( is_file( $target ) ) {
		return;
	}
	$transfer = $transfer ?? 'integration_transfer';
	$wait     = $wait ?? 'integration_wait';
	$delays   = array( 2, 5, 15 );
	$part     = $target . '.part';

	for ( $attempt = 0; ; ++$attempt ) {
		list( $success, $error, $errno, $status ) = $transfer( $url, $part );
		if ( $success ) {
			rename( $part, $target );
			return;
		}
		if ( is_file( $part ) ) {
			unlink( $part );
		}
		if ( ! isset( $delays[ $attempt ] ) || ! integration_download_is_transient( (int) $errno, (int) $status ) ) {
			throw new RuntimeException( 'Fixture download failed: ' . $error );
		}
		$wait( $delays[ $attempt ], (string) $error );
	}
}
