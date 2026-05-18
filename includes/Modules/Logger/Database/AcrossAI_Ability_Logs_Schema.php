<?php
/**
 * Database schema definition for ability execution logs.
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
 * Indexes are designed for FR-003 query performance (SC-002):
 * - (ability_slug, created_at): Filter/sort by ability slug over time
 * - (source, created_at): Filter/sort by source over time
 * - (user_id, created_at): Filter/sort by user over time
 * - (status, created_at): Filter/sort by status over time
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Logs_Schema extends Schema {

	/**
	 * Array of column definitions.
	 *
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

		// Ability slug identifier.
		array(
			'name'       => 'ability_slug',
			'type'       => 'varchar',
			'length'     => '255',
			'null'       => false,
			'searchable' => true,
			'sortable'   => true,
		),

		// Execution source (mcp, rest, cli, cron, ajax, direct).
		array(
			'name'     => 'source',
			'type'     => 'varchar',
			'length'   => '20',
			'null'     => false,
			'sortable' => true,
		),

		// MCP server ID (nullable, only set for MCP executions).
		array(
			'name'       => 'mcp_server_id',
			'type'       => 'varchar',
			'length'     => '255',
			'allow_null' => true,
			'default'    => null,
			'sortable'   => false,
		),

		// User ID (nullable, 0 for non-user contexts).
		array(
			'name'       => 'user_id',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'allow_null' => true,
			'default'    => null,
			'sortable'   => true,
		),

		// Input data (JSON-encoded, may be NULL).
		array(
			'name'       => 'input',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
		),

		// Output data (JSON-encoded, may be NULL for pending entries).
		array(
			'name'       => 'output',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
		),

		// Execution status (success, error, permission_denied).
		array(
			'name'     => 'status',
			'type'     => 'varchar',
			'length'   => '20',
			'null'     => false,
			'sortable' => true,
		),

		// Execution duration in milliseconds.
		array(
			'name'     => 'duration_ms',
			'type'     => 'int',
			'length'   => '11',
			'unsigned' => false,
			'default'  => 0,
			'sortable' => true,
		),

		// Timestamp when execution was logged.
		array(
			'name'     => 'created_at',
			'type'     => 'datetime',
			'null'     => false,
			'sortable' => true,
		),
	);

	/**
	 * Array of index definitions (composite indexes for query performance).
	 *
	 * @var array
	 */
	public $indexes = array(

		// Index for filtering/sorting by ability_slug and created_at (SC-002).
		array(
			'name'    => 'idx_ability_slug_created',
			'columns' => array( 'ability_slug', 'created_at' ),
		),

		// Index for filtering/sorting by source and created_at (SC-002).
		array(
			'name'    => 'idx_source_created',
			'columns' => array( 'source', 'created_at' ),
		),

		// Index for filtering/sorting by user_id and created_at (SC-002).
		array(
			'name'    => 'idx_user_id_created',
			'columns' => array( 'user_id', 'created_at' ),
		),

		// Index for filtering/sorting by status and created_at (SC-002).
		array(
			'name'    => 'idx_status_created',
			'columns' => array( 'status', 'created_at' ),
		),
	);
}
