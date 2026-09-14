=== Daraja M-Pesa Gateway for WooCommerce ===
Contributors: steven-ongati
Tags: woocommerce, mpesa, daraja, payments, kenya
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Direct Safaricom Daraja STK Push payments with durable, exact-amount WooCommerce reconciliation.

== Description ==

Daraja M-Pesa Gateway for WooCommerce initiates M-Pesa Express STK Push requests and keeps orders unpaid until correlated provider evidence passes exact amount and receipt checks.

Key capabilities:

* direct Safaricom Daraja sandbox and live integration;
* classic checkout and WooCommerce Checkout Blocks;
* High-Performance Order Storage compatibility;
* Kenyan Safaricom phone normalization;
* server-side STK Query polling with Action Scheduler;
* protected, idempotent callbacks;
* exact current-order amount validation;
* unique receipt enforcement;
* administrator-only manual verification with fresh Daraja status;
* private, read-only customer payment status;
* bounded, redacted WooCommerce logs.

An accepted STK Push or a successful STK Query is not treated as proof of exact payment. Callback loss, timeout, amount mismatch, duplicate receipt, or a superseded attempt keeps the order unpaid for review.

This independent open-source project is not affiliated with, endorsed by, or sponsored by Safaricom PLC, Automattic Inc., or WooCommerce.

== Installation ==

1. Upload the plugin ZIP through **Plugins > Add New Plugin > Upload Plugin**.
2. Activate the plugin.
3. Open **WooCommerce > Settings > Payments > Daraja M-Pesa**.
4. Select the Daraja sandbox environment.
5. Enter the consumer key, consumer secret, business shortcode, M-Pesa Express passkey, and merchant account type.
6. Confirm that the store currency is KES and the WordPress site is publicly reachable over HTTPS.
7. Enable the gateway and complete sandbox verification before using live credentials.

The plugin creates the protected callback URL automatically.

== Frequently Asked Questions ==

= Does the browser poll Safaricom? =

No. Browser refresh is read-only and displays bounded local state. Action Scheduler performs provider status queries on the server.

= Does an STK Push acceptance mark the order paid? =

No. Automatic settlement requires correlated success, the exact current WooCommerce order amount, and a unique nonempty receipt.

= What happens when the callback is lost? =

The plugin performs bounded STK Query polling. Query success without exact amount and receipt evidence remains unpaid and moves to administrator review.

= Can an administrator manually mark an order paid? =

Only through the protected verification workflow. It requires independent receipt and amount evidence, performs a fresh correlated Daraja query, checks the current order amount and receipt uniqueness, and records an audit entry.

= Can a customer retry after a timeout? =

Not automatically. A timeout does not prove that no money moved. The merchant must investigate the authoritative Safaricom record first. Definitive cancellation or failure may start a distinct new attempt.

= What data is logged? =

Bounded operational events and attempt identifiers. Credentials, access tokens, raw callbacks, phone numbers, and receipts are redacted.

== Changelog ==

= 0.1.0 =

* Initial pre-release implementation.
* Added direct Daraja OAuth, STK Push, and STK Query support.
* Added durable payment attempts, exact settlement checks, and callback idempotency.
* Added Checkout Blocks, HPOS declarations, customer status, and audited manual verification.
