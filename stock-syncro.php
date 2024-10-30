<?php
/**
 * This File is sending the stock change over the API to KASSA.AT, as well as
 * requesting the stock-amounts from KASSA.AT whenever a customer calls the
 * one-article-view or his cart.
 *
 * @package KASSA.AT For WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly!
}

/**
 * Activates or deactivates different parts of the Code so the user
 * can decide when he want the stocks to synchronize.
 *
 * There are three times that he can enable or disable individually (
 * - 'kaw-synchronize-at-singleproduct',
 * - 'kaw-synchronize-at-cart',
 * - 'kaw-synchronize-on-order'.
 *
 * The function writes eighter 'enabled' or 'disabled' in the correct wp_option
 * and forces a logentry about the change.
 */
function kaw_activate_synchro_option() {
	if ( ! isset( $_POST['mode'] ) || ! isset( $_POST['field'] ) ) { /* phpcs:ignore */
		wp_send_json_error();
	}

	if ( $_POST['mode'] == 'true' ) { /* phpcs:ignore */
		$val = 'enabled';
	} else {
		$val = 'disabled';
	}

	$field = $_POST['field']; /* phpcs:ignore */

	if ( get_option( $field ) ) {
		update_option( $field, $val );
	} else {
		add_option( $field, $val );
	}

	kaw_log_data(
		'SYSTEM',
		array(
			'message'  => $val . ' the synchronization: ' . $field,
			'location' => kaw_get_locationstring(),
		),
		true
	);

	wp_send_json_success();
}
add_action( 'wp_ajax_kaw_activate_synchro_option', 'kaw_activate_synchro_option' );

/**
 * An easy little function to return the stockamount requested from the KASSA.AT-API.
 *
 * @param string $product The article-number (sku) of the product, you want to retrieve the stock.
 * @return string
 */
function kaw_get_stock_amount( $product ) {
	$inventories_path = '/warehouses/' . get_option( 'kaw-warehouse' ) . '/inventories';
	$inventory        = kaw_call_api(
		'GET',
		$inventories_path,
		array(
			'article_number' => $product->get_sku(),
			'active'         => 'true',
			'inspect_stock'  => 'true',
		)
	);

	if ( 1 !== $inventory->length ) {
		kaw_log_data(
			'API-ERROR',
			array(
				'message'  => 'Wrong number of found entries {' . strval( $inventory->length ) . '}',
				'location' => kaw_get_locationstring(),
			)
		);
		return false;
	} else {
		return $inventory->details[0]->stock;
	}
}

/**
 * Send the data from the line-items to KASSA.AT to let its API know, that the
 * stock_amount has changed.
 *
 * @param object $order WooCommerce-order so we know the line-items.
 * @return string
 */
function kaw_reduce_stock( $order ) {
	$warehouse = get_option( 'kaw-warehouse' );

	if ( ! $order || get_option( 'woocommerce_manage_stock' ) !== 'yes' ) {
		return;
	}

	if ( get_option( 'kaw-synchronize-on-order' ) === 'disabled' ) {
		return;
	}

	$changes = array();

	/* Loop over all items */
	foreach ( $order->get_items() as $item ) {
		if ( ! $item->is_type( 'line_item' ) ) {
			continue;
		}

		$product            = $item->get_product();
		$item_stock_reduced = $item->get_meta( '_reduced_stock', true );
		if ( ! $product || ! $product->managing_stock() ) {
			continue;
		}
		if ( ! $item_stock_reduced ) {
			continue;
		}

		$qty              = apply_filters( 'woocommerce_order_item_quantity', $item->get_quantity(), $order, $item );
		$item_name        = $product->get_formatted_name();
		$inventories_path = '/warehouses/' . $warehouse . '/inventories';
		$inventory        = kaw_call_api(
			'GET',
			$inventories_path,
			array(
				'article_number' => $product->sku,
				'active'         => 'true',
			)
		);

		if ( 1 !== $inventory->length ) {
			continue;
		} else {
			$inventory_id = $inventory->details[0]->id;
		}

		$movement = kaw_call_api(
			'POST',
			$inventories_path . '/' . $inventory_id . '/movements',
			array(
				'quantity' => $qty,
				/* translators: The placeholder %s is to send the order-id! */
				'text'     => sprintf( esc_attr( __( 'Woocommerce Invoice: %s', 'kassa-at-for-woocommerce' ) ), esc_attr( $order->id ) ),
				'type'     => 'decrease',
			)
		);
	}
}
add_action( 'woocommerce_reduce_order_stock', 'kaw_reduce_stock' );

/**
 * Retrieve the stock-amounts from KASSA.AT and overwrite the WooCommerce ones
 * with the KASSA.AT ones.
 */
function kaw_synchronize_k_to_w() {
	$args = array(
		'post_type'  => array( 'product', 'product_variation' ),
		'meta_query' => array( /* phpcs:ignore */
			array(
				'key'     => '_manage_stock',
				'value'   => 'yes',
				'compare' => '=',
			),
		),
	);

	$loop = new WP_Query( $args );

	while ( $loop->have_posts() ) :
		$loop->the_post();
		global $product;
		$stock_amount = kaw_get_stock_amount( $product );
		$old_stock    = get_post_meta( $product->get_id(), '_stock' );

		if ( $stock_amount ) {
			update_post_meta( $product->get_id(), '_stock', $stock_amount );
			kaw_log_data(
				'DATA-UPDATE',
				array(
					'message'  => 'Stock amount updated ',
					'key'      => $product->get_name() . ' (' . $product->get_sku() . ')',
					'original' => strval( $old_stock[0] ),
					'updated'  => strval( $stock_amount ),
					'location' => kaw_get_locationstring(),
				)
			);
			kaw_update_stock_status( $product, $old_stock[0], $stock_amount );
		}
	endwhile;

	wp_reset_postdata();
}

