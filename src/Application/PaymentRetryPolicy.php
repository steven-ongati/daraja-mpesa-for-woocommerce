<?php
/**
 * Payment retry policy.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\PaymentAttemptRepository;

/**
 * Allows a new STK Push only after a definitive non-payment outcome.
 */
final class PaymentRetryPolicy {
	/**
	 * Configure retry state lookup.
	 *
	 * @param PaymentAttemptRepository $repository Payment-attempt repository.
	 */
	public function __construct(
		private readonly PaymentAttemptRepository $repository
	) {
	}

	/**
	 * Determine whether an order may start a distinct payment attempt.
	 *
	 * @param mixed $attempt_id Current order attempt metadata.
	 */
	public function may_start( mixed $attempt_id ): bool {
		if ( '' === $attempt_id || null === $attempt_id ) {
			return true;
		}

		if ( ! is_string( $attempt_id ) || 1 !== preg_match( '/^[a-f0-9-]{36}$/', $attempt_id ) ) {
			return false;
		}

		$attempt = $this->repository->find_by_attempt_id( $attempt_id );
		if ( null === $attempt ) {
			return false;
		}

		return in_array(
			$attempt->state(),
			array(
				AttemptState::CUSTOMER_CANCELLED,
				AttemptState::FAILED,
			),
			true
		);
	}
}
