<?php
/**
 * Manual verification rejection.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use RuntimeException;

/**
 * Carries a bounded reason without exposing credentials or provider payloads.
 */
final class ManualVerificationRejected extends RuntimeException {
	/**
	 * Store a stable rejection code.
	 *
	 * @param string $error_code Stable rejection code.
	 */
	public function __construct( private readonly string $error_code ) {
		parent::__construct( 'The payment evidence could not be verified.' );
	}

	/**
	 * Return the stable rejection code.
	 */
	public function error_code(): string {
		return $this->error_code;
	}
}
