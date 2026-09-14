<?php
/**
 * Daraja M-Pesa Express client.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

use DarajaMpesa\Infrastructure\Http\HttpResponse;
use DarajaMpesa\Infrastructure\Http\HttpTransport;
use DarajaMpesa\Infrastructure\Http\HttpTransportException;
use DarajaMpesa\Support\Clock;
use JsonException;

/**
 * Initiates STK Push requests and queries their provider status.
 */
final class DarajaClient {
	/**
	 * Configure M-Pesa Express access.
	 *
	 * @param Configuration       $configuration Merchant configuration.
	 * @param AccessTokenProvider $token_provider OAuth token provider.
	 * @param HttpTransport       $transport      HTTP transport.
	 * @param Clock               $clock          Provider timestamp source.
	 */
	public function __construct(
		private readonly Configuration $configuration,
		private readonly AccessTokenProvider $token_provider,
		private readonly HttpTransport $transport,
		private readonly Clock $clock
	) {
	}

	/**
	 * Initiate a customer STK Push.
	 *
	 * Acceptance is not payment evidence.
	 *
	 * @param StkPushRequest $request Validated payment request.
	 *
	 * @throws DarajaApiException When initiation fails or is rejected.
	 */
	public function push( StkPushRequest $request ): StkPushResult {
		$timestamp = $this->timestamp();
		$payload   = array(
			'BusinessShortCode' => $this->configuration->shortcode(),
			'Password'          => $this->password( $timestamp ),
			'Timestamp'         => $timestamp,
			'TransactionType'   => $this->configuration->transaction_type()->value,
			'Amount'            => $request->amount()->value(),
			'PartyA'            => $request->phone_number()->value(),
			'PartyB'            => $this->configuration->shortcode(),
			'PhoneNumber'       => $request->phone_number()->value(),
			'CallBackURL'       => $request->callback_url(),
			'AccountReference'  => $request->account_reference(),
			'TransactionDesc'   => $request->description(),
		);
		$response  = $this->post( '/mpesa/stkpush/v1/processrequest', $payload, 'stk_push' );

		if ( '0' !== $this->string_value( $response, 'ResponseCode' ) ) {
			throw new DarajaApiException(
				'Daraja rejected the STK Push request.',
				'stk_push_rejected',
				false
			);
		}

		$merchant_request_id = $this->string_value( $response, 'MerchantRequestID' );
		$checkout_request_id = $this->string_value( $response, 'CheckoutRequestID' );

		if ( '' === $merchant_request_id || '' === $checkout_request_id ) {
			throw new DarajaApiException(
				'Daraja accepted the request without correlation identifiers.',
				'stk_push_incomplete_response',
				true
			);
		}

		return new StkPushResult(
			$merchant_request_id,
			$checkout_request_id,
			$this->provider_text( $this->string_value( $response, 'CustomerMessage' ) )
		);
	}

	/**
	 * Query Daraja for an existing checkout request.
	 *
	 * A successful query is status evidence only and cannot settle an order without exact amount and receipt evidence.
	 *
	 * @param string $checkout_request_id Immutable provider checkout identifier.
	 *
	 * @throws DarajaApiException When the status query fails or is rejected.
	 */
	public function query( string $checkout_request_id ): StkQueryResult {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{8,128}$/', $checkout_request_id ) ) {
			throw new DarajaApiException(
				'The checkout request identifier is invalid.',
				'stk_query_invalid_identifier',
				false
			);
		}

		$timestamp = $this->timestamp();
		$response  = $this->post(
			'/mpesa/stkpushquery/v1/query',
			array(
				'BusinessShortCode' => $this->configuration->shortcode(),
				'Password'          => $this->password( $timestamp ),
				'Timestamp'         => $timestamp,
				'CheckoutRequestID' => $checkout_request_id,
			),
			'stk_query'
		);

		if ( '0' !== $this->string_value( $response, 'ResponseCode' ) ) {
			throw new DarajaApiException(
				'Daraja could not resolve the STK Push status.',
				'stk_query_unresolved',
				true
			);
		}

		$result_code          = $this->string_value( $response, 'ResultCode' );
		$merchant_request_id  = $this->string_value( $response, 'MerchantRequestID' );
		$returned_checkout_id = $this->string_value( $response, 'CheckoutRequestID' );

		if ( '' === $result_code || '' === $merchant_request_id || $checkout_request_id !== $returned_checkout_id ) {
			throw new DarajaApiException(
				'Daraja returned incomplete or mismatched STK Push status.',
				'stk_query_incomplete_response',
				true
			);
		}

