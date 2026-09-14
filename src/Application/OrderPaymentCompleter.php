<?php
/**
 * WooCommerce order settlement boundary.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Domain\KesAmount;

/**
 * Provides current order totals and idempotent payment completion.
 */
interface OrderPaymentCompleter {
	/**
	 * Return the order's current exact total.
	 *
	 * @param int $order_id WooCommerce order identifier.
	 */
	public function current_amount( int $order_id ): KesAmount;

	/**
	 * Mark an order paid with reconciled receipt evidence.
	 *
	 * @param int    $order_id      WooCommerce order identifier.
	 * @param string $receipt       Unique M-Pesa receipt.
	 * @param string $attempt_id    Immutable payment attempt identifier.
	 */
	public function complete( int $order_id, string $receipt, string $attempt_id ): void;
}
