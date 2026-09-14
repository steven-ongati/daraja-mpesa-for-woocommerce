<?php
/**
 * Payment-attempt repository contract.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Domain;

/**
 * Persists payment state independently of WooCommerce order storage.
 */
interface PaymentAttemptRepository {
	/**
	 * Insert a new payment attempt.
	 *
	 * @param PaymentAttempt $attempt Unpersisted attempt.
	 */
	public function add( PaymentAttempt $attempt ): PaymentAttempt;

	/**
	 * Find an attempt by its immutable public identifier.
	 *
	 * @param string $attempt_id Public attempt identifier.
	 */
	public function find_by_attempt_id( string $attempt_id ): ?PaymentAttempt;

	/**
	 * Find an attempt by its immutable Daraja correlation identifier.
	 *
	 * @param string $checkout_request_id Daraja checkout request identifier.
	 */
	public function find_by_checkout_request_id( string $checkout_request_id ): ?PaymentAttempt;

	/**
	 * Determine whether a receipt already belongs to another attempt.
	 *
	 * @param string   $receipt_number       M-Pesa receipt number.
	 * @param int|null $excluding_attempt_id Attempt allowed to own the receipt.
	 */
	public function receipt_exists( string $receipt_number, ?int $excluding_attempt_id = null ): bool;

	/**
	 * Save one optimistic state transition.
	 *
	 * @param PaymentAttempt $expected Previously loaded attempt.
	 * @param PaymentAttempt $updated  Versioned replacement attempt.
	 */
	public function save( PaymentAttempt $expected, PaymentAttempt $updated ): void;
}
