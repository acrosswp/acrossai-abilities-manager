<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Drops custom database tables and removes
 * plugin options when the delete-data setting is enabled. Data is preserved by default.
 *
 * @package    AcrossAI_Abilities_Manager
 * @since      0.0.1
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Respect the user's "delete data on uninstall" setting.
$delete_data = (bool) get_option( 'acrossai_abilities_uninstall_delete_data', 0 );

if ( $delete_data ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}acrossai_abilities`" );
	\delete_option( 'acrossai_abilities_db_version' );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wpb_access_control`" );
	\delete_option( 'wpb_access_control_db_version' );
}

// Always remove plugin settings options on uninstall (not data — configuration).
\delete_option( 'acrossai_abilities_log_retention_days' );
\delete_option( 'acrossai_abilities_uninstall_delete_data' );
