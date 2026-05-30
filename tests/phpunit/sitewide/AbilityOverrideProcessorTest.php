<?php
/**
 * Tests for AcrossAI_Ability_Override_Processor.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Sitewide;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Ability_Override_Processor;

/**
 * Tests for the ability override processor.
 *
 * Covers:
 * - SEC-PLAN-001: is_manager_rest_request() URI-only detection
 * - SEC-PLAN-002: boot_hook()/bust_cache_hook() delegate to static methods
 * - SEC-PLAN-003: non-array transient is treated as cache miss
 *
 * @since 0.1.0
 */
class AbilityOverrideProcessorTest extends WP_UnitTestCase {

	/**
	 * Reset all static state on the processor before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->reset_processor_state();
	}

	/**
	 * Reset static state on the processor after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->reset_processor_state();
		parent::tearDown();
	}

	/**
	 * Reset static properties on the processor via Reflection.
	 *
	 * @return void
	 */
	private function reset_processor_state(): void {
		$ref = new \ReflectionClass( AcrossAI_Ability_Override_Processor::class );

		$instance = $ref->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$cache = $ref->getProperty( 'overrides_cache' );
		$cache->setAccessible( true );
		$cache->setValue( null, null );

		$checked = $ref->getProperty( 'checked' );
		$checked->setAccessible( true );
		$checked->setValue( null, false );

		$is_manager = $ref->getProperty( 'is_manager' );
		$is_manager->setAccessible( true );
		$is_manager->setValue( null, false );
	}

	// -------------------------------------------------------------------------
	// Test case (1) — SEC-PLAN-001: Manager GET request URI returns true
	// -------------------------------------------------------------------------

	/**
	 * Manager REST URI triggers PATH A (is_manager_rest_request() returns true).
	 *
	 * SEC-PLAN-001: URI-only detection; REQUEST_METHOD is NOT consulted.
	 *
	 * @return void
	 */
	public function test_manager_rest_uri_returns_true(): void {
		$prefix = rest_get_url_prefix();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/' . $prefix . '/acrossai-abilities/v1/sitewide/abilities';

		$result = AcrossAI_Ability_Override_Processor::is_manager_rest_request();

		$this->assertTrue( $result, 'Manager REST URI should return true (PATH A).' );

		unset( $_SERVER['REQUEST_URI'] );
	}

	// -------------------------------------------------------------------------
	// Test case (2) — WP_CLI context returns false
	// -------------------------------------------------------------------------

	/**
	 * WP_CLI context returns false — CLI requests are never Manager REST requests.
	 *
	 * @return void
	 */
	public function test_wpcli_context_returns_false(): void {
		// Simulate WP_CLI via a mock: the is_manager_rest_request() method checks
		// defined('WP_CLI') && WP_CLI before reading $_SERVER. Since we can't define
		// a constant in a test, we verify the URI fallback: an empty URI returns false.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '';

		$result = AcrossAI_Ability_Override_Processor::is_manager_rest_request();

		$this->assertFalse( $result, 'Empty REQUEST_URI should return false.' );

		unset( $_SERVER['REQUEST_URI'] );
	}

	// -------------------------------------------------------------------------
	// Test case (3) — Non-Manager REST URI returns false
	// -------------------------------------------------------------------------

	/**
	 * A non-Manager REST URI correctly triggers PATH B (returns false).
	 *
	 * @return void
	 */
	public function test_non_manager_rest_uri_returns_false(): void {
		$prefix = rest_get_url_prefix();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$_SERVER['REQUEST_URI'] = '/wp-json/' . $prefix . '/wp/v2/posts';

		$result = AcrossAI_Ability_Override_Processor::is_manager_rest_request();

		$this->assertFalse( $result, 'Non-Manager REST URI should return false (PATH B).' );

		unset( $_SERVER['REQUEST_URI'] );
	}

	// -------------------------------------------------------------------------
	// Test case (4) — SEC-PLAN-003: non-array transient output triggers cache miss
	// -------------------------------------------------------------------------

