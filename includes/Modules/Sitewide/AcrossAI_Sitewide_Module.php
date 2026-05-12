<?php
/**
 * Sitewide Ability Management module — registers all hooks for this feature.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide;

use AcrossAI_Abilities_Manager\Includes\Base\AcrossAI_Module_Base;
use AcrossAI_Abilities_Manager\Includes\Loader;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table;
use AcrossAI_Abilities_Manager\Admin\Partials\SitewideAbilityPage;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Module class for sitewide ability management.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Module extends AcrossAI_Module_Base {

	/**
	 * Return the module machine name.
	 *
	 * @since  0.1.0
	 * @return string
	 */
	public function get_name(): string {
		return 'sitewide';
	}

	/**
	 * Register all hooks for this module with the Loader.
	 *
	 * @since  0.1.0
	 * @param  Loader $loader The plugin hook-loader instance.
	 * @return void
	 */
	public function register_hooks( Loader $loader ): void {
		// Instantiate on every request so BerlinDB registers $wpdb->acrossai_abilities_overwrite
		// and hooks maybe_upgrade() to admin_init (creates/upgrades the table).
		// Instantiate BerlinDB table on every request to register $wpdb property and maybe_upgrade hook.
		new AcrossAI_Sitewide_Table();

		// Enqueue admin assets for the sitewide ability page via the Loader.
		$admin_page = new SitewideAbilityPage();
		$loader->add_action( 'admin_enqueue_scripts', $admin_page, 'do_enqueue_assets' );

		$controller = new AcrossAI_Sitewide_Rest_Controller( new AcrossAI_Sitewide_Query() );
		$loader->add_action( 'rest_api_init', $controller, 'register_routes' );
	}
}
