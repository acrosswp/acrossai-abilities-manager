<?php
namespace AcrossAI_Abilities_Manager\Admin;

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;


/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/WPBoilerplate/acrossai-abilities-manager
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
 * @author     WPBoilerplate <contact@wpboilerplate.com>
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
	 * Asset manifest for the sitewide ability manager JS/CSS bundle.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      array
	 */
	private $sitewide_asset_file;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.0.1
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		$this->js_asset_file       = include \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/backend.asset.php';
		$this->css_asset_file      = include \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/css/backend.asset.php';
		$this->sitewide_asset_file = include \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/sitewide.asset.php';
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    0.0.1
	 * @param    string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_styles( string $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'acrossai-abilities-manager' ) ) {
			return;
		}

		wp_register_style(
			'acrossai-abilities-sitewide',
			\ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/css/sitewide.css',
			array(),
			$this->sitewide_asset_file['version']
		);
		wp_enqueue_style( 'acrossai-abilities-sitewide' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    0.0.1
	 * @param    string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_scripts( string $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'acrossai-abilities-manager' ) ) {
			return;
		}

		wp_register_script(
			'acrossai-abilities-sitewide',
			\ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/js/sitewide.js',
			$this->sitewide_asset_file['dependencies'],
			$this->sitewide_asset_file['version'],
			true
		);
		wp_enqueue_script( 'acrossai-abilities-sitewide' );

		wp_add_inline_script(
			'acrossai-abilities-sitewide',
			'window.acrossaiAbilitiesSitewide = ' . wp_json_encode(
				array(
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'rest_url'        => untrailingslashit( rest_url() ),
					'current_user_id' => get_current_user_id(),
				)
			) . ';',
			'before'
		);
	}
}
