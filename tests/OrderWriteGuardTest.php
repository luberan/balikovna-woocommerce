<?php

use Balikovna_WC\Order;
use Balikovna_WC\Order_Write_Guard;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Order_Database {
	public $prefix = 'wp_';
	public $posts = 'wp_posts';
	public $last_error = '';
	public $snapshot;
	public $queries = array();
	public $fail_query = '';
	public $engine = 'InnoDB';
	public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	public function suppress_errors( $suppress ) { return false; }
	public function get_var( $query ) {
		if ( is_string( $query ) ) { return 50; }
		if ( false !== strpos( $query[0], 'SELECT ENGINE' ) ) { return $this->engine; }
		return $this->snapshot['status'];
	}
	public function get_col( $query ) { return array_keys( $this->snapshot['items'] ); }
	public function get_results( $query, $output ) {
		$rows = array();
		foreach ( $this->snapshot['items'][ $query[1][1] ] as $key => $value ) {
			$rows[] = array( 'meta_key' => $key, 'meta_value' => $value );
		}
		return $rows;
	}
	public function query( $query ) {
		$sql = is_array( $query ) ? $query[0] : $query;
		$this->queries[] = $sql;
		return $this->fail_query === $sql ? false : 0;
	}
}

final class OrderWriteGuardTest extends TestCase {
	private function fixture() {
		if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
		$order = new WC_Order( array( new WC_Order_Item_Shipping( 'balikovna', '4', array( Order::META_TRACKING_NUMBER => 'BA1234567890A' ), 10 ) ) );
		$database = new Balikovna_Test_Order_Database();
		$guard = new Order_Write_Guard( $database );
		$database->snapshot = $guard->snapshot( $order );
		return array( $order, $database, $guard );
	}

	public function test_matching_snapshot_commits_and_conflicts_never_call_writer(): void {
		list( $order, $database, $guard ) = $this->fixture();
		$calls = 0;
		$write = function () use ( &$calls ) { ++$calls; return 'written'; };
		$this->assertSame( 'written', $guard->run( $order, $write ) );
		$this->assertContains( 'COMMIT', $database->queries );
		$database->queries = array();
		$database->snapshot['status'] = 'wc-cancelled';
		$this->assertFalse( $guard->run( $order, $write ) );
		$this->assertContains( 'ROLLBACK', $database->queries );
		$this->assertNotContains( 'COMMIT', $database->queries );
		$this->assertSame( 1, $calls );
	}

	public function test_tracking_change_or_removed_shipping_item_rejects_stale_write(): void {
		list( $order, $database, $guard ) = $this->fixture();
		$write = function () { $this->fail( 'Stale shipment must not be written.' ); };
		$database->snapshot['items'][10][ Order::META_TRACKING_NUMBER ] = 'BA9999999999A';
		$this->assertFalse( $guard->run( $order, $write ) );
		$database->snapshot['items'] = array();
		$this->assertFalse( $guard->run( $order, $write ) );
	}

	public function test_active_transaction_and_nontransactional_tables_fail_closed(): void {
		list( $order, $database, $guard ) = $this->fixture();
		$database->fail_query = 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ';
		$this->assertFalse( $guard->run( $order, function () { $this->fail( 'Must not join an outer transaction.' ); } ) );
		$this->assertNotContains( 'START TRANSACTION', $database->queries );
		$this->assertNotContains( 'ROLLBACK', $database->queries );
		$database->engine = 'MyISAM';
		$database->fail_query = '';
		$this->assertFalse( ( new Order_Write_Guard( $database ) )->run( $order, function () { return true; } ) );
	}

	public function test_writer_failure_rolls_back_and_exception_releases_transaction(): void {
		list( $order, $database, $guard ) = $this->fixture();
		$this->assertFalse( $guard->run( $order, function () { return false; } ) );
		$this->assertContains( 'ROLLBACK', $database->queries );
		try {
			$guard->run( $order, function () { throw new RuntimeException( 'write failed' ); } );
			$this->fail( 'Writer exception must be propagated.' );
		} catch ( RuntimeException $error ) {
			$this->assertSame( 'write failed', $error->getMessage() );
		}
		$this->assertSame( 2, count( array_filter( $database->queries, function ( $query ) { return 'ROLLBACK' === $query; } ) ) );
	}
}