<?php
/**
 * Callback-address tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\CallbackAddress;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Protects callback transport and attempt-bound authentication.
 */
final class CallbackAddressTest extends TestCase {
	/**
	 * Generates a verifiable token without exposing the secret.
	 */
	public function test_builds_attempt_bound_https_callback(): void {
		$address    = new CallbackAddress();
		$attempt_id = '123e4567-e89b-12d3-a456-426614174000';
		$url        = $address->build(
			'https://store.example/wp-json/daraja-mpesa/v1/callback',
			$attempt_id,
			'installation-secret'
		);

		self::assertStringContainsString( 'attempt=' . rawurlencode( $attempt_id ), $url );
		self::assertStringNotContainsString( 'installation-secret', $url );

		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		self::assertIsString( $query['token'] );
		self::assertTrue( $address->verify( $attempt_id, $query['token'], 'installation-secret' ) );
		self::assertFalse( $address->verify( $attempt_id, $query['token'], 'different-secret' ) );
	}

	/**
	 * Daraja callbacks cannot be configured over plaintext HTTP.
	 */
	public function test_rejects_insecure_callback_endpoint(): void {
		$this->expectException( InvalidArgumentException::class );

		( new CallbackAddress() )->build(
			'http://store.example/wp-json/daraja-mpesa/v1/callback',
			'123e4567-e89b-12d3-a456-426614174000',
			'installation-secret'
		);
	}
}
