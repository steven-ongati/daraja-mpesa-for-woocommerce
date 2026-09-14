<?php
/**
 * Daraja configuration factory.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use DarajaMpesa\Infrastructure\Daraja\Configuration;
use DarajaMpesa\Infrastructure\Daraja\Environment;
use DarajaMpesa\Infrastructure\Daraja\TransactionType;
use InvalidArgumentException;

/**
 * Converts sanitized gateway settings into validated Daraja configuration.
 */
final class ConfigurationFactory {
	/**
	 * Build configuration from WooCommerce gateway options.
	 *
	 * @param array<string, mixed> $settings Gateway settings.
	 *
	 * @throws InvalidArgumentException When a setting is missing or invalid.
	 */
	public function from_settings( array $settings ): Configuration {
		$environment = Environment::tryFrom( $this->string( $settings, 'environment' ) );
		$type        = TransactionType::tryFrom( $this->string( $settings, 'transaction_type' ) );

		if ( null === $environment || null === $type ) {
			throw new InvalidArgumentException( 'The Daraja environment or merchant account type is invalid.' );
		}

		return new Configuration(
			$environment,
			$this->string( $settings, 'consumer_key' ),
			$this->string( $settings, 'consumer_secret' ),
			$this->string( $settings, 'shortcode' ),
			$this->string( $settings, 'passkey' ),
			$type
		);
	}

	/**
	 * Read one required scalar setting.
	 *
	 * @param array<string, mixed> $settings Gateway settings.
	 * @param string               $key      Setting key.
	 *
	 * @throws InvalidArgumentException When a required setting is not scalar.
	 */
	private function string( array $settings, string $key ): string {
		$value = $settings[ $key ] ?? null;

		if ( ! is_scalar( $value ) ) {
			throw new InvalidArgumentException( 'A required Daraja setting is missing.' );
		}

		return trim( (string) $value );
	}
}
