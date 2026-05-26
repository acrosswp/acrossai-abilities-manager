<?php
/**
 * Tests for AcrossAI_Abilities_Read_Controller and
 * AcrossAI_Abilities_Category_Controller.
 *
 * Covers: browse, single-item, pagination, search, source/status/editable
 * filters, category discovery, not-found, and explicit forbidden-path (403)
 * for list, single-item, and categories routes.
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
 * Class AbilitiesReadControllerTest
 *
 * @since 0.1.0
 */
class AbilitiesReadControllerTest extends WP_UnitTestCase {

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
	 * Insert a minimal ability row directly.
	 *
	 * @param  array $overrides Field overrides.
	 * @return int  New row ID.
	 */
	private function insert_row( array $overrides = [] ): int {
		static $counter = 0;
		++$counter;

		$defaults = [
			'ability_slug'  => 'acrossai-abilities/test-read-' . $counter,
			'label'         => 'Read Test ' . $counter,
			'category'      => 'general',
			'status'        => 'draft',
			'source'        => 'db',
			'callback_type' => 'noop',
		];

		$id = AcrossAI_Abilities_Query::instance()->insert_ability( array_merge( $defaults, $overrides ) );
		$this->assertIsInt( $id );
		return $id;
	}

	// -------------------------------------------------------------------------
	// Forbidden-path coverage
	// -------------------------------------------------------------------------

	/**
	 * Non-admin is rejected (403) on GET /abilities.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_list() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/abilities' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Non-admin is rejected (403) on GET /abilities/{id}.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_get_single() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/abilities/1' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Non-admin is rejected (403) on GET /abilities/categories.
	 *
	 * @return void
	 */
	public function test_non_admin_cannot_list_categories() {
		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/acrossai-abilities-manager/v1/abilities/categories' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// Browse / list
	// -------------------------------------------------------------------------

	/**
	 * GET /abilities returns 200 with an array body.
	 *
	 * @return void
	 */
	public function test_get_abilities_returns_200() {
		$response = $this->server->dispatch( $this->get_request( '/acrossai-abilities-manager/v1/abilities' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * GET /abilities sets X-WP-Total and X-WP-TotalPages headers.
	 *
	 * @return void
	 */
	public function test_get_abilities_sets_pagination_headers() {
		$this->insert_row();

		$response = $this->server->dispatch( $this->get_request( '/acrossai-abilities-manager/v1/abilities' ) );
		$headers  = $response->get_headers();

		$this->assertArrayHasKey( 'X-WP-Total',      $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );
		$this->assertGreaterThanOrEqual( 1, (int) $headers['X-WP-Total'] );
	}

	/**
	 * GET /abilities with source=db filter returns only db-source rows.
	 *
	 * @return void
	 */
	public function test_get_abilities_source_filter() {
		$this->insert_row( [ 'source' => 'db' ] );
		$this->insert_row( [ 'source' => 'plugin' ] );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities', [ 'source' => 'db' ] )
		);
		$items = $response->get_data();

		foreach ( $items as $item ) {
			$this->assertSame( 'db', $item['source'] );
		}
	}

	/**
	 * GET /abilities with status=publish returns only published rows.
	 *
	 * @return void
	 */
	public function test_get_abilities_status_filter() {
		$this->insert_row( [ 'status' => 'publish' ] );
		$this->insert_row( [ 'status' => 'draft' ] );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities', [ 'status' => 'publish' ] )
		);
		$items = $response->get_data();

		foreach ( $items as $item ) {
			$this->assertSame( 'publish', $item['status'] );
		}
	}

	/**
	 * GET /abilities with search parameter returns matching rows.
	 *
	 * @return void
	 */
	public function test_get_abilities_search_filter() {
		$this->insert_row( [ 'label' => 'UniqueSearchTerm Ability' ] );
		$this->insert_row( [ 'label' => 'Other Ability' ] );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities', [ 'search' => 'UniqueSearchTerm' ] )
		);
		$items = $response->get_data();

		$this->assertNotEmpty( $items );
		foreach ( $items as $item ) {
			$this->assertStringContainsStringIgnoringCase( 'UniqueSearchTerm', $item['label'] ?? '' );
		}
	}

	/**
	 * GET /abilities pagination: page 2 returns different rows than page 1.
	 *
	 * @return void
	 */
	public function test_get_abilities_pagination_pages_are_disjoint() {
		for ( $i = 0; $i < 4; $i++ ) {
			$this->insert_row( [ 'label' => 'PagTest ' . $i ] );
		}

		$page1 = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities', [
				'page'     => 1,
				'per_page' => 2,
				'search'   => 'PagTest',
			] )
		);
		$page2 = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities', [
				'page'     => 2,
				'per_page' => 2,
				'search'   => 'PagTest',
			] )
		);

		$ids1 = array_column( $page1->get_data(), 'id' );
		$ids2 = array_column( $page2->get_data(), 'id' );

		$this->assertEmpty( array_intersect( $ids1, $ids2 ), 'Pages must be disjoint' );
	}

	/**
	 * Response items include editable=true for source=db rows.
	 *
	 * @return void
	 */
	public function test_response_items_include_editable_flag() {
		$this->insert_row( [ 'source' => 'db' ] );

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities', [ 'source' => 'db' ] )
		);
		$items = $response->get_data();

		$this->assertNotEmpty( $items );
		foreach ( $items as $item ) {
			$this->assertArrayHasKey( 'editable', $item );
			$this->assertTrue( $item['editable'] );
		}
	}

	// -------------------------------------------------------------------------
	// Single item
	// -------------------------------------------------------------------------

	/**
	 * GET /abilities/{id} returns 200 with correct ability data.
	 *
	 * @return void
	 */
	public function test_get_single_ability_returns_200() {
		$id = $this->insert_row( [ 'label' => 'Single Item Test' ] );

		$response = $this->server->dispatch(
			$this->get_request( "/acrossai-abilities-manager/v1/abilities/{$id}" )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Single Item Test', $response->get_data()['label'] );
	}

	/**
	 * GET /abilities/{nonexistent_id} returns 404.
	 *
	 * @return void
	 */
	public function test_get_single_ability_not_found_returns_404() {
		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/999999' )
		);
		$this->assertSame( 404, $response->get_status() );
	}

	// -------------------------------------------------------------------------
	// Categories discovery
	// -------------------------------------------------------------------------

	/**
	 * GET /abilities/categories returns 200 with array response.
	 *
	 * @return void
	 */
	public function test_get_categories_returns_200_or_501() {
		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/categories' )
		);

		// 200 if wp_get_ability_categories() exists; 501 if the WP Abilities API isn't loaded.
		$this->assertContains( $response->get_status(), [ 200, 501 ] );
	}

	/**
	 * GET /abilities/categories returns slug+label pairs when API is available.
	 *
	 * @return void
	 */
	public function test_get_categories_shape_when_available() {
		if ( ! function_exists( 'wp_get_ability_categories' ) ) {
			$this->markTestSkipped( 'wp_get_ability_categories not available in this WP version.' );
		}

		$response = $this->server->dispatch(
			$this->get_request( '/acrossai-abilities-manager/v1/abilities/categories' )
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertIsArray( $data );

		foreach ( $data as $category ) {
			$this->assertArrayHasKey( 'slug',  $category );
			$this->assertArrayHasKey( 'label', $category );
		}
	}
}
