<?php
/**
 * MCP Tools Pass-through Module
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/McpToolsPassthrough
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Bridges the per-ability pass_as_tool flag into the mcp-adapter server-config filter.
 *
 * Hooked to `mcp_adapter_server_config` (priority 10, 2 args) via Main.php.
 * No hooks are registered inside this class — all wiring is in Main.php (AC-HOOKS-MAIN).
 *
 * @since 0.1.0
 */
class AcrossAI_Mcp_Tools_Passthrough {

	/**
	 * Singleton instance.
	 *
	 * @since  0.1.0
	 * @static
	 * @var AcrossAI_Mcp_Tools_Passthrough|null
	 */
	protected static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since  0.1.0
	 * @static
	 * @return AcrossAI_Mcp_Tools_Passthrough
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Inject opted-in ability slugs into every MCP server's tools[] array.
	 *
	 * Filter execution model: `mcp_adapter_server_config` assembles the server tool list;
	 * `mcp_adapter_expose_ability` (if used by mcp-adapter) operates at a different
	 * enforcement layer — pass-as-tool injection does not bypass per-server
	 * `mcp_adapter_expose_ability` restrictions. (TSEC-T04)
	 *
	 * @since      0.1.0
	 * @param array  $config     Server config passed by mcp-adapter.
	 * @param string $server_id  Server identifier (reserved for future per-server logic).
	 * @return array
	 */
	public function inject_tools( array $config, string $server_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $server_id is part of the mcp_adapter_server_config filter signature (2 args); reserved for future per-server logic.
		$extra = AcrossAI_Abilities_Query::instance()->get_pass_as_tool_slugs();
		if ( empty( $extra ) ) {
			return $config;
		}
		$existing        = isset( $config['tools'] ) && is_array( $config['tools'] ) ? $config['tools'] : array();
		$config['tools'] = array_values( array_unique( array_merge( $existing, $extra ) ) );
		return $config;
	}
}
