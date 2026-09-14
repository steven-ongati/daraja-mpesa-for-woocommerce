<?php
/**
 * Unverified-success review transition.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Domain\PaymentAttemptRepository;

/**
 * Moves callback-less query success to explicit manual review.
 */
final class PaymentReviewMarker {
	/**
	 * Configure durable review transitions.
	 *
	 * @param PaymentAttemptRepository $repository Attempt repository.
	 */
	public function __construct( private readonly PaymentAttemptRepository $repository ) {
	}

	/**
	 * Mark unresolved success for administrator review.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 */
	public function mark( string $attempt_id ): ?PaymentAttempt {
		$attempt = $this->repository->find_by_attempt_id( $attempt_id );
		if ( null === $attempt || AttemptState::SUCCEEDED_UNVERIFIED !== $attempt->state() ) {
			return $attempt;
		}

		$updated = $attempt->require_manual_review();
		$this->repository->save( $attempt, $updated );

		return $updated;
	}
}
