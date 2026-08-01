<?php
/**
 * FimiPay webhook receiver.
 *
 * @package Fimipay_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles inbound FimiPay webhooks (HMAC-SHA256).
 */
class Fimipay_Webhook {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_api_fimipay_webhook', array( __CLASS__, 'handle_wc_api' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_route' ) );
	}

	/**
	 * REST route mirror of the WC API endpoint.
	 */
	public static function register_rest_route() {
		register_rest_route(
			'fimipay/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_rest' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * WC API handler: /?wc-api=fimipay_webhook
	 */
	public static function handle_wc_api() {
		$result = self::process_request();
		status_header( $result['code'] );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $result['body'] );
		exit;
	}

	/**
	 * REST handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_rest( WP_REST_Request $request ) {
		$result = self::process_request( $request->get_body(), $request->get_header( 'x-fimipay-signature' ) );
		return new WP_REST_Response( $result['body'], $result['code'] );
	}

	/**
	 * Shared webhook processing.
	 *
	 * @param string|null $raw_body  Optional raw body.
	 * @param string|null $signature Optional signature header.
	 * @return array{code:int,body:array}
	 */
	public static function process_request( $raw_body = null, $signature = null ) {
		if ( null === $raw_body ) {
			$raw_body = file_get_contents( 'php://input' );
			if ( false === $raw_body ) {
				$raw_body = '';
			}
		}

		if ( null === $signature ) {
			$signature = isset( $_SERVER['HTTP_X_FIMIPAY_SIGNATURE'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FIMIPAY_SIGNATURE'] ) )
				: '';
		}

		$gateway = self::get_gateway();
		if ( ! $gateway ) {
			return array(
				'code' => 503,
				'body' => array(
					'status'  => 'error',
					'message' => 'Gateway unavailable',
				),
			);
		}

		$secret = (string) $gateway->webhook_secret;
		if ( $secret !== '' ) {
			$expected = hash_hmac( 'sha256', $raw_body, $secret );
			if ( ! hash_equals( $expected, (string) $signature ) ) {
				self::log( $gateway, 'Webhook signature mismatch', 'warning' );
				return array(
					'code' => 401,
					'body' => array(
						'status'  => 'error',
						'message' => 'Invalid signature',
					),
				);
			}
		} else {
			self::log( $gateway, 'Webhook received without configured webhook_secret — signature not verified', 'warning' );
		}

		$payload = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) ) {
			return array(
				'code' => 400,
				'body' => array(
					'status'  => 'error',
					'message' => 'Invalid JSON',
				),
			);
		}

		$fimipay_order_id = isset( $payload['order_id'] ) ? (string) $payload['order_id'] : '';
		if ( $fimipay_order_id === '' ) {
			return array(
				'code' => 400,
				'body' => array(
					'status'  => 'error',
					'message' => 'Missing order_id',
				),
			);
		}

		$order = self::find_order( $fimipay_order_id, $payload );
		if ( ! $order ) {
			self::log( $gateway, 'Webhook order not found: ' . $fimipay_order_id, 'warning' );
			return array(
				'code' => 404,
				'body' => array(
					'status'  => 'error',
					'message' => 'Order not found',
				),
			);
		}

		$status = self::extract_status( $payload );
		$event  = isset( $payload['event'] ) ? (string) $payload['event'] : '';

		if ( $event === 'payment.success' && $status === '' ) {
			$status = 'SUCCESS';
		}

		self::log(
			$gateway,
			sprintf(
				'Webhook for WC #%1$s FimiPay %2$s event=%3$s status=%4$s',
				$order->get_id(),
				$fimipay_order_id,
				$event,
				$status
			)
		);

		$gateway->apply_payment_status( $order, $status, $payload );

		return array(
			'code' => 200,
			'body' => array(
				'status'  => 'success',
				'message' => 'Webhook processed',
			),
		);
	}

	/**
	 * Resolve payment status from v4 or v3 payload.
	 *
	 * @param array $payload Payload.
	 * @return string
	 */
	private static function extract_status( array $payload ) {
		if ( ! empty( $payload['payment_status'] ) ) {
			return strtoupper( (string) $payload['payment_status'] );
		}
		if ( ! empty( $payload['status'] ) ) {
			return strtoupper( (string) $payload['status'] );
		}
		return '';
	}

	/**
	 * Find WooCommerce order by FimiPay order id / merchant ref.
	 *
	 * @param string $fimipay_order_id FimiPay order id.
	 * @param array  $payload          Full payload.
	 * @return WC_Order|null
	 */
	private static function find_order( $fimipay_order_id, array $payload ) {
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'objects',
				'meta_key'   => '_fimipay_order_id',
				'meta_value' => $fimipay_order_id,
			)
		);

		if ( ! empty( $orders ) ) {
			return $orders[0];
		}

		// Fallback: merchant order_id we sent (wc_{id}_{rand}).
		$ref = $fimipay_order_id;
		if ( preg_match( '/^wc_(\d+)_/', $ref, $m ) ) {
			$order = wc_get_order( (int) $m[1] );
			if ( $order ) {
				return $order;
			}
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'return'     => 'objects',
				'meta_key'   => '_fimipay_merchant_ref',
				'meta_value' => $fimipay_order_id,
			)
		);

		return ! empty( $orders ) ? $orders[0] : null;
	}

	/**
	 * Get configured FimiPay gateway instance.
	 *
	 * @return Fimipay_Gateway|null
	 */
	private static function get_gateway() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return null;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $gateways['fimipay'] ) || ! $gateways['fimipay'] instanceof Fimipay_Gateway ) {
			return null;
		}
		return $gateways['fimipay'];
	}

	/**
	 * Log via gateway debug setting.
	 *
	 * @param Fimipay_Gateway $gateway Gateway.
	 * @param string          $message Message.
	 * @param string          $level   Level.
	 */
	private static function log( Fimipay_Gateway $gateway, $message, $level = 'info' ) {
		if ( 'yes' !== $gateway->debug || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		wc_get_logger()->log( $level, $message, array( 'source' => 'fimipay' ) );
	}
}