/**
 * When single-product view is loading, ask KASSA.AT through the API for the
 * correct stock-amount and use them in the database.
 * Once this is done, reload the page to fully take on the changes.
 */
function kaw_syncronize_on_single_product() {
	global $product;

	if ( get_option( 'kaw-synchronize-at-singleproduct' ) === 'disabled' ) {
		return;
	}

	$stock_amount = kaw_get_stock_amount( $product );
	$old_stock    = get_post_meta( $product->get_id(), '_stock' );

	if ( $stock_amount ) {
		$product->set_stock( $stock_amount );
		kaw_log_data(
			'DATA-UPDATE',
			array(
				'message'  => 'Stock amount updated ',
				'key'      => $product->get_name() . ' (' . $product->get_sku() . ')',
				'original' => strval( $old_stock[0] ),
				'updated'  => strval( $stock_amount ),
				'location' => kaw_get_locationstring(),
			)
		);
		kaw_update_stock_status( $product, $old_stock[0], $stock_amount );
	}

	$variations = $product->get_children();
	foreach ( $variations as $value ) {
		$variation = new WC_Product_Variation( $value );
		if ( get_post_meta( $variation->get_id(), '_manage_stock' ) ) {
			$stock_amount = kaw_get_stock_amount( $variation );
			$old_stock    = get_post_meta( $variation->get_id(), '_stock' );

			if ( $stock_amount ) {
				$variation->set_stock( $stock_amount );
				update_post_meta( $variation->get_id(), '_stock', $stock_amount );
				kaw_log_data(
					'DATA-UPDATE',
					array(
						'message'  => 'Stock amount updated ',
						'key'      => $variation->get_name() . ' (' . $variation->get_sku() . ')',
						'original' => strval( $old_stock[0] ),
						'updated'  => strval( $stock_amount ),
						'location' => kaw_get_locationstring(),
					)
				);
				kaw_update_stock_status( $variation, $old_stock[0], $stock_amount );
			}
		}
	}
}
add_action( 'woocommerce_before_single_product', 'kaw_syncronize_on_single_product' );

/**
 * When cart view is loading, ask KASSA.AT through the API for the correct
 * stock-amountss for all articles inside and use them in the database.
 * Once this is done, reload the page to fully take on the changes.
 */
function kaw_synchronize_in_cart() {
	global $woocommerce;

	$items = $woocommerce->cart->get_cart();

	if ( get_option( 'woocommerce_manage_stock' ) !== 'yes' ) {
		return;
	}

	if ( get_option( 'kaw-synchronize-at-cart' ) === 'disabled' ) {
		return;
	}

	foreach ( $items as $item => $values ) {
		$product = wc_get_product( $values['product_id'] );
		if ( $values['variation_id'] ) {
			$product = wc_get_product( $values['variation_id'] );
		}
		if ( ! $product->get_manage_stock() ) {
			continue;
		}

		$stock_amount = kaw_get_stock_amount( $product );
		$old_stock    = get_post_meta( $product->get_id(), '_stock' );

		if ( $stock_amount ) {
			$product->set_stock( $stock_amount );
			kaw_log_data(
				'DATA-UPDATE',
				array(
					'message'  => 'Stock amount updated ',
					'key'      => $product->get_name() . ' (' . $product->get_sku() . ')',
					'original' => strval( $old_stock[0] ),
					'updated'  => strval( $stock_amount ),
					'location' => kaw_get_locationstring(),
				)
			);
			kaw_update_stock_status( $product, $old_stock[0], $stock_amount );
		}
	}
}
add_action( 'woocommerce_before_cart_table', 'kaw_synchronize_in_cart' );

/**
 * Update the stock-status when the stock is updated through the kaw plugin.
 *
 * @param object $product WooCommerce-article so we which article we are checking.
 * @param mixed  $old_amount the stock-amount before the update.
 * @param mixed  $new_amount the stock-amount after the update.
 */
function kaw_update_stock_status( $product, $old_amount, $new_amount ) {
	$old_amount = floatval( $old_amount );
	$new_amount = floatval( $new_amount );

	if ( $product->backorders_allowed() ) {
		if ( $new_amount <= floatval( 0 ) ) {
			$status = 'onbackorder';
		}
	} else {
		if ( $old_amount === $new_amount && $new_amount > 0 ) {
			$status = 'instock';
		} elseif ( $old_amount > floatval( 0 ) && $new_amount <= floatval( 0 ) ) {
			$status = 'outofstock';
		} elseif ( $old_amount <= floatval( 0 ) && $new_amount > floatval( 0 ) ) {
			$status = 'instock';
		} elseif ( $old_amount === $new_amount && floatval( 0 ) === $new_amount && 'instock' === $product->get_stock_status() ) {
			$status = 'outofstock';
		}
	}

	if ( isset( $status ) ) {
		$old_status = $product->get_stock_status();
		$product->set_stock_status( $status );
		kaw_log_data(
			'DATA-UPDATE',
			array(
				'message'  => 'Stock status updated',
				'key'      => $product->get_name() . ' (' . $product->get_sku() . ')',
				'original' => $old_status,
				'updated'  => $status,
				'location' => kaw_get_locationstring(),
			)
		);
	}
}
