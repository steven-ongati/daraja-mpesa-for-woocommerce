<?php
/**
 * Daraja access-token provider tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Infrastructure\Daraja;

use DarajaMpesa\Infrastructure\Daraja\AccessTokenProvider;
use DarajaMpesa\Infrastructure\Daraja\Configuration;
use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\Daraja\Environment;
use DarajaMpesa\Infrastructure\Daraja\TransactionType;
use DarajaMpesa\Infrastructure\Http\HttpResponse;
use DarajaMpesa\Infrastructure\Http\HttpTransportException;
use DarajaMpesa\Tests\Doubles\InMemoryTokenStore;
use DarajaMpesa\Tests\Doubles\RecordingHttpTransport;
use PHPUnit\Framework\TestCase;

/**
 * Protects token caching and safe OAuth failure handling.
 */
final class AccessTokenProviderTest extends TestCase {
	/**
	 * A valid provider token is cached before use.
	 */
	public function test_acquires_and_caches_access_token(): void {
		$configuration = $this->configuration();
		$transport     = new RecordingHttpTransport(
			array( new HttpResponse( 200, '{"access_token":"token-value","expires_in":"3600"}' ) )
		);
		$store         = new InMemoryTokenStore();
		$provider      = new AccessTokenProvider( $configuration, $transport, $store );

		self::assertSame( 'token-value', $provider->token() );
		self::assertSame( 'token-value', $provider->token() );
		self::assertSame( 3540, $store->expiration( $configuration->token_cache_key() ) );

		$requests = $transport->requests();

		self::assertCount( 1, $requests );
		self::assertSame( 'GET', $requests[0]['method'] );
		self::assertSame(
			'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
			$requests[0]['url']
		);
		self::assertSame( 'Basic a2V5OnNlY3JldA==', $requests[0]['headers']['Authorization'] );
	}

	/**
	 * Credential rejection is classified without exposing the provider body.
	 */
	public function test_classifies_credential_rejection_safely(): void {
		$transport = new RecordingHttpTransport(
			array( new HttpResponse( 401, '{"errorMessage":"raw provider detail"}' ) )
		);
		$provider  = new AccessTokenProvider(
			$this->configuration(),
			$transport,
			new InMemoryTokenStore()
		);

		try {
			$provider->token();
			self::fail( 'Expected Daraja credential rejection.' );
		} catch ( DarajaApiException $exception ) {
			self::assertSame( 'oauth_rejected', $exception->error_code() );
			self::assertSame( 401, $exception->http_status() );
			self::assertFalse( $exception->is_retryable() );
			self::assertStringNotContainsString( 'raw provider detail', $exception->getMessage() );
		}
	}

	/**
	 * Network failures are safe and retryable.
	 */
	public function test_classifies_transport_failure_as_retryable(): void {
		$transport = new RecordingHttpTransport(
			array( new HttpTransportException( 'low-level network detail' ) )
		);
		$provider  = new AccessTokenProvider(
			$this->configuration(),
			$transport,
			new InMemoryTokenStore()
		);

		try {
			$provider->token();
			self::fail( 'Expected Daraja transport failure.' );
		} catch ( DarajaApiException $exception ) {
			self::assertSame( 'oauth_transport_error', $exception->error_code() );
			self::assertTrue( $exception->is_retryable() );
			self::assertStringNotContainsString( 'low-level network detail', $exception->getMessage() );
		}
	}

	/**
	 * Incomplete OAuth responses are never cached.
	 */
	public function test_rejects_incomplete_oauth_response(): void {
		$store     = new InMemoryTokenStore();
		$transport = new RecordingHttpTransport(
			array( new HttpResponse( 200, '{"expires_in":"3600"}' ) )
		);
		$provider  = new AccessTokenProvider( $this->configuration(), $transport, $store );

		try {
			$provider->token();
			self::fail( 'Expected incomplete Daraja authentication evidence.' );
		} catch ( DarajaApiException $exception ) {
			self::assertSame( 'oauth_incomplete_response', $exception->error_code() );
			self::assertTrue( $exception->is_retryable() );
			self::assertNull( $store->get( $this->configuration()->token_cache_key() ) );
		}
	}

	/**
	 * Return valid sandbox configuration.
	 */
	private function configuration(): Configuration {
		return new Configuration(
			Environment::SANDBOX,
			'key',
			'secret',
			'174379',
			'passkey',
			TransactionType::PAYBILL
		);
	}
}
