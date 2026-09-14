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
	 * Status querying is not needed by initiation tests.
	 *
	 * @param string $checkout_request_id Immutable provider checkout identifier.
	 *
	 * @throws LogicException Always, because no query result is configured.
	 */
	public function query( string $checkout_request_id ): StkQueryResult {
		unset( $checkout_request_id );

		throw new LogicException( 'No query result was configured.' );
	}
}
