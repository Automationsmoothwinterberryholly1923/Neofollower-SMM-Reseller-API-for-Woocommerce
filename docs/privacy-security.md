# Privacy and Security

## API key

The NeoFollower API key is a credential.

Do not expose it in:

- public GitHub issues
- screenshots
- frontend JavaScript
- browser-visible HTML
- public logs
- support posts
- code commits

The plugin uses the key server-side when communicating with the NeoFollower API.

## External requests

The plugin's core fulfillment functionality relies on:

```text
https://panel.neofollower.com/api/v1
```

Depending on the action and selected service, external requests can contain:

- NeoFollower API key
- service ID
- public target URL
- public username or identifier
- quantity
- custom comments
- drip-feed runs and interval
- NeoFollower order ID

## Optional support request

The plugin includes an administrator support form.

It sends an email to NeoFollower only after an authorized administrator explicitly checks the consent option and submits the form. The support message can include diagnostic details described in the WordPress.org plugin disclosure.

## Stored fulfillment data

The plugin can store fulfillment information in WooCommerce order items and custom plugin tables.

Depending on the order, this can include:

- target links or usernames
- quantities
- comments
- service IDs
- external order IDs
- fulfillment states
- API response information
- diagnostic logs

## Data retention

Diagnostic log retention is configurable.

Administrators can also enable deletion of plugin data during uninstall.

## WordPress privacy tools

The plugin adds suggested disclosure text to:

```text
Settings → Privacy
```

Store owners should adapt their site's privacy notice to their actual use of the plugin and external services.

## Passwords

The plugin is not designed to collect social media account passwords.

Supported workflows should use public target information required by the chosen service.

## Security reports

Do not publish a vulnerability together with working credentials, private site information, or customer data.

Follow [SECURITY.md](../SECURITY.md) for responsible reporting.
