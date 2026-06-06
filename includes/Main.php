<?php
/**
 * The core plugin class file.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/acrosswp/acrossai-abilities-manager
 * @since      0.0.1
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.0.1
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 * @author     AcrossWP <deepak@acrosswp.com>
 */
final class Main {

	/**
	 * The single instance of the class.
	 *
	 * @var AcrossAI_Abilities_Manager
	 * @since 0.0.1
	 */
	protected static $instance = null;

	/**
	 * The autoloader instance.
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      Autoloader    $autoloader    The plugin autoloader instance.
	 */
	protected $autoloader;

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      AcrossAI_Abilities_Manager_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The plugin dir path
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      string    $plugin_path    The string for plugin dir path
	 */
	protected $plugin_path;

	/**
	 * The current version of the plugin.
	 *
	 * @since    0.0.1
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	protected $plugin_dir;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    0.0.1
	 */
	private function __construct() {

		$this->define_constants();

		$this->plugin_name = 'acrossai-abilities-manager';
		$this->version     = ACROSSAI_ABILITIES_MANAGER_VERSION;

		// Load the autoloader class manually before registering it.
		$this->plugin_dir = ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH;

		$this->load_composer_dependencies();

		$this->load_dependencies();

		$this->load_hooks();
	}

	/**
	 * Main AcrossAI_Abilities_Manager Instance.
	 *
	 * Ensures only one instance of WooCommerce is loaded or can be loaded.
	 *
	 * @since 0.0.1
	 * @static
	 * @see AcrossAI_Abilities_Manager()
	 * @return AcrossAI_Abilities_Manager - Main instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Define WCE Constants
	 */
	private function define_constants() {

		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_BASENAME', plugin_basename( \ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE ) );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH', plugin_dir_path( \ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE ) );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL', plugin_dir_url( \ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE ) );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_NAME_SLUG', $this->plugin_name );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_NAME', 'AcrossAI Abilities Manager' );
		$this->define( 'ACROSSAI_ABILITIES_MANAGER_VERSION', '0.0.1' );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param  string      $acrossai_name  The constant name.
	 * @param  string|bool $acrossai_value The constant value.
	 */
	private function define( $acrossai_name, $acrossai_value ) {
		if ( ! defined( $acrossai_name ) ) {
			define( $acrossai_name, $acrossai_value );
		}
	}

	/**
	 * Register all the hook once all the active plugins are loaded
	 *
	 * Uses the plugins_loaded to load all the hooks and filters
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	public function load_hooks() {

		/**
		 * Check if plugin can be loaded safely or not
		 *
		 * @since    0.0.1
		 */
		if ( apply_filters( 'acrossai_abilities_manager_load', true ) ) {
			$this->define_admin_hooks();
			$this->define_public_hooks();
		}
	}

	/**
	 * Load the required composer dependencies for this plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function load_composer_dependencies() {

		/**
		 * Add composer file
		 */
		$plugin_path = ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH;

		if ( file_exists( $plugin_path . 'vendor/autoload_packages.php' ) ) {
			require_once $plugin_path . 'vendor/autoload_packages.php';
		}
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - AcrossAI_Abilities_Manager\Admin\Loader. Orchestrates the hooks of the plugin.
	 * - AcrossAI_Abilities_Manager\Admin\Main. Defines all hooks for the admin area.
	 * - AcrossAI_Abilities_Manager_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function load_dependencies() {

		$this->loader = AcrossAI_Loader::instance();
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new \AcrossAI_Abilities_Manager\Admin\Main( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		$this->loader->add_action( 'plugin_action_links', $plugin_admin, 'plugin_action_links', 1000, 2 );
		/**
		 * Add the Plugin Main Menu
		 */
		$main_menu = new \AcrossAI_Abilities_Manager\Admin\Partials\Menu( $this->get_plugin_name(), $this->get_version() );
		$this->loader->add_action( 'admin_menu', $main_menu, 'main_menu' );

		// Execution Logs submenu page (Feature 006: T014).
		$logs_menu = \AcrossAI_Abilities_Manager\Admin\Partials\LogsMenu::instance();
		$this->loader->add_action( 'admin_menu', $logs_menu, 'register_submenu' );

		// Settings submenu page (Feature 019).
		$settings_menu = \AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu::instance();
		$this->loader->add_action( 'admin_menu', $settings_menu, 'register_submenu' );
		$this->loader->add_action( 'admin_init', $settings_menu, 'register_settings' );

		// Add-ons submenu page (Feature 026).
		// The AddonsPage constructor self-registers all WordPress hooks — no Loader wiring needed.
		// Accepted deviation from Boot Flow Rule: external package API does not expose individual hook methods.
		// Guarded per Constitution §V Integration Resilience: fails gracefully when vendor is absent.
		if ( class_exists( \WPBoilerplate\AddonsPage\AddonsPage::class ) ) {
			try {
				new \WPBoilerplate\AddonsPage\AddonsPage(
					'acrossai-abilities-manager',
					ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE,
					array(
						'fs_product_id' => '31230',
						'fs_public_key' => 'pk_0f116582ac1b8e608827094024b1f',
						'fs_slug'       => 'acrossai-abilities-manager',
					)
				);
			} catch ( \Throwable $e ) {
				$error_message = $e->getMessage();
				add_action(
					'admin_notices',
					function () use ( $error_message ) {
						if ( ! current_user_can( 'manage_options' ) ) {
							return;
						}
						printf(
							'<div class="notice notice-error"><p><strong>AcrossAI Abilities Manager:</strong> %s</p></div>',
							esc_html( $error_message )
						);
					}
				);
			}
		}

		// Abilities DB table setup — BerlinDB hooks maybe_upgrade() to admin_init.
		// Named variable before Loader call — Boot Flow Rule variable-first pattern (AC-HOOKS-MAIN).
		$abilities_table = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Table::instance();

		// Collect MCP servers at priority 20, after McpAdapter initialises at priority 15.
		$mcp_servers_list = \WPBoilerplate\McpServersList\McpServersList::instance();
		$this->loader->add_action( 'rest_api_init', $mcp_servers_list, 'collect', 20 );

		// Expose collected servers via REST endpoint (GET /wpb-mcp-servers-list/v1/servers).
		// Static callable -- class string satisfies DEC-UTILITY-STATIC-ONLY (no instance()).
		// WARNING: Do NOT hook `wpb_mcp_servers_list_rest_capability` to lower the required
		// capability below `manage_options`. The vendor filter is an external attack surface.
		$mcp_servers_rest = \WPBoilerplate\McpServersList\RestEndpoint::class;
		$this->loader->add_action( 'rest_api_init', $mcp_servers_rest, 'register', 20, 0 );

		// Register Access Control REST routes and admin notice for absent library (SAC-01).
		$abilities_ac = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control::instance();
		$this->loader->add_action( 'rest_api_init', $abilities_ac, 'register_rest_api' );
		$this->loader->add_action( 'admin_notices', $abilities_ac, 'maybe_show_library_notice' );

		// Abilities module REST routes (Spec 009).
		// Named variable before Loader call — Boot Flow Rule variable-first pattern.
		$abilities_rest = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest\AcrossAI_Abilities_Rest_Controller::instance();
		$this->loader->add_action( 'rest_api_init', $abilities_rest, 'register_routes' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new \AcrossAI_Abilities_Manager\Front\Main( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Ability Override Processor — boot at plugins_loaded P20 and bust cache on override save.
		// Named variable before Loader calls satisfies the Boot Flow Rule (SEC-PLAN-002).
		$override_processor = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Ability_Override_Processor::instance();
		$this->loader->add_action( 'plugins_loaded', $override_processor, 'boot_hook', 20 );
		// CRITICAL-01 fix (sub-change 11d): wire bust_cache_hook to all three Abilities lifecycle hooks.
		$this->loader->add_action( 'acrossai_abilities_after_create', $override_processor, 'bust_cache_hook' );
		$this->loader->add_action( 'acrossai_abilities_after_update', $override_processor, 'bust_cache_hook' );
		$this->loader->add_action( 'acrossai_abilities_after_delete', $override_processor, 'bust_cache_hook' );

		// Ability Execution Logger — create DB table, boot logger at P20, register REST routes.
		\AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Table::instance();

		$logger = \AcrossAI_Abilities_Manager\Includes\Modules\Logger\AcrossAI_Ability_Logger::instance();
		$this->loader->add_filter( 'mcp_adapter_pre_tool_call', $logger, 'capture_mcp_server_id', 5, 4 );
		$this->loader->add_action( 'wp_before_execute_ability', $logger, 'start_pending_entry', 10, 2 );
		$this->loader->add_action( 'wp_after_execute_ability', $logger, 'finish_pending_entry', 10, 3 );
		$this->loader->add_filter( 'wp_register_ability_args', $logger, 'wrap_permission_callback', 100001, 2 );
		$this->loader->add_action( 'acrossai_ability_logger_cleanup', $logger, 'cleanup_old_logs', 10, 0 );
		$this->loader->add_action( 'plugins_loaded', $logger, 'schedule_cleanup', 20, 0 );

		$logger_rest_controller = \AcrossAI_Abilities_Manager\Includes\Modules\Logger\Rest\AcrossAI_Logger_Controller::instance();
		$this->loader->add_action( 'rest_api_init', $logger_rest_controller, 'register_routes' );

		// Abilities Processor — registers published source=db abilities at wp_abilities_api_init P10 (Spec 009).
		// Named variable before Loader call — Boot Flow Rule variable-first pattern.
		$abilities_processor = \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Processor::instance();
		$this->loader->add_action( 'wp_abilities_api_init', $abilities_processor, 'register_abilities', 10 );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    0.0.1
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     0.0.1
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     0.0.1
	 * @return    AcrossAI_Abilities_Manager_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * The reference to the autoloader instance.
	 *
	 * @since     0.0.1
	 * @return    Autoloader    The plugin autoloader instance.
	 */
	public function get_autoloader() {
		return $this->autoloader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     0.0.1
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}
