<?php
/**
 * Callback payload parser tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\CallbackPayloadParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Protects strict extraction of provider callback evidence.
 */
final class CallbackPayloadParserTest extends TestCase {
	/**
	 * Extracts exact success evidence from unordered metadata.
	 */
	public function test_parses_success_evidence(): void {
		$payload = ( new CallbackPayloadParser() )->parse(
			array(
				'Body' => array(
					'stkCallback' => array(
						'MerchantRequestID' => 'merchant_123',
						'CheckoutRequestID' => 'checkout_123',
						'ResultCode'        => 0,
						'CallbackMetadata'  => array(
							'Item' => array(
								array(
									'Name'  => 'PhoneNumber',
									'Value' => 254712345678,
								),
								array(
									'Name'  => 'Amount',
									'Value' => 1250.0,
								),
								array(
									'Name'  => 'MpesaReceiptNumber',
									'Value' => 'RKT123ABC',
								),
							),
						),
					),
				),
			)
		);

		self::assertSame( '0', $payload->result_code() );
		self::assertSame( 1250, $payload->amount()?->value() );
		self::assertSame( '254712345678', $payload->phone_number()?->value() );
		self::assertSame( 'RKT123ABC', $payload->receipt_number() );
	}

	/**
	 * Rejects fractional callback amounts.
	 */
	public function test_rejects_fractional_amount(): void {
		$this->expectException( InvalidArgumentException::class );

		( new CallbackPayloadParser() )->parse(
			array(
				'Body' => array(
					'stkCallback' => array(
						'MerchantRequestID' => 'merchant_123',
						'CheckoutRequestID' => 'checkout_123',
						'ResultCode'        => 0,
						'CallbackMetadata'  => array(
							'Item' => array(
								array(
									'Name'  => 'Amount',
									'Value' => 1250.5,
								),
							),
						),
					),
				),
			)
		);
	}
}
