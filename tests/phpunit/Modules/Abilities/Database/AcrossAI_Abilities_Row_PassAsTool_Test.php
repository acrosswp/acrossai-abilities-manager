<?php
/**
 * Tests: pass_as_tool property and casting in AcrossAI_Abilities_Row.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Abilities\Database;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Row;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the pass_as_tool column:
 * - Defaults to null on the Row.
 * - Is listed in the JSON blocklist (get_json_fields() must not include it).
 * - Is cast via cast_tri_state() in the constructor (tri_state_fields entry).
 */
class AcrossAI_Abilities_Row_PassAsTool_Test extends TestCase {

	public function test_pass_as_tool_defaults_to_null(): void {
		$row = new AcrossAI_Abilities_Row( (object) array( 'id' => 1, 'ability_slug' => 'test/slug' ) );
		$this->assertNull( $row->pass_as_tool );
	}

	public function test_pass_as_tool_excluded_from_json_fields(): void {
		$this->assertNotContains(
			'pass_as_tool',
			AcrossAI_Abilities_Row::get_json_fields(),
			'pass_as_tool must be in the JSON blocklist to prevent accidental decode'
		);
	}

	public function test_pass_as_tool_tinyint_one_casts_to_true(): void {
		$row = new AcrossAI_Abilities_Row( (object) array( 'id' => 1, 'ability_slug' => 'test/slug', 'pass_as_tool' => '1' ) );
		$this->assertTrue( $row->pass_as_tool );
	}

	public function test_pass_as_tool_tinyint_zero_casts_to_false(): void {
		$row = new AcrossAI_Abilities_Row( (object) array( 'id' => 1, 'ability_slug' => 'test/slug', 'pass_as_tool' => '0' ) );
		$this->assertFalse( $row->pass_as_tool );
	}

	public function test_pass_as_tool_db_null_casts_to_null(): void {
		$row = new AcrossAI_Abilities_Row( (object) array( 'id' => 1, 'ability_slug' => 'test/slug', 'pass_as_tool' => null ) );
		$this->assertNull( $row->pass_as_tool );
		// Guard: use null !== $value, never '' !== (string) $value (BUG-MERGER-BOOL-STRING-CAST).
		$this->assertNotSame( '', (string) $row->pass_as_tool, 'False would cast to empty string — use null !== $value guard' );
	}
}
