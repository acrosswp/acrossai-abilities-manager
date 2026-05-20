<?php
/**
 * Logger source detection utility.
 *
 * Static utility class for detecting execution source context
 * (mcp, rest, cli, cron, ajax, direct) based on request context.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Source detector utility
 *
 * Provides static methods to detect execution source based on request context.
 * Detection is deterministic: same context always returns same source.
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Source_Detector {

	/**
	 * MCP context flag
	 *
	 * Set during MCP adapter pre-tool-call hook to indicate MCP execution context.
	 *
	 * @since 0.1.0
	 * @static
	 * @var bool
	 */
	private static $is_mcp_context = false;

	/**
	 * MCP server ID for current execution
	 *
	 * Stashed during MCP adapter pre-tool-call hook.
	 *
	 * @since 0.1.0
	 * @static
	 * @var string|null
	 */
	private static $mcp_server_id = null;

	/**
	 * Detect execution source
	 *
	 * Returns one of: 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct'.
	 * Priority order ensures most specific context is returned first.
	 *
	 * @since 0.1.0
	 * @static
	 * @return string One of the 6 source types
	 */
	public static function detect_source() {
		// Priority 1: MCP context (most specific).
		if ( self::is_mcp_context() ) {
			return 'mcp';
		}

		// Priority 2: REST API.
		if ( self::is_rest_context() ) {
			return 'rest';
		}

		// Priority 3: WP-CLI.
		if ( self::is_cli_context() ) {
			return 'cli';
		}

		// Priority 4: WP-Cron.
		if ( self::is_cron_context() ) {
			return 'cron';
		}

		// Priority 5: AJAX.
		if ( self::is_ajax_context() ) {
			return 'ajax';
		}

		// Priority 6: Direct execution (fallback).
		return 'direct';
	}

	/**
	 * Check if currently in MCP context
	 *
	 * @since 0.1.0
	 * @static
	 * @return bool True if in MCP execution context
	 */
	public static function is_mcp_context() {
		return self::$is_mcp_context;
	}

	/**
	 * Check if currently in REST API context
	 *
	 * @since 0.1.0
	 * @static
	 * @return bool True if in REST API request
	 */
	public static function is_rest_context() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Check if currently in WP-CLI context
	 *
	 * @since 0.1.0
	 * @static
	 * @return bool True if running from WP-CLI
	 */
	public static function is_cli_context() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Check if currently in WP-Cron context
	 *
	 * @since 0.1.0
	 * @static
	 * @return bool True if running from WP-Cron
	 */
	public static function is_cron_context() {
		return wp_doing_cron();
	}

	/**
	 * Check if currently in AJAX context
	 *
	 * @since 0.1.0
	 * @static
	 * @return bool True if in AJAX request
	 */
	public static function is_ajax_context() {
		return wp_doing_ajax();
	}

	/**
	 * Detect MCP server ID from context
	 *
	 * Returns stashed server ID if available, null otherwise.
	 * Only valid during MCP execution context.
	 *
	 * @since 0.1.0
	 * @static
	 * @return string|null MCP server ID or null
	 */
	public static function detect_mcp_server_id() {
		return self::$mcp_server_id;
	}

	/**
	 * Set MCP context and server ID
	 *
	 * Called by logger during mcp_adapter_pre_tool_call hook.
	 * Used to stash MCP execution context for subsequent detection.
	 *
	 * @since 0.1.0
	 * @static
	 * @param string|null $server_id MCP server ID.
	 * @return void
	 */
	public static function set_mcp_context( $server_id = null ) {
		self::$is_mcp_context = true;
		self::$mcp_server_id  = $server_id;
	}

	/**
	 * Clear MCP context
	 *
	 * Called by logger after ability execution to reset context for next execution.
	 *
	 * @since 0.1.0
	 * @static
	 * @return void
	 */
	public static function clear_mcp_context() {
		self::$is_mcp_context = false;
		self::$mcp_server_id  = null;
	}

	/**
	 * Validate source value
	 *
	 * Ensures source is one of the 6 valid types.
	 * Used for input validation.
	 *
	 * @since 0.1.0
	 * @static
	 * @param string $source Source value to validate.
	 * @return bool True if valid source type.
	 */
	public static function is_valid_source( $source ) {
		$valid_sources = array( 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct' );
		return in_array( $source, $valid_sources, true );
	}
}
