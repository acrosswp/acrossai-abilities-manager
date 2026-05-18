<?php
/**
 * REST sub-controller: read abilities (US1).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Rest_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Merger;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Registry_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Protected_Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles GET /sitewide/abilities and GET /sitewide/abilities/{slug}.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Abilities_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Sitewide_Abilities_Controller|null
	 */
	protected static $_instance = null;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Sitewide_Abilities_Controller
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * BerlinDB query instance.
	 *
	 * @var AcrossAI_Sitewide_Query
	 */
	private $db_query;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->db_query = AcrossAI_Sitewide_Query::instance();
	}

	/**
	 * Register REST routes owned by this controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		// US1: Browse all abilities.
		register_rest_route(
			AcrossAI_Sitewide_Rest_Controller::REST_NAMESPACE,
			'/sitewide/abilities',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_abilities' ),
					'permission_callback' => array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' ),
					'args'                => array(
						'page'         => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'     => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'maximum'           => 100,
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'search'       => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'orderby'      => array(
							'type'    => 'string',
							'enum'    => array( 'slug', 'provider', 'source', 'status' ),
							'default' => 'slug',
						),
						'order'        => array(
							'type'    => 'string',
							'enum'    => array( 'asc', 'desc' ),
							'default' => 'asc',
						),
						'source'       => array(
							'type' => 'string',
							'enum' => array( 'plugin', 'theme', 'core', 'db', '' ),
						),
						'has_override' => array(
							'type' => 'boolean',
						),
					),
				),
			)
		);

		// US1: Get single ability.
		register_rest_route(
			AcrossAI_Sitewide_Rest_Controller::REST_NAMESPACE,
			'/sitewide/abilities/(?P<slug>[a-zA-Z0-9\-_\/]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ability' ),
					'permission_callback' => array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_ability_slug' ),
							'validate_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'validate_ability_slug' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Handle GET /sitewide/abilities.
	 *
	 * Delegates all filter/sort/paginate logic to AcrossAI_Ability_Registry_Query (RF-03).
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_abilities( \WP_REST_Request $request ) {
		$params = array(
			'page'         => $request->get_param( 'page' ),
			'per_page'     => $request->get_param( 'per_page' ),
			'search'       => $request->get_param( 'search' ),
			'orderby'      => $request->get_param( 'orderby' ),
			'order'        => $request->get_param( 'order' ),
			'source'       => $request->get_param( 'source' ),
			'has_override' => $request->get_param( 'has_override' ),
		);

		$result = AcrossAI_Ability_Registry_Query::query( $params, $this->db_query );

		$response = rest_ensure_response( $result['abilities'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	/**
	 * Handle GET /sitewide/abilities/{slug}.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_ability( \WP_REST_Request $request ) {
		$slug = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $request->get_param( 'slug' ) );

		// Protected abilities cannot be accessed via REST endpoints.
		if ( AcrossAI_Protected_Abilities::is_protected( $slug ) ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new \WP_Error( 'rest_not_supported', __( 'Abilities API not available.', 'acrossai-abilities-manager' ), array( 'status' => 501 ) );
		}

		$registry = wp_get_ability( $slug );
		if ( empty( $registry ) ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}
		$registry = AcrossAI_Ability_Merger::normalize_registry( $registry );

		$override = $this->db_query->get_override_by_slug( $slug );
		$merged   = AcrossAI_Ability_Merger::merge( $registry, $override );

		/**
		 * Filters the merged ability data before it is returned in the REST response.
		 *
		 * Consumers MUST NOT remove 'slug', 'has_override', or alter field types —
		 * doing so will break client-side Redux store deserialization.
		 *
		 * @since 0.1.0
		 * @param array  $merged Merged ability data (registry + override).
		 * @param string $slug   Sanitized ability slug.
		 */
		$merged = (array) apply_filters( 'acrossai_abilities_sitewide_rest_response', $merged, $slug );

		return rest_ensure_response( $merged );
	}
}
