<?php
/**
 * Plugin uninstall entrypoint.
 *
 * @package DarajaMpesa
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'woocommerce_daraja_mpesa_settings', array() );
if ( is_array( $settings ) ) {
	$environment  = isset( $settings['environment'] ) && is_scalar( $settings['environment'] )
		? (string) $settings['environment']
		: '';
	$consumer_key = isset( $settings['consumer_key'] ) && is_scalar( $settings['consumer_key'] )
		? (string) $settings['consumer_key']
		: '';

	if ( '' !== $environment && '' !== $consumer_key ) {
		$identity = $environment . ':' . $consumer_key;
		delete_transient( 'daraja_mpesa_token_' . substr( hash( 'sha256', $identity ), 0, 40 ) );
	}
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'daraja_mpesa_poll_attempt', array(), 'daraja-mpesa' );
	as_unschedule_all_actions( 'daraja_mpesa_review_attempt', array(), 'daraja-mpesa' );
}

global $wpdb;

if ( $wpdb instanceof wpdb ) {
	$attempts_table = $wpdb->prefix . 'daraja_mpesa_attempts';
	$audit_table    = $wpdb->prefix . 'daraja_mpesa_manual_audit';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall drops fixed plugin-owned tables using WordPress's configured prefix.
	$wpdb->query( "DROP TABLE IF EXISTS `{$attempts_table}`, `{$audit_table}`" );
}

delete_option( 'woocommerce_daraja_mpesa_settings' );
delete_option( 'daraja_mpesa_version' );
delete_option( 'daraja_mpesa_schema_version' );
