<?php
/**
 * REST sub-controller: create, update, and delete abilities.
 *
 * Handles:
 *   POST /abilities          — create a new db-managed ability (slug prefix injected)
 *   POST /abilities/{id}     — sparse update
 *   DELETE /abilities/{id}   — delete (db source only)
 *
 * Security contract:
 *   - All endpoints gate on manage_options via the shared orchestrator check_permission().
 *   - before_save hook receives sanitized fields only (SEC-02).
 *   - after_save hook receives the full re-read persisted row (BUG-PARTIAL-HOOK-FIELDS).
 *   - source field is server-controlled and never accepted from the request body.
 *   - For source≠db rows, identity/execution fields are stripped before write.
 *   - Audit fields (created_at, created_by) are never overwritten on update.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Abilities/Rest
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Validator;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Sanitizer;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Formatter;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Handles write operations for database-managed abilities.
 *
 * @since 0.1.0
 */
class AcrossAI_Abilities_Write_Controller {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Abilities_Write_Controller|null
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
	 * @return AcrossAI_Abilities_Write_Controller
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
		$permission = array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' );

		// Create.
		register_rest_route(
			AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE,
			'/abilities',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_ability' ),
					'permission_callback' => $permission,
				),
			)
		);

		// Update + Delete by integer ID.
		register_rest_route(
			AcrossAI_Abilities_Rest_Controller::REST_NAMESPACE,
			'/abilities/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_ability' ),
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
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_ability' ),
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
	 * Handle POST /abilities — create a new database-managed ability.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_ability( \WP_REST_Request $request ) {
		// Sanitize inputs.
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request( $request );

		// Validate.
		$valid = AcrossAI_Abilities_Validator::validate_ability( $fields, true );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		// Build full ability_slug from suffix + prefix injection.
		$suffix = (string) ( $fields['slug_suffix'] ?? '' );
		unset( $fields['slug_suffix'] );
		$fields['ability_slug'] = 'acrossai-abilities/' . $suffix;

		// Duplicate slug guard.
		if ( $this->db_query->slug_exists( $fields['ability_slug'] ) ) {
			return new \WP_Error(
				'rest_duplicate_slug',
				__( 'An ability with this slug already exists.', 'acrossai-abilities-manager' ),
				array( 'status' => 409 )
			);
		}

		// Server-controlled fields.
		$fields['source'] = 'db';
		if ( empty( $fields['status'] ) ) {
			$fields['status'] = 'draft';
		}
		if ( empty( $fields['callback_type'] ) ) {
			$fields['callback_type'] = 'noop';
		}

		/**
		 * Fires before creating an ability.
		 *
		 * @since 0.1.0
		 * @param array $fields Sanitized fields to insert.
		 */
		do_action( 'acrossai_abilities_before_create', $fields );

		$new_id = $this->db_query->insert_ability( $fields );
		if ( false === $new_id ) {
			return new \WP_Error( 'rest_create_failed', __( 'Failed to create ability.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		$saved_row = $this->db_query->get_ability_by_id( $new_id );
		if ( null === $saved_row ) {
			return new \WP_Error( 'rest_create_failed', __( 'Ability was created but could not be retrieved.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after creating an ability — receives the full persisted row (BUG-PARTIAL-HOOK-FIELDS).
		 *
		 * @since 0.1.0
		 * @param \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row $saved_row Full persisted row.
		 */
		do_action( 'acrossai_abilities_after_create', $saved_row );

		$response = rest_ensure_response( AcrossAI_Abilities_Formatter::format_for_response( $saved_row ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Handle POST /abilities/{id} — sparse update.
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_ability( \WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		// Verify row exists.
		$existing = $this->db_query->get_ability_by_id( $id );
		if ( null === $existing ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}

		// Sanitize inputs (sparse — only submitted fields are returned).
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_update_request( $request );

		// For source≠db rows, strip identity/execution fields.
		if ( 'db' !== $existing->source ) {
			$fields = AcrossAI_Abilities_Sanitizer::strip_protected_fields_for_non_db( $fields );
		}

		if ( empty( $fields ) ) {
			// Nothing to write — return current row.
			return rest_ensure_response( AcrossAI_Abilities_Formatter::format_for_response( $existing ) );
		}

		// Validate submitted fields.
		$validate_context                  = $fields;
		$validate_context['callback_type'] = $fields['callback_type'] ?? $existing->callback_type;
		$valid                             = AcrossAI_Abilities_Validator::validate_ability( $validate_context, false );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		/**
		 * Fires before updating an ability — receives sanitized submitted fields only (SEC-02).
		 *
		 * @since 0.1.0
		 * @param int   $id     Ability row ID.
		 * @param array $fields Sanitized fields to update.
		 */
		do_action( 'acrossai_abilities_before_update', $id, $fields );

		$updated = $this->db_query->update_ability( $id, $fields );
		if ( ! $updated ) {
			return new \WP_Error( 'rest_update_failed', __( 'Failed to update ability.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		// Re-read the full saved row before after-save hook (BUG-PARTIAL-HOOK-FIELDS).
		$saved_row = $this->db_query->get_ability_by_id( $id );
		if ( null === $saved_row ) {
			return new \WP_Error( 'rest_update_failed', __( 'Ability was updated but could not be retrieved.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after updating an ability — receives the full persisted row (BUG-PARTIAL-HOOK-FIELDS).
		 *
		 * @since 0.1.0
		 * @param \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row $saved_row Full persisted row.
		 */
		do_action( 'acrossai_abilities_after_update', $saved_row );

		return rest_ensure_response( AcrossAI_Abilities_Formatter::format_for_response( $saved_row ) );
	}

	/**
	 * Handle DELETE /abilities/{id}.
	 *
	 * Only source=db rows may be deleted (FR-006).
	 *
	 * @since  0.1.0
	 * @param  \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_ability( \WP_REST_Request $request ) {
		$id = (int) $request->get_param( 'id' );

		$existing = $this->db_query->get_ability_by_id( $id );
		if ( null === $existing ) {
			return new \WP_Error( 'rest_not_found', __( 'Ability not found.', 'acrossai-abilities-manager' ), array( 'status' => 404 ) );
		}

		// Reject delete for non-db rows (FR-006).
		if ( 'db' !== $existing->source ) {
			return new \WP_Error(
				'rest_delete_forbidden',
				__( 'Only database-managed abilities may be deleted.', 'acrossai-abilities-manager' ),
				array( 'status' => 403 )
			);
		}

		/**
		 * Fires before deleting an ability.
		 *
		 * @since 0.1.0
		 * @param \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row $existing Row about to be deleted.
		 */
		do_action( 'acrossai_abilities_before_delete', $existing );

		$deleted = $this->db_query->delete_ability( $id );
		if ( ! $deleted ) {
			return new \WP_Error( 'rest_delete_failed', __( 'Failed to delete ability.', 'acrossai-abilities-manager' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after deleting an ability.
		 *
		 * @since 0.1.0
		 * @param int    $id   Deleted ability row ID.
		 * @param string $slug Deleted ability slug.
		 */
		do_action( 'acrossai_abilities_after_delete', $id, $existing->ability_slug );

		$response = rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
		$response->set_status( 204 );
		return $response;
	}
}
