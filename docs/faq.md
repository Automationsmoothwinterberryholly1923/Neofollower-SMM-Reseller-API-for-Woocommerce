# FAQ

## What is Neofollower – SMM Reseller API for WooCommerce?

It is a free WordPress plugin that connects selected WooCommerce products to NeoFollower's reseller API so paid store orders can be submitted for external fulfillment.

## Is this a WooCommerce SMM panel plugin?

It provides SMM reseller functionality inside WooCommerce. It is best described as a WooCommerce-to-NeoFollower reseller integration rather than a full replacement for every feature of a dedicated SMM panel script.

## Where can I download the official plugin?

https://wordpress.org/plugins/neofollower-smm-reseller-api-for-woocommerce/

## What is the official WordPress plugin slug?

```text
neofollower-smm-reseller-api-for-woocommerce
```

## Is WooCommerce required?

Yes.

## Which WordPress version is required?

WordPress 6.2 or later.

## Which PHP version is required?

PHP 7.4 or later.

## Which WooCommerce version is required?

WooCommerce 6.0 or later.

## Do I need a NeoFollower account?

Yes. The plugin requires a NeoFollower account and API key to synchronize services, check balance, place orders, and synchronize fulfillment status.

## Is the plugin free?

Yes. The WordPress plugin code is free and GPL-licensed. NeoFollower API orders use the connected account's balance and service pricing.

## What API endpoint does the plugin use?

```text
https://panel.neofollower.com/api/v1
```

## How does the plugin communicate with NeoFollower?

It sends server-side `POST` requests using the WordPress HTTP API.

## Can the plugin automatically submit paid WooCommerce orders?

Yes. Products configured for NeoFollower fulfillment are processed after eligible WooCommerce payment/order events.

## How does it prevent duplicate orders?

It stores the external NeoFollower order ID and uses a per-order-item submission lock to reduce duplicate submissions when multiple WooCommerce events fire.

## Can I manually retry a failed external order?

Yes. An authorized WooCommerce administrator can review and retry a failed fulfillment record.

## Does it synchronize service status?

Yes. Active external orders can be synchronized automatically on a schedule or manually.

## Can it synchronize the NeoFollower service catalog?

Yes.

## Does it support custom comments?

Yes.

## Does it support customer-selected quantities?

Yes, within the configured product limits.

## Does it support package services without a quantity?

Yes.

## Does it support drip feed?

Yes. A configured drip-feed product can send quantity, runs, and interval values.

## Can it monitor my NeoFollower reseller balance?

Yes. It can check balance, send low-balance alerts, and optionally pause new fulfillment below a configured threshold.

## Does it support WooCommerce HPOS?

The plugin declares compatibility with WooCommerce custom order tables (HPOS).

## Does it support WooCommerce cart and checkout blocks?

The plugin declares compatibility with WooCommerce cart and checkout blocks.

## Does it include advertising telemetry?

No.

## Does it send customer social media passwords?

No. Do not configure your store to request customer social media passwords for this plugin.

## Where is the API documentation?

https://panel.neofollower.com/api/docs

## Where should I ask for plugin support?

Use the WordPress.org plugin support forum or **WooCommerce → Neofollower → Support / Report Bug**.

## Where should I ask about NeoFollower account balance, services, or live orders?

Use NeoFollower's official support channels.
