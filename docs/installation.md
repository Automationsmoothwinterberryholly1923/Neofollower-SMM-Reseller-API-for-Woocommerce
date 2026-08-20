# Installation

## Recommended: WordPress.org

Install the stable plugin from:

https://wordpress.org/plugins/neofollower-smm-reseller-api-for-woocommerce/

From the WordPress administrator:

1. Install and activate WooCommerce.
2. Go to **Plugins → Add New Plugin**.
3. Search for **Neofollower SMM Reseller API for WooCommerce**.
4. Install and activate the plugin.
5. Go to **WooCommerce → Neofollower**.
6. Enter your NeoFollower API key.
7. Save the settings.
8. Test the API connection.
9. Synchronize the service catalog.
10. Configure at least one WooCommerce product.
11. Place a complete test order.

## WP-CLI

```bash
wp plugin install neofollower-smm-reseller-api-for-woocommerce --activate
```

WooCommerce must also be installed and active.

## Manual installation

For a normal production site, use a stable ZIP distributed through WordPress.org.

1. Download the plugin ZIP.
2. Open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP.
4. Activate the plugin.

## Requirements

- WordPress 6.2+
- WooCommerce 6.0+
- PHP 7.4+
- NeoFollower account
- valid NeoFollower API key
- sufficient NeoFollower balance for live fulfillment

## Before accepting live orders

Always perform an end-to-end test that includes:

- product configuration;
- target field;
- quantity or comments when applicable;
- WooCommerce checkout;
- payment/order status transition;
- external NeoFollower submission;
- external order ID storage;
- status synchronization.

Also confirm the selected NeoFollower service's target format, minimum/maximum quantity, refill behavior, and current delivery conditions.
