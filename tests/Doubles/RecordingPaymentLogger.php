<?php
/**
 * Recording payment logger.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Doubles;

use DarajaMpesa\Infrastructure\Logging\PaymentLogger;

/**
 * Captures bounded payment events for assertions.
 */
final class RecordingPaymentLogger implements PaymentLogger {
	/**
	 * Recorded events.
	 *
	 * @var list<array{level: string, event: string, context: array<string, bool|float|int|string|null>}>
	 */
	public array $events = array();

	/**
	 * Record an informational event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function info( string $event, array $context = array() ): void {
		$this->record( 'info', $event, $context );
	}

	/**
	 * Record a warning event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function warning( string $event, array $context = array() ): void {
		$this->record( 'warning', $event, $context );
	}

	/**
	 * Record an error event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function error( string $event, array $context = array() ): void {
		$this->record( 'error', $event, $context );
	}

	/**
	 * Record one event.
	 *
	 * @param string                                    $level   Log level.
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	private function record( string $level, string $event, array $context ): void {
		$this->events[] = compact( 'level', 'event', 'context' );
	}
}
