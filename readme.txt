=== E-Transactions WooCommerce Payment Gateway ===
Contributors: E-Transactions
Donate link: none
Tags: Payment Gateway, Orders, woocommerce, e-commerce, payment, E-Transactions
Requires at least: 3.0.1
Tested up to: 5.7.2
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 2.6
WC tested up to: 5.4.1
This plugin is a E-Transactions payment gateway for WooCommerce 2.x

== Description ==

This module adds a E-Transactions Payment Gateway to your Installation of WooCommerce.

E-Transactions is a Payment Services Provider in Europe, part of the Crédit Agricole Bank.

plugin actions in wordpress:

this plugin offers an admin panel from the order section to the settings of Woocommerce.
it adds payment information to the orders details and changes the status of orders (upon reception of an IPN, see below.) and adds payment means on the checkout page.

This plugin takes information from the order and creates a form containing the details of the payment to be made, including parameters configured in the admin panel of the module that identify the mechant.

The plugin checks for availability of the E-Transactions platform, through a call to our servers.
It then submits with javascript the form to the first available server.

the customer is then presented with a payment page, hosted on the E-Transactions Platform (urls above).

The E-Transactions Platform sends an Instant Payment Notification (IPN) to the server when the customer actually made the payment, indicating to the merchant the status of the payment.

the plugin generates a url that can catch the IPN call from E-Transactions's server, filtering incoming calls to the E-Transactions IP address.

if payment is successfull, then the plugin validates the order though woocommerce.

== Installation ==

1. Upload the entire folder `woocommerce-etransactions` to the `/wp-content/plugins/` directory
or through WordPress's plugin upload/install mecanism.

2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= What is the HMAC key in the admin panel for ? =

The HMAC key is generated on etransactions server through your etransactions back office. it is used to authenticate your calls to E-Transactions Server. it is generated on the platform you choose: Production (live) or Pre-Production (test)

= My orders are not validated, even though the payment went through ? =

The oder paid with E-Transactions is only validated upon rectpion of a positive Instant Payment Notification (IPN) this IPN is authenticated with the filter on the IP address, if the IP is somewhow changed, the plugin will give a 500 HTTP error.
Avoid Maintenance mode, or allow E-Transactions IP to go through (194.2.122.158,195.25.7.166,195.101.99.76). If the WordPress Installation is in maintenance mode, the E-Transactions server will not be able to contact it.

= Something is not working for me, how can i get help ? =

Contact [E-Transactions WordPress Support](mailto:support@e-transactions.fr "WordPress support at support@e-transactions.fr"), we will be glad to help you out !

== Screenshots ==

1. The administration panel: payment configuration
2. The administration panel: E-Transactions Account parameters
3. The Checkout page: Payment method choice (1/ 3 times)
4. The Payment Means choice (hosted at E-Transactions)
5. The Payment page
6. Once successfully processed, the Payment transaction details appear in the order details

== Changelog ==
= 1.0 =
-Up2pay branding & Adding many features to the plugin:
-iFrame integration
-payment mean choice on the woocommerce checkout
-one-click payments
-debit upon change of status (when shipping for instance)
-settings is now fully sappable between production and testing: 
one can have 2 very different settings for both modes and review settings,
without changing the actual working mode for the website.

== Changelog ==
= 0.9.9.9.3 =
Force 3DSv2 for all cards

= 0.9.9.9.2 =
Add 3DSv2 support

= 0.9.8.9 =
various fixes and alignments

= 0.9.8.9 =
shortening long api names for plugin

= 0.9.8.8 =
fixing Woocommerce active detection mechanism

= 0.9.8.7 =
fixing mcrypt deprecation when goin with php > 7.1.x + translations and adding icon for payment means

= 0.9.8.6 =
Correcting transaction/call mixup and translation of minimal amount label.

= 0.9.8.5 =
adding HTTP/2 compatibility.

= 0.9.8.4 =
Correction for PHP7.2 mcrypt removal: openssl used if no mcrypt present.

= 0.9.8.3 =
Correction for potential HTTP 500 error: thx @.

= 0.9.8.2 =
Correction for network urls, order properties calling.

= 0.9.8.1 =
Correction of url called, to work for mobile.

== Changelog ==
= 0.9.8 =
Correction of minor bugs.

= 0.9.7.1 =
Correction of multisite wordpress bug.

= 0.9.7 =
Correction of a potential fatal error on error logging thx @vasyltech!.

Urls construct
= 0.9.6.9 =
Compatibility for folder-based wordpress mono-site.
Urls construct

= 0.9.6.8 =
Added compatibility for folder-based wordpress multi-site.
Removed IPN IP checking

= 0.9.6.7 =
Changed:
only rely on the $_SERVER data to check for the IP address:
this solves the non reception of the IPN  (error 500)

= 0.9.6.6 =
Second release:
Fixed:
-Missing table now created ok.
-"Syntax error: Unexpected token < " message when checking out,
-Use of deprecated functions to get pages url: now we use endpoints.

Added	:
-Informations about the payment on the order detail page, now actually displayed.
-3D Secure status properly rendered
-card numbers appear in the detail
-three time payment IPN properly stored


= 0.9.6.5 =
First stable release



== Upgrade Notice ==

= 1.0 =
This is the first major Release.

