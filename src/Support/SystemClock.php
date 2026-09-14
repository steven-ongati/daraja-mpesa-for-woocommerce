<?php
/**
 * System time source.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Supplies current East Africa time for Daraja timestamps.
 */
final class SystemClock implements Clock {
	/**
	 * Return the current instant in the provider timezone.
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'Africa/Nairobi' ) );
	}
}
