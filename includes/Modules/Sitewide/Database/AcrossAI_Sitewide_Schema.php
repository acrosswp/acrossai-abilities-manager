<?php
/**
 * Database schema definition for the abilities overwrite table.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database;

use BerlinDB\Database\Schema;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Schema class defining all 16 columns of the acrossai_abilities_overwrite table.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Schema extends Schema {

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

		// Ability identifier.
		array(
			'name'       => 'ability_slug',
			'type'       => 'varchar',
			'length'     => '255',
			'null'       => false,
			'searchable' => true,
			'sortable'   => true,
		),

		// Provider string.
		array(
			'name'       => 'provider',
			'type'       => 'varchar',
			'length'     => '100',
			'allow_null' => true,
			'default'    => null,
			'sortable'   => true,
		),

		// Source enum.
		array(
			'name'       => 'source',
			'type'       => 'varchar',
			'length'     => '50',
			'allow_null' => true,
			'default'    => null,
			'sortable'   => true,
		),

		// Tri-state boolean columns.
		array(
			'name'       => 'site_allowed',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'readonly',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'destructive',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'idempotent',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'show_in_rest',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'show_in_mcp',
			'type'       => 'tinyint',
			'length'     => '1',
			'allow_null' => true,
			'default'    => null,
		),

		// MCP type.
		array(
			'name'       => 'mcp_type',
			'type'       => 'varchar',
			'length'     => '100',
			'allow_null' => true,
			'default'    => null,
		),

		// MCP servers JSON.
		array(
			'name'       => 'mcp_servers',
			'type'       => 'longtext',
			'allow_null' => true,
			'default'    => null,
		),

		// Audit timestamps.
		array(
			'name'       => 'created_at',
			'type'       => 'datetime',
			'null'       => false,
			'default'    => 'CURRENT_TIMESTAMP',
			'created'    => true,
			'date_query' => true,
			'sortable'   => true,
		),
		array(
			'name'       => 'updated_at',
			'type'       => 'datetime',
			'null'       => false,
			'default'    => 'CURRENT_TIMESTAMP',
			'modified'   => true,
			'date_query' => true,
			'sortable'   => true,
		),

		// Audit user IDs.
		array(
			'name'       => 'created_by',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'allow_null' => true,
			'default'    => null,
		),
		array(
			'name'       => 'updated_by',
			'type'       => 'bigint',
			'length'     => '20',
			'unsigned'   => true,
			'allow_null' => true,
			'default'    => null,
		),
	);
}
