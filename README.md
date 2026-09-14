# Daraja M-Pesa Gateway for WooCommerce

An open-source WooCommerce payment gateway that initiates Safaricom M-Pesa Express STK Push requests and settles orders only after correlated payment evidence passes exact-amount checks.

## Why this plugin

Existing integrations demonstrate useful patterns such as direct Daraja access, callbacks, sandbox support, HPOS declarations, Checkout Blocks, and local status polling. This project focuses on recurring reliability and security gaps:

- stale WordPress and WooCommerce compatibility declarations;
- frontend-only polling that stops when the customer closes the page;
- direct order post-meta access that is unsafe for HPOS;
- missing M-Pesa Express status queries after callback loss;
- marking orders paid before validating the received amount;
- mutable or duplicate callback processing;
- sensitive API payloads or tokens in logs;
- “manual verification” that only rereads local state rather than obtaining external evidence.

## Payment guarantees

- Direct Safaricom Daraja integration; no payment intermediary.
- Kenyan Safaricom phone normalization.
- KES-only, whole-shilling payment validation.
- Durable payment attempts with immutable Daraja identifiers.
- Exact amount and unique receipt checks before automatic settlement.
- Idempotent callback processing and bounded server-side reconciliation.
- Audited manual verification for unresolved callback-loss cases.
- Classic checkout, Checkout Blocks, and HPOS-compatible order access.

## Requirements

- WordPress 6.8 or newer;
- WooCommerce 10.3 or newer;
- PHP 8.1 through 8.4;
- a store currency of Kenyan shillings (`KES`);
- a publicly reachable HTTPS WordPress site so Safaricom can deliver callbacks;
- Safaricom Daraja credentials for M-Pesa Express:
  - consumer key;
  - consumer secret;
  - business shortcode;
  - M-Pesa Express passkey;
  - PayBill or Till transaction type.

The plugin creates its callback address and callback authorization automatically. It does not require the merchant to copy payment identifiers between systems.

## Installation

1. Download the release ZIP from GitHub Releases.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP, install it, and activate it.
4. Open **WooCommerce → Settings → Payments → Daraja M-Pesa**.
5. Choose **Sandbox**, enter the sandbox credentials, and save.
6. Confirm that the store currency is `KES` and the site URL uses HTTPS.
7. Enable the gateway and complete a sandbox payment before configuring live credentials.

For local development:

```bash
make setup
make check
docker compose run --rm node npm run audit
```

## Payment lifecycle

1. Checkout validates and normalizes the customer's Kenyan phone number.
2. The plugin stores a new immutable payment attempt before calling Daraja.
3. Daraja accepts the STK Push and returns merchant and checkout request identifiers.
4. The WooCommerce order remains unpaid while server-side Action Scheduler jobs query STK status.
5. A protected callback supplies the exact amount and receipt evidence required for automatic settlement.
6. The plugin rereads the current WooCommerce order total, enforces receipt uniqueness, stores the settlement, and then calls WooCommerce payment completion.

STK Query success without an exact callback amount and receipt becomes `succeeded_unverified`; it never marks the order paid. See [the payment lifecycle](docs/payment-lifecycle.md) for state and retry details.

## Customer payment status

The order details page shows a bounded local status such as pending, confirming, confirmed, cancelled, failed, or manual review. Browser refreshes are read-only, private, non-cacheable, and limited to 60 automatic requests at five-second intervals.

The browser never calls Safaricom and cannot settle an order. Provider reconciliation continues on the server if the customer closes the page.

## Manual verification

For unresolved payments, a WooCommerce administrator can review the order and submit:

- the exact amount observed in an independent Safaricom record;
- the M-Pesa receipt;
- the evidence source;
- an operator reason.

The plugin performs a fresh correlated Daraja STK Query before accepting that evidence. The action is capability-protected, nonce-protected, idempotent, and recorded in an append-only audit table. It cannot settle a payment attempt superseded by a newer request.

## Data handling

- Consumer credentials and passkeys use WooCommerce password settings.
- OAuth access tokens are cached transiently and are never written to plugin logs.
- Customer phone numbers are normalized for the Daraja request, but only a one-way keyed hash is stored with the payment attempt.
- Raw callback bodies are parsed but not retained.
- Logs use bounded event names and redact credential, token, phone, receipt, and callback fields.
- M-Pesa receipts are stored only where required for payment uniqueness, WooCommerce transaction records, and administrator audit integrity.

See [the security policy](SECURITY.md) and [operations guide](docs/operations.md).

## Compatibility target

| Component | Target |
| --- | --- |
| WordPress | 7.1 |
| WooCommerce | 11.1 |
| PHP | 8.1–8.4 |
| Action Scheduler | 4.x |

Source compatibility is checked against WordPress 7.1 and WooCommerce 11.1 stubs. Runtime payment verification still requires merchant-owned Daraja sandbox credentials and a public HTTPS callback.

## Status

The plugin is in pre-release development. Use sandbox credentials until a stable release is published and your own callback, reconciliation, refund, and operational procedures have been exercised.

## Development and release

- `make check` runs PHP coding standards, PHPStan, PHPUnit, and JavaScript lint.
- `docker compose run --rm node npm run audit` checks Node development dependencies.
- `make package` creates the installable ZIP in `build/`.
- `make validate-package` checks the ZIP allowlist and runs PHP syntax checks against its contents.

See [CONTRIBUTING.md](CONTRIBUTING.md) for compatibility and release rules.

## License

GPL-2.0-or-later. See `LICENSE`.

## Independence and trademarks

This is an independent open-source project by Steven Ongati Moriasi. It is not affiliated with, endorsed by, or sponsored by Safaricom PLC, Automattic Inc., or WooCommerce. M-Pesa, Safaricom, WordPress, and WooCommerce are trademarks of their respective owners and are used only to describe compatibility.
