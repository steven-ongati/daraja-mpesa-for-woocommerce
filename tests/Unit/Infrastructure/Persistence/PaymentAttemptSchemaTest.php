<?php
/**
 * Payment-attempt schema tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Infrastructure\Persistence;

use DarajaMpesa\Infrastructure\Persistence\PaymentAttemptSchema;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Protects payment evidence storage constraints and indexes.
 */
final class PaymentAttemptSchemaTest extends TestCase {
	/**
	 * The schema enforces immutable correlation and receipt uniqueness.
	 */
	public function test_defines_required_unique_keys(): void {
		$sql = PaymentAttemptSchema::sql( 'wp_daraja_mpesa_attempts', 'DEFAULT CHARACTER SET utf8mb4' );

		self::assertStringContainsString( 'UNIQUE KEY attempt_id (attempt_id)', $sql );
		self::assertStringContainsString( 'UNIQUE KEY checkout_request_id (checkout_request_id)', $sql );
		self::assertStringContainsString( 'UNIQUE KEY receipt_number (receipt_number)', $sql );
		self::assertStringContainsString( 'KEY order_id (order_id)', $sql );
		self::assertStringContainsString( 'version bigint(20) unsigned NOT NULL DEFAULT 1', $sql );
	}

	/**
	 * The table contains no raw phone or callback payload column.
	 */
	public function test_stores_only_a_phone_hash(): void {
		$sql = PaymentAttemptSchema::sql( 'wp_daraja_mpesa_attempts', '' );

		self::assertStringContainsString( 'phone_hash char(64) NOT NULL', $sql );
		self::assertStringNotContainsString( "\nphone ", $sql );
		self::assertStringNotContainsString( 'callback_payload', $sql );
	}

	/**
	 * Manual review is append-only and does not retain raw receipts.
	 */
	public function test_defines_privacy_bounded_manual_audit(): void {
		$sql = PaymentAttemptSchema::audit_sql(
			'wp_daraja_mpesa_manual_audit',
			'DEFAULT CHARACTER SET utf8mb4'
		);

		self::assertStringContainsString( 'operator_id bigint(20) unsigned NOT NULL', $sql );
		self::assertStringContainsString( 'receipt_hash char(64) NOT NULL', $sql );
		self::assertStringContainsString( 'evidence_source varchar(32) NOT NULL', $sql );
		self::assertStringContainsString( 'KEY attempt_id (attempt_id)', $sql );
		self::assertStringNotContainsString( "\nreceipt_number ", $sql );
	}

	/**
	 * Unsafe database prefixes cannot reach SQL construction.
	 */
	public function test_rejects_unsafe_database_prefix(): void {
		$this->expectException( InvalidArgumentException::class );

		PaymentAttemptSchema::table_name( 'wp_; DROP TABLE users' );
	}
}
