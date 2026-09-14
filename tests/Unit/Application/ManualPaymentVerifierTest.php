<?php
/**
 * Manual payment verifier tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\ManualPaymentVerifier;
use DarajaMpesa\Application\ManualVerificationRejected;
use DarajaMpesa\Application\ManualVerificationRequest;
use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Infrastructure\Daraja\StkPushResult;
use DarajaMpesa\Infrastructure\Daraja\StkQueryResult;
use DarajaMpesa\Tests\Doubles\InMemoryManualVerificationAudit;
use DarajaMpesa\Tests\Doubles\InMemoryOrderPaymentCompleter;
use DarajaMpesa\Tests\Doubles\InMemoryPaymentAttemptRepository;
use DarajaMpesa\Tests\Doubles\RecordingDarajaGateway;
use DarajaMpesa\Tests\Doubles\RecordingPaymentLogger;
use PHPUnit\Framework\TestCase;

/**
 * Protects external evidence, provider query, and audit requirements.
 */
final class ManualPaymentVerifierTest extends TestCase {
	private const ATTEMPT_ID = '11111111-2222-4333-8444-555555555555';

	/**
	 * Exact external evidence settles after a fresh correlated query.
	 */
	public function test_settles_exact_reviewed_payment(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$this->reviewable_attempt( $repository );
		$daraja = new RecordingDarajaGateway(
			new StkPushResult( 'merchant_123', 'checkout_123', 'accepted' )
		);
		$daraja->query_result(
			new StkQueryResult( '0', 'processed', 'merchant_123', 'checkout_123' )
		);
		$orders  = new InMemoryOrderPaymentCompleter( new KesAmount( 1250 ) );
		$audit   = new InMemoryManualVerificationAudit();
		$service = new ManualPaymentVerifier(
			$repository,
			$daraja,
			$orders,
			$audit,
			new RecordingPaymentLogger()
		);

		$updated = $service->verify( $this->request( 1250, 'ABC123XYZ9' ) );

		self::assertSame( AttemptState::SETTLED, $updated->state() );
		self::assertSame( 'ABC123XYZ9', $orders->completed()['receipt'] ?? null );
		self::assertSame( 'settled', $audit->latest() );
	}

	/**
	 * An administrator cannot settle using a locally supplied amount alone.
	 */
	public function test_rejects_amount_conflict_and_audits_decision(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$this->reviewable_attempt( $repository );
		$daraja = new RecordingDarajaGateway(
			new StkPushResult( 'merchant_123', 'checkout_123', 'accepted' )
		);
		$daraja->query_result(
			new StkQueryResult( '0', 'processed', 'merchant_123', 'checkout_123' )
		);
		$audit   = new InMemoryManualVerificationAudit();
		$service = new ManualPaymentVerifier(
			$repository,
			$daraja,
			new InMemoryOrderPaymentCompleter( new KesAmount( 1250 ) ),
			$audit,
			new RecordingPaymentLogger()
		);

		try {
			$service->verify( $this->request( 1200, 'ABC123XYZ9' ) );
			self::fail( 'Amount conflict should reject manual verification.' );
		} catch ( ManualVerificationRejected $exception ) {
			self::assertSame( 'amount_mismatch', $exception->error_code() );
		}

		self::assertSame( 'amount_mismatch', $audit->latest() );
		self::assertSame(
			AttemptState::MANUAL_REVIEW,
			$repository->find_by_attempt_id( self::ATTEMPT_ID )?->state()
		);
	}

	/**
	 * An administrator cannot settle an attempt superseded by a newer request.
	 */
	public function test_rejects_superseded_attempt(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$this->reviewable_attempt( $repository );
		$orders = new InMemoryOrderPaymentCompleter( new KesAmount( 1250 ) );
		$orders->set_current_attempt( 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee' );
		$audit   = new InMemoryManualVerificationAudit();
		$service = new ManualPaymentVerifier(
			$repository,
			new RecordingDarajaGateway(
				new StkPushResult( 'merchant_123', 'checkout_123', 'accepted' )
			),
			$orders,
			$audit,
			new RecordingPaymentLogger()
		);

		try {
			$service->verify( $this->request( 1250, 'ABC123XYZ9' ) );
			self::fail( 'A superseded attempt should not settle.' );
		} catch ( ManualVerificationRejected $exception ) {
			self::assertSame( 'superseded_attempt', $exception->error_code() );
		}

		self::assertSame( 'superseded_attempt', $audit->latest() );
		self::assertNull( $orders->completed() );
	}

	/**
	 * Build administrator evidence.
	 *
	 * @param int    $amount  Externally observed amount.
	 * @param string $receipt Externally observed receipt.
	 */
	private function request( int $amount, string $receipt ): ManualVerificationRequest {
		return new ManualVerificationRequest(
			self::ATTEMPT_ID,
			91,
			7,
			new KesAmount( $amount ),
			$receipt,
			'safaricom_portal',
			'Confirmed against the Safaricom merchant portal transaction record.'
		);
	}

	/**
	 * Persist a callback-less successful attempt requiring review.
	 *
	 * @param InMemoryPaymentAttemptRepository $repository Attempt repository.
	 */
	private function reviewable_attempt( InMemoryPaymentAttemptRepository $repository ): void {
		$created    = PaymentAttempt::create(
			self::ATTEMPT_ID,
			91,
			new KesAmount( 1250 ),
			hash( 'sha256', 'phone' )
		);
		$created    = $repository->add( $created );
		$initiating = $created->initiating();
		$repository->save( $created, $initiating );
		$pending = $initiating->pending( 'merchant_123', 'checkout_123' );
		$repository->save( $initiating, $pending );
		$successful = $pending->queried( '0' );
		$repository->save( $pending, $successful );
		$review = $successful->require_manual_review();
		$repository->save( $successful, $review );
	}
}
