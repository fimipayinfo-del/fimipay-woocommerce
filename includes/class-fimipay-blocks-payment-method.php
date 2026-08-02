<?php
/**
 * Blocks payment method type for FimiPay.
 *
 * @package Fimipay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * FimiPay Blocks payment method.
 */
final class Fimipay_Blocks_Payment_Method extends AbstractPaymentMethodType {

	/**
	 * Payment method name matching gateway id.
	 *
	 * @var string
	 */
	protected $name = 'fimipay';

	/**
	 * Gateway instance.
	 *
	 * @var Fimipay_Gateway|null
	 */
	private $gateway;

	/**
	 * Initialize.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_fimipay_settings', array() );
		$gateways       = WC()->payment_gateways()->payment_gateways();
		$this->gateway  = isset( $gateways['fimipay'] ) ? $gateways['fimipay'] : null;
	}

	/**
	 * Whether active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->gateway && 'yes' === $this->gateway->enabled;
	}

	/**
	 * Script handles to register.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		$handle = 'fimipay-blocks-checkout';

		wp_register_script(
			$handle,
			FIMIPAY_WC_URL . 'assets/js/blocks-checkout.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			),
			FIMIPAY_WC_VERSION,
			true
		);

		wp_enqueue_style(
			'fimipay-font',
			'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'fimipay-checkout',
			FIMIPAY_WC_URL . 'assets/css/checkout.css',
			array( 'fimipay-font' ),
			FIMIPAY_WC_VERSION
		);

		return array( $handle );
	}

	/**
	 * Data passed to the Blocks script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		// Public-safe only — never include secret keys or webhook secrets.
		return array(
			'title'            => $this->gateway ? $this->gateway->title : __( 'FimiPay', 'fimipay-woocommerce' ),
			'description'      => $this->gateway ? $this->gateway->description : '',
			'supports'         => $this->gateway ? array_filter( $this->gateway->supports ) : array( 'products' ),
			'phoneLabel'       => __( 'Phone Number', 'fimipay-woocommerce' ),
			'phoneHint'        => __( 'You will receive a Push USSD prompt to enter your PIN.', 'fimipay-woocommerce' ),
			'phonePlaceholder' => '7XX XXX XXX',
			'dialCode'         => '+255',
			'logoUrl'          => $this->gateway ? $this->gateway->get_checkout_logo_url() : '',
			'brandMarkUrl'     => $this->gateway ? $this->gateway->get_brand_mark_url() : '',
			'testMode'         => $this->gateway ? $this->gateway->is_test_mode() : true,
			'publicKey'        => $this->gateway ? $this->gateway->get_active_public_key() : '',
			'checkoutStyle'    => $this->gateway ? $this->gateway->get_checkout_style() : 'fimipay',
			'amountLabel'      => $this->gateway ? $this->gateway->get_cart_amount_label() : '',
			'merchantName'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'completeLabel'    => __( 'Complete Payment', 'fimipay-woocommerce' ),
			'merchantLabel'    => __( 'Merchant', 'fimipay-woocommerce' ),
			'methodLabel'      => __( 'Choose Payment Method', 'fimipay-woocommerce' ),
			'payPrefix'        => __( 'Pay', 'fimipay-woocommerce' ),
			'secureNote'       => __( 'Secure checkout powered by FimiPay', 'fimipay-woocommerce' ),
			'providers'        => $this->gateway ? $this->gateway->get_provider_logos() : array(),
			'phoneHint'        => __( 'Enter the phone number linked to your wallet to pay.', 'fimipay-woocommerce' ),
		);
	}
}
