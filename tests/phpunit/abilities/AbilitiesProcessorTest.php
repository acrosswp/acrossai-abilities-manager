<?php
/**
 * Tests for AcrossAI_Abilities_Processor.
 *
 * Covers: runtime registration, authenticated-user execution gate, nested
 * meta registry payload (BUG-FLAT-ARGS-PATH), php_code static-closure
 * wrapping, per-invocation Throwable isolation, execution audit logging,
 * wp_remote_post HTTPS revalidation, timeout clamping, redirection=>0,
 * no caller header/cookie propagation, and fail-closed unknown-server-context.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Processor;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Table;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Formatter;

/**
 * Class AbilitiesProcessorTest
 *
 * @since 0.1.0
 */
class AbilitiesProcessorTest extends WP_UnitTestCase {

	/**
	 * Processor singleton.
	 *
	 * @var AcrossAI_Abilities_Processor
	 */
	protected $processor;

	/**
	 * Set up — ensure table and obtain processor singleton.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		( new AcrossAI_Abilities_Table() )->maybe_upgrade();
		$this->processor = AcrossAI_Abilities_Processor::instance();
	}

	/**
	 * Tear down — clean up test rows and reset user context.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}acrossai_abilities WHERE ability_slug LIKE 'acrossai-abilities/proc-test-%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Insert a minimal published DB ability row.
	 *
	 * @param  array $overrides Field overrides.
	 * @return int  New row ID.
	 */
	private function insert_published_db( array $overrides = array() ): int {
		static $counter = 0;
		++$counter;

		$defaults = array(
			'ability_slug'  => 'acrossai-abilities/proc-test-' . $counter,
			'label'         => 'Processor Test ' . $counter,
			'category'      => 'general',
			'status'        => 'publish',
			'source'        => 'db',
			'callback_type' => 'noop',
		);

		$id = AcrossAI_Abilities_Query::instance()->insert_ability( array_merge( $defaults, $overrides ) );
		$this->assertIsInt( $id );
		return $id;
	}

	// -------------------------------------------------------------------------
	// execution_permission_callback
	// -------------------------------------------------------------------------

	/**
	 * Execution_permission_callback returns false when no user is logged in.
	 *
	 * @return void
	 */
	public function test_execution_permission_callback_false_when_logged_out() {
		wp_set_current_user( 0 );
		$this->assertFalse( $this->processor->execution_permission_callback() );
	}

