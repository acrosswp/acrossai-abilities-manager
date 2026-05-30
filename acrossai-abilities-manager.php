<?php
/**
 * Instantiates the AcrossAI Abilities Manager plugin
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager;

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/WPBoilerplate/acrossai-abilities-manager
 * @since             0.0.1
 * @package           AcrossAI_Abilities_Manager
 *
 * @wordpress-plugin
 * Plugin Name:       AcrossAI Abilities Manager
 * Plugin URI:        https://github.com/WPBoilerplate/acrossai-abilities-manager
 * Description:       AcrossAI Abilities Manager by WPBoilerplate
 * Version:           0.0.1
 * Requires PHP:      7.4
 * Requires at least: 6.9
 * Tested up to:      7.0
 * Author:            WPBoilerplate
 * Author URI:        https://github.com/WPBoilerplate/acrossai-abilities-manager
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       acrossai-abilities-manager
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 0.0.1 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE', __FILE__ );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/activator.php
 */
function acrossai_abilities_manager_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/AcrossAI_Activator.php';
	Includes\AcrossAI_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/deactivator.php
 */
function acrossai_abilities_manager_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/AcrossAI_Deactivator.php';
	Includes\AcrossAI_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'AcrossAI_Abilities_Manager\acrossai_abilities_manager_activate' );
register_deactivation_hook( __FILE__, 'AcrossAI_Abilities_Manager\acrossai_abilities_manager_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/Main.php';

use AcrossAI_Abilities_Manager\Includes\Main;

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.0.1
 */
function acrossai_abilities_manager_run() {

	$plugin = Main::instance();

	/**
	 * Run this plugin on the plugins_loaded functions
	 */
	add_action( 'plugins_loaded', array( $plugin, 'run' ), 0 );
}
acrossai_abilities_manager_run();
