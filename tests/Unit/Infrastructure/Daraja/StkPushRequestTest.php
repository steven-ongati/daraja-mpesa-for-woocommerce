<?php
/**
 * STK Push request tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Infrastructure\Daraja;

use DarajaMpesa\Domain\KenyanPhoneNumber;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Infrastructure\Daraja\StkPushRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects provider callback and text constraints.
 */
final class StkPushRequestTest extends TestCase {
	/**
	 * A bounded HTTPS request exposes validated values.
	 */
	public function test_accepts_validated_payment_context(): void {
		$request = new StkPushRequest(
			new KenyanPhoneNumber( '0712345678' ),
			new KesAmount( 2500 ),
			'https://shop.example/wp-json/daraja/callback/token',
			'Order-123',
			'Order payment'
		);

		self::assertSame( '254712345678', $request->phone_number()->value() );
		self::assertSame( 2500, $request->amount()->value() );
		self::assertSame( 'Order-123', $request->account_reference() );
	}

	/**
	 * Unsafe or unsupported provider fields are rejected.
	 *
	 * @param string $callback_url     Provider callback URL.
	 * @param string $account_reference Merchant order reference.
	 * @param string $description      Transaction description.
	 */
	#[DataProvider( 'invalid_request_fields' )]
	public function test_rejects_invalid_provider_fields(
		string $callback_url,
		string $account_reference,
		string $description
	): void {
		$this->expectException( InvalidArgumentException::class );

		new StkPushRequest(
			new KenyanPhoneNumber( '0712345678' ),
			new KesAmount( 2500 ),
			$callback_url,
			$account_reference,
			$description
		);
	}

	/**
	 * Unsupported provider fields.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function invalid_request_fields(): array {
		return array(
			'insecure callback' => array( 'http://shop.example/callback', 'Order-123', 'Payment' ),
			'missing host'      => array( 'https:///callback', 'Order-123', 'Payment' ),
			'long reference'    => array( 'https://shop.example/callback', 'Order-1234567', 'Payment' ),
			'unsafe reference'  => array( 'https://shop.example/callback', 'Order 123', 'Payment' ),
			'long description'  => array( 'https://shop.example/callback', 'Order-123', 'Order payment!' ),
		);
	}
}
