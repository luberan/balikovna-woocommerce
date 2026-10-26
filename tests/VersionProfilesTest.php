<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/version-profiles.php';

final class VersionProfilesTest extends TestCase {
	public function test_profiles_pin_the_declared_minimum_and_current_tested_versions(): void {
		$minimum = balikovna_test_versions( 'minimum' );
		$current = balikovna_test_versions( 'current' );
		$this->assertSame( '6.9', $minimum['wordpress'] );
		$this->assertSame( '10.8.0', $minimum['woocommerce'] );
		$this->assertSame( '7.1', $current['wordpress'] );
		$this->assertSame( '11.1.0', $current['woocommerce'] );
		foreach ( array( $minimum, $current ) as $profile ) {
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $profile['woocommerce_sha256'] );
		}
	}

	public function test_unknown_profile_is_rejected_instead_of_running_another_version(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Unknown integration version profile' );
		balikovna_test_versions( 'misspelled-minimum' );
	}

	public function test_upgrade_baseline_is_a_pinned_published_release(): void {
		$baseline = balikovna_test_upgrade_baseline();
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $baseline['version'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $baseline['sha256'] );
	}
}
