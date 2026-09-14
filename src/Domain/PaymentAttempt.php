<?php
/**
 * Durable M-Pesa payment attempt.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Domain;

use InvalidArgumentException;

/**
 * Applies versioned, explicit payment-attempt state transitions.
 */
final class PaymentAttempt {
	/**
	 * Restore a payment attempt from durable storage.
	 *
	 * @param int|null     $id                  Database identifier.
	 * @param string       $attempt_id          Immutable attempt UUID.
	 * @param int          $order_id            WooCommerce order identifier.
	 * @param KesAmount    $amount              Expected order amount.
	 * @param string       $phone_hash          One-way customer phone hash.
	 * @param AttemptState $state               Current attempt state.
	 * @param string|null  $merchant_request_id Daraja merchant request identifier.
	 * @param string|null  $checkout_request_id Daraja checkout request identifier.
	 * @param string|null  $receipt_number      Settled M-Pesa receipt number.
	 * @param string|null  $provider_result_code Latest Daraja result code.
	 * @param string|null  $failure_code        Stable internal failure code.
	 * @param int          $poll_count          Completed status queries.
	 * @param int          $version             Optimistic concurrency version.
	 *
	 * @throws InvalidArgumentException When restored state violates storage invariants.
	 */
	private function __construct(
		private readonly ?int $id,
		private readonly string $attempt_id,
		private readonly int $order_id,
		private readonly KesAmount $amount,
		private readonly string $phone_hash,
		private readonly AttemptState $state,
		private readonly ?string $merchant_request_id,
		private readonly ?string $checkout_request_id,
		private readonly ?string $receipt_number,
		private readonly ?string $provider_result_code,
		private readonly ?string $failure_code,
		private readonly int $poll_count,
		private readonly int $version
	) {
		if ( 1 !== preg_match( '/^[a-f0-9-]{36}$/', $this->attempt_id ) ) {
			throw new InvalidArgumentException( 'The payment attempt identifier is invalid.' );
		}

		if ( $this->order_id < 1 || 1 !== preg_match( '/^[a-f0-9]{64}$/', $this->phone_hash ) ) {
			throw new InvalidArgumentException( 'The payment attempt context is invalid.' );
		}

		if ( $this->poll_count < 0 || $this->version < 1 ) {
			throw new InvalidArgumentException( 'The payment attempt version is invalid.' );
		}
	}

	/**
	 * Start a new unpersisted payment attempt.
	 *
	 * @param string    $attempt_id Immutable attempt UUID.
	 * @param int       $order_id   WooCommerce order identifier.
	 * @param KesAmount $amount     Expected order amount.
	 * @param string    $phone_hash One-way customer phone hash.
	 */
	public static function create(
		string $attempt_id,
		int $order_id,
		KesAmount $amount,
		string $phone_hash
	): self {
		return new self(
			null,
			$attempt_id,
			$order_id,
			$amount,
			$phone_hash,
			AttemptState::CREATED,
			null,
			null,
			null,
			null,
			null,
			0,
			1
		);
	}

	/**
	 * Restore a persisted payment attempt.
	 *
	 * @param int          $id                   Database identifier.
	 * @param string       $attempt_id           Immutable attempt UUID.
	 * @param int          $order_id             WooCommerce order identifier.
	 * @param KesAmount    $amount               Expected order amount.
	 * @param string       $phone_hash           One-way customer phone hash.
	 * @param AttemptState $state                Current attempt state.
	 * @param string|null  $merchant_request_id  Daraja merchant request identifier.
	 * @param string|null  $checkout_request_id  Daraja checkout request identifier.
	 * @param string|null  $receipt_number       Settled M-Pesa receipt number.
	 * @param string|null  $provider_result_code Latest Daraja result code.
	 * @param string|null  $failure_code         Stable internal failure code.
	 * @param int          $poll_count           Completed status queries.
	 * @param int          $version              Optimistic concurrency version.
	 */
	public static function restore(
		int $id,
		string $attempt_id,
		int $order_id,
		KesAmount $amount,
		string $phone_hash,
		AttemptState $state,
		?string $merchant_request_id,
		?string $checkout_request_id,
		?string $receipt_number,
		?string $provider_result_code,
		?string $failure_code,
		int $poll_count,
		int $version
	): self {
		return new self(
			$id,
			$attempt_id,
			$order_id,
			$amount,
			$phone_hash,
			$state,
			$merchant_request_id,
			$checkout_request_id,
			$receipt_number,
			$provider_result_code,
			$failure_code,
			$poll_count,
			$version
		);
	}

