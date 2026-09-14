<?php
/**
 * Payment attempt state.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Domain;

/**
 * Enumerates explicit payment outcomes without equating timeout to payment.
 */
enum AttemptState: string {
	case CREATED              = 'created';
	case INITIATING           = 'initiating';
	case PENDING              = 'pending';
	case SUCCEEDED_UNVERIFIED = 'succeeded_unverified';
	case SETTLED              = 'settled';
	case CUSTOMER_CANCELLED   = 'customer_cancelled';
	case TIMED_OUT            = 'timed_out';
	case FAILED               = 'failed';
	case AMOUNT_MISMATCH      = 'amount_mismatch';
	case DUPLICATE_RECEIPT    = 'duplicate_receipt';
	case MANUAL_REVIEW        = 'manual_review';

	/**
	 * Whether automatic polling may stop for this state.
	 */
	public function is_terminal(): bool {
		return match ( $this ) {
			self::CREATED, self::INITIATING, self::PENDING => false,
			default => true,
		};
	}

	/**
	 * Whether this state proves that the WooCommerce order is paid.
	 */
	public function is_settled(): bool {
		return self::SETTLED === $this;
	}
}
