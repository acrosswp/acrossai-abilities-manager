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
 * @link       https://github.com/WPBoilerplate/acrossai-abilities-manager
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
 * @author     WPBoilerplate <contact@wpboilerplate.com>
 */
final class Main {

	/**
	 * The single instance of the class.
	 *
	 * @var AcrossAI_Abilities_Manager
	 * @since 0.0.1
	 */
	protected static $_instance = null;

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
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
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

		$this->set_locale();

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
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
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
	 * Define constant if not already set
	 *
	 * @param  string      $name
	 * @param  string|bool $value
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
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
	 * - AcrossAI_Abilities_Manager\Admin\I18n. Defines internationalization functionality.
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
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the AcrossAI_Abilities_Manager_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    0.0.1
	 * @access   private
	 */
	private function set_locale() {
		$i18n = new AcrossAI_I18n();

		// Now attach it to `init`, not `plugins_loaded`.
		$this->loader->add_action( 'init', $i18n, 'do_load_textdomain' );
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

		// Sitewide Ability Manager — DB table setup and REST routes via singleton pattern.
		// Table instance call makes the class available; BerlinDB hooks maybe_upgrade() to admin_init.
		\AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table::instance();

		// Register REST routes on rest_api_init.
		$rest_controller = \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Rest_Controller::instance();
		$this->loader->add_action( 'rest_api_init', $rest_controller, 'register_routes' );

		// Collect MCP servers at priority 20, after McpAdapter initialises at priority 15.
		$mcp_servers_list = \WPBoilerplate\McpServersList\McpServersList::instance();
		$this->loader->add_action( 'rest_api_init', $mcp_servers_list, 'collect', 20 );

		// Register Access Control REST routes and admin notice for absent library (SAC-01).
		$sitewide_ac = \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Access_Control::instance();
		$this->loader->add_action( 'rest_api_init', $sitewide_ac, 'register_rest_api' );
		$this->loader->add_action( 'admin_notices', $sitewide_ac, 'maybe_show_library_notice' );

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

		$plugin_public = new \AcrossAI_Abilities_Manager\Public\Main( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Ability Override Processor — boot at plugins_loaded P20 and bust cache on override save.
		// Named variable before Loader calls satisfies the Boot Flow Rule (SEC-PLAN-002).
		$override_processor = \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Ability_Override_Processor::instance();
		$this->loader->add_action( 'plugins_loaded', $override_processor, 'boot_hook', 20 );
		$this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );

		// Ability Execution Logger — create DB table, boot logger at P20, register REST routes.
		\AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Table::instance();

		$logger = \AcrossAI_Abilities_Manager\Includes\Modules\Logger\AcrossAI_Ability_Logger::instance();
		$this->loader->add_action( 'plugins_loaded', $logger, 'boot', 20 );

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
