# Security Policy

## Supported version

Security fixes should target the current stable plugin release unless otherwise stated.

## Reporting a vulnerability

Do not publish exploitable security details, API credentials, private site diagnostics, or customer information in a public GitHub issue.

Use NeoFollower's official support/contact channel to report a security issue privately:

https://neofollower.com

Include enough information to reproduce the issue without including real customer credentials.

## API credentials

A NeoFollower API key must be treated as a secret.

If a key is accidentally published, remove it from public access and replace/revoke it through the appropriate NeoFollower account or support process.

## Scope

Relevant reports can include issues involving:

- privilege checks;
- nonce verification;
- sanitization and escaping;
- SQL handling;
- WooCommerce order processing;
- duplicate external submissions;
- credential exposure;
- unintended external requests;
- sensitive diagnostic data.

For vulnerabilities in WordPress or WooCommerce themselves, use the security process of the affected upstream project.
