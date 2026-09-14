<?php
/**
 * Payment-attempt state tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Domain;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\InvalidAttemptTransition;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use PHPUnit\Framework\TestCase;

/**
 * Protects explicit, versioned payment state transitions.
 */
final class PaymentAttemptTest extends TestCase {
	/**
	 * Accepted initiation records immutable provider identifiers.
	 */
	public function test_moves_from_created_to_pending(): void {
		$created    = $this->attempt();
		$initiating = $created->initiating();
		$pending    = $initiating->pending( 'merchant-123', 'ws_CO_12345678' );

		self::assertSame( AttemptState::CREATED, $created->state() );
		self::assertSame( AttemptState::INITIATING, $initiating->state() );
		self::assertSame( AttemptState::PENDING, $pending->state() );
		self::assertSame( 'merchant-123', $pending->merchant_request_id() );
		self::assertSame( 'ws_CO_12345678', $pending->checkout_request_id() );
		self::assertSame( 3, $pending->version() );
	}

	/**
	 * Query success remains unpaid until receipt and amount evidence exist.
	 */
	public function test_query_success_is_unverified(): void {
		$queried = $this->pending_attempt()->queried( '0' );

		self::assertSame( AttemptState::SUCCEEDED_UNVERIFIED, $queried->state() );
		self::assertFalse( $queried->state()->is_settled() );
		self::assertSame( 1, $queried->poll_count() );
	}

	/**
	 * Timeout and cancellation remain explicit unpaid outcomes.
	 */
	public function test_maps_timeout_and_cancellation_without_settlement(): void {
		$cancelled = $this->pending_attempt()->queried( '1032' );
		$timed_out = $this->pending_attempt()->queried( '1037' );

		self::assertSame( AttemptState::CUSTOMER_CANCELLED, $cancelled->state() );
		self::assertSame( AttemptState::TIMED_OUT, $timed_out->state() );
		self::assertFalse( $cancelled->state()->is_settled() );
		self::assertFalse( $timed_out->state()->is_settled() );
	}

	/**
	 * Unresolved polling increments bounded reconciliation evidence.
	 */
	public function test_records_unresolved_poll_without_changing_state(): void {
		$pending = $this->pending_attempt()->poll_unresolved();

		self::assertSame( AttemptState::PENDING, $pending->state() );
		self::assertSame( 1, $pending->poll_count() );
	}

	/**
	 * Exact receipt evidence settles once and replays are idempotent.
	 */
	public function test_settlement_is_idempotent_for_the_same_receipt(): void {
		$settled = $this->pending_attempt()->settle( 'qwe123abc' );
		$replay  = $settled->settle( 'QWE123ABC' );

		self::assertSame( AttemptState::SETTLED, $settled->state() );
		self::assertSame( 'QWE123ABC', $settled->receipt_number() );
		self::assertSame( $settled, $replay );
	}

	/**
	 * Contradictory terminal mutations are rejected.
	 */
	public function test_rejects_conflicting_settlement_replay(): void {
		$settled = $this->pending_attempt()->settle( 'QWE123ABC' );

		$this->expectException( InvalidAttemptTransition::class );

		$settled->settle( 'OTHER123' );
	}

	/**
	 * Provider identifiers cannot be attached out of order.
	 */
	public function test_rejects_out_of_order_transition(): void {
		$this->expectException( InvalidAttemptTransition::class );

		$this->attempt()->pending( 'merchant-123', 'ws_CO_12345678' );
	}

	/**
	 * Return a new payment attempt.
	 */
	private function attempt(): PaymentAttempt {
		return PaymentAttempt::create(
			'123e4567-e89b-12d3-a456-426614174000',
			123,
			new KesAmount( 2500 ),
			hash( 'sha256', '254712345678' )
		);
	}

	/**
	 * Return an attempt accepted by Daraja.
	 */
	private function pending_attempt(): PaymentAttempt {
		return $this->attempt()->initiating()->pending( 'merchant-123', 'ws_CO_12345678' );
	}
}
