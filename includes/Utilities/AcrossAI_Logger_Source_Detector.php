<?php
/**
 * Logger source detection utility.
 *
 * Singleton utility class for detecting execution source context
 * (mcp, rest, cli, cron, ajax, direct) based on request context.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Utilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Source detector utility
 *
 * Provides methods to detect execution source based on request context.
 * Detection is deterministic: same context always returns same source.
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Source_Detector {

	/**
	 * Singleton instance
	 *
	 * @since 0.1.0
	 * @var self|null
	 */
	protected static $_instance = null;

	/**
	 * MCP context flag
	 *
	 * Set during MCP adapter pre-tool-call hook to indicate MCP execution context.
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private $is_mcp_context = false;

	/**
	 * MCP server ID for current execution
	 *
	 * Stashed during MCP adapter pre-tool-call hook.
	 *
	 * @since 0.1.0
	 * @var string|null
	 */
	private $mcp_server_id = null;

	/**
	 * Get singleton instance
	 *
	 * @since 0.1.0
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Private constructor — use instance() to obtain the singleton.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Detect execution source
	 *
	 * Returns one of: 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct'.
	 * Priority order ensures most specific context is returned first.
	 *
	 * @since 0.1.0
	 * @return string One of the 6 source types
	 */
	public function detect_source() {
		// Priority 1: MCP context (most specific).
		if ( $this->is_mcp_context() ) {
			return 'mcp';
		}

		// Priority 2: REST API.
		if ( $this->is_rest_context() ) {
			return 'rest';
		}

		// Priority 3: WP-CLI.
		if ( $this->is_cli_context() ) {
			return 'cli';
		}

		// Priority 4: WP-Cron.
		if ( $this->is_cron_context() ) {
			return 'cron';
		}

		// Priority 5: AJAX.
		if ( $this->is_ajax_context() ) {
			return 'ajax';
		}

		// Priority 6: Direct execution (fallback).
		return 'direct';
	}

	/**
	 * Check if currently in MCP context
	 *
	 * @since 0.1.0
	 * @return bool True if in MCP execution context
	 */
	public function is_mcp_context() {
		return $this->is_mcp_context;
	}

	/**
	 * Check if currently in REST API context
	 *
	 * @since 0.1.0
	 * @return bool True if in REST API request
	 */
	public function is_rest_context() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Check if currently in WP-CLI context
	 *
	 * @since 0.1.0
	 * @return bool True if running from WP-CLI
	 */
	public function is_cli_context() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Check if currently in WP-Cron context
	 *
	 * @since 0.1.0
	 * @return bool True if running from WP-Cron
	 */
	public function is_cron_context() {
		return wp_doing_cron();
	}

	/**
	 * Check if currently in AJAX context
	 *
	 * @since 0.1.0
	 * @return bool True if in AJAX request
	 */
	public function is_ajax_context() {
		return wp_doing_ajax();
	}

	/**
	 * Detect MCP server ID from context
	 *
	 * Returns stashed server ID if available, null otherwise.
	 * Only valid during MCP execution context.
	 *
	 * @since 0.1.0
	 * @return string|null MCP server ID or null
	 */
	public function detect_mcp_server_id() {
		return $this->mcp_server_id;
	}

	/**
	 * Set MCP context and server ID
	 *
	 * Called by logger during mcp_adapter_pre_tool_call hook.
	 * Used to stash MCP execution context for subsequent detection.
	 *
	 * @since 0.1.0
	 * @param string|null $server_id MCP server ID.
	 * @return void
	 */
	public function set_mcp_context( $server_id = null ) {
		$this->is_mcp_context = true;
		$this->mcp_server_id  = $server_id;
	}

	/**
	 * Clear MCP context
	 *
	 * Called by logger after ability execution to reset context for next execution.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function clear_mcp_context() {
		$this->is_mcp_context = false;
		$this->mcp_server_id  = null;
	}

	/**
	 * Validate source value
	 *
	 * Ensures source is one of the 6 valid types.
	 * Used for input validation.
	 *
	 * @since 0.1.0
	 * @param string $source Source value to validate.
	 * @return bool True if valid source type.
	 */
	public function is_valid_source( $source ) {
		$valid_sources = array( 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct' );
		return in_array( $source, $valid_sources, true );
	}
}
