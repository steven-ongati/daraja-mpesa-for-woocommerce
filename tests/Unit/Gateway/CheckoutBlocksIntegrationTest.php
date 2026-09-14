<?php
/**
 * Checkout Blocks integration tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;

/**
 * Protects the dependency-free browser registration contract.
 */
final class CheckoutBlocksIntegrationTest extends TestCase {
	/**
	 * The browser asset submits the phone through payment method data.
	 */
	public function test_browser_asset_registers_payment_method_data(): void {
		$script = file_get_contents( dirname( __DIR__, 3 ) . '/assets/js/checkout-blocks.js' );

		self::assertIsString( $script );
		self::assertStringContainsString( "name: 'daraja_mpesa'", $script );
		self::assertStringContainsString( 'paymentMethodData', $script );
		self::assertStringContainsString( 'daraja_mpesa_phone: phone', $script );
		self::assertStringContainsString( 'onPaymentSetup', $script );
	}
}
