<?php
/**
 * Payment log redaction tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Infrastructure\Logging;

use DarajaMpesa\Infrastructure\Logging\LogContextRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Protects credentials and customer evidence from operational logs.
 */
final class LogContextRedactorTest extends TestCase {
	/**
	 * Credentials and payment evidence are always replaced.
	 */
	public function test_redacts_sensitive_context_by_key(): void {
		$redacted = ( new LogContextRedactor() )->redact(
			array(
				'consumer_secret'     => 'secret',
				'authorization'       => 'Bearer token',
				'phone_number'        => '254712345678',
				'mpesa_receipt'       => 'ABC123',
				'callback_payload'    => '{"Body":"raw"}',
				'checkout_request_id' => 'ws_CO_12345678',
				'order_id'            => 123,
			)
		);

		self::assertSame( '[redacted]', $redacted['consumer_secret'] );
		self::assertSame( '[redacted]', $redacted['authorization'] );
		self::assertSame( '[redacted]', $redacted['phone_number'] );
		self::assertSame( '[redacted]', $redacted['mpesa_receipt'] );
		self::assertSame( '[redacted]', $redacted['callback_payload'] );
		self::assertSame( 'ws_CO_12345678', $redacted['checkout_request_id'] );
		self::assertSame( 123, $redacted['order_id'] );
	}

	/**
	 * Retained text is stripped of control characters and bounded.
	 */
	public function test_bounds_safe_operational_context(): void {
		$redacted = ( new LogContextRedactor() )->redact(
			array(
				'error_code' => "provider_error\n" . str_repeat( 'x', 200 ),
			)
		);

		self::assertIsString( $redacted['error_code'] );
		self::assertStringNotContainsString( "\n", $redacted['error_code'] );
		self::assertSame( 128, strlen( $redacted['error_code'] ) );
	}
}
