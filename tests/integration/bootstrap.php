<?php

$site = getenv( 'BALIKOVNA_TEST_SITE' );
if ( ! $site || ! is_file( $site . '/.balikovna-integration-site' ) ) {
	throw new RuntimeException( 'Set BALIKOVNA_TEST_SITE to an isolated site prepared by setup-integration.php.' );
}
$GLOBALS['balikovna_integration_errors'] = array();
set_error_handler(
	function ( $severity, $message, $file, $line ) {
		if ( ! ( error_reporting() & $severity ) ) {
			return false;
		}
		$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		$owned = false !== strpos( str_replace( '\\', '/', $file ), '/plugins/balikovna-woocommerce/' );
		foreach ( $frames as $frame ) {
			$owned = $owned || 0 === strpos( $frame['class'] ?? '', 'Balikovna_WC\\' );
		}
		if ( $owned ) {
			$GLOBALS['balikovna_integration_errors'][] = compact( 'severity', 'message', 'file', 'line' );
		}
		return false;
	}
);
require_once $site . '/wp-load.php';
if ( ! defined( 'BALIKOVNA_INTEGRATION_TESTS' ) || ! BALIKOVNA_INTEGRATION_TESTS || 'local' !== wp_get_environment_type()
	|| ! preg_match( '/^balikovna_test_[a-z0-9_]+$/', DB_NAME ) ) {
	throw new RuntimeException( 'Refusing to run integration tests against a non-test installation.' );
}
add_filter( 'pre_wp_mail', '__return_false' );
add_filter( 'woocommerce_defer_transactional_emails', '__return_false' );