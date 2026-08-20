=== Neofollower – SMM Reseller API for WooCommerce ===
Contributors: faridzain
Tags: woocommerce, smm panel, reseller, api, order fulfillment
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WooCommerce products to the Neofollower SMM panel API and automatically submit paid reseller orders for fulfillment.

== Description ==

**Neofollower – SMM Reseller API for WooCommerce** helps agencies, resellers, and store owners sell selected social media marketing services through a normal WooCommerce storefront.

The plugin connects individual WooCommerce products to services available in a Neofollower account. After a customer pays, the plugin can submit the service order to Neofollower, save the external order ID, and synchronize the fulfillment status back to WooCommerce.

This is useful when you want a WooCommerce-based SMM reseller store without building or operating a separate SMM panel interface.

= Main features =

* Connect each WooCommerce product to a synced Neofollower service.
* Collect a target profile, post, page, channel, username, or other required public link.
* Support fixed-quantity, customer-quantity, custom-comment, package, and drip-feed orders.
* Submit eligible paid WooCommerce orders automatically.
* Prevent duplicate external submissions with per-order-item locking.
* Synchronize active Neofollower order statuses on a schedule or manually.
* Monitor the Neofollower balance and send optional low-balance alerts.
* Pause new fulfillment when balance is below a configured threshold.
* Optionally remove all plugin data when the plugin is deleted.

= Requirements =

* WordPress 6.2 or later.
* WooCommerce 6.0 or later.
* PHP 7.4 or later.
* A Neofollower account with a valid API key and sufficient account balance.

The plugin is free software. Neofollower is a separate paid service whose catalog, pricing, and fulfillment terms apply.

= Useful documentation =

