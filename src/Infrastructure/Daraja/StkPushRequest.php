<?php
/**
 * Validated STK Push request.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Daraja;

use DarajaMpesa\Domain\KenyanPhoneNumber;
use DarajaMpesa\Domain\KesAmount;
use InvalidArgumentException;

/**
 * Carries bounded customer and order data into an STK Push.
 */
final class StkPushRequest {
	/**
	 * Configure a provider request.
	 *
	 * @param KenyanPhoneNumber $phone_number     Customer phone number.
	 * @param KesAmount         $amount           Exact order amount.
	 * @param string            $callback_url     Protected public callback URL.
	 * @param string            $account_reference Bounded merchant order reference.
	 * @param string            $description      Bounded transaction description.
	 *
	 * @throws InvalidArgumentException When callback or text fields violate Daraja constraints.
	 */
	public function __construct(
		private readonly KenyanPhoneNumber $phone_number,
		private readonly KesAmount $amount,
		private readonly string $callback_url,
		private readonly string $account_reference,
		private readonly string $description
	) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- This value object also runs outside WordPress in unit tests.
		$callback_parts = parse_url( $this->callback_url );

		if (
			! is_array( $callback_parts )
			|| 'https' !== ( $callback_parts['scheme'] ?? null )
			|| ! isset( $callback_parts['host'] )
		) {
			throw new InvalidArgumentException( 'The Daraja callback must use a public HTTPS URL.' );
		}

		if ( 1 !== preg_match( '/^[A-Za-z0-9._-]{1,12}$/', $this->account_reference ) ) {
			throw new InvalidArgumentException( 'The Daraja account reference must be 1 to 12 safe characters.' );
		}

		if ( '' === trim( $this->description ) || strlen( $this->description ) > 13 ) {
			throw new InvalidArgumentException( 'The Daraja transaction description must be 1 to 13 characters.' );
		}
	}

	/**
	 * Return the customer phone number.
	 */
	public function phone_number(): KenyanPhoneNumber {
		return $this->phone_number;
	}

	/**
	 * Return the exact order amount.
	 */
	public function amount(): KesAmount {
		return $this->amount;
	}

	/**
	 * Return the protected callback URL.
	 */
	public function callback_url(): string {
		return $this->callback_url;
	}

	/**
	 * Return the merchant order reference.
	 */
	public function account_reference(): string {
		return $this->account_reference;
	}

	/**
	 * Return the transaction description.
	 */
	public function description(): string {
		return $this->description;
	}
}
