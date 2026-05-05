<?php
/**
 * Access Control Manager.
 *
 * Initializes and manages the AccessControlManager and AccessControlUI
 * from the wpb-access-control library. Provides a singleton instance
 * and handles AJAX operations for access control updates.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Access_Control;

use WPBoilerplate\AccessControl\AccessControlManager;
use WPBoilerplate\AccessControl\Admin\AccessControlUI;
use WPBoilerplate\AccessControl\WpRoleProvider;
use WPBoilerplate\AccessControl\WpUserProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton manager for access control system initialization and UI rendering.
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 */
class Manager {

	/**
	 * Singleton instance of AccessControlManager.
	 *
	 * @var AccessControlManager|null
	 */
	private static ?AccessControlManager $manager = null;

	/**
	 * Singleton instance of AccessControlUI.
	 *
	 * @var AccessControlUI|null
	 */
	private static ?AccessControlUI $ui = null;

	/**
	 * Bootstrap the access control system.
	 *
	 * Initializes the AccessControlManager with the WpRoleProvider and
	 * registers providers. This should be called once during plugin bootstrap.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Ensure the access control table exists.
		\WPBoilerplate\AccessControl\AccessControlTable::maybe_create_table();

		if ( null === self::$manager ) {
			self::$manager = new AccessControlManager( 'acrossai_abilities_manager_access_control_providers' );

			// Register the WordPress Role Provider.
			add_filter(
				'acrossai_abilities_manager_access_control_providers',
				static function ( array $providers ): array {
					$providers[] = new WpRoleProvider();
					$providers[] = new WpUserProvider();
					return $providers;
				}
			);

			// Ensure UI is initialized with the manager.
			if ( null === self::$ui ) {
				self::$ui = new AccessControlUI( self::$manager );
			}
		}
	}

	/**
	 * Get the AccessControlManager instance.
	 *
	 * Initializes if not already done.
	 *
	 * @return AccessControlManager
	 */
	public static function get_manager(): AccessControlManager {
		if ( null === self::$manager ) {
			self::init();
		}
		return self::$manager;
	}

	/**
	 * Get the AccessControlUI instance.
	 *
	 * Initializes if not already done.
	 *
	 * @return AccessControlUI
	 */
	public static function get_ui(): AccessControlUI {
		if ( null === self::$ui ) {
			self::init();
		}
		return self::$ui;
	}

	/**
	 * Enqueue the AccessControlUI assets.
	 *
	 * Call this from an admin_enqueue_scripts hook during the edit screen.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		self::get_ui()->enqueue_assets();
	}

	/**
	 * Render the access control panel for a given resource.
	 *
	 * @param string $slug The ability slug (used as the resource identifier).
	 * @param array  $args Optional arguments passed to AccessControlUI::render().
	 *
	 * @return void
	 */
	public static function render( string $slug, array $args = array() ): void {
		$defaults = array(
			'submit_label' => __( 'Save', 'acrossai-abilities-manager' ),
			'default_type' => 'everyone',
		);
		$args     = wp_parse_args( $args, $defaults );

		self::get_ui()->render( 'acrossai-abilities-manager', $slug, $args );
	}

	/**
	 * Check if the current user has access to an ability.
	 *
	 * This wraps the AccessControlManager::user_has_access() method for
	 * convenience and is typically used in runtime enforcement.
	 *
	 * @param int    $user_id The user ID to check access for.
	 * @param string $slug    The ability slug (resource identifier).
	 *
	 * @return bool True if the user has access, false otherwise.
	 */
	public static function user_has_access( int $user_id, string $slug ): bool {
		return self::get_manager()->user_has_access( $user_id, 'acrossai-abilities-manager', $slug );
	}
}
