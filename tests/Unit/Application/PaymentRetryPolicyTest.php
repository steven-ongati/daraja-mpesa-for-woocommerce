<?php
/**
 * Payment retry policy tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\PaymentRetryPolicy;
use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Tests\Doubles\InMemoryPaymentAttemptRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects retries from overlapping unresolved payment requests.
 */
final class PaymentRetryPolicyTest extends TestCase {
	private const ATTEMPT_ID = '11111111-2222-4333-8444-555555555555';

	/**
	 * Only definitive non-payment states allow a new attempt.
	 *
	 * @param AttemptState $state   Existing attempt state.
	 * @param bool         $allowed Expected retry decision.
	 */
	#[DataProvider( 'state_provider' )]
	public function test_requires_definitive_non_payment_outcome(
		AttemptState $state,
		bool $allowed
	): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$repository->add( $this->attempt( $state ) );
		$policy = new PaymentRetryPolicy( $repository );

		self::assertSame( $allowed, $policy->may_start( self::ATTEMPT_ID ) );
	}

	/**
	 * Orders without a previous payment attempt may start one.
	 */
	public function test_allows_first_attempt(): void {
		$policy = new PaymentRetryPolicy( new InMemoryPaymentAttemptRepository() );

		self::assertTrue( $policy->may_start( '' ) );
		self::assertTrue( $policy->may_start( null ) );
	}

	/**
	 * Invalid or missing referenced attempts fail closed.
	 */
	public function test_rejects_invalid_attempt_reference(): void {
		$policy = new PaymentRetryPolicy( new InMemoryPaymentAttemptRepository() );

		self::assertFalse( $policy->may_start( 'invalid' ) );
		self::assertFalse( $policy->may_start( self::ATTEMPT_ID ) );
	}

	/**
	 * Existing-state cases.
	 *
	 * @return list<array{AttemptState, bool}>
	 */
	public static function state_provider(): array {
		return array(
			array( AttemptState::CREATED, false ),
			array( AttemptState::INITIATING, false ),
			array( AttemptState::PENDING, false ),
			array( AttemptState::SUCCEEDED_UNVERIFIED, false ),
			array( AttemptState::SETTLED, false ),
			array( AttemptState::CUSTOMER_CANCELLED, true ),
			array( AttemptState::TIMED_OUT, false ),
			array( AttemptState::FAILED, true ),
			array( AttemptState::AMOUNT_MISMATCH, false ),
			array( AttemptState::DUPLICATE_RECEIPT, false ),
			array( AttemptState::MANUAL_REVIEW, false ),
		);
	}

	/**
	 * Restore an existing payment attempt.
	 *
	 * @param AttemptState $state Existing state.
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
