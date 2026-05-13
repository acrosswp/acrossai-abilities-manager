<?php
/**
 * REST API controller for sitewide ability management.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Merger;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Registry_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Source_Detector;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * REST API controller for sitewide ability management.
 *
 * Routes are registered incrementally per user story phase.
 * All endpoints require manage_options capability + nonce verification.
 *
 * @since 0.1.0
 */
class AcrossAI_Sitewide_Rest_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'acrossai-abilities-manager/v1';

	/**
	 * BerlinDB query instance.
	 *
	 * @var AcrossAI_Sitewide_Query
	 */
	private $db_query;

	/**
	 * Constructor.
	 *
	 * @since  0.1.0
	 * @param  AcrossAI_Sitewide_Query $db_query BerlinDB query instance.
	 */
	public function __construct( AcrossAI_Sitewide_Query $db_query ) {
		$this->db_query = $db_query;
	}

	/**
	 * Register all REST routes for this controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		// US1: Browse all abilities.
		register_rest_route(
			self::REST_NAMESPACE,
			'/sitewide/abilities',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_abilities' ),
					'permission_callback' => array( $this, 'check_permission' ),
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

		// US5: Bulk action.
		register_rest_route(
			self::REST_NAMESPACE,
			'/sitewide/abilities/bulk',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_action' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slugs'  => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array( 'type' => 'string' ),
						),
						'action' => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array( 'allow', 'disallow', 'reset' ),
						),
					),
				),
			)
		);

		// US2: Toggle site_allowed.
		register_rest_route(
			self::REST_NAMESPACE,
			'/sitewide/abilities/(?P<slug>[a-zA-Z0-9\-_\/]+)/toggle',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle_ability' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug'         => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_ability_slug' ),
						),
						'site_allowed' => array(
							'type'     => 'boolean',
							'required' => true,
						),
					),
				),
			)
		);

		// US1: Get single ability.
		register_rest_route(
			self::REST_NAMESPACE,
			'/sitewide/abilities/(?P<slug>[a-zA-Z0-9\-_\/]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ability' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_ability_slug' ),
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_override' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug'         => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_ability_slug' ),
						),
						// All 8 overridable fields must be declared — missing args cause WP to reject
						// the entire request with 'Invalid parameter(s)' before the callback runs.
						'site_allowed' => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'readonly'     => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'destructive'  => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'idempotent'   => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'show_in_rest' => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'show_in_mcp'  => array(
							'required'          => false,
							'type'              => array( 'boolean', 'null' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_tri_state' ),
						),
						'mcp_type'     => array(
							'required'          => false,
							'type'              => array( 'string', 'null' ),
							'enum'              => array( 'tool', 'resource', 'prompt', null ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_mcp_type' ),
						),
						'mcp_servers'  => array(
							'required'          => false,
							'type'              => array( 'array', 'null' ),
							'items'             => array( 'type' => 'string' ),
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_mcp_servers_array' ),
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_override' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( 'AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer', 'sanitize_ability_slug' ),
						),
					),
				),
			)
		);

		// MCP servers list.
		register_rest_route(
			self::REST_NAMESPACE,
			'/sitewide/mcp-servers',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_mcp_servers' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Verify current user has manage_options capability and a valid REST nonce.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request Incoming request.
	 * @return bool|\WP_Error
	 */
	public function check_permission( \WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage abilities.', 'acrossai-abilities-manager' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Nonce verification failed.', 'acrossai-abilities-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// US1: Browse abilities
	// -------------------------------------------------------------------------

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

		return rest_ensure_response( $merged );
	}

	/**
	 * Handle POST /sitewide/abilities/{slug} — save per-ability override.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_override( \WP_REST_Request $request ) {
		$slug = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $request->get_param( 'slug' ) );

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new \WP_Error( 'rest_not_supported', __( 'Abilities API not available.', 'acrossai-abilities-manager' ), array( 'status' => 501 ) );
		}

		$registry = wp_get_ability( $slug );
		if ( empty( $registry ) ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}
		$registry = AcrossAI_Ability_Merger::normalize_registry( $registry );

		// Only collect fields that were explicitly sent in the request body.
		// Per-tab save: General tab sends 5 fields; MCP tab sends 3 fields.
		// Collecting absent fields via get_param() returns null and would
		// overwrite the other tab's saved DB values with NULL on UPDATE.
		// has_param() is true even when the field is explicitly null in the body
		// (intentional "clear this field"), so only truly absent fields are skipped.
		$fields      = array();
		$tri_state   = array( 'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'show_in_mcp' );
		foreach ( $tri_state as $field ) {
			if ( $request->has_param( $field ) ) {
				$fields[ $field ] = AcrossAI_Sanitizer::sanitize_tri_state( $request->get_param( $field ) );
			}
		}
		if ( $request->has_param( 'mcp_type' ) ) {
			$fields['mcp_type'] = AcrossAI_Sanitizer::sanitize_mcp_type( $request->get_param( 'mcp_type' ) );
		}
		if ( $request->has_param( 'mcp_servers' ) ) {
			$fields['mcp_servers'] = AcrossAI_Sanitizer::sanitize_mcp_servers_array( $request->get_param( 'mcp_servers' ) );
		}

		// Detect and set source (RF-04).
		$fields['source'] = AcrossAI_Ability_Source_Detector::detect( $registry );

		// If every submitted field is already at its registry default, nothing to write.
		// Do NOT auto-delete the override row for partial submits — the other tab may
		// still hold meaningful overrides. Full-row cleanup belongs to the DELETE endpoint.
		if ( AcrossAI_Ability_Merger::is_all_default( $fields, $registry ) ) {
			return rest_ensure_response( array( 'unchanged' => true ) );
		}

		/**
		 * Fires before saving an ability override.
		 *
		 * @since 0.1.0
		 * @param string $slug   Ability slug.
		 * @param array  $fields Sanitized fields to save.
		 */
		do_action( 'acrossai_abilities_sitewide_before_save', $slug, $fields );

		$saved = $this->db_query->save_override( $slug, $fields );

		if ( ! $saved ) {
			return new \WP_Error( 'rest_save_failed', __( 'Failed to save override.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after saving an ability override.
		 *
		 * @since 0.1.0
		 * @param string $slug   Ability slug.
		 * @param array  $fields Sanitized fields that were saved.
		 */
		do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields );

		$override = $this->db_query->get_override_by_slug( $slug );
		$merged   = AcrossAI_Ability_Merger::merge( $registry, $override );

		return rest_ensure_response( $merged );
	}

	/**
	 * Handle DELETE /sitewide/abilities/{slug} — remove stored override.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_override( \WP_REST_Request $request ) {
		$slug = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $request->get_param( 'slug' ) );

		if ( function_exists( 'wp_get_ability' ) ) {
			$registry = wp_get_ability( $slug );
			if ( empty( $registry ) ) {
				return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
			}
		}

		$deleted = $this->db_query->delete_override_by_slug( $slug );

		if ( ! $deleted ) {
			return rest_ensure_response(
				array(
					'slug'    => $slug,
					'deleted' => false,
					'message' => __( 'No override existed for this ability.', 'acrossai-abilities-manager' ),
				)
			);
		}

		return rest_ensure_response(
			array(
				'slug'    => $slug,
				'deleted' => true,
			)
		);
	}

	// -------------------------------------------------------------------------
	// US2: Toggle site_allowed
	// -------------------------------------------------------------------------

	/**
	 * Handle POST /sitewide/abilities/{slug}/toggle.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function toggle_ability( \WP_REST_Request $request ) {
		$slug         = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $request->get_param( 'slug' ) );
		$site_allowed = AcrossAI_Sanitizer::sanitize_tri_state( $request->get_param( 'site_allowed' ) );

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new \WP_Error( 'rest_not_supported', __( 'Abilities API not available.', 'acrossai-abilities-manager' ), array( 'status' => 501 ) );
		}

		$registry = wp_get_ability( $slug );
		if ( empty( $registry ) ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}
		$registry = AcrossAI_Ability_Merger::normalize_registry( $registry );

		$source = AcrossAI_Ability_Source_Detector::detect( $registry );
		$fields = array(
			'site_allowed' => $site_allowed,
			'source'       => $source,
		);

		$saved = $this->db_query->save_override( $slug, $fields );
		if ( ! $saved ) {
			return new \WP_Error( 'rest_save_failed', __( 'Failed to save toggle.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after toggling an ability override.
		 *
		 * @since 0.1.0
		 * @param string $slug   Ability slug.
		 * @param array  $fields Sanitized fields that were saved.
		 */
		do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields );

		$override = $this->db_query->get_override_by_slug( $slug );

		return rest_ensure_response(
			array(
				'slug'         => $slug,
				'site_allowed' => null !== $override ? $override->site_allowed : $site_allowed,
				'has_override' => null !== $override,
			)
		);
	}

	// -------------------------------------------------------------------------
	// US5: Bulk action
	// -------------------------------------------------------------------------

	/**
	 * Handle POST /sitewide/abilities/bulk.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function bulk_action( \WP_REST_Request $request ) {
		$raw_slugs = $request->get_param( 'slugs' );
		$action    = sanitize_text_field( (string) $request->get_param( 'action' ) );

		// Validate action before processing (SEC-01).
		$allowed_actions = array( 'allow', 'disallow', 'reset' );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error( 'rest_invalid_param', __( 'Invalid bulk action.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
		}

		if ( ! is_array( $raw_slugs ) ) {
			return new \WP_Error( 'rest_invalid_param', __( 'slugs must be an array.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
		}

		if ( count( $raw_slugs ) > 100 ) {
			return new \WP_Error( 'rest_too_many', __( 'Maximum 100 slugs per bulk request.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
		}

		$succeeded = 0;
		$failed    = 0;
		$skipped   = array();
		$results   = array();

		foreach ( $raw_slugs as $raw_slug ) {
			$slug = AcrossAI_Sanitizer::sanitize_ability_slug( (string) $raw_slug );

			if ( '' === $slug ) {
				$skipped[] = (string) $raw_slug;
				continue;
			}

			if ( function_exists( 'wp_get_ability' ) ) {
				$registry = wp_get_ability( $slug );
				if ( empty( $registry ) ) {
					$skipped[] = $slug;
					continue;
				}
				$registry = AcrossAI_Ability_Merger::normalize_registry( $registry );
			} else {
				$registry = array( 'slug' => $slug );
			}

			$ok = false;

			if ( 'reset' === $action ) {
				$ok = $this->db_query->delete_override_by_slug( $slug );
				// delete returns false if no record; treat as success.
				$ok = true;
			} else {
				$site_allowed = 'allow' === $action;
				$source       = AcrossAI_Ability_Source_Detector::detect( $registry );
				$fields       = array(
					'site_allowed' => $site_allowed,
					'source'       => $source,
				);
				$ok           = $this->db_query->save_override( $slug, $fields );

				if ( $ok ) {
					do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields );
				}
			}

			if ( $ok ) {
				++$succeeded;
				$results[] = array(
					'slug'   => $slug,
					'status' => 'success',
				);
			} else {
				++$failed;
				$results[] = array(
					'slug'   => $slug,
					'status' => 'failed',
				);
			}
		}

		return rest_ensure_response(
			array(
				'succeeded' => $succeeded,
				'failed'    => $failed,
				'skipped'   => $skipped,
				'results'   => $results,
			)
		);
	}

	// -------------------------------------------------------------------------
	// MCP Servers
	// -------------------------------------------------------------------------

	/**
	 * Handle GET /sitewide/mcp-servers.
	 *
	 * @since  0.1.0
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_mcp_servers() { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		if ( ! class_exists( 'WP\\MCP\\Core\\McpAdapter' ) ) {
			return rest_ensure_response( array() );
		}

		$servers = \WP\MCP\Core\McpAdapter::instance()->get_servers();

		if ( ! is_array( $servers ) ) {
			return rest_ensure_response( array() );
		}

		return rest_ensure_response( $servers );
	}
}
