<?php
/**
 * STK Query result.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

/**
 * Carries provider status without treating it as amount or receipt evidence.
 */
final class StkQueryResult {
	/**
	 * Store a provider status query result.
	 *
	 * @param string $result_code         Provider result code.
	 * @param string $result_description  Provider result description.
	 * @param string $merchant_request_id Provider merchant request identifier.
	 * @param string $checkout_request_id Provider checkout request identifier.
	 */
	public function __construct(
		private readonly string $result_code,
		private readonly string $result_description,
		private readonly string $merchant_request_id,
		private readonly string $checkout_request_id
	) {
	}

	/**
	 * Return the provider result code.
	 */
	public function result_code(): string {
		return $this->result_code;
	}

	/**
	 * Return the bounded provider description.
	 */
	public function result_description(): string {
		return $this->result_description;
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
	 * Whether Daraja reports payment success.
	 */
	public function is_successful(): bool {
		return '0' === $this->result_code;
	}
}
