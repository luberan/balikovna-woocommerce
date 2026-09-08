<?php

if ( 'cli' !== PHP_SAPI ) { exit( 1 ); }
require __DIR__ . '/bootstrap.php';
$site = getenv( 'BALIKOVNA_TEST_SITE' );
wp_set_current_user( 1 );
WC_Install::create_tables();
$synchronizer = wc_get_container()->get( Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
for ( $batch = 0; $batch < 20; ++$batch ) {
	$ids = $synchronizer->get_next_batch_to_process( 100 );
	if ( ! $ids ) { break; }
	$synchronizer->process_batch( $ids );
}
update_option( 'woocommerce_custom_orders_table_enabled', 'hpos' === getenv( 'BALIKOVNA_TEST_STORAGE' ) ? 'yes' : 'no' );
switch_theme( WP_DEFAULT_THEME );
WC_Install::create_pages();
foreach ( array( 'woocommerce_currency' => 'CZK', 'woocommerce_default_country' => 'CZ', 'woocommerce_weight_unit' => 'kg', 'woocommerce_dimension_unit' => 'cm', 'woocommerce_coming_soon' => 'no', 'woocommerce_enable_guest_checkout' => 'yes', 'woocommerce_calc_taxes' => 'no' ) as $name => $value ) {
	update_option( $name, $value );
}
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_methods' => array() ) );
$product_id = wc_get_product_id_by_sku( 'balikovna-integration-product' );
$product = $product_id ? wc_get_product( $product_id ) : new WC_Product_Simple();
$product->set_name( 'Integration parcel' );
$product->set_sku( 'balikovna-integration-product' );
$product->set_status( 'publish' );
$product->set_regular_price( '100' );
$product->set_weight( '2' );
$product->set_length( '10' );
$product->set_width( '10' );
$product->set_height( '10' );
$product->save();
Balikovna_WC\Plugin::instance()->load_shipping_methods();
$zone = new WC_Shipping_Zone( 0 );
$methods = $zone->get_shipping_methods();
$instance_id = 0;
foreach ( $methods as $method ) {
	if ( 'balikovna' === $method->id ) {
		$instance_id = $method->get_instance_id();
		break;
	}
}
if ( ! $instance_id ) { $instance_id = $zone->add_shipping_method( 'balikovna' ); }
update_option( 'woocommerce_balikovna_' . $instance_id . '_settings', array( 'title' => 'Balikovna', 'cost' => '79', 'cost_type' => 'flat', 'tax_status' => 'none' ) );
$classic_id = (int) get_option( 'balikovna_integration_classic_id', 0 );
if ( ! $classic_id ) {
	$classic_id = wp_insert_post( array( 'post_title' => 'Classic checkout', 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[woocommerce_checkout]' ) );
	update_option( 'balikovna_integration_classic_id', $classic_id );
}
$data = array( 'product_id' => $product->get_id(), 'block_id' => wc_get_page_id( 'checkout' ), 'classic_id' => $classic_id, 'rate_id' => 'balikovna:' . $instance_id );
file_put_contents( $site . '/fixture-data.json', json_encode( $data, JSON_PRETTY_PRINT ) );
echo json_encode( array_merge( $data, array( 'wordpress' => get_bloginfo( 'version' ), 'woocommerce' => WC_VERSION, 'database' => DB_NAME ) ), JSON_PRETTY_PRINT ) . PHP_EOL;