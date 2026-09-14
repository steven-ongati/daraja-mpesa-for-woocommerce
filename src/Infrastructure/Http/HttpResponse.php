<?php
/**
 * HTTP response value.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Http;

/**
 * Carries the provider status and body without WordPress response internals.
 */
final class HttpResponse {
	/**
	 * Store an HTTP response.
	 *
	 * @param int    $status_code HTTP status code.
	 * @param string $body        Raw response body.
	 */
	public function __construct(
		private readonly int $status_code,
		private readonly string $body
	) {
	}

	/**
	 * Return the HTTP status code.
	 */
	public function status_code(): int {
		return $this->status_code;
	}

	/**
	 * Return the raw response body.
	 */
	public function body(): string {
		return $this->body;
	}

	/**
	 * Whether the provider returned a successful HTTP status.
	 */
	public function is_successful(): bool {
		return $this->status_code >= 200 && $this->status_code < 300;
	}
}
