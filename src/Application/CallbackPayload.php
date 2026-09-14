<?php
/**
 * Validated Daraja callback value object.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\KenyanPhoneNumber;
use DarajaMpesa\Domain\KesAmount;
use InvalidArgumentException;

/**
 * Carries only bounded callback fields needed for reconciliation.
 */
final class CallbackPayload {
	/**
	 * Store callback outcome and optional payment evidence.
	 *
	 * @param string                 $merchant_request_id Provider merchant identifier.
	 * @param string                 $checkout_request_id Provider checkout identifier.
	 * @param string                 $result_code         Provider result code.
	 * @param KesAmount|null         $amount              Exact callback amount.
	 * @param string|null            $receipt_number      M-Pesa receipt number.
	 * @param KenyanPhoneNumber|null $phone_number        Callback customer phone.
	 *
	 * @throws InvalidArgumentException When callback values are invalid.
	 */
	public function __construct(
		private readonly string $merchant_request_id,
		private readonly string $checkout_request_id,
		private readonly string $result_code,
		private readonly ?KesAmount $amount,
		private readonly ?string $receipt_number,
		private readonly ?KenyanPhoneNumber $phone_number
	) {
		if (
			1 !== preg_match( '/^[A-Za-z0-9_.-]{8,128}$/', $this->merchant_request_id )
			|| 1 !== preg_match( '/^[A-Za-z0-9_.-]{8,128}$/', $this->checkout_request_id )
			|| 1 !== preg_match( '/^[A-Za-z0-9_.-]{1,64}$/', $this->result_code )
		) {
			throw new InvalidArgumentException( 'The Daraja callback identifiers are invalid.' );
		}

		if (
			null !== $this->receipt_number
			&& 1 !== preg_match( '/^[A-Za-z0-9]{6,64}$/', $this->receipt_number )
		) {
			throw new InvalidArgumentException( 'The Daraja callback receipt is invalid.' );
		}
	}

	/**
	 * Return the provider merchant identifier.
	 */
	public function merchant_request_id(): string {
		return $this->merchant_request_id;
	}

	/**
	 * Return the provider checkout identifier.
	 */
	public function checkout_request_id(): string {
		return $this->checkout_request_id;
	}

	/**
	 * Return the provider result code.
	 */
	public function result_code(): string {
		return $this->result_code;
	}

	/**
	 * Whether the provider reports payment success.
	 */
	public function is_successful(): bool {
		return '0' === $this->result_code;
	}

	/**
	 * Return exact callback amount evidence.
	 */
	public function amount(): ?KesAmount {
		return $this->amount;
	}

	/**
	 * Return normalized receipt evidence.
	 */
	public function receipt_number(): ?string {
		return null === $this->receipt_number ? null : strtoupper( $this->receipt_number );
	}

	/**
	 * Return normalized callback phone evidence.
	 */
	public function phone_number(): ?KenyanPhoneNumber {
		return $this->phone_number;
	}
}
