<?php
/**
 * BerlinDB Row definition for custom abilities
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Modules\Custom_Ability\Database
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Database;

use BerlinDB\Database\Row;

/**
 * Class AcrossAI_Custom_Ability_Row
 *
 * Represents a single custom ability record.
 * Handles JSON decoding/encoding for JSON columns (Watchpoint 1).
 *
 * JSON columns: callback_config, permission_config, input_schema, output_schema, mcp_servers
 *
 * @since 0.0.1
 */
class AcrossAI_Custom_Ability_Row extends Row {

	/**
	 * List of JSON column names that should be decoded on construct.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	protected $json_columns = array(
		'callback_config',
		'permission_config',
		'input_schema',
		'output_schema',
		'mcp_servers',
	);

	/**
	 * Constructor.
	 *
	 * Calls parent constructor and decodes JSON columns.
	 *
	 * @since 0.0.1
	 * @param object $row Database row object.
	 */
	public function __construct( $row = null ) {
		parent::__construct( $row );

		// Decode JSON columns (Watchpoint 1: JSON column casting)
		foreach ( $this->json_columns as $column ) {
			if ( isset( $this->$column ) && is_string( $this->$column ) ) {
				$decoded = json_decode( $this->$column, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$this->$column = $decoded;
				}
			}
		}
	}

	/**
	 * Get the value of a property.
	 *
	 * @since 0.0.1
	 * @param string $key Property key.
	 * @return mixed Property value.
	 */
	public function get( $key = '' ) {
		// Return JSON columns as decoded arrays/objects
		if ( in_array( $key, $this->json_columns, true ) ) {
			return $this->$key ?? null;
		}

		return parent::get( $key );
	}

	/**
	 * Convert JSON columns to string before save.
	 *
	 * @since 0.0.1
	 * @return array Array of data ready for database save.
	 */
	public function to_array() {
		$data = parent::to_array();

		// Encode JSON columns for storage
		foreach ( $this->json_columns as $column ) {
			if ( isset( $data[ $column ] ) && is_array( $data[ $column ] ) ) {
				$data[ $column ] = wp_json_encode( $data[ $column ] );
			}
		}

		return $data;
	}

	/**
	 * Get callback_config.
	 *
	 * @since 0.0.1
	 * @return array|null Decoded callback configuration.
	 */
	public function get_callback_config() {
		return $this->callback_config ?? null;
	}

	/**
	 * Get permission_config.
	 *
	 * @since 0.0.1
	 * @return array|null Decoded permission configuration.
	 */
	public function get_permission_config() {
		return $this->permission_config ?? null;
	}

	/**
	 * Get input_schema.
	 *
	 * @since 0.0.1
	 * @return array|null Decoded input schema.
	 */
	public function get_input_schema() {
		return $this->input_schema ?? null;
	}

	/**
	 * Get output_schema.
	 *
	 * @since 0.0.1
	 * @return array|null Decoded output schema.
	 */
	public function get_output_schema() {
		return $this->output_schema ?? null;
	}

	/**
	 * Get mcp_servers.
	 *
	 * @since 0.0.1
	 * @return array|null Decoded MCP servers list.
	 */
	public function get_mcp_servers() {
		return $this->mcp_servers ?? null;
	}

	/**
	 * Check if ability is enabled.
	 *
	 * @since 0.0.1
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled() {
		return (bool) $this->enabled;
	}

	/**
	 * Check if ability is shown in REST API.
	 *
	 * @since 0.0.1
	 * @return bool True if shown in REST, false otherwise.
	 */
	public function is_shown_in_rest() {
		return (bool) $this->show_in_rest;
	}

	/**
	 * Check if ability is shown in MCP.
	 *
	 * @since 0.0.1
	 * @return bool True if shown in MCP, false otherwise.
	 */
	public function is_shown_in_mcp() {
		return (bool) $this->show_in_mcp;
	}
}
