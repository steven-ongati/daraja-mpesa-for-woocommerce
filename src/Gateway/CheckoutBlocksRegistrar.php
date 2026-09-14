<?php
/**
 * WooCommerce Checkout Blocks registrar.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Gateway;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

/**
 * Registers the M-Pesa payment-method integration.
 */
final class CheckoutBlocksRegistrar {
	/**
	 * Register the Blocks payment method.
	 *
	 * @param PaymentMethodRegistry $registry WooCommerce payment registry.
	 */
	public static function register( PaymentMethodRegistry $registry ): void {
		$registry->register( new CheckoutBlocksIntegration() );
	}
}
