<?php
/**
 * Accepted STK Push identifiers.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

/**
 * Carries immutable provider correlation identifiers after acceptance.
 */
final class StkPushResult {
	/**
	 * Store accepted provider identifiers.
	 *
	 * @param string $merchant_request_id Provider merchant request identifier.
	 * @param string $checkout_request_id Provider checkout request identifier.
	 * @param string $customer_message    Provider customer message.
	 */
	public function __construct(
		private readonly string $merchant_request_id,
		private readonly string $checkout_request_id,
		private readonly string $customer_message
	) {
	}

	/**
	 * Return the merchant request identifier.
	 */
	public function merchant_request_id(): string {
		return $this->merchant_request_id;
	}

	/**
	 * Return the checkout request identifier.
	 */
	public function checkout_request_id(): string {
		return $this->checkout_request_id;
	}

	/**
	 * Return the provider customer message.
	 */
	public function customer_message(): string {
		return $this->customer_message;
	}
}
