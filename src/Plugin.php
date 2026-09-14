<?php
/**
 * Plugin lifecycle orchestration.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use DarajaMpesa\Infrastructure\Persistence\PaymentAttemptSchema;
use DarajaMpesa\Infrastructure\Requirements;

/**
 * Coordinates the plugin lifecycle.
 */
final class Plugin {
	/**
	 * Register plugin hooks.
	 */
	public static function boot(): void {
		add_action( 'before_woocommerce_init', array( self::class, 'declare_compatibility' ) );
		add_action( 'plugins_loaded', array( self::class, 'load' ), 20 );
	}

	/**
	 * Prepare persistent plugin resources.
	 */
	public static function activate(): void {
		PaymentAttemptSchema::install();
		update_option( 'daraja_mpesa_version', DARAJA_MPESA_VERSION, false );
	}

	/**
	 * Declare compatibility with WooCommerce storage features.
	 */
	public static function declare_compatibility(): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', DARAJA_MPESA_FILE, true );
		}
	}

	/**
	 * Start the plugin after dependency validation.
	 */
	public static function load(): void {
		$requirements = Requirements::evaluate();

		if ( ! $requirements->is_satisfied() ) {
			add_action( 'admin_notices', array( $requirements, 'render_admin_notice' ) );
			return;
		}

		PaymentAttemptSchema::maybe_upgrade();

		load_plugin_textdomain(
			'daraja-mpesa-for-woocommerce',
			false,
			dirname( plugin_basename( DARAJA_MPESA_FILE ) ) . '/languages'
		);

		/**
		 * Fires after the plugin dependencies have been validated.
		 */
		do_action( 'daraja_mpesa_loaded' );
	}
}
