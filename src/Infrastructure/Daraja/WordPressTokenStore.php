<?php
/**
 * WordPress Daraja access-token cache.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

/**
 * Stores OAuth tokens in expiring WordPress transients.
 */
final class WordPressTokenStore implements TokenStore {
	/**
	 * Return a cached token.
	 *
	 * @param string $key Credential-specific cache key.
	 */
	public function get( string $key ): ?string {
		$token = get_transient( $key );

		return is_string( $token ) && '' !== $token ? $token : null;
	}

	/**
	 * Cache a token for a bounded lifetime.
	 *
	 * @param string $key        Credential-specific cache key.
	 * @param string $token      OAuth access token.
	 * @param int    $expiration Lifetime in seconds.
	 */
	public function put( string $key, string $token, int $expiration ): void {
		set_transient( $key, $token, $expiration );
	}

	/**
	 * Remove a cached token.
	 *
	 * @param string $key Credential-specific cache key.
	 */
	public function delete( string $key ): void {
		delete_transient( $key );
	}
}
