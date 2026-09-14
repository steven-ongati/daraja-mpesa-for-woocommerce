<?php
/**
 * Time source contract.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Support;

use DateTimeImmutable;

/**
 * Supplies time for provider timestamps and deterministic tests.
 */
interface Clock {
	/**
	 * Return the current instant.
	 */
	public function now(): DateTimeImmutable;
}
