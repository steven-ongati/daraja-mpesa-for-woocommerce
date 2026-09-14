<?php
/**
 * Daraja M-Pesa Express client tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Infrastructure\Daraja;

use DarajaMpesa\Domain\KenyanPhoneNumber;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Infrastructure\Daraja\AccessTokenProvider;
use DarajaMpesa\Infrastructure\Daraja\Configuration;
use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\Daraja\DarajaClient;
use DarajaMpesa\Infrastructure\Daraja\Environment;
use DarajaMpesa\Infrastructure\Daraja\StkPushRequest;
use DarajaMpesa\Infrastructure\Daraja\TransactionType;
use DarajaMpesa\Infrastructure\Http\HttpResponse;
use DarajaMpesa\Tests\Doubles\FixedClock;
use DarajaMpesa\Tests\Doubles\InMemoryTokenStore;
use DarajaMpesa\Tests\Doubles\RecordingHttpTransport;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PHPUnit\Framework\TestCase;

/**
 * Protects STK Push payloads, identifiers, queries, and token refresh.
 */
final class DarajaClientTest extends TestCase {
	/**
	 * STK Push acceptance returns correlation identifiers, not payment settlement.
	 *
	 * @throws JsonException When the recorded request is not valid JSON.
	 */
	public function test_initiates_stk_push_with_exact_payment_context(): void {
		$transport = new RecordingHttpTransport(
			array(
				$this->oauth_response( 'token-one' ),
				new HttpResponse(
					200,
					'{"MerchantRequestID":"merchant-123","CheckoutRequestID":"ws_CO_12345678","ResponseCode":"0","CustomerMessage":"Request accepted"}'
				),
			)
		);
		$client    = $this->client( $transport );
		$result    = $client->push( $this->push_request() );
		$requests  = $transport->requests();
		$payload   = json_decode( (string) $requests[1]['body'], true, 32, JSON_THROW_ON_ERROR );

		self::assertSame( 'merchant-123', $result->merchant_request_id() );
		self::assertSame( 'ws_CO_12345678', $result->checkout_request_id() );
		self::assertSame( 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest', $requests[1]['url'] );
		self::assertSame( 'Bearer token-one', $requests[1]['headers']['Authorization'] );
		self::assertSame( 2500, $payload['Amount'] );
		self::assertSame( '254712345678', $payload['PartyA'] );
		self::assertSame( '254712345678', $payload['PhoneNumber'] );
		self::assertSame( '20260102030405', $payload['Timestamp'] );
		self::assertSame(
			'MTc0Mzc5cGFzc2tleTIwMjYwMTAyMDMwNDA1',
			$payload['Password']
		);
	}

	/**
	 * One authorization rejection invalidates the token and retries once.
	 */
	public function test_refreshes_rejected_access_token_once(): void {
		$transport = new RecordingHttpTransport(
			array(
				$this->oauth_response( 'expired-token' ),
				new HttpResponse( 401, '{"errorCode":"invalid_token"}' ),
				$this->oauth_response( 'fresh-token' ),
				new HttpResponse(
					200,
					'{"MerchantRequestID":"merchant-123","CheckoutRequestID":"ws_CO_12345678","ResponseCode":"0"}'
				),
			)
		);
		$client    = $this->client( $transport );

		$client->push( $this->push_request() );

		$requests = $transport->requests();

		self::assertCount( 4, $requests );
		self::assertSame( 'Bearer expired-token', $requests[1]['headers']['Authorization'] );
		self::assertSame( 'Bearer fresh-token', $requests[3]['headers']['Authorization'] );
	}

	/**
	 * Repeated authorization rejection stops after one refresh.
	 */
	public function test_stops_after_one_rejected_token_refresh(): void {
		$transport = new RecordingHttpTransport(
			array(
				$this->oauth_response( 'expired-token' ),
				new HttpResponse( 401, '{"errorCode":"invalid_token"}' ),
				$this->oauth_response( 'rejected-token' ),
				new HttpResponse( 401, '{"errorCode":"invalid_token"}' ),
			)
		);
		$client    = $this->client( $transport );

		try {
			$client->push( $this->push_request() );
			self::fail( 'Expected repeated Daraja authorization rejection.' );
		} catch ( DarajaApiException $exception ) {
			self::assertSame( 'stk_push_unauthorized', $exception->error_code() );
			self::assertFalse( $exception->is_retryable() );
			self::assertCount( 4, $transport->requests() );
		}
	}

	/**
	 * A successful query remains status-only evidence.
	 */
	public function test_queries_stk_status_without_inventing_receipt_evidence(): void {
		$transport = new RecordingHttpTransport(
			array(
				$this->oauth_response( 'token-one' ),
				new HttpResponse(
					200,
					'{"ResponseCode":"0","MerchantRequestID":"merchant-123","CheckoutRequestID":"ws_CO_12345678","ResultCode":"0","ResultDesc":"Processed successfully"}'
				),
			)
		);
		$client    = $this->client( $transport );
		$result    = $client->query( 'ws_CO_12345678' );

		self::assertTrue( $result->is_successful() );
		self::assertSame( '0', $result->result_code() );
		self::assertSame( 'ws_CO_12345678', $result->checkout_request_id() );
		self::assertSame( 'Processed successfully', $result->result_description() );
	}

	/**
	 * Cancellation remains an explicit provider result.
	 */
	public function test_returns_non_successful_query_result(): void {
		$transport = new RecordingHttpTransport(
			array(
				$this->oauth_response( 'token-one' ),
				new HttpResponse(
					200,
					'{"ResponseCode":"0","MerchantRequestID":"merchant-123","CheckoutRequestID":"ws_CO_12345678","ResultCode":"1032","ResultDesc":"Request cancelled by user"}'
				),
			)
		);
		$result    = $this->client( $transport )->query( 'ws_CO_12345678' );

		self::assertFalse( $result->is_successful() );
		self::assertSame( '1032', $result->result_code() );
	}

	/**
	 * A mismatched query response cannot affect another payment attempt.
	 */
	public function test_rejects_mismatched_query_identifier(): void {
		$transport = new RecordingHttpTransport(
			array(
				$this->oauth_response( 'token-one' ),
				new HttpResponse(
					200,
					'{"ResponseCode":"0","MerchantRequestID":"merchant-123","CheckoutRequestID":"ws_CO_DIFFERENT","ResultCode":"0"}'
				),
			)
		);
		$client    = $this->client( $transport );

		try {
			$client->query( 'ws_CO_12345678' );
			self::fail( 'Expected mismatched query evidence to be rejected.' );
		} catch ( DarajaApiException $exception ) {
			self::assertSame( 'stk_query_incomplete_response', $exception->error_code() );
			self::assertTrue( $exception->is_retryable() );
		}
	}

	/**
	 * Build the client with deterministic sandbox dependencies.
	 *
	 * @param RecordingHttpTransport $transport Recording provider transport.
	 */
	private function client( RecordingHttpTransport $transport ): DarajaClient {
		$configuration = $this->configuration();
		$tokens        = new InMemoryTokenStore();

		return new DarajaClient(
			$configuration,
			new AccessTokenProvider( $configuration, $transport, $tokens ),
			$transport,
			new FixedClock(
				new DateTimeImmutable( '2026-01-02 03:04:05', new DateTimeZone( 'Africa/Nairobi' ) )
			)
		);
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

	/**
	 * Return an accepted OAuth response.
	 *
	 * @param string $token Access token.
	 */
	private function oauth_response( string $token ): HttpResponse {
		return new HttpResponse(
			200,
			'{"access_token":"' . $token . '","expires_in":"3600"}'
		);
	}

	/**
	 * Return a valid payment request.
	 */
	private function push_request(): StkPushRequest {
		return new StkPushRequest(
			new KenyanPhoneNumber( '0712345678' ),
			new KesAmount( 2500 ),
			'https://shop.example/wp-json/daraja/callback/token',
			'Order-123',
			'Order payment'
		);
	}
}
