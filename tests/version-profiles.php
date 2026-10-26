<?php

declare( strict_types=1 );

function balikovna_test_versions( ?string $profile = null ): array {
	$profile = $profile ?? ( getenv( 'BALIKOVNA_TEST_VERSION_PROFILE' ) ?: 'current' );
	$config = json_decode( file_get_contents( __DIR__ . '/versions.json' ), true, 512, JSON_THROW_ON_ERROR );
	if ( ! in_array( $profile, array( 'current', 'minimum' ), true ) || ! isset( $config[ $profile ] ) ) {
		throw new RuntimeException( 'Unknown integration version profile: ' . $profile );
	}
	$versions = $config[ $profile ];
	foreach ( array( 'wordpress', 'woocommerce' ) as $key ) {
		if ( ! isset( $versions[ $key ] ) || ! is_string( $versions[ $key ] ) || ! preg_match( '/^\d+\.\d+(?:\.\d+)?$/', $versions[ $key ] ) ) {
			throw new RuntimeException( 'Invalid integration version: ' . $profile . '/' . $key );
		}
	}
	if ( ! isset( $versions['woocommerce_sha256'] ) || ! is_string( $versions['woocommerce_sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $versions['woocommerce_sha256'] ) ) {
		throw new RuntimeException( 'The integration WooCommerce archive needs a SHA-256 digest.' );
	}
	return $versions;
}

function balikovna_test_upgrade_baseline(): array {
	$config = json_decode( file_get_contents( __DIR__ . '/versions.json' ), true, 512, JSON_THROW_ON_ERROR );
	$baseline = $config['upgrade'] ?? null;
	if ( ! is_array( $baseline ) || ! isset( $baseline['version'], $baseline['sha256'] )
		|| ! is_string( $baseline['version'] ) || ! is_string( $baseline['sha256'] )
		|| ! preg_match( '/^\d+\.\d+\.\d+$/', $baseline['version'] )
		|| ! preg_match( '/^[a-f0-9]{64}$/', $baseline['sha256'] ) ) {
		throw new RuntimeException( 'The upgrade baseline needs a release version and SHA-256 digest.' );
	}
	return $baseline;
}
