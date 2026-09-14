<?php
/**
 * Configuration-factory tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Application;

use DarajaMpesa\Application\ConfigurationFactory;
use DarajaMpesa\Infrastructure\Daraja\Environment;
use DarajaMpesa\Infrastructure\Daraja\TransactionType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Protects conversion of merchant settings into validated configuration.
 */
final class ConfigurationFactoryTest extends TestCase {
	/**
	 * Builds sandbox configuration from complete gateway settings.
	 */
	public function test_builds_valid_configuration(): void {
		$configuration = ( new ConfigurationFactory() )->from_settings(
			array(
				'environment'      => 'sandbox',
				'consumer_key'     => 'key',
				'consumer_secret'  => 'secret',
				'shortcode'        => '174379',
				'passkey'          => 'passkey',
				'transaction_type' => 'CustomerPayBillOnline',
			)
		);

		self::assertSame( Environment::SANDBOX, $configuration->environment() );
		self::assertSame( '174379', $configuration->shortcode() );
		self::assertSame( TransactionType::PAYBILL, $configuration->transaction_type() );
	}

	/**
	 * Rejects unsupported environment values.
	 */
	public function test_rejects_unknown_environment(): void {
		$this->expectException( InvalidArgumentException::class );

		( new ConfigurationFactory() )->from_settings(
			array(
				'environment'      => 'staging',
				'consumer_key'     => 'key',
				'consumer_secret'  => 'secret',
				'shortcode'        => '174379',
				'passkey'          => 'passkey',
				'transaction_type' => 'CustomerPayBillOnline',
			)
		);
	}
}
