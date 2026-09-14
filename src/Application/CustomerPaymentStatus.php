<?php
/**
 * Bounded customer payment status.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

/**
 * Carries customer-safe status without provider or payment identifiers.
 */
final class CustomerPaymentStatus {
	/**
	 * Create a customer-safe payment status.
	 *
	 * @param string $code       Stable public status.
	 * @param string $message    Customer-facing guidance.
	 * @param bool   $refreshable Whether local status may still change.
	 */
	public function __construct(
		private readonly string $code,
		private readonly string $message,
		private readonly bool $refreshable
	) {
	}

	/**
	 * Return the stable public status.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Return the customer-facing guidance.
	 */
	public function message(): string {
		return $this->message;
	}

	/**
	 * Whether the browser may refresh this read-only status.
	 */
	public function is_refreshable(): bool {
		return $this->refreshable;
	}
}
