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
 * Maps database columns to PHP properties with type hints.
 * Handles JSON decoding for input/output fields.
 *
 * @since 0.1.0
 *
 * @property int         $id                Primary key
 * @property string      $ability_slug      Ability identifier
 * @property string      $source            Execution source (mcp, rest, cli, cron, ajax, direct)
 * @property string|null $mcp_server_id     MCP server ID (nullable)
 * @property int|null    $user_id           User ID (nullable)
 * @property string|null $input             Input data (JSON-encoded)
 * @property string|null $output            Output data (JSON-encoded)
 * @property string      $status            Execution status (success, error, permission_denied)
 * @property int         $duration_ms       Duration in milliseconds
 * @property string      $created_at        Timestamp
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
	 * Execution source (mcp, rest, cli, cron, ajax, direct).
	 *
	 * @var string
	 */
	public $source = 'direct';

	/**
	 * MCP server ID (nullable, only set for MCP executions).
	 *
	 * @var string|null
	 */
	public $mcp_server_id = null;

	/**
	 * User ID (nullable, 0 for non-user contexts).
	 *
	 * @var int|null
	 */
	public $user_id = null;

	/**
	 * Input data (JSON-encoded).
	 *
	 * @var string|null
	 */
	public $input = null;

	/**
	 * Output data (JSON-encoded).
	 *
	 * @var string|null
	 */
	public $output = null;

	/**
	 * Execution status (success, error, permission_denied).
	 *
	 * @var string
	 */
	public $status = 'success';

	/**
	 * Execution duration in milliseconds.
	 *
	 * @var int
	 */
	public $duration_ms = 0;

	/**
	 * Timestamp when execution was logged.
	 *
	 * @var string
	 */
	public $created_at = '';

	/**
	 * Convert row to array representation.
	 *
	 * @since 0.1.0
	 * @return array Associative array with all 10 fields
	 */
	public function to_array(): array {
		return array(
			'id'            => (int) $this->id,
			'ability_slug'  => (string) $this->ability_slug,
			'source'        => (string) $this->source,
			'mcp_server_id' => $this->mcp_server_id ? (string) $this->mcp_server_id : null,
			'user_id'       => $this->user_id ? (int) $this->user_id : null,
			'input'         => $this->input ? (string) $this->input : null,
			'output'        => $this->output ? (string) $this->output : null,
			'status'        => (string) $this->status,
			'duration_ms'   => (int) $this->duration_ms,
			'created_at'    => (string) $this->created_at,
		);
	}

	/**
	 * Convert row to JSON representation.
	 *
	 * @since 0.1.0
	 * @return string JSON-encoded string of all properties
	 */
	public function to_json(): string {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return wp_json_encode( $this->to_array() );
	}
}
