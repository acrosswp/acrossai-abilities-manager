<?php
/**
 * Tests for AcrossAI_Abilities_Write_Controller.
 *
 * Covers: create, sparse update, delete, duplicate-slug (409), invalid-payload
 * (400), non-db delete protection (403), not-found (404), and explicit
 * forbidden-path (403) for all write routes.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_REST_Request;
use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Rest\AcrossAI_Abilities_Rest_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table;

/**
 * Class AbilitiesWriteControllerTest
 *
 * @since 0.1.0
 */
class AbilitiesWriteControllerTest extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * REST nonce for the admin user.
	 *
	 * @var string
	 */
	protected $nonce;

	/**
	 * Set up REST server, routes, table, and admin credentials.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		( new AcrossAI_Sitewide_Table() )->maybe_upgrade();

		$this->admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
		$this->nonce = wp_create_nonce( 'wp_rest' );

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		AcrossAI_Abilities_Rest_Controller::instance()->register_routes();
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down — remove test rows and reset user.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}acrossai_abilities WHERE ability_slug LIKE 'acrossai-abilities/test-%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a REST request with nonce and JSON body.
	 *
	 * @param  string $method  HTTP method.
	 * @param  string $route   Route path.
	 * @param  array  $body    Request body.
	 * @return WP_REST_Request
	 */
	private function make_request( string $method, string $route, array $body = [] ): WP_REST_Request {
		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'X-WP-Nonce', $this->nonce );
		$request->set_header( 'Content-Type', 'application/json' );
		if ( ! empty( $body ) ) {
			$request->set_body( wp_json_encode( $body ) );
		}
		return $request;
	}

	/**
	 * Create a db-source ability via POST and return the response.
	 *
	 * @param  string $slug_suffix Slug suffix.
	 * @param  array  $extra       Extra body fields.
	 * @return \WP_REST_Response
	 */
	private function create_ability( string $slug_suffix, array $extra = [] ) {
		$request = $this->make_request( 'POST', '/acrossai-abilities-manager/v1/abilities', array_merge(
			[ 'slug_suffix' => $slug_suffix, 'label' => 'Test', 'category' => 'general' ],
			$extra
		) );
		return $this->server->dispatch( $request );
	}

	// -------------------------------------------------------------------------
	// Forbidden-path coverage
	// -------------------------------------------------------------------------

	/**
	 * Non-admin user is rejected (403) on POST /abilities.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_create() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/abilities' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Non-admin is rejected (403) on POST /abilities/{id}.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_update() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/abilities/1' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Non-admin is rejected (403) on DELETE /abilities/{id}.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_delete() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'DELETE', '/acrossai-abilities-manager/v1/abilities/1' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// Create
	// -------------------------------------------------------------------------

	/**
	 * POST /abilities with valid payload returns 201 and prefixed slug.
	 *
	 * @return void
	 */
	public function test_create_ability_returns_201_with_prefixed_slug() {
		$response = $this->create_ability( 'test-create' );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'ability_slug', $data );
		$this->assertSame( 'acrossai-abilities/test-create', $data['ability_slug'] );
	}

	/**
	 * New ability defaults source=db and status=draft.
	 *
	 * @return void
	 */
	public function test_create_ability_defaults_source_db_and_draft() {
		$response = $this->create_ability( 'test-defaults' );
		$data     = $response->get_data();

		$this->assertSame( 'db', $data['source'] );
		$this->assertSame( 'draft', $data['status'] );
	}

	/**
	 * POST /abilities with duplicate slug returns 409.
	 *
	 * @return void
	 */
	public function test_create_ability_duplicate_slug_returns_409() {
		$this->create_ability( 'test-dup' );
		$response = $this->create_ability( 'test-dup' );

		$this->assertSame( 409, $response->get_status() );
	}

	/**
	 * POST /abilities missing slug_suffix returns 400.
	 *
	 * @return void
	 */
	public function test_create_ability_missing_slug_suffix_returns_400() {
		$request  = $this->make_request( 'POST', '/acrossai-abilities-manager/v1/abilities', [
			'label'    => 'No Slug',
			'category' => 'general',
		] );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * POST /abilities with invalid status returns 400.
	 *
	 * @return void
	 */
	public function test_create_ability_invalid_status_returns_400() {
		$response = $this->create_ability( 'test-bad-status', [ 'status' => 'archived' ] );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * POST /abilities with blocked php_code function returns 400.
	 *
	 * @return void
	 */
	public function test_create_ability_blocked_php_code_returns_400() {
		$response = $this->create_ability( 'test-blocked-code', [
			'callback_type'   => 'php_code',
			'callback_config' => [ 'code' => 'exec("id");' ],
		] );
		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Response body contains all expected fields (spot check for format_for_response shape).
	 *
	 * @return void
	 */
	public function test_create_ability_response_contains_required_fields() {
		$response = $this->create_ability( 'test-fields' );
		$data     = $response->get_data();

		foreach ( [ 'id', 'ability_slug', 'source', 'status', 'label', 'category', 'editable', 'created_at', 'updated_at' ] as $field ) {
			$this->assertArrayHasKey( $field, $data, "Response must contain field: $field" );
		}
		$this->assertTrue( $data['editable'], 'source=db rows must be editable=true' );
	}

	// -------------------------------------------------------------------------
	// Sparse update
	// -------------------------------------------------------------------------

	/**
	 * POST /abilities/{id} updates only submitted fields; untouched fields preserved.
	 *
	 * @return void
	 */
	public function test_sparse_update_preserves_untouched_fields() {
		$create  = $this->create_ability( 'test-update', [ 'category' => 'original-cat', 'label' => 'Original' ] );
		$data    = $create->get_data();
		$id      = $data['id'];

		$request  = $this->make_request( 'POST', "/acrossai-abilities-manager/v1/abilities/{$id}", [
			'label' => 'Updated Label',
		] );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$updated = $response->get_data();
		$this->assertSame( 'Updated Label', $updated['label'], 'Submitted field must be updated' );
		$this->assertSame( 'original-cat', $updated['category'], 'Untouched field must be preserved' );
	}

	/**
	 * POST /abilities/{nonexistent_id} returns 404.
	 *
	 * @return void
	 */
	public function test_update_nonexistent_ability_returns_404() {
		$request  = $this->make_request( 'POST', '/acrossai-abilities-manager/v1/abilities/999999', [
			'label' => 'X',
		] );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Update for source≠db row strips identity/execution fields (immutable-field matrix).
	 *
	 * @return void
	 */
	public function test_update_non_db_row_strips_protected_fields() {
		// Insert a plugin-source row directly.
		$query = AcrossAI_Abilities_Query::instance();
		$id    = $query->insert_ability( [
			'ability_slug'  => 'acrossai-abilities/test-plugin-row',
			'label'         => 'Plugin Ability',
			'category'      => 'plugin-cat',
			'source'        => 'plugin',
			'status'        => 'publish',
			'callback_type' => 'noop',
		] );

		// Try to override label (protected) and show_in_mcp (allowed).
		$request  = $this->make_request( 'POST', "/acrossai-abilities-manager/v1/abilities/{$id}", [
			'label'      => 'Overridden Label',
			'show_in_mcp' => true,
		] );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		// label must not have been changed.
		$this->assertSame( 'Plugin Ability', $data['label'], 'label must be protected for source≠db' );
		// show_in_mcp should have been applied.
		$this->assertSame( 1, (int) $data['show_in_mcp'], 'show_in_mcp must be editable for source≠db' );
	}

	// -------------------------------------------------------------------------
	// Delete
	// -------------------------------------------------------------------------

	/**
	 * DELETE /abilities/{id} for a db-source row returns 204 with deleted=true.
	 *
	 * @return void
	 */
	public function test_delete_db_ability_returns_204() {
		$create  = $this->create_ability( 'test-delete' );
		$id      = $create->get_data()['id'];

		$request  = $this->make_request( 'DELETE', "/acrossai-abilities-manager/v1/abilities/{$id}" );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 204, $response->get_status() );
	}

	/**
	 * DELETE /abilities/{id} for a source≠db row returns 403.
	 *
	 * @return void
	 */
	public function test_delete_non_db_ability_returns_403() {
		$query = AcrossAI_Abilities_Query::instance();
		$id    = $query->insert_ability( [
			'ability_slug'  => 'acrossai-abilities/test-plugin-nodelete',
			'source'        => 'plugin',
			'status'        => 'publish',
			'callback_type' => 'noop',
		] );

		$request  = $this->make_request( 'DELETE', "/acrossai-abilities-manager/v1/abilities/{$id}" );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * DELETE /abilities/{nonexistent_id} returns 404.
	 *
	 * @return void
	 */
	public function test_delete_nonexistent_ability_returns_404() {
		$request  = $this->make_request( 'DELETE', '/acrossai-abilities-manager/v1/abilities/999999' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}
}
