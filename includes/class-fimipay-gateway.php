<?php
/**
 * WooCommerce payment gateway for FimiPay.
 *
 * @package Fimipay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * FimiPay payment gateway.
 */
class Fimipay_Gateway extends WC_Payment_Gateway {

	/** @var string */
	public $testmode = 'yes';

	/** @var string */
	public $test_public_key = '';

	/** @var string */
	public $test_secret_key = '';

	/** @var string */
	public $live_public_key = '';

	/** @var string */
	public $live_secret_key = '';

	/** @var string */
	public $webhook_secret = '';

	/** @var string */
	public $api_base_url = '';

	/** @var string */
	public $custom_logo = '';

	/** @var string */
	public $checkout_style = 'fimipay';

	/** @var string */
	public $saved_cards = 'no';

	/** @var string */
	public $autocomplete_order = 'yes';

	/** @var string */
	public $debug = 'no';

	/** @var string */
	public $test_outcome = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'fimipay';
		$this->icon               = '';
		$this->method_title       = __( 'FimiPay', 'fimipay-woocommerce' );
		$this->method_description = __( 'Accept mobile money payments via FimiPay Push USSD.', 'fimipay-woocommerce' );
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled            = $this->get_option( 'enabled', 'no' );
		$this->title              = $this->get_option( 'title', __( 'Mobile Money (FimiPay)', 'fimipay-woocommerce' ) );
		$this->description        = $this->get_option( 'description', __( 'Approve the Push USSD on your phone to complete payment.', 'fimipay-woocommerce' ) );
		$this->testmode           = $this->get_option( 'testmode', 'yes' );
		$this->test_public_key    = $this->get_option( 'test_public_key', '' );
		$this->test_secret_key    = $this->get_option( 'test_secret_key', '' );
		$this->live_public_key    = $this->get_option( 'live_public_key', '' );
		$this->live_secret_key    = $this->get_option( 'live_secret_key', '' );
		$this->webhook_secret     = $this->get_option( 'webhook_secret', '' );
		$this->api_base_url       = $this->get_option( 'api_base_url', '' );
		$this->custom_logo        = $this->get_option( 'custom_logo', '' );
		$this->checkout_style     = $this->get_option( 'checkout_style', 'fimipay' );
		$this->saved_cards        = $this->get_option( 'saved_cards', 'no' );
		$this->autocomplete_order = $this->get_option( 'autocomplete_order', 'yes' );
		$this->debug              = $this->get_option( 'debug', 'no' );
		$this->test_outcome       = $this->get_option( 'test_outcome', '' );

		// Migrate legacy single secret_key if present.
		if ( '' === $this->test_secret_key && '' === $this->live_secret_key ) {
			$legacy = $this->get_option( 'secret_key', '' );
			if ( $legacy && 0 === strpos( $legacy, 'sk_test_' ) ) {
				$this->test_secret_key = $legacy;
			} elseif ( $legacy && 0 === strpos( $legacy, 'sk_live_' ) ) {
				$this->live_secret_key = $legacy;
			}
		}

		$logo = $this->get_checkout_logo_url();
		if ( $logo ) {
			$this->icon = $logo;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Whether test mode is enabled.
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		return 'yes' === $this->testmode;
	}

	/**
	 * Active secret key (never expose to frontend).
	 *
	 * @return string
	 */
	public function get_active_secret_key() {
		return $this->is_test_mode() ? (string) $this->test_secret_key : (string) $this->live_secret_key;
	}

	/**
	 * Active public key (safe for frontend).
	 *
	 * @return string
	 */
	public function get_active_public_key() {
		return $this->is_test_mode() ? (string) $this->test_public_key : (string) $this->live_public_key;
	}

	/**
	 * Checkout / icon logo URL.
	 *
	 * @return string
	 */
	public function get_checkout_logo_url() {
		if ( $this->custom_logo ) {
			return esc_url( $this->custom_logo );
		}
		return esc_url( FIMIPAY_WC_URL . 'assets/images/fimipay-logo.png' );
	}

	/**
	 * Selected checkout UI style.
	 *
	 * @return string default|fimipay
	 */
	public function get_checkout_style() {
		$style   = (string) $this->checkout_style;
		$allowed = array( 'default', 'fimipay' );
		if ( 'midnight' === $style ) {
			return 'fimipay';
		}
		return in_array( $style, $allowed, true ) ? $style : 'fimipay';
	}

