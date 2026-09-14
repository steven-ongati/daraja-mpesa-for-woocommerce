<?php
/**
 * Administrator-supplied payment evidence.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\KesAmount;
use InvalidArgumentException;

/**
 * Carries independently checked evidence without storing a raw provider response.
 */
final class ManualVerificationRequest {
	/**
	 * Validate administrator verification evidence.
	 *
	 * @param string    $attempt_id     Immutable payment attempt identifier.
	 * @param int       $order_id       WooCommerce order identifier.
	 * @param int       $operator_id    WordPress administrator identifier.
	 * @param KesAmount $amount         Amount observed in the external evidence.
	 * @param string    $receipt        M-Pesa receipt observed externally.
	 * @param string    $evidence_source External evidence source.
	 * @param string    $reason         Administrator verification rationale.
	 *
	 * @throws InvalidArgumentException When evidence is malformed or incomplete.
	 */
	public function __construct(
		private readonly string $attempt_id,
		private readonly int $order_id,
		private readonly int $operator_id,
		private readonly KesAmount $amount,
		private readonly string $receipt,
		private readonly string $evidence_source,
		private readonly string $reason
	) {
		if ( 1 !== preg_match( '/^[a-f0-9-]{36}$/', $this->attempt_id ) ) {
			throw new InvalidArgumentException( 'The payment attempt identifier is invalid.' );
		}

		if ( $this->order_id < 1 || $this->operator_id < 1 ) {
			throw new InvalidArgumentException( 'The verification actor or order is invalid.' );
		}

		if ( 1 !== preg_match( '/^[A-Z0-9]{6,32}$/', strtoupper( $this->receipt ) ) ) {
			throw new InvalidArgumentException( 'The M-Pesa receipt is invalid.' );
		}

		if ( ! in_array( $this->evidence_source, self::sources(), true ) ) {
			throw new InvalidArgumentException( 'The evidence source is invalid.' );
		}

		$reason_length = strlen( trim( $this->reason ) );
		if ( $reason_length < 20 || $reason_length > 500 ) {
			throw new InvalidArgumentException( 'The verification reason must contain 20 to 500 characters.' );
		}
	}

	/**
	 * Supported independent evidence sources.
	 *
	 * @return list<string>
	 */
	public static function sources(): array {
		return array( 'safaricom_portal', 'merchant_statement', 'provider_support' );
	}

	/**
	 * Return the payment attempt identifier.
	 */
	public function attempt_id(): string {
		return $this->attempt_id;
	}

	/**
	 * Return the WooCommerce order identifier.
	 */
	public function order_id(): int {
		return $this->order_id;
	}

	/**
	 * Return the administrator identifier.
	 */
	public function operator_id(): int {
		return $this->operator_id;
	}

	/**
	 * Return the externally observed amount.
	 */
	public function amount(): KesAmount {
		return $this->amount;
	}

	/**
	 * Return the normalized M-Pesa receipt.
	 */
	public function receipt(): string {
		return strtoupper( $this->receipt );
	}

	/**
	 * Return the independent evidence source.
	 */
	public function evidence_source(): string {
		return $this->evidence_source;
	}

	/**
	 * Return the administrator rationale.
	 */
	public function reason(): string {
		return trim( $this->reason );
	}
}
