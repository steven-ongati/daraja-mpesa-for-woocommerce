<?php
/**
 * Durable payment reconciliation scheduler.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

/**
 * Schedules server-side status polling and unresolved-success review.
 */
interface PaymentPollScheduler {
	/**
	 * Schedule a provider status query.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 * @param int    $delay      Delay in seconds.
	 */
	public function schedule_poll( string $attempt_id, int $delay ): void;

	/**
	 * Schedule transition of callback-less success to manual review.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 * @param int    $delay      Delay in seconds.
	 */
	public function schedule_review( string $attempt_id, int $delay ): void;
}
