<?php

define( 'BALIKOVNA_INTEGRATION_TESTS', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DB_NAME', getenv( 'BALIKOVNA_TEST_DB_NAME' ) );
define( 'DB_USER', getenv( 'BALIKOVNA_TEST_DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'BALIKOVNA_TEST_DB_PASSWORD' ) ?: '' );
define( 'DB_HOST', ( getenv( 'BALIKOVNA_TEST_DB_HOST' ) ?: '127.0.0.1' ) . ':' . ( getenv( 'BALIKOVNA_TEST_DB_PORT' ) ?: '3306' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
if ( ! preg_match( '/^balikovna_test_[a-z0-9_]+$/', (string) DB_NAME ) ) {
	throw new RuntimeException( 'Integration database must use the balikovna_test_ prefix.' );
}
define( 'AUTH_KEY', 'local-balikovna-integration-auth-key-not-for-production' );
define( 'AUTH_SALT', 'local-balikovna-integration-auth-salt-not-for-production' );
define( 'WP_HOME', getenv( 'BALIKOVNA_TEST_BASE_URL' ) ?: 'http://127.0.0.1:8873' );
define( 'WP_SITEURL', WP_HOME );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', true );
define( 'DISABLE_WP_CRON', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_MEMORY_LIMIT', '512M' );
$table_prefix = 'bwc_';
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';