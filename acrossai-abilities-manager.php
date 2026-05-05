<?php
/**
 * Plugin Name:       AcrossAI Abilities Manager
 * Plugin URI:        https://github.com/AcrossWP/acrossai-abilities-manager
 * Description:       Manage WordPress Abilities metadata from a classic wp-admin UI.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.0
 * Author:            acrosswp
 * Author URI:        https://acrosswp.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       acrossai-abilities-manager
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Guard against double-define in case another plugin includes this file directly.
if ( ! defined( 'ACROSSAI_ABILITIES_MANAGER_VERSION' ) ) {
	define( 'ACROSSAI_ABILITIES_MANAGER_VERSION', '0.1.0' );
}

// Absolute path to the main plugin file, used for register_activation_hook() and plugin_basename().
if ( ! defined( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE' ) ) {
	define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE', __FILE__ );
}

// Absolute filesystem path to the plugin root directory (trailing slash included).
if ( ! defined( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_DIR' ) ) {
	define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Public URL to the plugin root directory (trailing slash included).
if ( ! defined( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL' ) ) {
	define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * PSR-4-style autoloader for the AcrossAI_Abilities_Manager namespace.
 *
 * Maps AcrossAI_Abilities_Manager\Foo\Bar to includes/Foo/Bar.php.
 * Only classes within the plugin's own namespace are handled here; all
 * others are passed through silently so the next registered autoloader
 * can take over.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'AcrossAI_Abilities_Manager\\';

		// Not our namespace — let the next registered autoloader handle it.
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file           = ACROSSAI_ABILITIES_MANAGER_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';

		// Only load the file when it actually exists to avoid fatal errors on typos.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Registers all plugin hooks after all other plugins have loaded.
 *
 * Runs on `plugins_loaded` so that any WordPress Abilities API functions
 * registered by other plugins are already available before this plugin
 * attaches its own hooks. Admin-only hooks are wrapped in is_admin() to
 * avoid unnecessary work on front-end requests.
 *
 * @return void
 */
function acrossai_abilities_manager_bootstrap(): void {
	// Admin hooks are skipped on front-end requests to reduce overhead.
	if ( is_admin() ) {
		add_action( 'admin_menu', array( 'AcrossAI_Abilities_Manager\Admin\Menu', 'register' ) );
		add_action( 'admin_init', array( 'AcrossAI_Abilities_Manager\Database\Schema', 'maybe_upgrade_table' ) );
		add_action( 'admin_init', array( 'AcrossAI_Abilities_Manager\Admin\Edit_Screen', 'handle_actions' ) );
		add_action( 'admin_init', array( 'AcrossAI_Abilities_Manager\Admin\Add_Ability_Page', 'register' ) );
		add_action( 'admin_enqueue_scripts', array( 'AcrossAI_Abilities_Manager\Admin\Edit_Screen', 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE ), array( 'AcrossAI_Abilities_Manager\Admin\Menu', 'plugin_action_links' ) );
	}
	add_action( 'wp_abilities_api_init', array( 'AcrossAI_Abilities_Manager\Runtime\Override_Applier', 'bootstrap' ), 0 );
	add_action( 'rest_api_init', array( 'AcrossAI_Abilities_Manager\REST\Custom_Abilities_Controller', 'register_rest_routes' ) );
	add_action( 'rest_api_init', array( 'AcrossAI_Abilities_Manager\Runtime\Mcp_Server_Filter', 'register' ), 5 );

	// Assign abilities to the default MCP server at initialization time via
	// the mcp_adapter_default_server_config filter (fires inside mcp_adapter_init
	// at priority 10 when DefaultServerFactory runs). Must be registered before
	// rest_api_init so the filter is in place when the adapter initializes.
	\AcrossAI_Abilities_Manager\Runtime\Mcp_Server_Assigner::register();

	// Cache MCP server list after the adapter finishes initializing (fires on rest_api_init
	// during REST requests). Priority 999 ensures all servers — including the default server
	// created at priority 10 — are already registered before we read the list.
	add_action( 'mcp_adapter_init', 'acrossai_abilities_manager_cache_mcp_servers', 999 );
}
add_action( 'plugins_loaded', 'acrossai_abilities_manager_bootstrap' );

/**
 * Caches the list of registered MCP servers in a transient so the admin UI
 * can display them without having to trigger MCP adapter initialization.
 *
 * @param mixed $adapter McpAdapter instance passed by the mcp_adapter_init action.
 * @return void
 */
function acrossai_abilities_manager_cache_mcp_servers( $adapter ): void {
	if ( ! method_exists( $adapter, 'get_servers' ) ) {
		return;
	}

	$servers = array();
	foreach ( $adapter->get_servers() as $server ) {
		$id    = method_exists( $server, 'get_server_id' ) ? $server->get_server_id() : ( method_exists( $server, 'get_id' ) ? $server->get_id() : '' );
		$label = method_exists( $server, 'get_server_name' ) ? $server->get_server_name() : ( method_exists( $server, 'get_name' ) ? $server->get_name() : $id );

		if ( '' !== $id ) {
			$servers[] = array(
				'id'    => $id,
				'label' => $label,
			);
		}
	}

	if ( ! empty( $servers ) ) {
		set_transient( 'aam_mcp_servers', $servers, HOUR_IN_SECONDS );
	}
}

/**
 * Activation hook: ensures the custom database table exists immediately after activation.
 *
 * The schema check also runs on every `admin_init`, but this closure handles
 * the edge case where the table does not yet exist the very first time the
 * plugin activates and admin_init has not fired yet.
 */
register_activation_hook(
	ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE,
	static function (): void {
		\AcrossAI_Abilities_Manager\Database\Schema::maybe_upgrade_table();
	}
);
