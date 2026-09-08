<?php

declare( strict_types=1 );

require dirname( __DIR__, 2 ) . '/tests/bootstrap.php';
$GLOBALS['wpdb'] = new Balikovna_Test_Lock_Database();
foreach ( array( 'BALIKOVNY' => 'balikovna', 'POST_OFFICE' => 'cp_na_postu' ) as $type => $service ) {
	$body = '';
	$curl = curl_init( Balikovna_WC\Points::API_URL . '?type%5B%5D=' . $type );
	curl_setopt_array(
		$curl,
		array(
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_USERAGENT => 'Balikovna-Contract-Monitor',
			CURLOPT_WRITEFUNCTION => function ( $handle, $chunk ) use ( &$body ) {
				if ( strlen( $body ) + strlen( $chunk ) > 8 * MB_IN_BYTES ) { return 0; }
				$body .= $chunk;
				return strlen( $chunk );
			},
		)
	);
	if ( ! curl_exec( $curl ) || 200 !== curl_getinfo( $curl, CURLINFO_HTTP_CODE ) ) {
		throw new RuntimeException( 'Pickup directory request failed or exceeded 8 MiB: ' . $type . '; HTTP ' . curl_getinfo( $curl, CURLINFO_HTTP_CODE ) . '; cURL ' . curl_errno( $curl ) . ': ' . curl_error( $curl ) );
	}
	$rows = json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
	if ( ! is_array( $rows ) || count( $rows ) < 100 || empty( $rows[0]['id'] ) ) {
		throw new RuntimeException( 'Unexpected pickup directory shape: ' . $type );
	}
	$GLOBALS['balikovna_test_points_http'] = function () use ( $body ) { return array( 'response' => array( 'code' => 200 ), 'body' => $body ); };
	$point = Balikovna_WC\Points::validate( array( 'id' => $rows[0]['id'] ), $service );
	$directory = get_transient( 'balikovna_wc_points_' . strtolower( $type ) . '_v1' );
	if ( is_wp_error( $point ) || empty( $point['name'] ) || ! is_array( $directory ) || count( $directory ) < count( $rows ) * 0.99 ) {
		throw new RuntimeException( 'Production pickup parser rejected the current directory: ' . $type );
	}
	echo $type . ': ' . count( $directory ) . ' canonical points, ' . strlen( $body ) . " bytes.\n";
}