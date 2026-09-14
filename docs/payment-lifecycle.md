# Payment lifecycle

## Settlement boundary

An STK Push acceptance means Safaricom accepted a request for processing. It does not mean the customer paid. An STK Query success confirms provider processing but does not contain the exact callback amount and receipt evidence required by this plugin.

Automatic settlement requires all of the following:

1. the callback authorization matches the immutable attempt;
2. merchant and checkout request identifiers match the stored attempt;
3. the provider result is successful;
4. the callback amount equals both the attempt amount and the current WooCommerce order total;
5. the receipt is nonempty and is not assigned to another payment attempt;
6. the attempt is still the order's active payment request.

The durable settlement is stored before WooCommerce payment completion is invoked. Replayed callbacks with the same evidence are idempotent. Conflicting callbacks do not overwrite terminal outcomes.

## States

| State | Meaning | Customer retry |
| --- | --- | --- |
| `created` | Intent is durably stored | No |
| `initiating` | Daraja initiation is in progress | No |
| `pending` | STK Push is accepted and awaiting evidence | No |
| `succeeded_unverified` | STK Query succeeded without exact amount and receipt | No |
| `settled` | Exact evidence passed and WooCommerce completion is allowed | No |
| `customer_cancelled` | Provider reports customer cancellation | Yes, as a new attempt |
| `timed_out` | Polling was exhausted without exact evidence | No; manual review is required |
| `failed` | Provider reports a definitive non-payment failure | Yes, as a new attempt |
| `amount_mismatch` | Paid evidence does not match the order amount | No |
| `duplicate_receipt` | Receipt is already assigned elsewhere | No |
| `manual_review` | Human investigation is required | No |

Every retry creates a distinct attempt. A late success callback for an attempt superseded by a newer request moves the older attempt to manual review and cannot automatically complete the order.

## Reconciliation

Action Scheduler performs server-side STK Query work. Browser status refresh is deliberately read-only and is not part of the settlement path.

The query schedule is bounded. A query result may:

- retain `pending` while Safaricom continues processing;
- move to `customer_cancelled` for a definitive cancellation;
- move to `failed` for a definitive non-payment failure;
- move to `succeeded_unverified` when provider processing succeeds without exact receipt evidence;
- move to `timed_out` after the configured attempts are exhausted.

## Manual verification

Manual verification is available only for `succeeded_unverified`, `manual_review`, and `timed_out` attempts. The administrator must supply independent external evidence. The plugin then:

1. verifies administrator capability and request nonce;
2. reloads the immutable attempt;
3. rejects a superseded attempt;
4. performs a fresh STK Query;
5. validates immutable provider correlation;
6. compares the supplied amount with the attempt and current order amount;
7. checks receipt uniqueness;
8. stores settlement before calling WooCommerce completion;
9. appends an audit record containing the operator, outcome, evidence source, and reason.
