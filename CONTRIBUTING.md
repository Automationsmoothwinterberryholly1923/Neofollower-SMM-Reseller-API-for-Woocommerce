# Contributing

Thanks for helping improve **Neofollower – SMM Reseller API for WooCommerce**.

## Before opening an issue

For NeoFollower account, balance, service availability, pricing, or individual external order questions, use NeoFollower support rather than GitHub.

GitHub issues are appropriate for:

- reproducible plugin bugs;
- compatibility problems;
- documentation corrections;
- code-quality improvements;
- development proposals.

Never include a real NeoFollower API key or customer data.

## Bug reports

Please include:

- plugin version;
- WordPress version;
- WooCommerce version;
- PHP version;
- reproducible steps;
- expected behavior;
- actual behavior;
- relevant sanitized error text.

Do not paste unsanitized production logs.

## Pull requests

Keep changes focused.

Code should:

- support PHP 7.4+;
- support WordPress 6.2+;
- follow WordPress security practices;
- sanitize input;
- escape output;
- use nonces for state-changing administrator/front-end requests where appropriate;
- check capabilities for privileged actions;
- use WooCommerce APIs for order data;
- avoid leaking the NeoFollower API key;
- preserve duplicate-order protections.

Run PHP syntax checks before submitting:

```bash
find . -name '*.php' -type f -print0 | xargs -0 -n1 php -l
```

## WordPress.org releases

The WordPress.org plugin directory is the canonical stable distribution channel. A GitHub change is not automatically a WordPress.org release.
