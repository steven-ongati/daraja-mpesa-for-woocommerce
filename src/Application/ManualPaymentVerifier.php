<?php
/**
 * Audited administrator payment verification.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Domain\PaymentAttemptRepository;
use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\Daraja\DarajaGateway;
use DarajaMpesa\Infrastructure\Logging\PaymentLogger;

/**
 * Settles only after a fresh provider query and exact external evidence.
 */
final class ManualPaymentVerifier {
	/**
	 * Configure manual verification dependencies.
	 *
	 * @param PaymentAttemptRepository $repository Payment-attempt repository.
	 * @param DarajaGateway            $daraja     Authenticated Daraja boundary.
	 * @param OrderPaymentCompleter    $orders     WooCommerce settlement boundary.
	 * @param ManualVerificationAudit  $audit      Immutable administrator audit.
	 * @param PaymentLogger            $logger     Redacted operational logger.
	 */
	public function __construct(
		private readonly PaymentAttemptRepository $repository,
		private readonly DarajaGateway $daraja,
		private readonly OrderPaymentCompleter $orders,
		private readonly ManualVerificationAudit $audit,
		private readonly PaymentLogger $logger
	) {
	}

	/**
	 * Verify and settle one unresolved payment.
	 *
	 * @param ManualVerificationRequest $request Administrator evidence.
	 *
	 * @throws ManualVerificationRejected When any evidence is incomplete or conflicts.
	 */
	public function verify( ManualVerificationRequest $request ): PaymentAttempt {
		$attempt = $this->repository->find_by_attempt_id( $request->attempt_id() );
		if ( null === $attempt || $attempt->order_id() !== $request->order_id() ) {
			$this->reject( $request, 'attempt_not_found' );
		}

		if ( AttemptState::SETTLED === $attempt->state() ) {
			if ( $attempt->receipt_number() !== $request->receipt() ) {
				$this->reject( $request, 'settled_receipt_conflict' );
			}

			$this->orders->complete( $attempt->order_id(), $request->receipt(), $attempt->attempt_id() );
			$this->audit->record( $request, 'already_settled' );
			return $attempt;
		}

		if (
			! in_array(
				$attempt->state(),
				array(
					AttemptState::SUCCEEDED_UNVERIFIED,
					AttemptState::MANUAL_REVIEW,
					AttemptState::TIMED_OUT,
				),
				true
			)
		) {
			$this->reject( $request, 'attempt_not_reviewable' );
		}

		$merchant_request_id = $attempt->merchant_request_id();
		$checkout_request_id = $attempt->checkout_request_id();
		if ( null === $merchant_request_id || null === $checkout_request_id ) {
			$this->reject( $request, 'provider_correlation_missing' );
		}

		try {
			$query = $this->daraja->query( $checkout_request_id );
		} catch ( DarajaApiException ) {
			$this->reject( $request, 'provider_query_failed' );
		}

		if (
			! $query->is_successful()
			|| $query->merchant_request_id() !== $merchant_request_id
			|| $query->checkout_request_id() !== $checkout_request_id
		) {
			$this->reject( $request, 'provider_status_conflict' );
		}

		if (
			$attempt->amount()->value() !== $request->amount()->value()
			|| $attempt->amount()->value() !== $this->orders->current_amount( $attempt->order_id() )->value()
		) {
			$this->reject( $request, 'amount_mismatch' );
		}

		if ( $this->repository->receipt_exists( $request->receipt(), $attempt->id() ) ) {
			$this->reject( $request, 'duplicate_receipt' );
		}

		$updated = $attempt->settle( $request->receipt() );
		$this->repository->save( $attempt, $updated );
		$this->orders->complete( $updated->order_id(), $request->receipt(), $updated->attempt_id() );
		$this->audit->record( $request, 'settled' );
		$this->logger->info(
			'payment.manual_verification_settled',
			array( 'attempt_id' => $updated->attempt_id() )
		);

		return $updated;
	}

	/**
	 * Audit and reject invalid evidence.
	 *
	 * @param ManualVerificationRequest $request Administrator evidence.
	 * @param string                    $outcome Stable rejection code.
	 *
	 * @throws ManualVerificationRejected Always.
	 */
	private function reject( ManualVerificationRequest $request, string $outcome ): never {
		$this->audit->record( $request, $outcome );
		$this->logger->warning(
			'payment.manual_verification_rejected',
			array(
				'attempt_id' => $request->attempt_id(),
				'outcome'    => $outcome,
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal code is not rendered and the exception message is fixed.
		throw new ManualVerificationRejected( $outcome );
	}
}
