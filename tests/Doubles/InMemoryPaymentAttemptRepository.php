<?php
/**
 * In-memory payment-attempt repository.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Domain\PaymentAttemptRepository;
use DarajaMpesa\Infrastructure\Persistence\ConcurrentAttemptUpdate;

/**
 * Exercises application services with production concurrency semantics.
 */
final class InMemoryPaymentAttemptRepository implements PaymentAttemptRepository {
	/**
	 * Stored attempts keyed by public identifier.
	 *
	 * @var array<string, PaymentAttempt>
	 */
	private array $attempts = array();

	/**
	 * Next database identifier.
	 *
	 * @var int
	 */
	private int $next_id = 1;

	/**
	 * Insert a new payment attempt.
	 *
	 * @param PaymentAttempt $attempt Unpersisted attempt.
	 */
	public function add( PaymentAttempt $attempt ): PaymentAttempt {
		$stored = PaymentAttempt::restore(
			$this->next_id++,
			$attempt->attempt_id(),
			$attempt->order_id(),
			$attempt->amount(),
			$attempt->phone_hash(),
			$attempt->state(),
			$attempt->merchant_request_id(),
			$attempt->checkout_request_id(),
			$attempt->receipt_number(),
			$attempt->provider_result_code(),
			$attempt->failure_code(),
			$attempt->poll_count(),
			$attempt->version()
		);

		$this->attempts[ $stored->attempt_id() ] = $stored;

		return $stored;
	}

	/**
	 * Find an attempt by its immutable public identifier.
	 *
	 * @param string $attempt_id Public attempt identifier.
	 */
	public function find_by_attempt_id( string $attempt_id ): ?PaymentAttempt {
		return $this->attempts[ $attempt_id ] ?? null;
	}

	/**
	 * Find an attempt by its immutable Daraja correlation identifier.
	 *
	 * @param string $checkout_request_id Daraja checkout request identifier.
	 */
	public function find_by_checkout_request_id( string $checkout_request_id ): ?PaymentAttempt {
		foreach ( $this->attempts as $attempt ) {
			if ( $checkout_request_id === $attempt->checkout_request_id() ) {
				return $attempt;
			}
		}

		return null;
	}

	/**
	 * Determine whether a receipt already belongs to another attempt.
	 *
	 * @param string   $receipt_number       M-Pesa receipt number.
	 * @param int|null $excluding_attempt_id Attempt allowed to own the receipt.
	 */
	public function receipt_exists( string $receipt_number, ?int $excluding_attempt_id = null ): bool {
		foreach ( $this->attempts as $attempt ) {
			if (
				strtoupper( $receipt_number ) === $attempt->receipt_number()
				&& $excluding_attempt_id !== $attempt->id()
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Save one optimistic state transition.
	 *
	 * @param PaymentAttempt $expected Previously loaded attempt.
	 * @param PaymentAttempt $updated  Versioned replacement attempt.
	 *
	 * @throws ConcurrentAttemptUpdate When the expected version is stale.
	 */
	public function save( PaymentAttempt $expected, PaymentAttempt $updated ): void {
		$current = $this->attempts[ $expected->attempt_id() ] ?? null;

		if (
			null === $current
			|| $current->version() !== $expected->version()
			|| $expected->id() !== $updated->id()
			|| $expected->attempt_id() !== $updated->attempt_id()
			|| $expected->version() + 1 !== $updated->version()
		) {
			throw new ConcurrentAttemptUpdate( 'The in-memory attempt changed.' );
		}

		$this->attempts[ $updated->attempt_id() ] = $updated;
	}
}
