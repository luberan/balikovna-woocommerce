<?php

use Balikovna_WC\Export;
use PHPUnit\Framework\TestCase;

final class Balikovna_Test_Export extends Export {
	public function encodeRow( array $row ) {
		$stream = fopen( 'php://memory', 'w+' );
		$this->fputcsv_cp1250( $stream, $row );
		rewind( $stream );
		return stream_get_contents( $stream );
	}

	public function destination( WC_Order $order, array $point, $service_code ) {
		return $this->destination_columns( $order, $point, $service_code );
	}

	public function weight( WC_Order $order ) {
		return $this->calc_weight( $order );
	}
}

final class ExportTest extends TestCase {
	public function test_formula_prefixes_are_escaped_after_transliteration(): void {
		$export = new Balikovna_Test_Export();
		$raw = $export->encodeRow( array( '＝HYPERLINK("https://example.test")', '＋1+1', '－1+1', '＠SUM(1,1)', '−1+1' ) );
		$values = str_getcsv( trim( $raw ), ';', '"', '' );
		foreach ( $values as $value ) {
			$this->assertStringStartsWith( "'", $value );
		}
	}

	public function test_post_office_destination_uses_selected_office(): void {
		$export = new Balikovna_Test_Export();
		$order  = new WC_Order( array(), array(), array( 'shipping_address_1' => 'Customer street', 'shipping_postcode' => '60200', 'shipping_city' => 'Brno' ) );
		$point  = array( 'id' => 'P10003', 'name' => 'Depo Praha 701', 'street' => 'Sazecska 7', 'zip' => '10003', 'city' => 'Praha' );
		$this->assertSame( array( 'Sazecska 7', '10003', 'Praha' ), $export->destination( $order, $point, 'NP' ) );
		$this->assertSame( array( 'Balíkova', 'B10000', '' ), $export->destination( $order, array( 'id' => 'B10000' ), 'NB' ) );
		$this->assertSame( array( 'Customer street', '60200', 'Brno' ), $export->destination( $order, array(), 'DR' ) );
	}

	public function test_weight_uses_order_snapshot_when_product_no_longer_exists(): void {
		$export = new Balikovna_Test_Export();
		$order  = new WC_Order(
			array(),
			array(),
			array(),
			array( new WC_Order_Item_Product( array( Balikovna_WC\Order::META_UNIT_WEIGHT => '2.500000' ), 2, null ) )
		);
		$this->assertSame( '5.00', $export->weight( $order ) );
	}
}
