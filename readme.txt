=== iNPAY Checkout for WooCommerce ===
Contributors: intechdevelopers, arowolodaniel
Donate link: https://intechdevelopers.com/donate
Tags: woocommerce, payments, nigeria, bank-transfer, pay-id
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Pay ID and bank transfer payments in WooCommerce using the iNPAY Checkout inline modal.

== Description ==

iNPAY Checkout for WooCommerce enables Nigerian merchants to accept Pay ID and bank transfer payments without leaving their store. The gateway surfaces the iNPAY inline modal on both the classic "pay for order" page and the WooCommerce block-based checkout, then verifies each transaction server-to-server and via webhooks for maximum reliability.

= Features =
* Inline checkout modal powered by iNPAY Checkout.
* Support for Pay ID and bank transfer flows.
* Currency guard for Nigerian Naira (NGN).
* Automatic order verification using iNPAY’s status/verify endpoints.
* Webhook listener with signature validation and timestamp checks.
* Optional debug logging to WooCommerce logs.
* Compatible with the WooCommerce Checkout Block.

== Installation ==

1. Upload the plugin folder `inpay-checkout-for-woocommerce` to the `/wp-content/plugins/` directory, or install the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **WooCommerce → Settings → Payments** and enable **iNPAY Checkout**.

== Frequently Asked Questions ==

= What keys are required? =
Log in to the [iNPAY Checkout Dashboard](https://dashboard.inpaycheckout.com/), then go to **Settings → Webhooks** to copy your Public Key and Secret Key.

= How do I configure the webhook? =
The gateway settings screen shows a webhook URL. Copy it to the iNPAY Dashboard under **Settings → Webhooks** so iNPAY can send payment notifications.

= What currencies are supported? =
The gateway currently supports payments in Nigerian Naira (NGN).

= Where can I get help? =
Open a discussion on [GitHub](https://github.com/IntechNG/inpay-checkout-for-woocommerce/discussions) or email [support@inpaycheckout.com](mailto:support@inpaycheckout.com).

== Screenshots ==
1. iNPAY Checkout settings page in WooCommerce.
2. iNPAY modal on the pay-for-order screen.
3. iNPAY modal launched from the Checkout Block.

== Changelog ==

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.0 =
Initial public release of the iNPAY Checkout gateway.
