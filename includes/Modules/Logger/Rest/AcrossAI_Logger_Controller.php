<?php
/**
 * AcrossAI Logger REST Orchestrator
 *
 * REST orchestrator class managing namespace, route registration,
 * and shared permission checks for logger endpoints.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger\Rest;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * REST logger orchestrator
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Controller extends WP_REST_Controller {

	/**
	 * REST namespace
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $namespace = 'acrossai-abilities/v1';

	/**
	 * Singleton instance
	 *
	 * @since 0.1.0
	 * @static
	 * @var AcrossAI_Logger_Controller|null
	 */
	protected static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 0.1.0
	 * @static
	 * @return AcrossAI_Logger_Controller
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor for singleton
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Register REST routes
	 *
	 * Delegates route registration to sub-controllers.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_routes() {
		// Delegate to sub-controller.
		$logs_controller = AcrossAI_Logger_Logs_Controller::instance();
		$logs_controller->register_routes();
	}

	/**
	 * Check permission for logger endpoints
	 *
	 * Verifies user has manage_options capability.
	 * Early permission check runs BEFORE any database queries (DEC-EARLY-404-REST-CHECK).
	 *
	 * @since 0.1.0
	 * @param WP_REST_Request $request REST request object.
	 * @return bool|WP_REST_Response True if allowed, WP_REST_Response with error if denied
	 */
	public function check_permission( $request ) {
		// Check capability (FR-010: only manage_options).
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'rest_forbidden',
					'message' => __( 'You do not have permission to access logger endpoints.', 'acrossai-abilities-manager' ),
				),
				403
			);
		}

		return true;
	}
}
