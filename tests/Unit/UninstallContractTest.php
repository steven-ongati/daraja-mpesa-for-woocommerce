<?php
/**
 * Plugin uninstall contract tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Protects cleanup of plugin-owned payment data and configuration.
 */
final class UninstallContractTest extends TestCase {
	/**
	 * Uninstall removes settings, work, and plugin tables.
	 */
	public function test_uninstall_cleans_plugin_owned_resources(): void {
		$contents = file_get_contents( dirname( __DIR__, 2 ) . '/uninstall.php' );

		self::assertIsString( $contents );
		self::assertStringContainsString(
			"delete_option( 'woocommerce_daraja_mpesa_settings' )",
			$contents
		);
		self::assertStringContainsString( 'daraja_mpesa_poll_attempt', $contents );
		self::assertStringContainsString( 'daraja_mpesa_review_attempt', $contents );
		self::assertStringContainsString( 'daraja_mpesa_attempts', $contents );
		self::assertStringContainsString( 'daraja_mpesa_manual_audit', $contents );
	}
}
