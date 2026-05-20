<?php
/**
 * Custom Ability REST Controller (Orchestrator)
 *
 * Orchestrator for REST endpoints with sub-controller delegation.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Modules/Custom_Ability/Rest
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\Rest;

/**
 * REST Controller singleton - orchestrates route registration and permission checks
 *
 * @since 1.0.0
 */
class AcrossAI_Custom_Ability_Rest_Controller {

	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	protected static $_instance = null;

	/**
	 * REST namespace
	 *
	 * @var string
	 */
	const NAMESPACE = 'acrossai-abilities-manager/v1';

	/**
	 * Get singleton instance
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Private constructor (singleton)
	 */
	private function __construct() {}

	/**
	 * Register REST routes
	 *
	 * TODO: Implement sub-controller delegation
	 *
	 * @return void
	 */
	public function register_routes() {
		// Phase 2 Implementation: T005, T006
	}

	/**
	 * Check permission (shared callback for all endpoints)
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return bool|WP_Error
	 */
	public function check_permission( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage custom abilities.', 'acrossai-abilities-manager' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}
}
