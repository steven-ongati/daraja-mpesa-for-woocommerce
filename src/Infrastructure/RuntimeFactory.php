<?php
/**
 * WordPress runtime dependency factory.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure;

use DarajaMpesa\Application\CallbackAddress;
use DarajaMpesa\Application\PaymentInitiator;
use DarajaMpesa\Infrastructure\Daraja\AccessTokenProvider;
use DarajaMpesa\Infrastructure\Daraja\Configuration;
use DarajaMpesa\Infrastructure\Daraja\DarajaClient;
use DarajaMpesa\Infrastructure\Daraja\WordPressTokenStore;
use DarajaMpesa\Infrastructure\Http\WordPressHttpTransport;
use DarajaMpesa\Infrastructure\Logging\LogContextRedactor;
use DarajaMpesa\Infrastructure\Logging\WooCommercePaymentLogger;
use DarajaMpesa\Infrastructure\Persistence\WordPressPaymentAttemptRepository;
use DarajaMpesa\Support\SystemClock;
use DarajaMpesa\Support\WordPressAttemptIdGenerator;
use RuntimeException;
use wpdb;

/**
 * Composes WordPress, WooCommerce, and Daraja adapters at the boundary.
 */
final class RuntimeFactory {
	/**
	 * Create a configured payment initiator.
	 *
	 * @param Configuration $configuration Validated merchant configuration.
	 *
	 * @throws RuntimeException When WordPress database access is unavailable.
	 */
	public function payment_initiator( Configuration $configuration ): PaymentInitiator {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			throw new RuntimeException( 'WordPress database access is unavailable.' );
		}

		$clock      = new SystemClock();
		$transport  = new WordPressHttpTransport();
		$repository = new WordPressPaymentAttemptRepository( $wpdb, $clock );
		$logger     = new WooCommercePaymentLogger( new LogContextRedactor() );
		$daraja     = new DarajaClient(
			$configuration,
			new AccessTokenProvider(
				$configuration,
				$transport,
				new WordPressTokenStore()
			),
			$transport,
			$clock
		);

		return new PaymentInitiator(
			$repository,
			$daraja,
			$logger,
			new WordPressAttemptIdGenerator(),
			new CallbackAddress(),
			rest_url( 'daraja-mpesa/v1/callback' ),
			wp_salt( 'auth' )
		);
	}
}
