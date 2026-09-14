<?php
/**
 * Payment initiation tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\AttemptIdGenerator;
use DarajaMpesa\Application\CallbackAddress;
use DarajaMpesa\Application\PaymentInitiator;
use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\Daraja\StkPushResult;
use DarajaMpesa\Tests\Doubles\InMemoryPaymentAttemptRepository;
use DarajaMpesa\Tests\Doubles\RecordingDarajaGateway;
use DarajaMpesa\Tests\Doubles\RecordingPaymentLogger;
use DarajaMpesa\Tests\Doubles\RecordingPaymentPollScheduler;
use PHPUnit\Framework\TestCase;

/**
 * Protects persist-before-network initiation and safe failure states.
 */
final class PaymentInitiatorTest extends TestCase {
	private const ATTEMPT_ID = '123e4567-e89b-12d3-a456-426614174000';

	/**
	 * Persists exact intent before returning accepted correlation identifiers.
	 */
	public function test_initiates_a_persisted_stk_push(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$daraja     = new RecordingDarajaGateway(
			new StkPushResult( 'merchant-123', 'ws_CO_12345678', 'Check your phone' )
		);
		$logger     = new RecordingPaymentLogger();
		$scheduler  = new RecordingPaymentPollScheduler();
		$initiator  = $this->initiator( $repository, $daraja, $logger, $scheduler );

		$attempt = $initiator->start( 123, '2500.00', '0712345678' );

		self::assertSame( AttemptState::PENDING, $attempt->state() );
		self::assertSame( 2500, $attempt->amount()->value() );
		self::assertSame( '254712345678', $daraja->request?->phone_number()->value() );
		self::assertSame( 2500, $daraja->request?->amount()->value() );
		self::assertStringNotContainsString(
			'installation-secret',
			(string) $daraja->request?->callback_url()
		);
		self::assertSame( $attempt, $repository->find_by_attempt_id( self::ATTEMPT_ID ) );
		self::assertSame( 'payment.stk_push_accepted', $logger->events[0]['event'] );
		self::assertSame( self::ATTEMPT_ID, $scheduler->poll()['attempt_id'] ?? null );
	}

	/**
	 * Provider rejection becomes a durable unpaid failure before propagation.
	 */
	public function test_persists_safe_provider_failure(): void {
		$repository = new InMemoryPaymentAttemptRepository();
		$exception  = new DarajaApiException( 'Daraja is unavailable.', 'stk_push_transport_error', true );
		$daraja     = new RecordingDarajaGateway( $exception );
		$logger     = new RecordingPaymentLogger();
		$initiator  = $this->initiator(
			$repository,
			$daraja,
			$logger,
			new RecordingPaymentPollScheduler()
		);

		try {
			$initiator->start( 123, 2500, '254712345678' );
			self::fail( 'Expected provider failure.' );
		} catch ( DarajaApiException $caught ) {
			self::assertSame( $exception, $caught );
		}

		$attempt = $repository->find_by_attempt_id( self::ATTEMPT_ID );

		self::assertNotNull( $attempt );
		self::assertSame( AttemptState::FAILED, $attempt->state() );
		self::assertSame( 'stk_push_transport_error', $attempt->failure_code() );
		self::assertFalse( $attempt->state()->is_settled() );
		self::assertSame( 'payment.initiation_failed', $logger->events[0]['event'] );
	}

	/**
	 * Build the initiation service.
	 *
	 * @param InMemoryPaymentAttemptRepository $repository Attempt repository.
	 * @param RecordingDarajaGateway           $daraja     Provider gateway.
	 * @param RecordingPaymentLogger           $logger     Event logger.
	 * @param RecordingPaymentPollScheduler    $scheduler  Reconciliation scheduler.
	 */
	private function initiator(
		InMemoryPaymentAttemptRepository $repository,
		RecordingDarajaGateway $daraja,
		RecordingPaymentLogger $logger,
		RecordingPaymentPollScheduler $scheduler
	): PaymentInitiator {
		$generator = new class(self::ATTEMPT_ID) implements AttemptIdGenerator {
			/**
			 * Configure a deterministic attempt identifier.
			 *
			 * @param string $attempt_id Attempt identifier.
			 */
			public function __construct(
				private readonly string $attempt_id
			) {
			}

			/**
			 * Return a deterministic attempt identifier.
			 */
			public function generate(): string {
				return $this->attempt_id;
			}
		};

		return new PaymentInitiator(
			$repository,
			$daraja,
			$logger,
			$scheduler,
			$generator,
			new CallbackAddress(),
			'https://store.example/wp-json/daraja-mpesa/v1/callback',
			'installation-secret'
		);
	}
}
