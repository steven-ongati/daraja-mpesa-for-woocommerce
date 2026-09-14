<?php
/**
 * Safaricom callback parser.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\KenyanPhoneNumber;
use DarajaMpesa\Domain\KesAmount;
use InvalidArgumentException;

/**
 * Extracts bounded reconciliation evidence without retaining raw callbacks.
 */
final class CallbackPayloadParser {
	/**
	 * Parse a Daraja STK callback body.
	 *
	 * @param array<array-key, mixed> $payload Decoded callback body.
	 *
	 * @throws InvalidArgumentException When the callback shape or evidence is invalid.
	 */
	public function parse( array $payload ): CallbackPayload {
		$body     = $this->array_value( $payload, 'Body' );
		$callback = $this->array_value( $body, 'stkCallback' );
		$code     = $this->scalar_string( $callback, 'ResultCode' );

		if ( '0' !== $code ) {
			return new CallbackPayload(
				$this->scalar_string( $callback, 'MerchantRequestID' ),
				$this->scalar_string( $callback, 'CheckoutRequestID' ),
				$code,
				null,
				null,
				null
			);
		}

		$metadata = $this->array_value( $callback, 'CallbackMetadata' );
		$items    = $this->array_value( $metadata, 'Item' );
		$evidence = $this->metadata( $items );
		$amount   = $evidence['Amount'] ?? null;
		$receipt  = $evidence['MpesaReceiptNumber'] ?? null;
		$phone    = $evidence['PhoneNumber'] ?? null;

		return new CallbackPayload(
			$this->scalar_string( $callback, 'MerchantRequestID' ),
			$this->scalar_string( $callback, 'CheckoutRequestID' ),
			$code,
			null === $amount ? null : new KesAmount( $this->amount_value( $amount ) ),
			is_string( $receipt ) ? $receipt : null,
			is_string( $phone ) || is_int( $phone ) ? new KenyanPhoneNumber( (string) $phone ) : null
		);
	}

	/**
	 * Index callback metadata by bounded field name.
	 *
	 * @param array<array-key, mixed> $items Callback metadata items.
	 *
	 * @return array<string, mixed>
	 */
	private function metadata( array $items ): array {
		$metadata = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$name = $item['Name'] ?? null;
			if ( is_string( $name ) && in_array( $name, array( 'Amount', 'MpesaReceiptNumber', 'PhoneNumber' ), true ) ) {
				$metadata[ $name ] = $item['Value'] ?? null;
			}
		}

		return $metadata;
	}

	/**
	 * Return a required nested array.
	 *
	 * @param array<array-key, mixed> $payload Decoded provider structure.
	 * @param string                  $key     Required key.
	 *
	 * @return array<array-key, mixed>
	 *
	 * @throws InvalidArgumentException When the key is absent or not an array.
	 */
	private function array_value( array $payload, string $key ): array {
		$value = $payload[ $key ] ?? null;
		if ( ! is_array( $value ) ) {
			throw new InvalidArgumentException( 'The Daraja callback structure is invalid.' );
		}

		return $value;
	}

	/**
	 * Return a required scalar as a string.
	 *
	 * @param array<array-key, mixed> $payload Decoded provider structure.
	 * @param string                  $key     Required key.
	 *
	 * @throws InvalidArgumentException When the value is not scalar.
	 */
	private function scalar_string( array $payload, string $key ): string {
		$value = $payload[ $key ] ?? null;
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			throw new InvalidArgumentException( 'The Daraja callback value is invalid.' );
		}

		return (string) $value;
	}

	/**
	 * Normalize a callback amount for exact KES validation.
	 *
	 * @param mixed $amount Provider amount value.
	 *
	 * @return int|string
	 *
	 * @throws InvalidArgumentException When the amount is not a whole number.
	 */
	private function amount_value( mixed $amount ): int|string {
		if ( is_int( $amount ) || is_string( $amount ) ) {
			return $amount;
		}

		if ( is_float( $amount ) && floor( $amount ) === $amount ) {
			return (int) $amount;
		}

		throw new InvalidArgumentException( 'The Daraja callback amount is invalid.' );
	}
}
