<?php
/**
 * Daraja OAuth access-token provider.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

use DarajaMpesa\Infrastructure\Http\HttpTransport;
use DarajaMpesa\Infrastructure\Http\HttpTransportException;
use JsonException;

/**
 * Acquires and caches Daraja OAuth tokens without logging credentials.
 */
final class AccessTokenProvider {
	/**
	 * Configure OAuth access.
	 *
	 * @param Configuration $configuration Validated merchant configuration.
	 * @param HttpTransport $transport HTTP transport.
	 * @param TokenStore    $token_store Expiring token store.
	 */
	public function __construct(
		private readonly Configuration $configuration,
		private readonly HttpTransport $transport,
		private readonly TokenStore $token_store
	) {
	}

	/**
	 * Return a cached or newly acquired access token.
	 *
	 * @throws DarajaApiException When token acquisition fails.
	 */
	public function token(): string {
		$cache_key = $this->configuration->token_cache_key();
		$cached    = $this->token_store->get( $cache_key );

		if ( null !== $cached ) {
			return $cached;
		}

		$url = $this->configuration->environment()->base_url()
			. '/oauth/v1/generate?grant_type=client_credentials';

		try {
			$response = $this->transport->request(
				'GET',
				$url,
				array(
					'Accept'        => 'application/json',
					'Authorization' => $this->configuration->basic_authorization(),
				)
			);
		} catch ( HttpTransportException ) {
			throw new DarajaApiException(
				'Daraja authentication is temporarily unavailable.',
				'oauth_transport_error',
				true
			);
		}

		if ( ! $response->is_successful() ) {
			throw new DarajaApiException(
				'Daraja rejected the configured API credentials.',
				'oauth_rejected',
				$response->status_code() >= 500,
				$response->status_code()
			);
		}

		try {
			$payload = json_decode( $response->body(), true, 32, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new DarajaApiException(
				'Daraja returned an invalid authentication response.',
				'oauth_invalid_response',
				true,
				$response->status_code()
			);
		}

		if ( ! is_array( $payload ) ) {
			throw new DarajaApiException(
				'Daraja returned an invalid authentication response.',
				'oauth_invalid_response',
				true,
				$response->status_code()
			);
		}

		$token      = $payload['access_token'] ?? null;
		$expires_in = $payload['expires_in'] ?? null;

		if ( ! is_string( $token ) || '' === $token || ! is_numeric( $expires_in ) ) {
			throw new DarajaApiException(
				'Daraja returned incomplete authentication evidence.',
				'oauth_incomplete_response',
				true,
				$response->status_code()
			);
		}

		$expiration = (int) $expires_in;

		if ( $expiration < 1 ) {
			throw new DarajaApiException(
				'Daraja returned an invalid token lifetime.',
				'oauth_invalid_expiration',
				true,
				$response->status_code()
			);
		}

		$this->token_store->put( $cache_key, $token, max( 1, $expiration - 60 ) );

		return $token;
	}

	/**
	 * Remove the token after an authorization rejection.
	 */
	public function invalidate(): void {
		$this->token_store->delete( $this->configuration->token_cache_key() );
	}
}
