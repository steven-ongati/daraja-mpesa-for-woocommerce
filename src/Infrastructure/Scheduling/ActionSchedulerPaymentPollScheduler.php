<?php
/**
 * Action Scheduler payment reconciliation adapter.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Scheduling;

use DarajaMpesa\Application\PaymentPollScheduler;

/**
 * Uses WooCommerce Action Scheduler for durable background work.
 */
final class ActionSchedulerPaymentPollScheduler implements PaymentPollScheduler {
	public const POLL_HOOK   = 'daraja_mpesa_poll_attempt';
	public const REVIEW_HOOK = 'daraja_mpesa_review_attempt';
	private const GROUP      = 'daraja-mpesa';

	/**
	 * Schedule a provider status query.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 * @param int    $delay      Delay in seconds.
	 */
	public function schedule_poll( string $attempt_id, int $delay ): void {
		$this->schedule( self::POLL_HOOK, $attempt_id, $delay );
	}

	/**
	 * Schedule transition of callback-less success to manual review.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 * @param int    $delay      Delay in seconds.
	 */
	public function schedule_review( string $attempt_id, int $delay ): void {
		$this->schedule( self::REVIEW_HOOK, $attempt_id, $delay );
	}

	/**
	 * Schedule one unique action.
	 *
	 * @param string $hook       Action hook.
	 * @param string $attempt_id Payment attempt identifier.
	 * @param int    $delay      Delay in seconds.
	 */
	private function schedule( string $hook, string $attempt_id, int $delay ): void {
		$arguments = array( 'attempt_id' => $attempt_id );
		if ( false !== as_has_scheduled_action( $hook, $arguments, self::GROUP ) ) {
			return;
		}

		as_schedule_single_action(
			time() + max( 1, $delay ),
			$hook,
			$arguments,
			self::GROUP,
			true
		);
	}
}
