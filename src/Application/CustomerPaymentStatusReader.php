<?php
/**
 * Customer-safe payment-status reader.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\AttemptState;
use DarajaMpesa\Domain\PaymentAttemptRepository;

/**
 * Projects durable attempt state into bounded customer guidance.
 */
final class CustomerPaymentStatusReader {
	/**
	 * Configure the read-only status projection.
	 *
	 * @param PaymentAttemptRepository $repository Payment-attempt repository.
	 */
	public function __construct(
		private readonly PaymentAttemptRepository $repository
	) {
	}

	/**
	 * Read one order-bound attempt without changing payment state.
	 *
	 * @param string $attempt_id Attempt identifier stored on the order.
	 * @param int    $order_id   Expected WooCommerce order.
	 * @param bool   $order_paid Whether WooCommerce already considers the order paid.
	 */
	public function read( string $attempt_id, int $order_id, bool $order_paid ): ?CustomerPaymentStatus {
		$attempt = $this->repository->find_by_attempt_id( $attempt_id );
		if ( null === $attempt || $order_id !== $attempt->order_id() ) {
			return null;
		}

		if ( $order_paid || AttemptState::SETTLED === $attempt->state() ) {
			return new CustomerPaymentStatus(
				'confirmed',
				__( 'Your M-Pesa payment is confirmed.', 'daraja-mpesa-for-woocommerce' ),
				false
			);
		}

		return match ( $attempt->state() ) {
			AttemptState::CREATED,
			AttemptState::INITIATING,
			AttemptState::PENDING => new CustomerPaymentStatus(
				'pending',
				__( 'Check your phone for the M-Pesa prompt. The order remains unpaid while confirmation is pending.', 'daraja-mpesa-for-woocommerce' ),
				true
			),
			AttemptState::SUCCEEDED_UNVERIFIED => new CustomerPaymentStatus(
				'confirming',
				__( 'Safaricom reports success. We are still verifying the exact amount and receipt before marking the order paid.', 'daraja-mpesa-for-woocommerce' ),
				true
			),
			AttemptState::MANUAL_REVIEW,
			AttemptState::TIMED_OUT,
			AttemptState::AMOUNT_MISMATCH,
			AttemptState::DUPLICATE_RECEIPT => new CustomerPaymentStatus(
				'review',
				__( 'Automatic confirmation is incomplete. The store must verify this payment before the order can proceed.', 'daraja-mpesa-for-woocommerce' ),
				true
			),
			AttemptState::CUSTOMER_CANCELLED => new CustomerPaymentStatus(
				'cancelled',
				__( 'The M-Pesa request was cancelled. Return to checkout to start a new payment attempt.', 'daraja-mpesa-for-woocommerce' ),
				false
			),
			AttemptState::FAILED => new CustomerPaymentStatus(
				'failed',
				__( 'The M-Pesa request could not be confirmed. Return to checkout to start a new payment attempt.', 'daraja-mpesa-for-woocommerce' ),
				false
			),
		};
	}
}
