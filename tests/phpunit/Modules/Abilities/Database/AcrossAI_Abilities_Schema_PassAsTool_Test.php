<?php
/**
 * Tests: pass_as_tool column definition in AcrossAI_Abilities_Schema.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Abilities\Database;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Schema;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the pass_as_tool column is defined correctly per plan.md CHANGE-1 guards:
 * - No 'primary' => true key (BUG-BERLINDB-V3-DOUBLE-PRIMARY).
 * - type=tinyint, length=1, allow_null=true, default=null.
 */
class AcrossAI_Abilities_Schema_PassAsTool_Test extends TestCase {

	private function get_column( string $name ): ?array {
		$schema  = new AcrossAI_Abilities_Schema();
		$columns = $schema->columns;
		foreach ( $columns as $col ) {
			if ( isset( $col['name'] ) && $col['name'] === $name ) {
				return $col;
			}
		}
		return null;
	}

	public function test_pass_as_tool_column_exists(): void {
		$this->assertNotNull(
			$this->get_column( 'pass_as_tool' ),
			'pass_as_tool column must be present in AcrossAI_Abilities_Schema'
		);
	}

	public function test_pass_as_tool_type_is_tinyint(): void {
		$col = $this->get_column( 'pass_as_tool' );
		$this->assertSame( 'tinyint', $col['type'] );
	}

	public function test_pass_as_tool_length_is_one(): void {
		$col = $this->get_column( 'pass_as_tool' );
		$this->assertSame( '1', $col['length'] );
	}

	public function test_pass_as_tool_allows_null(): void {
		$col = $this->get_column( 'pass_as_tool' );
		$this->assertTrue( $col['allow_null'] );
	}

	public function test_pass_as_tool_default_is_null(): void {
		$col = $this->get_column( 'pass_as_tool' );
		$this->assertNull( $col['default'] );
	}

	public function test_pass_as_tool_has_no_primary_key(): void {
		$col = $this->get_column( 'pass_as_tool' );
		$this->assertArrayNotHasKey(
			'primary',
			$col,
			'pass_as_tool must not have a primary key (BUG-BERLINDB-V3-DOUBLE-PRIMARY)'
		);
	}
}
