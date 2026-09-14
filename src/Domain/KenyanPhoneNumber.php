<?php
/**
 * Kenyan mobile phone number.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Domain;

use InvalidArgumentException;

/**
 * Normalizes supported Kenyan mobile formats for Daraja requests.
 */
final class KenyanPhoneNumber {
	/**
	 * Canonical Daraja phone number.
	 *
	 * @var string
	 */
	private readonly string $value;

	/**
	 * Normalize a customer-supplied phone number.
	 *
	 * @param string $phone_number Customer-supplied phone number.
	 *
	 * @throws InvalidArgumentException When the number is not a supported Kenyan mobile number.
	 */
	public function __construct( string $phone_number ) {
		$compact = preg_replace( '/[\s()-]+/', '', trim( $phone_number ) );

		if ( null === $compact ) {
			throw new InvalidArgumentException( 'The phone number could not be normalized.' );
		}

		if ( str_starts_with( $compact, '+' ) ) {
			$compact = substr( $compact, 1 );
		}

		if ( str_starts_with( $compact, '0' ) ) {
			$compact = '254' . substr( $compact, 1 );
		}

		if ( 1 !== preg_match( '/^254(?:7|1)\d{8}$/', $compact ) ) {
			throw new InvalidArgumentException( 'Enter a valid Safaricom number.' );
		}

		$this->value = $compact;
	}

	/**
	 * Return the canonical 254-prefixed value.
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Return a value safe for operational logs.
	 */
	public function masked(): string {
		return substr( $this->value, 0, 5 ) . '*****' . substr( $this->value, -2 );
	}
}
