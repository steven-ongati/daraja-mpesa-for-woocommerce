<?php
/**
 * Fixed clock test double.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Support\Clock;
use DateTimeImmutable;

/**
 * Supplies one deterministic provider timestamp.
 */
final class FixedClock implements Clock {
	/**
	 * Configure the current instant.
	 *
	 * @param DateTimeImmutable $now Fixed current instant.
	 */
	public function __construct( private readonly DateTimeImmutable $now ) {
	}

	/**
	 * Return the fixed current instant.
	 */
	public function now(): DateTimeImmutable {
		return $this->now;
	}
}
