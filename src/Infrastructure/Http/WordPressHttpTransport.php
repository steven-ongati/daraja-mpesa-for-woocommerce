<?php
/**
 * WordPress HTTP API transport.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Http;

/**
 * Sends provider requests through WordPress rather than direct cURL calls.
 */
final class WordPressHttpTransport implements HttpTransport {
	/**
	 * Send an HTTP request.
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $url     Absolute provider URL.
	 * @param array<string, string> $headers Request headers.
	 * @param string|null           $body    Serialized request body.
	 * @param int                   $timeout Request timeout in seconds.
	 *
	 * @throws HttpTransportException When WordPress reports a network failure.
	 */
	public function request(
		string $method,
		string $url,
		array $headers = array(),
		?string $body = null,
		int $timeout = 15
	): HttpResponse {
		$arguments = array(
			'method'      => $method,
			'timeout'     => $timeout,
			'redirection' => 0,
			'headers'     => $headers,
			'data_format' => 'body',
		);

		if ( null !== $body ) {
			$arguments['body'] = $body;
		}

		$response = wp_safe_remote_request( $url, $arguments );

		if ( is_wp_error( $response ) ) {
			throw new HttpTransportException( 'Daraja could not be reached.' );
		}

		return new HttpResponse(
			(int) wp_remote_retrieve_response_code( $response ),
			wp_remote_retrieve_body( $response )
		);
	}
}
