<?php
/**
 * WooCommerce gateway registration.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Gateway;

/**
 * Adds the Daraja gateway to WooCommerce.
 */
final class GatewayRegistrar {
	/**
	 * Register the payment gateway class.
	 *
	 * @param array<class-string> $gateways Registered gateway classes.
	 *
	 * @return array<class-string>
	 */
	public static function register( array $gateways ): array {
		$gateways[] = DarajaMpesaGateway::class;

		return $gateways;
	}
}
