<?php
/**
 * Plugin lifecycle orchestration.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use DarajaMpesa\Gateway\GatewayRegistrar;
use DarajaMpesa\Application\ConfigurationFactory;
use DarajaMpesa\Infrastructure\Admin\ManualVerificationController;
use DarajaMpesa\Infrastructure\RuntimeFactory;
use DarajaMpesa\Infrastructure\Scheduling\ActionSchedulerPaymentPollScheduler;
use InvalidArgumentException;
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
		add_action( 'rest_api_init', array( self::class, 'register_rest_routes' ) );
		add_action(
			ActionSchedulerPaymentPollScheduler::POLL_HOOK,
			array( self::class, 'poll_payment_attempt' )
		);
		add_action(
			ActionSchedulerPaymentPollScheduler::REVIEW_HOOK,
			array( self::class, 'review_payment_attempt' )
		);

		load_plugin_textdomain(
			'daraja-mpesa-for-woocommerce',
			false,
			dirname( plugin_basename( DARAJA_MPESA_FILE ) ) . '/languages'
		);
		add_filter( 'woocommerce_payment_gateways', array( GatewayRegistrar::class, 'register' ) );
		( new ManualVerificationController() )->register();

		/**
		 * Fires after the plugin dependencies have been validated.
		 */
		do_action( 'daraja_mpesa_loaded' );
	}

	/**
	 * Register provider-facing REST routes.
	 */
	public static function register_rest_routes(): void {
		( new RuntimeFactory() )->callback_controller()->register();
	}

	/**
	 * Query Daraja for one pending payment attempt.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 */
	public static function poll_payment_attempt( string $attempt_id ): void {
		$settings = get_option( 'woocommerce_daraja_mpesa_settings', array() );
		if ( ! is_array( $settings ) ) {
			return;
		}

		try {
			$configuration = ( new ConfigurationFactory() )->from_settings( $settings );
		} catch ( InvalidArgumentException ) {
			return;
		}

		( new RuntimeFactory() )->payment_poller( $configuration )->poll( $attempt_id );
	}

	/**
	 * Move callback-less provider success to administrator review.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 */
	public static function review_payment_attempt( string $attempt_id ): void {
		( new RuntimeFactory() )->review_marker()->mark( $attempt_id );
	}
}