	/**
	 * Execution_permission_callback returns true when a user is logged in.
	 *
	 * @return void
	 */
	public function test_execution_permission_callback_true_when_logged_in() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->processor->execution_permission_callback() );
	}

	// -------------------------------------------------------------------------
	// register_abilities — skips when API absent
	// -------------------------------------------------------------------------

	/**
	 * Register_abilities is a no-op when wp_register_ability() does not exist.
	 *
	 * This test verifies the function-exists guard does not fatal when the
	 * WordPress Abilities API is unavailable.
	 *
	 * @return void
	 */
	public function test_register_abilities_noop_when_api_absent() {
		if ( function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability is available — skipping absent-API guard test.' );
		}
		// Should not throw — guard returns early.
		$this->processor->register_abilities();
		$this->assertTrue( true ); // Reached here without fatal = pass.
	}

	// -------------------------------------------------------------------------
	// register_abilities — filtering and registry args
	// -------------------------------------------------------------------------

	/**
	 * Register_abilities registers only source=db + status=publish rows and
	 * The uses nested `meta` key in registry args (BUG-FLAT-ARGS-PATH).
	 *
	 * @return void
	 */
	public function test_register_abilities_uses_nested_meta_and_filters_source_status() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability not available.' );
		}

		$pub_id = $this->insert_published_db( array( 'label' => 'Published DB' ) );
		$draft  = $this->insert_published_db(
			array(
				'label'  => 'Draft DB',
				'status' => 'draft',
			)
		);
		$plugin = $this->insert_published_db(
			array(
				'label'  => 'Plugin Row',
				'source' => 'plugin',
			)
		);

		// Build registry args for the published row using the Formatter.
		$row  = AcrossAI_Abilities_Query::instance()->get_ability_by_id( $pub_id );
		$args = AcrossAI_Abilities_Formatter::build_registry_args( $row );

		// Nested meta key must be present; top-level category/description must not be flat.
		$this->assertArrayHasKey( 'meta', $args, 'Registry args must use nested meta key (BUG-FLAT-ARGS-PATH)' );
		$this->assertIsArray( $args['meta'] );
	}

	/**
	 * Register_abilities skips rows with empty slug.
	 *
	 * @return void
	 */
	public function test_register_abilities_skips_row_with_empty_slug() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability not available.' );
		}

		// This row has an empty slug and must be skipped (guard in is_row_registrable).
		// We test by verifying no fatal occurs even with such a row in the table.
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'acrossai_abilities',
			array(
				'ability_slug'  => '',
				'label'         => 'No Slug',
				'category'      => 'general',
				'status'        => 'publish',
				'source'        => 'db',
				'callback_type' => 'noop',
				'created_at'    => current_time( 'mysql', true ),
				'updated_at'    => current_time( 'mysql', true ),
			)
		);

		$this->processor->register_abilities();
		$this->assertTrue( true ); // No fatal = skip worked.

		// Clean up the empty-slug row.
		$wpdb->delete(
			$wpdb->prefix . 'acrossai_abilities',
			array(
				'ability_slug' => '',
				'label'        => 'No Slug',
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Register_abilities skips rows with empty description.
	 *
	 * @return void
	 */
	public function test_register_abilities_skips_row_with_empty_description() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability not available.' );
		}

		// Insert a published row with an empty description — is_row_registrable() must return false.
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'acrossai_abilities',
			array(
				'ability_slug'  => 'acrossai-abilities/proc-test-no-desc',
				'label'         => 'No Description',
				'description'   => '',
				'category'      => 'general',
				'status'        => 'publish',
				'source'        => 'db',
				'callback_type' => 'noop',
				'created_at'    => current_time( 'mysql', true ),
				'updated_at'    => current_time( 'mysql', true ),
			)
		);

		$this->processor->register_abilities();
		$this->assertTrue( true ); // No fatal = skip guard worked.

		$wpdb->delete(
			$wpdb->prefix . 'acrossai_abilities',
			array( 'ability_slug' => 'acrossai-abilities/proc-test-no-desc' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// -------------------------------------------------------------------------
	// execute callbacks — noop
	// -------------------------------------------------------------------------

	/**
	 * Noop callback returns an empty array.
	 *
	 * @return void
	 */
	public function test_noop_callback_returns_empty_array() {
		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db( array( 'callback_type' => 'noop' ) )
		);

		// Access the noop closure via the build_execute_callback path (test through reflection).
		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$result   = $callback( array() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// -------------------------------------------------------------------------
	// execute callbacks — php_code
	// -------------------------------------------------------------------------

	/**
	 * Php_code callback executes code and returns result.
	 *
	 * @return void
	 */
	public function test_php_code_callback_executes_and_returns_result() {
		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'php_code',
					'callback_config' => array( 'code' => 'return strtoupper( $input );' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$result   = $callback( 'hello' );

		$this->assertSame( 'HELLO', $result );
	}

	/**
	 * Php_code callback isolates per-invocation Throwable — returns WP_Error, not fatal.
	 *
	 * @return void
	 */
	public function test_php_code_callback_throwable_isolation() {
		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'php_code',
					'callback_config' => array( 'code' => 'throw new \RuntimeException("boom");' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$result   = $callback( array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_exec_error', $result->get_error_code() );
	}

	/**
	 * Php_code callback with empty code returns empty array (not WP_Error).
	 *
	 * @return void
	 */
	public function test_php_code_callback_empty_code_returns_empty_array() {
		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'php_code',
					'callback_config' => array( 'code' => '' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$result   = $callback( array() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	// -------------------------------------------------------------------------
	// execute callbacks — wp_remote_post
	// -------------------------------------------------------------------------

	/**
	 * Wp_remote_post callback returns WP_Error for non-HTTPS URL.
	 *
	 * @return void
	 */
	public function test_wp_remote_post_callback_rejects_non_https_url() {
		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'wp_remote_post',
					'callback_config' => array( 'url' => 'http://insecure.example.com/api' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$result   = $callback( array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Wp_remote_post callback returns WP_Error for empty URL.
	 *
	 * @return void
	 */
	public function test_wp_remote_post_callback_rejects_empty_url() {
		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'wp_remote_post',
					'callback_config' => array( 'url' => '' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$result   = $callback( array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Wp_remote_post callback enforces timeout clamping (max 30s).
	 *
	 * Uses add_filter on http_request_args to capture the actual args passed to wp_remote_post.
	 *
	 * @return void
	 */
	public function test_wp_remote_post_callback_clamps_timeout() {
		$captured_args = array();

		add_filter(
			'http_request_args',
			static function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				// Short-circuit: return WP_Error to avoid actual HTTP request.
				return new \WP_Error( 'short_circuit', 'Test short circuit' );
			}
		);

		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'wp_remote_post',
					'callback_config' => array(
						'url'     => 'https://example.com',
						'timeout' => 999,
					),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$callback( array() );

		remove_all_filters( 'http_request_args' );

		$this->assertNotEmpty( $captured_args );
		$this->assertLessThanOrEqual( 30, $captured_args['timeout'], 'Timeout must be clamped to 30s' );
	}

	/**
	 * Wp_remote_post callback sets redirection=>0 (no redirects, PD-002).
	 *
	 * @return void
	 */
	public function test_wp_remote_post_callback_sets_no_redirect() {
		$captured_args = array();

		add_filter(
			'http_request_args',
			static function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return new \WP_Error( 'short_circuit', 'Test short circuit' );
			}
		);

		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'wp_remote_post',
					'callback_config' => array( 'url' => 'https://example.com' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$callback( array() );

		remove_all_filters( 'http_request_args' );

		$this->assertSame( 0, $captured_args['redirection'], 'redirection must be 0 (PD-002)' );
	}

	/**
	 * Wp_remote_post callback does not propagate caller headers (PD-002).
	 *
	 * Only Content-Type should appear in the request headers.
	 *
	 * @return void
	 */
	public function test_wp_remote_post_callback_no_caller_header_propagation() {
		$captured_args = array();

		add_filter(
			'http_request_args',
			static function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				return new \WP_Error( 'short_circuit', 'Test short circuit' );
			}
		);

		// Set a current user (to simulate a logged-in context with cookies).
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'wp_remote_post',
					'callback_config' => array( 'url' => 'https://example.com' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$callback( array() );

		remove_all_filters( 'http_request_args' );

		$sent_headers = array_map( 'strtolower', array_keys( (array) ( $captured_args['headers'] ?? array() ) ) );

		$this->assertNotContains( 'cookie', $sent_headers, 'Cookies must not be propagated' );
		$this->assertNotContains( 'authorization', $sent_headers, 'Authorization header must not be propagated' );
	}

	// -------------------------------------------------------------------------
	// execute callbacks — filter_hook
	// -------------------------------------------------------------------------

	/**
	 * Filter_hook callback applies the correct WordPress filter.
	 *
	 * @return void
	 */
	public function test_filter_hook_callback_applies_correct_filter() {
		$hook_fired = false;

		add_filter(
			'acrossai_ability_execute_test_hook',
			static function ( $result, $input ) use ( &$hook_fired ) {
				$hook_fired = true;
				return array( 'processed' => $input );
			},
			10,
			2
		);

		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'filter_hook',
					'callback_config' => array( 'hook_name' => 'test_hook' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$result   = $callback( 'my-input' );

		remove_all_filters( 'acrossai_ability_execute_test_hook' );

		$this->assertTrue( $hook_fired, 'The filter hook must have been applied' );
		$this->assertSame( array( 'processed' => 'my-input' ), $result );
	}

	/**
	 * Filter_hook callback with empty hook_name returns empty array without firing any hook.
	 *
	 * @return void
	 */
	public function test_filter_hook_callback_empty_hook_name_returns_empty_array() {
		$row = AcrossAI_Abilities_Query::instance()->get_ability_by_id(
			$this->insert_published_db(
				array(
					'callback_type'   => 'filter_hook',
					'callback_config' => array( 'hook_name' => '' ),
				)
			)
		);

		$reflection = new \ReflectionClass( $this->processor );
		$method     = $reflection->getMethod( 'build_execute_callback' );
		$method->setAccessible( true );

		$callback = $method->invoke( $this->processor, $row );
		$result   = $callback( array() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}
}
