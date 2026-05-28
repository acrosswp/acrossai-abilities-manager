<?php
/**
 * Smoke test for AcrossAI_Ability_Logs_Table (Feature 017 WARNING-1).
 *
 * Covered assertions:
 *  (a) instance() returns an AcrossAI_Ability_Logs_Table object.
 *  (b) Constructor is NOT private — BerlinDB side-effects can run.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Modules\Logger\Database;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Table;

/**
 * Tests for AcrossAI_Ability_Logs_Table singleton.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Logs_Table_Test extends WP_UnitTestCase {

	/**
	 * (a) instance() must return an AcrossAI_Ability_Logs_Table object (WARNING-1).
	 *
	 * @return void
	 */
	public function test_instance_returns_correct_class() {
		$table = AcrossAI_Ability_Logs_Table::instance();

		$this->assertInstanceOf(
			AcrossAI_Ability_Logs_Table::class,
			$table,
			'AcrossAI_Ability_Logs_Table::instance() must return an instance of the class'
		);
	}

	/**
	 * (b) Constructor is NOT private — BerlinDB pattern (WARNING-1).
	 *
	 * Verifies the constructor is accessible (not private) via Reflection,
	 * which is necessary for BerlinDB table registration side-effects.
	 *
	 * @return void
	 */
	public function test_constructor_is_not_private() {
		$reflection  = new \ReflectionClass( AcrossAI_Ability_Logs_Table::class );
		$constructor = $reflection->getConstructor();

		$this->assertFalse(
			$constructor ? $constructor->isPrivate() : false,
			'AcrossAI_Ability_Logs_Table constructor must NOT be private (BerlinDB exception)'
		);
	}
}
