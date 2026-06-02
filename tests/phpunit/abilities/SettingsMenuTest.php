<?php
/**
 * Tests for SettingsMenu::sanitize_per_page().
 *
 * Covers boundary values, out-of-range inputs, and default fallback.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu;

/**
 * Class SettingsMenuTest
 *
 * @since 0.1.0
 */
class SettingsMenuTest extends WP_UnitTestCase {

	// =========================================================================
	// sanitize_per_page — in-range values
	// =========================================================================

	/**
	 * Accepts the minimum valid value (1).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_minimum(): void {
		$this->assertSame( 1, SettingsMenu::instance()->sanitize_per_page( 1 ) );
	}

	/**
	 * Accepts the maximum valid value (200).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_maximum(): void {
		$this->assertSame( 200, SettingsMenu::instance()->sanitize_per_page( 200 ) );
	}

	/**
	 * Accepts a mid-range value (50).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_midrange(): void {
		$this->assertSame( 50, SettingsMenu::instance()->sanitize_per_page( 50 ) );
	}

	/**
	 * Accepts the default value (20).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_default(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 20 ) );
	}

	// =========================================================================
	// sanitize_per_page — out-of-range → 20
	// =========================================================================

	/**
	 * Returns 20 when value is 0 (below minimum).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_zero(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 0 ) );
	}

	/**
	 * Returns 20 when value is 201 (above maximum).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_above_max(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 201 ) );
	}

	/**
	 * Returns 20 for a large out-of-range integer.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_large_value(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 99999 ) );
	}

	/**
	 * absint(-5) = 5 which is in range — returns 5.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_negative_converts_via_absint(): void {
		$this->assertSame( 5, SettingsMenu::instance()->sanitize_per_page( -5 ) );
	}

	/**
	 * Returns 20 for a very negative value (absint(-300) = 300, out-of-range → 20).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_large_negative_returns_default(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( -300 ) );
	}

	/**
	 * Returns 20 for an empty string.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_empty_string(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( '' ) );
	}

	/**
	 * Returns 20 for a non-numeric string.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_string(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 'many' ) );
	}

	/**
	 * String-formatted numeric value within range is accepted.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_numeric_string(): void {
		$this->assertSame( 10, SettingsMenu::instance()->sanitize_per_page( '10' ) );
	}
}
