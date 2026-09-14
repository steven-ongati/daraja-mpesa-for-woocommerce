# Operations guide

## Sandbox readiness

Before enabling live payments:

1. configure Daraja sandbox credentials in WooCommerce payment settings;
2. confirm WordPress and REST API URLs are publicly reachable over HTTPS;
3. confirm the WooCommerce store currency is `KES`;
4. confirm Action Scheduler is processing scheduled actions;
5. complete successful, cancelled, failed, callback-loss, duplicate-callback, amount-mismatch, and timeout scenarios;
6. confirm customer order status never exposes provider identifiers or raw payloads;
7. confirm administrators can complete the documented manual-verification workflow;
8. confirm backups include the plugin payment-attempt and manual-audit tables.

## Monitoring

Use **WooCommerce → Status → Logs** and select the `daraja-mpesa` source. Logs contain bounded event names and attempt identifiers for correlation. They must not contain credentials, access tokens, raw callbacks, phone numbers, or receipts.

Investigate these events:

| Event | Action |
| --- | --- |
| `payment.initiation_failed` | Check Daraja availability and merchant configuration |
| `payment.callback_conflict` | Compare stored attempt correlation with provider records |
| `payment.callback_superseded_attempt` | Check for a late payment and possible refund |
| `payment.poll_failed` | Check network and Daraja availability; Action Scheduler will retry within its bound |
| `payment.manual_verification_rejected` | Review the bounded outcome and external evidence |

## Timeout handling

A timeout does not prove that no money moved. Do not ask the customer to pay again until the transaction has been checked in an independent Safaricom or merchant record.

For exact verified evidence, use the order's M-Pesa manual-verification form. If the transaction did not complete, leave the attempt unresolved until the external record is conclusive. If a late payment exists alongside a newer payment, follow the merchant's refund process; the plugin intentionally does not issue automated refunds.

## Incident containment

If credentials may be exposed:

1. disable the payment gateway;
2. rotate the Daraja consumer secret and M-Pesa Express passkey;
3. inspect WooCommerce logs and access logs without copying sensitive payloads into tickets;
4. reconcile unresolved attempts against authoritative merchant records;
5. report plugin vulnerabilities through a private GitHub Security Advisory.

## Backup and recovery

Back up the WordPress database before plugin upgrades. The payment-attempt table contains reconciliation state and receipt uniqueness constraints. The manual-verification audit table provides operator evidence history.

After restoration:

1. confirm the plugin schema version;
2. confirm unresolved attempts and audit rows exist;
3. confirm WooCommerce orders retain their active attempt metadata;
4. run Action Scheduler and observe one pending attempt;
5. replay a previously processed callback in sandbox and confirm idempotent behavior.

## Removal

Deactivation keeps configuration and reconciliation records so the gateway can be reactivated safely. Uninstallation removes plugin configuration, cached tokens, scheduled actions, and plugin-owned tables. Export records required by accounting or incident-retention policies before uninstalling.
