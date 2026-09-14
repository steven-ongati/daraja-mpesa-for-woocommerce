<?php
/**
 * Customer payment-status reader tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\CustomerPaymentStatusReader;
use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Tests\Doubles\InMemoryPaymentAttemptRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects the bounded, read-only customer projection.
 */
final class CustomerPaymentStatusReaderTest extends TestCase {
	private const ATTEMPT_ID = '11111111-2222-4333-8444-555555555555';

	/**
	 * Attempt states become bounded public guidance.
	 *
	 * @param AttemptState $state       Durable payment state.
	 * @param string       $code        Expected public code.
	 * @param bool         $refreshable Whether local status may change.
	 */
	#[DataProvider( 'status_provider' )]
	public function test_projects_customer_safe_status(
		AttemptState $state,
		string $code,
		bool $refreshable
	): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$repository->add( $this->attempt( $state ) );
		$reader = new CustomerPaymentStatusReader( $repository );

		$status = $reader->read( self::ATTEMPT_ID, 91, false );

		self::assertSame( $code, $status?->code() );
		self::assertSame( $refreshable, $status?->is_refreshable() );
		self::assertStringNotContainsString( self::ATTEMPT_ID, $status?->message() ?? '' );
	}

	/**
	 * An attempt cannot be read through another order.
	 */
	public function test_rejects_mismatched_order(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$repository->add( $this->attempt( AttemptState::PENDING ) );
		$reader = new CustomerPaymentStatusReader( $repository );

		self::assertNull( $reader->read( self::ATTEMPT_ID, 92, false ) );
	}

	/**
	 * WooCommerce paid state takes precedence over stale local display state.
	 */
	public function test_paid_order_is_confirmed(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$repository->add( $this->attempt( AttemptState::PENDING ) );
		$reader = new CustomerPaymentStatusReader( $repository );

		$status = $reader->read( self::ATTEMPT_ID, 91, true );

		self::assertSame( 'confirmed', $status?->code() );
		self::assertFalse( $status?->is_refreshable() ?? true );
	}

	/**
	 * Public status cases.
	 *
	 * @return list<array{AttemptState, string, bool}>
	 */
	public static function status_provider(): array {
		return array(
			array( AttemptState::CREATED, 'pending', true ),
			array( AttemptState::INITIATING, 'pending', true ),
			array( AttemptState::PENDING, 'pending', true ),
			array( AttemptState::SUCCEEDED_UNVERIFIED, 'confirming', true ),
			array( AttemptState::SETTLED, 'confirmed', false ),
			array( AttemptState::CUSTOMER_CANCELLED, 'cancelled', false ),
			array( AttemptState::TIMED_OUT, 'review', true ),
			array( AttemptState::FAILED, 'failed', false ),
			array( AttemptState::AMOUNT_MISMATCH, 'review', true ),
			array( AttemptState::DUPLICATE_RECEIPT, 'review', true ),
			array( AttemptState::MANUAL_REVIEW, 'review', true ),
		);
	}

	/**
	 * Restore a persisted attempt for display.
	 *
	 * @param AttemptState $state Durable payment state.
	 */
	private function attempt( AttemptState $state ): PaymentAttempt {
		return PaymentAttempt::restore(
			1,
			self::ATTEMPT_ID,
			91,
			new KesAmount( 1250 ),
			hash( 'sha256', 'phone' ),
			$state,
			'merchant_123',
			'checkout_123',
			AttemptState::SETTLED === $state ? 'ABC123XYZ' : null,
			null,
			null,
			0,
			1
		);
	}
}
