<?php
/**
 * Manual payment verification audit boundary.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

/**
 * Appends immutable records for administrator verification decisions.
 */
interface ManualVerificationAudit {
	/**
	 * Record one verification outcome.
	 *
	 * @param ManualVerificationRequest $request Verification evidence.
	 * @param string                    $outcome Stable bounded outcome.
	 */
	public function record( ManualVerificationRequest $request, string $outcome ): void;
}
