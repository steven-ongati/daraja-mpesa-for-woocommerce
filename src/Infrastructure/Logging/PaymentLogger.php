<?php
/**
 * Payment event logger contract.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Logging;

/**
 * Records bounded operational payment events.
 */
interface PaymentLogger {
	/**
	 * Record an informational event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function info( string $event, array $context = array() ): void;

	/**
	 * Record a warning event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function warning( string $event, array $context = array() ): void;

	/**
	 * Record an error event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function error( string $event, array $context = array() ): void;
}
