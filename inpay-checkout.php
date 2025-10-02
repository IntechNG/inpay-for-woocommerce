<?php
/**
 * Plugin Name: iNPAY Checkout for WooCommerce
 * Description: Accept Pay ID and bank transfer payments via iNPAY Checkout.
 * Version: 0.1.0
 * Author: iNPAY Contributors
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Text Domain: inpay-checkout
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INPAY_CHECKOUT_MAIN_FILE', __FILE__ );
define( 'INPAY_CHECKOUT_VERSION', '0.1.0' );
define( 'INPAY_CHECKOUT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );

define( 'INPAY_CHECKOUT_MIN_WC_VERSION', '8.0.0' );

define( 'INPAY_CHECKOUT_SUPPORTED_CURRENCY', 'NGN' );

/**
 * Bootstrap the gateway after WooCommerce is ready.
 */
function inpay_checkout_init_gateway() {
	load_plugin_textdomain( 'inpay-checkout', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', 'inpay_checkout_wc_missing_notice' );
		return;
	}

	if ( version_compare( WC_VERSION, INPAY_CHECKOUT_MIN_WC_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'inpay_checkout_wc_version_notice' );
		return;
	}

	require_once __DIR__ . '/includes/class-wc-gateway-inpay-checkout.php';

	if ( inpay_checkout_blocks_supported() ) {
		require_once __DIR__ . '/includes/class-wc-gateway-inpay-checkout-blocks-support.php';
	}
}
add_action( 'plugins_loaded', 'inpay_checkout_init_gateway', 99 );

/**
 * Register gateway with WooCommerce.
 *
 * @param array $methods Payment gateways.
 * @return array
 */
function inpay_checkout_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Inpay_Checkout';

	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'inpay_checkout_add_gateway' );

/**
 * Add settings shortcut on the plugins screen.
 *
 * @param array $links Existing links.
 * @return array
 */
function inpay_checkout_plugin_action_links( $links ) {
	$settings_link = array(
		'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=inpay_checkout' ) ) . '">' . esc_html__( 'Settings', 'inpay-checkout' ) . '</a>',
	);

	return array_merge( $settings_link, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'inpay_checkout_plugin_action_links' );

/**
 * Check if WooCommerce Blocks integration is available.
 *
 * @return bool
 */
function inpay_checkout_blocks_supported() {
	return class_exists( '\\Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType' );
}

/**
 * Register blocks support for the gateway.
 */
function inpay_checkout_register_blocks_support() {
	if ( ! inpay_checkout_blocks_supported() ) {
		return;
	}

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function( $payment_method_registry ) {
			$payment_method_registry->register( new WC_Gateway_Inpay_Checkout_Blocks_Support() );
		}
	);
}
add_action( 'woocommerce_blocks_loaded', 'inpay_checkout_register_blocks_support' );

/**
 * Show a notice when WooCommerce is missing.
 */
function inpay_checkout_wc_missing_notice() {
	echo '<div class="error"><p><strong>' . esc_html__( 'iNPAY Checkout requires WooCommerce to be activated.', 'inpay-checkout' ) . '</strong></p></div>';
}

/**
 * Show a notice when WooCommerce is outdated.
 */
function inpay_checkout_wc_version_notice() {
	echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'iNPAY Checkout requires WooCommerce %s or newer.', 'inpay-checkout' ), INPAY_CHECKOUT_MIN_WC_VERSION ) . '</strong></p></div>';
}

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', INPAY_CHECKOUT_MAIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', INPAY_CHECKOUT_MAIN_FILE, true );
		}
	}
);
