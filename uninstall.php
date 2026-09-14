<?php
/**
 * Plugin uninstall entrypoint.
 *
 * @package DarajaMpesa
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'daraja_mpesa_version' );
