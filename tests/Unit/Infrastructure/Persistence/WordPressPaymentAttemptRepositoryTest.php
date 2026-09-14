<?php
/**
 * WordPress payment-attempt repository tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Infrastructure\Persistence;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Infrastructure\Persistence\ConcurrentAttemptUpdate;
use DarajaMpesa\Infrastructure\Persistence\WordPressPaymentAttemptRepository;
use DarajaMpesa\Tests\Doubles\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use wpdb;

/**
 * Protects privacy, hydration, and optimistic repository writes.
 */
final class WordPressPaymentAttemptRepositoryTest extends TestCase {
	/**
	 * New attempts store exact amounts and hashes without raw phone values.
	 */
	public function test_adds_privacy_bounded_attempt(): void {
		$database   = new wpdb();
		$repository = $this->repository( $database );
		$stored     = $repository->add( $this->attempt() );

		self::assertSame( 41, $stored->id() );
		self::assertSame( 2500, $database->inserted_data['amount'] );
		self::assertSame( hash( 'sha256', '254712345678' ), $database->inserted_data['phone_hash'] );
		self::assertArrayNotHasKey( 'phone', $database->inserted_data );
		self::assertArrayNotHasKey( 'callback_payload', $database->inserted_data );
	}

	/**
	 * Updates compare the loaded version and persist exactly one transition.
	 */
	public function test_saves_with_optimistic_version_predicate(): void {
		$database   = new wpdb();
		$repository = $this->repository( $database );
		$expected   = $repository->add( $this->attempt() );
		$updated    = $expected->initiating();

		$repository->save( $expected, $updated );

		self::assertSame(
			array(
				'id'      => 41,
				'version' => 1,
			),
			$database->updated_where
		);
		self::assertSame( AttemptState::INITIATING->value, $database->updated_data['state'] );
		self::assertSame( 2, $database->updated_data['version'] );
		self::assertSame( '2026-01-01 00:00:00', $database->updated_data['created_at_gmt'] );
	}

	/**
	 * A lost optimistic update never overwrites the winning callback.
	 */
	public function test_rejects_concurrent_update(): void {
		$database                = new wpdb();
		$database->update_result = 0;
		$repository              = $this->repository( $database );
		$expected                = $repository->add( $this->attempt() );

		$this->expectException( ConcurrentAttemptUpdate::class );

		$repository->save( $expected, $expected->initiating() );
	}

	/**
	 * Stored rows restore exact domain state.
	 */
	public function test_hydrates_attempt_by_checkout_identifier(): void {
		$database             = new wpdb();
		$database->row_result = array(
			'id'                   => '41',
			'attempt_id'           => '123e4567-e89b-12d3-a456-426614174000',
			'order_id'             => '123',
			'amount'               => '2500',
			'phone_hash'           => hash( 'sha256', '254712345678' ),
			'state'                => 'pending',
			'merchant_request_id'  => 'merchant-123',
			'checkout_request_id'  => 'ws_CO_12345678',
			'receipt_number'       => null,
			'provider_result_code' => null,
			'failure_code'         => null,
			'poll_count'           => '2',
			'version'              => '4',
		);

		$attempt = $this->repository( $database )->find_by_checkout_request_id( 'ws_CO_12345678' );

		self::assertNotNull( $attempt );
		self::assertSame( AttemptState::PENDING, $attempt->state() );
		self::assertSame( 2500, $attempt->amount()->value() );
		self::assertSame( 2, $attempt->poll_count() );
		self::assertSame( 4, $attempt->version() );
	}

	/**
	 * Receipt checks can exclude an idempotent replay owner.
	 */
	public function test_detects_existing_receipt(): void {
		$database             = new wpdb();
		$database->var_result = 1;

		self::assertTrue( $this->repository( $database )->receipt_exists( 'qwe123abc', 41 ) );
	}

	/**
	 * Return the repository under test.
	 *
	 * @param wpdb $database Recording WordPress database.
	 */
	private function repository( wpdb $database ): WordPressPaymentAttemptRepository {
		return new WordPressPaymentAttemptRepository(
			$database,
			new FixedClock( new DateTimeImmutable( '2026-01-01T03:00:00+03:00' ) )
		);
	}

	/**
	 * Return a new unpersisted attempt.
	 */
	private function attempt(): PaymentAttempt {
		return PaymentAttempt::create(
			'123e4567-e89b-12d3-a456-426614174000',
			123,
			new KesAmount( 2500 ),
			hash( 'sha256', '254712345678' )
		);
	}
}
