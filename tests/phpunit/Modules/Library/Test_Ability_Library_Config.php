<?php
/**
 * Tests: AcrossAI_Ability_Library_Config — Feature 033 SC-033-04 invariant.
 *
 * Asserts that even when a save_config() payload includes a stray
 * 'sub_group' field on an entry, the saved option shape stays exactly
 * { enabled, mode, sub_keys } — sub_group MUST NOT appear anywhere in
 * the on-disk acrossai_library_config option (FR-011, FR-012).
 *
 * Uses the bootstrap's acrossai_test_site_options() helper to inspect
 * the in-process site-option store written by update_site_option().
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Config;
use PHPUnit\Framework\TestCase;

/**
 * The Library Config sub_group-stripping save_config() invariant test.
 */
class Test_Ability_Library_Config extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset the shared site-option store between tests.
		acrossai_test_site_options( array() );
	}

	public function test_save_config_ignores_stray_sub_group_field(): void {
		// SC-033-04: stray sub_group on input MUST NOT reach saved data.
		$payload = array(
			'file-manager' => array(
				'enabled'   => true,
				'mode'      => 'specific',
				'sub_keys'  => array( 'plugin/read-file' => true ),
				'sub_group' => 'core',  // <-- stray, must be dropped.
			),
		);

		$result = AcrossAI_Ability_Library_Config::save_config( $payload );
		$this->assertTrue( $result );

		$saved = get_site_option( 'acrossai_library_config', array() );
		$this->assertIsArray( $saved );
		$this->assertArrayHasKey( 'file-manager', $saved );

		$entry = $saved['file-manager'];
		$this->assertArrayHasKey( 'enabled', $entry );
		$this->assertArrayHasKey( 'mode', $entry );
		$this->assertArrayHasKey( 'sub_keys', $entry );

		// The key assertion — sub_group MUST NOT survive into saved data.
		$this->assertArrayNotHasKey( 'sub_group', $entry );

		// And the JSON serialisation MUST contain no occurrence of "sub_group".
		$this->assertStringNotContainsString( 'sub_group', wp_json_encode( $saved ) );
	}

	public function test_save_config_preserves_shape_when_no_sub_group_present(): void {
		// Baseline: no stray field, shape stays canonical.
		$payload = array(
			'themes' => array(
				'enabled'  => true,
				'mode'     => 'specific',
				'sub_keys' => array( 'plugin/list-themes' => true ),
			),
		);

		AcrossAI_Ability_Library_Config::save_config( $payload );

		$saved = get_site_option( 'acrossai_library_config', array() );
		$entry = $saved['themes'];

		$this->assertSame(
			array( 'enabled', 'mode', 'sub_keys' ),
			array_keys( $entry )
		);
	}

	public function test_save_config_sparse_storage_strips_default_state(): void {
		// Pre-existing sparse-storage behavior (FR-017): entries at default state
		// are dropped. Confirms our edits did not regress this — Feature 033 must
		// not change AcrossAI_Ability_Library_Config behavior.
		$payload = array(
			'all-default' => array(
				'enabled'  => true,
				'mode'     => 'all',
				'sub_keys' => array(),
			),
			'kept'        => array(
				'enabled'  => true,
				'mode'     => 'specific',
				'sub_keys' => array( 'plugin/x' => true ),
			),
		);

		AcrossAI_Ability_Library_Config::save_config( $payload );

		$saved = get_site_option( 'acrossai_library_config', array() );

		$this->assertArrayNotHasKey( 'all-default', $saved );
		$this->assertArrayHasKey( 'kept', $saved );
	}
}
