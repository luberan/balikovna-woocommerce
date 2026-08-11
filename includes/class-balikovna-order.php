<?php
/**
 * Order meta, admin column, emails.
 *
 * @package Balikovna_WC
 */

namespace Balikovna_WC;

defined( 'ABSPATH' ) || exit;

class Order {

	const META_KEY              = '_balikovna_point';
	const META_PACKAGE_KEY      = '_balikovna_package_key';
	const META_RATE_ID          = '_balikovna_rate_id';
	const META_PACKAGE_WEIGHT   = '_balikovna_weight_kg';
	const META_PACKAGE_VALUE    = '_balikovna_contents_value';
	const META_UNIT_WEIGHT      = '_balikovna_unit_weight_kg';
	const META_DATA_VERSION     = '_balikovna_data_version';
	const META_PARCEL_TYPE      = 'balikovna_parcel_type';
	const META_TRACKING_NUMBER  = '_balikovna_tracking_number';
	const TRACKING_NONCE_ACTION = 'balikovna_save_tracking_numbers';
	const TRACKING_NONCE_NAME   = '_balikovna_tracking_nonce';
	const DATA_VERSION          = 3;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		add_action( 'woocommerce_checkout_create_order_shipping_item', array( $this, 'add_shipping_item_metadata' ), 10, 4 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'snapshot_line_item_weight' ), 10, 4 );
		add_action( 'woocommerce_before_order_item_object_save', array( $this, 'remove_stale_shipping_metadata' ), 10, 2 );
		add_action( 'woocommerce_saved_order_items', array( $this, 'refresh_order_summary' ), 10, 1 );

		// Admin order screen.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin_after_shipping' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_tracking_numbers' ), 35, 2 );

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

	/**
	 * Attach package-specific pickup data while WooCommerce creates a shipping item.
	 */
	public function add_shipping_item_metadata( $item, $package_key, $package, $order ) {
		if ( ! $item instanceof \WC_Order_Item_Shipping ) {
			return;
		}

		$service_id = (string) $item->get_method_id();
		$service    = Services::get( $service_id );
		if ( ! $service ) {
			return;
		}

		$rate_id = self::shipping_item_rate_id( $item );
		$item->update_meta_data( self::META_PACKAGE_KEY, (string) $package_key );
		$item->update_meta_data( self::META_RATE_ID, $rate_id );
		$item->update_meta_data( self::META_PACKAGE_WEIGHT, self::package_weight_kg( $package ) );
		$item->update_meta_data( self::META_PACKAGE_VALUE, self::package_contents_value( $package ) );
		$item->update_meta_data( self::META_DATA_VERSION, self::DATA_VERSION );

		if ( empty( $service['pickup'] ) ) {
			$item->delete_meta_data( self::META_KEY );
			return;
		}

		$selection = Checkout::get_session_selection( $package_key, $rate_id );
		if ( ! empty( $selection['point'] ) && Points::matches_service( $selection['point'], $service_id ) ) {
			$item->update_meta_data( self::META_KEY, $selection['point'] );
		} else {
			$item->delete_meta_data( self::META_KEY );
		}
	}

	/**
	 * Snapshot product weight on each order line for legacy/single-package fallback.
	 */
	public function snapshot_line_item_weight( $item, $cart_item_key, $values, $order ) {
		$product = isset( $values['data'] ) ? $values['data'] : null;
		if ( $product && is_callable( array( $product, 'get_weight' ) ) ) {
			$weight = '' !== (string) $product->get_weight()
				? wc_get_weight( (float) $product->get_weight(), 'kg' )
				: 0;
			$item->update_meta_data( self::META_UNIT_WEIGHT, wc_format_decimal( $weight, 6 ) );
		}
	}

	/**
	 * Remove a point when an administrator changes a shipping item to another service.
	 */
	public function remove_stale_shipping_metadata( $item, $data_store ) {
		if ( ! $item instanceof \WC_Order_Item_Shipping ) {
			return;
		}

		$service_id  = (string) $item->get_method_id();
		$service     = Services::get( $service_id );
		$point       = $item->get_meta( self::META_KEY, true );
		$parcel_type = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $item->get_meta( self::META_PARCEL_TYPE, true ) ) );
		if ( ! $service || empty( $service['pickup'] ) || ! is_array( $point ) || ! Points::matches_service( $point, $service_id ) ) {
			$item->delete_meta_data( self::META_KEY );
		}
		if ( $service && $parcel_type ) {
			$allowed_types = ! empty( $service['service_code_options'] )
				? (array) $service['service_code_options']
				: array( (string) ( $service['service_code'] ?? '' ) );
			if ( ! in_array( $parcel_type, $allowed_types, true ) ) {
				$item->delete_meta_data( self::META_PARCEL_TYPE );
			}
		}
		if ( ! $service ) {
			$item->delete_meta_data( self::META_PACKAGE_KEY );
			$item->delete_meta_data( self::META_RATE_ID );
			$item->delete_meta_data( self::META_PACKAGE_WEIGHT );
			$item->delete_meta_data( self::META_PACKAGE_VALUE );
			$item->delete_meta_data( self::META_PARCEL_TYPE );
			$item->delete_meta_data( self::META_TRACKING_NUMBER );
			$item->delete_meta_data( self::META_DATA_VERSION );
		}
	}

	/**
	 * Synchronize Store API draft shipping items with current session selections.
	 */
	public static function sync_shipping_points( \WC_Order $order ) {
		$selections    = Checkout::get_session_selections();
		$chosen_rates  = Checkout::chosen_shipping_rates();
		$items         = $order->get_shipping_methods();
		$assignments   = array();
		$explicit_keys = array();
		$used          = array();

		// Reserve every explicit package mapping before assigning legacy/unmarked items.
		foreach ( $items as $item_key => $item ) {
			if ( ! Services::get( (string) $item->get_method_id() ) ) {
				continue;
			}
			$rate_id     = self::shipping_item_rate_id( $item );
			$package_key = Checkout::normalize_package_key( $item->get_meta( self::META_PACKAGE_KEY, true ) );
			if ( null !== $package_key ) {
				$explicit_keys[ $item_key ] = true;
			}
			if ( null !== $package_key && ! isset( $used[ $package_key ] ) && isset( $chosen_rates[ $package_key ] ) && $rate_id === $chosen_rates[ $package_key ] ) {
				$assignments[ $item_key ] = $package_key;
				$used[ $package_key ]     = true;
			}
		}

		foreach ( $items as $item_key => $item ) {
			$service_id = (string) $item->get_method_id();
			$service    = Services::get( $service_id );
			if ( ! $service ) {
				$item->delete_meta_data( self::META_KEY );
				$item->delete_meta_data( self::META_PACKAGE_KEY );
				$item->delete_meta_data( self::META_RATE_ID );
				$item->delete_meta_data( self::META_PACKAGE_WEIGHT );
				$item->delete_meta_data( self::META_PACKAGE_VALUE );
				$item->delete_meta_data( self::META_PARCEL_TYPE );
				$item->delete_meta_data( self::META_TRACKING_NUMBER );
				$item->delete_meta_data( self::META_DATA_VERSION );
				$item->save();
				continue;
			}

			$rate_id     = self::shipping_item_rate_id( $item );
			$package_key = $assignments[ $item_key ] ?? null;
			if ( null === $package_key && ! isset( $explicit_keys[ $item_key ] ) ) {
				foreach ( $chosen_rates as $candidate_key => $chosen_rate_id ) {
					if ( ! isset( $used[ $candidate_key ] ) && $rate_id === $chosen_rate_id ) {
						$package_key            = $candidate_key;
						$used[ $candidate_key ] = true;
						break;
					}
				}
			}

			$item->update_meta_data( self::META_RATE_ID, $rate_id );
			$item->update_meta_data( self::META_DATA_VERSION, self::DATA_VERSION );
			if ( null !== $package_key ) {
				$item->update_meta_data( self::META_PACKAGE_KEY, $package_key );
			} else {
				$item->delete_meta_data( self::META_PACKAGE_KEY );
			}

			if ( ! empty( $service['pickup'] ) && null !== $package_key && ! empty( $selections[ $package_key ]['point'] ) && $rate_id === $selections[ $package_key ]['rateId'] ) {
				$item->update_meta_data( self::META_KEY, $selections[ $package_key ]['point'] );
			} else {
				$item->delete_meta_data( self::META_KEY );
			}
			$item->save();
		}

		self::sync_order_summary( $order, false );
	}

	/**
	 * Return each Czech Post shipping item and its own point.
	 *
	 * @return array<int,array>
	 */
	public static function get_shipments( \WC_Order $order, $allow_legacy = true ) {
		$shipments      = array();
		$legacy_point   = $order->get_meta( self::META_KEY );
		$legacy_service = (string) $order->get_meta( '_balikovna_service' );
		$legacy_used    = false;
		$shipping_items = $order->get_shipping_methods();
		foreach ( $shipping_items as $item ) {
			if ( $item->get_meta( self::META_DATA_VERSION, true ) ) {
				$allow_legacy = false;
				break;
			}
		}

		foreach ( $shipping_items as $item ) {
			$service_id = (string) $item->get_method_id();
			$service    = Services::get( $service_id );
			if ( ! $service ) {
				continue;
			}

			$point       = $item->get_meta( self::META_KEY, true );
			$point       = is_array( $point ) && Points::matches_service( $point, $service_id ) ? Points::sanitize( $point ) : array();
			$parcel_type = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $item->get_meta( self::META_PARCEL_TYPE, true ) ) );
			if ( '' === $parcel_type ) {
				$parcel_type = (string) ( $service['service_code'] ?? '' );
			}
			if ( $allow_legacy && ! $point && ! $legacy_used && $legacy_service === $service_id && is_array( $legacy_point ) ) {
				$point       = Points::sanitize( $legacy_point );
				$legacy_used = true;
			}

			$shipments[] = array(
				'item'           => $item,
				'serviceId'      => $service_id,
				'service'        => $service,
				'point'          => $point,
				'packageKey'     => (string) $item->get_meta( self::META_PACKAGE_KEY, true ),
				'rateId'         => self::shipping_item_rate_id( $item ),
				'weightKg'       => (string) $item->get_meta( self::META_PACKAGE_WEIGHT, true ),
				'contentsValue'  => (string) $item->get_meta( self::META_PACKAGE_VALUE, true ),
				'parcelType'     => $parcel_type,
				'trackingNumber' => self::sanitize_tracking_number( $item->get_meta( self::META_TRACKING_NUMBER, true ) ),
				'serviceCodes'   => (string) $item->get_meta( 'balikovna_service_codes', true ),
			);
		}

		return $shipments;
	}

	/**
	 * Keep legacy order metadata as a derived summary, never as source of truth.
	 */
	public static function sync_order_summary( \WC_Order $order, $allow_legacy = true ) {
		$shipments = self::get_shipments( $order, $allow_legacy );
		$order->delete_meta_data( self::META_KEY );
		$order->delete_meta_data( '_balikovna_point_id' );
		$order->delete_meta_data( '_balikovna_point_name' );
		$order->delete_meta_data( '_balikovna_service' );
		$order->delete_meta_data( '_balikovna_points' );

		$points = array();
		foreach ( $shipments as $shipment ) {
			if ( ! empty( $shipment['point']['id'] ) ) {
				$points[] = array(
					'serviceId'  => $shipment['serviceId'],
					'packageKey' => $shipment['packageKey'],
					'point'      => $shipment['point'],
				);
			}
		}

		if ( $points ) {
			$order->update_meta_data( '_balikovna_points', $points );
		}
		if ( 1 === count( $shipments ) && 1 === count( $points ) ) {
			$order->update_meta_data( self::META_KEY, $points[0]['point'] );
			$order->update_meta_data( '_balikovna_point_id', $points[0]['point']['id'] );
			$order->update_meta_data( '_balikovna_point_name', $points[0]['point']['name'] );
			$order->update_meta_data( '_balikovna_service', $points[0]['serviceId'] );
		}
	}

	public function refresh_order_summary( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::sync_order_summary( $order );
			$order->save();
		}
	}

	public static function shipping_item_rate_id( \WC_Order_Item_Shipping $item ) {
		$method_id   = (string) $item->get_method_id();
		$instance_id = (string) $item->get_instance_id();
		return '' !== $instance_id && '0' !== $instance_id ? $method_id . ':' . $instance_id : $method_id;
	}

	private static function package_weight_kg( $package ) {
		$weight = 0.0;
		$items  = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : array();
		foreach ( $items as $values ) {
			$product = isset( $values['data'] ) ? $values['data'] : null;
			if ( $product && '' !== (string) $product->get_weight() ) {
				$weight += (float) $product->get_weight() * (int) ( $values['quantity'] ?? 0 );
			}
		}
		return wc_format_decimal( wc_get_weight( $weight, 'kg' ), 6 );
	}

	private static function package_contents_value( $package ) {
		$value = 0.0;
		$items = isset( $package['contents'] ) && is_array( $package['contents'] ) ? $package['contents'] : array();
		foreach ( $items as $item ) {
			$value += (float) ( $item['line_total'] ?? 0 ) + (float) ( $item['line_tax'] ?? 0 );
		}
		return wc_format_decimal( max( 0.0, $value ), wc_get_price_decimals() );
	}

	public static function get_point( \WC_Order $order ) {
		foreach ( self::get_shipments( $order ) as $shipment ) {
			if ( ! empty( $shipment['point'] ) ) {
				return $shipment['point'];
			}
		}
		return array();
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

	public static function sanitize_tracking_number( $tracking_number ) {
		$tracking_number = strtoupper( sanitize_text_field( (string) $tracking_number ) );
		$tracking_number = preg_replace( '/[\s-]+/', '', $tracking_number );
		return is_string( $tracking_number ) && preg_match( '/^[A-Z0-9]{6,35}$/', $tracking_number )
			? $tracking_number
			: '';
	}

	public static function tracking_url( $tracking_number ) {
		$tracking_number = self::sanitize_tracking_number( $tracking_number );
		if ( '' === $tracking_number ) {
			return '';
		}
		$url = add_query_arg(
			'parcelNumbers',
			$tracking_number,
			'https://www.postaonline.cz/trackandtrace/-/zasilka/cislo'
		);
		return (string) apply_filters( 'balikovna_wc_tracking_url', $url, $tracking_number );
	}

	public function admin_after_shipping( $order ) {
		$shipments = self::get_shipments( $order );
		if ( ! $shipments ) {
			return;
		}
		wp_nonce_field( self::TRACKING_NONCE_ACTION, self::TRACKING_NONCE_NAME );
		echo '<p><strong>' . esc_html__( 'Česká pošta - zásilky', 'balikovna-wc' ) . ':</strong></p>';
		foreach ( $shipments as $shipment_index => $shipment ) {
			$shipment_label = sprintf(
				/* translators: 1: shipping service name, 2: shipment sequence number. */
				__( '%1$s - zásilka %2$d', 'balikovna-wc' ),
				$shipment['service']['label'],
				$shipment_index + 1
			);
			echo '<p><strong>' . esc_html( $shipment_label ) . ':</strong><br>';
			if ( ! empty( $shipment['point']['id'] ) ) {
				echo nl2br( esc_html( self::format_point( $shipment['point'] ) ) ) . '<br>';
			}
			$item_id = absint( $shipment['item']->get_id() );
			if ( $item_id ) {
				$field_id = 'balikovna-tracking-' . $item_id;
				echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html__( 'Podací číslo', 'balikovna-wc' ) . ':</label><br>';
				echo '<input type="text" class="short" id="' . esc_attr( $field_id ) . '" name="balikovna_tracking_numbers[' . esc_attr( (string) $item_id ) . ']" value="' . esc_attr( $shipment['trackingNumber'] ) . '" maxlength="35" autocomplete="off">';
			}
			if ( $shipment['trackingNumber'] ) {
				echo '<br><a href="' . esc_url( self::tracking_url( $shipment['trackingNumber'] ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sledovat zásilku', 'balikovna-wc' ) . '</a>';
			}
			echo '</p>';
		}
	}

	public function save_tracking_numbers( $order_id, $order_or_post = null ) {
		$nonce = isset( $_POST[ self::TRACKING_NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( $_POST[ self::TRACKING_NONCE_NAME ] ) )
			: '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::TRACKING_NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$order = $order_or_post instanceof \WC_Order ? $order_or_post : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$posted = isset( $_POST['balikovna_tracking_numbers'] ) && is_array( $_POST['balikovna_tracking_numbers'] )
			? map_deep( wp_unslash( $_POST['balikovna_tracking_numbers'] ), 'sanitize_text_field' )
			: array();
		foreach ( self::get_shipments( $order ) as $shipment ) {
			$item    = $shipment['item'];
			$item_id = (string) absint( $item->get_id() );
			if ( '0' === $item_id || ! array_key_exists( $item_id, $posted ) ) {
				continue;
			}
			$raw = sanitize_text_field( (string) $posted[ $item_id ] );
			if ( '' === trim( $raw ) ) {
				$item->delete_meta_data( self::META_TRACKING_NUMBER );
				$item->save();
				continue;
			}
			$tracking_number = self::sanitize_tracking_number( $raw );
			if ( '' === $tracking_number ) {
				if ( class_exists( '\\WC_Admin_Meta_Boxes' ) ) {
					\WC_Admin_Meta_Boxes::add_error( __( 'Podací číslo smí obsahovat pouze písmena a číslice.', 'balikovna-wc' ) );
				}
				continue;
			}
			$item->update_meta_data( self::META_TRACKING_NUMBER, $tracking_number );
			$item->save();
		}
	}

	public function add_orders_column( $columns ) {
		$new = array();
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'shipping_address' === $k ) {
				$new['balikovna'] = __( 'Česká pošta', 'balikovna-wc' );
			}
		}
		if ( ! isset( $new['balikovna'] ) ) {
			$new['balikovna'] = __( 'Česká pošta', 'balikovna-wc' );
		}
		return $new;
	}

	public function render_orders_column( $column, $order ) {
		if ( 'balikovna' !== $column ) {
			return;
		}
		$names = array();
		foreach ( self::get_shipments( $order ) as $shipment ) {
			if ( ! empty( $shipment['point']['name'] ) ) {
				$names[] = $shipment['point']['name'];
			}
			if ( $shipment['trackingNumber'] ) {
				$names[] = $shipment['trackingNumber'];
			}
		}
		echo esc_html( $names ? implode( ', ', $names ) : '—' );
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
		$shipments = self::visible_shipments( $order );
		if ( ! $shipments ) {
			return;
		}
		if ( $plain_text ) {
			echo "\n\n" . wp_strip_all_tags( __( 'Česká pošta - zásilky', 'balikovna-wc' ) ) . ":\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email; HTML escaping would expose entities.
			foreach ( $shipments as $shipment ) {
				echo wp_strip_all_tags( $shipment['service']['label'] ) . ":\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email; value is stripped of HTML.
				if ( ! empty( $shipment['point']['id'] ) ) {
					echo wp_strip_all_tags( self::format_point( $shipment['point'] ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email; value is stripped of HTML.
				}
				if ( $shipment['trackingNumber'] ) {
					echo wp_strip_all_tags( __( 'Podací číslo', 'balikovna-wc' ) ) . ': ' . $shipment['trackingNumber'] . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated alphanumeric tracking number.
					echo wp_strip_all_tags( __( 'Sledování zásilky', 'balikovna-wc' ) ) . ': ' . self::tracking_url( $shipment['trackingNumber'] ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URL is generated from a validated tracking number.
				}
			}
			return;
		}
		$this->render_points_html( $shipments );
	}

	public function order_details( $order ) {
		$shipments = self::visible_shipments( $order );
		if ( ! $shipments ) {
			return;
		}
		$this->render_points_html( $shipments );
	}

	private static function point_shipments( \WC_Order $order ) {
		return array_values(
			array_filter(
				self::get_shipments( $order ),
				function ( $shipment ) {
					return ! empty( $shipment['point']['id'] );
				}
			)
		);
	}

	private static function visible_shipments( \WC_Order $order ) {
		return array_values(
			array_filter(
				self::get_shipments( $order ),
				function ( $shipment ) {
					return ! empty( $shipment['point']['id'] ) || ! empty( $shipment['trackingNumber'] );
				}
			)
		);
	}

	private function render_points_html( array $shipments ) {
		echo '<h2>' . esc_html__( 'Česká pošta - zásilky', 'balikovna-wc' ) . '</h2>';
		foreach ( $shipments as $shipment ) {
			echo '<p><strong>' . esc_html( $shipment['service']['label'] ) . ':</strong><br>';
			if ( ! empty( $shipment['point']['id'] ) ) {
				echo nl2br( esc_html( self::format_point( $shipment['point'] ) ) );
			}
			if ( $shipment['trackingNumber'] ) {
				if ( ! empty( $shipment['point']['id'] ) ) {
					echo '<br>';
				}
				echo '<strong>' . esc_html__( 'Podací číslo', 'balikovna-wc' ) . ':</strong> ' . esc_html( $shipment['trackingNumber'] );
				echo '<br><a href="' . esc_url( self::tracking_url( $shipment['trackingNumber'] ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sledovat zásilku', 'balikovna-wc' ) . '</a>';
			}
			echo '</p>';
		}
	}
}
