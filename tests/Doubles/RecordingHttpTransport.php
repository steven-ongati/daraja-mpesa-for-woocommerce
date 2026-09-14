<?php
/**
 * Recording HTTP transport test double.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Infrastructure\Http\HttpResponse;
use DarajaMpesa\Infrastructure\Http\HttpTransport;
use DarajaMpesa\Infrastructure\Http\HttpTransportException;
use LogicException;

/**
 * Returns queued provider responses and records outbound requests.
 */
final class RecordingHttpTransport implements HttpTransport {
	/**
	 * Queued outcomes.
	 *
	 * @var list<HttpResponse|HttpTransportException>
	 */
	private array $outcomes;

	/**
	 * Recorded requests.
	 *
	 * @var list<array{method: string, url: string, headers: array<string, string>, body: string|null, timeout: int}>
	 */
	private array $requests = array();

	/**
	 * Configure provider outcomes.
	 *
	 * @param list<HttpResponse|HttpTransportException> $outcomes Queued provider outcomes.
	 */
	public function __construct( array $outcomes ) {
		$this->outcomes = $outcomes;
	}

	/**
	 * Send and record an HTTP request.
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $url     Absolute provider URL.
	 * @param array<string, string> $headers Request headers.
	 * @param string|null           $body    Serialized request body.
	 * @param int                   $timeout Request timeout in seconds.
	 *
	 * @throws LogicException When no provider outcome remains.
	 */
	public function request(
		string $method,
		string $url,
		array $headers = array(),
		?string $body = null,
		int $timeout = 15
	): HttpResponse {
		$this->requests[] = array(
			'method'  => $method,
			'url'     => $url,
			'headers' => $headers,
			'body'    => $body,
			'timeout' => $timeout,
		);

		$outcome = array_shift( $this->outcomes );

		if ( null === $outcome ) {
			throw new LogicException( 'No HTTP outcome was queued.' );
		}

		if ( $outcome instanceof HttpTransportException ) {
			throw $outcome;
		}

		return $outcome;
	}

	/**
	 * Return recorded requests.
	 *
	 * @return list<array{method: string, url: string, headers: array<string, string>, body: string|null, timeout: int}>
	 */
	public function requests(): array {
		return $this->requests;
	}
}
