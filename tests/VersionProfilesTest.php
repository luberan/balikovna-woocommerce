<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/version-profiles.php';

final class VersionProfilesTest extends TestCase {
	public function test_profiles_pin_the_declared_minimum_and_current_tested_versions(): void {
		$minimum = balikovna_test_versions( 'minimum' );
		$current = balikovna_test_versions( 'current' );
		$this->assertSame( '6.9', $minimum['wordpress'] );
		$this->assertSame( '10.8.0', $minimum['woocommerce'] );
		$this->assertSame( '7.1.2', $current['wordpress'] );
		$this->assertSame( '11.2.1', $current['woocommerce'] );
		foreach ( array( $minimum, $current ) as $profile ) {
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $profile['woocommerce_sha256'] );
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{40}$/', $profile['wordpress_sha1'] );
		}
		$this->assertNotSame( $minimum['wordpress_sha1'], $current['wordpress_sha1'] );
	}

	public function test_wordpress_archive_is_verified_before_it_is_extracted(): void {
		$setup    = file_get_contents( dirname( __DIR__ ) . '/.github/scripts/setup-integration.php' );
		$verified = strpos( $setup, "hash_equals( \$versions['wordpress_sha1'], hash_file( 'sha1', \$wordpress_archive ) )" );
		$this->assertIsInt( $verified );
		$this->assertLessThan( strpos( $setup, 'integration_extract( $wordpress_archive' ), $verified );
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