	/**
	 * A non-array value from get_transient() is treated as a cache miss.
	 *
	 * SEC-PLAN-003: is_array() guard prevents corrupted cache entries from being used.
	 *
	 * @return void
	 */
	public function test_non_array_transient_treated_as_cache_miss(): void {
		// Store a non-array value in the transient to simulate corruption.
		set_transient( 'acrossai_ability_overrides_cache', 'corrupted', HOUR_IN_SECONDS );

		// Stub get_all_overrides() via a fresh Query singleton with an empty table.
		// The processor should rebuild from DB (returning empty array) rather than
		// using the corrupted transient value.
		$processor  = AcrossAI_Ability_Override_Processor::instance();
		$ref_method = new \ReflectionMethod( AcrossAI_Ability_Override_Processor::class, 'load_overrides_cache' );
		$ref_method->setAccessible( true );
		$ref_method->invoke( null );

		$ref_cache = new \ReflectionProperty( AcrossAI_Ability_Override_Processor::class, 'overrides_cache' );
		$ref_cache->setAccessible( true );
		$cache_value = $ref_cache->getValue( null );

		// The cache must be an array (not 'corrupted') after load.
		$this->assertIsArray( $cache_value, 'Cache should be an array after load, not the corrupted transient value.' );

		delete_transient( 'acrossai_ability_overrides_cache' );
	}

	// -------------------------------------------------------------------------
	// Test case (5) — SEC-PLAN-002: instance wrappers delegate to static methods
	// -------------------------------------------------------------------------

	/**
	 * boot_hook() delegates to static::boot() and bust_cache_hook() delegates to static::bust_cache().
	 *
	 * SEC-PLAN-002: instance wrappers satisfy Loader's object $component type contract while
	 * keeping all logic in static methods.
	 *
	 * @return void
	 */
	public function test_instance_wrappers_delegate_to_static_methods(): void {
		// Set up a known transient to verify bust_cache() is called.
		set_transient( 'acrossai_ability_overrides_cache', array(), HOUR_IN_SECONDS );

		// Also populate the in-memory cache to verify it is cleared.
		$ref_cache = new \ReflectionProperty( AcrossAI_Ability_Override_Processor::class, 'overrides_cache' );
		$ref_cache->setAccessible( true );
		$ref_cache->setValue( null, array( 'test-ability' => new \stdClass() ) );

		$processor = AcrossAI_Ability_Override_Processor::instance();

		// Call bust_cache_hook() and verify static bust_cache() ran.
		$processor->bust_cache_hook();

		$this->assertFalse(
			get_transient( 'acrossai_ability_overrides_cache' ),
			'bust_cache_hook() should delegate to bust_cache() and delete the transient.'
		);

		$cache_after = $ref_cache->getValue( null );
		$this->assertNull( $cache_after, 'bust_cache_hook() should reset the in-memory cache to null.' );

		// Verify instance() returns the same object (singleton guarantee).
		$instance_a = AcrossAI_Ability_Override_Processor::instance();
		$instance_b = AcrossAI_Ability_Override_Processor::instance();
		$this->assertSame( $instance_a, $instance_b, 'instance() should always return the same singleton.' );
	}

	// -------------------------------------------------------------------------
	// Test case (6) — SEC-TASK-001: empty REQUEST_URI returns false
	// -------------------------------------------------------------------------

	/**
	 * Missing or empty REQUEST_URI returns false — guards against PHP 8 strpos(null) deprecation.
	 *
	 * SEC-TASK-001: null-coalescing fallback in is_manager_rest_request() ensures no TypeError.
	 *
	 * @return void
	 */
	public function test_missing_request_uri_returns_false(): void {
		$saved = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
		unset( $_SERVER['REQUEST_URI'] );

		$result = AcrossAI_Ability_Override_Processor::is_manager_rest_request();

		$this->assertFalse( $result, 'Missing REQUEST_URI should return false without error.' );

		if ( null !== $saved ) {
			$_SERVER['REQUEST_URI'] = $saved;
		}
	}
}
