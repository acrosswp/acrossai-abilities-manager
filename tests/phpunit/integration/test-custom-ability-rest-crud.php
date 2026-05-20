<?php
/**
 * REST API CRUD Tests for Custom Abilities
 *
 * Tests cover: GET list, GET/:id, POST create, POST/:id update, DELETE/:id,
 * permission checks, validation errors, hook firing, MCP filtering.
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Tests
 */

namespace AcrossAI_Abilities_Manager\Tests;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Test suite for Custom Ability REST CRUD operations.
 *
 * @covers AcrossAI_Custom_Ability_Rest_Controller
 * @covers AcrossAI_Custom_Ability_Read_Controller
 * @covers AcrossAI_Custom_Ability_Write_Controller
 * @covers AcrossAI_Custom_Ability_Mcp_Controller
 */
class Test_Custom_Ability_REST_CRUD extends \WP_UnitTestCase {

	/**
	 * Server instance for REST testing.
	 *
	 * @var \WP_REST_Server
	 */
	protected $server;

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Editor user ID (non-admin).
	 *
	 * @var int
	 */
	protected $editor_id;

	/**
	 * Sample custom ability data.
	 *
	 * @var array
	 */
	protected $sample_ability = array(
		'ability_slug'       => 'custom/test-ability',
		'label'              => 'Test Ability',
		'description'        => 'A test ability for unit tests',
		'category'           => 'custom',
		'enabled'            => 1,
		'callback_type'      => 'noop',
		'callback_config'    => '{}',
		'permission_type'    => 'always_allow',
		'permission_config'  => '{}',
		'input_schema'       => '{}',
		'output_schema'      => '{}',
		'show_in_rest'       => 1,
		'show_in_mcp'        => 0,
		'mcp_type'           => null,
		'mcp_servers'        => null,
		'readonly'           => null,
		'destructive'        => null,
		'idempotent'         => null,
	);

	/**
	 * Test hooks tracking.
	 *
	 * @var array
	 */
	protected $hooks_fired = array();

