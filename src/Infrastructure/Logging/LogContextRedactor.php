<?php
/**
 * Payment log context redaction.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Logging;

/**
 * Removes credentials and customer payment evidence from log context.
 */
final class LogContextRedactor {
	/**
	 * Redact sensitive values and bound retained strings.
	 *
	 * @param array<string, bool|float|int|string|null> $context Payment event context.
	 *
	 * @return array<string, bool|float|int|string|null>
	 */
	public function redact( array $context ): array {
		$redacted = array();

		foreach ( $context as $key => $value ) {
			if (
				1 === preg_match(
					'/authorization|callback|consumer|credential|passkey|password|payload|phone|receipt|response|secret|token/i',
					$key
				)
			) {
				$redacted[ $key ] = '[redacted]';
				continue;
			}

			if ( is_string( $value ) ) {
				$clean            = preg_replace( '/[\x00-\x1F\x7F]/', ' ', $value );
				$redacted[ $key ] = substr( null === $clean ? '' : $clean, 0, 128 );
				continue;
			}

			$redacted[ $key ] = $value;
		}

		return $redacted;
	}
}
