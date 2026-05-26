<?php
/**
 * REST sub-controller: MCP/REST exposure collections.
 *
 * Handles:
 *   GET /abilities/exposures/{type}  — type ∈ tools | resources | prompts
 *
 * Security contract (PD-001 decision):
 *   - Admin-only (manage_options), same gate as all other Spec 009 endpoints.
 *   - Fail-closed on unknown/missing MCP server context: server-scoped rows are
 *     excluded when the current server ID cannot be resolved.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Abilities/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Formatter;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles MCP exposure collection endpoints.
 *
 * @since 0.1.0
 */
class AcrossAI_Abilities_Exposure_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Abilities_Exposure_Controller|null
	 */
	protected static $_instance = null;

	/**
	 * DB query instance.
	 *
	 * @var AcrossAI_Abilities_Query
	 */
	private $db_query;

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Abilities_Exposure_Controller
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
		$this->db_query = AcrossAI_Abilities_Query::instance();
	}

	/**
	 * Register REST routes owned by this controller.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE,
			'/abilities/exposures/(?P<type>tools|resources|prompts)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_exposures' ),
					'permission_callback' => array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' ),
					'args'                => array(
						'type'      => array(
							'type'              => 'string',
							'required'          => true,
							'enum'              => array( 'tools', 'resources', 'prompts' ),
							'sanitize_callback' => 'sanitize_key',
						),
						'server_id' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Handle GET /abilities/exposures/{type}.
	 *
	 * Maps URL type (tools/resources/prompts) to mcp_type (tool/resource/prompt).
	 * Fail-closed on unknown server context: rows with a non-empty mcp_servers list
	 * that does not include the resolved server ID are excluded.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_exposures( \WP_REST_Request $request ) {
		$type = sanitize_key( (string) $request->get_param( 'type' ) );

		// Map plural URL segment to mcp_type singular value.
		$type_map = array(
			'tools'     => 'tool',
			'resources' => 'resource',
			'prompts'   => 'prompt',
		);

		if ( ! isset( $type_map[ $type ] ) ) {
			return new \WP_Error( 'rest_invalid_param', __( 'Invalid exposure type.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
		}

		$mcp_type = $type_map[ $type ];
		$rows     = $this->db_query->by_mcp_type( $mcp_type, true );

		// Server-scoping filter (fail-closed on unknown server context).
		$server_id = sanitize_text_field( (string) ( $request->get_param( 'server_id' ) ?? '' ) );
		$rows      = $this->filter_by_server( $rows, $server_id );

		return rest_ensure_response( AcrossAI_Abilities_Formatter::format_exposure_collection( $rows ) );
	}

	/**
	 * Filter rows by server ID with fail-closed semantics.
	 *
	 * Rules:
	 *   - Row with null/empty mcp_servers  → always included (unrestricted).
	 *   - Row with non-empty mcp_servers + known server_id → included if server_id is in list.
	 *   - Row with non-empty mcp_servers + empty/unknown server_id → EXCLUDED (fail-closed).
	 *
	 * @since  0.1.0
	 * @param  \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row[] $rows      Rows to filter.
	 * @param  string                                                                                 $server_id Resolved current server ID (may be empty).
	 * @return \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row[]
	 */
	private function filter_by_server( array $rows, string $server_id ): array {
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $server_id ) {
					// Null or empty mcp_servers = unrestricted.
					if ( null === $row->mcp_servers || empty( $row->mcp_servers ) ) {
						return true;
					}
					// Non-empty mcp_servers: fail-closed on unknown server.
					if ( '' === $server_id ) {
						return false;
					}
					// Strict membership check.
					return in_array( $server_id, $row->mcp_servers, true );
				}
			)
		);
	}
}
