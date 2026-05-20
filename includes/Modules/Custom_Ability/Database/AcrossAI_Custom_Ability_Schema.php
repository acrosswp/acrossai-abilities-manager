<?php
/**
 * BerlinDB Schema definition for custom abilities table
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability\Database
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database;

use BerlinDB\Database\Schema;

/**
 * Class AcrossAI_Custom_Ability_Schema
 *
 * Defines the structure of the custom_abilities database table.
 * Extends BerlinDB\Database\Schema for database abstraction.
 *
 * Table: {prefix}acrossai_custom_abilities
 * Per-site table (not global) for multisite isolation.
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Schema extends Schema {

	/**
	 * Array of column definitions for the custom abilities table.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	public $columns = array(
		// Primary key
		'id'                    => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY',

		// Ability identifier (unique, required)
		'ability_slug'          => 'varchar(255) NOT NULL UNIQUE',

		// Display and metadata
		'label'                 => 'varchar(255) NOT NULL',
		'description'           => 'longtext',
		'category'              => 'varchar(100)',

		// Registration control
		'enabled'               => 'tinyint(1) DEFAULT 1',

		// Callback configuration
		'callback_type'         => 'varchar(50) NOT NULL',
		'callback_config'       => 'longtext', // JSON structure for callback-specific config

		// Permission configuration
		'permission_type'       => 'varchar(50) NOT NULL',
		'permission_config'     => 'longtext', // JSON structure for permission-specific config

		// Ability schemas (JSON)
		'input_schema'          => 'longtext', // JSON Schema Draft 7
		'output_schema'         => 'longtext', // JSON Schema Draft 7

		// REST API exposure
		'show_in_rest'          => 'tinyint(1) DEFAULT 1',

		// MCP exposure
		'show_in_mcp'           => 'tinyint(1) DEFAULT 0',
		'mcp_type'              => 'varchar(50)', // 'tool', 'resource', 'prompt'
		'mcp_servers'           => 'longtext', // JSON array of server slugs

		// Metadata flags (tri-state: NULL/0/1)
		'readonly'              => 'tinyint(1)',
		'destructive'           => 'tinyint(1)',
		'idempotent'            => 'tinyint(1)',

		// Timestamps
		'created_at'            => 'datetime DEFAULT CURRENT_TIMESTAMP',
		'updated_at'            => 'datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
	);

	/**
	 * Get the table name.
	 *
	 * @since 0.0.1
	 * @return string The table name with prefix.
	 */
	public function get_table_name() {
		return 'acrossai_custom_abilities';
	}

	/**
	 * Get the primary key column.
	 *
	 * @since 0.0.1
	 * @return string The primary key column name.
	 */
	public function get_primary_key() {
		return 'id';
	}

	/**
	 * Get unique columns.
	 *
	 * @since 0.0.1
	 * @return array Array of unique columns.
	 */
	public function get_unique_keys() {
		return array( 'ability_slug' );
	}
}
