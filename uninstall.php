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
$acrossai_delete_data = (bool) get_option( 'acrossai_abilities_uninstall_delete_data', 0 );

if ( $acrossai_delete_data ) {
	$acrossai_abilities_table = $wpdb->prefix . 'acrossai_abilities';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query(
		$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_abilities_table )
	);
	\delete_option( 'acrossai_abilities_db_version' );

	$acrossai_access_control_table = $wpdb->prefix . 'wpb_access_control';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query(
		$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_access_control_table )
	);
	\delete_option( 'wpb_access_control_db_version' );
}

// Always remove plugin settings options on uninstall (not data — configuration).
\delete_option( 'acrossai_abilities_log_retention_days' );
\delete_option( 'acrossai_abilities_uninstall_delete_data' );
