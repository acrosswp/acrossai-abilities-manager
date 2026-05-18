<?php
/**
 * BerlinDB Row class for a single ability execution log record.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger/Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database;

use BerlinDB\Database\Row;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Represents a single row from the acrossai_ability_logs table.
 *
 * @since 0.1.0
 *
 * @property int         $id
 * @property string      $ability_slug
 * @property string      $source
 * @property string|null $mcp_server_id
 * @property int|null    $user_id
 * @property string|null $input
 * @property string|null $output
 * @property string      $status
 * @property int         $duration_ms
 * @property string      $created_at
 */
class AcrossAI_Ability_Logs_Row extends Row {

	/**
	 * Primary key.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Ability slug identifier.
	 *
	 * @var string
	 */
	public $ability_slug = '';

	/**
	 * Execution source: mcp|rest|cli|cron|ajax|direct.
	 *
	 * @var string
	 */
	public $source = '';

	/**
	 * MCP server ID — null when source is not mcp.
	 *
	 * @var string|null
	 */
	public $mcp_server_id = null;

	/**
	 * WordPress user ID — null for non-authenticated contexts.
	 *
	 * @var int|null
	 */
	public $user_id = null;

	/**
	 * JSON-encoded ability input arguments.
	 *
	 * @var string|null
	 */
	public $input = null;

	/**
	 * JSON-encoded ability output.
	 *
	 * @var string|null
	 */
	public $output = null;

	/**
	 * Execution result: success|error|permission_denied.
	 *
	 * @var string
	 */
	public $status = '';

	/**
	 * Wall-clock execution time in milliseconds.
	 *
	 * @var int
	 */
	public $duration_ms = 0;

	/**
	 * Execution timestamp.
	 *
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Constructor — casts integer fields after BerlinDB hydration.
	 *
	 * @since  0.1.0
	 * @param  object|array $item Raw DB row.
	 */
	public function __construct( $item ) {
		parent::__construct( $item );

		$this->id          = (int) $this->id;
		$this->duration_ms = (int) $this->duration_ms;
		$this->user_id     = null !== $this->user_id ? (int) $this->user_id : null;
	}
}
