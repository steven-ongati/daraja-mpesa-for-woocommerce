<?php
/**
 * Customer-facing M-Pesa status controller.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Rest;

use DarajaMpesa\Application\CustomerPaymentStatus;
use DarajaMpesa\Application\CustomerPaymentStatusReader;
use WC_Order;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves and renders read-only, order-authorized payment status.
 */
final class CustomerPaymentStatusController {
	/**
	 * Configure the customer status boundary.
	 *
	 * @param CustomerPaymentStatusReader $reader Read-only status projection.
	 */
	public function __construct(
		private readonly CustomerPaymentStatusReader $reader
	) {
	}

	/**
	 * Register customer status hooks.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render' ) );
	}

	/**
	 * Register the order-authorized status route.
	 */
	public function register_route(): void {
		register_rest_route(
			'daraja-mpesa/v1',
			'/orders/(?P<order_id>\d+)/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * Determine whether the current customer can view the order.
	 *
	 * @param WP_REST_Request $request WordPress REST request.
	 */
	public function can_view( WP_REST_Request $request ): bool {
		$order = $this->order_from_request( $request );
		if ( null === $order || 'daraja_mpesa' !== $order->get_payment_method() ) {
			return false;
		}

		if (
			current_user_can( 'manage_woocommerce' )
			|| (
				get_current_user_id() > 0
				&& get_current_user_id() === $order->get_customer_id()
			)
		) {
			return true;
		}

		$order_key = $request->get_param( 'key' );

		return is_string( $order_key )
			&& '' !== $order_key
			&& hash_equals( $order->get_order_key(), $order_key );
	}

	/**
	 * Return current local status without querying or settling through the browser.
	 *
	 * @param WP_REST_Request $request WordPress REST request.
	 */
	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$order = $this->order_from_request( $request );
		if ( null === $order ) {
			return $this->response( array( 'code' => 'unavailable' ), 404 );
		}

		$status = $this->status( $order );
		if ( null === $status ) {
			return $this->response( array( 'code' => 'unavailable' ), 404 );
		}

		return $this->response(
			array(
				'code'        => $status->code(),
				'message'     => $status->message(),
				'refreshable' => $status->is_refreshable(),
			)
		);
	}

	/**
	 * Render current status on authorized WooCommerce order pages.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function render( WC_Order $order ): void {
		if ( 'daraja_mpesa' !== $order->get_payment_method() ) {
			return;
		}

		$status = $this->status( $order );
		if ( null === $status ) {
			return;
		}

		$endpoint = add_query_arg(
			array( 'key' => $order->get_order_key() ),
			rest_url( 'daraja-mpesa/v1/orders/' . $order->get_id() . '/status' )
		);

		echo '<section class="daraja-mpesa-customer-status" data-status-url="' . esc_url( $endpoint ) . '">';
		echo '<h2>' . esc_html__( 'M-Pesa payment status', 'daraja-mpesa-for-woocommerce' ) . '</h2>';
		echo '<p role="status" data-daraja-status="' . esc_attr( $status->code() ) . '">'
			. esc_html( $status->message() )
			. '</p>';
		if ( $status->is_refreshable() ) {
			echo '<button type="button" class="button" data-daraja-refresh>'
				. esc_html__( 'Refresh payment status', 'daraja-mpesa-for-woocommerce' )
				. '</button>';
		}
		echo '</section>';

		$script_url = plugins_url( 'assets/js/customer-payment-status.js', DARAJA_MPESA_FILE );
		if ( '' !== $script_url ) {
			wp_enqueue_script(
				'daraja-mpesa-customer-status',
				$script_url,
				array(),
				DARAJA_MPESA_VERSION,
				true
			);
		}
	}

	/**
	 * Read status using the order-bound attempt identifier.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function status( WC_Order $order ): ?CustomerPaymentStatus {
		$attempt_id = $order->get_meta( '_daraja_mpesa_attempt_id', true );
		if ( ! is_string( $attempt_id ) || 1 !== preg_match( '/^[a-f0-9-]{36}$/', $attempt_id ) ) {
			return null;
		}

		return $this->reader->read( $attempt_id, $order->get_id(), $order->is_paid() );
	}

	/**
	 * Load one order from the bounded route parameter.
	 *
	 * @param WP_REST_Request $request WordPress REST request.
	 */
	private function order_from_request( WP_REST_Request $request ): ?WC_Order {
		$order_id = $request->get_param( 'order_id' );
		$order    = is_numeric( $order_id ) ? wc_get_order( absint( $order_id ) ) : false;

		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Build a private, non-cacheable customer response.
	 *
	 * @param array<string, bool|string> $data   Bounded status data.
	 * @param int                        $status HTTP response status.
	 */
	private function response( array $data, int $status = 200 ): WP_REST_Response {
		$response = new WP_REST_Response( $data, $status );
		$response->header( 'Cache-Control', 'no-store, private' );

		return $response;
	}
}
