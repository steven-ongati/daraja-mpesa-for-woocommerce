<?php
/**
 * Payment attempt identifier generator.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

/**
 * Generates immutable public identifiers for payment attempts.
 */
interface AttemptIdGenerator {
	/**
	 * Generate a UUID.
	 */
	public function generate(): string;
}
