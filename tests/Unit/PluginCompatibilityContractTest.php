<?php
/**
 * WooCommerce compatibility contract tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Protects the declared WooCommerce feature compatibility.
 */
final class PluginCompatibilityContractTest extends TestCase {
	/**
	 * The plugin declares each integration it implements.
	 */
	public function test_supported_woocommerce_features_are_declared(): void {
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/src/Plugin.php' );

		self::assertIsString( $contents );
		self::assertStringContainsString(
			"declare_compatibility( 'custom_order_tables'",
			$contents
		);
		self::assertStringContainsString(
			"declare_compatibility( 'cart_checkout_blocks'",
			$contents
		);
	}
}
