<?php
/**
 * Tests: pass_as_tool key presence in all three AcrossAI_Abilities_Formatter outputs.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Utilities;

use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Formatter;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Row;
use PHPUnit\Framework\TestCase;

/**
 * Verifies pass_as_tool appears in format_for_response(), format_for_exposure(),
 * and format_merged_ability() — all three insertion points per plan.md CHANGE-4
 * and DEC-ABILITIES-DUAL-MODE-LIST.
 */
class AcrossAI_Abilities_Formatter_PassAsTool_Test extends TestCase {

	private function make_row( ?bool $pass_as_tool = null ): AcrossAI_Abilities_Row {
		return new AcrossAI_Abilities_Row(
			(object) array(
				'id'           => 1,
				'ability_slug' => 'test/slug',
				'pass_as_tool' => $pass_as_tool,
			)
		);
	}

	public function test_format_for_response_has_pass_as_tool_key(): void {
		$out = AcrossAI_Abilities_Formatter::format_for_response( $this->make_row() );
		$this->assertArrayHasKey( 'pass_as_tool', $out );
	}

	public function test_format_for_response_pass_as_tool_true(): void {
		$out = AcrossAI_Abilities_Formatter::format_for_response( $this->make_row( true ) );
		$this->assertTrue( $out['pass_as_tool'] );
	}

	public function test_format_for_response_pass_as_tool_null(): void {
		$out = AcrossAI_Abilities_Formatter::format_for_response( $this->make_row( null ) );
		$this->assertNull( $out['pass_as_tool'] );
	}

	public function test_format_for_exposure_has_pass_as_tool_key(): void {
		$out = AcrossAI_Abilities_Formatter::format_for_exposure( $this->make_row() );
		$this->assertArrayHasKey( 'pass_as_tool', $out );
	}

	public function test_format_merged_ability_has_pass_as_tool_key(): void {
		$out = AcrossAI_Abilities_Formatter::format_merged_ability( array(
			'id'           => 1,
			'slug'         => 'test/slug',
			'pass_as_tool' => true,
		) );
		$this->assertArrayHasKey( 'pass_as_tool', $out );
		$this->assertTrue( $out['pass_as_tool'] );
	}

	public function test_format_merged_ability_pass_as_tool_null_when_missing(): void {
		$out = AcrossAI_Abilities_Formatter::format_merged_ability( array(
			'id'   => 1,
			'slug' => 'test/slug',
			// pass_as_tool intentionally absent — must default to null.
		) );
		$this->assertNull( $out['pass_as_tool'] );
	}
}