	/**
	 * Record that provider initiation has started.
	 */
	public function initiating(): self {
		$this->require_state( AttemptState::CREATED );

		return $this->copy( state: AttemptState::INITIATING );
	}

	/**
	 * Record accepted provider correlation identifiers.
	 *
	 * @param string $merchant_request_id Daraja merchant request identifier.
	 * @param string $checkout_request_id Daraja checkout request identifier.
	 */
	public function pending( string $merchant_request_id, string $checkout_request_id ): self {
		$this->require_state( AttemptState::INITIATING );
		$this->require_provider_identifier( $merchant_request_id );
		$this->require_provider_identifier( $checkout_request_id );

		return $this->copy(
			state: AttemptState::PENDING,
			merchant_request_id: $merchant_request_id,
			checkout_request_id: $checkout_request_id
		);
	}

	/**
	 * Record a safe initiation failure.
	 *
	 * @param string $failure_code Stable internal failure code.
	 */
	public function initiation_failed( string $failure_code ): self {
		$this->require_state( AttemptState::INITIATING );

		return $this->copy(
			state: AttemptState::FAILED,
			failure_code: $this->validate_code( $failure_code )
		);
	}

	/**
	 * Record a provider status query result.
	 *
	 * @param string $result_code Daraja result code.
	 */
	public function queried( string $result_code ): self {
		$this->require_state( AttemptState::PENDING );
		$result_code = $this->validate_code( $result_code );
		$state       = match ( $result_code ) {
			'0' => AttemptState::SUCCEEDED_UNVERIFIED,
			'1032' => AttemptState::CUSTOMER_CANCELLED,
			'1037' => AttemptState::TIMED_OUT,
			default => AttemptState::FAILED,
		};

		return $this->copy(
			state: $state,
			provider_result_code: $result_code,
			poll_count: $this->poll_count + 1
		);
	}

	/**
	 * Record another unresolved poll without treating it as payment.
	 */
	public function poll_unresolved(): self {
		$this->require_state( AttemptState::PENDING );

		return $this->copy( poll_count: $this->poll_count + 1 );
	}

	/**
	 * Move successful but incomplete evidence to administrator review.
	 */
	public function require_manual_review(): self {
		$this->require_state( AttemptState::SUCCEEDED_UNVERIFIED );

		return $this->copy( state: AttemptState::MANUAL_REVIEW );
	}

	/**
	 * Record exact, unique receipt evidence.
	 *
	 * @param string $receipt_number Provider receipt number.
	 */
	public function settle( string $receipt_number ): self {
		$receipt_number = $this->validate_receipt( $receipt_number );

		if ( AttemptState::SETTLED === $this->state && $receipt_number === $this->receipt_number ) {
			return $this;
		}

		$this->require_one_of(
			AttemptState::PENDING,
			AttemptState::SUCCEEDED_UNVERIFIED,
			AttemptState::MANUAL_REVIEW
		);

		return $this->copy(
			state: AttemptState::SETTLED,
			receipt_number: $receipt_number,
			provider_result_code: '0',
			failure_code: null
		);
	}

	/**
	 * Record provider success with the wrong amount.
	 */
	public function amount_mismatch(): self {
		$this->require_one_of( AttemptState::PENDING, AttemptState::SUCCEEDED_UNVERIFIED );

		return $this->copy( state: AttemptState::AMOUNT_MISMATCH );
	}

	/**
	 * Record receipt evidence already used by another attempt.
	 */
	public function duplicate_receipt(): self {
		$this->require_one_of( AttemptState::PENDING, AttemptState::SUCCEEDED_UNVERIFIED );

		return $this->copy( state: AttemptState::DUPLICATE_RECEIPT );
	}

