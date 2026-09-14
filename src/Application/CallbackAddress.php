<?php
/**
 * Protected callback address.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use InvalidArgumentException;

/**
 * Generates HTTPS callback URLs with an attempt-bound authentication token.
 */
final class CallbackAddress {
	/**
	 * Build a protected provider callback URL.
	 *
	 * @param string $endpoint   Public REST endpoint.
	 * @param string $attempt_id Immutable payment attempt identifier.
	 * @param string $secret     WordPress installation secret.
	 *
	 * @throws InvalidArgumentException When the endpoint or callback identity is invalid.
	 */
	public function build( string $endpoint, string $attempt_id, string $secret ): string {
		$parts = wp_parse_url( $endpoint );

		if (
			! is_array( $parts )
			|| 'https' !== ( $parts['scheme'] ?? null )
			|| ! isset( $parts['host'] )
			|| '' === $parts['host']
		) {
			throw new InvalidArgumentException( 'The Daraja callback endpoint must use public HTTPS.' );
		}

		if ( 1 !== preg_match( '/^[a-f0-9-]{36}$/', $attempt_id ) || '' === $secret ) {
			throw new InvalidArgumentException( 'The Daraja callback identity is invalid.' );
		}

		return add_query_arg(
			array(
				'attempt' => $attempt_id,
				'token'   => $this->token( $attempt_id, $secret ),
			),
			$endpoint
		);
	}

	/**
	 * Verify an attempt-bound callback token.
	 *
	 * @param string $attempt_id Immutable payment attempt identifier.
	 * @param string $token      Presented callback token.
	 * @param string $secret     WordPress installation secret.
	 */
	public function verify( string $attempt_id, string $token, string $secret ): bool {
		if ( '' === $token || '' === $secret ) {
			return false;
		}

		return hash_equals( $this->token( $attempt_id, $secret ), $token );
	}

	/**
	 * Generate an attempt-bound callback token.
	 *
	 * @param string $attempt_id Immutable payment attempt identifier.
	 * @param string $secret     WordPress installation secret.
	 */
	private function token( string $attempt_id, string $secret ): string {
		return hash_hmac( 'sha256', 'daraja-mpesa-callback:' . $attempt_id, $secret );
	}
}
