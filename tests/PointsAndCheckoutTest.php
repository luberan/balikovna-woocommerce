<?php

use Balikovna_WC\Checkout;
use Balikovna_WC\Points;
use PHPUnit\Framework\TestCase;

final class PointsAndCheckoutTest extends TestCase {
	protected function setUp(): void {
		remove_all_filters();
		$GLOBALS['balikovna_test_wc']->session = new Balikovna_Test_Session();
		add_filter(
			'balikovna_wc_points_directory',
			function ( $directory, $type ) {
				$points = array(
					'BALIKOVNY'   => array(
						'B10000' => Points::sanitize( array( 'id' => 'B10000', 'name' => 'Praha 10', 'street' => 'Cernokostelecka 1', 'city' => 'Praha', 'zip' => '10000', 'country' => 'CZ', 'type' => 'BALIKOVNY' ) ),
					),
					'POST_OFFICE' => array(
						'P10003' => Points::sanitize( array( 'id' => 'P10003', 'name' => 'Depo Praha 701', 'street' => 'Sazecska 7', 'city' => 'Praha', 'zip' => '10003', 'country' => 'CZ', 'type' => 'POST_OFFICE' ) ),
					),
				);
				return $points[ $type ] ?? array();
			},
			10,
			2
		);
	}

	public function test_server_replaces_forged_point_fields_with_canonical_data(): void {
		$result = Points::validate( array( 'id' => 'B10000', 'name' => 'Forged', 'street' => 'Forged' ), 'balikovna' );
		$this->assertIsArray( $result );
		$this->assertSame( 'Praha 10', $result['name'] );
		$this->assertSame( 'BALIKOVNY', $result['type'] );
	}

	public function test_service_type_and_unknown_ids_are_rejected(): void {
		$this->assertSame( 'balikovna_invalid_point_id', Points::validate( array( 'id' => 'P10003' ), 'balikovna' )->get_error_code() );
		$this->assertSame( 'balikovna_unknown_point', Points::validate( array( 'id' => 'B99999' ), 'balikovna' )->get_error_code() );
	}

	public function test_session_keeps_separate_exact_rate_selections_per_package(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna:4', 'cp_na_postu:7' ) );
		Checkout::set_session_selections(
			array(
				'0' => array( 'packageKey' => '0', 'rateId' => 'balikovna:4', 'serviceId' => 'balikovna', 'point' => Points::validate( array( 'id' => 'B10000' ), 'balikovna' ) ),
				'1' => array( 'packageKey' => '1', 'rateId' => 'cp_na_postu:7', 'serviceId' => 'cp_na_postu', 'point' => Points::validate( array( 'id' => 'P10003' ), 'cp_na_postu' ) ),
			)
		);
		$this->assertCount( 2, Checkout::get_session_selections() );
		$this->assertSame( 'P10003', Checkout::get_session_selection( '1', 'cp_na_postu:7' )['point']['id'] );

		WC()->session->set( 'chosen_shipping_methods', array( 'balikovna_na_adresu:4', 'cp_na_postu:8' ) );
		$this->assertSame( array(), Checkout::get_session_selections() );
	}

	public function test_guest_nonce_action_is_woocommerce_session_scoped(): void {
		$this->assertStringStartsWith( 'woocommerce', Checkout::NONCE_ACTION );
	}

	public function test_final_posted_pickup_rate_is_validated_even_when_session_had_flat_rate(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate:2' ) );
		$errors = new WP_Error();
		Checkout::instance()->validate_selection( array( 'shipping_method' => array( 'balikovna:4' ) ), $errors );
		$this->assertTrue( $errors->has_errors() );
		$this->assertSame( 'balikovna_point_required', $errors->get_error_code() );
	}

	public function test_fresh_fallback_directory_avoids_a_network_dependency(): void {
		remove_all_filters();
		$GLOBALS['balikovna_test_options']['balikovna_wc_points_balikovny_v1_stale'] = array(
			'updated'   => time(),
			'directory' => array(
				'B10000' => Points::sanitize( array( 'id' => 'B10000', 'name' => 'Cached point', 'type' => 'BALIKOVNY' ) ),
			),
		);
		$result = Points::validate( array( 'id' => 'B10000' ), 'balikovna' );
		$this->assertIsArray( $result );
		$this->assertSame( 'Cached point', $result['name'] );
	}
}
