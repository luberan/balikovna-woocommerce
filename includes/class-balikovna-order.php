<?php
/**
 * Order meta, admin column, emails.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Order {

	const META_KEY = '_balikovna_point';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		// Admin order screen.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_after_shipping' ) );

		// Orders list column (HPOS + legacy).
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_orders_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_orders_column' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_orders_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_orders_column_legacy' ), 10, 2 );

		// Emails.
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email_after_order_table' ), 10, 4 );

		// Customer order details (thank you / my account).
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_details' ) );
	}

	public static function save_point_to_order( \WC_Order $order, array $point, $service_id = 'balikovna' ) {
		$order->update_meta_data( self::META_KEY, $point );
		$order->update_meta_data( '_balikovna_point_id', $point['id'] ?? '' );
		$order->update_meta_data( '_balikovna_point_name', $point['name'] ?? '' );
		$order->update_meta_data( '_balikovna_service', $service_id );
	}

	public static function get_point( \WC_Order $order ) {
		$p = $order->get_meta( self::META_KEY );
		return is_array( $p ) ? $p : array();
	}

	public static function format_point( array $point ) {
		if ( empty( $point ) ) {
			return '';
		}
		$lines = array();
		if ( ! empty( $point['name'] ) ) {
			$lines[] = $point['name'] . ( ! empty( $point['id'] ) ? ' (#' . $point['id'] . ')' : '' );
		}
		if ( ! empty( $point['street'] ) ) {
			$lines[] = $point['street'];
		}
		$cityzip = trim( ( $point['zip'] ?? '' ) . ' ' . ( $point['city'] ?? '' ) );
		if ( $cityzip ) {
			$lines[] = $cityzip;
		}
		return implode( "\n", $lines );
	}

	public function admin_after_shipping( $order ) {
		$point = self::get_point( $order );
		if ( empty( $point ) ) {
			return;
		}
		echo '<p><strong>' . esc_html__( 'Balíkovna - výdejní místo', 'balikovna-wc' ) . ':</strong><br>';
		echo nl2br( esc_html( self::format_point( $point ) ) ) . '</p>';
	}

	public function add_orders_column( $columns ) {
		$new = array();
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'shipping_address' === $k || 'order_total' === $k ) {
				$new['balikovna'] = __( 'Balíkovna', 'balikovna-wc' );
			}
		}
		if ( ! isset( $new['balikovna'] ) ) {
			$new['balikovna'] = __( 'Balíkovna', 'balikovna-wc' );
		}
		return $new;
	}

	public function render_orders_column( $column, $order ) {
		if ( 'balikovna' !== $column ) {
			return;
		}
		$point = self::get_point( $order );
		echo esc_html( $point['name'] ?? '—' );
	}

	public function render_orders_column_legacy( $column, $post_id ) {
		if ( 'balikovna' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			$this->render_orders_column( $column, $order );
		}
	}

	public function email_after_order_table( $order, $sent_to_admin, $plain_text, $email ) {
		$point = self::get_point( $order );
		if ( empty( $point ) ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n\n" . esc_html__( 'Balíkovna - výdejní místo', 'balikovna-wc' ) . ":\n";
			echo esc_html( self::format_point( $point ) ) . "\n";
			return;
		}
		echo '<h2>' . esc_html__( 'Balíkovna - výdejní místo', 'balikovna-wc' ) . '</h2>';
		echo '<p>' . nl2br( esc_html( self::format_point( $point ) ) ) . '</p>';
	}

	public function order_details( $order ) {
		$point = self::get_point( $order );
		if ( empty( $point ) ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Balíkovna - výdejní místo', 'balikovna-wc' ) . '</h2>';
		echo '<p>' . nl2br( esc_html( self::format_point( $point ) ) ) . '</p>';
	}
}
