<?php
/**
 * WooCommerce payment event logger.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Logging;

/**
 * Sends redacted structured events to the WooCommerce logger.
 */
final class WooCommercePaymentLogger implements PaymentLogger {
	/**
	 * Configure context redaction.
	 *
	 * @param LogContextRedactor $redactor Payment context redactor.
	 */
	public function __construct( private readonly LogContextRedactor $redactor ) {
	}

	/**
	 * Record an informational event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function info( string $event, array $context = array() ): void {
		$this->log( 'info', $event, $context );
	}

	/**
	 * Record a warning event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function warning( string $event, array $context = array() ): void {
		$this->log( 'warning', $event, $context );
	}

	/**
	 * Record an error event.
	 *
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	public function error( string $event, array $context = array() ): void {
		$this->log( 'error', $event, $context );
	}

	/**
	 * Write a redacted WooCommerce log event.
	 *
	 * @param string                                    $level   WooCommerce log level.
	 * @param string                                    $event   Stable event name.
	 * @param array<string, bool|float|int|string|null> $context Bounded event context.
	 */
	private function log( string $level, string $event, array $context ): void {
		$event_name = preg_replace( '/[^a-z0-9_.-]/', '_', strtolower( $event ) );
		$event_name = substr( null === $event_name ? 'unknown' : $event_name, 0, 80 );

		wc_get_logger()->log(
			$level,
			'Daraja M-Pesa event: ' . $event_name,
			array(
				'source'  => 'daraja-mpesa-for-woocommerce',
				'context' => $this->redactor->redact( $context ),
			)
		);
	}
}
