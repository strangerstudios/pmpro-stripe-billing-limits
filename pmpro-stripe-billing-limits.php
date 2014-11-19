<?php
/*
Plugin Name: PMPro Stipe Billing Limits
Plugin URI: http://www.paidmembershipspro.com/add-ons/pmpro-stripe-billing-limits/
Description: Allow billing limits with Stripe, where the Stripe subscription is cancelled, but the PMPro membership is not after X payments.
Version: .2
Author: strangerstudios
Author URI: http://www.strangerstudios.com
*/
/*
	The Plan
	* Hook into pmpro_after_checkout
	* If $level->billing_limit > 0 and gateway == stripe, save user meta with level id, Stripe sub id, billing limits #
	
	* Hook into new recurring order created.
	* Check for user meta RE billing limits.
	* Check if X successful payments have been made within the same sub id.
	* If so, cancel the subscription. BUT NOT the membership.
	
	* Going to leave the built-in PMPro warnings for now.
*/
//save some user meta after checkout to track billing limit
function pmprosbl_pmpro_after_checkout($user_id)
{
	//what level?
	global $pmpro_level, $gateway;
	
	//get order
	$order = new MemberOrder();
	$order->getLastMemberOrder($user_id);
	
	//billing limit? gateway stripe?
	if($pmpro_level->billing_limit > 0 && $order->gateway == "stripe")
	{
		//customer id is on order
		$customer_id = $order->subscription_transaction_id;
		
		//get sub id via Stripe API
		$subscription = pmprosbl_getStripeSubscription($customer_id, $order->code);
		
		//no sub? return
		if(!empty($subscription))
		{		
			$subscription_id = $subscription->id;
		
			//build array to save
			$pmpro_stripe_billing_limit = array(
				'user_id' => $user_id,			
				'level_id' => $pmpro_level->id,
				'billing_limit' => $pmpro_level->billing_limit,
				'customer_id' => $customer_id,
				'subscription_id' => $subscription_id,
				'payments' => 0	//start with 0
			);
			
			//save it
			update_user_meta($user_id, "pmpro_stripe_billing_limit", $pmpro_stripe_billing_limit);
			
			return;
		}
	}
	
	//if we got here, make sure to clear out any old billing limit
	delete_user_meta($user_id, "pmpro_stripe_billing_limit");
}
add_action('pmpro_after_checkout', 'pmprosbl_pmpro_after_checkout');

//check billing limit with each new order
function pmprosbl_pmpro_added_order($order)
{
	//new stripe order?
	if($order->gateway == "stripe")
	{
		//billing limit on this one?
		$pmpro_stripe_billing_limit = get_user_meta($order->user_id, "pmpro_stripe_billing_limit", true);
		
		if(!empty($pmpro_stripe_billing_limit))
		{
			//hit limit?
			if(empty($pmpro_stripe_billing_limit['cancelled']) && $pmpro_stripe_billing_limit['payments'] >= $pmpro_stripe_billing_limit['billing_limit'])
			{
				//cancel the subscription
				$customer = Stripe_Customer::retrieve($pmpro_stripe_billing_limit['customer_id']);
				$subscription = $customer->subscriptions->retrieve($pmpro_stripe_billing_limit['subscription_id']);
				$subscription->cancel();
				
				//make sure we don't try to cancel again
				$pmpro_stripe_billing_limit['payments']++;
				$pmpro_stripe_billing_limit['cancelled'] = true;
				update_user_meta($order->user_id, "pmpro_stripe_billing_limit", $pmpro_stripe_billing_limit);
			}
			else
			{
				//update the # of payments
				$pmpro_stripe_billing_limit['payments']++;
				update_user_meta($order->user_id, "pmpro_stripe_billing_limit", $pmpro_stripe_billing_limit);
			}
		}
	}
}
add_action('pmpro_added_order', 'pmprosbl_pmpro_added_order');

//don't do anything when a stripe subscription is deleted if the user had a billing limit
function pmprosbl_pmpro_stripe_subscription_deleted($user_id)
{
	//billing limit?
	$pmpro_stripe_billing_limit = get_user_meta($user_id, "pmpro_stripe_billing_limit", true);
	
	if(!empty($pmpro_stripe_billing_limit) && !empty($pmpro_stripe_billing_limit['cancelled']))
	{	
		global $logstr;
		$logstr .= "Subscription user ID #" . $user_id . " has hit its billing limit. Subscription deleted.";
		pmpro_stripeWebhookExit();
	}
}
add_action('pmpro_stripe_subscription_deleted', 'pmprosbl_pmpro_stripe_subscription_deleted', 1);

/*
	Get a Stripe Subscription for customer with id = customer_id and plan = plan_id.
	If no plan_id is given, the first subscription will be returned.
*/
function pmprosbl_getStripeSubscription($customer_id, $plan_id = false)
{
	//make sure Stripe is loaded
	if(!class_exists("Stripe"))
		require_once(PMPRODIR . "/includes/lib/Stripe/Stripe.php");
		
	//find the customer
	$stripe = new PMProGateway_stripe("stripe");
	
	$customer = Stripe_Customer::retrieve($customer_id);
				
	if(!empty($customer))
	{
		//find subscription with this order code
		$subscriptions = $customer->subscriptions->all();												
							
		if(!empty($subscriptions))
		{					
			//no plan given? return the first
			if(empty($plan_id))
				return $subscriptions->data[0];
			
			//find sub with the same plan id
			foreach($subscriptions->data as $sub)
			{						
				if($sub->plan->id == $plan_id)
				{
					//found it
					return $sub;
				}
			}
		}
	}
	
	return false;
}