<?php
/**
 * Daraja operations contract.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

/**
 * Initiates and queries M-Pesa Express requests.
 */
interface DarajaGateway {
	/**
	 * Initiate a customer STK Push.
	 *
	 * @param StkPushRequest $request Validated payment request.
	 */
	public function push( StkPushRequest $request ): StkPushResult;

	/**
	 * Query an existing STK Push.
	 *
	 * @param string $checkout_request_id Immutable provider checkout identifier.
	 */
	public function query( string $checkout_request_id ): StkQueryResult;
}
