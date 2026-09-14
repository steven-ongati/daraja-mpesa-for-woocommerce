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
use DarajaMpesa\Application\CustomerPaymentStatusReader;
use DarajaMpesa\Application\ManualPaymentVerifier;
use DarajaMpesa\Application\PaymentInitiator;
use DarajaMpesa\Application\PaymentReviewMarker;
use DarajaMpesa\Application\PaymentRetryPolicy;
use DarajaMpesa\Application\PaymentStatusPoller;
use DarajaMpesa\Infrastructure\Daraja\AccessTokenProvider;
use DarajaMpesa\Infrastructure\Daraja\Configuration;
use DarajaMpesa\Infrastructure\Daraja\DarajaClient;
use DarajaMpesa\Infrastructure\Daraja\WordPressTokenStore;
use DarajaMpesa\Infrastructure\Http\WordPressHttpTransport;
use DarajaMpesa\Infrastructure\Logging\LogContextRedactor;
use DarajaMpesa\Infrastructure\Logging\WooCommercePaymentLogger;
use DarajaMpesa\Infrastructure\Persistence\WordPressPaymentAttemptRepository;
use DarajaMpesa\Infrastructure\Persistence\WordPressManualVerificationAudit;
use DarajaMpesa\Infrastructure\Rest\CallbackController;
use DarajaMpesa\Infrastructure\Rest\CustomerPaymentStatusController;
use DarajaMpesa\Infrastructure\Scheduling\ActionSchedulerPaymentPollScheduler;
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
	 * Create the customer-safe local status controller.
	 */
	public function customer_status_controller(): CustomerPaymentStatusController {
		return new CustomerPaymentStatusController(
			new CustomerPaymentStatusReader( $this->repository() )
		);
	}

	/**
	 * Create a configured status poller.
	 *
	 * @param Configuration $configuration Validated merchant configuration.
	 */
	public function payment_poller( Configuration $configuration ): PaymentStatusPoller {
		$logger = new WooCommercePaymentLogger( new LogContextRedactor() );

		return new PaymentStatusPoller(
			$this->repository(),
			$this->daraja( $configuration ),
			new ActionSchedulerPaymentPollScheduler(),
			$logger
		);
	}

	/**
	 * Create the callback-less success review marker.
	 */
	public function review_marker(): PaymentReviewMarker {
		return new PaymentReviewMarker( $this->repository() );
	}

	/**
	 * Create the order retry policy.
	 */
	public function payment_retry_policy(): PaymentRetryPolicy {
		return new PaymentRetryPolicy( $this->repository() );
	}

	/**
	 * Create an audited manual payment verifier.
	 *
	 * @param Configuration $configuration Validated merchant configuration.
	 */
	public function manual_payment_verifier( Configuration $configuration ): ManualPaymentVerifier {
		$clock  = new SystemClock();
		$logger = new WooCommercePaymentLogger( new LogContextRedactor() );

		return new ManualPaymentVerifier(
			$this->repository(),
			$this->daraja( $configuration ),
			new WooCommerceOrderPaymentCompleter(),
			$this->manual_audit( $clock ),
			$logger
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
		$repository = $this->repository();
		$logger     = new WooCommercePaymentLogger( new LogContextRedactor() );
		$daraja     = $this->daraja( $configuration );

		return new PaymentInitiator(
			$repository,
			$daraja,
			$logger,
			new ActionSchedulerPaymentPollScheduler(),
			new WordPressAttemptIdGenerator(),
			new CallbackAddress(),
			rest_url( 'daraja-mpesa/v1/callback' ),
			wp_salt( 'auth' )
		);
	}

	/**
	 * Create a configured Daraja client.
	 *
	 * @param Configuration $configuration Validated merchant configuration.
	 */
	private function daraja( Configuration $configuration ): DarajaClient {
		$clock     = new SystemClock();
		$transport = new WordPressHttpTransport();

		return new DarajaClient(
			$configuration,
			new AccessTokenProvider(
				$configuration,
				$transport,
				new WordPressTokenStore()
			),
			$transport,
			$clock
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

	/**
	 * Create the immutable administrator audit repository.
	 *
	 * @param SystemClock $clock Runtime clock.
	 *
	 * @throws RuntimeException When WordPress database access is unavailable.
	 */
	private function manual_audit( SystemClock $clock ): WordPressManualVerificationAudit {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb ) {
			throw new RuntimeException( 'WordPress database access is unavailable.' );
		}

		return new WordPressManualVerificationAudit( $wpdb, $clock );
	}
}
