<?php
/*
Plugin Name: Paid Memberships Pro - Stripe Billing Limits
Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-stripe-billing-limits/
Description: Allow Billing Limits with Stripe as your primary gateway.
Version: 1.0
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
*/

/**
 * When an order is saved, check if we've reached the billing limit for the subscription.
 * If so, cancel the subscription in Stripe.
 *
 * @since TBD
 *
 * @param MemberOrder $order The order object.
 */
function pmprosbl_pmpro_added_order( $order ) {
	// If we haven't reached the billing limit for this order, we don't need to do anything here.
	if ( ! pmprosbl_is_billing_limit_reached( $order ) ) {
		return;
	}

	// Billing limit has been reached, so cancel the subscription.
	$stripe = new PMProGateway_stripe(); // Make sure that Stripe is loaded.
	try {
		$subscription = \Stripe\Subscription::retrieve( $order->subscription_transaction_id );
		$subscription->cancel();
	} catch ( Exception $e ) {
		// There was an error. Let's not do anything for now.
	}

	// Clean up old "pmpro_stripe_billing_limit" metadata from old versions of this plugin.
	delete_user_meta( $order->user_id, 'pmpro_stripe_billing_limit' );
}
add_action('pmpro_added_order', 'pmprosbl_pmpro_added_order');
add_action('pmpro_updated_order', 'pmprosbl_pmpro_added_order');

/**
 * When a subcsription is deleted in Stripe, check if we deleted it because of a billing limit.
 * If so, we don't want to cancel the user's membership.
 *
 * @since TBD
 *
 * @param int $user_id The user ID.
 */
function pmprosbl_pmpro_stripe_subscription_deleted( $user_id ) {
	// Unfortunately, we don't have a way to get the subscription being deleted in this hook.
	// So let's get the user's most recent successful stripe order and assume that's the one being deleted.
	// This should be safe since we only cancel subscriptions when an order is saved that passes the billing limit.
	$order = new MemberOrder();
	$order->getLastMemberOrder( $user_id, 'success', NULL, 'stripe' );

	// If we haven't reached the billing limit for this order, we should let the membership be cancelled as normal.
	if ( ! pmprosbl_is_billing_limit_reached( $order ) ) {
		return;
	}

	// We deleted this subscription because of a billing limit, so we don't want to cancel the user's membership.
	// Let's log a messsage and exit the webhook handler.
	global $logstr;
	$logstr .= "Subscription user ID #" . $user_id . " has hit its billing limit. Subscription deleted.";
	pmpro_stripeWebhookExit();
}
add_action( 'pmpro_stripe_subscription_deleted', 'pmprosbl_pmpro_stripe_subscription_deleted', 1 );

/**
 * Helper function to check if the billing limit has been reached for a given Stripe order.
 *
 * @since TBD
 *
 * @param MemberOrder $order The order object.
 * @return bool True if the billing limit has been reached, false otherwise.
 */
function pmprosbl_is_billing_limit_reached( $order ) {
	// Check if this is a Stripe order.
	if ( 'stripe' !== $order->gateway ) {
		return false;
	}

	// Check if this order was successful.
	if ( 'success' !== $order->status ) {
		return false;
	}

	// Check if this order has a subscription ID.
	if ( empty( $order->subscription_transaction_id ) ) {
		return false;
	}

	// Make sure that this order has a user ID.
	if ( empty( $order->user_id ) ) {
		return false;
	}

	// Check if the level for this order has a billing limit.
	$level = $order->getMembershipLevel();
	if ( empty( $level ) || empty( $level->billing_limit ) ) {
		return false;
	}

	// Get the number of successful payments for this subscription.
	global $wpdb;
	$order_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->pmpro_membership_orders WHERE gateway = 'stripe' AND status = 'success' AND subscription_transaction_id = %s AND user_id = %s", $order->subscription_transaction_id, $order->user_id ) );

	// Check if we are still below the limit.
	// Note that the initial pamyent is counted in the order count, but not the billing limit. So if these are equal, we shouldn't cancel the subscription yet.
	if ( $order_count <= $level->billing_limit ) {
		return false;
	}

	return true;
}

/**
  * Function to add links to the plugin row meta
  *
  * @param array  $links Array of links to be shown in plugin meta.
  * @param string $file Filename of the plugin meta is being shown for.
  */
function pmprosbl_plugin_row_meta( $links, $file ) {
 	if ( strpos( $file, 'pmpro-stripe-billing-limits.php' ) !== false ) {
 		$new_links = array(
 			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/pmpro-stripe-billing-limits/' ) . '" title="' . esc_attr( __( 'View Documentation', 'pmpro-stripe-billing-limits' ) ) . '">' . esc_html__( 'Docs', 'pmpro-stripe-billing-limits' ) . '</a>',
 			'<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro-stripe-billing-limits' ) ) . '">' . esc_html__( 'Support', 'pmpro-stripe-billing-limits' ) . '</a>',
 		);
 		$links = array_merge( $links, $new_links );
 	}
 	return $links;
}
add_filter( 'plugin_row_meta', 'pmprosbl_plugin_row_meta', 10, 2 );
