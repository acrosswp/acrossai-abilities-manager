<?php
/**
 * Unit tests for AcrossAI_Logger_Query (Feature 017).
 *
 * Covered assertions:
 *  (a) get_logs() is NOT a static method — must be called via instance() (FIX-3).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Modules\Logger\Database;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\AcrossAI_Logger_Query;

/**
 * Tests for AcrossAI_Logger_Query constitution compliance.
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Query_Test extends WP_UnitTestCase {

	/**
	 * (a) get_logs() must not be a static method (FIX-3 — Module Contract).
	 *
	 * The Module Contract requires instance() as the sole public interface.
	 * Static public methods bypass the singleton and violate the contract.
	 *
	 * @return void
	 */
	public function test_get_logs_is_not_static() {
		$reflection = new \ReflectionMethod( AcrossAI_Logger_Query::class, 'get_logs' );

		$this->assertFalse(
			$reflection->isStatic(),
			'AcrossAI_Logger_Query::get_logs() must not be static (Module Contract violation)'
		);
	}

	/**
	 * (b) get_logs() is callable as an instance method via instance().
	 *
	 * @return void
	 */
	public function test_get_logs_callable_via_instance() {
		$this->assertTrue(
			method_exists( AcrossAI_Logger_Query::instance(), 'get_logs' ),
			'AcrossAI_Logger_Query::instance() must expose get_logs() as an instance method'
		);
	}
}
