# iNPAY Checkout for WooCommerce

Accept Pay ID and bank transfer payments on WooCommerce stores using [iNPAY Checkout](https://inpaycheckout.com/). The gateway renders the iNPAY inline modal, verifies payments server-to-server, and supports both the classic and block-based checkout experiences.

## Requirements

- WordPress 6.2+
- WooCommerce 8.0+
- PHP 7.4+
- Store currency set to Nigerian Naira (NGN)

## Installation

1. Download or clone this repository into `wp-content/plugins/inpay-checkout-for-woocommerce`.
2. In wp-admin, go to **Plugins → Installed Plugins** and activate **iNPAY Checkout for WooCommerce**.
3. Navigate to **WooCommerce → Settings → Payments** and enable **iNPAY Checkout**.

## Configuration

### Obtain API Keys

1. Log in to the [iNPAY Checkout Dashboard](https://dashboard.inpaycheckout.com/).
2. Navigate to **Settings → Webhooks**.
3. Copy your Public Key and Secret Key.

### Configure the Gateway

1. In wp-admin, go to **WooCommerce → Settings → Payments → iNPAY Checkout**.
2. Enable the gateway and paste your Public and Secret Keys.
3. (Optional) Enable **Debug logging** while testing. Logs are stored under **WooCommerce → Status → Logs**.

### Configure the Webhook

1. On the same gateway settings screen, copy the Webhook URL provided in the blue notice.
2. In the iNPAY Dashboard, go to **Settings → Webhooks**, paste the URL, and save.
3. iNPAY expects the endpoint to return HTTP 200 for accepted payloads.

## Usage Notes

- The integration currently supports one-off payments in NGN via Pay ID and bank transfer.
- The gateway relies on iNPAY's server-side verification and webhook events; the order will complete when iNPAY marks the transaction as `completed` and `verified`.
- Keep logging enabled during initial testing to troubleshoot payloads or amounts; disable it for production sites to minimize log noise.

## Support

For assistance, contact [support@inpaycheckout.com](mailto:support@inpaycheckout.com).

## Contributors

- Plugin author: [Dan](https://github.com/arowolodaniel)
- Maintained by the iNTECH Developers team at iNTECH Management Limited

## License

This plugin is distributed under the GPL-2.0+ license. © iNTECH Management Limited – All rights reserved.
