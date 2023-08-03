<?php
/*
 * Plugin Name:       Stripe Processing Fee To Woocommerce
 * Plugin URI:        https://kiwiwebsitedesign.nz/
 * Description:       Adds the stripe processing fee to woocommerce orders for Fundraising Products on checkout
 * Version:           1.0
 * Author:            Babar
 * Author URI:        https://kiwiwebsitedesign.nz/
 * License:           GPL v2 or later
 */



/**
 * Add a 2.7% surcharge when Stripe is selected on the checkout page.
 */
function add_stripe_surcharge( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) )
			return;

	$chosen_payment_method = WC()->session->get( 'chosen_payment_method' );

	if ( 'stripe' === $chosen_payment_method ) {
			if ( WC()->session->__isset( 'surcharge' ) ) {
					$cart->add_fee( __( 'Stripe Processing Fee', 'your-text-domain' ), WC()->session->get( 'surcharge' ) );
			}
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'add_stripe_surcharge', 20, 999999 );

/**
* Calculate the 2.7% surcharge based on the cart total.
*/
function calculate_stripe_surcharge() {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) )
			return;

  $fundraising_product;

  // Loop over $cart items
  foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
    $product = $cart_item['data'];
    $product_id = $cart_item['product_id'];
    $variation_id = $cart_item['variation_id'];
    $quantity = $cart_item['quantity'];
    $price = WC()->cart->get_product_price( $product );
    $subtotal = WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] );
    $link = $product->get_permalink( $cart_item );
    // Anything related to $product, check $product tutorial
    $attributes = $product->get_attributes();
    $whatever_attribute = $product->get_attribute( 'whatever' );
    $whatever_attribute_tax = $product->get_attribute( 'pa_whatever' );
    $any_attribute = $cart_item['variation']['attribute_whatever'];
    $meta = wc_get_formatted_cart_item_data( $cart_item );

    $terms = get_the_terms( $product_id, 'product_cat' );

    foreach( $terms as $term ) {
      if ( $term->slug == 'fundraising-products' ) {
        $fundraising_product = true;
      } else {
        $fundraising_product = false;
      }
    }
  }

  if ( $fundraising_product == true ) {
    $shipping_total = (float)WC()->cart->get_shipping_total();
    $cart_subtotal = WC()->cart->subtotal;
    $cart_total = $shipping_total + $cart_subtotal;
  
    $surcharge_rate = 2.7; // Change this value if you want to use a different surcharge rate.
  
    // Calculate the surcharge based on the cart total.
    $surcharge = ( $surcharge_rate / 100 ) * $cart_total;
    $surcharge_final = $surcharge + 0.30;
  } else {
    $surcharge_final = 0;
  }
  

	// Set the surcharge to the session for later use.
	WC()->session->set( 'surcharge', $surcharge_final );
	WC()->session->set( 'chosen_payment_method', WC()->session->get( 'chosen_payment_method' ) );
}
add_action( 'woocommerce_cart_calculate_fees', 'calculate_stripe_surcharge', 10, 99999 );

/**
* Enqueue script to handle payment method changes via Ajax.
*/
function custom_checkout_scripts() {
	if ( is_checkout() && ! is_wc_endpoint_url() ) {
			wc_enqueue_js( "
					jQuery( 'form.checkout' ).on( 'change', 'input[name=payment_method]', function() {
							jQuery.ajax({
									type: 'POST',
									url: '" . WC()->ajax_url() . "',
									data: {
											'action': 'update_payment_method',
											'payment_method': jQuery( 'input[name=payment_method]:checked' ).val(),
									},
									success: function( result ) {
											jQuery( document.body ).trigger( 'update_checkout' );
									}
							});
					});
			" );
	}
}
add_action( 'wp_enqueue_scripts', 'custom_checkout_scripts' );

/**
* Update surcharge when payment method is changed via Ajax.
*/
function update_stripe_surcharge_ajax() {
	if ( isset( $_POST['payment_method'] ) ) {
			$chosen_payment_method = sanitize_key( $_POST['payment_method'] );
			WC()->session->set( 'chosen_payment_method', $chosen_payment_method );

			if ( 'stripe' !== $chosen_payment_method ) {
					WC()->session->set( 'surcharge', 0 ); // Reset surcharge when payment method changed.
			}

			calculate_stripe_surcharge(); // Recalculate the surcharge based on the chosen payment method.
			WC()->cart->calculate_totals(); // Update the cart totals.
	}
}
add_action( 'wp_ajax_update_payment_method', 'update_stripe_surcharge_ajax' );
add_action( 'wp_ajax_nopriv_update_payment_method', 'update_stripe_surcharge_ajax' );
