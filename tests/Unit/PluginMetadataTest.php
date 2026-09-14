<?php
/**
 * Plugin metadata contract tests.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Protects the public WordPress plugin metadata contract.
 */
final class PluginMetadataTest extends TestCase {
	/**
	 * Main plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Locate the main plugin file.
	 */
	protected function setUp(): void {
		$this->plugin_file = dirname( __DIR__, 2 ) . '/daraja-mpesa-for-woocommerce.php';
	}

	/**
	 * The distributable identifies its owner and license.
	 */
	public function test_plugin_identity_and_license_are_stable(): void {
		$contents = file_get_contents( $this->plugin_file );

		self::assertIsString( $contents );
		self::assertStringContainsString(
			'Plugin Name:       Daraja M-Pesa Gateway for WooCommerce',
			$contents
		);
		self::assertStringContainsString( 'Author:            Steven Ongati Moriasi', $contents );
		self::assertStringContainsString( 'License:           GPL-2.0-or-later', $contents );
		self::assertStringContainsString(
			'Text Domain:       daraja-mpesa-for-woocommerce',
			$contents
		);
	}

	/**
	 * The public compatibility floor matches the source language level.
	 */
	public function test_declared_platform_floor_matches_tooling(): void {
		$contents = file_get_contents( $this->plugin_file );

		self::assertIsString( $contents );
		self::assertStringContainsString( 'Requires PHP:      8.1', $contents );
		self::assertStringContainsString( 'Requires at least: 6.8', $contents );
		self::assertStringContainsString( 'WC requires at least: 10.3', $contents );
	}
}
