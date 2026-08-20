# How NeoFollower WooCommerce Fulfillment Works

## 1. Product configuration

A WooCommerce administrator enables NeoFollower fulfillment on a product and connects that product to a NeoFollower service ID.

The product configuration controls which storefront fields are collected and how the API payload is prepared.

## 2. Customer order

The customer purchases the WooCommerce product through the normal storefront and checkout.

Depending on the configured service type, the plugin can collect:

- public target link or username
- quantity
- custom comments
- drip-feed runs
- drip-feed interval

The plugin copies the required fulfillment information to WooCommerce order-item metadata.

## 3. Eligible paid order event

The plugin listens to these WooCommerce events:

```text
woocommerce_order_status_processing
woocommerce_order_status_completed
woocommerce_payment_complete
```

When one is triggered, the plugin checks the order items and processes only items that have NeoFollower fulfillment enabled.

## 4. Duplicate-submission protection

Before sending an item to NeoFollower, the plugin obtains a per-order-item submission lock.

If an external NeoFollower order ID is already stored, the item is not submitted again during normal processing.

This protects against common duplicate-trigger situations where more than one WooCommerce payment/order hook fires for the same order.

## 5. API order submission

The plugin sends a server-side `POST` request to:

```text
https://panel.neofollower.com/api/v1
```

with:

```text
action=add
```

plus the configured service payload.

## 6. External order ID

When NeoFollower returns an order ID, the plugin stores it against the WooCommerce order item and records the fulfillment event.

That external order ID is then used for later status synchronization.

## 7. Status synchronization

The plugin schedules status synchronization and also exposes administrator controls for manual synchronization.

A status check calls the NeoFollower API using:

```text
action=status
order=NEOFOLLOWER_ORDER_ID
```

The local fulfillment record is updated with the latest external state.

## 8. Failures and retries

When submission fails, the plugin stores failure information and can send an optional administrator email.

Authorized administrators can retry a failed fulfillment record after reviewing the target, service, balance, and failure details.

Because external order creation is not inherently idempotent, retries should be deliberate.

## 9. Customer-visible status

The plugin can show fulfillment status alongside WooCommerce order item information without exposing internal API credentials.