	/**
	 * Set up test environment.
	 */
	public function set_up() {
		parent::set_up();

		// Initialize REST server.
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		// Create test users.
		$this->admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		// Reset hooks tracking.
		$this->hooks_fired = array();

		// Track hook firing.
		add_action( 'acrossai_custom_ability_before_save', array( $this, 'track_hook_fired' ), 10, 2 );
		add_action( 'acrossai_custom_ability_after_save', array( $this, 'track_hook_fired' ), 10, 2 );
		add_action( 'acrossai_custom_ability_deleted', array( $this, 'track_hook_fired' ), 10, 2 );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down() {
		parent::tear_down();
		remove_action( 'acrossai_custom_ability_before_save', array( $this, 'track_hook_fired' ), 10 );
		remove_action( 'acrossai_custom_ability_after_save', array( $this, 'track_hook_fired' ), 10 );
		remove_action( 'acrossai_custom_ability_deleted', array( $this, 'track_hook_fired' ), 10 );
	}

	/**
	 * Track hook firing for test assertions.
	 *
	 * @param mixed $arg1 First argument.
	 * @param mixed $arg2 Second argument.
	 */
	public function track_hook_fired( $arg1, $arg2 ) {
		$this->hooks_fired[] = array( $arg1, $arg2 );
	}

	/**
	 * Helper: Insert test ability into database.
	 *
	 * @param array $overrides Custom ability field overrides.
	 * @return int Ability ID.
	 */
	protected function insert_test_ability( $overrides = array() ) {
		global $wpdb;
		$table = $wpdb->get_blog_prefix() . 'acrossai_custom_abilities';

		$data = array_merge( $this->sample_ability, $overrides );

		// Insert into database.
		$wpdb->insert( $table, $data );

		return $wpdb->insert_id;
	}

	/**
	 * Test: GET list of custom abilities with pagination.
	 */
	public function test_get_abilities_list_pagination() {
		wp_set_current_user( $this->admin_id );

		// Insert 3 test abilities.
		$this->insert_test_ability( array( 'ability_slug' => 'custom/ability-1' ) );
		$this->insert_test_ability( array( 'ability_slug' => 'custom/ability-2' ) );
		$this->insert_test_ability( array( 'ability_slug' => 'custom/ability-3' ) );

		// Request with per_page=2.
		$request = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_param( 'per_page', 2 );
		$request->set_param( 'page', 1 );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$this->assertEqual( 2, count( $response->get_data() ) );

		// Check pagination headers.
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertEqual( '3', $headers['X-WP-Total'] );
	}

	/**
	 * Test: GET list with search filter.
	 */
	public function test_get_abilities_list_search() {
		wp_set_current_user( $this->admin_id );

		$this->insert_test_ability( array( 'ability_slug' => 'custom/search-test', 'label' => 'Searchable Label' ) );
		$this->insert_test_ability( array( 'ability_slug' => 'custom/other', 'label' => 'Other Ability' ) );

		$request = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_param( 'search', 'searchable' );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEqual( 1, count( $data ) );
		$this->assertEqual( 'Searchable Label', $data[0]['label'] );
	}

	/**
	 * Test: GET list with category filter.
	 */
	public function test_get_abilities_list_filter_category() {
		wp_set_current_user( $this->admin_id );

		$this->insert_test_ability( array( 'ability_slug' => 'custom/cat1', 'category' => 'custom' ) );
		$this->insert_test_ability( array( 'ability_slug' => 'custom/cat2', 'category' => 'integration' ) );

		$request = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_param( 'category', 'integration' );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEqual( 1, count( $data ) );
		$this->assertEqual( 'integration', $data[0]['category'] );
	}

	/**
	 * Test: GET list with enabled filter.
	 */
	public function test_get_abilities_list_filter_enabled() {
		wp_set_current_user( $this->admin_id );

		$this->insert_test_ability( array( 'ability_slug' => 'custom/enabled', 'enabled' => 1 ) );
		$this->insert_test_ability( array( 'ability_slug' => 'custom/disabled', 'enabled' => 0 ) );

		$request = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_param( 'enabled', '1' );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEqual( 1, count( $data ) );
		$this->assertEqual( 1, $data[0]['enabled'] );
	}

	/**
	 * Test: GET single ability by ID.
	 */
	public function test_get_single_ability() {
		wp_set_current_user( $this->admin_id );

		$id = $this->insert_test_ability();

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities/' . $id );
		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEqual( $id, $data['id'] );
		$this->assertEqual( 'Test Ability', $data['label'] );
	}

	/**
	 * Test: GET non-existent ability returns 404.
	 */
	public function test_get_single_ability_not_found() {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities/99999' );
		$response = $this->server->dispatch( $request );

		$this->assertEqual( 404, $response->get_status() );
	}

	/**
	 * Test: POST create new ability with valid data.
	 */
	public function test_post_create_ability_valid() {
		wp_set_current_user( $this->admin_id );

		$body = array(
			'ability_slug'      => 'custom/new-ability',
			'label'             => 'New Ability',
			'description'       => 'Test description',
			'category'          => 'custom',
			'enabled'           => 1,
			'callback_type'     => 'noop',
			'callback_config'   => '{}',
			'permission_type'   => 'always_allow',
			'permission_config' => '{}',
			'input_schema'      => '{}',
			'output_schema'     => '{}',
			'show_in_rest'      => 1,
		);

		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_body_params( $body );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 201, $response->get_status() );
		$data = $response->get_data();
		$this->assertEqual( 'custom/new-ability', $data['ability_slug'] );
		$this->assertEqual( 'New Ability', $data['label'] );

		// Verify hooks fired.
		$this->assertGreaterThan( 0, count( $this->hooks_fired ) );
	}

