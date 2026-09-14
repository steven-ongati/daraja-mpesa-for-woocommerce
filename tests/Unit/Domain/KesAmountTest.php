<?php
/**
 * Kenya shilling amount tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Domain;

use DarajaMpesa\Domain\KesAmount;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects exact-amount payment validation.
 */
final class KesAmountTest extends TestCase {
	/**
	 * Whole-shilling order totals are accepted.
	 *
	 * @param string|int $input    WooCommerce order total.
	 * @param int        $expected Daraja integer amount.
	 */
	#[DataProvider( 'valid_amounts' )]
	public function test_accepts_whole_shilling_amounts( string|int $input, int $expected ): void {
		self::assertSame( $expected, ( new KesAmount( $input ) )->value() );
	}

	/**
	 * Supported whole-shilling values.
	 *
	 * @return array<string, array{string|int, int}>
	 */
	public static function valid_amounts(): array {
		return array(
			'integer'         => array( 1, 1 ),
			'numeric string'  => array( '2500', 2500 ),
			'decimal display' => array( '2500.00', 2500 ),
			'leading zeroes'  => array( '00025', 25 ),
		);
	}

	/**
	 * Fractional or nonpositive totals are rejected instead of rounded.
	 *
	 * @param string|int $input Unsupported WooCommerce order total.
	 */
	#[DataProvider( 'invalid_amounts' )]
	public function test_rejects_unsupported_amounts( string|int $input ): void {
		$this->expectException( InvalidArgumentException::class );

		new KesAmount( $input );
	}

	/**
	 * Unsupported order totals.
	 *
	 * @return array<string, array{string|int}>
	 */
	public static function invalid_amounts(): array {
		return array(
			'zero'       => array( 0 ),
			'negative'   => array( -1 ),
			'fractional' => array( '1.50' ),
			'currency'   => array( 'KES 100' ),
			'empty'      => array( '' ),
			'overflow'   => array( '999999999999999999999999999999' ),
		);
	}

	/**
	 * Provider evidence must equal the order total exactly.
	 */
	public function test_matches_provider_evidence_without_rounding(): void {
		$amount = new KesAmount( '2500.00' );

		self::assertTrue( $amount->matches_provider_value( 2500 ) );
		self::assertTrue( $amount->matches_provider_value( 2500.0 ) );
		self::assertTrue( $amount->matches_provider_value( '2500.00' ) );
		self::assertFalse( $amount->matches_provider_value( 2500.5 ) );
		self::assertFalse( $amount->matches_provider_value( '2501' ) );
	}
}
