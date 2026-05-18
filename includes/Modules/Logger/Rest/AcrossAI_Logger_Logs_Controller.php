<?php
/**
 * AcrossAI Logger Logs REST Endpoint
 *
 * REST controller for read-only logs endpoint with filtering, sorting, and pagination.
 * All filtering logic happens in query builder (AC-QUERY-LAYER-FILTERING).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger\Rest;

use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Request;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\AcrossAI_Logger_Query;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * REST logs endpoint controller
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Logs_Controller extends WP_REST_Controller {

	/**
	 * REST namespace
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $namespace = 'acrossai-abilities/v1';

	/**
	 * REST resource
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $resource = 'logger/logs';

	/**
	 * Singleton instance
	 *
	 * @since 0.1.0
	 * @static
	 * @var AcrossAI_Logger_Logs_Controller|null
	 */
	protected static $_instance = null;

	/**
	 * Get singleton instance
	 *
	 * @since 0.1.0
	 * @static
	 * @return AcrossAI_Logger_Logs_Controller
	 */
	public static function instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Private constructor for singleton
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Register REST route
	 *
	 * Registers GET /acrossai-abilities/v1/logger/logs endpoint.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->resource,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( AcrossAI_Logger_Controller::instance(), 'check_permission' ),
				'args'                => array(
					'search'       => array(
						'type'              => 'string',
						'description'       => __( 'Search by ability slug (partial match)', 'acrossai-abilities' ),
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'orderby'      => array(
						'type'        => 'string',
						'description' => __( 'Column to sort by', 'acrossai-abilities' ),
						'required'    => false,
						'default'     => 'created_at',
						'enum'        => array( 'ability_slug', 'source', 'user_id', 'status', 'duration_ms', 'created_at' ),
					),
					'order'        => array(
						'type'        => 'string',
						'description' => __( 'Sort direction', 'acrossai-abilities' ),
						'required'    => false,
						'default'     => 'DESC',
						'enum'        => array( 'ASC', 'DESC' ),
					),
					'source'       => array(
						'type'        => 'string',
						'description' => __( 'Filter by source (comma-separated list)', 'acrossai-abilities' ),
						'required'    => false,
					),
					'status'       => array(
						'type'        => 'string',
						'description' => __( 'Filter by status (comma-separated list)', 'acrossai-abilities' ),
						'required'    => false,
					),
					'ability_slug' => array(
						'type'              => 'string',
						'description'       => __( 'Filter by ability slug (exact match)', 'acrossai-abilities' ),
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
					'user_id'      => array(
						'type'        => 'integer',
						'description' => __( 'Filter by user ID', 'acrossai-abilities' ),
						'required'    => false,
					),
					'page'         => array(
						'type'              => 'integer',
						'description'       => __( 'Page number', 'acrossai-abilities' ),
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page'     => array(
						'type'              => 'integer',
						'description'       => __( 'Records per page (max 100)', 'acrossai-abilities' ),
						'required'          => false,
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get logs from query builder
	 *
	 * Extracts and sanitizes request params, calls query builder,
	 * and returns paginated results with headers.
	 *
	 * @since 0.1.0
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response Response with logs array and pagination headers
	 */
	public function get_logs( $request ) {
		// Extract and sanitize parameters.
		$search       = $request->get_param( 'search' );
		$orderby      = $request->get_param( 'orderby' );
		$order        = $request->get_param( 'order' );
		$source       = $request->get_param( 'source' );
		$status       = $request->get_param( 'status' );
		$ability_slug = $request->get_param( 'ability_slug' );
		$user_id      = $request->get_param( 'user_id' );
		$page         = $request->get_param( 'page' );
		$per_page     = $request->get_param( 'per_page' );

		// Build query arguments.
		$args = array(
			'search'       => $search ? sanitize_text_field( $search ) : '',
			'orderby'      => $orderby ? sanitize_key( $orderby ) : 'created_at',
			'order'        => $order ? strtoupper( sanitize_key( $order ) ) : 'DESC',
			'source'       => $source ? $source : '',
			'status'       => $status ? $status : '',
			'ability_slug' => $ability_slug ? sanitize_key( $ability_slug ) : '',
			'user_id'      => $user_id ? absint( $user_id ) : 0,
			'page'         => $page ? absint( $page ) : 1,
			'per_page'     => $per_page ? absint( $per_page ) : 20,
		);

		// Call query builder (all filtering happens here — AC-QUERY-LAYER-FILTERING).
		$query_result = AcrossAI_Logger_Query::get_logs( $args );

		// Build response data.
		$logs_data = array();
		foreach ( $query_result['logs'] as $log ) {
			$logs_data[] = get_object_vars( $log );
		}

		$response_data = array(
			'logs'  => $logs_data,
			'total' => $query_result['total'],
			'pages' => $query_result['pages'],
		);

		// Build response.
		$response = new WP_REST_Response( $response_data, 200 );

		// Add pagination headers (X-WP-Total reflects filtered results — AC-QUERY-LAYER-FILTERING).
		$response->header( 'X-WP-Total', (int) $query_result['total'] );
		$response->header( 'X-WP-TotalPages', (int) $query_result['pages'] );

		return $response;
	}

	/**
	 * Get item schema for documentation
	 *
	 * @since 0.1.0
	 * @return array Schema definition
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'Logger Log Entry',
			'type'       => 'object',
			'properties' => array(
				'id'            => array(
					'description' => __( 'Log entry ID', 'acrossai-abilities' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
				),
				'ability_slug'  => array(
					'description' => __( 'Ability slug', 'acrossai-abilities' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
				),
				'source'        => array(
					'description' => __( 'Execution source (mcp, rest, cli, cron, ajax, direct)', 'acrossai-abilities' ),
					'type'        => 'string',
					'enum'        => array( 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct' ),
					'context'     => array( 'view' ),
				),
				'mcp_server_id' => array(
					'description' => __( 'MCP server ID (null if not from MCP)', 'acrossai-abilities' ),
					'type'        => array( 'string', 'null' ),
					'context'     => array( 'view' ),
				),
				'user_id'       => array(
					'description' => __( 'User ID (0 for non-user contexts)', 'acrossai-abilities' ),
					'type'        => array( 'integer', 'null' ),
					'context'     => array( 'view' ),
				),
				'input'         => array(
					'description' => __( 'Execution input (JSON or null)', 'acrossai-abilities' ),
					'type'        => array( 'string', 'null' ),
					'context'     => array( 'view' ),
				),
				'output'        => array(
					'description' => __( 'Execution output (JSON or null)', 'acrossai-abilities' ),
					'type'        => array( 'string', 'null' ),
					'context'     => array( 'view' ),
				),
				'status'        => array(
					'description' => __( 'Execution status', 'acrossai-abilities' ),
					'type'        => 'string',
					'enum'        => array( 'success', 'error', 'permission_denied' ),
					'context'     => array( 'view' ),
				),
				'duration_ms'   => array(
					'description' => __( 'Execution duration in milliseconds', 'acrossai-abilities' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
				),
				'created_at'    => array(
					'description' => __( 'Timestamp in format YYYY-MM-DD HH:MM:SS', 'acrossai-abilities' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
				),
			),
		);
	}
}
