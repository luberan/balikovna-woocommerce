<?php

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'BALIKOVNA_WC_VERSION', '1.26.5' );
define( 'BALIKOVNA_WC_PATH', dirname( __DIR__ ) . '/' );
define( 'BALIKOVNA_WC_URL', 'https://example.test/wp-content/plugins/balikovna-woocommerce/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MB_IN_BYTES', 1048576 );

$GLOBALS['balikovna_test_filters']    = array();
$GLOBALS['balikovna_test_transients'] = array();
$GLOBALS['balikovna_test_options']    = array();
$GLOBALS['balikovna_test_actions']    = array();
$GLOBALS['balikovna_test_did_actions'] = array();
$GLOBALS['balikovna_test_order_query'] = null;
$GLOBALS['balikovna_test_logger']      = null;
$GLOBALS['balikovna_test_scheduled_actions'] = array();
$GLOBALS['balikovna_test_enqueued_scripts']  = array();
$GLOBALS['balikovna_test_order_statuses'] = array(
	'wc-pending'      => 'Čeká na platbu',
	'wc-processing'   => 'Zpracovává se',
	'wc-completed'    => 'Dokončeno',
	'wc-cancelled'    => 'Zrušeno',
	'wc-refunded'     => 'Vráceno',
	'wc-failed'       => 'Selhalo',
	'wc-shipped'      => 'Odesláno',
	'wc-ready-pickup' => 'Připraveno k vyzvednutí',
);

class Balikovna_Test_Session {
	private $data = array();

	public function get( $key, $default = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}
}

$runtime_stubs = file_get_contents( __DIR__ . '/runtime-stubs.txt' );
if ( false === $runtime_stubs ) {
	throw new RuntimeException( 'Unable to load test runtime stubs.' );
}
eval( $runtime_stubs );

$GLOBALS['balikovna_test_wc'] = (object) array( 'session' => new Balikovna_Test_Session() );

require_once dirname( __DIR__ ) . '/includes/class-balikovna-services.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-points.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-checkout.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-order.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-shipment-status.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-napi-authentication.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-napi-client.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-tracking-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-status-dictionary.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-eligible-orders.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-order-status-mapper.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-tracking-logger.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-shipment-synchronizer.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-tracking-scheduler.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-tracking-admin.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-tracking.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-export.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-shipping-method-base.php';
require_once dirname( __DIR__ ) . '/includes/class-balikovna-blocks.php';
