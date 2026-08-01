<?php
/**
 * FimiPay Merchant V1 API client.
 *
 * @package Fimipay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTTP client for FimiPay Merchant API.
 */
class Fimipay_API {

	/**
	 * API base URL (no trailing slash).
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Secret API key (sk_test_… / sk_live_…).
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Whether to write WooCommerce debug logs.
	 *
	 * @var bool
	 */
	private $debug;

	/**
	 * Constructor.
	 *
	 * @param string $secret_key Secret key.
	 * @param string $base_url   Optional base URL override.
	 * @param bool   $debug      Enable logging.
	 */
	public function __construct( $secret_key, $base_url = '', $debug = false ) {
		$this->secret_key = trim( (string) $secret_key );
		$base             = trim( (string) $base_url );
		$this->base_url   = untrailingslashit( $base !== '' ? $base : FIMIPAY_WC_API_BASE_DEFAULT );
		$this->debug      = (bool) $debug;
	}

	/**
	 * Whether the configured key is a test key.
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		return 0 === strpos( $this->secret_key, 'sk_test_' );
	}

	/**
	 * Create a payment order (Push USSD / simulated).
	 *
	 * @param array $payload Request body.
	 * @return array|\WP_Error Decoded response or error.
	 */
	public function create_order( array $payload ) {
		return $this->request( 'POST', '/payment/create_order', $payload );
	}

	/**
	 * Fetch payment order status.
	 *
	 * @param string $order_id FimiPay order ID.
	 * @return array|\WP_Error
	 */
	public function order_status( $order_id ) {
		return $this->request(
			'POST',
			'/payment/order_status',
			array(
				'order_id' => (string) $order_id,
			)
		);
	}

	/**
	 * Perform an authenticated JSON request.
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path starting with /.
	 * @param array  $body   JSON body.
	 * @return array|\WP_Error
	 */
	private function request( $method, $path, array $body = array() ) {
		if ( $this->secret_key === '' ) {
			return new WP_Error( 'fimipay_missing_key', __( 'FimiPay secret key is not configured.', 'fimipay-woocommerce' ) );
		}

		$url  = $this->base_url . $path;
		$args = array(
			'method'  => $method,
			'timeout' => 45,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->secret_key,
				'User-Agent'    => 'FimiPay-WooCommerce/' . FIMIPAY_WC_VERSION,
			),
			'body'    => wp_json_encode( $body ),
		);

		$this->log( 'Request ' . $method . ' ' . $url . ' body=' . $this->redact( $body ) );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'Transport error: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$data     = json_decode( $raw_body, true );

		$this->log( 'Response HTTP ' . $code . ' body=' . $this->redact_string( $raw_body ) );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'fimipay_invalid_response',
				__( 'Invalid response from FimiPay.', 'fimipay-woocommerce' ),
				array(
					'status' => $code,
					'body'   => $raw_body,
				)
			);
		}

		if ( $code < 200 || $code >= 300 || ( isset( $data['status'] ) && 'error' === $data['status'] ) ) {
			$message = isset( $data['message'] ) ? (string) $data['message'] : __( 'FimiPay payment request failed.', 'fimipay-woocommerce' );
			$error   = isset( $data['error'] ) ? (string) $data['error'] : 'fimipay_api_error';

			return new WP_Error(
				$error,
				$message,
				array(
					'status'   => $code,
					'response' => $data,
				)
			);
		}

		return $data;
	}

	/**
	 * Redact sensitive fields for logs.
	 *
	 * @param array $body Request body.
	 * @return string
	 */
	private function redact( array $body ) {
		$copy = $body;
		unset( $copy['buyer_email'] );
		return $this->redact_string( wp_json_encode( $copy ) );
	}

	/**
	 * Strip API keys from any log string.
	 *
	 * @param string $value Raw.
	 * @return string
	 */
	private function redact_string( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/sk_(test|live)_[A-Za-z0-9]+/', 'sk_$1_[REDACTED]', $value );
		$value = preg_replace( '/pk_(test|live)_[A-Za-z0-9]+/', 'pk_$1_[REDACTED]', $value );
		$value = preg_replace( '/Bearer\s+\S+/i', 'Bearer [REDACTED]', $value );
		return $value;
	}

	/**
	 * Write to WooCommerce logger when debug is on.
	 *
	 * @param string $message Message.
	 * @param string $level   Log level.
	 */
	private function log( $message, $level = 'info' ) {
		if ( ! $this->debug || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->log(
			$level,
			$this->redact_string( $message ),
			array(
				'source' => 'fimipay',
			)
		);
	}
}