	/**
	 * Copy this attempt with one versioned mutation.
	 *
	 * @param AttemptState|null $state                Replacement state.
	 * @param string|null       $merchant_request_id  Replacement merchant identifier.
	 * @param string|null       $checkout_request_id  Replacement checkout identifier.
	 * @param string|null       $receipt_number       Replacement receipt.
	 * @param string|null       $provider_result_code Replacement provider result.
	 * @param string|null       $failure_code         Replacement internal failure.
	 * @param int|null          $poll_count           Replacement poll count.
	 */
	private function copy(
		?AttemptState $state = null,
		?string $merchant_request_id = null,
		?string $checkout_request_id = null,
		?string $receipt_number = null,
		?string $provider_result_code = null,
		?string $failure_code = null,
		?int $poll_count = null
	): self {
		return new self(
			$this->id,
			$this->attempt_id,
			$this->order_id,
			$this->amount,
			$this->phone_hash,
			$state ?? $this->state,
			$merchant_request_id ?? $this->merchant_request_id,
			$checkout_request_id ?? $this->checkout_request_id,
			$receipt_number ?? $this->receipt_number,
			$provider_result_code ?? $this->provider_result_code,
			$failure_code ?? $this->failure_code,
			$poll_count ?? $this->poll_count,
			$this->version + 1
		);
	}

	/**
	 * Require one exact current state.
	 *
	 * @param AttemptState $state Required current state.
	 */
	private function require_state( AttemptState $state ): void {
		$this->require_one_of( $state );
	}

	/**
	 * Require one of the supplied current states.
	 *
	 * @param AttemptState ...$states Allowed current states.
	 *
	 * @throws InvalidAttemptTransition When the current state is not allowed.
	 */
	private function require_one_of( AttemptState ...$states ): void {
		if ( ! in_array( $this->state, $states, true ) ) {
			throw new InvalidAttemptTransition( 'The payment attempt transition is not allowed.' );
		}
	}

	/**
	 * Validate a bounded provider or internal code.
	 *
	 * @param string $code Provider or internal code.
	 *
	 * @throws InvalidArgumentException When the code is not safe for storage.
	 */
	private function validate_code( string $code ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_.-]{1,64}$/', $code ) ) {
			throw new InvalidArgumentException( 'The payment result code is invalid.' );
		}

		return $code;
	}

	/**
	 * Validate an immutable provider identifier.
	 *
	 * @param string $identifier Provider identifier.
	 *
	 * @throws InvalidArgumentException When the identifier is invalid.
	 */
	private function require_provider_identifier( string $identifier ): void {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_.-]{8,128}$/', $identifier ) ) {
			throw new InvalidArgumentException( 'The provider request identifier is invalid.' );
		}
	}

	/**
	 * Validate a receipt identifier.
	 *
	 * @param string $receipt_number Provider receipt number.
	 *
	 * @throws InvalidArgumentException When the receipt is invalid.
	 */
	private function validate_receipt( string $receipt_number ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9]{6,64}$/', $receipt_number ) ) {
			throw new InvalidArgumentException( 'The M-Pesa receipt number is invalid.' );
		}

		return strtoupper( $receipt_number );
	}

	/**
	 * Return the database identifier.
	 */
	public function id(): ?int {
		return $this->id;
	}

	/**
	 * Return the immutable attempt identifier.
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
	 * Return the exact expected amount.
	 */
	public function amount(): KesAmount {
		return $this->amount;
	}

	/**
	 * Return the one-way phone hash.
	 */
	public function phone_hash(): string {
		return $this->phone_hash;
	}

	/**
	 * Return the current state.
	 */
	public function state(): AttemptState {
		return $this->state;
	}

	/**
	 * Return the merchant request identifier.
	 */
	public function merchant_request_id(): ?string {
		return $this->merchant_request_id;
	}

	/**
	 * Return the checkout request identifier.
	 */
	public function checkout_request_id(): ?string {
		return $this->checkout_request_id;
	}

	/**
	 * Return the settled receipt number.
	 */
	public function receipt_number(): ?string {
		return $this->receipt_number;
	}

	/**
	 * Return the latest provider result code.
	 */
	public function provider_result_code(): ?string {
		return $this->provider_result_code;
	}

	/**
	 * Return the safe internal failure code.
	 */
	public function failure_code(): ?string {
		return $this->failure_code;
	}

	/**
	 * Return the completed query count.
	 */
	public function poll_count(): int {
		return $this->poll_count;
	}

	/**
	 * Return the optimistic concurrency version.
	 */
	public function version(): int {
		return $this->version;
	}
}
