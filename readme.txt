=== FimiPay for WooCommerce ===
Contributors: fimipay
Donate link: https://fimipay.com
Tags: woocommerce, payments, mobile money, mpesa, tanzania, fimipay, ussd
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept mobile money payments via FimiPay Push USSD on your WooCommerce checkout.

== Description ==

**FimiPay for WooCommerce** lets merchants collect mobile money payments through FimiPay’s Merchant API. At checkout, a Push USSD request is sent to the customer’s phone so they can approve payment.

= Features =

* WooCommerce payment gateway (classic + Blocks checkout)
* Push USSD mobile money collection
* Test (`sk_test_`) and live (`sk_live_`) API keys
* HMAC-signed webhooks (`X-Fimipay-Signature`)
* Automatic status polling fallback
* HPOS compatible

= Backend settings =

After activation, configure the gateway at:

**WooCommerce → Settings → Payments → FimiPay**

Settings include: Enable/Disable, Title, Description, Secret key, Webhook secret, Webhook URL, API base URL, Test outcome, and Debug log.

= Requirements =

* WordPress 6.0+
* WooCommerce 7.0+
* A [FimiPay](https://fimipay.com) merchant account with an API secret key

= Third-party service =

This plugin connects to the FimiPay API (`https://fimipay.com/api/v1`) to create payments, check order status, and receive webhooks. Order amount, currency, customer name, email, and phone number are sent to FimiPay when a customer pays. See [fimipay.com](https://fimipay.com) for FimiPay’s terms and privacy policy.

== Installation ==

1. Upload the `fimipay-woocommerce` folder to `/wp-content/plugins/`, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate **FimiPay for WooCommerce** through the **Plugins** menu.
3. Go to **WooCommerce → Settings → Payments → FimiPay**.
4. Enter your FimiPay secret key (`sk_test_…` or `sk_live_…`) and webhook secret.
5. Copy the webhook URL shown in settings into your FimiPay app webhook configuration.
6. Enable the gateway and place a test order.

== Frequently Asked Questions ==

= Where are the backend settings? =

**WooCommerce → Settings → Payments → FimiPay**

(Direct path: `wp-admin/admin.php?page=wc-settings&tab=checkout&section=fimipay`)

= Is there a separate sandbox URL? =

No. Use the same base URL (`https://fimipay.com/api/v1`). Environment is selected by your key prefix (`sk_test_` vs `sk_live_`).

= Which payment methods are supported? =

Live Merchant V1 collection uses Push USSD mobile money (network detected from the customer phone number).

= Does this plugin change the WordPress login URL? =

No. FimiPay for WooCommerce is a payment gateway only. It does not change `wp-login.php` or any login URL.

= Does deactivation remove settings or orders? =

Deactivating the plugin disables the payment method at checkout. WooCommerce order history and gateway settings stored in the database are kept. Deleting the plugin may remove plugin files; order records remain in WooCommerce.

= How do I uninstall cleanly? =

1. Disable FimiPay under **WooCommerce → Settings → Payments**.
2. Deactivate the plugin under **Plugins**.
3. Optionally delete the plugin. Orders already paid are not deleted.

== Screenshots ==

1. WooCommerce Payments list with FimiPay enabled.
2. FimiPay gateway settings (API keys, webhook URL, test options).
3. Checkout with FimiPay selected and mobile money phone field.

== Changelog ==

= 1.1.0 =
* Test Mode toggle with separate Test/Live public and secret keys.
* Custom logo, autocomplete order after payment, saved-cards setting (reserved).
* Mobile checkout UI styled like FimiPay (phone shell, method chips, secure note).
* Hardened secret handling: secrets never localized to JS; logs redact keys.

= 1.0.0 =
* Initial release: create_order, order_status, webhooks, polling fallback, Blocks support.
* WordPress.org assets: banner, icon, screenshots.

== Upgrade Notice ==

= 1.1.0 =
Adds Test/Live keys, custom logo, and improved checkout UI. Re-enter keys under Payments → FimiPay if you used the older single secret field.
