<?php
/**
 * HTTP transport contract.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Http;

/**
 * Sends bounded requests to provider APIs.
 */
interface HttpTransport {
	/**
	 * Send an HTTP request.
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $url     Absolute provider URL.
	 * @param array<string, string> $headers Request headers.
	 * @param string|null           $body    Serialized request body.
	 * @param int                   $timeout Request timeout in seconds.
	 */
	public function request(
		string $method,
		string $url,
		array $headers = array(),
		?string $body = null,
		int $timeout = 15
	): HttpResponse;
}
