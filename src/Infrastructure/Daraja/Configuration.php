<?php
/**
 * Validated Daraja configuration.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

use InvalidArgumentException;

/**
 * Holds merchant credentials and immutable API routing choices.
 */
final class Configuration {
	/**
	 * Configure a Daraja merchant account.
	 *
	 * @param Environment     $environment     Daraja environment.
	 * @param string          $consumer_key    Daraja application consumer key.
	 * @param string          $consumer_secret Daraja application consumer secret.
	 * @param string          $shortcode       Paybill or till shortcode.
	 * @param string          $passkey         M-Pesa Express passkey.
	 * @param TransactionType $transaction_type Merchant account type.
	 *
	 * @throws InvalidArgumentException When a required credential is invalid.
	 */
	public function __construct(
		private readonly Environment $environment,
		private readonly string $consumer_key,
		private readonly string $consumer_secret,
		private readonly string $shortcode,
		private readonly string $passkey,
		private readonly TransactionType $transaction_type
	) {
		if ( '' === trim( $this->consumer_key ) ) {
			throw new InvalidArgumentException( 'The Daraja consumer key is required.' );
		}

		if ( '' === trim( $this->consumer_secret ) ) {
			throw new InvalidArgumentException( 'The Daraja consumer secret is required.' );
		}

		if ( 1 !== preg_match( '/^\d{5,7}$/', $this->shortcode ) ) {
			throw new InvalidArgumentException( 'The Daraja shortcode must contain 5 to 7 digits.' );
		}

		if ( '' === trim( $this->passkey ) ) {
			throw new InvalidArgumentException( 'The M-Pesa Express passkey is required.' );
		}
	}

	/**
	 * Return the selected Daraja environment.
	 */
	public function environment(): Environment {
		return $this->environment;
	}

	/**
	 * Return the merchant shortcode.
	 */
	public function shortcode(): string {
		return $this->shortcode;
	}

	/**
	 * Return the M-Pesa Express passkey.
	 */
	public function passkey(): string {
		return $this->passkey;
	}

	/**
	 * Return the merchant account type.
	 */
	public function transaction_type(): TransactionType {
		return $this->transaction_type;
	}

	/**
	 * Build the OAuth Basic authentication value.
	 */
	public function basic_authorization(): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authentication requires Base64.
		return 'Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret );
	}

	/**
	 * Return a credential-specific cache key without exposing a credential.
	 */
	public function token_cache_key(): string {
		$identity = $this->environment->value . ':' . $this->consumer_key;

		return 'daraja_mpesa_token_' . substr( hash( 'sha256', $identity ), 0, 40 );
	}
}