* [Neofollower API guide for resellers](https://neofollower.com/help-center/resellers-affiliates/neofollower-api-guide-for-resellers/)
* [Selling Neofollower services with WordPress and WooCommerce](https://neofollower.com/help-center/resellers-affiliates/how-to-sell-neofollower-services-with-wordpress-and-woocommerce/)

= Independent platform notice =

Neofollower and this plugin are independent from WooCommerce, Automattic, WordPress, and the social networks or platforms represented by available service categories. Product and platform names are used only to describe compatibility or the target of a service.

== External services ==

This plugin relies on the Neofollower service to provide its core fulfillment functionality.

By installing, configuring, and using a Neofollower API key, the store administrator authorizes the plugin to communicate with:

`https://panel.neofollower.com/api/v1`

The plugin contacts this API for the following administrator actions and enabled automations:

* Testing the API connection or checking balance.
* Synchronizing the service catalog.
* Submitting an eligible paid WooCommerce order.
* Synchronizing a previously submitted order status.
* Running configured low-balance or status synchronization tasks.

Depending on the action and selected service, transmitted information can include:

* Neofollower API key.
* Service ID.
* Public profile, post, video, page, channel, group, website, or other target URL.
* Public username or other target identifier entered by the customer.
* Quantity.
* Custom comment text.
* Drip-feed runs and interval.
* Neofollower order ID.

The optional dashboard support form sends an email to `info@neofollower.com` only after an authorized administrator checks the consent box and submits the form. It sends the support message together with the site name and URL, administrator email, current user name and email, WordPress version, WooCommerce version, PHP version, plugin version, and submission date.

Neofollower service policies:

* [Terms and Conditions](https://neofollower.com/terms-and-conditions/)
* [Privacy Policy](https://neofollower.com/privacy-policy/)
* [Refunds Policy](https://neofollower.com/refunds-policy/)

== Privacy ==

The plugin does not include advertising telemetry and does not contact Neofollower until an administrator configures the external service or explicitly sends a support request.

For enabled products, fulfillment information is stored in WooCommerce and plugin tables. It can include public target links or usernames, quantities, comments, drip-feed settings, service/order IDs, status responses, and logs.

The data supports fulfillment, status display, duplicate prevention, troubleshooting, and optional alerts. Log retention is configurable, and administrators can enable **Delete data on uninstall**.

The plugin adds suggested disclosure text to **Settings > Privacy** in WordPress so the store owner can adapt it for the site's own privacy policy.

Store owners are responsible for providing their customers with an appropriate privacy notice and for using the plugin and external service in accordance with applicable law and platform rules.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin ZIP through **Plugins > Add New > Upload Plugin**, or install it from the WordPress.org Plugin Directory when available.
3. Activate **Neofollower – SMM Reseller API for WooCommerce**.
4. Open **WooCommerce > Neofollower**.
5. Enter the API key from your Neofollower account and save the settings.
6. Test the connection, then synchronize the service catalog.
7. Edit a WooCommerce product and enable **Neofollower Fulfillment**.
8. Select the corresponding Neofollower service and configure the quantity, link field, and service type.
9. Place a complete test order before accepting live customer orders.

Always verify the service description, required link format, minimum and maximum quantity, refill rule, and delivery conditions before selling a service.

== Frequently Asked Questions ==

= Does this plugin create an SMM reseller panel? =

It connects WooCommerce products and payments to Neofollower API fulfillment. It does not replace every feature of a dedicated panel script.

= Is a Neofollower account required? =

Yes. A valid Neofollower account and API key are required because service synchronization, balance checks, order submission, and order status are provided by the external Neofollower service.

= Is the plugin itself paid? =

No. The plugin code is free and GPL-licensed. Orders placed through the Neofollower API use the balance and pricing of the connected Neofollower account.

= When is a customer order submitted? =

Enabled products are submitted after eligible WooCommerce payment/order events. An external order ID and per-item lock reduce duplicate submissions.

= What service types are supported? =

The plugin supports standard quantity services, customer-selected quantity, custom comments, package services without quantity, and drip-feed orders. Actual availability depends on the connected Neofollower service catalog.

= Can customers enter a username instead of a full link? =

Yes, when the selected service accepts a username. The product field label and placeholder can be changed for each WooCommerce product.

= Does it support variable products? =

Variations can inherit settings from an enabled parent product. Test the setup before selling live orders.

= Can I submit an order again after a failure? =

Yes. Authorized WooCommerce administrators can review the local fulfillment record and retry a failed item manually. Check the target, service, balance, and error message before retrying.

= Does the plugin send customer passwords? =

No. Normal Neofollower services should use public links, public usernames, quantities, or service-specific public information. Do not request or store a customer's social media password through this plugin.

= Where can I get support? =

Use **WooCommerce > Neofollower > Support / Report Bug**. The form lists the diagnostic data sent and requires consent.

== Changelog ==

= 1.2.3 =
* Aligned WordPress.org contributor metadata with the plugin owner account.
* Kept admin notices context-limited and hardened request handling for administrator actions.
* Standardized custom-table database queries with prepared identifier placeholders.

= 1.2.2 =
* Scoped the WooCommerce dependency notice to the Plugins screen.
* Updated the WordPress.org contributor username.

= 1.2.1 =

* Replaced dynamically assembled service-list SQL with identifier and value placeholders supported by `wpdb::prepare()`.
* Removed the unused Domain Path header because the plugin does not bundle a languages directory.
* Raised the minimum WordPress version to 6.2 for `%i` identifier-placeholder support.

= 1.2.0 =

* Renamed the plugin to Neofollower – SMM Reseller API for WooCommerce.
* Aligned the plugin folder, main file, and text domain with the intended WordPress.org slug.
* Added WordPress.org metadata and GPLv2-or-later licensing.
* Added external-service, data-transfer, privacy, and paid-service disclosures.
* Added product-setting and front-end add-to-cart nonce verification.
* Added explicit consent for dashboard support requests.
* Added privacy-policy suggestion text for site administrators.
* Added optional data cleanup on uninstall.
* Added a direct Settings action link on the Plugins screen.
* Hardened duplicate order submission with an atomic per-order-item lock.
* Improved request unslashing and review-sensitive input handling.
* Updated declared WordPress and WooCommerce compatibility.

= 1.1.3 =

* Previous internal release.

== Upgrade Notice ==

= 1.2.3 =

Improves WordPress.org contributor metadata alignment, request handling, and custom-table query preparation.
