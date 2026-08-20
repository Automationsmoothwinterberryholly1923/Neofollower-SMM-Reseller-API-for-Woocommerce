# Developer Guide

## Main plugin file

```text
neofollower-smm-reseller-api-for-woocommerce.php
```

The bootstrap file defines constants, loads plugin classes, declares WooCommerce compatibility, registers activation/deactivation hooks, and starts the singleton plugin instance after plugins are loaded.

## Core classes

### `NFWC_Plugin`

Responsibilities:

- plugin initialization
- WooCommerce dependency handling
- plugin action links
- WordPress privacy-policy suggestion
- recurring schedule registration
- log cleanup
- balance monitoring and alerts
- activation/deactivation scheduling

### `NFWC_API`

Responsibilities:

- read the configured API key
- resolve the NeoFollower API endpoint
- send `POST` requests using WordPress HTTP APIs
- parse JSON responses
- normalize transport/API failures
- list services
- check balance
- place external orders
- check external order status
- synchronize the service catalog

Core methods:

```php
services()
balance()
add_order($payload)
status($neo_order_id)
sync_services()
```

### `NFWC_Admin`

Responsibilities:

- WooCommerce → Neofollower admin UI
- settings
- connection testing
- balance checks
- service synchronization
- fulfillment order administration
- manual retries
- manual status synchronization
- log management
- support form

### `NFWC_Product`

Responsibilities:

- product-level fulfillment configuration
- service search
- storefront target/quantity/comment fields
- cart validation
- cart item data
- dynamic quantity/price handling
- order-item fulfillment metadata

### `NFWC_Order`

Responsibilities:

- detect eligible paid orders
- submit configured line items
- duplicate-submission locking
- external order ID storage
- fulfillment records
- scheduled and manual status synchronization
- failure notifications
- customer-facing status display

### `NFWC_DB`

Responsibilities:

- custom database table creation
- default settings
- service catalog persistence
- fulfillment-order persistence
- diagnostic logs
- cleanup operations

## Custom database tables

The plugin creates three tables using the site's WordPress database prefix.

### `nfwc_services`

Stores synchronized NeoFollower service information.

### `nfwc_orders`

Stores the relationship between WooCommerce line items and NeoFollower fulfillment orders.

### `nfwc_logs`

Stores diagnostic events.

## Important order-item metadata

Internal order-item fields use the `_nfwc_` prefix, including:

```text
_nfwc_enabled
_nfwc_product_id
_nfwc_service_id
_nfwc_service_type
_nfwc_link
_nfwc_api_quantity
_nfwc_comments
_nfwc_runs
_nfwc_interval
_nfwc_neo_order_id
_nfwc_status
_nfwc_submit_lock
```

Internal fields are hidden from normal WooCommerce order-item metadata output where appropriate.

## Cron hooks

Current scheduled hooks include:

```text
nfwc_cron_sync_statuses
nfwc_cron_check_balance
nfwc_cron_cleanup_logs
```

The plugin adds a 10-minute cron interval and a daily interval for its scheduled work.

## API endpoint filter

Developers can replace the default API endpoint with:

```php
add_filter('nfwc_api_endpoint', function ($endpoint) {
    return $endpoint;
});
```

The production default is:

```text
https://panel.neofollower.com/api/v1
```

## WooCommerce compatibility

The plugin declares compatibility with:

```text
custom_order_tables
cart_checkout_blocks
```

## Development principles

Changes should:

- preserve WordPress 6.2+ compatibility;
- preserve PHP 7.4+ syntax;
- use WordPress escaping, sanitization, capability checks, and nonces;
- use WooCommerce APIs rather than direct assumptions about order storage;
- use `$wpdb->prepare()` correctly for dynamic SQL;
- avoid exposing the NeoFollower API key;
- preserve duplicate-submission protection;
- avoid sending external requests before an administrator configures the service or an eligible fulfillment action requires one.

## PHP syntax check

A simple local syntax check can be run with:

```bash
find . -name '*.php' -type f -print0 | xargs -0 -n1 php -l
```

The GitHub workflow in this repository runs a syntax check across supported PHP versions.
