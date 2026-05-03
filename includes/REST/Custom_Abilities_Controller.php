<?php
/**
 * REST API controller for custom abilities.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\REST;

use AcrossAI_Abilities_Manager\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Handles REST API endpoints for custom abilities CRUD operations.
 *
 * Provides endpoints:
 * - GET /acrossai-abilities-manager/v1/custom-abilities
 * - GET /acrossai-abilities-manager/v1/custom-abilities/{slug}
 * - POST /acrossai-abilities-manager/v1/custom-abilities/{slug}
 * - DELETE /acrossai-abilities-manager/v1/custom-abilities/{slug}
 *
 * @since   0.1.0
 * @package AcrossAI_Abilities_Manager
 */
class Custom_Abilities_Controller extends \WP_REST_Controller {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'acrossai-abilities-manager/v1';
		$this->rest_base = 'custom-abilities';
	}

	/**
	 * Static hook callback to register REST routes.
	 *
	 * Called from rest_api_init hook.
	 *
	 * @return void
	 */
	public static function register_rest_routes(): void {
		$controller = new self();
		$controller->register_routes();
	}

	/**
	 * Registers all REST routes for custom abilities.
	 *
	 * @return void
	 */
	private function register_routes(): void {
		// GET /custom-abilities — List custom abilities with filters.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		// GET /custom-abilities/{slug} — Get single custom ability.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<slug>[a-zA-Z0-9_/-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'slug' => array(
							'description' => 'The slug of the custom ability.',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'slug' => array(
							'description' => 'The slug of the custom ability to delete.',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Checks if the user has permission to manage custom abilities (manage_options).
	 *
	 * @return bool|\WP_Error True if allowed, WP_Error if forbidden.
	 */
	public function check_admin_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				'Only administrators can manage custom abilities.',
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Retrieves a list of custom abilities with optional filters and pagination.
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error REST response or error.
	 */
	public function get_items( $request ) {
		$params = $request->get_query_params();
		$args   = array(
			'status'   => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '',
			'category' => isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : '',
			'search'   => isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '',
			'page'     => isset( $params['page'] ) ? (int) $params['page'] : 1,
			'per_page' => isset( $params['per_page'] ) ? (int) $params['per_page'] : 20,
			'orderby'  => isset( $params['orderby'] ) ? sanitize_key( $params['orderby'] ) : 'ability_slug',
			'order'    => isset( $params['order'] ) ? sanitize_key( strtoupper( $params['order'] ) ) : 'ASC',
		);

		$result = Repository::get_all_custom_abilities( $args );

		$response = new \WP_REST_Response( $result );
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', $result['pages'] );

		return $response;
	}

	/**
	 * Retrieves a single custom ability by slug.
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error REST response or error.
	 */
	public function get_item( $request ) {
		$slug    = $request->get_param( 'slug' );
		$ability = Repository::get_custom_ability( $slug );

		if ( ! $ability ) {
			return new \WP_Error(
				'rest_not_found',
				'Custom ability not found.',
				array( 'status' => 404 )
			);
		}

		return new \WP_REST_Response( $ability );
	}

	/**
	 * Creates or updates a custom ability.
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error REST response or error.
	 */
	public function create_item( $request ) {
		$slug = $request->get_param( 'slug' );
		$body = $request->get_json_params();

		if ( ! $slug ) {
			return new \WP_Error(
				'rest_invalid_param',
				'The slug parameter is required.',
				array( 'status' => 400 )
			);
		}

		// Validate required fields.
		if ( empty( $body['label'] ) ) {
			return new \WP_Error(
				'rest_missing_callback_param',
				'The label field is required.',
				array( 'status' => 400 )
			);
		}

		// Upsert the custom ability.
		$ability = Repository::upsert_custom_ability( $slug, $body );

		if ( null === $ability ) {
			return new \WP_Error(
				'rest_cannot_create',
				'Failed to create or update custom ability.',
				array( 'status' => 500 )
			);
		}

		$response = new \WP_REST_Response( $ability, 201 );
		return $response;
	}

	/**
	 * Deletes a custom ability by slug.
	 *
	 * @param \WP_REST_Request $request Full request object.
	 * @return \WP_REST_Response|\WP_Error REST response or error.
	 */
	public function delete_item( $request ) {
		$slug = $request->get_param( 'slug' );

		if ( ! $slug ) {
			return new \WP_Error(
				'rest_invalid_param',
				'The slug parameter is required.',
				array( 'status' => 400 )
			);
		}

		$deleted = Repository::delete_custom_ability( $slug );

		if ( ! $deleted ) {
			return new \WP_Error(
				'rest_cannot_delete',
				'Failed to delete custom ability.',
				array( 'status' => 500 )
			);
		}

		return new \WP_REST_Response( array( 'deleted' => true ) );
	}

	/**
	 * Retrieves the query parameters for collection requests.
	 *
	 * @return array<string, array> Associative array of query parameters.
	 */
	public function get_collection_params(): array {
		return array(
			'status'   => array(
				'description' => 'Filter by status (active/draft/archived).',
				'type'        => 'string',
			),
			'category' => array(
				'description' => 'Filter by category.',
				'type'        => 'string',
			),
			'search'   => array(
				'description' => 'Search in ability slug and label.',
				'type'        => 'string',
			),
			'page'     => array(
				'description' => 'The page number (1-based).',
				'type'        => 'integer',
				'default'     => 1,
			),
			'per_page' => array(
				'description' => 'Results per page; 0 disables pagination.',
				'type'        => 'integer',
				'default'     => 20,
			),
			'orderby'  => array(
				'description' => 'Sort by field (ability_slug, label, status, category, created_at).',
				'type'        => 'string',
				'default'     => 'ability_slug',
			),
			'order'    => array(
				'description' => 'Sort direction (ASC or DESC).',
				'type'        => 'string',
				'default'     => 'ASC',
			),
		);
	}

	/**
	 * Retrieves the schema for custom abilities.
	 *
	 * @return array Item schema.
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->schema;
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'Custom Ability',
			'type'       => 'object',
			'properties' => array(
				'id'                  => array(
					'description' => 'Unique identifier.',
					'type'        => 'integer',
					'readOnly'    => true,
				),
				'ability_slug'        => array(
					'description' => 'Unique slug for the custom ability.',
					'type'        => 'string',
					'required'    => true,
				),
				'label'               => array(
					'description' => 'Human-readable display name.',
					'type'        => 'string',
					'required'    => true,
				),
				'description'         => array(
					'description' => 'Full description of the ability.',
					'type'        => 'string',
				),
				'category'            => array(
					'description' => 'Ability category slug for organization.',
					'type'        => 'string',
				),
				'status'              => array(
					'description' => 'Status (active/draft/archived).',
					'type'        => 'string',
					'enum'        => array( 'active', 'draft', 'archived' ),
				),
				'input_schema'        => array(
					'description' => 'JSON Schema defining input parameters.',
					'type'        => 'object',
				),
				'output_schema'       => array(
					'description' => 'JSON Schema defining output structure.',
					'type'        => 'object',
				),
				'execute_callback'    => array(
					'description' => 'PHP callable or logic for execution.',
					'type'        => 'string',
				),
				'permission_callback' => array(
					'description' => 'PHP callable or logic for permission checks.',
					'type'        => 'string',
				),
				'readonly'            => array(
					'description' => 'Whether the ability is read-only.',
					'type'        => array( 'boolean', 'null' ),
				),
				'destructive'         => array(
					'description' => 'Whether the ability is destructive.',
					'type'        => array( 'boolean', 'null' ),
				),
				'idempotent'          => array(
					'description' => 'Whether the ability is idempotent.',
					'type'        => array( 'boolean', 'null' ),
				),
				'show_in_rest'        => array(
					'description' => 'Whether to expose in REST API.',
					'type'        => array( 'boolean', 'null' ),
				),
				'mcp_public'          => array(
					'description' => 'Whether to expose to MCP clients.',
					'type'        => array( 'boolean', 'null' ),
				),
				'mcp_type'            => array(
					'description' => 'MCP endpoint type (tools/resources/prompts).',
					'type'        => 'string',
				),
				'custom_meta'         => array(
					'description' => 'Additional extensible metadata.',
					'type'        => 'object',
				),
				'version'             => array(
					'description' => 'Semantic version string.',
					'type'        => 'string',
				),
				'deprecated_at'       => array(
					'description' => 'Deprecation timestamp.',
					'type'        => array( 'string', 'null' ),
				),
				'created_by'          => array(
					'description' => 'WordPress user ID of the creator.',
					'type'        => array( 'integer', 'null' ),
				),
				'created_at'          => array(
					'description' => 'Creation timestamp.',
					'type'        => 'string',
					'readOnly'    => true,
				),
				'updated_at'          => array(
					'description' => 'Last update timestamp.',
					'type'        => 'string',
					'readOnly'    => true,
				),
			),
		);

		return $this->schema;
	}
}
