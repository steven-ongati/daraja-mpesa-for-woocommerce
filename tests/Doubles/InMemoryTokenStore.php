<?php
/**
 * In-memory Daraja token store.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Infrastructure\Daraja\TokenStore;

/**
 * Records token caching behavior without WordPress state.
 */
final class InMemoryTokenStore implements TokenStore {
	/**
	 * Cached tokens.
	 *
	 * @var array<string, string>
	 */
	private array $tokens = array();

	/**
	 * Recorded lifetimes.
	 *
	 * @var array<string, int>
	 */
	private array $expirations = array();

	/**
	 * Return a cached token.
	 *
	 * @param string $key Credential-specific cache key.
	 */
	public function get( string $key ): ?string {
		return $this->tokens[ $key ] ?? null;
	}

	/**
	 * Cache a token for a bounded lifetime.
	 *
	 * @param string $key        Credential-specific cache key.
	 * @param string $token      OAuth access token.
	 * @param int    $expiration Lifetime in seconds.
	 */
	public function put( string $key, string $token, int $expiration ): void {
		$this->tokens[ $key ]      = $token;
		$this->expirations[ $key ] = $expiration;
	}

	/**
	 * Remove a cached token.
	 *
	 * @param string $key Credential-specific cache key.
	 */
	public function delete( string $key ): void {
		unset( $this->tokens[ $key ], $this->expirations[ $key ] );
	}

	/**
	 * Return the recorded token lifetime.
	 *
	 * @param string $key Credential-specific cache key.
	 */
	public function expiration( string $key ): ?int {
		return $this->expirations[ $key ] ?? null;
	}
}
