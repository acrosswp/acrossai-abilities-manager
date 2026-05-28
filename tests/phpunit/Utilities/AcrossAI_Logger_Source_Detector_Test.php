<?php
/**
 * Unit tests for AcrossAI_Logger_Source_Detector (Feature 017).
 *
 * Covered assertions:
 *  (a) AcrossAI_Logger_Source_Detector::instance() returns correct class type (FIX-5).
 *  (b) detect_source(), set_mcp_context(), clear_mcp_context() work as instance methods (FIX-5).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Utilities;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Source_Detector;

/**
 * Tests for AcrossAI_Logger_Source_Detector singleton and instance methods.
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Source_Detector_Test extends WP_UnitTestCase {

	/**
	 * (a) instance() must return an AcrossAI_Logger_Source_Detector object (FIX-5).
	 *
	 * @return void
	 */
	public function test_instance_returns_correct_class() {
		$detector = AcrossAI_Logger_Source_Detector::instance();

		$this->assertInstanceOf(
			AcrossAI_Logger_Source_Detector::class,
			$detector,
			'AcrossAI_Logger_Source_Detector::instance() must return an instance of the class'
		);
	}

	/**
	 * (b) detect_source() works as an instance method (FIX-5).
	 *
	 * @return void
	 */
	public function test_detect_source_via_instance() {
		$detector = AcrossAI_Logger_Source_Detector::instance();
		$source   = $detector->detect_source();

		$valid_sources = array( 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct' );

		$this->assertContains(
			$source,
			$valid_sources,
			'detect_source() must return one of the 6 valid source types'
		);
	}

	/**
	 * (c) set_mcp_context() and clear_mcp_context() work as instance methods (FIX-5).
	 *
	 * Verifies MCP context lifecycle is functional via instance calls.
	 * Critical: these are the security-sensitive call sites per security-constraints.md.
	 *
	 * @return void
	 */
	public function test_mcp_context_lifecycle_via_instance() {
		$detector = AcrossAI_Logger_Source_Detector::instance();

		// Set MCP context.
		$detector->set_mcp_context( 'test-server-id' );
		$this->assertTrue(
			$detector->is_mcp_context(),
			'is_mcp_context() must return true after set_mcp_context()'
		);
		$this->assertSame(
			'test-server-id',
			$detector->detect_mcp_server_id(),
			'detect_mcp_server_id() must return the stashed server ID'
		);

		// Clear MCP context.
		$detector->clear_mcp_context();
		$this->assertFalse(
			$detector->is_mcp_context(),
			'is_mcp_context() must return false after clear_mcp_context()'
		);
		$this->assertNull(
			$detector->detect_mcp_server_id(),
			'detect_mcp_server_id() must return null after clear_mcp_context()'
		);
	}
}
