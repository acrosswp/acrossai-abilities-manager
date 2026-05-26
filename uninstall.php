<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Drops all custom database tables created by this plugin and removes
 * all associated options. No data is preserved after uninstall.
 *
 * @package    AcrossAI_Abilities_Manager
 * @since      0.0.1
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the unified abilities table (renamed in 008-unified-abilities-table).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}acrossai_abilities`" );
\delete_option( 'acrossai_abilities_db_version' );


// Drop the WPBoilerplate Access Control rules table (created on activation via RuleTable).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wpb_access_control`" );

// Remove BerlinDB db-version options so the tables are recreated on re-activation.
\delete_option( 'wpb_access_control_db_version' );
