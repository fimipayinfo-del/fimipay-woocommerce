<?php
/**
 * Plugin Name: FimiPay for WooCommerce
 * Plugin URI:  https://fimipay.com
 * Description: Accept mobile money payments (Push USSD) via FimiPay on your WooCommerce store.
 * Version:     1.1.0
 * Author:      FimiPay
 * Author URI:  https://fimipay.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fimipay-woocommerce
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 *
 * @package Fimipay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'FIMIPAY_WC_VERSION', '1.1.0' );
define( 'FIMIPAY_WC_FILE', __FILE__ );
define( 'FIMIPAY_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'FIMIPAY_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'FIMIPAY_WC_API_BASE_DEFAULT', 'https://fimipay.com/api/v1' );

/**
 * Declare HPOS (High-Performance Order Storage) compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FIMIPAY_WC_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FIMIPAY_WC_FILE, true );
		}
	}
);

/**
 * Bootstrap after plugins are loaded.
 */
add_action( 'plugins_loaded', 'fimipay_wc_init', 20 );

/**
 * Initialize the gateway when WooCommerce is available.
 */
function fimipay_wc_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'fimipay_wc_missing_wc_notice' );
		return;
	}

	require_once FIMIPAY_WC_PATH . 'includes/class-fimipay-api.php';
	require_once FIMIPAY_WC_PATH . 'includes/class-fimipay-gateway.php';
	require_once FIMIPAY_WC_PATH . 'includes/class-fimipay-webhook.php';
	require_once FIMIPAY_WC_PATH . 'includes/class-fimipay-blocks.php';

	add_filter( 'woocommerce_payment_gateways', 'fimipay_wc_add_gateway' );

	Fimipay_Webhook::init();
	Fimipay_Blocks::init();

	add_action( 'fimipay_wc_poll_order_status', 'fimipay_wc_handle_poll_order_status', 10, 1 );
}

/**
 * Admin notice when WooCommerce is missing.
 */
function fimipay_wc_missing_wc_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'FimiPay for WooCommerce requires WooCommerce to be installed and active.', 'fimipay-woocommerce' );
	echo '</p></div>';
}

/**
 * Register the payment gateway.
 *
 * @param array $gateways Existing gateways.
 * @return array
 */
function fimipay_wc_add_gateway( $gateways ) {
	$gateways[] = 'Fimipay_Gateway';
	return $gateways;
}

/**
 * Scheduled fallback: poll FimiPay for order status.
 *
 * @param int $order_id WooCommerce order ID.
 */
function fimipay_wc_handle_poll_order_status( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
		return;
	}

	$fimipay_order_id = $order->get_meta( '_fimipay_order_id' );
	if ( empty( $fimipay_order_id ) ) {
		return;
	}

	$gateways = WC()->payment_gateways()->payment_gateways();
	if ( empty( $gateways['fimipay'] ) || ! $gateways['fimipay'] instanceof Fimipay_Gateway ) {
		return;
	}

	/** @var Fimipay_Gateway $gateway */
	$gateway = $gateways['fimipay'];
	$gateway->sync_order_status_from_api( $order );
}

/**
 * Plugin action links.
 *
 * @param array $links Existing links.
 * @return array
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fimipay' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'fimipay-woocommerce' ) . '</a>'
		);
		return $links;
	}
);
