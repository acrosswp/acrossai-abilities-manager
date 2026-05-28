<?php
/**
 * Core logger singleton for ability execution logging.
 *
 * Manages pending log entries, registers WordPress hooks,
 * and writes completed entries to database.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger;

use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Formatter;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Source_Detector;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Ability logger singleton
 *
 * Manages pending log entries stack, registers WordPress hooks,
 * and writes completed entries to database.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Logger {

	/**
	 * Singleton instance
	 *
	 * @since 0.1.0
	 * @static
	 * @var AcrossAI_Ability_Logger|null
	 */
	protected static $_instance = null;

	/**
	 * Pending entries stack
	 *
	 * Stack of incomplete log entries currently being processed.
	 * Supports concurrent/nested ability executions (EC-002).
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $pending_entries = array();

	/**
	 * MCP server ID for current context
	 *
	 * Stashed during mcp_adapter_pre_tool_call hook (P5).
	 * Used in start_pending_entry to populate mcp_server_id field.
	 *
	 * @since 0.1.0
	 * @var string|null
	 */
	protected $mcp_server_id = null;

	/**
	 * Get singleton instance
	 *
	 * @since 0.1.0
	 * @static
	 * @return AcrossAI_Ability_Logger
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Private constructor for singleton pattern
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->pending_entries = array();
		$this->mcp_server_id   = null;
	}

	/**
	 * Capture MCP server ID from execution context.
	 *
	 * Called on mcp_adapter_pre_tool_call hook P5 (before execution).
	 * Hook signature: apply_filters( 'mcp_adapter_pre_tool_call', $args, $tool_name, $mcp_tool, $server )
	 *
	 * @since 0.1.0
	 * @param array  $args      Tool arguments (filterable value — must be returned unchanged).
	 * @param string $tool_name Tool name being called.
	 * @param mixed  $mcp_tool  McpTool instance.
	 * @param mixed  $server    McpServer instance.
	 * @return array Unmodified $args (pass-through filter)
	 */
	public function capture_mcp_server_id( $args, $tool_name, $mcp_tool, $server ) {
		$server_id = null;
		if ( is_object( $server ) && method_exists( $server, 'get_server_id' ) ) {
			$server_id = $server->get_server_id();
		}

		$this->mcp_server_id = $server_id;
		AcrossAI_Logger_Source_Detector::instance()->set_mcp_context( $server_id );

		return $args;
	}

	/**
	 * Start recording a pending log entry
	 *
	 * Called on wp_before_execute_ability hook P10.
	 * Initializes pending entry with execution metadata and starts timer.
	 *
	 * @since 0.1.0
	 * @param string $ability_slug Ability slug being executed.
	 * @param array  $args Ability execution arguments.
	 * @return void
	 */
	public function start_pending_entry( $ability_slug, $args ) {
		// Record start time with microsecond precision.
		$start_time = microtime( true );

		// Detect source (uses context checks).
		$source = AcrossAI_Logger_Source_Detector::instance()->detect_source();

		// Get user ID (may be 0 for non-user contexts — EC-003).
		$user_id = get_current_user_id();

		// Build pending entry.
		$pending = array(
			'ability_slug'  => $ability_slug,
			'source'        => $source,
			'mcp_server_id' => AcrossAI_Logger_Source_Detector::instance()->detect_mcp_server_id(),
			'user_id'       => $user_id > 0 ? $user_id : 0,
			'input'         => $args,
			'start_time'    => $start_time,
		);

		// Push onto stack (supports concurrent executions — EC-002).
		array_push( $this->pending_entries, $pending );
	}

	/**
	 * Finish recording and write pending entry to database.
	 *
	 * Called on wp_after_execute_ability hook P10.
	 * Hook signature: do_action( 'wp_after_execute_ability', $name, $input, $result )
	 * Duration is calculated from $pending['start_time'] — WP core does not pass execution time.
	 *
	 * @since 0.1.0
	 * @param string $ability_slug Ability slug executed.
	 * @param mixed  $input        Input data passed to the ability (may be null for no-arg abilities).
	 * @param mixed  $result       Result from ability execution.
	 * @return void
	 */
	public function finish_pending_entry( $ability_slug, $input, $result ) {
		// Pop pending entry from stack.
		$pending = array_pop( $this->pending_entries );

		// Handle case where stack is empty (defensive).
		if ( null === $pending ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Logger: attempted to finish pending entry but stack is empty' );
			return;
		}

		// Calculate duration from start_time (WP core does not pass execution time).
		$duration_ms = isset( $pending['start_time'] )
			? (int) round( ( microtime( true ) - $pending['start_time'] ) * 1000 )
			: 0;

		// Detect result status.
		$status       = 'success';
		$output_value = $result;

		if ( is_wp_error( $result ) ) {
			$status       = 'error';
			$output_value = $result->get_error_message();
		} elseif ( $result instanceof \Exception ) {
			$status       = 'error';
			$output_value = $result->getMessage();
		} elseif ( false === $result || null === $result ) {
			$status       = 'error';
			$output_value = 'Execution returned false or null';
		}

		// Format input and output (truncation at 65535 bytes — EC-005).
		// Use $input from hook (same as $pending['input']) for the stored input field.
		$formatted_input  = AcrossAI_Logger_Formatter::format_value( $input );
		$formatted_output = AcrossAI_Logger_Formatter::format_value( $output_value );

		// Build complete entry (10 fields).
		$entry = array(
			'ability_slug'  => $ability_slug,
			'source'        => $pending['source'],
			'mcp_server_id' => $pending['mcp_server_id'],
			'user_id'       => $pending['user_id'],
			'input'         => $formatted_input,
			'output'        => $formatted_output,
			'status'        => $status,
			'duration_ms'   => $duration_ms,
			'created_at'    => current_time( 'mysql' ),
		);

		// Apply extensibility filter (Phase 1 spec).
		$entry = apply_filters( 'acrossai_ability_log_entry', $entry, $ability_slug, $pending['source'] );

		// Validate entry.
		if ( ! AcrossAI_Logger_Formatter::validate_entry( $entry ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Logger: entry validation failed' );
			return;
		}

		// Insert to database.
		$query  = AcrossAI_Ability_Logs_Query::instance();
		$result = $query->insert_log( $entry );

		if ( ! $result ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Logger: failed to insert log entry to database' );
		}

		// Clear MCP context after execution.
		AcrossAI_Logger_Source_Detector::instance()->clear_mcp_context();
	}

	/**
	 * Wrap ability permission callback to log permission denials
	 *
	 * Called on wp_register_ability_args hook P100001 (maximum priority).
	 * Intercepts permission callback to detect and log permission denials.
	 *
	 * @since 0.1.0
	 * @param array  $args Ability registration arguments.
	 * @param string $ability_slug Ability slug.
	 * @return array Modified $args with wrapped permission callback
	 */
	public function wrap_permission_callback( $args, $ability_slug ) {
		// Skip if no permission callback defined.
		if ( ! isset( $args['permission_callback'] ) || ! is_callable( $args['permission_callback'] ) ) {
			return $args;
		}

		$original_callback = $args['permission_callback'];

		// Wrap callback to intercept permission denials.
		// Use variadic args so any parameters the ability system passes (e.g. $input) are forwarded.
		$args['permission_callback'] = function ( ...$cb_args ) use ( $original_callback, $ability_slug ) {
			$result = call_user_func_array( $original_callback, $cb_args );

			// Log permission denials.
			if ( ! $result || is_wp_error( $result ) ) {
				// Create permission denial log entry.
				$source    = AcrossAI_Logger_Source_Detector::instance()->detect_source();
				$user_id   = get_current_user_id();
				$error_msg = is_wp_error( $result ) ? $result->get_error_message() : 'Permission denied';

				$entry = array(
					'ability_slug'  => $ability_slug,
					'source'        => $source,
					'mcp_server_id' => AcrossAI_Logger_Source_Detector::instance()->detect_mcp_server_id(),
					'user_id'       => $user_id > 0 ? $user_id : 0,
					'input'         => null,
					'output'        => $error_msg,
					'status'        => 'permission_denied',
					'duration_ms'   => 0,
					'created_at'    => current_time( 'mysql' ),
				);

				// Apply filter.
				$entry = apply_filters( 'acrossai_ability_log_entry', $entry, $ability_slug, $source );

				// Validate and insert.
				if ( AcrossAI_Logger_Formatter::validate_entry( $entry ) ) {
					$query = AcrossAI_Ability_Logs_Query::instance();
					$query->insert_log( $entry );
				}
			}

			return $result;
		};

		return $args;
	}

	/**
	 * Schedule daily log cleanup job
	 *
	 * Called during boot to schedule a recurring Action Scheduler job for log retention.
	 * This method is idempotent: safe to call multiple times.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function schedule_cleanup() {
		// Only schedule if Action Scheduler is available.
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		// Check if already scheduled (idempotent).
		$next_run = as_next_scheduled_action(
			'acrossai_ability_logger_cleanup',
			array(),
			'acrossai-abilities-logger'
		);

		if ( false !== $next_run ) {
			// Already scheduled.
			return;
		}

		// Schedule cleanup at 1 hour from now for first run.
		$first_run = time() + HOUR_IN_SECONDS;

		// Schedule recurring daily action.
		as_schedule_recurring_action(
			$first_run,
			DAY_IN_SECONDS,
			'acrossai_ability_logger_cleanup',
			array(),
			'acrossai-abilities-logger'
		);
	}

	/**
	 * Cleanup old logs via Action Scheduler job
	 *
	 * Called by Action Scheduler at the scheduled time.
	 * Deletes logs older than the configured retention period (T016, T017).
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function cleanup_old_logs() {
		// Get retention days from filter (default: 30 days).
		$retention_days = (int) apply_filters( 'acrossai_ability_log_retention_days', 30 );

		if ( $retention_days < 1 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Logger: Invalid retention days, skipping cleanup' );
			return;
		}

		// Calculate date cutoff (30 days ago).
		$cutoff_date = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

		// Delete old logs (T017: uses delete_logs_before_date method).
		$query  = AcrossAI_Ability_Logs_Query::instance();
		$result = $query->delete_logs_before_date( $cutoff_date );

		// Log result.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "Logger: Deleted {$result} log entries older than {$cutoff_date}" );
	}

	/**
	 * Unschedule cleanup jobs on deactivation
	 *
	 * Called during plugin deactivation.
	 * Removes all scheduled cleanup actions.
	 *
	 * @since 0.1.0
	 * @static
	 * @return void
	 */
	public static function unschedule_cleanup() {
		// Check if Action Scheduler is available.
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		// Unschedule all cleanup jobs.
		as_unschedule_all_actions( 'acrossai_ability_logger_cleanup', array(), 'acrossai-abilities-logger' );
	}

	/**
	 * Get pending entries count
	 *
	 * Used for testing concurrent execution handling.
	 *
	 * @since 0.1.0
	 * @return int Count of pending entries on stack
	 */
	public function get_pending_count() {
		return count( $this->pending_entries );
	}
}
