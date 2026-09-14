<?php
/**
 * WooCommerce Checkout Blocks payment-method integration.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Gateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use DarajaMpesa\Application\ConfigurationFactory;
use InvalidArgumentException;

/**
 * Exposes the classic gateway contract to Checkout Blocks.
 */
final class CheckoutBlocksIntegration extends AbstractPaymentMethodType {
	/**
	 * Payment method identifier.
	 *
	 * @var string
	 */
	protected $name = 'daraja_mpesa';

	/**
	 * Load gateway settings.
	 */
	public function initialize(): void {
		$settings       = get_option( 'woocommerce_daraja_mpesa_settings', array() );
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	/**
	 * Whether the merchant has enabled this integration.
	 */
	public function is_active(): bool {
		if (
			'yes' !== $this->get_setting( 'enabled', 'no' )
			|| 'KES' !== get_woocommerce_currency()
			|| 'https' !== wp_parse_url( rest_url( 'daraja-mpesa/v1/callback' ), PHP_URL_SCHEME )
		) {
			return false;
		}

		try {
			( new ConfigurationFactory() )->from_settings( $this->settings );
			return true;
		} catch ( InvalidArgumentException ) {
			return false;
		}
	}

	/**
	 * Register and return the browser integration handle.
	 *
	 * @return list<string>
	 */
	public function get_payment_method_script_handles(): array {
		$script_url = plugins_url( 'assets/js/checkout-blocks.js', DARAJA_MPESA_FILE );
		if ( '' === $script_url ) {
			return array();
		}

		wp_register_script(
			'daraja-mpesa-checkout-blocks',
			$script_url,
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			),
			DARAJA_MPESA_VERSION,
			true
		);

		return array( 'daraja-mpesa-checkout-blocks' );
	}

	/**
	 * Return customer-facing gateway data.
	 *
	 * @return array{title: string, description: string, supports: array<string>}
	 */
	public function get_payment_method_data(): array {
		return array(
			'title'       => (string) $this->get_setting(
				'title',
				__( 'M-Pesa', 'daraja-mpesa-for-woocommerce' )
			),
			'description' => (string) $this->get_setting(
				'description',
				__( 'Pay securely through an M-Pesa prompt on your phone.', 'daraja-mpesa-for-woocommerce' )
			),
			'supports'    => array( 'products' ),
		);
	}
}
