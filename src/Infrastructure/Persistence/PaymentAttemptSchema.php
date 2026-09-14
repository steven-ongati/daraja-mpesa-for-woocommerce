<?php
/**
 * Payment-attempt database schema.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Persistence;

use InvalidArgumentException;
use RuntimeException;
use wpdb;

/**
 * Installs the versioned payment-attempt table.
 */
final class PaymentAttemptSchema {
	public const VERSION = '2';

	/**
	 * Upgrade the schema when the installed version is stale.
	 *
	 * @throws RuntimeException         When WordPress database access is unavailable.
	 * @throws InvalidArgumentException When the configured table prefix is unsafe.
	 */
	public static function maybe_upgrade(): void {
		if ( self::VERSION !== get_option( 'daraja_mpesa_schema_version' ) ) {
			self::install();
		}
	}

	/**
	 * Install or upgrade the durable schema.
	 *
	 * @throws RuntimeException When WordPress database access is unavailable.
	 */
	public static function install(): void {
		global $wpdb;

		if ( ! $wpdb instanceof wpdb || ! defined( 'ABSPATH' ) ) {
			throw new RuntimeException( 'WordPress database access is unavailable.' );
		}

		$wordpress_root = (string) constant( 'ABSPATH' );

		require_once $wordpress_root . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		dbDelta( self::sql( self::table_name( $wpdb->prefix ), $charset_collate ) );
		dbDelta( self::audit_sql( self::audit_table_name( $wpdb->prefix ), $charset_collate ) );
		update_option( 'daraja_mpesa_schema_version', self::VERSION, false );
	}

	/**
	 * Build the prefixed table name.
	 *
	 * @param string $prefix WordPress database prefix.
	 *
	 * @throws InvalidArgumentException When the database prefix is unsafe.
	 */
	public static function table_name( string $prefix ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
			throw new InvalidArgumentException( 'The WordPress database prefix is invalid.' );
		}

		return $prefix . 'daraja_mpesa_attempts';
	}

	/**
	 * Build the prefixed manual-verification audit table name.
	 *
	 * @param string $prefix WordPress database prefix.
	 *
	 * @throws InvalidArgumentException When the database prefix is unsafe.
	 */
	public static function audit_table_name( string $prefix ): string {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $prefix ) ) {
			throw new InvalidArgumentException( 'The WordPress database prefix is invalid.' );
		}

		return $prefix . 'daraja_mpesa_manual_audit';
	}

	/**
	 * Build a dbDelta-compatible schema statement.
	 *
	 * @param string $table_name      Validated table name.
	 * @param string $charset_collate WordPress charset and collation clause.
	 */
	public static function sql( string $table_name, string $charset_collate ): string {
		return "CREATE TABLE {$table_name} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
attempt_id char(36) NOT NULL,
order_id bigint(20) unsigned NOT NULL,
amount bigint(20) unsigned NOT NULL,
phone_hash char(64) NOT NULL,
state varchar(32) NOT NULL,
merchant_request_id varchar(128) NULL,
checkout_request_id varchar(128) NULL,
receipt_number varchar(64) NULL,
provider_result_code varchar(64) NULL,
failure_code varchar(64) NULL,
poll_count int(10) unsigned NOT NULL DEFAULT 0,
version bigint(20) unsigned NOT NULL DEFAULT 1,
created_at_gmt datetime NOT NULL,
updated_at_gmt datetime NOT NULL,
settled_at_gmt datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY attempt_id (attempt_id),
UNIQUE KEY checkout_request_id (checkout_request_id),
UNIQUE KEY receipt_number (receipt_number),
KEY order_id (order_id),
KEY state_updated (state, updated_at_gmt)
) {$charset_collate};";
	}

	/**
	 * Build the immutable manual-verification audit schema.
	 *
	 * @param string $table_name      Validated table name.
	 * @param string $charset_collate WordPress charset and collation clause.
	 */
	public static function audit_sql( string $table_name, string $charset_collate ): string {
		return "CREATE TABLE {$table_name} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
attempt_id char(36) NOT NULL,
order_id bigint(20) unsigned NOT NULL,
operator_id bigint(20) unsigned NOT NULL,
amount bigint(20) unsigned NOT NULL,
receipt_hash char(64) NOT NULL,
evidence_source varchar(32) NOT NULL,
reason text NOT NULL,
outcome varchar(64) NOT NULL,
created_at_gmt datetime NOT NULL,
PRIMARY KEY  (id),
KEY attempt_id (attempt_id),
KEY order_id (order_id),
KEY operator_created (operator_id, created_at_gmt)
) {$charset_collate};";
	}
}