	/**
	 * Test: POST create with duplicate slug returns 409.
	 */
	public function test_post_create_ability_duplicate_slug() {
		wp_set_current_user( $this->admin_id );

		// Insert first ability.
		$this->insert_test_ability( array( 'ability_slug' => 'custom/duplicate' ) );

		// Try to create another with same slug.
		$body = array(
			'ability_slug'      => 'custom/duplicate',
			'label'             => 'Duplicate',
			'callback_type'     => 'noop',
			'callback_config'   => '{}',
			'permission_type'   => 'always_allow',
			'permission_config' => '{}',
		);

		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_body_params( $body );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 409, $response->get_status() );
	}

	/**
	 * Test: POST create with invalid slug pattern returns 400.
	 */
	public function test_post_create_ability_invalid_slug_pattern() {
		wp_set_current_user( $this->admin_id );

		$body = array(
			'ability_slug'      => 'invalid-no-slash',
			'label'             => 'Invalid',
			'callback_type'     => 'noop',
			'callback_config'   => '{}',
			'permission_type'   => 'always_allow',
			'permission_config' => '{}',
		);

		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_body_params( $body );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 400, $response->get_status() );
	}

	/**
	 * Test: POST create with missing required fields returns 400.
	 */
	public function test_post_create_ability_missing_required_fields() {
		wp_set_current_user( $this->admin_id );

		// Missing label.
		$body = array(
			'ability_slug'      => 'custom/no-label',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_body_params( $body );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 400, $response->get_status() );
	}

	/**
	 * Test: POST update ability by ID.
	 */
	public function test_post_update_ability() {
		wp_set_current_user( $this->admin_id );

		$id = $this->insert_test_ability( array( 'ability_slug' => 'custom/to-update', 'label' => 'Original Label' ) );

		$body = array(
			'ability_slug' => 'custom/to-update',
			'label'        => 'Updated Label',
		);

		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/custom-abilities/' . $id );
		$request->set_body_params( $body );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEqual( 'Updated Label', $data['label'] );
	}

	/**
	 * Test: DELETE ability by ID.
	 */
	public function test_delete_ability() {
		wp_set_current_user( $this->admin_id );

		$id = $this->insert_test_ability( array( 'ability_slug' => 'custom/to-delete' ) );

		$request  = new WP_REST_Request( 'DELETE', '/acrossai-abilities-manager/v1/custom-abilities/' . $id );
		$response = $this->server->dispatch( $request );

		$this->assertEqual( 204, $response->get_status() );

		// Verify delete hook fired.
		$this->assertGreaterThan( 0, count( $this->hooks_fired ) );
	}

	/**
	 * Test: DELETE non-existent ability returns 404.
	 */
	public function test_delete_ability_not_found() {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'DELETE', '/acrossai-abilities-manager/v1/custom-abilities/99999' );
		$response = $this->server->dispatch( $request );

		$this->assertEqual( 404, $response->get_status() );
	}

	/**
	 * Test: Non-admin cannot GET list - permission denied.
	 */
	public function test_get_abilities_list_permission_denied() {
		wp_set_current_user( $this->editor_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities' );
		$response = $this->server->dispatch( $request );

		$this->assertEqual( 403, $response->get_status() );
	}

	/**
	 * Test: Non-admin cannot POST - permission denied.
	 */
	public function test_post_create_ability_permission_denied() {
		wp_set_current_user( $this->editor_id );

		$body = array(
			'ability_slug'      => 'custom/denied',
			'label'             => 'Denied',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_body_params( $body );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 403, $response->get_status() );
	}

	/**
	 * Test: MCP GET /mcp/tools filters correctly.
	 */
	public function test_get_mcp_tools() {
		wp_set_current_user( $this->admin_id );

		// Insert tool (show_in_mcp=1, mcp_type=tool).
		$this->insert_test_ability(
			array(
				'ability_slug' => 'custom/tool1',
				'show_in_mcp'  => 1,
				'mcp_type'     => 'tool',
			)
		);

		// Insert resource (should not appear in tools).
		$this->insert_test_ability(
			array(
				'ability_slug' => 'custom/resource1',
				'show_in_mcp'  => 1,
				'mcp_type'     => 'resource',
			)
		);

		// Insert non-MCP ability (should not appear).
		$this->insert_test_ability(
			array(
				'ability_slug' => 'custom/non-mcp',
				'show_in_mcp'  => 0,
			)
		);

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities/mcp/tools' );
		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$data = $response->get_data();

		// Should only contain tool.
		$this->assertEqual( 1, count( $data ) );
		$this->assertEqual( 'tool', $data[0]['mcp_type'] );
	}

	/**
	 * Test: MCP GET /mcp/resources filters correctly.
	 */
	public function test_get_mcp_resources() {
		wp_set_current_user( $this->admin_id );

		$this->insert_test_ability(
			array(
				'ability_slug' => 'custom/resource1',
				'show_in_mcp'  => 1,
				'mcp_type'     => 'resource',
			)
		);

		$this->insert_test_ability(
			array(
				'ability_slug' => 'custom/tool1',
				'show_in_mcp'  => 1,
				'mcp_type'     => 'tool',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities/mcp/resources' );
		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertEqual( 1, count( $data ) );
		$this->assertEqual( 'resource', $data[0]['mcp_type'] );
	}

	/**
	 * Test: MCP GET /mcp/prompts filters correctly.
	 */
	public function test_get_mcp_prompts() {
		wp_set_current_user( $this->admin_id );

		$this->insert_test_ability(
			array(
				'ability_slug' => 'custom/prompt1',
				'show_in_mcp'  => 1,
				'mcp_type'     => 'prompt',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities/mcp/prompts' );
		$response = $this->server->dispatch( $request );

		$this->assertEqual( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertEqual( 1, count( $data ) );
		$this->assertEqual( 'prompt', $data[0]['mcp_type'] );
	}

	/**
	 * Test: POST create fires before/after save hooks with correct data.
	 */
	public function test_post_create_fires_hooks() {
		wp_set_current_user( $this->admin_id );

		$this->hooks_fired = array();

		$body = array(
			'ability_slug'      => 'custom/hook-test',
			'label'             => 'Hook Test',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
		);

		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_body_params( $body );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 201, $response->get_status() );

		// Verify hooks were fired (before_save + after_save = 2 minimum).
		$this->assertGreaterThanOrEqual( 2, count( $this->hooks_fired ) );
	}

	/**
	 * Test: Response includes all 20 fields.
	 */
	public function test_response_includes_all_fields() {
		wp_set_current_user( $this->admin_id );

		$id = $this->insert_test_ability();

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities/' . $id );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();

		// Verify all 20 fields present.
		$expected_fields = array(
			'id',
			'ability_slug',
			'label',
			'description',
			'category',
			'enabled',
			'callback_type',
			'callback_config',
			'permission_type',
			'permission_config',
			'input_schema',
			'output_schema',
			'show_in_rest',
			'show_in_mcp',
			'mcp_type',
			'mcp_servers',
			'readonly',
			'destructive',
			'idempotent',
			'created_at',
			'updated_at',
		);

		foreach ( $expected_fields as $field ) {
			$this->assertArrayHasKey( $field, $data, "Field '$field' missing from response" );
		}
	}

	/**
	 * Test: POST create with invalid JSON schema returns 400.
	 */
	public function test_post_create_invalid_json_schema() {
		wp_set_current_user( $this->admin_id );

		$body = array(
			'ability_slug'      => 'custom/bad-schema',
			'label'             => 'Bad Schema',
			'callback_type'     => 'noop',
			'permission_type'   => 'always_allow',
			'input_schema'      => '{ invalid json',
		);

		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/custom-abilities' );
		$request->set_body_params( $body );

		$response = $this->server->dispatch( $request );

		$this->assertEqual( 400, $response->get_status() );
	}

	/**
	 * Test: GET response uses correct data types.
	 */
	public function test_response_correct_data_types() {
		wp_set_current_user( $this->admin_id );

		$id = $this->insert_test_ability( array( 'enabled' => 1, 'show_in_mcp' => 0 ) );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/custom-abilities/' . $id );
		$response = $this->server->dispatch( $request );

		$data = $response->get_data();

		// Verify type casting.
		$this->assertIsInt( $data['id'] );
		$this->assertIsString( $data['ability_slug'] );
		$this->assertIsString( $data['label'] );
		$this->assertIsInt( $data['enabled'] );
		$this->assertIsInt( $data['show_in_mcp'] );
	}
}
