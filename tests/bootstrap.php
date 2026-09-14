<?php
/**
 * PHPUnit bootstrap.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/Stubs/wpdb.php';
require_once __DIR__ . '/Stubs/wordpress-functions.php';

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'DarajaMpesa\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file           = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
