<?php
/**
 * Safe Daraja API failure.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

use RuntimeException;

/**
 * Carries actionable provider failure metadata without raw sensitive bodies.
 */
final class DarajaApiException extends RuntimeException {
	/**
	 * Store a safe provider failure.
	 *
	 * @param string $message     Human-readable safe message.
	 * @param string $error_code  Stable internal error code.
	 * @param bool   $retryable   Whether a later retry may succeed.
	 * @param int    $http_status Provider HTTP status, when available.
	 */
	public function __construct(
		string $message,
		private readonly string $error_code,
		private readonly bool $retryable,
		private readonly int $http_status = 0
	) {
		parent::__construct( $message );
	}

	/**
	 * Return the stable internal error code.
	 */
	public function error_code(): string {
		return $this->error_code;
	}

	/**
	 * Whether a later retry may succeed.
	 */
	public function is_retryable(): bool {
		return $this->retryable;
	}

	/**
	 * Return the provider HTTP status.
	 */
	public function http_status(): int {
		return $this->http_status;
	}
}