		return new StkQueryResult(
			$result_code,
			$this->provider_text( $this->string_value( $response, 'ResultDesc' ) ),
			$merchant_request_id,
			$returned_checkout_id
		);
	}

	/**
	 * Post an authorized JSON request with one token refresh.
	 *
	 * @param string                    $path      Trusted Daraja API path.
	 * @param array<string, int|string> $payload   Provider request payload.
	 * @param string                    $operation Stable operation name.
	 *
	 * @return array<array-key, mixed>
	 *
	 * @throws DarajaApiException When the provider cannot return valid JSON.
	 */
	private function post( string $path, array $payload, string $operation ): array {
		$body = $this->encode( $payload );

		for ( $attempt = 0; $attempt < 2; ++$attempt ) {
			try {
				$response = $this->transport->request(
					'POST',
					$this->configuration->environment()->base_url() . $path,
					array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $this->token_provider->token(),
						'Content-Type'  => 'application/json',
					),
					$body
				);
			} catch ( HttpTransportException ) {
				throw new DarajaApiException(
					'Daraja is temporarily unavailable.',
					$operation . '_transport_error',
					true
				);
			}

			if ( 401 === $response->status_code() ) {
				if ( 0 === $attempt ) {
					$this->token_provider->invalidate();
					continue;
				}

				throw new DarajaApiException(
					'Daraja authorization failed.',
					$operation . '_unauthorized',
					false,
					401
				);
			}

			return $this->decode_response( $response, $operation );
		}

		throw new DarajaApiException(
			'Daraja did not return a payment response.',
			$operation . '_missing_response',
			true
		);
	}

	/**
	 * Decode a successful provider response.
	 *
	 * @param HttpResponse $response  Provider HTTP response.
	 * @param string       $operation Stable operation name.
	 *
	 * @return array<array-key, mixed>
	 *
	 * @throws DarajaApiException When the response is rejected or invalid.
	 */
	private function decode_response( HttpResponse $response, string $operation ): array {
		if ( ! $response->is_successful() ) {
			$status = $response->status_code();

			throw new DarajaApiException(
				'Daraja rejected the payment request.',
				$operation . '_http_error',
				429 === $status || $status >= 500,
				$status
			);
		}

		try {
			$payload = json_decode( $response->body(), true, 32, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new DarajaApiException(
				'Daraja returned an invalid payment response.',
				$operation . '_invalid_response',
				true,
				$response->status_code()
			);
		}

		if ( ! is_array( $payload ) ) {
			throw new DarajaApiException(
				'Daraja returned an invalid payment response.',
				$operation . '_invalid_response',
				true,
				$response->status_code()
			);
		}

		return $payload;
	}

	/**
	 * Encode a provider request.
	 *
	 * @param array<string, int|string> $payload Provider request payload.
	 *
	 * @throws DarajaApiException When request encoding fails.
	 */
	private function encode( array $payload ): string {
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Throwing JSON errors are mapped to a safe provider exception.
			return json_encode( $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
		} catch ( JsonException ) {
			throw new DarajaApiException(
				'The Daraja request could not be encoded.',
				'request_encoding_error',
				false
			);
		}
	}

	/**
	 * Generate the Daraja password for a request timestamp.
	 *
	 * @param string $timestamp Daraja request timestamp.
	 */
	private function password( string $timestamp ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Daraja requires this password encoding.
		return base64_encode(
			$this->configuration->shortcode()
			. $this->configuration->passkey()
			. $timestamp
		);
	}

	/**
	 * Return a Daraja-formatted timestamp.
	 */
	private function timestamp(): string {
		return $this->clock->now()->format( 'YmdHis' );
	}

	/**
	 * Read a scalar provider field as a string.
	 *
	 * @param array<array-key, mixed> $payload Provider response.
	 * @param string                  $key     Provider field.
	 */
	private function string_value( array $payload, string $key ): string {
		$value = $payload[ $key ] ?? null;

		return is_string( $value ) || is_int( $value ) ? (string) $value : '';
	}

	/**
	 * Bound provider text retained for customer and operator context.
	 *
	 * @param string $text Provider-supplied text.
	 */
	private function provider_text( string $text ): string {
		$clean = preg_replace( '/[\x00-\x1F\x7F]/', ' ', $text );

		return substr( null === $clean ? '' : $clean, 0, 500 );
	}
}
