# Configuration

## Global NeoFollower settings

Open:

```text
WooCommerce → Neofollower
```

The plugin stores its settings in WordPress and uses them for server-side fulfillment.

Current settings include:

- NeoFollower API key
- automatic status synchronization
- low-balance threshold
- low-balance email alerts
- low-balance recipient
- low-balance alert interval
- pause fulfillment when balance is low
- failed-fulfillment email alerts
- failure notification recipient
- log retention period
- delete plugin data on uninstall

## API connection

The default API endpoint is:

```text
https://panel.neofollower.com/api/v1
```

The plugin uses WordPress `wp_remote_post()` and sends API requests from the server.

The endpoint can be filtered by developers through:

```php
nfwc_api_endpoint
```

## Service synchronization

Use the service synchronization action in **WooCommerce → Neofollower** to retrieve the current NeoFollower service catalog.

Synchronized service data is stored locally so product configuration can search and select NeoFollower services without requesting the complete API service list every time an administrator edits a product.

## Product configuration

Edit a WooCommerce product and enable **Neofollower Fulfillment**.

The plugin can store product-level configuration including:

- service ID
- service type
- quantity mode
- fixed quantity
- minimum quantity
- maximum quantity
- target/link label
- target/link placeholder
- customer-facing note

## Fulfillment types

### Standard

Use for services that require:

```text
target + quantity
```

### Customer quantity

Allows the customer to select the API quantity within the configured limits.

### Custom comments

Collects comment lines from the customer and submits them for a compatible service.

### Package

Use for services where the configured API request does not require a quantity.

### Drip feed

Collects:

```text
quantity
runs
interval
```

for a service configured to use drip-feed fulfillment.

## Low-balance protection

If enabled, the plugin can:

1. check the NeoFollower balance;
2. compare it with the configured threshold;
3. send an alert;
4. optionally pause new external fulfillment while the balance is below the threshold.

This is designed to reduce avoidable failed WooCommerce fulfillment attempts caused by insufficient reseller balance.

## Logs

Logs support troubleshooting of actions such as:

- API communication
- service synchronization
- external order submission
- status synchronization
- balance monitoring
- alerting

Log retention is configurable.

Do not copy logs into public bug reports without reviewing them for order, customer, or site information.
