# Contributing

## Requirements

- Docker with Compose
- Git

Project commands run in containers so contributors do not need host PHP, Composer, Node.js, WordPress, or WooCommerce installations.

## Workflow

1. Make a focused change.
2. Add or update tests for changed behavior.
3. Run the scoped formatter, linter, static analysis, and tests.
4. Run the complete `make check` target before proposing a release.

Never commit credentials, callback payloads containing personal data, access tokens, or merchant certificates.

## Release validation

Run:

```bash
make release
```

The release target runs PHP quality checks, JavaScript lint, the Node dependency audit, creates the installable ZIP, validates its allowlist, checks packaged PHP syntax, and verifies the SHA-256 checksum.

Inspect `build/archive-contents.txt` before publishing. The archive must contain only the plugin bootstrap, uninstall handler, WordPress readme, license, browser assets, and runtime source. It must not contain tests, development dependencies, credentials, local environment files, or build tooling.

## Compatibility claims

Do not update “Tested up to” metadata based only on source inspection. Add the WordPress/WooCommerce combination to the compatibility test matrix and retain its passing evidence.
