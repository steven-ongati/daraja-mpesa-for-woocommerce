<?php
/**
 * Payment initiation service.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\KenyanPhoneNumber;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Domain\PaymentAttemptRepository;
use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\Daraja\DarajaGateway;
use DarajaMpesa\Infrastructure\Daraja\StkPushRequest;
use DarajaMpesa\Infrastructure\Logging\PaymentLogger;

/**
 * Persists intent before initiating an STK Push.
 */
final class PaymentInitiator {
	/**
	 * Configure payment initiation.
	 *
	 * @param PaymentAttemptRepository $repository       Durable attempt repository.
	 * @param DarajaGateway            $daraja           Daraja provider client.
	 * @param PaymentLogger            $logger           Redacted event logger.
	 * @param PaymentPollScheduler     $scheduler        Durable reconciliation scheduler.
	 * @param AttemptIdGenerator       $id_generator     Attempt UUID generator.
	 * @param CallbackAddress          $callback_address Callback URL protector.
	 * @param string                   $callback_endpoint Public callback endpoint.
	 * @param string                   $installation_secret WordPress installation secret.
	 */
	public function __construct(
		private readonly PaymentAttemptRepository $repository,
		private readonly DarajaGateway $daraja,
		private readonly PaymentLogger $logger,
		private readonly PaymentPollScheduler $scheduler,
		private readonly AttemptIdGenerator $id_generator,
		private readonly CallbackAddress $callback_address,
		private readonly string $callback_endpoint,
		private readonly string $installation_secret
	) {
	}

	/**
	 * Persist and initiate one payment attempt.
	 *
	 * @param int        $order_id        WooCommerce order identifier.
	 * @param string|int $order_total     Exact WooCommerce order total.
	 * @param string     $customer_phone Customer M-Pesa phone number.
	 *
	 * @throws DarajaApiException When Daraja cannot accept the request.
	 */
	public function start( int $order_id, string|int $order_total, string $customer_phone ): PaymentAttempt {
		$phone      = new KenyanPhoneNumber( $customer_phone );
		$amount     = new KesAmount( $order_total );
		$attempt_id = $this->id_generator->generate();
		$attempt    = PaymentAttempt::create(
			$attempt_id,
			$order_id,
			$amount,
			hash_hmac( 'sha256', $phone->value(), $this->installation_secret )
		);
		$request    = new StkPushRequest(
			$phone,
			$amount,
			$this->callback_address->build(
				$this->callback_endpoint,
				$attempt_id,
				$this->installation_secret
			),
			$this->account_reference( $order_id ),
			substr( 'Order ' . $order_id, 0, 13 )
		);
		$attempt    = $this->repository->add( $attempt );
		$initiating = $attempt->initiating();

		$this->repository->save( $attempt, $initiating );

		try {
			$result = $this->daraja->push( $request );
		} catch ( DarajaApiException $exception ) {
			$failed = $initiating->initiation_failed( $exception->error_code() );
			$this->repository->save( $initiating, $failed );
			$this->logger->warning(
				'payment.initiation_failed',
				array(
					'attempt_id' => $attempt_id,
					'error_code' => $exception->error_code(),
					'order_id'   => $order_id,
					'retryable'  => $exception->is_retryable(),
				)
			);

			throw $exception;
		}

		$pending = $initiating->pending(
			$result->merchant_request_id(),
			$result->checkout_request_id()
		);
		$this->repository->save( $initiating, $pending );
		$this->scheduler->schedule_poll( $pending->attempt_id(), 30 );
		$this->logger->info(
			'payment.stk_push_accepted',
			array(
				'attempt_id' => $attempt_id,
				'order_id'   => $order_id,
			)
		);

		return $pending;
	}

	/**
	 * Build a bounded, order-specific Daraja account reference.
	 *
	 * @param int $order_id WooCommerce order identifier.
	 */
	private function account_reference( int $order_id ): string {
		return substr( 'ORDER' . $order_id, -12 );
	}
}
