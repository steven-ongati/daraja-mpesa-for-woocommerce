<?php
/**
 * Gateway registration test.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Gateway;

use DarajaMpesa\Gateway\DarajaMpesaGateway;
use DarajaMpesa\Gateway\GatewayRegistrar;
use PHPUnit\Framework\TestCase;

/**
 * Protects WooCommerce payment-gateway registration.
 */
final class GatewayRegistrarTest extends TestCase {
	/**
	 * Preserves existing gateways and appends Daraja M-Pesa.
	 */
	public function test_registers_gateway_class(): void {
		$gateways = GatewayRegistrar::register( array( 'ExistingGateway' ) );

		self::assertSame( array( 'ExistingGateway', DarajaMpesaGateway::class ), $gateways );
	}
}
