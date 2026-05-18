<?php
/**
 * Database schema definition for the ability execution logs table.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database;

use BerlinDB\Database\Schema;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Schema class defining all 10 columns of the acrossai_ability_logs table.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Logs_Schema extends Schema {

	/**
	 * Array of column definitions.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	public $columns = array(

		// Primary key.
		array(
			'name'     => 'id',
			'type'     => 'bigint',
			'length'   => '20',
			'unsigned' => true,
			'extra'    => 'auto_increment',
			'primary'  => true,
			'sortable' => true,
		),

		// Ability identifier.
		array(
			'name'       => 'ability_slug',
			'type'       => 'varchar',
			'length'     => '255',
			'null'       => false,
			'searchable' => true,
			'sortable'   => true,
		),

		// Execution source: mcp|rest|cli|cron|ajax|direct.
		array(
			'name'     => 'source',
			'type'     => 'varchar',
			'length'   => '20',
			'null'     => false,
			'sortable' => true,
		),

		// MCP server ID — null when source is not mcp.
		array(
			'name'       => 'mcp_server_id',
			'type'       => 'varchar',
			'length'     => '255',
			'allow_null' => true,
			'default'    => null,
		),

		// WordPress user ID — null for non-authenticated contexts.
		array(
			'name'       => 'user_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'allow_null' => true,
			'default'    => null,
			'sortable'   => true,
		),

		// JSON-encoded ability input arguments.
		array(
			'name'       => 'input',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
		),

		// JSON-encoded ability output.
		array(
			'name'       => 'output',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
		),

		// Execution result: success|error|permission_denied.
		array(
			'name'     => 'status',
			'type'     => 'varchar',
			'length'   => '20',
			'null'     => false,
			'sortable' => true,
		),

		// Wall-clock execution time in milliseconds.
		array(
			'name'     => 'duration_ms',
			'type'     => 'int',
			'length'   => '11',
			'null'     => false,
			'default'  => '0',
			'sortable' => true,
		),

		// Execution timestamp.
		array(
			'name'       => 'created_at',
			'type'       => 'datetime',
			'null'       => false,
			'default'    => 'CURRENT_TIMESTAMP',
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
		),
	);
}
