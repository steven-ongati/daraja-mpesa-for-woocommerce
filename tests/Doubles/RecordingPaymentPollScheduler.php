<?php
/**
 * Recording payment scheduler.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Application\PaymentPollScheduler;

/**
 * Records reconciliation schedules for tests.
 */
final class RecordingPaymentPollScheduler implements PaymentPollScheduler {
	/**
	 * Scheduled poll.
	 *
	 * @var array{attempt_id: string, delay: int}|null
	 */
	private ?array $poll = null;

	/**
	 * Scheduled review.
	 *
	 * @var array{attempt_id: string, delay: int}|null
	 */
	private ?array $review = null;

	/**
	 * Record a poll schedule.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 * @param int    $delay      Delay in seconds.
	 */
	public function schedule_poll( string $attempt_id, int $delay ): void {
		$this->poll = compact( 'attempt_id', 'delay' );
	}

	/**
	 * Record a review schedule.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 * @param int    $delay      Delay in seconds.
	 */
	public function schedule_review( string $attempt_id, int $delay ): void {
		$this->review = compact( 'attempt_id', 'delay' );
	}

	/**
	 * Return the latest poll schedule.
	 *
	 * @return array{attempt_id: string, delay: int}|null
	 */
	public function poll(): ?array {
		return $this->poll;
	}

	/**
	 * Return the latest review schedule.
	 *
	 * @return array{attempt_id: string, delay: int}|null
	 */
	public function review(): ?array {
		return $this->review;
	}
}
