<?php
/**
 * In-memory manual-verification audit.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Application\ManualVerificationAudit;
use DarajaMpesa\Application\ManualVerificationRequest;

/**
 * Records immutable audit outcomes for application tests.
 */
final class InMemoryManualVerificationAudit implements ManualVerificationAudit {
	/**
	 * Recorded outcomes.
	 *
	 * @var list<string>
	 */
	private array $outcomes = array();

	/**
	 * Record one verification outcome.
	 *
	 * @param ManualVerificationRequest $request Verification evidence.
	 * @param string                    $outcome Stable bounded outcome.
	 */
	public function record( ManualVerificationRequest $request, string $outcome ): void {
		unset( $request );
		$this->outcomes[] = $outcome;
	}

	/**
	 * Return the latest outcome.
	 */
	public function latest(): ?string {
		$outcome = end( $this->outcomes );

		return false === $outcome ? null : $outcome;
	}
}
