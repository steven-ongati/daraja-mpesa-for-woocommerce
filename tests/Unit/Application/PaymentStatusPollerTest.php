<?php
/**
 * Payment status poller tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\PaymentStatusPoller;
use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\Daraja\StkPushResult;
use DarajaMpesa\Infrastructure\Daraja\StkQueryResult;
use DarajaMpesa\Tests\Doubles\InMemoryPaymentAttemptRepository;
use DarajaMpesa\Tests\Doubles\RecordingDarajaGateway;
use DarajaMpesa\Tests\Doubles\RecordingPaymentLogger;
use DarajaMpesa\Tests\Doubles\RecordingPaymentPollScheduler;
use PHPUnit\Framework\TestCase;

/**
 * Protects bounded server-side query reconciliation.
 */
final class PaymentStatusPollerTest extends TestCase {
	private const ATTEMPT_ID = '11111111-2222-4333-8444-555555555555';

	/**
	 * Query success remains unpaid and awaits callback evidence.
	 */
	public function test_successful_query_schedules_unverified_review(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$this->pending_attempt( $repository );
		$daraja = new RecordingDarajaGateway(
			new StkPushResult( 'merchant_123', 'checkout_123', 'accepted' )
		);
		$daraja->query_result(
			new StkQueryResult( '0', 'processed', 'merchant_123', 'checkout_123' )
		);
		$scheduler = new RecordingPaymentPollScheduler();
		$poller    = new PaymentStatusPoller(
			$repository,
			$daraja,
			$scheduler,
			new RecordingPaymentLogger()
		);

		$updated = $poller->poll( self::ATTEMPT_ID );

		self::assertSame( AttemptState::SUCCEEDED_UNVERIFIED, $updated?->state() );
		self::assertSame( self::ATTEMPT_ID, $scheduler->review()['attempt_id'] ?? null );
		self::assertFalse( $updated?->state()->is_settled() ?? true );
	}

	/**
	 * Repeated unresolved queries stop in an unpaid timeout.
	 */
	public function test_exhausted_queries_time_out_without_settlement(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$this->pending_attempt( $repository );
		$daraja = new RecordingDarajaGateway(
			new StkPushResult( 'merchant_123', 'checkout_123', 'accepted' )
		);
		$daraja->query_result(
			new DarajaApiException( 'unavailable', 'stk_query_unresolved', true )
		);
		$poller = new PaymentStatusPoller(
			$repository,
			$daraja,
			new RecordingPaymentPollScheduler(),
			new RecordingPaymentLogger(),
			1
		);

		$updated = $poller->poll( self::ATTEMPT_ID );

		self::assertSame( AttemptState::TIMED_OUT, $updated?->state() );
		self::assertSame( 'poll_exhausted', $updated?->failure_code() );
		self::assertFalse( $updated?->state()->is_settled() ?? true );
	}

	/**
	 * Persist a pending attempt with provider correlation identifiers.
	 *
	 * @param InMemoryPaymentAttemptRepository $repository Attempt repository.
	 */
	private function pending_attempt( InMemoryPaymentAttemptRepository $repository ): void {
		$created = PaymentAttempt::create(
			self::ATTEMPT_ID,
			91,
			new KesAmount( 1250 ),
			hash( 'sha256', 'phone' )
		);
		$created = $repository->add( $created );
		$pending = $created->initiating()->pending( 'merchant_123', 'checkout_123' );
		$repository->save( $created, $pending );
	}
}
