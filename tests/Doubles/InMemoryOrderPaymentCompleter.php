<?php
/**
 * In-memory order payment completer.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Application\OrderPaymentCompleter;
use DarajaMpesa\Domain\KesAmount;

/**
 * Records order settlement for reconciliation tests.
 */
final class InMemoryOrderPaymentCompleter implements OrderPaymentCompleter {
	/**
	 * Completed order evidence.
	 *
	 * @var array{order_id: int, receipt: string, attempt_id: string}|null
	 */
	private ?array $completed = null;

	/**
	 * Configure the current order total.
	 *
	 * @param KesAmount $amount Current order amount.
	 */
	public function __construct( private readonly KesAmount $amount ) {
	}

	/**
	 * Return the configured current total.
	 *
	 * @param int $order_id WooCommerce order identifier.
	 */
	public function current_amount( int $order_id ): KesAmount {
		return $this->amount;
	}

	/**
	 * Record idempotent order settlement.
	 *
	 * @param int    $order_id   WooCommerce order identifier.
	 * @param string $receipt    M-Pesa receipt.
	 * @param string $attempt_id Payment attempt identifier.
	 */
	public function complete( int $order_id, string $receipt, string $attempt_id ): void {
		$this->completed = compact( 'order_id', 'receipt', 'attempt_id' );
	}

	/**
	 * Return the latest settlement evidence.
	 *
	 * @return array{order_id: int, receipt: string, attempt_id: string}|null
	 */
	public function completed(): ?array {
		return $this->completed;
	}
}
