<?php

declare( strict_types=1 );

if ( 'cli' !== PHP_SAPI || $argc > 2 || ( 2 === $argc && '--verify' !== $argv[1] ) ) {
	throw new RuntimeException( 'Usage: php tests/integration/upgrade.php [--verify]' );
}

require __DIR__ . '/bootstrap.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

function balikovna_upgrade_check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function balikovna_upgrade_installed_files( string $directory ): array {
	$files = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		balikovna_upgrade_check( ! $file->isLink(), 'Upgrade verification must not follow plugin symlinks.' );
		$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $directory ) + 1 ) );
		$files[ $relative ] = hash_file( 'sha256', $file->getPathname() );
	}
	ksort( $files );
	return $files;
}

function balikovna_upgrade_archive( string $path ): array {
	$archive = new ZipArchive();
	balikovna_upgrade_check( true === $archive->open( $path ), 'Cannot open the upgrade verification ZIP.' );
	$files = array();
	try {
		for ( $index = 0; $index < $archive->numFiles; ++$index ) {
			$name = $archive->getNameIndex( $index );
			balikovna_upgrade_check( is_string( $name ) && 0 === strpos( $name, 'balikovna-woocommerce/' ) && ! preg_match( '~(?:^|/)\.\.(?:/|$)|[\\\\:]~', $name ), 'Unexpected distribution ZIP entry.' );
			if ( '/' === substr( $name, -1 ) ) {
				continue;
			}
			$contents = $archive->getFromIndex( $index );
			balikovna_upgrade_check( false !== $contents, 'Cannot read a distribution ZIP entry.' );
			$files[ substr( $name, strlen( 'balikovna-woocommerce/' ) ) ] = hash( 'sha256', $contents );
		}
		$header = $archive->getFromName( 'balikovna-woocommerce/balikovna-woocommerce.php' );
		balikovna_upgrade_check( is_string( $header ) && 1 === preg_match( '/^[ \t\/*#@]*Version:\s*(\d+\.\d+\.\d+)\s*$/mi', $header, $version ), 'The distribution ZIP has no valid plugin version.' );
	} finally {
		$archive->close();
	}
	ksort( $files );
	return array( 'version' => $version[1], 'files' => $files );
}

$site = rtrim( (string) getenv( 'BALIKOVNA_TEST_SITE' ), '/\\' );
$plugin = 'balikovna-woocommerce/balikovna-woocommerce.php';
$plugin_directory = WP_PLUGIN_DIR . '/balikovna-woocommerce';
$state_path = $site . '/upgrade-state.json';
wp_set_current_user( 1 );
balikovna_upgrade_check( is_plugin_active( $plugin ), 'The baseline or upgraded plugin is not active.' );
balikovna_upgrade_check( class_exists( Balikovna_WC\Credentials::class ), 'The plugin did not initialize against the selected WooCommerce runtime.' );
foreach ( Balikovna_WC\Credentials::FIELDS as $field => $name ) {
	balikovna_upgrade_check( null === Balikovna_WC\Credentials::external( $field ), 'Upgrade fixtures must not use external carrier credentials.' );
}

if ( '--verify' === ( $argv[1] ?? '' ) ) {
	$state = json_decode( file_get_contents( $state_path ), true, 512, JSON_THROW_ON_ERROR );
	balikovna_upgrade_check( is_plugin_active( $plugin ), 'The upgraded plugin is not active.' );
	$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
	balikovna_upgrade_check( $state['version'] === $data['Version'], 'The installed plugin version does not match the tested ZIP.' );
	balikovna_upgrade_check( $state['files'] === balikovna_upgrade_installed_files( $plugin_directory ), 'The upgraded installation differs from the distribution ZIP or kept obsolete files.' );
	balikovna_upgrade_check( ( 'hpos' === getenv( 'BALIKOVNA_TEST_STORAGE' ) ) === Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(), 'Upgrade changed the selected order storage.' );
	balikovna_upgrade_check( $state['shipping_settings'] === get_option( $state['shipping_option'] ), 'Upgrade changed shipping method settings.' );
	$tracking = Balikovna_WC\Tracking_Settings::get();
	foreach ( $state['tracking'] as $key => $value ) {
		balikovna_upgrade_check( $value === $tracking[ $key ], 'Upgrade changed tracking setting: ' . $key );
	}
	$order = wc_get_order( $state['order_id'] );
	balikovna_upgrade_check( $order instanceof WC_Order && 'processing' === $order->get_status(), 'Upgrade lost or changed the existing order.' );
	balikovna_upgrade_check( $state['point'] === Balikovna_WC\Order::get_point( $order ), 'Upgrade changed the order pickup point.' );
	balikovna_upgrade_check( $state['shipping_count'] === count( $order->get_shipping_methods() ), 'Upgrade changed the order shipping items.' );
	foreach ( $state['shipping_meta'] as $key => $value ) {
		balikovna_upgrade_check( $value === wc_get_order_item_meta( $state['shipping_id'], $key, true ), 'Upgrade changed shipping metadata: ' . $key );
	}
	balikovna_upgrade_check( array() === $GLOBALS['balikovna_integration_errors'], 'The upgraded plugin produced PHP diagnostics.' );
	echo 'Verified native ZIP upgrade, activation, exact files, settings and order pickup data (' . getenv( 'BALIKOVNA_TEST_STORAGE' ) . ").\n";
	exit( 0 );
}

