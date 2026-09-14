<?php
/**
 * Daraja API environment.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

/**
 * Restricts API traffic to Safaricom-owned endpoints.
 */
enum Environment: string {
	case SANDBOX = 'sandbox';
	case LIVE    = 'live';

	/**
	 * Return the trusted Daraja API origin.
	 */
	public function base_url(): string {
		return match ( $this ) {
			self::SANDBOX => 'https://sandbox.safaricom.co.ke',
			self::LIVE => 'https://api.safaricom.co.ke',
		};
	}
}
