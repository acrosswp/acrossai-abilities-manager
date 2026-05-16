<?php
/**
 * Sitewide access-control integration wrapper.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide;

use WPBoilerplate\AccessControl\AccessControlManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sitewide access-control integration wrapper.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Access_Control {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	protected static $_instance = null;

	/**
	 * Plugin-scoped provider filter tag.
	 *
	 * @var string
	 */
	const PROVIDERS_FILTER = 'acrossai_abilities_access_control_providers';

	/**
	 * Access-control manager instance.
	 *
	 * @var AccessControlManager|null
	 */
	private $manager = null;

	/**
	 * Return singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
	}

	/**
	 * Boot the access-control manager.
	 *
	 * @return void
	 */
	public function boot_manager(): void {
		if ( ! $this->is_available() || $this->manager instanceof AccessControlManager ) {
			return;
		}

		$this->manager = new AccessControlManager( self::PROVIDERS_FILTER );
	}

	/**
	 * Register the library REST routes when available.
	 *
	 * @return void
	 */
	public function register_rest_api(): void {
		$manager = $this->get_manager();

		if ( null === $manager ) {
			return;
		}

		$manager->register_rest_api();
	}

	/**
	 * Check whether the access-control library is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( AccessControlManager::class );
	}

	/**
	 * Return the manager instance when available.
	 *
	 * @return AccessControlManager|null
	 */
	public function get_manager(): ?AccessControlManager {
		if ( ! $this->manager instanceof AccessControlManager ) {
			$this->boot_manager();
		}

		return $this->manager;
	}
}
