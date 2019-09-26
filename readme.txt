=== Paid Memberships Pro - Stripe Billing Limits Add On ===
Contributors: strangerstudios
Tags: paid memberships pro, stripe, billing
Requires at least: 4.0
Tested up to: 5.2.3
Stable tag: .3

Allow Billing Limits with Stripe as your primary gateway.

== Description ==

This Add On allows you to charge a limited number of recurring subscription payments (installments) then stop the payments but maintain the user's membership. The membership can continue past the required number of payments until a specific membership expiration or indefinitely.

== Installation ==

1. Make sure you have the Paid Memberships Pro plugin installed and activated and that Stripe is your primary gateway.
1. Upload the `pmpro-stripe-billing-limits` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the GitHub issue tracker here: https://github.com/strangerstudios/pmpro-stripe-billing-limits/issues

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at https://www.paidmembershipspro.com for more documentation and our support forums.

== Changelog ==
= .3 =
* ENHANCEMENT: Updated Stripe Namespace.
* BUG FIX/ENHANCEMENT: Added try-catch around API requests to prevent Uncaught Stripe Error.
* First version with a readme.
