<?php
/**
 * WooCommerce Daraja M-Pesa payment gateway.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Gateway;

use DarajaMpesa\Application\ConfigurationFactory;
use DarajaMpesa\Domain\KenyanPhoneNumber;
use DarajaMpesa\Infrastructure\Daraja\DarajaApiException;
use DarajaMpesa\Infrastructure\RuntimeFactory;
use InvalidArgumentException;
use RuntimeException;
use WC_Order;
use WC_Payment_Gateway;

/**
 * Initiates M-Pesa Express without marking an order paid on acceptance.
 */
final class DarajaMpesaGateway extends WC_Payment_Gateway {
	/**
	 * Configure gateway metadata, settings, and admin hooks.
	 */
	public function __construct() {
		$this->id                 = 'daraja_mpesa';
		$this->method_title       = __( 'Daraja M-Pesa', 'daraja-mpesa-for-woocommerce' );
		$this->method_description = __(
			'Direct Safaricom Daraja STK Push with durable, exact-amount reconciliation.',
			'daraja-mpesa-for-woocommerce'
		);
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->option( 'title', __( 'M-Pesa', 'daraja-mpesa-for-woocommerce' ) );
		$this->description = $this->option(
			'description',
			__( 'Pay securely through an M-Pesa prompt on your phone.', 'daraja-mpesa-for-woocommerce' )
		);
		$this->enabled     = $this->option( 'enabled', 'no' );

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
	}

	/**
	 * Define merchant-facing gateway settings.
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'          => array(
				'title'   => __( 'Enable/Disable', 'daraja-mpesa-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Daraja M-Pesa payments', 'daraja-mpesa-for-woocommerce' ),
				'default' => 'no',
			),
			'title'            => array(
				'title'       => __( 'Checkout title', 'daraja-mpesa-for-woocommerce' ),
				'type'        => 'text',
				'default'     => __( 'M-Pesa', 'daraja-mpesa-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'Shown to customers during checkout.', 'daraja-mpesa-for-woocommerce' ),
			),
			'description'      => array(
				'title'   => __( 'Checkout description', 'daraja-mpesa-for-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Pay securely through an M-Pesa prompt on your phone.', 'daraja-mpesa-for-woocommerce' ),
			),
			'environment'      => array(
				'title'       => __( 'Daraja environment', 'daraja-mpesa-for-woocommerce' ),
				'type'        => 'select',
				'default'     => 'sandbox',
				'description' => __( 'Test with sandbox credentials before switching to live.', 'daraja-mpesa-for-woocommerce' ),
				'options'     => array(
					'sandbox' => __( 'Sandbox', 'daraja-mpesa-for-woocommerce' ),
					'live'    => __( 'Live', 'daraja-mpesa-for-woocommerce' ),
				),
			),
			'consumer_key'     => array(
				'title' => __( 'Consumer key', 'daraja-mpesa-for-woocommerce' ),
				'type'  => 'password',
			),
			'consumer_secret'  => array(
				'title' => __( 'Consumer secret', 'daraja-mpesa-for-woocommerce' ),
				'type'  => 'password',
			),
			'shortcode'        => array(
				'title'       => __( 'Business shortcode', 'daraja-mpesa-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'The PayBill or Till shortcode enabled for M-Pesa Express.', 'daraja-mpesa-for-woocommerce' ),
			),
			'passkey'          => array(
				'title' => __( 'M-Pesa Express passkey', 'daraja-mpesa-for-woocommerce' ),
				'type'  => 'password',
			),
			'transaction_type' => array(
				'title'   => __( 'Merchant account type', 'daraja-mpesa-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'CustomerPayBillOnline',
				'options' => array(
					'CustomerPayBillOnline'  => __( 'PayBill', 'daraja-mpesa-for-woocommerce' ),
					'CustomerBuyGoodsOnline' => __( 'Till / Buy Goods', 'daraja-mpesa-for-woocommerce' ),
				),
			),
		);
	}

	/**
	 * Render the customer M-Pesa phone field.
	 */
	public function payment_fields(): void {
		if ( '' !== $this->description ) {
			echo wp_kses_post( wpautop( $this->description ) );
		}

		woocommerce_form_field(
			'daraja_mpesa_phone',
			array(
				'type'         => 'tel',
				'label'        => __( 'M-Pesa phone number', 'daraja-mpesa-for-woocommerce' ),
				'placeholder'  => '07XXXXXXXX',
				'required'     => true,
				'class'        => array( 'form-row-wide' ),
				'autocomplete' => 'tel',
			),
			$this->posted_phone()
		);
	}

