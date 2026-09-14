<?php
/**
 * Runtime dependency validation.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure;

/**
 * Reports whether the plugin runtime dependencies are available.
 */
final class Requirements {
	/**
	 * Human-readable dependency failures.
	 *
	 * @var list<string>
	 */
	private readonly array $errors;

	/**
	 * Store dependency failures.
	 *
	 * @param array $errors Human-readable dependency failures.
	 * @phpstan-param list<string> $errors
	 */
	private function __construct( array $errors ) {
		$this->errors = $errors;
	}

	/**
	 * Evaluate runtime dependencies.
	 */
	public static function evaluate(): self {
		$errors = array();

		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			$errors[] = __( 'PHP 8.1 or newer is required.', 'daraja-mpesa-for-woocommerce' );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			$errors[] = __( 'WooCommerce must be installed and active.', 'daraja-mpesa-for-woocommerce' );
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '10.3', '<' ) ) {
			$errors[] = __( 'WooCommerce 10.3 or newer is required.', 'daraja-mpesa-for-woocommerce' );
		}

		return new self( $errors );
	}

	/**
	 * Whether all dependencies are available.
	 */
	public function is_satisfied(): bool {
		return array() === $this->errors;
	}

	/**
	 * Render dependency failures to administrators.
	 */
	public function render_admin_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( $this->errors as $error ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( $error )
			);
		}
	}
}
