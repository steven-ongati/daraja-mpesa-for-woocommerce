<?php
/**
 * WooCommerce administrator manual-verification controls.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Admin;

use DarajaMpesa\Application\ConfigurationFactory;
use DarajaMpesa\Application\ManualVerificationRejected;
use DarajaMpesa\Application\ManualVerificationRequest;
use DarajaMpesa\Domain\KesAmount;
use DarajaMpesa\Infrastructure\RuntimeFactory;
use InvalidArgumentException;
use RuntimeException;
use WC_Order;

/**
 * Renders and processes capability-protected external evidence review.
 */
final class ManualVerificationController {
	private const ACTION       = 'daraja_mpesa_manual_verify';
	private const NONCE_ACTION = 'daraja_mpesa_manual_verification';

	/**
	 * Register WooCommerce administrator hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'process' ) );
	}

	/**
	 * Render the order-scoped verification form.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function render( WC_Order $order ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || $order->is_paid() ) {
			return;
		}

		$attempt_id = $order->get_meta( '_daraja_mpesa_attempt_id', true );
		if ( ! is_string( $attempt_id ) || 1 !== preg_match( '/^[a-f0-9-]{36}$/', $attempt_id ) ) {
			return;
		}

		$this->render_notice();

		echo '<div class="order_data_column">';
		echo '<h3>' . esc_html__( 'M-Pesa manual verification', 'daraja-mpesa-for-woocommerce' ) . '</h3>';
		echo '<p>' . esc_html__(
			'Use only after checking the receipt and amount in an independent Safaricom or merchant record. A fresh Daraja status query is required before settlement.',
			'daraja-mpesa-for-woocommerce'
		) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::NONCE_ACTION, '_daraja_mpesa_nonce' );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo '<input type="hidden" name="attempt_id" value="' . esc_attr( $attempt_id ) . '">';
		echo '<input type="hidden" name="order_id" value="' . esc_attr( (string) $order->get_id() ) . '">';
		echo '<p><label for="daraja_mpesa_manual_amount">'
			. esc_html__( 'Externally verified amount (KES)', 'daraja-mpesa-for-woocommerce' )
			. '</label><br><input id="daraja_mpesa_manual_amount" name="amount" type="number" min="1" step="1" required></p>';
		echo '<p><label for="daraja_mpesa_manual_receipt">'
			. esc_html__( 'M-Pesa receipt', 'daraja-mpesa-for-woocommerce' )
			. '</label><br><input id="daraja_mpesa_manual_receipt" name="receipt" type="text" minlength="6" maxlength="32" required></p>';
		echo '<p><label for="daraja_mpesa_evidence_source">'
			. esc_html__( 'Evidence source', 'daraja-mpesa-for-woocommerce' )
			. '</label><br><select id="daraja_mpesa_evidence_source" name="evidence_source" required>';
		echo '<option value="safaricom_portal">'
			. esc_html__( 'Safaricom merchant portal', 'daraja-mpesa-for-woocommerce' )
			. '</option>';
		echo '<option value="merchant_statement">'
			. esc_html__( 'Merchant settlement statement', 'daraja-mpesa-for-woocommerce' )
			. '</option>';
		echo '<option value="provider_support">'
			. esc_html__( 'Safaricom support confirmation', 'daraja-mpesa-for-woocommerce' )
			. '</option></select></p>';
		echo '<p><label for="daraja_mpesa_manual_reason">'
			. esc_html__( 'Verification reason', 'daraja-mpesa-for-woocommerce' )
			. '</label><br><textarea id="daraja_mpesa_manual_reason" name="reason" minlength="20" maxlength="500" required></textarea></p>';
		submit_button(
			__( 'Verify with Daraja and settle', 'daraja-mpesa-for-woocommerce' ),
			'secondary',
			'submit',
			false
		);
		echo '</form></div>';
	}

	// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing -- Validation exceptions are converted to bounded redirect outcomes.
	/**
	 * Process one administrator verification request.
	 */
	public function process(): void {
		// phpcs:enable Squiz.Commenting.FunctionCommentThrowTag.Missing
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die(
				esc_html__( 'You cannot verify WooCommerce payments.', 'daraja-mpesa-for-woocommerce' ),
				esc_html__( 'Payment verification denied', 'daraja-mpesa-for-woocommerce' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE_ACTION, '_daraja_mpesa_nonce' );

		$order_id   = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		$attempt_id = isset( $_POST['attempt_id'] )
			? sanitize_text_field( wp_unslash( $_POST['attempt_id'] ) )
			: '';
		$amount     = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : '';
		$receipt    = isset( $_POST['receipt'] ) ? sanitize_text_field( wp_unslash( $_POST['receipt'] ) ) : '';
		$source     = isset( $_POST['evidence_source'] ) ? sanitize_key( wp_unslash( $_POST['evidence_source'] ) ) : '';
		$reason     = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$outcome    = 'invalid_evidence';

		try {
			$request  = $this->request( $attempt_id, $order_id, $amount, $receipt, $source, $reason );
			$settings = get_option( 'woocommerce_daraja_mpesa_settings', array() );
			if ( ! is_array( $settings ) ) {
				throw new InvalidArgumentException( 'The gateway settings are invalid.' );
			}

			$configuration = ( new ConfigurationFactory() )->from_settings( $settings );
			( new RuntimeFactory() )->manual_payment_verifier( $configuration )->verify( $request );
			$outcome = 'settled';
		} catch ( ManualVerificationRejected $exception ) {
			$outcome = $exception->error_code();
		} catch ( InvalidArgumentException | RuntimeException ) {
			$outcome = 'verification_unavailable';
		}

		$order        = wc_get_order( $order_id );
		$redirect_url = $order instanceof WC_Order
			? $order->get_edit_order_url()
			: admin_url( 'edit.php?post_type=shop_order' );
		wp_safe_redirect(
			add_query_arg(
				array( 'daraja_mpesa_verification' => $outcome ),
				$redirect_url
			)
		);
		exit;
	}

	/**
	 * Build validated evidence from the nonce-protected request.
	 *
	 * @param string $attempt_id Payment attempt identifier.
	 * @param int    $order_id   WooCommerce order identifier.
	 * @param string $amount     Externally verified amount.
	 * @param string $receipt    Externally verified receipt.
	 * @param string $source     External evidence source.
	 * @param string $reason     Administrator rationale.
	 *
	 * @throws InvalidArgumentException When submitted evidence is invalid.
	 */
	private function request(
		string $attempt_id,
		int $order_id,
		string $amount,
		string $receipt,
		string $source,
		string $reason
	): ManualVerificationRequest {
		return new ManualVerificationRequest(
			$attempt_id,
			$order_id,
			get_current_user_id(),
			new KesAmount( $amount ),
			$receipt,
			$source,
			$reason
		);
	}

	/**
	 * Render a bounded result from the previous verification request.
	 */
	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bounded status from this controller's redirect.
		$outcome = isset( $_GET['daraja_mpesa_verification'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only bounded status from this controller's redirect.
			? sanitize_key( wp_unslash( $_GET['daraja_mpesa_verification'] ) )
			: '';

		if ( 'settled' === $outcome ) {
			echo '<div class="notice notice-success inline"><p>'
				. esc_html__( 'The external evidence and Daraja status were verified.', 'daraja-mpesa-for-woocommerce' )
				. '</p></div>';
		} elseif ( '' !== $outcome ) {
			echo '<div class="notice notice-error inline"><p>'
				. esc_html__( 'The payment was not settled. Review the evidence and current Daraja status.', 'daraja-mpesa-for-woocommerce' )
				. '</p></div>';
		}
	}
}
