<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Monster_Maintenance_Mode
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mmmode_settings' );
delete_option( 'pmf_settings' );

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mmmode_subscribers" );