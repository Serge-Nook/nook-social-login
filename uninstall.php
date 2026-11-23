<?php
/**
 * Removes plugin data on uninstall.
 *
 * @package NookSocialLogin
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$nooksl_settings = get_option( 'nooksl_settings', array() );

delete_option( 'nooksl_settings' );

if ( ! empty( $nooksl_settings['delete_data'] ) ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'nooksl\\_%'" );
}
