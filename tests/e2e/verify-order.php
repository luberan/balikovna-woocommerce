<?php

if ( 'cli' !== PHP_SAPI ) { exit( 1 ); }
require dirname( __DIR__ ) . '/integration/bootstrap.php';
$order = wc_get_order( (int) ( $argv[1] ?? 0 ) );
if ( ! $order || 'e2e@example.test' !== $order->get_billing_email() ) {
	throw new RuntimeException( 'Expected an E2E fixture order.' );
}
$export = new class extends Balikovna_WC\Export {
	public function rows( $order ) { return $this->prepare_order_rows( $order ); }
};
$rows = $export->rows( $order );
$shipments = array_map( function ( $shipment ) {
	return array( 'point' => $shipment['point']['id'] ?? '', 'weight' => $shipment['weightKg'], 'contents' => $shipment['contentsValue'], 'limit' => $shipment['maxWeightKg'] );
}, Balikovna_WC\Order::get_shipments( $order ) );
echo json_encode( array( 'status' => $order->get_status(), 'shipments' => $shipments, 'csv' => is_wp_error( $rows ) ? $rows->get_error_message() : $rows, 'errors' => $GLOBALS['balikovna_integration_errors'] ) ) . PHP_EOL;