<?php
/**
 * WooCommerce Blocks checkout integration.
 *
 * @package Fimipay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registers FimiPay with Cart & Checkout Blocks.
 */
final class Fimipay_Blocks {

	/**
	 * Boot Blocks integration when available.
	 */
	public static function init() {
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'on_blocks_loaded' ) );
	}

	/**
	 * Register payment method type with Blocks.
	 */
	public static function on_blocks_loaded() {
		if ( ! class_exists( AbstractPaymentMethodType::class ) ) {
			return;
		}

		require_once FIMIPAY_WC_PATH . 'includes/class-fimipay-blocks-payment-method.php';

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			static function ( $registry ) {
				$registry->register( new Fimipay_Blocks_Payment_Method() );
			}
		);
	}
}
