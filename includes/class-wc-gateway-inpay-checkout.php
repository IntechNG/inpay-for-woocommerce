<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Inpay_Checkout extends WC_Payment_Gateway {

	/**
	 * API public key.
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	protected $secret_key;

	/**
	 * Whether logging is enabled.
	 *
	 * @var bool
	 */
	protected $logging_enabled;

	/**
	 * Logger instance.
	 *
	 * @var WC_Logger
	 */
	protected $logger;

	/**
	 * Supported webhook completion events.
	 *
	 * @var string[]
	 */
	protected $webhook_completion_events = array(
		'payment.virtual_payid.completed',
		'payment.checkout_payid.completed',
		'payment.virtual_account.completed',
		'payment.checkout_virtual_account.completed',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'inpay_checkout';
		$this->method_title       = __( 'iNPAY Checkout', 'inpay-checkout' );
		$this->method_description = __( 'Accept Pay ID and bank transfer payments with iNPAY Checkout.', 'inpay-checkout' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->icon               = INPAY_CHECKOUT_URL . '/assets/images/inpay.png';

		$this->init_form_fields();
		$this->init_settings();

		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->enabled          = $this->get_option( 'enabled' );
		$this->public_key       = trim( $this->get_option( 'public_key' ) );
		$this->secret_key       = trim( $this->get_option( 'secret_key' ) );
		$this->logging_enabled  = 'yes' === $this->get_option( 'enable_logging', 'no' );
		$this->logger           = wc_get_logger();

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_wc_gateway_inpay_checkout', array( $this, 'handle_checkout_callback' ) );
		add_action( 'woocommerce_api_inpay_checkout_webhook', array( $this, 'handle_webhook' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'log_available_gateways' ), 1000 );
		
		// Hide WooCommerce default order summary on pay-for-order page for our gateway
		add_action( 'wp', array( $this, 'hide_default_order_summary' ) );
	}

	/**
	 * Render admin options page content.
	 */
	public function admin_options() {
		$webhook_url = WC()->api_request_url( 'inpay_checkout_webhook' );
		?>
		<h2>
			<?php echo esc_html( $this->get_method_title() ); ?>
			<?php
			if ( function_exists( 'wc_back_link' ) ) {
				wc_back_link( __( 'Return to payments', 'inpay-checkout' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
			}
			?>
		</h2>

		<?php if ( $this->method_description ) : ?>
		<p><?php echo wp_kses_post( $this->method_description ); ?></p>
		<?php endif; ?>

		<div class="notice notice-info" style="margin: 15px 0;">
			<p>
				<strong><?php esc_html_e( 'Webhook configuration', 'inpay-checkout' ); ?></strong><br />
				<?php esc_html_e( 'Copy the URL below into your iNPAY dashboard so payment notifications are delivered. iNPAY expects your endpoint to return HTTP 200 when the payload is accepted.', 'inpay-checkout' ); ?>
			</p>
			<code style="display: inline-block; padding: 6px 10px; background: #fff; border: 1px solid #ccd0d4; border-radius: 3px;"><?php echo esc_html( $webhook_url ); ?></code>
		</div>

		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table>
		<?php
	}

	/**
	 * Gateway settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'        => array(
				'title'       => __( 'Enable/Disable', 'inpay-checkout' ),
				'label'       => __( 'Enable iNPAY Checkout', 'inpay-checkout' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable iNPAY Checkout as a payment option.', 'inpay-checkout' ),
				'default'     => 'no',
			),
			'title'          => array(
				'title'       => __( 'Title', 'inpay-checkout' ),
				'type'        => 'text',
				'description' => __( 'Controls the payment method title seen during checkout.', 'inpay-checkout' ),
				'default'     => __( 'iNPAY Checkout', 'inpay-checkout' ),
			),
			'description'    => array(
				'title'       => __( 'Description', 'inpay-checkout' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown on checkout forms.', 'inpay-checkout' ),
				'default'     => __( 'Pay securely using iNPAY Checkout.', 'inpay-checkout' ),
			),
			'public_key'     => array(
				'title'       => __( 'Public Key', 'inpay-checkout' ),
				'type'        => 'text',
				'description' => __( 'Your iNPAY public key.', 'inpay-checkout' ),
				'default'     => '',
			),
			'secret_key'     => array(
				'title'       => __( 'Secret Key', 'inpay-checkout' ),
				'type'        => 'password',
				'description' => __( 'Your iNPAY secret key.', 'inpay-checkout' ),
				'default'     => '',
			),
			'enable_logging' => array(
				'title'       => __( 'Logging', 'inpay-checkout' ),
				'label'       => __( 'Enable debug logging', 'inpay-checkout' ),
				'type'        => 'checkbox',
				'description' => __( 'Logs iNPAY Checkout events to WooCommerce > Status > Logs.', 'inpay-checkout' ),
				'default'     => 'no',
			),
		);
	}

	/**
	 * Display additional information on the checkout page.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) );
		}
	}

	/**
	 * Show admin warnings.
	 */
	public function admin_notices() {
		if ( 'yes' !== $this->enabled ) {
			return;
		}

		if ( ! $this->public_key || ! $this->secret_key ) {
			printf( '<div class="error"><p>%s</p></div>', esc_html__( 'Enter your iNPAY public and secret keys to start accepting payments.', 'inpay-checkout' ) );
		}

		if ( INPAY_CHECKOUT_SUPPORTED_CURRENCY !== get_woocommerce_currency() ) {
			printf( '<div class="error"><p>%s</p></div>', esc_html__( 'iNPAY Checkout only supports Nigerian Naira (NGN). Update your store currency to enable the gateway.', 'inpay-checkout' ) );
		}
	}

	/**
	 * Check if gateway can be used.
	 *
	 * @return bool
	 */
	public function is_available() {
		$currency      = get_woocommerce_currency();
		$currency_ok   = INPAY_CHECKOUT_SUPPORTED_CURRENCY === $currency;
		$key_provided  = ! empty( $this->public_key ) && ! empty( $this->secret_key );
		$enabled       = 'yes' === $this->enabled;
		$is_available  = $enabled && $currency_ok && $key_provided;

		if ( ! $is_available ) {
			$reasons = array();
			if ( ! $enabled ) {
				$reasons[] = 'gateway disabled';
			}
			if ( ! $currency_ok ) {
				$reasons[] = sprintf( 'unsupported currency (%s)', $currency );
			}
			if ( ! $key_provided ) {
				$reasons[] = 'missing keys';
			}
			$this->log( 'Gateway unavailable: ' . implode( ', ', $reasons ) );

			return false;
		}

		$this->log( sprintf( 'Gateway available. currency=%s, customer_country=%s', $currency, WC()->customer ? WC()->customer->get_billing_country() : 'n/a' ) );

		return true;
	}

	/**
	 * Process the payment and redirect to the order confirmation screen.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$this->ensure_order_reference( $order );
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true ),
		);
	}

	/**
	 * Enqueue scripts on the pay-for-order page.
	 */
	public function payment_scripts() {
		if ( ! $this->public_key || 'yes' !== $this->enabled ) {
			return;
		}

		if ( is_checkout() && ! is_checkout_pay_page() ) {
			$this->enqueue_checkout_scripts();
			return;
		}

		if ( is_checkout_pay_page() ) {
			$this->enqueue_order_pay_scripts();
		}
	}

	/**
	 * Enqueue scripts for classic checkout page.
	 */
	protected function enqueue_checkout_scripts() {
		if ( ! WC()->cart ) {
			return;
		}

		$order_id = get_current_user_id() ? WC()->session->get( 'inpay_last_order_id' ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			$order = false;
		}

		if ( $order ) {
			$params = $this->build_script_params_from_order( $order );
		} else {
			$params = array(
				'publicKey'   => $this->public_key,
				'ajaxUrl'     => WC()->api_request_url( 'wc_gateway_inpay_checkout' ),
				'returnUrl'   => wc_get_checkout_url(),
				'logoUrl'     => INPAY_CHECKOUT_URL . '/assets/images/inpay.png',
				'amountError' => __( 'Invalid payment amount.', 'inpay-checkout' ),
			);
		}

		$this->enqueue_script_with_params( $params );
	}

	/**
	 * Enqueue scripts for pay-for-order page.
	 */
	protected function enqueue_order_pay_scripts() {
		$order_id = absint( get_query_var( 'order-pay' ) );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$params = $this->build_script_params_from_order( $order );

		$this->enqueue_script_with_params( $params );
	}

	/**
	 * Localize script parameters and enqueue assets.
	 *
	 * @param array $params Script parameters.
	 */
	protected function enqueue_script_with_params( array $params ) {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'wc_inpay_checkout', plugins_url( 'assets/js/inpay-checkout' . $suffix . '.js', INPAY_CHECKOUT_MAIN_FILE ), array( 'jquery' ), INPAY_CHECKOUT_VERSION, true );
		wp_localize_script( 'wc_inpay_checkout', 'wc_inpay_checkout_params', $params );
	}

	/**
	 * Build localized script parameters from an order.
	 *
	 * @param WC_Order $order Order instance.
	 * @return array
	 */
	protected function build_script_params_from_order( WC_Order $order ) {
		$reference = $this->ensure_order_reference( $order );
		$order->save();

		$amount_kobo = (int) round( $order->get_total() * 100 );

		$metadata = array(
			'order_id'     => $order->get_id(),
			'gateway'      => 'woocommerce-inpay-checkout',
			'reference'    => $reference,
			'phone'        => $order->get_billing_phone(),
			'callback_url' => WC()->api_request_url( 'wc_gateway_inpay_checkout' ),
		);

		return array(
			'publicKey'     => $this->public_key,
			'amount'        => $amount_kobo,
			'currency'      => $order->get_currency(),
			'reference'     => $reference,
			'email'         => $order->get_billing_email(),
			'firstName'     => $order->get_billing_first_name(),
			'lastName'      => $order->get_billing_last_name(),
			'phone'         => $order->get_billing_phone(),
			'orderId'       => $order->get_id(),
			'nonce'         => wp_create_nonce( 'inpay_checkout_verify_' . $order->get_id() ),
			'metadata'      => wp_json_encode( $metadata ),
			'amountError'   => __( 'Invalid payment amount.', 'inpay-checkout' ),
			'logoUrl'       => INPAY_CHECKOUT_URL . '/assets/images/inpay.png',
			'ajaxUrl'       => WC()->api_request_url( 'wc_gateway_inpay_checkout' ),
			'orderUrl'      => $this->get_return_url( $order ),
			'cancelUrl'     => $order->get_cancel_order_url(),
			'payButtonText' => __( 'Pay with iNPAY', 'inpay-checkout' ),
			'amountText'    => wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ),
		);
	}

	/**
	 * Output the payment button on the order pay page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function receipt_page( $order_id ) {
		$order = wc_get_order( $order_id );

		// Enqueue our custom CSS
		wp_enqueue_style( 
			'inpay-checkout-style', 
			INPAY_CHECKOUT_URL . '/assets/css/inpay-checkout.css', 
			array(), 
			INPAY_CHECKOUT_VERSION 
		);

		?>
		<div class="inpay-checkout-container">
			<!-- Order Summary -->
			<div class="inpay-order-summary">
				<h3><?php esc_html_e( 'Order Summary', 'inpay-checkout' ); ?></h3>
				<div class="inpay-order-details">
					<div class="inpay-order-item">
						<span class="inpay-order-item-label"><?php esc_html_e( 'Order Number', 'inpay-checkout' ); ?></span>
						<span class="inpay-order-item-value">#<?php echo esc_html( $order->get_order_number() ); ?></span>
					</div>
					<div class="inpay-order-item">
						<span class="inpay-order-item-label"><?php esc_html_e( 'Date', 'inpay-checkout' ); ?></span>
						<span class="inpay-order-item-value"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></span>
					</div>
					<div class="inpay-order-item">
						<span class="inpay-order-item-label"><?php esc_html_e( 'Total', 'inpay-checkout' ); ?></span>
						<span class="inpay-order-item-value inpay-amount"><?php echo wp_kses_post( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></span>
					</div>
					<div class="inpay-order-item">
						<span class="inpay-order-item-label"><?php esc_html_e( 'Payment Method', 'inpay-checkout' ); ?></span>
						<span class="inpay-order-item-value"><?php esc_html_e( 'iNPAY Checkout', 'inpay-checkout' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Payment Instructions -->
			<div class="inpay-payment-instructions">
				<img src="<?php echo esc_url( INPAY_CHECKOUT_URL . '/assets/images/inpay.png' ); ?>" alt="iNPAY" />
				<p><?php esc_html_e( 'Click the button below to complete your payment with iNPAY Checkout.', 'inpay-checkout' ); ?></p>
			</div>

			<!-- Action Buttons -->
			<div class="inpay-action-buttons">
				<button class="inpay-pay-button" id="inpay-checkout-button">
					<span class="inpay-loading"></span>
					<?php esc_html_e( 'Pay with iNPAY', 'inpay-checkout' ); ?>
				</button>
				<a class="inpay-cancel-button" href="<?php echo esc_url( $order->get_cancel_order_url() ); ?>">
					<?php esc_html_e( 'Cancel order & restore cart', 'inpay-checkout' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX verification requests from the frontend.
	 */
	public function handle_checkout_callback() {
		$payload = file_get_contents( 'php://input' );
		$data    = json_decode( $payload, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			$data = wp_unslash( $_REQUEST );
		}

		$reference = isset( $data['reference'] ) ? sanitize_text_field( wp_unslash( $data['reference'] ) ) : '';
		$order_id  = isset( $data['order_id'] ) ? absint( $data['order_id'] ) : 0;
		$nonce     = isset( $data['nonce'] ) ? sanitize_text_field( wp_unslash( $data['nonce'] ) ) : '';

		if ( ! $reference || ! $order_id || ! wp_verify_nonce( $nonce, 'inpay_checkout_verify_' . $order_id ) ) {
			$this->log( 'Verification request rejected: invalid payload.' );
			wp_send_json_error( array( 'message' => __( 'Invalid verification request.', 'inpay-checkout' ) ), 400 );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			$this->log( 'Verification request rejected: order mismatch.' );
			wp_send_json_error( array( 'message' => __( 'Unable to verify this payment.', 'inpay-checkout' ) ), 404 );
		}

		$result = $this->verify_transaction( $reference );

		if ( is_wp_error( $result ) ) {
			$this->log( 'Verification error: ' . $result->get_error_message() );
			wp_send_json_error( array( 'message' => __( 'We could not verify the payment. Please try again.', 'inpay-checkout' ) ), 502 );
		}

		list( $transaction, $reason ) = $result;

		if ( ! $this->is_transaction_successful( $transaction ) ) {
			$this->log( sprintf( 'Verification failed for %s: %s', $reference, $reason ) );
			wp_send_json_error( array( 'message' => __( 'Payment not completed. Please try again.', 'inpay-checkout' ) ), 402 );
		}

		$stored_reference   = $order->get_meta( '_inpay_checkout_reference' );
		$transaction_meta   = isset( $transaction['metadata'] ) ? $this->parse_metadata( $transaction['metadata'] ) : array();
		$metadata_reference = isset( $transaction_meta['reference'] ) ? $transaction_meta['reference'] : '';
		$transaction_ref    = isset( $transaction['reference'] ) ? $transaction['reference'] : '';

		if ( $stored_reference && $metadata_reference && $stored_reference !== $metadata_reference ) {
			$this->log( sprintf( 'Reference mismatch detected. stored=%1$s metadata=%2$s txn=%3$s order=%4$d', $stored_reference, $metadata_reference, $transaction_ref, $order_id ) );
			wp_send_json_error( array( 'message' => __( 'Payment reference mismatch.', 'inpay-checkout' ) ), 409 );
		}

		if ( empty( $stored_reference ) && $metadata_reference ) {
			$order->update_meta_data( '_inpay_checkout_reference', $metadata_reference );
			$order->save();
		}

		$status = $this->finalize_order_payment( $order, $transaction );

		if ( is_wp_error( $status ) ) {
			$this->log( 'Order finalization error: ' . $status->get_error_message() );
			wp_send_json_error( array( 'message' => __( 'Payment verified but the order could not be updated. Contact support.', 'inpay-checkout' ) ), 500 );
		}

		$this->log( sprintf( 'Payment verified for order %d via checkout confirmation.', $order_id ) );

		wp_send_json_success( array( 'redirect' => $this->get_return_url( $order ) ) );
	}

	/**
	 * Handle incoming webhook requests.
	 */
	public function handle_webhook() {
		$payload   = file_get_contents( 'php://input' );
		$signature = isset( $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ) ) : '';
		$timestamp = isset( $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ) ? (int) $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] : 0;
		$event     = isset( $_SERVER['HTTP_X_WEBHOOK_EVENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WEBHOOK_EVENT'] ) ) : '';

		if ( ! $this->secret_key ) {
			http_response_code( 401 );
			exit;
		}

		if ( ! $this->is_webhook_timestamp_valid( $timestamp ) ) {
			$this->log( 'Webhook rejected: timestamp skew.' );
			http_response_code( 400 );
			exit;
		}

		if ( ! $this->is_webhook_signature_valid( $payload, $signature ) ) {
			$this->log( 'Webhook rejected: signature mismatch.' );
			http_response_code( 401 );
			exit;
		}

		$data = json_decode( $payload, true );
		$this->log( 'Webhook payload received: ' . $payload );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['event'] ) ) {
			$this->log( 'Webhook rejected: invalid payload.' );
			http_response_code( 400 );
			echo 'Invalid payload';
			exit;
		}

		if ( ! in_array( $event, $this->webhook_completion_events, true ) ) {
			http_response_code( 200 );
			echo 'Ignored event';
			exit;
		}

		$transaction = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		$metadata    = $this->parse_metadata( isset( $transaction['metadata'] ) ? $transaction['metadata'] : array() );

		$order_id = isset( $metadata['order_id'] ) ? absint( $metadata['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			http_response_code( 200 );
			echo 'Order not found';
			exit;
		}

		$reference = isset( $transaction['reference'] ) ? sanitize_text_field( $transaction['reference'] ) : '';

		if ( ! $reference ) {
			http_response_code( 200 );
			echo 'Reference missing';
			exit;
		}

		$verify = $this->verify_transaction( $reference );

		if ( is_wp_error( $verify ) ) {
			$this->log( 'Webhook verification failed: ' . $verify->get_error_message() );
			http_response_code( 200 );
			echo 'Verification failed';
			exit;
		}

		list( $verified_transaction ) = $verify;

		if ( ! $this->is_transaction_successful( $verified_transaction ) ) {
			http_response_code( 200 );
			echo 'Transaction not completed';
			exit;
		}

		$status = $this->finalize_order_payment( $order, $verified_transaction );

		if ( is_wp_error( $status ) ) {
			$this->log( 'Webhook order update failed: ' . $status->get_error_message() );
			http_response_code( 500 );
			echo 'Order update failed';
			exit;
		} else {
			$this->log( sprintf( 'Payment verified for order %d via webhook.', $order->get_id() ) );
		}

		http_response_code( 200 );
		echo 'OK';
		exit;
	}

	/**
	 * Ensure the order has a unique reference stored.
	 *
	 * @param WC_Order $order Order instance.
	 * @return string
	 */
	protected function ensure_order_reference( WC_Order $order ) {
		$reference = $order->get_meta( '_inpay_checkout_reference' );

		if ( $reference ) {
			return $reference;
		}

		$reference = sprintf( '%d_%d_%s', $order->get_id(), time(), strtolower( wp_generate_password( 8, false, false ) ) );

		$order->update_meta_data( '_inpay_checkout_reference', $reference );

		return $reference;
	}

	/**
	 * Verify a transaction with iNPAY.
	 *
	 * @param string $reference Transaction reference.
	 * @return array|WP_Error
	 */
	protected function verify_transaction( $reference ) {
		if ( ! $this->secret_key ) {
			return new WP_Error( 'inpay_missing_secret', __( 'Missing iNPAY secret key.', 'inpay-checkout' ) );
		}

		$endpoints = array(
			array(
				'method' => 'GET',
				'url'    => 'https://api.inpaycheckout.com/api/v1/developer/transaction/status?reference=' . rawurlencode( $reference ),
			),
			array(
				'method' => 'POST',
				'url'    => 'https://api.inpaycheckout.com/api/v1/developer/transaction/verify',
				'body'   => wp_json_encode( array( 'reference' => $reference ) ),
			),
		);

		$headers = array(
			'Authorization' => 'Bearer ' . $this->secret_key,
			'Accept'        => 'application/json',
		);

		foreach ( $endpoints as $endpoint ) {
			$args = array(
				'headers' => $headers,
				'body'    => isset( $endpoint['body'] ) ? $endpoint['body'] : null,
				'method'  => $endpoint['method'],
				'timeout' => 30,
			);

			if ( $endpoint['method'] === 'POST' ) {
				$args['headers']['Content-Type'] = 'application/json';
			}

			$response = wp_remote_request( $endpoint['url'], $args );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
				continue;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['data'] ) ) {
				continue;
			}

			$transaction = $data['data'];
			$reason      = isset( $data['message'] ) ? sanitize_text_field( $data['message'] ) : '';

			$this->log( 'Verification data received: ' . wp_json_encode( $transaction ) );

			return array( $transaction, $reason );
		}

		return new WP_Error( 'inpay_verification_failed', __( 'Unable to verify transaction with iNPAY.', 'inpay-checkout' ) );
	}

	/**
	 * Determine if the transaction is successful.
	 *
	 * @param array $transaction Transaction data.
	 * @return bool
	 */
	protected function is_transaction_successful( $transaction ) {
		if ( empty( $transaction ) ) {
			return false;
		}

		$status   = isset( $transaction['status'] ) ? strtolower( $transaction['status'] ) : '';
		$verified = isset( $transaction['verified'] ) ? $transaction['verified'] : false;

		return 'completed' === $status && in_array( $verified, array( true, 'true', 1, '1' ), true );
	}

	/**
	 * Finalize the WooCommerce order after a successful transaction.
	 *
	 * @param WC_Order $order Order instance.
	 * @param array    $transaction Transaction data.
	 * @return true|WP_Error
	 */
	protected function finalize_order_payment( WC_Order $order, $transaction ) {
		if ( in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			return true;
		}

		$reference    = isset( $transaction['reference'] ) ? sanitize_text_field( $transaction['reference'] ) : '';
		$paid_amount = 0;

		if ( isset( $transaction['amount'] ) ) {
			$paid_amount = (int) $transaction['amount'];
		}

		if ( ! $paid_amount && isset( $transaction['amount_paid'] ) ) {
			$paid_amount = (int) $transaction['amount_paid'];
		}

		if ( ! $paid_amount && isset( $transaction['amountKobo'] ) ) {
			$paid_amount = (int) $transaction['amountKobo'];
		}

		if ( ! $paid_amount ) {
			$metadata_amount = $this->extract_amount_from_metadata( $transaction );
			if ( $metadata_amount ) {
				$paid_amount = $metadata_amount;
			}
		}

		$order_amount = (int) round( $order->get_total() * 100 );

		if ( ! $paid_amount ) {
			$this->log( sprintf( 'No amount returned for order %d. Falling back to order total.', $order->get_id() ) );
			$paid_amount = $order_amount;
		}

		if ( $paid_amount && $order_amount && $paid_amount !== $order_amount ) {
			if ( $paid_amount * 100 === $order_amount ) {
				$this->log( sprintf( 'Normalizing amount for order %d. Converting %d to kobo.', $order->get_id(), $paid_amount ) );
				$paid_amount *= 100;
			} elseif ( (int) round( $order_amount / 100 ) === $paid_amount ) {
				$this->log( sprintf( 'Normalizing amount for order %d. Converting %d to naira.', $order->get_id(), $paid_amount ) );
				$paid_amount *= 100;
			}
		}

		$order->set_transaction_id( $reference );

		if ( $paid_amount < $order_amount ) {
			$this->log( sprintf( 'Amount mismatch for order %d. paid=%d expected=%d reference=%s', $order->get_id(), $paid_amount, $order_amount, $reference ) );
			$order->update_status( 'on-hold', __( 'iNPAY payment completed with a lower amount than expected.', 'inpay-checkout' ) );
			$order->save();

			return new WP_Error( 'inpay_amount_mismatch', __( 'Payment amount mismatch.', 'inpay-checkout' ) );
		}

		if ( $order->get_currency() !== INPAY_CHECKOUT_SUPPORTED_CURRENCY ) {
			$order->update_status( 'on-hold', __( 'iNPAY payment currency differs from the order currency.', 'inpay-checkout' ) );
			$order->save();

			return new WP_Error( 'inpay_currency_mismatch', __( 'Payment currency mismatch.', 'inpay-checkout' ) );
		}

		$order->update_meta_data( '_inpay_checkout_transaction', wp_json_encode( $transaction ) );
		$order->add_order_note( sprintf( __( 'iNPAY Checkout payment completed. Reference: %s', 'inpay-checkout' ), $reference ) );
		$order->payment_complete( $reference );

		if ( function_exists( 'WC' ) && isset( WC()->cart ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return true;
	}

	/**
	 * Parse metadata field.
	 *
	 * @param mixed $metadata Raw metadata.
	 * @return array
	 */
	protected function parse_metadata( $metadata ) {
		if ( is_array( $metadata ) ) {
			return $metadata;
		}

		if ( is_string( $metadata ) ) {
			$decoded = json_decode( $metadata, true );

			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Attempt to pull amount information from the transaction metadata.
	 *
	 * @param array $transaction Transaction data.
	 * @return int
	 */
	protected function extract_amount_from_metadata( $transaction ) {
		if ( empty( $transaction['metadata'] ) ) {
			return 0;
		}

		$metadata = $this->parse_metadata( $transaction['metadata'] );

		if ( isset( $metadata['amount'] ) ) {
			return (int) $metadata['amount'];
		}

		if ( isset( $metadata['amount_kobo'] ) ) {
			return (int) $metadata['amount_kobo'];
		}

		if ( isset( $metadata['amountKobo'] ) ) {
			return (int) $metadata['amountKobo'];
		}

		return 0;
	}

	/**
	 * Validate webhook signature.
	 *
	 * @param string $payload Raw body.
	 * @param string $signature Raw signature header.
	 * @return bool
	 */
	protected function is_webhook_signature_valid( $payload, $signature ) {
		if ( ! $signature ) {
			return false;
		}

		$computed = hash_hmac( 'sha256', $payload, $this->secret_key );
		$clean    = preg_replace( '/^sha256=/', '', $signature );

		return hash_equals( $computed, $clean );
	}

	/**
	 * Check webhook timestamp tolerance.
	 *
	 * @param int $timestamp Supplied timestamp in milliseconds.
	 * @return bool
	 */
	protected function is_webhook_timestamp_valid( $timestamp ) {
		if ( ! $timestamp ) {
			return false;
		}

		$now        = (int) round( microtime( true ) * 1000 );
		$allowed_skew = 5 * 60 * 1000;

		return abs( $now - $timestamp ) <= $allowed_skew;
	}

	/**
	 * Log data when enabled.
	 *
	 * @param string $message Log message.
	 */
	protected function log( $message ) {
		if ( ! $this->logging_enabled ) {
			return;
		}

		$this->logger->info( $message, array( 'source' => $this->id ) );
	}

	/**
	 * Log available gateways for troubleshooting.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function log_available_gateways( $gateways ) {
		if ( $this->logging_enabled ) {
			$this->logger->info( 'Available gateways: ' . implode( ', ', array_keys( $gateways ) ), array( 'source' => $this->id ) );
		}

		return $gateways;
	}

	/**
	 * Hide WooCommerce default order summary on pay-for-order page for our gateway.
	 */
	public function hide_default_order_summary() {
		if ( ! is_checkout_pay_page() ) {
			return;
		}

		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		// Remove WooCommerce default order summary actions
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_output_all_notices', 10 );
		
		// Remove order details from pay-for-order page
		remove_action( 'woocommerce_pay_order_before_payment', 'woocommerce_order_details_table', 10 );
		remove_action( 'woocommerce_pay_order_before_payment', 'woocommerce_order_details_table', 20 );
	}
}
