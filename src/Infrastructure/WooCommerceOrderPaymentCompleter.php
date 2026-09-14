<?php
/**
 * WooCommerce order settlement adapter.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure;

use DarajaMpesa\Application\OrderPaymentCompleter;
use DarajaMpesa\Domain\KesAmount;
use RuntimeException;
use WC_Order;

/**
 * Completes reconciled orders through WooCommerce CRUD APIs.
 */
final class WooCommerceOrderPaymentCompleter implements OrderPaymentCompleter {
	/**
	 * Whether an attempt is still the order's active payment request.
	 *
	 * @param int    $order_id   WooCommerce order identifier.
	 * @param string $attempt_id Immutable payment attempt identifier.
	 */
	public function is_current_attempt( int $order_id, string $attempt_id ): bool {
		$current_attempt = $this->order( $order_id )->get_meta( '_daraja_mpesa_attempt_id', true );

		return ! is_string( $current_attempt )
			|| '' === $current_attempt
			|| hash_equals( $current_attempt, $attempt_id );
	}

	/**
	 * Return the order's current exact total.
	 *
	 * @param int $order_id WooCommerce order identifier.
	 *
	 * @throws RuntimeException When the order cannot be loaded.
	 */
	public function current_amount( int $order_id ): KesAmount {
		$order = $this->order( $order_id );

		return new KesAmount( (string) $order->get_total() );
	}

	/**
	 * Complete an order with immutable provider receipt evidence.
	 *
	 * @param int    $order_id   WooCommerce order identifier.
	 * @param string $receipt    Unique M-Pesa receipt.
	 * @param string $attempt_id Immutable payment attempt identifier.
	 */
	public function complete( int $order_id, string $receipt, string $attempt_id ): void {
		$order = $this->order( $order_id );
		$order->update_meta_data( '_daraja_mpesa_attempt_id', $attempt_id );
		if ( ! $order->is_paid() ) {
			$order->payment_complete( $receipt );
			$order->add_order_note(
				__( 'M-Pesa payment reconciled against exact amount and receipt evidence.', 'daraja-mpesa-for-woocommerce' )
			);
		}
		$order->save();
	}

	/**
	 * Load a WooCommerce order.
	 *
	 * @param int $order_id WooCommerce order identifier.
	 *
	 * @throws RuntimeException When the order cannot be loaded.
	 */
	private function order( int $order_id ): WC_Order {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			throw new RuntimeException( 'The WooCommerce order is unavailable.' );
		}

		return $order;
	}
}
