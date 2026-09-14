<?php
/**
 * Callback reconciliation tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\CallbackPayload;
use DarajaMpesa\Application\CallbackReconciler;
use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\KenyanPhoneNumber;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Tests\Doubles\InMemoryOrderPaymentCompleter;
use DarajaMpesa\Tests\Doubles\InMemoryPaymentAttemptRepository;
use DarajaMpesa\Tests\Doubles\RecordingPaymentLogger;
use PHPUnit\Framework\TestCase;

/**
 * Protects exact settlement and mismatch behavior.
 */
final class CallbackReconcilerTest extends TestCase {
	private const ATTEMPT_ID = '11111111-2222-4333-8444-555555555555';
	private const SECRET     = 'installation-secret-with-sufficient-entropy';

	/**
	 * Settles only exact, correlated success evidence.
	 */
	public function test_settles_exact_success_callback(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$attempt    = $this->pending_attempt( $repository, 1250 );
		$orders     = new InMemoryOrderPaymentCompleter( new KesAmount( 1250 ) );
		$reconciler = new CallbackReconciler(
			$repository,
			$orders,
			new RecordingPaymentLogger(),
			self::SECRET
		);

		$updated = $reconciler->reconcile( self::ATTEMPT_ID, $this->success_payload( 1250 ) );

		self::assertSame( AttemptState::SETTLED, $updated->state() );
		self::assertSame( 'RKT123ABC', $updated->receipt_number() );
		self::assertSame( $attempt->order_id(), $orders->completed()['order_id'] ?? null );
	}

	/**
	 * Keeps the order unpaid when the callback amount differs.
	 */
	public function test_records_amount_mismatch_without_completing_order(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$this->pending_attempt( $repository, 1250 );
		$orders     = new InMemoryOrderPaymentCompleter( new KesAmount( 1250 ) );
		$reconciler = new CallbackReconciler(
			$repository,
			$orders,
			new RecordingPaymentLogger(),
			self::SECRET
		);

		$updated = $reconciler->reconcile( self::ATTEMPT_ID, $this->success_payload( 1200 ) );

		self::assertSame( AttemptState::AMOUNT_MISMATCH, $updated->state() );
		self::assertNull( $orders->completed() );
	}

	/**
	 * Create and persist a pending payment attempt.
	 *
	 * @param InMemoryPaymentAttemptRepository $repository Attempt repository.
	 * @param int                              $amount     Expected amount.
	 */
	private function pending_attempt(
		InMemoryPaymentAttemptRepository $repository,
		int $amount
	): PaymentAttempt {
		$created = PaymentAttempt::create(
			self::ATTEMPT_ID,
			91,
			new KesAmount( $amount ),
			hash_hmac( 'sha256', '254712345678', self::SECRET )
		);
		$created = $repository->add( $created );
		$pending = $created->initiating()->pending( 'merchant_123', 'checkout_123' );
		$repository->save( $created, $pending );

		return $pending;
	}

	/**
	 * Build exact provider success evidence.
	 *
	 * @param int $amount Callback amount.
	 */
	private function success_payload( int $amount ): CallbackPayload {
		return new CallbackPayload(
			'merchant_123',
			'checkout_123',
			'0',
			new KesAmount( $amount ),
			'RKT123ABC',
			new KenyanPhoneNumber( '0712345678' )
		);
	}
}
