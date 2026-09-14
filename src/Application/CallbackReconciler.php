<?php
/**
 * Daraja callback reconciler.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\PaymentAttempt;
use DarajaMpesa\Domain\PaymentAttemptRepository;
use DarajaMpesa\Infrastructure\Logging\PaymentLogger;

/**
 * Settles only correlated callbacks with exact amount, phone, and receipt evidence.
 */
final class CallbackReconciler {
	/**
	 * Configure callback reconciliation dependencies.
	 *
	 * @param PaymentAttemptRepository $repository          Durable attempt repository.
	 * @param OrderPaymentCompleter    $orders              WooCommerce settlement boundary.
	 * @param PaymentLogger            $logger              Redacted payment logger.
	 * @param string                   $installation_secret Installation-specific HMAC secret.
	 */
	public function __construct(
		private readonly PaymentAttemptRepository $repository,
		private readonly OrderPaymentCompleter $orders,
		private readonly PaymentLogger $logger,
		private readonly string $installation_secret
	) {
	}

	/**
	 * Reconcile one authenticated callback.
	 *
	 * @param string          $attempt_id Immutable local attempt identifier.
	 * @param CallbackPayload $payload    Validated provider evidence.
	 *
	 * @throws CallbackConflict When callback evidence conflicts with payment intent.
	 */
	public function reconcile( string $attempt_id, CallbackPayload $payload ): PaymentAttempt {
		$attempt = $this->repository->find_by_attempt_id( $attempt_id );
		if ( null === $attempt ) {
			throw new CallbackConflict( 'The callback payment attempt is unknown.' );
		}

		$this->require_correlation( $attempt, $payload );

		if ( AttemptState::SETTLED === $attempt->state() ) {
			if ( $payload->is_successful() && $attempt->receipt_number() === $payload->receipt_number() ) {
				if ( $this->orders->is_current_attempt( $attempt->order_id(), $attempt->attempt_id() ) ) {
					$this->orders->complete( $attempt->order_id(), (string) $attempt->receipt_number(), $attempt->attempt_id() );
				}
				return $attempt;
			}

			throw new CallbackConflict( 'The callback conflicts with a settled attempt.' );
		}

		if ( ! $payload->is_successful() ) {
			if ( AttemptState::PENDING !== $attempt->state() ) {
				if ( $attempt->provider_result_code() === $payload->result_code() ) {
					return $attempt;
				}

				throw new CallbackConflict( 'The callback conflicts with an existing payment outcome.' );
			}

			$updated = $attempt->queried( $payload->result_code() );
			$this->repository->save( $attempt, $updated );
			return $updated;
		}

		$successful = match ( $attempt->state() ) {
			AttemptState::PENDING => $attempt->queried( '0' ),
			AttemptState::SUCCEEDED_UNVERIFIED,
			AttemptState::MANUAL_REVIEW,
			AttemptState::TIMED_OUT => $attempt,
			default => throw new CallbackConflict( 'The callback conflicts with an existing payment outcome.' ),
		};

		if ( $successful !== $attempt ) {
			$this->repository->save( $attempt, $successful );
			$attempt = $successful;
		}

		if ( ! $this->orders->is_current_attempt( $attempt->order_id(), $attempt->attempt_id() ) ) {
			$updated = AttemptState::MANUAL_REVIEW === $successful->state()
				? $successful
				: $successful->require_manual_review();
			$this->save_if_changed( $attempt, $updated );
			$this->logger->warning(
				'payment.callback_superseded_attempt',
				array( 'attempt_id' => $attempt->attempt_id() )
			);
			return $updated;
		}

		$amount  = $payload->amount();
		$phone   = $payload->phone_number();
		$receipt = $payload->receipt_number();

		if ( null === $amount || null === $phone || null === $receipt ) {
			$updated = AttemptState::MANUAL_REVIEW === $successful->state()
				? $successful
				: $successful->require_manual_review();
			$this->save_if_changed( $attempt, $updated );
			return $updated;
		}

		if (
			$attempt->amount()->value() !== $amount->value()
			|| $attempt->amount()->value() !== $this->orders->current_amount( $attempt->order_id() )->value()
		) {
			$updated = $successful->amount_mismatch();
			$this->repository->save( $attempt, $updated );
			$this->logger->warning(
				'payment.callback_amount_mismatch',
				array( 'attempt_id' => $attempt->attempt_id() )
			);
			return $updated;
		}

		$phone_hash = hash_hmac( 'sha256', $phone->value(), $this->installation_secret );
		if ( ! hash_equals( $attempt->phone_hash(), $phone_hash ) ) {
			$updated = AttemptState::MANUAL_REVIEW === $successful->state()
				? $successful
				: $successful->require_manual_review();
			$this->save_if_changed( $attempt, $updated );
			$this->logger->warning(
				'payment.callback_phone_mismatch',
				array( 'attempt_id' => $attempt->attempt_id() )
			);
			return $updated;
		}

		if ( $this->repository->receipt_exists( $receipt, $attempt->id() ) ) {
			$updated = $successful->duplicate_receipt();
			$this->repository->save( $attempt, $updated );
			$this->logger->warning(
				'payment.callback_duplicate_receipt',
				array( 'attempt_id' => $attempt->attempt_id() )
			);
			return $updated;
		}

		$updated = $successful->settle( $receipt );
		$this->repository->save( $attempt, $updated );
		$this->orders->complete( $updated->order_id(), $receipt, $updated->attempt_id() );
		$this->logger->info(
			'payment.callback_settled',
			array( 'attempt_id' => $updated->attempt_id() )
		);

		return $updated;
	}

	/**
	 * Require exact immutable provider identifiers.
	 *
	 * @param PaymentAttempt  $attempt Persisted payment intent.
	 * @param CallbackPayload $payload Validated callback fields.
	 *
	 * @throws CallbackConflict When provider identifiers do not match.
	 */
	private function require_correlation( PaymentAttempt $attempt, CallbackPayload $payload ): void {
		if (
			$attempt->merchant_request_id() !== $payload->merchant_request_id()
			|| $attempt->checkout_request_id() !== $payload->checkout_request_id()
		) {
			throw new CallbackConflict( 'The callback correlation identifiers do not match.' );
		}
	}

	/**
	 * Persist a mutation unless reconciliation is already idempotent.
	 *
	 * @param PaymentAttempt $expected Previously loaded attempt.
	 * @param PaymentAttempt $updated  Reconciled attempt.
	 */
	private function save_if_changed( PaymentAttempt $expected, PaymentAttempt $updated ): void {
		if ( $expected !== $updated ) {
			$this->repository->save( $expected, $updated );
		}
	}
}
