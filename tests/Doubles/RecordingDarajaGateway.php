<?php
/**
 * Recording Daraja gateway.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\Daraja\DarajaGateway;
use DarajaMpesa\Infrastructure\Daraja\StkPushRequest;
use DarajaMpesa\Infrastructure\Daraja\StkPushResult;
use DarajaMpesa\Infrastructure\Daraja\StkQueryResult;
use LogicException;

/**
 * Records application requests and returns a configured result.
 */
final class RecordingDarajaGateway implements DarajaGateway {
	/**
	 * Last STK Push request.
	 *
	 * @var StkPushRequest|null
	 */
	public ?StkPushRequest $request = null;

	/**
	 * Configured status-query outcome.
	 *
	 * @var StkQueryResult|DarajaApiException|null
	 */
	private StkQueryResult|DarajaApiException|null $query_result = null;

	/**
	 * Create a configured gateway.
	 *
	 * @param StkPushResult|DarajaApiException $push_result Push outcome.
	 */
	public function __construct(
		private readonly StkPushResult|DarajaApiException $push_result
	) {
	}

	/**
	 * Record an STK Push.
	 *
	 * @param StkPushRequest $request Validated payment request.
	 *
	 * @throws DarajaApiException When configured to reject initiation.
	 */
	public function push( StkPushRequest $request ): StkPushResult {
		$this->request = $request;

		if ( $this->push_result instanceof DarajaApiException ) {
			throw $this->push_result;
		}

		return $this->push_result;
	}

	/**
	 * Return the configured status-query outcome.
	 *
	 * @param string $checkout_request_id Immutable provider checkout identifier.
	 *
	 * @throws LogicException When no query result is configured.
	 */
	public function query( string $checkout_request_id ): StkQueryResult {
		unset( $checkout_request_id );

		if ( null === $this->query_result ) {
			throw new LogicException( 'No query result was configured.' );
		}

		if ( $this->query_result instanceof DarajaApiException ) {
			throw $this->query_result;
		}

		return $this->query_result;
	}

	/**
	 * Configure a status-query outcome.
	 *
	 * @param StkQueryResult|DarajaApiException $result Query outcome.
	 */
	public function query_result( StkQueryResult|DarajaApiException $result ): void {
		$this->query_result = $result;
	}
}
