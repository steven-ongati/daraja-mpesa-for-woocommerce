<?php
/**
 * WordPress manual-verification audit tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Infrastructure\Persistence;

use DarajaMpesa\Application\ManualVerificationRequest;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Infrastructure\Persistence\WordPressManualVerificationAudit;
use DarajaMpesa\Tests\Doubles\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use wpdb;

/**
 * Protects audit attribution and receipt privacy.
 */
final class WordPressManualVerificationAuditTest extends TestCase {
	/**
	 * Audit rows retain operator rationale but hash receipt evidence.
	 */
	public function test_appends_privacy_bounded_audit_record(): void {
		$database = new wpdb();
		$audit    = new WordPressManualVerificationAudit(
			$database,
			new FixedClock( new DateTimeImmutable( '2026-01-01T03:00:00+03:00' ) )
		);
		$request  = new ManualVerificationRequest(
			'123e4567-e89b-12d3-a456-426614174000',
			123,
			7,
			new KesAmount( 2500 ),
			'ABC123XYZ9',
			'merchant_statement',
			'Matched the receipt and amount in the merchant settlement statement.'
		);

		$audit->record( $request, 'settled' );

		self::assertSame( 7, $database->inserted_data['operator_id'] );
		self::assertSame( 2500, $database->inserted_data['amount'] );
		self::assertSame( hash( 'sha256', 'ABC123XYZ9' ), $database->inserted_data['receipt_hash'] );
		self::assertArrayNotHasKey( 'receipt', $database->inserted_data );
		self::assertSame( '2026-01-01 00:00:00', $database->inserted_data['created_at_gmt'] );
	}
}
