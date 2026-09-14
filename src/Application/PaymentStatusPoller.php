<?php
/**
 * Server-side Daraja payment status polling.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Domain\PaymentAttemptRepository;
use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\Daraja\DarajaGateway;
use DarajaMpesa\Infrastructure\Logging\PaymentLogger;

/**
 * Reconciles provider status without treating a query as receipt evidence.
 */
final class PaymentStatusPoller {
	/**
	 * Configure bounded status polling.
	 *
	 * @param PaymentAttemptRepository $repository Attempt repository.
	 * @param DarajaGateway            $daraja     Provider client.
	 * @param PaymentPollScheduler     $scheduler  Durable action scheduler.
	 * @param PaymentLogger            $logger     Redacted event logger.
	 * @param int                      $max_polls  Maximum status queries.
	 */
	public function __construct(
		private readonly PaymentAttemptRepository $repository,
		private readonly DarajaGateway $daraja,
		private readonly PaymentPollScheduler $scheduler,
		private readonly PaymentLogger $logger,
		private readonly int $max_polls = 6
	) {
	}

	/**
	 * Poll one pending payment attempt.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 */
	public function poll( string $attempt_id ): ?PaymentAttempt {
		$attempt = $this->repository->find_by_attempt_id( $attempt_id );
		if ( null === $attempt || AttemptState::PENDING !== $attempt->state() ) {
			return $attempt;
		}

		$checkout_request_id = $attempt->checkout_request_id();
		if ( null === $checkout_request_id ) {
			$updated = $attempt->require_manual_review();
			$this->repository->save( $attempt, $updated );
			return $updated;
		}

		try {
			$result = $this->daraja->query( $checkout_request_id );
		} catch ( DarajaApiException ) {
			return $this->record_unresolved( $attempt );
		}

		if (
			$attempt->merchant_request_id() !== $result->merchant_request_id()
			|| $checkout_request_id !== $result->checkout_request_id()
		) {
			$updated = $attempt->require_manual_review();
			$this->repository->save( $attempt, $updated );
			$this->logger->warning(
				'payment.poll_correlation_mismatch',
				array( 'attempt_id' => $attempt->attempt_id() )
			);
			return $updated;
		}

		$updated = $attempt->queried( $result->result_code() );
		$this->repository->save( $attempt, $updated );

		if ( AttemptState::SUCCEEDED_UNVERIFIED === $updated->state() ) {
			$this->scheduler->schedule_review( $updated->attempt_id(), 300 );
		}

		$this->logger->info(
			'payment.poll_resolved',
			array(
				'attempt_id' => $updated->attempt_id(),
				'state'      => $updated->state()->value,
			)
		);

		return $updated;
	}

	/**
	 * Persist an unresolved query and schedule bounded retry.
	 *
	 * @param PaymentAttempt $attempt Pending payment attempt.
	 */
	private function record_unresolved( PaymentAttempt $attempt ): PaymentAttempt {
		$updated = $attempt->poll_count() + 1 >= $this->max_polls
			? $attempt->poll_timed_out()
			: $attempt->poll_unresolved();
		$this->repository->save( $attempt, $updated );

		if ( AttemptState::PENDING === $updated->state() ) {
			$delay = min( 300, 30 * ( 2 ** $updated->poll_count() ) );
			$this->scheduler->schedule_poll( $updated->attempt_id(), $delay );
		}

		$this->logger->warning(
			'payment.poll_unresolved',
			array(
				'attempt_id' => $updated->attempt_id(),
				'poll_count' => $updated->poll_count(),
				'state'      => $updated->state()->value,
			)
		);

		return $updated;
	}
}
