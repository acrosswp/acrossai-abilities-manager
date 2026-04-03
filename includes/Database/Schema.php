<?php
/**
 * Database schema management.
 *
 * @package Abilities_Editor
 */

declare( strict_types=1 );

namespace Abilities_Editor\Database;

defined( 'ABSPATH' ) || exit;

class Schema {
	public const TABLE_NAME = 'abilities_editor_overrides';
	private const SCHEMA_VERSION = '2';
	private const SCHEMA_VERSION_OPTION = 'abe_schema_version';

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	public static function maybe_upgrade_table(): void {
		if ( self::SCHEMA_VERSION === get_option( self::SCHEMA_VERSION_OPTION, '' ) && self::table_exists() ) {
			return;
		}
		self::create_table();

		if ( self::table_exists() ) {
			update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION, false );
		}
	}

	public static function create_table(): void {
		global $wpdb;
		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ability_slug VARCHAR(255) NOT NULL,
			provider VARCHAR(100) DEFAULT NULL,
			readonly TINYINT(1) DEFAULT NULL,
			destructive TINYINT(1) DEFAULT NULL,
			idempotent TINYINT(1) DEFAULT NULL,
			show_in_rest TINYINT(1) DEFAULT NULL,
			mcp_public TINYINT(1) DEFAULT NULL,
			mcp_type VARCHAR(100) DEFAULT NULL,
			custom_meta LONGTEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY ability_slug (ability_slug),
			KEY idx_provider (provider)
		) {$charset_collate};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		self::migrate_legacy_schema();
	}

	public static function drop_table(): void {
		global $wpdb;
		$table_name = self::get_table_name();
		if ( ! self::table_exists() ) {
			delete_option( self::SCHEMA_VERSION_OPTION );
			return;
		}
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		delete_option( self::SCHEMA_VERSION_OPTION );
	}


	private static function migrate_legacy_schema(): void {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( ! self::table_exists() ) {
			return;
		}

		$legacy_columns = array( 'slug', 'meta_json' );

		foreach ( $legacy_columns as $column ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", $column ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( $exists ) {
				$wpdb->query( "ALTER TABLE {$table_name} DROP COLUMN {$column}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
			}
		}

		$wpdb->query( "ALTER TABLE {$table_name} MODIFY updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	private static function table_exists(): bool {
		global $wpdb;
		$table_name = self::get_table_name();
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		return $result === $table_name;
	}
}
