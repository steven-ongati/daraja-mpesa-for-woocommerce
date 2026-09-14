<?php
/**
 * Plugin Name:       Daraja M-Pesa Gateway for WooCommerce
 * Plugin URI:        https://github.com/steven-ongati/daraja-mpesa-for-woocommerce
 * Description:       Direct Safaricom Daraja STK Push payments with durable reconciliation for WooCommerce.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * WC requires at least: 10.3
 * WC tested up to:   11.1
 * Author:            Steven Ongati Moriasi
 * Author URI:        https://github.com/steven-ongati
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       daraja-mpesa-for-woocommerce
 * Domain Path:       /languages
 *
 * @package DarajaMpesa
 */

defined( 'ABSPATH' ) || exit;

define( 'DARAJA_MPESA_VERSION', '0.1.0' );
define( 'DARAJA_MPESA_FILE', __FILE__ );
define( 'DARAJA_MPESA_PATH', plugin_dir_path( __FILE__ ) );
define( 'DARAJA_MPESA_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'DarajaMpesa\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file           = DARAJA_MPESA_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( \DarajaMpesa\Plugin::class, 'activate' ) );

\DarajaMpesa\Plugin::boot();
