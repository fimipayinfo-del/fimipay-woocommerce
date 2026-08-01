# FimiPay for WooCommerce

WooCommerce payment gateway for [FimiPay](https://fimipay.com) mobile money (Push USSD).

## Install

1. Copy the `fimipay-woocommerce` folder to `wp-content/plugins/`.
2. Activate **FimiPay for WooCommerce**.
3. Configure under **WooCommerce → Settings → Payments → FimiPay**.

## WordPress.org assets

Banners, icons, and screenshots for the plugin directory are in [`.wordpress-org/`](.wordpress-org/) (see that folder’s README). Runtime checkout CSS/JS is in `assets/`.

## Configure

| Setting | Description |
|---------|-------------|
| Secret key | `sk_test_…` or `sk_live_…` from FimiPay |
| Webhook secret | From FimiPay app webhook settings |
| Webhook URL | Shown in gateway settings — paste into FimiPay dashboard |

API base (default): `https://fimipay.com/api/v1`

## Flow

1. Customer enters phone at checkout.
2. Plugin calls `POST /payment/create_order`.
3. Customer approves Push USSD.
4. FimiPay sends webhook (`X-Fimipay-Signature`) or plugin polls `order_status`.
