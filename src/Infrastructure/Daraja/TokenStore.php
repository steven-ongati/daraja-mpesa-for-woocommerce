<?php
/**
 * Daraja access-token cache.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

/**
 * Stores short-lived OAuth access tokens.
 */
interface TokenStore {
	/**
	 * Return a cached token.
	 *
	 * @param string $key Credential-specific cache key.
	 */
	public function get( string $key ): ?string;

	/**
	 * Cache a token for a bounded lifetime.
	 *
	 * @param string $key        Credential-specific cache key.
	 * @param string $token      OAuth access token.
	 * @param int    $expiration Lifetime in seconds.
	 */
	public function put( string $key, string $token, int $expiration ): void;

	/**
	 * Remove a cached token.
	 *
	 * @param string $key Credential-specific cache key.
	 */
	public function delete( string $key ): void;
}
