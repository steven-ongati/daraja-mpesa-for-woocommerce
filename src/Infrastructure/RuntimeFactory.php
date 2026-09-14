<?php
/**
 * WordPress runtime dependency factory.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure;

use DarajaMpesa\Application\CallbackAddress;
use DarajaMpesa\Application\CallbackPayloadParser;
use DarajaMpesa\Application\CallbackReconciler;
use DarajaMpesa\Application\PaymentInitiator;
use DarajaMpesa\Infrastructure\Daraja\AccessTokenProvider;
use DarajaMpesa\Infrastructure\Daraja\Configuration;
use DarajaMpesa\Infrastructure\Daraja\DarajaClient;
use DarajaMpesa\Infrastructure\Daraja\WordPressTokenStore;
use DarajaMpesa\Infrastructure\Http\WordPressHttpTransport;
use DarajaMpesa\Infrastructure\Logging\LogContextRedactor;
use DarajaMpesa\Infrastructure\Logging\WooCommercePaymentLogger;
use DarajaMpesa\Infrastructure\Persistence\WordPressPaymentAttemptRepository;
use DarajaMpesa\Infrastructure\Rest\CallbackController;
use DarajaMpesa\Support\SystemClock;
use DarajaMpesa\Support\WordPressAttemptIdGenerator;
use RuntimeException;
use wpdb;

/**
 * Composes WordPress, WooCommerce, and Daraja adapters at the boundary.
 */
final class RuntimeFactory {
	/**
	 * Create the authenticated callback controller.
	 *
	 * @throws RuntimeException When WordPress database access is unavailable.
	 */
	public function callback_controller(): CallbackController {
		$repository = $this->repository();
		$logger     = new WooCommercePaymentLogger( new LogContextRedactor() );
		$secret     = wp_salt( 'auth' );

		return new CallbackController(
			new CallbackAddress(),
			new CallbackPayloadParser(),
			new CallbackReconciler(
				$repository,
				new WooCommerceOrderPaymentCompleter(),
				$logger,
				$secret
			),
			$logger,
			$secret
		);
	}

	/**
	 * Create a configured payment initiator.
	 *
	 * @param Configuration $configuration Validated merchant configuration.
	 *
	 * @throws RuntimeException When WordPress database access is unavailable.
	 */
	public function payment_initiator( Configuration $configuration ): PaymentInitiator {
		$clock      = new SystemClock();
		$transport  = new WordPressHttpTransport();
		$repository = $this->repository();
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

	/**
	 * Create the durable payment-attempt repository.
	 *
	 * @throws RuntimeException When WordPress database access is unavailable.
	 */
	private function repository(): WordPressPaymentAttemptRepository {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			throw new RuntimeException( 'WordPress database access is unavailable.' );
		}

		return new WordPressPaymentAttemptRepository( $wpdb, new SystemClock() );
	}
}
