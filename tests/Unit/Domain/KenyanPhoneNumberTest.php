<?php
/**
 * Kenyan phone number tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Domain;

use DarajaMpesa\Domain\KenyanPhoneNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects phone normalization sent to Daraja.
 */
final class KenyanPhoneNumberTest extends TestCase {
	/**
	 * Supported customer input formats.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function valid_phone_numbers(): array {
		return array(
			'local Safaricom format' => array( '0712 345 678', '254712345678' ),
			'local 01 format'        => array( '0112-345-678', '254112345678' ),
			'international format'   => array( '+254712345678', '254712345678' ),
			'canonical format'       => array( '254112345678', '254112345678' ),
		);
	}

	/**
	 * Accepted formats normalize to the Daraja representation.
	 *
	 * @param string $input    Customer-supplied value.
	 * @param string $expected Canonical Daraja value.
	 */
	#[DataProvider( 'valid_phone_numbers' )]
	public function test_normalizes_supported_formats( string $input, string $expected ): void {
		$phone_number = new KenyanPhoneNumber( $input );

		self::assertSame( $expected, $phone_number->value() );
		self::assertStringNotContainsString( substr( $expected, 5, 5 ), $phone_number->masked() );
	}

	/**
	 * Unsupported numbers never reach the provider.
	 *
	 * @param string $input Invalid customer-supplied value.
	 */
	#[DataProvider( 'invalid_phone_numbers' )]
	public function test_rejects_invalid_numbers( string $input ): void {
		$this->expectException( InvalidArgumentException::class );

		new KenyanPhoneNumber( $input );
	}

	/**
	 * Invalid customer input.
	 *
	 * @return array<string, array{string}>
	 */
	public static function invalid_phone_numbers(): array {
		return array(
			'empty'               => array( '' ),
			'too short'           => array( '071234567' ),
			'non-Kenyan prefix'   => array( '+255712345678' ),
			'unsupported network' => array( '254912345678' ),
			'letters'             => array( '2547ABC45678' ),
		);
	}
}
