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

## Compatibility target

| Component | Target |
| --- | --- |
| WordPress | 7.1 |
| WooCommerce | 11.1 |
| PHP | 8.1–8.4 |
| Action Scheduler | 4.x |

Compatibility claims will be updated only after the corresponding automated checks pass.

## Status

The plugin is under active initial development. Do not use it for production payments until the first stable release is published.

## License

GPL-2.0-or-later. See `LICENSE`.

## Independence and trademarks

This is an independent open-source project by Steven Ongati Moriasi. It is not affiliated with, endorsed by, or sponsored by Safaricom PLC, Automattic Inc., or WooCommerce. M-Pesa, Safaricom, WordPress, and WooCommerce are trademarks of their respective owners and are used only to describe compatibility.
