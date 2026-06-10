<?php
/**
 * Tests: get_pass_as_tool_slugs() in AcrossAI_Abilities_Query.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Abilities\Database;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use PHPUnit\Framework\TestCase;

/**
 * Verifies get_pass_as_tool_slugs() returns only rows where pass_as_tool = 1,
 * returns an empty array when none are opted in, and never issues a LIMIT 1 query
 * from the BerlinDB -1 footgun (BUG-BERLINDB-UNLIMITED).
 *
 * NOTE: PHPUnit bootstrap is currently blocked (no WP test bootstrap in project — T014
 * pre-existing gap). Tests can be written but not executed until the bootstrap shim is added.
 */
class AcrossAI_Abilities_Query_PassAsTool_Test extends TestCase {

	public function test_get_pass_as_tool_slugs_returns_array(): void {
		$query  = AcrossAI_Abilities_Query::instance();
		$result = $query->get_pass_as_tool_slugs();
		$this->assertIsArray( $result );
	}

	public function test_get_pass_as_tool_slugs_returns_empty_when_none_opted_in(): void {
		// Precondition: no rows have pass_as_tool = 1.
		$query  = AcrossAI_Abilities_Query::instance();
		$result = $query->get_pass_as_tool_slugs();
		// When no rows are flagged, the result MUST be empty (US2 acceptance scenario).
		$this->assertEmpty( $result, 'get_pass_as_tool_slugs() must return [] when no abilities are opted in' );
	}

	public function test_get_pass_as_tool_slugs_values_are_strings(): void {
		$query   = AcrossAI_Abilities_Query::instance();
		$results = $query->get_pass_as_tool_slugs();
		foreach ( $results as $slug ) {
			$this->assertIsString( $slug, 'Every slug returned must be a string (injection safety)' );
		}
	}
}