$baseline = balikovna_test_upgrade_baseline();
$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, false );
balikovna_upgrade_check( $baseline['version'] === $data['Version'], 'Prepare the upgrade site using setup-integration.php --baseline.' );
$baseline_path = $site . '/.plugin-baseline.zip';
balikovna_upgrade_check( is_file( $baseline_path ) && hash_equals( $baseline['sha256'], hash_file( 'sha256', $baseline_path ) ), 'The upgrade baseline is not the pinned published ZIP.' );
$published = balikovna_upgrade_archive( $baseline_path );
balikovna_upgrade_check( $published['files'] === balikovna_upgrade_installed_files( $plugin_directory ), 'The initial installation is not exactly the published baseline ZIP.' );
$root = dirname( __DIR__, 2 );
$archive_path = $root . '/build/balikovna-woocommerce.zip';
$target = balikovna_upgrade_archive( $archive_path );
balikovna_upgrade_check( version_compare( $target['version'], $published['version'], '>=' ), 'The upgrade target must not be older than the published baseline.' );

$fixture = json_decode( file_get_contents( $site . '/fixture-data.json' ), true, 512, JSON_THROW_ON_ERROR );
balikovna_upgrade_check( 1 === preg_match( '/^balikovna:(\d+)$/', $fixture['rate_id'], $rate ), 'The upgrade fixture has no shipping instance.' );
$shipping_option = 'woocommerce_balikovna_' . $rate[1] . '_settings';
$shipping_settings = array( 'title' => 'Upgrade fixture', 'cost' => '123', 'cost_type' => 'flat', 'tax_status' => 'none' );
update_option( $shipping_option, $shipping_settings );
$tracking = array(
	'enabled' => false,
	'batch_size' => 17,
	'api_token' => 'integration-upgrade-token',
	'secret_key' => 'integration-upgrade-secret',
);
$saved = Balikovna_WC\Tracking_Settings::save( array_merge( Balikovna_WC\Tracking_Settings::defaults(), $tracking ) );
balikovna_upgrade_check( ! is_wp_error( $saved ), 'Cannot seed tracking settings for upgrade.' );
$point = Balikovna_WC\Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'street' => 'Testovaci 1', 'city' => 'Praha', 'zip' => '10000', 'country' => 'CZ', 'type' => 'BALIKOVNY' ) );
$order = wc_create_order( array( 'status' => 'processing' ) );
balikovna_upgrade_check( $order instanceof WC_Order, 'Cannot seed the upgrade order.' );
$item = new WC_Order_Item_Shipping();
$item->set_method_id( 'balikovna' );
$item->set_instance_id( (int) $rate[1] );
$order->add_item( $item );
Balikovna_WC\Order::save_point_to_order( $order, $point );
$order->save();
$shipping_meta = array(
	Balikovna_WC\Order::META_KEY => $point,
	Balikovna_WC\Order::META_PACKAGE_KEY => '0',
	Balikovna_WC\Order::META_RATE_ID => $fixture['rate_id'],
	Balikovna_WC\Order::META_TRACKING_NUMBER => 'BA1234567890A',
);
foreach ( $shipping_meta as $key => $value ) {
	wc_update_order_item_meta( $item->get_id(), $key, $value );
}
$state = array(
	'version' => $target['version'],
	'files' => $target['files'],
	'shipping_option' => $shipping_option,
	'shipping_settings' => $shipping_settings,
	'tracking' => $tracking,
	'order_id' => $order->get_id(),
	'point' => $point,
	'shipping_id' => $item->get_id(),
	'shipping_count' => count( $order->get_shipping_methods() ),
	'shipping_meta' => $shipping_meta,
);
balikovna_upgrade_check( false !== file_put_contents( $state_path, json_encode( $state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION ) ), 'Cannot store upgrade verification state.' );
balikovna_upgrade_check( false !== file_put_contents( $plugin_directory . '/.upgrade-obsolete-fixture', 'An upgrade must remove this obsolete file.' ), 'Cannot create the obsolete-file fixture.' );
$package = $site . '/upgrade-target.zip';
balikovna_upgrade_check( copy( $archive_path, $package ), 'Cannot copy the upgrade package into the isolated site.' );

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$skin = new Automatic_Upgrader_Skin();
$upgrader = new Plugin_Upgrader( $skin );
$updates = (object) array(
	'last_checked' => time(),
	'checked' => array( $plugin => $baseline['version'] ),
	'response' => array( $plugin => (object) array( 'slug' => 'balikovna-woocommerce', 'plugin' => $plugin, 'new_version' => $target['version'], 'package' => $package ) ),
);
$update_fixture = static function () use ( $updates ) { return $updates; };
add_filter( 'pre_site_transient_update_plugins', $update_fixture );
try {
	$result = $upgrader->bulk_upgrade( array( $plugin ), array( 'clear_update_cache' => false ) );
} finally {
	remove_filter( 'pre_site_transient_update_plugins', $update_fixture );
}
balikovna_upgrade_check( is_array( $result ) && isset( $result[ $plugin ] ) && is_array( $result[ $plugin ] ), 'Native WordPress upgrade failed: ' . implode( '; ', $skin->get_upgrade_messages() ) );

$php_args = json_decode( getenv( 'BALIKOVNA_PHP_ARGS' ) ?: '[]', true, 512, JSON_THROW_ON_ERROR );
$process = proc_open(
	array_merge( array( PHP_BINARY ), $php_args, array( __FILE__, '--verify' ) ),
	array( STDIN, STDOUT, STDERR ),
	$pipes,
	$root
);
balikovna_upgrade_check( is_resource( $process ), 'Cannot start a fresh PHP process to verify the upgraded plugin.' );
balikovna_upgrade_check( 0 === proc_close( $process ), 'The upgraded plugin failed fresh-process verification.' );
