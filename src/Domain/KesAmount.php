<?php
/**
 * Daraja-compatible Kenya shilling amount.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Domain;

use InvalidArgumentException;

/**
 * Represents a positive whole-shilling payment amount.
 */
final class KesAmount {
	/**
	 * Whole Kenya shillings.
	 *
	 * @var positive-int
	 */
	private readonly int $value;

	/**
	 * Validate an order amount for M-Pesa Express.
	 *
	 * @param string|int $amount WooCommerce order total.
	 *
	 * @throws InvalidArgumentException When the amount is not a positive whole-shilling value.
	 */
	public function __construct( string|int $amount ) {
		$normalized = (string) $amount;

		if ( 1 !== preg_match( '/^[0-9]+(?:\.0{1,2})?$/', $normalized ) ) {
			throw new InvalidArgumentException( 'M-Pesa payments require a whole Kenya shilling amount.' );
		}

		$decimal_separator = strstr( $normalized, '.', true );
		$integer_part      = false === $decimal_separator ? $normalized : $decimal_separator;
		$canonical         = ltrim( $integer_part, '0' );
		$canonical         = '' === $canonical ? '0' : $canonical;
		$maximum           = (string) PHP_INT_MAX;

		if (
			strlen( $canonical ) > strlen( $maximum )
			|| ( strlen( $canonical ) === strlen( $maximum ) && strcmp( $canonical, $maximum ) > 0 )
		) {
			throw new InvalidArgumentException( 'The M-Pesa amount is too large.' );
		}

		$value = (int) $canonical;

		if ( $value < 1 ) {
			throw new InvalidArgumentException( 'M-Pesa payments require a positive amount.' );
		}

		$this->value = $value;
	}

	/**
	 * Return the Daraja integer amount.
	 *
	 * @return positive-int
	 */
	public function value(): int {
		return $this->value;
	}

	/**
	 * Determine whether provider evidence matches exactly.
	 *
	 * @param string|int|float $provider_amount Amount returned by Daraja.
	 */
	public function matches_provider_value( string|int|float $provider_amount ): bool {
		if ( is_float( $provider_amount ) ) {
			return is_finite( $provider_amount )
				&& floor( $provider_amount ) === $provider_amount
				&& $this->value === (int) $provider_amount;
		}

		try {
			return ( new self( $provider_amount ) )->value() === $this->value;
		} catch ( InvalidArgumentException ) {
			return false;
		}
	}
}
