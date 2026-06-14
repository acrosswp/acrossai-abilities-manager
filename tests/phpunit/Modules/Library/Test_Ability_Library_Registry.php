<?php
/**
 * Tests: AcrossAI_Ability_Library_Registry — Feature 033 CHANGE-B-2.
 *
 * Verifies the OPTIONAL sub_group / sub_group_label pass-through through
 * validate_and_normalize(), the sanitize_sub_group helper, and the
 * ALLOWED_ARGS_FIELDS allowlist now containing 'sub_group' /
 * 'sub_group_label'. validate_and_normalize() is private; tests call it
 * via Reflection (the public collect() path is filter-driven and our
 * apply_filters() stub returns the first value unchanged).
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use PHPUnit\Framework\TestCase;

/**
 * The Library Registry sub_group validation test.
 */
class Test_Ability_Library_Registry extends TestCase {

	/**
	 * Invoke the private validate_and_normalize() via Reflection.
	 *
	 * @param  array<int, array<string, mixed>> $raw Raw filter payload.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize( array $raw ): array {
		$registry = AcrossAI_Ability_Library_Registry::instance();
		$ref      = new \ReflectionClass( $registry );
		$method   = $ref->getMethod( 'validate_and_normalize' );
		$method->setAccessible( true );
		return $method->invoke( $registry, $raw );
	}

	/**
	 * The minimum valid row shape (required fields only).
	 *
	 * @param  array<string, mixed> $overrides Field overrides.
	 * @return array<string, mixed>
	 */
	private function valid_row( array $overrides = array() ): array {
		return array_merge(
			array(
				'category'       => 'file-manager',
				'category_label' => 'File Manager',
				'slug'           => 'plugin/read-file',
				'slug_label'     => 'Read File',
				'name'           => 'plugin/read-file',
				'args'           => array(
					'label'    => 'Read File',
					'category' => 'file-manager',
				),
			),
			$overrides
		);
	}

	public function test_registry_omits_sub_group_when_not_declared(): void {
		$rows = $this->normalize( array( $this->valid_row() ) );
		$this->assertCount( 1, $rows );
		$this->assertArrayNotHasKey( 'sub_group', $rows[0] );
		$this->assertArrayNotHasKey( 'sub_group_label', $rows[0] );
	}

	public function test_registry_accepts_sub_group_and_derives_label(): void {
		$row = $this->valid_row(
			array(
				'sub_group' => 'core',
				'args'      => array(
					'label'     => 'Read File',
					'category'  => 'file-manager',
					'sub_group' => 'core',
				),
			)
		);

		$rows = $this->normalize( array( $row ) );

		$this->assertSame( 'core', $rows[0]['sub_group'] );
		$this->assertSame( 'Core', $rows[0]['sub_group_label'] );
	}

	public function test_registry_strips_invalid_sub_group_characters(): void {
		$row = $this->valid_row(
			array(
				'sub_group' => '!!! BAD-INPUT ###',
			)
		);

		$rows = $this->normalize( array( $row ) );

		// sanitize_key() lowercases and strips non [a-z0-9_-]. ' ' and '!' / '#' go away.
		$this->assertSame( 'bad-input', $rows[0]['sub_group'] );
		$this->assertSame( 'Bad Input', $rows[0]['sub_group_label'] );
	}

	public function test_registry_omits_sub_group_when_empty_after_sanitize(): void {
		$row = $this->valid_row(
			array(
				'sub_group' => '!!! ###',
			)
		);

		$rows = $this->normalize( array( $row ) );

		// FR-018 / SC-033-03: empty after sanitize → omit both keys.
		$this->assertArrayNotHasKey( 'sub_group', $rows[0] );
		$this->assertArrayNotHasKey( 'sub_group_label', $rows[0] );
	}

	public function test_registry_allows_sub_group_in_args_allowlist(): void {
		// 'sub_group' MUST be in ALLOWED_ARGS_FIELDS so the
		// array_intersect_key() strip does NOT remove it.
		$row = $this->valid_row(
			array(
				'args' => array(
					'label'           => 'Read File',
					'category'        => 'file-manager',
					'sub_group'       => 'core',
					'sub_group_label' => 'Core (custom)',
					'NOT_ALLOWED_KEY' => 'should be stripped',
				),
			)
		);

		$rows = $this->normalize( array( $row ) );

		$this->assertArrayHasKey( 'sub_group', $rows[0]['args'] );
		$this->assertSame( 'core', $rows[0]['args']['sub_group'] );
		$this->assertArrayHasKey( 'sub_group_label', $rows[0]['args'] );
		$this->assertSame( 'Core (custom)', $rows[0]['args']['sub_group_label'] );
		$this->assertArrayNotHasKey( 'NOT_ALLOWED_KEY', $rows[0]['args'] );
	}

	public function test_registry_explicit_sub_group_label_overrides_auto_derived(): void {
		$row = $this->valid_row(
			array(
				'sub_group'       => 'core',
				'sub_group_label' => 'My Custom Heading',
			)
		);

		$rows = $this->normalize( array( $row ) );

		$this->assertSame( 'core', $rows[0]['sub_group'] );
		$this->assertSame( 'My Custom Heading', $rows[0]['sub_group_label'] );
	}
}
