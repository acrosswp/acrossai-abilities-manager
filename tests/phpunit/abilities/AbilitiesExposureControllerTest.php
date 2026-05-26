<?php
/**
 * Tests for AcrossAI_Abilities_Exposure_Controller.
 *
 * Covers: explicit forbidden-path (403) for all exposure routes, route-level
 * fail-closed server-context behavior, unrestricted rows always included,
 * server-scoped rows included only when server_id matches, and correct
 * mcp_type mapping for tools/resources/prompts URL segments.
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
 * Class AbilitiesExposureControllerTest
 *
 * @since 0.1.0
 */
class AbilitiesExposureControllerTest extends WP_UnitTestCase {

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
	 * Tear down — clean up test rows and reset user.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}acrossai_abilities WHERE ability_slug LIKE 'acrossai-abilities/exp-test-%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a REST GET request with admin nonce.
	 *
	 * @param  string $route  Route path.
	 * @param  array  $params Query params.
	 * @return WP_REST_Request
	 */
	private function get_request( string $route, array $params = [] ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', $route );
		$request->set_header( 'X-WP-Nonce', $this->nonce );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	/**
	 * Insert a published ability with given mcp_type, show_in_mcp, and optional mcp_servers.
	 *
	 * @param  string     $mcp_type    One of 'tool', 'resource', 'prompt'.
	 * @param  array|null $mcp_servers Array of server IDs or null for unrestricted.
	 * @return int  New row ID.
	 */
	private function insert_published_mcp( string $mcp_type, ?array $mcp_servers = null ): int {
		static $counter = 0;
		++$counter;

		return AcrossAI_Abilities_Query::instance()->insert_ability( [
			'ability_slug'  => 'acrossai-abilities/exp-test-' . $counter,
			'label'         => 'Exposure Test ' . $counter,
			'category'      => 'general',
			'status'        => 'publish',
			'source'        => 'db',
			'callback_type' => 'noop',
			'mcp_type'      => $mcp_type,
			'show_in_mcp'   => 1,
			'mcp_servers'   => $mcp_servers,
		] );
	}

	// -------------------------------------------------------------------------
	// Forbidden-path coverage (admin-only, PD-001)
	// -------------------------------------------------------------------------

	/**
	 * Non-admin is rejected (403) on GET /abilities/exposures/tools.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_get_tools_exposure() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/abilities/exposures/tools' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Non-admin is rejected (403) on GET /abilities/exposures/resources.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_get_resources_exposure() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/abilities/exposures/resources' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Non-admin is rejected (403) on GET /abilities/exposures/prompts.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_get_prompts_exposure() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/abilities/exposures/prompts' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// Route-level behavior
	// -------------------------------------------------------------------------

	/**
	 * GET /abilities/exposures/tools returns 200 with an array body.
	 *
	 * @return void
	 */
	public function test_get_exposures_tools_returns_200() {
		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/tools' )
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * GET /abilities/exposures/resources returns 200 with an array body.
	 *
	 * @return void
	 */
	public function test_get_exposures_resources_returns_200() {
		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/resources' )
		);
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * GET /abilities/exposures/prompts returns 200 with an array body.
	 *
	 * @return void
	 */
	public function test_get_exposures_prompts_returns_200() {
		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/prompts' )
		);
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Invalid exposure type in URL does not match the route pattern (WP returns 404).
	 *
	 * @return void
	 */
	public function test_invalid_exposure_type_not_matched_by_route() {
		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/invalid-type' )
		);
		// WP REST router returns 404 when the enum constraint in the URL pattern doesn't match.
		$this->assertSame( 404, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// Fail-closed server-context filtering
	// -------------------------------------------------------------------------

	/**
	 * Unrestricted rows (null mcp_servers) are always included regardless of server_id.
	 *
	 * @return void
	 */
	public function test_unrestricted_row_included_without_server_id() {
		$id = $this->insert_published_mcp( 'tool', null );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/tools' )
		);

		$data = $response->get_data();
		$ids  = array_column( $data, 'id' );

		$this->assertContains( $id, $ids, 'Unrestricted row must be included without server_id' );
	}

	/**
	 * Unrestricted rows are included even when a server_id is supplied.
	 *
	 * @return void
	 */
	public function test_unrestricted_row_included_with_any_server_id() {
		$id = $this->insert_published_mcp( 'tool', null );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/tools', [
				'server_id' => 'some-unknown-server',
			] )
		);

		$data = $response->get_data();
		$ids  = array_column( $data, 'id' );

		$this->assertContains( $id, $ids, 'Unrestricted row must be included for any server_id' );
	}

	/**
	 * Server-scoped row (non-empty mcp_servers) is EXCLUDED when server_id is empty (fail-closed).
	 *
	 * @return void
	 */
	public function test_server_scoped_row_excluded_when_server_id_empty() {
		$id = $this->insert_published_mcp( 'tool', [ 'server-a', 'server-b' ] );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/tools' )
			// No server_id param → fail-closed.
		);

		$data = $response->get_data();
		$ids  = array_column( $data, 'id' );

		$this->assertNotContains( $id, $ids, 'Server-scoped row must be excluded when server_id is empty (fail-closed)' );
	}

	/**
	 * Server-scoped row is EXCLUDED when server_id is unknown (fail-closed).
	 *
	 * @return void
	 */
	public function test_server_scoped_row_excluded_when_server_id_unknown() {
		$id = $this->insert_published_mcp( 'tool', [ 'server-a', 'server-b' ] );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/tools', [
				'server_id' => 'server-unknown',
			] )
		);

		$data = $response->get_data();
		$ids  = array_column( $data, 'id' );

		$this->assertNotContains( $id, $ids, 'Server-scoped row must be excluded for unknown server_id' );
	}

	/**
	 * Server-scoped row is INCLUDED when server_id matches a value in mcp_servers.
	 *
	 * @return void
	 */
	public function test_server_scoped_row_included_when_server_id_matches() {
		$id = $this->insert_published_mcp( 'tool', [ 'server-a', 'server-b' ] );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/tools', [
				'server_id' => 'server-a',
			] )
		);

		$data = $response->get_data();
		$ids  = array_column( $data, 'id' );

		$this->assertContains( $id, $ids, 'Server-scoped row must be included when server_id matches' );
	}

	// -------------------------------------------------------------------------
	// mcp_type filtering
	// -------------------------------------------------------------------------

	/**
	 * tools endpoint returns only mcp_type=tool rows.
	 *
	 * @return void
	 */
	public function test_tools_endpoint_returns_only_tool_type_rows() {
		$tool_id     = $this->insert_published_mcp( 'tool' );
		$resource_id = $this->insert_published_mcp( 'resource' );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/tools', [
				'server_id' => '', // unrestricted rows only.
			] )
		);

		$data = $response->get_data();
		$ids  = array_column( $data, 'id' );

		// Both rows were unrestricted; but only the tool row should be in /tools.
		$this->assertContains( $tool_id,     $ids );
		$this->assertNotContains( $resource_id, $ids );
	}

	/**
	 * resources endpoint returns only mcp_type=resource rows.
	 *
	 * @return void
	 */
	public function test_resources_endpoint_returns_only_resource_type_rows() {
		$tool_id     = $this->insert_published_mcp( 'tool' );
		$resource_id = $this->insert_published_mcp( 'resource' );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/exposures/resources' )
		);

		$data = $response->get_data();
		$ids  = array_column( $data, 'id' );

		$this->assertContains( $resource_id, $ids );
		$this->assertNotContains( $tool_id,  $ids );
	}
}
