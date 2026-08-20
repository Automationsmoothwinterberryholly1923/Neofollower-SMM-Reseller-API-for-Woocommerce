# Changelog

This file mirrors the public release history of **Neofollower – SMM Reseller API for WooCommerce**.

## 1.2.3

- Aligned WordPress.org contributor metadata with the plugin owner account.
- Kept admin notices context-limited and hardened request handling for administrator actions.
- Standardized custom-table database queries with prepared identifier placeholders.

## 1.2.2

- Scoped the WooCommerce dependency notice to the Plugins screen.
- Updated the WordPress.org contributor username.

## 1.2.1

- Replaced dynamically assembled service-list SQL with identifier and value placeholders supported by `wpdb::prepare()`.
- Removed the unused Domain Path header because the plugin does not bundle a languages directory.
- Raised the minimum WordPress version to 6.2 for `%i` identifier-placeholder support.

## 1.2.0

- Renamed the plugin to Neofollower – SMM Reseller API for WooCommerce.
- Aligned the plugin folder, main file, and text domain with the intended WordPress.org slug.
- Added WordPress.org metadata and GPLv2-or-later licensing.
- Added external-service, data-transfer, privacy, and paid-service disclosures.
- Added product-setting and front-end add-to-cart nonce verification.
- Added explicit consent for dashboard support requests.
- Added privacy-policy suggestion text for site administrators.
- Added optional data cleanup on uninstall.
- Added a direct Settings action link on the Plugins screen.
- Hardened duplicate order submission with an atomic per-order-item lock.
- Improved request unslashing and review-sensitive input handling.
- Updated declared WordPress and WooCommerce compatibility.

## 1.1.3

- Previous internal release.

The canonical stable release history is also maintained in [`readme.txt`](readme.txt) for WordPress.org.