	/**
	 * Validate the checkout phone number.
	 */
	public function validate_fields(): bool {
		try {
			new KenyanPhoneNumber( $this->posted_phone() );
			return true;
		} catch ( InvalidArgumentException ) {
			wc_add_notice(
				__( 'Enter a valid Safaricom number such as 0712345678.', 'daraja-mpesa-for-woocommerce' ),
				'error'
			);

			return false;
		}
	}

	/**
	 * Initiate M-Pesa payment and keep the order unpaid.
	 *
	 * @param int $order_id WooCommerce order identifier.
	 *
	 * @return array{result: string, redirect: string}
	 */
	public function process_payment( $order_id ): array {
		$order = wc_get_order( (int) $order_id );

		if ( ! $order instanceof WC_Order || 'KES' !== $order->get_currency() ) {
			wc_add_notice(
				__( 'M-Pesa payments require a valid order in Kenyan shillings.', 'daraja-mpesa-for-woocommerce' ),
				'error'
			);

			return $this->failure_result();
		}

		try {
			$runtime = new RuntimeFactory();
			if ( ! $runtime->payment_retry_policy()->may_start( $order->get_meta( '_daraja_mpesa_attempt_id', true ) ) ) {
				wc_add_notice(
					__( 'A previous M-Pesa request is still being verified. Refresh the order status or contact the store before trying again.', 'daraja-mpesa-for-woocommerce' ),
					'error'
				);

				return $this->failure_result();
			}

			$configuration = ( new ConfigurationFactory() )->from_settings( $this->merchant_settings() );
			$attempt       = $runtime->payment_initiator( $configuration )->start(
				$order->get_id(),
				(string) $order->get_total(),
				$this->posted_phone()
			);
		} catch ( InvalidArgumentException | RuntimeException $exception ) {
			wc_add_notice( $this->customer_error( $exception ), 'error' );

			return $this->failure_result();
		}

		$order->update_meta_data( '_daraja_mpesa_attempt_id', $attempt->attempt_id() );
		$order->add_order_note(
			__( 'M-Pesa request sent. The order remains unpaid until exact receipt evidence is verified.', 'daraja-mpesa-for-woocommerce' )
		);
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Only expose a configured gateway for KES over HTTPS.
	 */
	public function is_available(): bool {
		if ( ! parent::is_available() || 'KES' !== get_woocommerce_currency() ) {
			return false;
		}

		$scheme = wp_parse_url( rest_url( 'daraja-mpesa/v1/callback' ), PHP_URL_SCHEME );

		return 'https' === $scheme && ! $this->needs_setup();
	}

	/**
	 * Determine whether required merchant settings are incomplete.
	 */
	public function needs_setup(): bool {
		try {
			( new ConfigurationFactory() )->from_settings( $this->merchant_settings() );
			return false;
		} catch ( InvalidArgumentException ) {
			return true;
		}
	}

	/**
	 * Return scalar gateway settings for configuration validation.
	 *
	 * @return array<string, string>
	 */
	private function merchant_settings(): array {
		return array(
			'environment'      => $this->option( 'environment', 'sandbox' ),
			'consumer_key'     => $this->option( 'consumer_key' ),
			'consumer_secret'  => $this->option( 'consumer_secret' ),
			'shortcode'        => $this->option( 'shortcode' ),
			'passkey'          => $this->option( 'passkey' ),
			'transaction_type' => $this->option( 'transaction_type', 'CustomerPayBillOnline' ),
		);
	}

	/**
	 * Read a scalar gateway option.
	 *
	 * @param string $key      Gateway option key.
	 * @param string $fallback Default value.
	 */
	private function option( string $key, string $fallback = '' ): string {
		$value = $this->get_option( $key, $fallback );

		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Return a sanitized submitted phone value.
	 */
	private function posted_phone(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before invoking gateways.
		if ( ! isset( $_POST['daraja_mpesa_phone'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before invoking gateways.
		return sanitize_text_field( wp_unslash( (string) $_POST['daraja_mpesa_phone'] ) );
	}

	/**
	 * Return a stable checkout failure result.
	 *
	 * @return array{result: string, redirect: string}
	 */
	private function failure_result(): array {
		return array(
			'result'   => 'failure',
			'redirect' => '',
		);
	}

	/**
	 * Map safe provider errors to actionable customer messages.
	 *
	 * @param InvalidArgumentException|RuntimeException $exception Safe payment exception.
	 */
	private function customer_error(
		InvalidArgumentException|RuntimeException $exception
	): string {
		if ( $exception instanceof DarajaApiException && $exception->is_retryable() ) {
			return __( 'M-Pesa is temporarily unavailable. Please try again.', 'daraja-mpesa-for-woocommerce' );
		}

		return __( 'The M-Pesa request could not be started. Check the payment details or contact the store.', 'daraja-mpesa-for-woocommerce' );
	}
}
