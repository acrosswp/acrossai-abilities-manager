<?php
/**
 * REST sub-controller: list and single-item reads.
 *
 * Handles:
 *   GET /abilities          — paginated list with search, source, status filters
 *   GET /abilities/{id}     — single ability by integer ID
 *
 * All filtering, sorting, and pagination is delegated to query layer classes:
 * - source=db  → AcrossAI_Abilities_Query (DB table only, includes drafts)
 * - all others → AcrossAI_Ability_Registry_Query (WP registry + DB overrides merged)
 * (AC-QUERY-LAYER-FILTERING constraint — no post-query filter logic in REST controllers).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Abilities/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Formatter;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Registry_Query;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles read operations for the unified abilities catalog.
 *
 * @since 0.1.0
 */
class AcrossAI_Abilities_Read_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Abilities_Read_Controller|null
	 */
	protected static $_instance = null;

	/**
	 * DB query instance (custom abilities, source=db).
	 *
	 * @var AcrossAI_Abilities_Query
	 */
	private $db_query;

	/**
	 * Sitewide DB query instance (used for override lookups in registry merge).
	 *
	 * @var AcrossAI_Sitewide_Query
	 */
	private $sitewide_query;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Abilities_Read_Controller
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		$this->db_query       = AcrossAI_Abilities_Query::instance();
		$this->sitewide_query = AcrossAI_Sitewide_Query::instance();
	}

	/**
	 * Register REST routes owned by this controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		$permission = array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' );

		// List.
		register_rest_route(
			AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE,
			'/abilities',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_abilities' ),
					'permission_callback' => $permission,
					'args'                => array(
						'page'     => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'minimum'           => 1,
							'maximum'           => 100,
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'search'   => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'orderby'  => array(
							'type'    => 'string',
							'enum'    => array( 'ability_slug', 'label', 'status', 'source', 'updated_at', 'created_at' ),
							'default' => 'ability_slug',
						),
						'order'    => array(
							'type'    => 'string',
							'enum'    => array( 'asc', 'desc', 'ASC', 'DESC' ),
							'default' => 'asc',
						),
						'source'   => array(
							'type' => 'string',
							'enum' => array( 'db', 'plugin', 'theme', 'core', '' ),
						),
						'status'   => array(
							'type' => 'string',
							'enum' => array( 'draft', 'publish', '' ),
						),
						'category' => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'editable' => array(
							'type' => 'string',
							'enum' => array( 'true', 'false', '1', '0', '' ),
						),
					),
				),
			)
		);

		// Single item.
		register_rest_route(
			AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE,
			'/abilities/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ability' ),
					'permission_callback' => $permission,
					'args'                => array(
						'id' => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
							'minimum'           => 1,
						),
					),
				),
			)
		);
	}

	/**
	 * Handle GET /abilities — paginated list.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_abilities( \WP_REST_Request $request ) {
		$source = (string) ( $request->get_param( 'source' ) ?? '' );

		// source=db: query the custom abilities table only (includes drafts).
		if ( 'db' === $source ) {
			$params   = array(
				'page'     => $request->get_param( 'page' ),
				'per_page' => $request->get_param( 'per_page' ),
				'search'   => $request->get_param( 'search' ),
				'orderby'  => $request->get_param( 'orderby' ),
				'order'    => $request->get_param( 'order' ),
				'source'   => $source,
				'status'   => $request->get_param( 'status' ),
				'category' => $request->get_param( 'category' ),
				'editable' => $request->get_param( 'editable' ),
			);
			$result   = $this->db_query->get_paginated( $params );
			$response = rest_ensure_response( AcrossAI_Abilities_Formatter::format_collection( $result['items'] ) );
			$response->header( 'X-WP-Total', (string) $result['total'] );
			$response->header( 'X-WP-TotalPages', (string) $result['pages'] );
			return $response;
		}

		// All other cases: merge WP registry abilities with DB overrides.
		// This returns all WP-registered abilities (plugin/theme/core + published DB abilities)
		// merged with any stored site overrides — the same data set shown on the sitewide page.
		$registry_params = array(
			'search'   => (string) ( $request->get_param( 'search' ) ?? '' ),
			'orderby'  => 'ability_slug' === $request->get_param( 'orderby' ) ? 'slug' : (string) ( $request->get_param( 'orderby' ) ?? 'slug' ),
			'order'    => (string) ( $request->get_param( 'order' ) ?? 'asc' ),
			'source'   => $source,
			'page'     => (int) ( $request->get_param( 'page' ) ?? 1 ),
			'per_page' => (int) ( $request->get_param( 'per_page' ) ?? 20 ),
		);

		$result   = AcrossAI_Ability_Registry_Query::query( $registry_params, $this->sitewide_query );
		$response = rest_ensure_response( AcrossAI_Abilities_Formatter::format_merged_collection( $result['abilities'] ) );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	/**
	 * Handle GET /abilities/{id} — single ability.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_ability( \WP_REST_Request $request ) {
		$id  = (int) $request->get_param( 'id' );
		$row = $this->db_query->get_ability_by_id( $id );

		if ( null === $row ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( AcrossAI_Abilities_Formatter::format_for_response( $row ) );
	}
}
