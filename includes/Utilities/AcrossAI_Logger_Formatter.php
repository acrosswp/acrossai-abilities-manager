<?php
/**
 * Logger entry formatter utility.
 *
 * Static utility for log entry formatting, JSON encoding, input/output truncation,
 * and error handling.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Utilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Log entry formatter utility
 *
 * Handles JSON encoding, truncation, error message formatting, and type casting.
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Formatter {

	/**
	 * Format log entry with validation and sanitization
	 *
	 * Returns well-formed 10-field log entry array ready for database insertion.
	 *
	 * @since 0.1.0
	 * @static
	 * @param array $entry Raw entry data to format.
	 * @return array Formatted 10-field log entry
	 */
	public static function format_log_entry( $entry = array() ) {
		// Validate all required fields are present.
		$required_fields = array(
			'ability_slug',
			'source',
			'status',
			'duration_ms',
			'created_at',
		);

		foreach ( $required_fields as $field ) {
			if ( ! isset( $entry[ $field ] ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "Logger: missing required field '{$field}'" );
				return array();
			}
		}

		// Validate source is valid (SEC-04: strict comparison).
		$valid_sources = array( 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct' );
		if ( ! in_array( $entry['source'], $valid_sources, true ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Logger: invalid source value' );
			$entry['source'] = 'direct'; // Fallback.
		}

		// Validate status is valid (SEC-04: strict comparison).
		$valid_statuses = array( 'success', 'error', 'permission_denied' );
		if ( ! in_array( $entry['status'], $valid_statuses, true ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Logger: invalid status value' );
			$entry['status'] = 'error'; // Fallback.
		}

		// Format input and output (truncate and JSON encode).
		$input  = isset( $entry['input'] ) ? self::format_value( $entry['input'] ) : null;
		$output = isset( $entry['output'] ) ? self::format_value( $entry['output'] ) : null;

		// Extract error message if result is WP_Error or Exception.
		if ( isset( $entry['result'] ) ) {
			if ( is_wp_error( $entry['result'] ) ) {
				$output = self::format_value( $entry['result']->get_error_message() );
			} elseif ( $entry['result'] instanceof \Exception ) {
				$output = self::format_value( $entry['result']->getMessage() );
			} elseif ( is_array( $entry['result'] ) || is_object( $entry['result'] ) ) {
				$output = self::format_value( $entry['result'] );
			}
		}

		// Build formatted entry.
		$formatted_entry = array(
			'ability_slug'  => (string) $entry['ability_slug'],
			'source'        => (string) $entry['source'],
			'mcp_server_id' => isset( $entry['mcp_server_id'] ) ? (string) $entry['mcp_server_id'] : null,
			'user_id'       => isset( $entry['user_id'] ) ? (int) $entry['user_id'] : null,
			'input'         => $input,
			'output'        => $output,
			'status'        => (string) $entry['status'],
			'duration_ms'   => (int) $entry['duration_ms'],
			'created_at'    => (string) $entry['created_at'],
		);

		return $formatted_entry;
	}

	/**
	 * Format and truncate a value for logging
	 *
	 * Handles JSON encoding, truncation to 65535 bytes (EC-005), and UTF-8 safety.
	 *
	 * @since 0.1.0
	 * @static
	 * @param mixed $value Value to format.
	 * @return string|null Formatted string or null
	 */
	public static function format_value( $value = null ) {
		if ( null === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			// Already string, just truncate.
			return self::truncate_string( $value, 65535 );
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			// JSON encode complex types.
			$json = self::json_encode_safe( $value );
			return self::truncate_string( $json, 65535 );
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_numeric( $value ) ) {
			return (string) $value;
		}

		// Fallback for other types.
		return (string) $value;
	}

	/**
	 * Truncate string to maximum length (with UTF-8 safety)
	 *
	 * @since 0.1.0
	 * @static
	 * @param string $str        String to truncate.
	 * @param int    $max_length Maximum length in bytes.
	 * @return string Truncated string
	 */
	public static function truncate_string( $str = '', $max_length = 65535 ) {
		if ( empty( $str ) || strlen( $str ) <= $max_length ) {
			return $str;
		}

		// Truncate to max length, handling UTF-8 safely.
		$truncated = substr( $str, 0, $max_length );

		// Trim back past any incomplete multi-byte UTF-8 sequence.
		$len = strlen( $truncated );
		while ( $len > 0 && ord( $truncated[ $len - 1 ] ) >= 0x80 ) {
			$truncated = substr( $truncated, 0, -1 );
			--$len;
		}

		return $truncated;
	}

	/**
	 * JSON encode safely with error handling
	 *
	 * @since 0.1.0
	 * @static
	 * @param mixed $value Value to encode.
	 * @return string JSON-encoded string or error message
	 */
	public static function json_encode_safe( $value ) {
		$json = wp_json_encode( $value );

		if ( false === $json ) {
			$error = json_last_error_msg();
			return "JSON encoding error: {$error}";
		}

		return $json;
	}

	/**
	 * Validate a formatted log entry
	 *
	 * Ensures all 10 fields are present and of correct types.
	 *
	 * @since 0.1.0
	 * @static
	 * @param array $entry Entry to validate.
	 * @return bool True if valid
	 */
	public static function validate_entry( $entry = array() ) {
		$required_fields = array(
			'ability_slug',
			'source',
			'mcp_server_id',
			'user_id',
			'input',
			'output',
			'status',
			'duration_ms',
			'created_at',
		);

		foreach ( $required_fields as $field ) {
			if ( ! array_key_exists( $field, $entry ) ) {
				return false;
			}
		}

		return true;
	}
}
