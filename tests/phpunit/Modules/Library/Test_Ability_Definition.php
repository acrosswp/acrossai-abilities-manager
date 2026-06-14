<?php
/**
 * Tests: AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition.
 *
 * Feature 033 CHANGE-B-1 — verify push_definition() hoists the OPTIONAL
 * args['sub_group'] (and auto-derives sub_group_label) without affecting
 * subclasses that do not declare a sub-group.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use PHPUnit\Framework\TestCase;

/**
 * The Ability_Definition push_definition() OPTIONAL sub_group pass-through test.
 */
class Test_Ability_Definition extends TestCase {

	/**
	 * The minimal subclass without a sub_group declaration.
	 *
	 * @return Ability_Definition
	 */
	private function make_subclass_without_sub_group(): Ability_Definition {
		return new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/no-sub',
					'args' => array(
						'label'    => 'No Sub',
						'category' => 'test-category',
					),
				);
			}
		};
	}

	/**
	 * The minimal subclass declaring args['sub_group'] = 'core'.
	 *
	 * @return Ability_Definition
	 */
	private function make_subclass_with_core_sub_group(): Ability_Definition {
		return new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/with-sub',
					'args' => array(
						'label'     => 'With Sub',
						'category'  => 'test-category',
						'sub_group' => 'core',
					),
				);
			}
		};
	}

	/**
	 * The minimal subclass declaring an explicit args['sub_group_label'] override.
	 *
	 * @return Ability_Definition
	 */
	private function make_subclass_with_explicit_label(): Ability_Definition {
		return new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/explicit-label',
					'args' => array(
						'label'           => 'Explicit Label',
						'category'        => 'test-category',
						'sub_group'       => 'debug-log',
						'sub_group_label' => 'Debug Log (custom)',
					),
				);
			}
		};
	}

	public function test_push_definition_omits_sub_group_when_absent(): void {
		$subject = $this->make_subclass_without_sub_group();
		$rows    = $subject->push_definition( array() );

		$this->assertCount( 1, $rows );
		$row = $rows[0];

		$this->assertArrayNotHasKey( 'sub_group', $row );
		$this->assertArrayNotHasKey( 'sub_group_label', $row );
		$this->assertSame( 'test/no-sub', $row['slug'] );
		$this->assertSame( 'No Sub', $row['slug_label'] );
		$this->assertSame( 'test-category', $row['category'] );
	}

	public function test_push_definition_includes_sub_group_when_present(): void {
		$subject = $this->make_subclass_with_core_sub_group();
		$rows    = $subject->push_definition( array() );

		$this->assertCount( 1, $rows );
		$row = $rows[0];

		$this->assertArrayHasKey( 'sub_group', $row );
		$this->assertArrayHasKey( 'sub_group_label', $row );
		$this->assertSame( 'core', $row['sub_group'] );
		$this->assertSame( 'Core', $row['sub_group_label'] );
	}

	public function test_push_definition_auto_derives_label_from_hyphenated_key(): void {
		$subject = new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'test/hyphen-sub',
					'args' => array(
						'label'     => 'Hyphen Sub',
						'category'  => 'test-category',
						'sub_group' => 'debug-log',
					),
				);
			}
		};

		$rows = $subject->push_definition( array() );
		$row  = $rows[0];

		$this->assertSame( 'debug-log', $row['sub_group'] );
		$this->assertSame( 'Debug Log', $row['sub_group_label'] );
	}

	public function test_push_definition_prefers_explicit_sub_group_label_when_provided(): void {
		$subject = $this->make_subclass_with_explicit_label();
		$rows    = $subject->push_definition( array() );
		$row     = $rows[0];

		$this->assertSame( 'debug-log', $row['sub_group'] );
		$this->assertSame( 'Debug Log (custom)', $row['sub_group_label'] );
	}

	public function test_push_definition_does_not_mutate_existing_rows(): void {
		$subject  = $this->make_subclass_with_core_sub_group();
		$existing = array(
			array(
				'category'   => 'other-cat',
				'slug'       => 'other/ability',
				'name'       => 'other/ability',
				'slug_label' => 'Other',
				'args'       => array(),
				// Intentionally NO category_label — proves push_definition() appends rather than rebuilds.
			),
		);

		$rows = $subject->push_definition( $existing );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'other/ability', $rows[0]['slug'] );
		$this->assertSame( 'test/with-sub', $rows[1]['slug'] );
	}
}
