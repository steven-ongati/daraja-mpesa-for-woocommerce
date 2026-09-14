<?php
/**
 * Daraja configuration tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Infrastructure\Daraja;

use DarajaMpesa\Infrastructure\Daraja\Configuration;
use DarajaMpesa\Infrastructure\Daraja\Environment;
use DarajaMpesa\Infrastructure\Daraja\TransactionType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protects merchant configuration and trusted endpoint selection.
 */
final class ConfigurationTest extends TestCase {
	/**
	 * Sandbox and live hosts remain Safaricom-owned HTTPS origins.
	 */
	public function test_environment_selects_trusted_api_origin(): void {
		self::assertSame( 'https://sandbox.safaricom.co.ke', Environment::SANDBOX->base_url() );
		self::assertSame( 'https://api.safaricom.co.ke', Environment::LIVE->base_url() );
	}

	/**
	 * Merchant credentials produce OAuth and STK configuration.
	 */
	public function test_exposes_required_request_configuration(): void {
		$configuration = $this->configuration();

		self::assertSame( '174379', $configuration->shortcode() );
		self::assertSame( 'passkey', $configuration->passkey() );
		self::assertSame( TransactionType::PAYBILL, $configuration->transaction_type() );
		self::assertSame( 'Basic a2V5OnNlY3JldA==', $configuration->basic_authorization() );
		self::assertStringNotContainsString( 'key', $configuration->token_cache_key() );
		self::assertStringNotContainsString( 'secret', $configuration->token_cache_key() );
	}

	/**
	 * Invalid merchant configuration is rejected before a provider request.
	 *
	 * @param string $consumer_key    Daraja consumer key.
	 * @param string $consumer_secret Daraja consumer secret.
	 * @param string $shortcode       Merchant shortcode.
	 * @param string $passkey         M-Pesa Express passkey.
	 */
	#[DataProvider( 'invalid_configuration' )]
	public function test_rejects_invalid_configuration(
		string $consumer_key,
		string $consumer_secret,
		string $shortcode,
		string $passkey
	): void {
		$this->expectException( InvalidArgumentException::class );

		new Configuration(
			Environment::SANDBOX,
			$consumer_key,
			$consumer_secret,
			$shortcode,
			$passkey,
			TransactionType::PAYBILL
		);
	}

	/**
	 * Invalid credential sets.
	 *
	 * @return array<string, array{string, string, string, string}>
	 */
	public static function invalid_configuration(): array {
		return array(
			'missing key'     => array( '', 'secret', '174379', 'passkey' ),
			'missing secret'  => array( 'key', '', '174379', 'passkey' ),
			'bad shortcode'   => array( 'key', 'secret', '17A379', 'passkey' ),
			'missing passkey' => array( 'key', 'secret', '174379', '' ),
		);
	}

	/**
	 * Return valid sandbox configuration.
	 */
	private function configuration(): Configuration {
		return new Configuration(
			Environment::SANDBOX,
			'key',
			'secret',
			'174379',
			'passkey',
			TransactionType::PAYBILL
		);
	}
}
