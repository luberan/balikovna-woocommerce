<?php

defined( 'BALIKOVNA_INTEGRATION_TESTS' ) && BALIKOVNA_INTEGRATION_TESTS || exit;

add_filter( 'pre_wp_mail', '__return_false' );
add_filter( 'woocommerce_is_checkout', function ( $checkout ) {
	return $checkout || ( is_page() && (int) get_queried_object_id() === (int) get_option( 'balikovna_integration_classic_id' ) );
} );
add_filter( 'balikovna_wc_points_directory', function () {
	return array(
		'B10000' => Balikovna_WC\Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'street' => 'Testovaci 1', 'city' => 'Praha', 'zip' => '10000', 'country' => 'CZ', 'type' => 'BALIKOVNY' ) ),
		'B60200' => Balikovna_WC\Points::sanitize( array( 'id' => 'B60200', 'name' => 'Brno', 'street' => 'Testovaci 2', 'city' => 'Brno', 'zip' => '60200', 'country' => 'CZ', 'type' => 'BALIKOVNY' ) ),
	);
} );
add_filter( 'woocommerce_cart_shipping_packages', function ( $packages ) {
	if ( '2' !== ( $_COOKIE['balikovna_test_packages'] ?? '' ) || count( $packages ) !== 1 ) {
		return $packages;
	}
	$package = reset( $packages );
	foreach ( $package['contents'] as &$item ) {
		$item['quantity'] /= 2;
		foreach ( array( 'line_total', 'line_tax', 'line_subtotal', 'line_subtotal_tax' ) as $field ) {
			$item[ $field ] /= 2;
		}
	}
	unset( $item );
	$package['contents_cost'] /= 2;
	return array( $package, $package );
}, 20 );