	/**
	 * Provider logo map (URL + label).
	 *
	 * @return array<string, array{label:string,url:string}>
	 */
	public function get_provider_logos() {
		$base = FIMIPAY_WC_URL . 'assets/images/providers/';
		return array(
			'mpesa'    => array(
				'label' => 'M-Pesa',
				'url'   => $base . 'mpesa.webp',
			),
			'airtel'   => array(
				'label' => 'Airtel Money',
				'url'   => $base . 'airtel.webp',
			),
			'mixx'     => array(
				'label' => 'Mixx by Yas',
				'url'   => $base . 'mixx.webp',
			),
			'halopesa' => array(
				'label' => 'HaloPesa',
				'url'   => $base . 'halopesa.webp',
			),
		);
	}

	/**
	 * Admin settings fields.
	 */
	public function init_form_fields() {
		$webhook_url = home_url( '/?wc-api=fimipay_webhook' );

		$this->form_fields = array(
			'enabled'             => array(
				'title'   => __( 'Enable/Disable', 'fimipay-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable FimiPay', 'fimipay-woocommerce' ),
				'default' => 'no',
			),
			'title'               => array(
				'title'       => __( 'Title', 'fimipay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown at checkout.', 'fimipay-woocommerce' ),
				'default'     => __( 'Mobile Money (FimiPay)', 'fimipay-woocommerce' ),
				'desc_tip'    => true,
			),
			'description'         => array(
				'title'       => __( 'Description', 'fimipay-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown at checkout.', 'fimipay-woocommerce' ),
				'default'     => __( 'Enter your mobile money phone number below, then tap Pay. You will get a prompt on your phone to approve.', 'fimipay-woocommerce' ),
			),
			'checkout_style'      => array(
				'title'       => __( 'Checkout style', 'fimipay-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose how the FimiPay payment box looks on checkout.', 'fimipay-woocommerce' ),
				'default'     => 'fimipay',
				'options'     => array(
					'default' => __( 'Default — simple with Pay button', 'fimipay-woocommerce' ),
					'fimipay' => __( 'FimiPay — complete payment card', 'fimipay-woocommerce' ),
				),
			),
			'api_credentials'     => array(
				'title'       => __( 'API credentials', 'fimipay-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Secret keys stay on the server only and are never sent to the browser. Public keys may be used on checkout.', 'fimipay-woocommerce' ),
			),
			'testmode'            => array(
				'title'       => __( 'Test mode', 'fimipay-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Test Mode', 'fimipay-woocommerce' ),
				'default'     => 'yes',
				'description' => __( 'Checked = Test API keys. Unchecked = Live API keys.', 'fimipay-woocommerce' ),
			),
			'test_public_key'     => array(
				'title'       => __( 'Test Public Key', 'fimipay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Starts with pk_test_…', 'fimipay-woocommerce' ),
				'default'     => '',
				'placeholder' => 'pk_test_…',
			),
			'test_secret_key'     => array(
				'title'       => __( 'Test Secret Key', 'fimipay-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Starts with sk_test_…. Leave blank when saving to keep the current key.', 'fimipay-woocommerce' ),
				'default'     => '',
				'placeholder' => 'sk_test_…',
			),
			'live_public_key'     => array(
				'title'       => __( 'Live Public Key', 'fimipay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Starts with pk_live_…', 'fimipay-woocommerce' ),
				'default'     => '',
				'placeholder' => 'pk_live_…',
			),
			'live_secret_key'     => array(
				'title'       => __( 'Live Secret Key', 'fimipay-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Starts with sk_live_…. Leave blank when saving to keep the current key.', 'fimipay-woocommerce' ),
				'default'     => '',
				'placeholder' => 'sk_live_…',
			),
			'webhook_secret'      => array(
				'title'       => __( 'Webhook secret', 'fimipay-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'HMAC secret for X-Fimipay-Signature. Leave blank to keep current.', 'fimipay-woocommerce' ),
				'default'     => '',
			),
			'webhook_url'         => array(
				'title'       => __( 'Webhook URL', 'fimipay-woocommerce' ),
				'type'        => 'title',
				'description' => sprintf(
					/* translators: %s: webhook URL */
					__( 'Paste this URL into your FimiPay dashboard webhook settings: %s', 'fimipay-woocommerce' ),
					'<code style="user-select:all;">' . esc_html( $webhook_url ) . '</code>'
				),
			),
			'custom_logo'         => array(
				'title'       => __( 'Custom Logo', 'fimipay-woocommerce' ),
				'type'        => 'fimipay_media',
				'description' => __( 'Optional checkout logo. Defaults to the FimiPay logo.', 'fimipay-woocommerce' ),
				'default'     => '',
			),
			'saved_cards'         => array(
				'title'       => __( 'Saved Cards', 'fimipay-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Payment via Saved Cards', 'fimipay-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'Reserved for future card tokenization. Current Merchant API collects via Push USSD mobile money.', 'fimipay-woocommerce' ),
			),
			'autocomplete_order'  => array(
				'title'       => __( 'Autocomplete Order After Payment', 'fimipay-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Mark order as Completed after successful payment', 'fimipay-woocommerce' ),
				'default'     => 'yes',
				'description' => __( 'When payment succeeds, set the WooCommerce order status to Completed.', 'fimipay-woocommerce' ),
			),
			'api_base_url'        => array(
				'title'       => __( 'API base URL', 'fimipay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Optional override. Leave blank to use https://fimipay.com/api/v1', 'fimipay-woocommerce' ),
				'default'     => '',
				'placeholder' => FIMIPAY_WC_API_BASE_DEFAULT,
			),
			'test_outcome'        => array(
				'title'       => __( 'Test outcome', 'fimipay-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Only with Test Mode + sk_test_ keys.', 'fimipay-woocommerce' ),
				'default'     => '',
				'options'     => array(
					''        => __( 'Default (pending)', 'fimipay-woocommerce' ),
					'success' => __( 'success', 'fimipay-woocommerce' ),
					'failed'  => __( 'failed', 'fimipay-woocommerce' ),
					'pending' => __( 'pending', 'fimipay-woocommerce' ),
				),
			),
			'debug'               => array(
				'title'       => __( 'Debug log', 'fimipay-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'fimipay-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'Logs never include secret keys. WooCommerce → Status → Logs (source: fimipay).', 'fimipay-woocommerce' ),
			),
		);
	}

	/**
	 * Media URL field with WordPress media picker.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 * @return string
	 */
	public function generate_fimipay_media_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);
		$data  = wp_parse_args( $data, $defaults );
		$value = esc_attr( $this->get_option( $key ) );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<input
						class="input-text regular-input fimipay-media-url <?php echo esc_attr( $data['class'] ); ?>"
						type="url"
						name="<?php echo esc_attr( $field_key ); ?>"
						id="<?php echo esc_attr( $field_key ); ?>"
						value="<?php echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>"
						placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>"
					/>
					<button type="button" class="button fimipay-media-upload" data-target="<?php echo esc_attr( $field_key ); ?>">
						<?php esc_html_e( 'Upload / Select', 'fimipay-woocommerce' ); ?>
					</button>
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( $value ) : ?>
						<p><img src="<?php echo esc_url( $value ); ?>" alt="" style="max-height:48px;margin-top:8px;" /></p>
					<?php endif; ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Keep password secrets when the admin leaves them blank.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$secret_fields = array( 'test_secret_key', 'live_secret_key', 'webhook_secret' );

		foreach ( $secret_fields as $field ) {
			$key = $this->get_field_key( $field );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC settings nonce verified upstream.
			$posted = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			if ( '' === $posted ) {
				// Keep existing secret when the password field is left blank.
				$_POST[ $key ] = $this->get_option( $field ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$_POST[ $key ] = $this->sanitize_secret( $field, $posted ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
		}

		$result = parent::process_admin_options();
		$this->validate_key_prefixes();
		return $result;
	}

	/**
	 * Sanitize secret values.
	 *
	 * @param string $field Field id.
	 * @param string $value Raw value.
	 * @return string
	 */
	private function sanitize_secret( $field, $value ) {
		$value = trim( $value );
		if ( in_array( $field, array( 'test_secret_key', 'live_secret_key' ), true ) && ! preg_match( '/^sk_(test|live)_/', $value ) ) {
			WC_Admin_Settings::add_error( __( 'Secret keys must start with sk_test_ or sk_live_.', 'fimipay-woocommerce' ) );
		}
		return $value;
	}

	/**
	 * Warn if key prefixes do not match mode.
	 */
	private function validate_key_prefixes() {
		$test_sk = $this->get_option( 'test_secret_key' );
		$live_sk = $this->get_option( 'live_secret_key' );
		if ( $test_sk && 0 !== strpos( $test_sk, 'sk_test_' ) ) {
			WC_Admin_Settings::add_error( __( 'Test Secret Key should start with sk_test_.', 'fimipay-woocommerce' ) );
		}
		if ( $live_sk && 0 !== strpos( $live_sk, 'sk_live_' ) ) {
			WC_Admin_Settings::add_error( __( 'Live Secret Key should start with sk_live_.', 'fimipay-woocommerce' ) );
		}
	}

	/**
	 * API client using active secret (server-side only).
	 *
	 * @return Fimipay_API
	 */
	public function get_api() {
		return new Fimipay_API(
			$this->get_active_secret_key(),
			$this->api_base_url,
			'yes' === $this->debug
		);
	}

	/**
	 * Admin media uploader script.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['section'] ) || 'fimipay' !== $_GET['section'] ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'fimipay-admin',
			FIMIPAY_WC_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FIMIPAY_WC_VERSION,
			true
		);
	}

	/**
	 * Enqueue checkout CSS/JS. Never localize secret keys.
	 */
	public function enqueue_checkout_assets() {
		if ( ! is_checkout() || 'yes' !== $this->enabled ) {
			return;
		}

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

		wp_enqueue_script(
			'fimipay-checkout',
			FIMIPAY_WC_URL . 'assets/js/checkout.js',
			array( 'jquery' ),
			FIMIPAY_WC_VERSION,
			true
		);

		// Public-safe data only — never secret keys, webhook secrets, or vendor credentials.
		wp_localize_script(
			'fimipay-checkout',
			'fimipayCheckout',
			array(
				'testMode'      => $this->is_test_mode(),
				'publicKey'     => $this->get_active_public_key(),
				'logoUrl'       => $this->get_checkout_logo_url(),
				'dialCode'      => '+255',
				'checkoutStyle' => $this->get_checkout_style(),
				'amountLabel'   => $this->get_cart_amount_label(),
				'merchantName'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'providers'     => $this->get_provider_logos(),
			)
		);
	}

	/**
	 * Formatted cart total for checkout UI.
	 *
	 * @return string
	 */
	public function get_cart_amount_label() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		$total = (float) WC()->cart->get_total( 'edit' );
		$currency = get_woocommerce_currency();
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $total ) );
		}
		return $currency . ' ' . number_format( $total, 0 );
	}

	/**
	 * Checkout payment fields — 3 selectable styles.
	 */
	public function payment_fields() {
		$phone = '';
		if ( WC()->customer ) {
			$phone = WC()->customer->get_billing_phone();
		}
		$local = preg_replace( '/\D+/', '', (string) $phone );
		if ( 0 === strpos( (string) $local, '255' ) && strlen( $local ) >= 12 ) {
			$local = substr( $local, 3 );
		} elseif ( 0 === strpos( (string) $local, '0' ) ) {
			$local = substr( $local, 1 );
		}

		$style = $this->get_checkout_style();
		$logo  = $this->get_checkout_logo_url();
		$mode  = $this->is_test_mode() ? __( 'Test mode', 'fimipay-woocommerce' ) : __( 'Live', 'fimipay-woocommerce' );
		$desc  = $this->description
			? esc_html( wp_strip_all_tags( $this->description ) )
			: esc_html__( 'Enter your mobile money phone number below, then tap Pay. You will get a prompt on your phone to approve.', 'fimipay-woocommerce' );

		echo '<div class="fimipay-checkout-card fimipay-style-' . esc_attr( $style ) . '" id="fimipay-cc-form" data-style="' . esc_attr( $style ) . '">';

		if ( 'default' === $style ) {
			$this->render_checkout_style_default( $logo, $mode, $desc, $local, $phone );
		} else {
			$this->render_checkout_style_fimipay( $logo, $mode, $desc, $local, $phone );
		}

		echo '</div>';
	}

	/**
	 * Phone input markup shared by styles.
	 *
	 * @param string $local Local 9 digits.
	 * @param string $phone Raw phone.
	 * @param string $placeholder Placeholder.
	 */
	private function render_phone_field( $local, $phone, $placeholder = '7XX XXX XXX', $label = '' ) {
		if ( '' === $label ) {
			$label = __( 'Phone Number', 'fimipay-woocommerce' );
		}
		echo '<label class="fimipay-field-label" for="fimipay_phone_local">' . esc_html( $label ) . ' <span class="required">*</span></label>';
		echo '<div class="fimipay-phone-shell">';
		echo '<div class="fimipay-phone-shell__prefix"><span class="fimipay-flag" aria-hidden="true">🇹🇿</span><span>+255</span></div>';
		echo '<input id="fimipay_phone_local" type="tel" inputmode="tel" autocomplete="tel-national" class="fimipay-phone-shell__input" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $local ) . '" />';
		echo '<input type="hidden" id="fimipay_phone" name="fimipay_phone" value="' . esc_attr( self::normalize_phone( $phone ) ) . '" />';
		echo '</div>';
	}

	/**
	 * Selectable mobile money methods (logo only).
	 *
	 * @param string $selected Selected method id.
	 */
	private function render_method_picker( $selected = 'mpesa' ) {
		$methods = $this->get_provider_logos();
		echo '<div class="fimipay-section-label">' . esc_html__( 'Choose Payment Method', 'fimipay-woocommerce' ) . '</div>';
		echo '<div class="fimipay-method-grid" role="radiogroup" aria-label="' . esc_attr__( 'Payment method', 'fimipay-woocommerce' ) . '">';
		foreach ( $methods as $id => $meta ) {
			$is_active = ( $selected === $id ) ? ' is-active' : '';
			echo '<button type="button" class="fimipay-method-chip fimipay-method-chip--logo' . esc_attr( $is_active ) . '" data-method="' . esc_attr( $id ) . '" aria-label="' . esc_attr( $meta['label'] ) . '" aria-pressed="' . ( $selected === $id ? 'true' : 'false' ) . '" title="' . esc_attr( $meta['label'] ) . '">';
			echo '<img src="' . esc_url( $meta['url'] ) . '" alt="' . esc_attr( $meta['label'] ) . '" class="fimipay-provider-logo" loading="lazy" width="78" height="26" />';
			echo '</button>';
		}
		echo '</div>';
		echo '<input type="hidden" name="fimipay_channel" id="fimipay_channel" value="' . esc_attr( $selected ) . '" />';
	}

	/**
	 * Non-selectable provider logos row (under Pay button).
	 */
	private function render_provider_logos_row() {
		echo '<div class="fimipay-providers-row" aria-label="' . esc_attr__( 'Supported mobile money providers', 'fimipay-woocommerce' ) . '">';
		foreach ( $this->get_provider_logos() as $meta ) {
			echo '<span class="fimipay-providers-row__item">';
			echo '<img src="' . esc_url( $meta['url'] ) . '" alt="' . esc_attr( $meta['label'] ) . '" class="fimipay-provider-logo" loading="lazy" width="72" height="32" />';
			echo '</span>';
		}
		echo '</div>';
	}

	/**
	 * Shared Pay CTA that triggers WooCommerce place order.
	 *
	 * @param string $amount Formatted amount.
	 */
	private function render_pay_button( $amount ) {
		echo '<button type="button" class="fimipay-pay-btn" id="fimipay-pay-btn">';
		echo '<span class="fimipay-pay-btn__icon" aria-hidden="true">▶</span> ';
		echo esc_html(
			sprintf(
				/* translators: %s: formatted amount */
				__( 'Pay %s', 'fimipay-woocommerce' ),
				$amount ? $amount : __( 'now', 'fimipay-woocommerce' )
			)
		);
		echo '</button>';
	}

	/**
	 * Style 1 — Default (simple).
	 *
	 * @param string $logo  Logo URL.
	 * @param string $mode  Mode label.
	 * @param string $desc  Description.
	 * @param string $local Local phone.
	 * @param string $phone Raw phone.
	 */
	private function render_checkout_style_default( $logo, $mode, $desc, $local, $phone ) {
		$amount = $this->get_cart_amount_label();

		echo '<div class="fimipay-checkout-card__brand">';
		if ( $logo ) {
			echo '<img class="fimipay-checkout-card__logo fimipay-checkout-card__logo--lg" src="' . esc_url( $logo ) . '" alt="FimiPay" />';
		} else {
			echo '<strong class="fimipay-brand-text">FimiPay</strong>';
		}
		echo '<span class="fimipay-checkout-card__mode">' . esc_html( $mode ) . '</span>';
		echo '</div>';

		if ( $desc ) {
			echo '<p class="fimipay-checkout-card__desc">' . $desc . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped upstream
		}

		$this->render_phone_field( $local, $phone, '712 345 678', __( 'Phone Number', 'fimipay-woocommerce' ) );
		echo '<p class="fimipay-phone-hint">' . esc_html__( 'Use the number registered to your mobile money wallet.', 'fimipay-woocommerce' ) . '</p>';

		$this->render_pay_button( $amount );
		$this->render_provider_logos_row();
	}

	/**
	 * Style 2 — FimiPay complete payment card (site wireframe).
	 *
	 * @param string $logo  Logo URL.
	 * @param string $mode  Mode label.
	 * @param string $desc  Description.
	 * @param string $local Local phone.
	 * @param string $phone Raw phone.
	 */
	private function render_checkout_style_fimipay( $logo, $mode, $desc, $local, $phone ) {
		$amount   = $this->get_cart_amount_label();
		$merchant = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		if ( '' === $merchant ) {
			$merchant = __( 'Store', 'fimipay-woocommerce' );
		}

		echo '<div class="fimipay-checkout-card__brand">';
		if ( $logo ) {
			echo '<img class="fimipay-checkout-card__logo fimipay-checkout-card__logo--lg" src="' . esc_url( $logo ) . '" alt="FimiPay" />';
		} else {
			echo '<strong class="fimipay-brand-text">FimiPay</strong>';
		}
		echo '<span class="fimipay-checkout-card__mode">' . esc_html( $mode ) . '</span>';
		echo '</div>';

		echo '<hr class="fimipay-rule" />';

		echo '<div class="fimipay-complete">';
		echo '<div class="fimipay-complete__label">' . esc_html__( 'Complete Payment', 'fimipay-woocommerce' ) . '</div>';
		echo '<div class="fimipay-complete__amount" id="fimipay-amount-label">' . esc_html( $amount ) . '</div>';
		echo '<div class="fimipay-complete__merchant-label">' . esc_html__( 'Merchant', 'fimipay-woocommerce' ) . '</div>';
		echo '<div class="fimipay-complete__merchant">' . esc_html( $merchant ) . '</div>';
		echo '</div>';

		echo '<hr class="fimipay-rule" />';

		$this->render_method_picker( 'mpesa' );

		echo '<hr class="fimipay-rule" />';

		$this->render_phone_field( $local, $phone, '712 345 678', __( 'Phone Number', 'fimipay-woocommerce' ) );
		echo '<p class="fimipay-phone-hint">' . esc_html__( 'Enter the phone number linked to your wallet to pay.', 'fimipay-woocommerce' ) . '</p>';

		echo '<hr class="fimipay-rule" />';

		$this->render_pay_button( $amount );

		echo '<hr class="fimipay-rule" />';

		echo '<div class="fimipay-secure-note fimipay-secure-note--center">';
		echo '<span class="fimipay-secure-note__icon" aria-hidden="true">🔒</span>';
		echo '<span>' . esc_html__( 'Secure checkout powered by FimiPay', 'fimipay-woocommerce' ) . '</span>';
		echo '</div>';
	}

	/**
	 * Validate checkout fields.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$phone = isset( $_POST['fimipay_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['fimipay_phone'] ) ) : '';

		if ( '' === $phone && WC()->checkout() ) {
			$phone = WC()->checkout()->get_value( 'billing_phone' );
		}

		if ( '' === self::normalize_phone( $phone ) ) {
			wc_add_notice( __( 'Please enter a valid mobile money phone number for FimiPay.', 'fimipay-woocommerce' ), 'error' );
			return false;
		}

		if ( '' === $this->get_active_secret_key() ) {
			wc_add_notice( __( 'FimiPay is not configured. Please contact the store owner.', 'fimipay-woocommerce' ), 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Normalize TZ MSISDN to 255XXXXXXXXX.
	 *
	 * @param string $phone Raw phone.
	 * @return string
	 */
	public static function normalize_phone( $phone ) {
		$digits = preg_replace( '/\D+/', '', (string) $phone );
		if ( ! is_string( $digits ) || $digits === '' ) {
			return '';
		}

		if ( 0 === strpos( $digits, '255' ) && strlen( $digits ) >= 12 ) {
			return substr( $digits, 0, 12 );
		}

		if ( 0 === strpos( $digits, '0' ) && strlen( $digits ) >= 10 ) {
			return '255' . substr( $digits, 1, 9 );
		}

		if ( strlen( $digits ) === 9 ) {
			return '255' . $digits;
		}

		return '';
	}

	/**
	 * Process payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'fimipay-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$phone = isset( $_POST['fimipay_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['fimipay_phone'] ) ) : '';

		if ( '' === $phone && isset( $_POST['payment_data'] ) && is_array( $_POST['payment_data'] ) ) {
			foreach ( wp_unslash( $_POST['payment_data'] ) as $entry ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( is_array( $entry ) && isset( $entry['key'], $entry['value'] ) && 'fimipay_phone' === $entry['key'] ) {
					$phone = sanitize_text_field( (string) $entry['value'] );
					break;
				}
			}
		}

		if ( '' === $phone ) {
			$phone = $order->get_billing_phone();
		}

		$buyer_phone = self::normalize_phone( $phone );
		if ( '' === $buyer_phone ) {
			wc_add_notice( __( 'Invalid phone number for FimiPay payment.', 'fimipay-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$secret = $this->get_active_secret_key();
		if ( '' === $secret ) {
			wc_add_notice( __( 'FimiPay payment is unavailable. Missing API secret key.', 'fimipay-woocommerce' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$merchant_ref = 'wc_' . $order->get_id() . '_' . wp_generate_password( 6, false, false );

		$payload = array(
			'buyer_phone' => $buyer_phone,
			'amount'      => (float) $order->get_total(),
			'currency'    => $order->get_currency() ? $order->get_currency() : 'TZS',
			'buyer_name'  => trim( $order->get_formatted_billing_full_name() ),
			'buyer_email' => $order->get_billing_email(),
			'order_id'    => $merchant_ref,
		);

		$api = $this->get_api();
		if ( $this->is_test_mode() && $this->test_outcome !== '' ) {
			$payload['test_outcome'] = $this->test_outcome;
		}

		$response = $api->create_order( $payload );

		if ( is_wp_error( $response ) ) {
			$this->log( 'create_order failed: ' . $response->get_error_message(), 'error' );
			wc_add_notice(
				sprintf(
					/* translators: %s: error message */
					__( 'FimiPay error: %s', 'fimipay-woocommerce' ),
					$response->get_error_message()
				),
				'error'
			);
			return array( 'result' => 'failure' );
		}

		$data             = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
		$fimipay_order_id = isset( $data['order_id'] ) ? (string) $data['order_id'] : $merchant_ref;
		$payment_status   = isset( $data['payment_status'] ) ? strtoupper( (string) $data['payment_status'] ) : 'PENDING';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$channel = isset( $_POST['fimipay_channel'] ) ? sanitize_key( wp_unslash( $_POST['fimipay_channel'] ) ) : '';
		if ( '' === $channel && isset( $_POST['payment_data'] ) && is_array( $_POST['payment_data'] ) ) {
			foreach ( wp_unslash( $_POST['payment_data'] ) as $entry ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( is_array( $entry ) && isset( $entry['key'], $entry['value'] ) && 'fimipay_channel' === $entry['key'] ) {
					$channel = sanitize_key( (string) $entry['value'] );
					break;
				}
			}
		}
		$allowed_channels = array( 'mpesa', 'airtel', 'mixx', 'halopesa' );
		if ( ! in_array( $channel, $allowed_channels, true ) ) {
			$channel = 'mpesa';
		}

		$order->update_meta_data( '_fimipay_order_id', $fimipay_order_id );
		$order->update_meta_data( '_fimipay_merchant_ref', $merchant_ref );
		$order->update_meta_data( '_fimipay_buyer_phone', $buyer_phone );
		$order->update_meta_data( '_fimipay_payment_status', $payment_status );
		$order->update_meta_data( '_fimipay_channel_selected', $channel );
		$order->update_meta_data( '_fimipay_env', $this->is_test_mode() ? 'test' : 'live' );
		$order->set_transaction_id( $fimipay_order_id );

		$order->add_order_note(
			sprintf(
				/* translators: 1: order id, 2: status, 3: env */
				__( 'FimiPay payment initiated. Order ID: %1$s. Status: %2$s. Mode: %3$s.', 'fimipay-woocommerce' ),
				$fimipay_order_id,
				$payment_status,
				$this->is_test_mode() ? 'test' : 'live'
			)
		);

		if ( self::is_success_status( $payment_status ) ) {
			$this->complete_paid_order( $order, $fimipay_order_id );
		} elseif ( self::is_failure_status( $payment_status ) ) {
			$order->update_status( 'failed', __( 'FimiPay payment failed at initiation.', 'fimipay-woocommerce' ) );
			wc_add_notice( __( 'Payment failed. Please try again.', 'fimipay-woocommerce' ), 'error' );
			$order->save();
			return array( 'result' => 'failure' );
		} else {
			$order->update_status( 'on-hold', __( 'Awaiting FimiPay Push USSD confirmation.', 'fimipay-woocommerce' ) );
			$this->schedule_status_polls( $order->get_id() );
		}

		$order->save();
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Complete order and optionally mark Completed.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $txn   Transaction id.
	 */
	public function complete_paid_order( WC_Order $order, $txn = '' ) {
		if ( ! $order->is_paid() ) {
			$order->payment_complete( $txn ? $txn : $order->get_meta( '_fimipay_order_id' ) );
			$order->add_order_note( __( 'FimiPay payment confirmed.', 'fimipay-woocommerce' ) );
		}

		if ( 'yes' === $this->autocomplete_order && 'completed' !== $order->get_status() ) {
			$order->update_status( 'completed', __( 'Order autocompleted after FimiPay payment.', 'fimipay-woocommerce' ) );
		}

		$order->save();
	}

	/**
	 * Thank-you page messaging.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$logo = $this->get_checkout_logo_url();

		if ( $order->is_paid() ) {
			echo '<div class="fimipay-thankyou fimipay-thankyou--paid">';
			if ( $logo ) {
				echo '<img src="' . esc_url( $logo ) . '" alt="" class="fimipay-thankyou__logo" />';
			}
			echo '<p><strong>' . esc_html__( 'Payment received. Thank you!', 'fimipay-woocommerce' ) . '</strong></p>';
			echo '</div>';
			return;
		}

		$phone = $order->get_meta( '_fimipay_buyer_phone' );
		echo '<div class="fimipay-thankyou fimipay-thankyou--pending">';
		if ( $logo ) {
			echo '<img src="' . esc_url( $logo ) . '" alt="" class="fimipay-thankyou__logo" />';
		}
		echo '<p><strong>' . esc_html__( 'Approve the payment on your phone', 'fimipay-woocommerce' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'A Push USSD prompt was sent. Enter your PIN to complete the order.', 'fimipay-woocommerce' ) . '</p>';
		if ( $phone ) {
			echo '<p class="fimipay-thankyou__phone">' . esc_html( $phone ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Schedule status polls.
	 *
	 * @param int $order_id Order ID.
	 */
	public function schedule_status_polls( $order_id ) {
		foreach ( array( 60, 180, 420 ) as $delay ) {
			wp_schedule_single_event( time() + $delay, 'fimipay_wc_poll_order_status', array( $order_id ) );
		}
	}

	/**
	 * Sync from API.
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public function sync_order_status_from_api( WC_Order $order ) {
		$fimipay_order_id = $order->get_meta( '_fimipay_order_id' );
		if ( empty( $fimipay_order_id ) ) {
			return false;
		}

		$response = $this->get_api()->order_status( $fimipay_order_id );
		if ( is_wp_error( $response ) ) {
			$this->log( 'order_status poll failed for #' . $order->get_id() . ': ' . $response->get_error_message(), 'error' );
			return false;
		}

		$data   = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;
		$status = isset( $data['payment_status'] ) ? strtoupper( (string) $data['payment_status'] ) : '';

		return $this->apply_payment_status( $order, $status, $data );
	}

	/**
	 * Apply payment status.
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $status Status.
	 * @param array    $data   Payload.
	 * @return bool
	 */
	public function apply_payment_status( WC_Order $order, $status, array $data = array() ) {
		$status = strtoupper( (string) $status );
		$order->update_meta_data( '_fimipay_payment_status', $status );

		foreach ( array( 'transid', 'channel', 'reference' ) as $meta ) {
			if ( ! empty( $data[ $meta ] ) ) {
				$order->update_meta_data( '_fimipay_' . $meta, sanitize_text_field( (string) $data[ $meta ] ) );
			}
		}

		if ( self::is_success_status( $status ) ) {
			$this->complete_paid_order( $order );
			return true;
		}

		if ( self::is_failure_status( $status ) ) {
			if ( ! in_array( $order->get_status(), array( 'cancelled', 'failed', 'refunded' ), true ) ) {
				$order->update_status(
					'failed',
					sprintf(
						/* translators: %s: status */
						__( 'FimiPay payment not completed (%s).', 'fimipay-woocommerce' ),
						$status
					)
				);
			}
			$order->save();
			return true;
		}

		$order->save();
		return false;
	}

	/**
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_success_status( $status ) {
		return in_array( strtoupper( (string) $status ), array( 'SUCCESS', 'PAID', 'COMPLETED' ), true );
	}

	/**
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_failure_status( $status ) {
		return in_array(
			strtoupper( (string) $status ),
			array( 'CANCELLED', 'USERCANCELLED', 'REJECTED', 'FAILED', 'FAILURE' ),
			true
		);
	}

	/**
	 * @param string $message Message.
	 * @param string $level   Level.
	 */
	private function log( $message, $level = 'info' ) {
		if ( 'yes' !== $this->debug || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		$message = preg_replace( '/sk_(test|live)_[A-Za-z0-9]+/', 'sk_$1_[REDACTED]', $message );
		$message = preg_replace( '/pk_(test|live)_[A-Za-z0-9]+/', 'pk_$1_[REDACTED]', $message );
		wc_get_logger()->log( $level, $message, array( 'source' => 'fimipay' ) );
	}
}
