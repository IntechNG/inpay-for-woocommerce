<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Inpay_Checkout_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Gateway instance.
	 *
	 * @var WC_Gateway_Inpay_Checkout|null
	 */
	protected $gateway_instance;

	/**
	 * Payment method identifier.
	 *
	 * @var string
	 */
	protected $name = 'inpay_checkout';

	/**
	 * Set up block settings.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_inpay_checkout_settings', array() );
		$this->gateway_instance = null;
	}

	/**
	 * Whether the gateway should appear in the blocks checkout.
	 *
	 * @return bool
	 */
	public function is_active() {
		$payment_gateways = WC()->payment_gateways();

		$gateways = $payment_gateways->payment_gateways();

		if ( ! isset( $gateways[ $this->name ] ) ) {
			return false;
		}

		return $gateways[ $this->name ]->is_available();
}

	/**
	 * Register frontend scripts.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		$script_path = 'assets/js/blocks/frontend/inpay-checkout.js';
		$script_url  = plugins_url( $script_path, INPAY_CHECKOUT_MAIN_FILE );
		$asset_path  = plugin_dir_path( INPAY_CHECKOUT_MAIN_FILE ) . 'assets/js/blocks/frontend/inpay-checkout.asset.php';

		$asset = file_exists( $asset_path )
			? include $asset_path
			: array(
				'dependencies' => array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-i18n' ),
				'version'      => INPAY_CHECKOUT_VERSION,
			);

		wp_register_script(
			'wc-inpay-checkout-blocks',
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		return array( 'wc-inpay-checkout-blocks' );
	}

	/**
	 * Data supplied to the blocks script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateway = $this->get_gateway();

		if ( ! $gateway ) {
			return array();
		}

		return array(
			'title'             => $gateway->get_title(),
			'description'       => $gateway->get_description(),
			'supports'          => array_filter( $gateway->supports, array( $gateway, 'supports' ) ),
			'logoUrl'           => INPAY_CHECKOUT_URL . '/assets/images/inpay.png',
			'publicKey'         => $gateway->public_key,
			'isEnabled'         => $gateway->is_available(),
			'allowSavedCards'   => false,
			'inlineScriptsUrls' => array( 'https://js.inpaycheckout.com/v1/inline.js' ),
		);
	}

	/**
	 * Retrieve the concrete gateway instance.
	 *
	 * @return WC_Gateway_Inpay_Checkout|false
	 */
	protected function get_gateway() {
		if ( null !== $this->gateway_instance ) {
			return $this->gateway_instance;
		}

		$payment_gateways = WC()->payment_gateways();
		$gateways         = $payment_gateways->payment_gateways();
		$gateway          = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : false;

		if ( $gateway ) {
			$this->gateway_instance = $gateway;
		}

		return $this->gateway_instance;
	}
}
