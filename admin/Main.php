<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin
 * @since      0.0.1
 */

namespace AcrossAI_Abilities_Manager\Admin;

use AcrossAI_Abilities_Manager\Admin\Partials\LogsMenu;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/acrosswp/acrossai-abilities-manager
 * @since      0.0.1
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin
 * @author     AcrossWP <deepak@acrosswp.com>
 */
class Main {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.0.1
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * The js_asset_file of the backend
	 *
	 * @since    0.0.1
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $js_asset_file;

	/**
	 * The css_asset_file of the backend
	 *
	 * @since    0.0.1
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $css_asset_file;


	/**
	 * Asset manifest for the logger JS/CSS bundle.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array|null
	 */
	private $logger_asset_file;

	/**
	 * Asset manifest for the Custom Abilities JS/CSS bundle.
	 *
	 * @since    0.2.0
	 * @access   private
	 * @var      array|null
	 */
	private $abilities_asset_file;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.0.1
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		$this->js_asset_file  = include \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/backend.asset.php';
		$this->css_asset_file = include \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/css/backend.asset.php';

		// Load logger asset file if it exists (built by @wordpress/scripts build).
		$logger_asset_path = \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/logger.asset.php';
		if ( file_exists( $logger_asset_path ) ) {
			$this->logger_asset_file = include $logger_asset_path;
		}

		// Load Custom Abilities asset file if it exists (built by @wordpress/scripts build).
		$abilities_asset_path = \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/abilities.asset.php';
		if ( file_exists( $abilities_asset_path ) ) {
			$this->abilities_asset_file = include $abilities_asset_path;
		} elseif ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// Log a notice when the build artifact is absent so developers can diagnose missing builds.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'acrossai-abilities-manager: build/js/abilities.asset.php not found — run npm run build.' );
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    0.0.1
	 * @param    string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_styles( string $hook_suffix ) {
		if ( ! $this->is_manager_page( $hook_suffix )
			&& ! $this->is_logs_page( $hook_suffix )
			&& ! $this->is_settings_page( $hook_suffix ) ) {
			return;
		}

		// Enqueue logger styles only on Logs submenu page (T015: feature 006).
		if ( $this->logger_asset_file && $this->is_logs_page( $hook_suffix ) ) {
			wp_register_style(
				'acrossai-abilities-logger',
				\ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/css/logger.css',
				array(),
				$this->logger_asset_file['version']
			);
			wp_enqueue_style( 'acrossai-abilities-logger' );
		}

		// Enqueue Abilities Manager styles only on main manager page (feature 011).
		if ( $this->abilities_asset_file && $this->is_manager_page( $hook_suffix ) ) {
			wp_register_style(
				'acrossai-abilities-manager-abilities',
				\ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/css/abilities.css',
				array(),
				$this->abilities_asset_file['version']
			);
			wp_enqueue_style( 'acrossai-abilities-manager-abilities' );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    0.0.1
	 * @param    string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_scripts( string $hook_suffix ) {
		if ( ! $this->is_manager_page( $hook_suffix )
			&& ! $this->is_logs_page( $hook_suffix )
			&& ! $this->is_settings_page( $hook_suffix ) ) {
			return;
		}

		// Enqueue logger scripts only on Logs submenu page (T015: feature 006).
		if ( $this->logger_asset_file && $this->is_logs_page( $hook_suffix ) ) {
			wp_register_script(
				'acrossai-abilities-logger',
				\ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/js/logger.js',
				$this->logger_asset_file['dependencies'],
				$this->logger_asset_file['version'],
				true
			);
			wp_enqueue_script( 'acrossai-abilities-logger' );

			wp_add_inline_script(
				'acrossai-abilities-logger',
				'window.acrossaiAbilitiesLogger = ' . wp_json_encode(
					array(
						'restEndpoint' => untrailingslashit( rest_url( 'acrossai-abilities/v1/logger/logs' ) ),
						'nonce'        => wp_create_nonce( 'wp_rest' ),
					)
				) . ';',
				'before'
			);
		}

		// Enqueue Abilities Manager scripts only on main manager page (feature 011).
		if ( $this->abilities_asset_file && $this->is_manager_page( $hook_suffix ) ) {
			wp_register_script(
				'acrossai-abilities-manager-abilities',
				\ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/js/abilities.js',
				$this->abilities_asset_file['dependencies'],
				$this->abilities_asset_file['version'],
				true
			);
			wp_enqueue_script( 'acrossai-abilities-manager-abilities' );

			wp_add_inline_script(
				'acrossai-abilities-manager-abilities',
				'window.acrossaiAbilitiesManager = ' . wp_json_encode(
					array(
						'nonce'                    => wp_create_nonce( 'wp_rest' ),
						'rest_url'                 => untrailingslashit( rest_url() ),
						'rest_namespace'           => 'acrossai-abilities-manager/v1',
						'current_user_id'          => get_current_user_id(),
						'perPage'                  => (int) get_option( 'acrossai_abilities_per_page', 20 ),
						// Client rendering gate only — server authorization enforced by wpb-ac/v1 REST endpoints (SEC-018-02).
						'access_control_available' => \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control::instance()->is_available(),
					)
				) . ';',
				'before'
			);
		}
	}

	/**
	 * Check if currently viewing the Logs submenu page
	 *
	 * @since    0.1.0
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool True if on Logs submenu page.
	 */
	private function is_logs_page( string $hook_suffix ): bool {
		$logs_menu = LogsMenu::instance();
		return $hook_suffix === $logs_menu->get_hook_suffix();
	}

	/**
	 * Check if currently viewing the main Abilities Manager page.
	 *
	 * SC-011-04: Uses === strict comparison to prevent type-coercion bypass.
	 *
	 * @since    0.3.0
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return bool True if on main Abilities Manager page.
	 */
	private function is_manager_page( string $hook_suffix ): bool {
		return 'toplevel_page_acrossai-abilities-manager' === $hook_suffix;
	}

	/**
	 * Checks whether the current admin screen is the settings page.
	 *
	 * @since    0.1.0
	 * @param string $hook_suffix The hook suffix for the current admin screen.
	 * @return bool
	 */
	private function is_settings_page( string $hook_suffix ): bool {
		return 'acrossai-abilities-manager_page_acrossai-abilities-settings' === $hook_suffix;
	}

	/**
	 * Add Settings link to plugins area
	 *
	 * @since 0.0.1
	 * @param array  $links Links array in which we would prepend our link.
	 * @param string $file  Current plugin basename.
	 * @return array
	 */
	public function plugin_action_links( $links, $file ) {
		if ( 'acrossai-abilities-manager/acrossai-abilities-manager.php' === $file ) {
			$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=acrossai-abilities-manager' ) ) . '">' . esc_html__( 'Settings', 'acrossai-abilities-manager' ) . '</a>';
			$logs_link     = '<a href="' . esc_url( admin_url( 'admin.php?page=acrossai-abilities-logs' ) ) . '">' . esc_html__( 'Logs', 'acrossai-abilities-manager' ) . '</a>';
			array_unshift( $links, $settings_link, $logs_link );
		}
		return $links;
	}
}
