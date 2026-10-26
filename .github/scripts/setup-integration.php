<?php

declare( strict_types=1 );

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}
$root = dirname( __DIR__, 2 );
require_once $root . '/tests/version-profiles.php';
$versions = balikovna_test_versions();
$baseline = 2 === $argc && '--baseline' === $argv[1];
if ( $argc > 2 || ( 2 === $argc && ! $baseline ) ) {
	throw new RuntimeException( 'Usage: setup-integration.php [--baseline]' );
}
$site = rtrim( (string) getenv( 'BALIKOVNA_TEST_SITE' ), '/\\' );
$database = (string) getenv( 'BALIKOVNA_TEST_DB_NAME' );
$host = getenv( 'BALIKOVNA_TEST_DB_HOST' ) ?: '127.0.0.1';
$port = (int) ( getenv( 'BALIKOVNA_TEST_DB_PORT' ) ?: 3306 );
if ( '' === $site || ! preg_match( '/^balikovna_test_[a-z0-9_]+$/', $database ) || ! in_array( $host, array( '127.0.0.1', 'localhost' ), true ) ) {
	throw new RuntimeException( 'Set an isolated BALIKOVNA_TEST_SITE and local balikovna_test_* database.' );
}
if ( is_dir( $site ) && count( scandir( $site ) ) > 2 && ! is_file( $site . '/.balikovna-integration-site' ) ) {
	throw new RuntimeException( 'Refusing to overwrite an existing non-test directory.' );
}
if ( ! is_dir( $site ) && ! mkdir( $site, 0777, true ) ) {
	throw new RuntimeException( 'Unable to create the test directory.' );
}
file_put_contents( $site . '/.balikovna-integration-site', "Isolated Balikovna integration fixture.\n" );
function integration_download( string $url, string $target ): void {
	if ( is_file( $target ) ) {
		return;
	}
	$stream = fopen( $target . '.part', 'wb' );
	$curl = curl_init( $url );
	curl_setopt_array( $curl, array( CURLOPT_FILE => $stream, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_TIMEOUT => 180, CURLOPT_FAILONERROR => true, CURLOPT_USERAGENT => 'Balikovna-Integration-Tests' ) );
	$success = curl_exec( $curl );
	$error = curl_error( $curl );
	fclose( $stream );
	if ( ! $success ) {
		unlink( $target . '.part' );
		throw new RuntimeException( 'Fixture download failed: ' . $error );
	}
	rename( $target . '.part', $target );
}

function integration_copy( string $source, string $destination ): void {
	if ( is_link( $source ) ) {
		throw new RuntimeException( 'Symlinks are not supported in fixture source.' );
	}
	if ( is_file( $source ) ) {
		if ( ! is_dir( dirname( $destination ) ) ) {
			mkdir( dirname( $destination ), 0777, true );
		}
		if ( ! copy( $source, $destination ) ) {
			throw new RuntimeException( 'Cannot copy fixture file.' );
		}
		return;
	}
	if ( ! is_dir( $destination ) ) {
		mkdir( $destination, 0777, true );
	}
	foreach ( new DirectoryIterator( $source ) as $entry ) {
		if ( ! $entry->isDot() ) {
			integration_copy( $entry->getPathname(), $destination . '/' . $entry->getFilename() );
		}
	}
}

function integration_extract( string $archive, string $destination, string $prefix ): void {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $archive ) ) {
		throw new RuntimeException( 'Cannot open fixture ZIP.' );
	}
	for ( $index = 0; $index < $zip->numFiles; ++$index ) {
		$name = $zip->getNameIndex( $index );
		if ( 0 !== strpos( $name, $prefix . '/' ) || preg_match( '~(?:^|/)\.\.(?:/|$)|[\\\\:]~', $name ) ) {
			throw new RuntimeException( 'Unexpected ZIP entry: ' . $name );
		}
	}
	if ( ! $zip->extractTo( $destination ) ) {
		throw new RuntimeException( 'Fixture ZIP extraction failed.' );
	}
	$zip->close();
}

$wordpress_archive = getenv( 'BALIKOVNA_TEST_WP_ARCHIVE' ) ?: $site . '/.wordpress.zip';
$woocommerce_archive = getenv( 'BALIKOVNA_TEST_WC_ARCHIVE' ) ?: $site . '/.woocommerce.zip';
integration_download( 'https://downloads.wordpress.org/release/wordpress-' . $versions['wordpress'] . '.zip', $wordpress_archive );
integration_download( 'https://github.com/woocommerce/woocommerce/releases/download/' . $versions['woocommerce'] . '/woocommerce.zip', $woocommerce_archive );
if ( ! hash_equals( $versions['woocommerce_sha256'], hash_file( 'sha256', $woocommerce_archive ) ) ) {
	throw new RuntimeException( 'WooCommerce archive checksum does not match the pinned release.' );
}
integration_extract( $wordpress_archive, $site . '/.source', 'wordpress' );
integration_copy( $site . '/.source/wordpress', $site );
integration_extract( $woocommerce_archive, $site . '/wp-content/plugins', 'woocommerce' );
$plugin_archive = $root . '/build/balikovna-woocommerce.zip';
if ( $baseline ) {
	$release = balikovna_test_upgrade_baseline();
	$plugin_archive = $site . '/.plugin-baseline.zip';
	integration_download( 'https://github.com/luberan/balikovna-woocommerce/releases/download/v' . $release['version'] . '/balikovna-woocommerce.zip', $plugin_archive );
	if ( ! hash_equals( $release['sha256'], hash_file( 'sha256', $plugin_archive ) ) ) {
		throw new RuntimeException( 'Plugin baseline archive checksum does not match the pinned release.' );
	}
}
if ( ! is_file( $plugin_archive ) ) {
	throw new RuntimeException( 'Run composer build to create the plugin ZIP before preparing the integration site.' );
}
integration_extract( $plugin_archive, $site . '/wp-content/plugins', 'balikovna-woocommerce' );
integration_copy( $root . '/tests/integration/wp-config.php', $site . '/wp-config.php' );
integration_copy( $root . '/tests/integration/fixtures.php', $site . '/wp-content/mu-plugins/balikovna-test-fixtures.php' );

$connection = new PDO( 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4', getenv( 'BALIKOVNA_TEST_DB_USER' ) ?: 'root', getenv( 'BALIKOVNA_TEST_DB_PASSWORD' ) ?: '', array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
$connection->exec( 'CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
define( 'WP_INSTALLING', true );
require $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_blog_installed() ) {
	wp_install( 'Balikovna Integration', 'integration-admin', 'integration@example.test', false, '', wp_generate_password( 40 ) );
}
wp_set_current_user( 1 );
foreach ( array( 'woocommerce/woocommerce.php', 'balikovna-woocommerce/balikovna-woocommerce.php' ) as $plugin ) {
	$result = activate_plugin( $plugin );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
}
$php_args = json_decode( getenv( 'BALIKOVNA_PHP_ARGS' ) ?: '[]', true, 512, JSON_THROW_ON_ERROR );
$command = array_merge( array( PHP_BINARY ), $php_args, array( $root . '/tests/integration/configure.php' ) );
$process = proc_open( $command, array( STDIN, STDOUT, STDERR ), $pipes, $root );
if ( ! is_resource( $process ) ) {
	throw new RuntimeException( 'Unable to start the post-activation fixture process.' );
}
exit( proc_close( $process ) );