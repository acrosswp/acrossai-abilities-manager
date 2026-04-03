<?php
/**
 * Plugin Name:       Abilities Editor
 * Description:       Override WordPress Abilities metadata from a classic wp-admin UI.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Author:            WordPress.org Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       abilities-editor
 *
 * @package Abilities_Editor
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ABE_VERSION' ) ) {
	define( 'ABE_VERSION', '0.1.0' );
}

if ( ! defined( 'ABE_PLUGIN_FILE' ) ) {
	define( 'ABE_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ABE_PLUGIN_DIR' ) ) {
	define( 'ABE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ABE_PLUGIN_URL' ) ) {
	define( 'ABE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Abilities_Editor\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative_class = substr( $class_name, strlen( $prefix ) );
		$file = ABE_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative_class ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

function abilities_editor_bootstrap(): void {
	if ( is_admin() ) {
		add_action( 'admin_menu', array( 'Abilities_Editor\Admin\Menu', 'register' ) );
		add_action( 'admin_init', array( 'Abilities_Editor\Database\Schema', 'maybe_upgrade_table' ) );
		add_action( 'admin_init', array( 'Abilities_Editor\Admin\Edit_Screen', 'handle_actions' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ABE_PLUGIN_FILE ), array( 'Abilities_Editor\Admin\Menu', 'plugin_action_links' ) );
	}
	add_action( 'rest_api_init', array( 'Abilities_Editor\REST\Overrides_Controller', 'register_routes' ) );
	add_action( 'wp_abilities_api_init', array( 'Abilities_Editor\Runtime\Override_Applier', 'bootstrap' ), 0 );
}
add_action( 'plugins_loaded', 'abilities_editor_bootstrap' );

register_activation_hook(
	ABE_PLUGIN_FILE,
	static function (): void {
		\Abilities_Editor\Database\Schema::maybe_upgrade_table();
	}
);
