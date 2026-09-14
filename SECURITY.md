# Security policy

## Reporting

Do not open a public issue for a suspected vulnerability. Send a private report through GitHub Security Advisories with:

- the affected version;
- reproduction steps;
- expected and observed behavior;
- impact;
- suggested remediation, if known.

Never include real Daraja credentials, access tokens, customer phone numbers, M-Pesa receipts, or production callback payloads.

## Supported versions

Security support begins with the first stable release. Until then, only the latest commit on `main` is maintained.

## Payment safety boundary

An accepted STK Push request is not proof of payment. A timeout is not proof of payment. The plugin may settle an order automatically only after correlated Daraja evidence includes a successful result, the exact expected amount, and a receipt that has not settled another attempt.
