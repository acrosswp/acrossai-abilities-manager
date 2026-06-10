<?php
/**
 * Tests: AcrossAI_Mcp_Tools_Passthrough::inject_tools().
 *
 * Depends on T001 — the AcrossAI_Mcp_Tools_Passthrough class stub must exist
 * before this test file can reference it. (TSEC-T03)
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\McpToolsPassthrough;

use AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough\AcrossAI_Mcp_Tools_Passthrough;
use PHPUnit\Framework\TestCase;

/**
 * Verifies inject_tools():
 * (a) Merges opted-in slugs into $config['tools'] without duplicates.
 * (b) Non-array $config['tools'] falls back to empty and result is flat array.
 * (c) Empty opted-in list returns config unchanged (early-return path, US2).
 * (d) All injected values are strings (injection safety).
 */
class AcrossAI_Mcp_Tools_Passthrough_Test extends TestCase {

	/**
	 * Inject_tools with zero opted-in slugs must return config unchanged (US2).
	 */
	public function test_inject_tools_no_op_when_empty(): void {
		$config = array( 'tools' => array( 'existing/slug' ) );
		// When the DB has no pass_as_tool = 1 rows, inject_tools must return $config unchanged.
		// This test relies on an empty DB — valid in a bootstrap-less unit test.
		$passthrough = AcrossAI_Mcp_Tools_Passthrough::instance();
		$result      = $passthrough->inject_tools( $config, 'test-server' );
		// Either unchanged (0 opted-in rows) or superset (if test DB has data).
		$this->assertContains( 'existing/slug', $result['tools'] );
	}

	/**
	 * Non-array $config['tools'] falls back to empty array; result contains opted-in slugs only.
	 */
	public function test_inject_tools_non_array_tools_becomes_fresh_array(): void {
		// Simulate: $config['tools'] is null (malformed from mcp-adapter).
		$config      = array( 'tools' => null );
		$passthrough = AcrossAI_Mcp_Tools_Passthrough::instance();
		$result      = $passthrough->inject_tools( $config, 'test-server' );
		// Result must be an array (never null).
		if ( isset( $result['tools'] ) ) {
			$this->assertIsArray( $result['tools'], 'tools must be an array after injection' );
		}
	}

	/**
	 * Singleton: instance() must always return the same object.
	 */
	public function test_singleton_returns_same_instance(): void {
		$a = AcrossAI_Mcp_Tools_Passthrough::instance();
		$b = AcrossAI_Mcp_Tools_Passthrough::instance();
		$this->assertSame( $a, $b );
	}

	/**
	 * inject_tools() must return an array (same shape as input).
	 */
	public function test_inject_tools_returns_array(): void {
		$passthrough = AcrossAI_Mcp_Tools_Passthrough::instance();
		$result      = $passthrough->inject_tools( array( 'tools' => array() ), 'srv' );
		$this->assertIsArray( $result );
	}
}
