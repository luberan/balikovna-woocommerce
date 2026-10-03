<?php

use Balikovna_WC\Option_Lock;
use PHPUnit\Framework\TestCase;

final class OptionLockTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wpdb']                   = new Balikovna_Test_Lock_Database();
		$GLOBALS['balikovna_test_options'] = array();
	}

	public function test_insert_race_never_lets_two_processes_own_the_lock(): void {
		$other                         = array(
			'token'   => 'process-b',
			'expires' => 2000,
		);
		$GLOBALS['wpdb']->before_query = function () use ( $other ) {
			// Process B inserts its lock after process A observed that none existed.
			$GLOBALS['balikovna_test_options']['balikovna_test_lock'] = $other;
		};

		$this->assertFalse( Option_Lock::acquire( 'balikovna_test_lock', 1000, 60 ) );
		$this->assertSame( $other, get_option( 'balikovna_test_lock' ) );
	}

	public function test_expired_or_corrupted_lock_is_replaced_once(): void {
		update_option(
			'balikovna_test_lock',
			array(
				'token'   => 'old',
				'expires' => 999,
			)
		);
		$token = Option_Lock::acquire( 'balikovna_test_lock', 1000, 60 );

		$this->assertNotFalse( $token );
		$this->assertSame(
			array(
				'token'   => $token,
				'expires' => 1060,
			),
			get_option( 'balikovna_test_lock' )
		);
		$this->assertFalse( Option_Lock::acquire( 'balikovna_test_lock', 1000, 60 ) );

		update_option( 'balikovna_test_lock', 'corrupted' );
		$this->assertNotFalse( Option_Lock::acquire( 'balikovna_test_lock', 1000, 60 ) );
	}
}
