<?php
/**
 * Daraja callback REST controller.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Rest;

use DarajaMpesa\Application\CallbackAddress;
use DarajaMpesa\Application\CallbackConflict;
use DarajaMpesa\Application\CallbackPayloadParser;
use DarajaMpesa\Application\CallbackReconciler;
use DarajaMpesa\Infrastructure\Logging\PaymentLogger;
use InvalidArgumentException;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Authenticates and acknowledges provider callbacks without exposing internals.
 */
final class CallbackController {
	/**
	 * Configure callback processing.
	 *
	 * @param CallbackAddress       $address             Attempt-token verifier.
	 * @param CallbackPayloadParser $parser              Bounded payload parser.
	 * @param CallbackReconciler    $reconciler          Payment reconciler.
	 * @param PaymentLogger         $logger              Redacted payment logger.
	 * @param string                $installation_secret Installation HMAC secret.
	 */
	public function __construct(
		private readonly CallbackAddress $address,
		private readonly CallbackPayloadParser $parser,
		private readonly CallbackReconciler $reconciler,
		private readonly PaymentLogger $logger,
		private readonly string $installation_secret
	) {
	}

	/**
	 * Register the public provider callback route.
	 */
	public function register(): void {
		register_rest_route(
			'daraja-mpesa/v1',
			'/callback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Authenticate and reconcile one callback.
	 *
	 * @param WP_REST_Request $request WordPress REST request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$attempt_id = $request->get_param( 'attempt' );
		$token      = $request->get_param( 'token' );

		if (
			! is_string( $attempt_id )
			|| ! is_string( $token )
			|| ! $this->address->verify( $attempt_id, $token, $this->installation_secret )
		) {
			$this->logger->warning( 'payment.callback_authentication_failed' );
			return $this->response( 'rejected', 403 );
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return $this->response( 'invalid', 400 );
		}

		try {
			$this->reconciler->reconcile( $attempt_id, $this->parser->parse( $body ) );
		} catch ( InvalidArgumentException ) {
			$this->logger->warning(
				'payment.callback_invalid',
				array( 'attempt_id' => $attempt_id )
			);
			return $this->response( 'invalid', 400 );
		} catch ( CallbackConflict ) {
			$this->logger->warning(
				'payment.callback_conflict',
				array( 'attempt_id' => $attempt_id )
			);
			return $this->response( 'conflict', 409 );
			// @phpstan-ignore catch.neverThrown
		} catch ( RuntimeException ) {
			$this->logger->error(
				'payment.callback_processing_failed',
				array( 'attempt_id' => $attempt_id )
			);
			return $this->response( 'unavailable', 503 );
		}

		return $this->response( 'accepted', 200 );
	}

	/**
	 * Build a bounded provider acknowledgement.
	 *
	 * @param string $result Stable result name.
	 * @param int    $status HTTP response status.
	 */
	private function response( string $result, int $status ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ResultCode' => 200 === $status ? 0 : 1,
				'ResultDesc' => $result,
			),
			$status
		);
	}
}
