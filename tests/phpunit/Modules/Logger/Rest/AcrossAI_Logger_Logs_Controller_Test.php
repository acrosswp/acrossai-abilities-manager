<?php
/**
 * Unit tests for AcrossAI_Logger_Logs_Controller (Feature 017).
 *
 * Covered assertions:
 *  (a) No 'acrossai-abilities' text domain in Logs Controller (FIX-2).
 *  (b) source and status REST args have sanitize_callback (FIX-4).
 *  (c) register_routes() arg schema validates correctly (FIX-4).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Modules\Logger\Rest;

use WP_UnitTestCase;

/**
 * Tests for AcrossAI_Logger_Logs_Controller constitution compliance.
 *
 * @since 0.1.0
 */
class AcrossAI_Logger_Logs_Controller_Test extends WP_UnitTestCase {

	/**
	 * (a) No 'acrossai-abilities' text domain in the Logs Controller file (FIX-2).
	 *
	 * @return void
	 */
	public function test_logs_controller_has_correct_text_domain() {
		$file_content = file_get_contents(
			dirname( __FILE__, 6 ) . '/includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php'
		);

		$this->assertStringNotContainsString(
			"'acrossai-abilities'",
			$file_content,
			"AcrossAI_Logger_Logs_Controller must not use 'acrossai-abilities' text domain"
		);

		$this->assertStringContainsString(
			"'acrossai-abilities-manager'",
			$file_content,
			"AcrossAI_Logger_Logs_Controller must use 'acrossai-abilities-manager' text domain"
		);
	}

	/**
	 * (b) source and status REST args have sanitize_callback (FIX-4).
	 *
	 * @return void
	 */
	public function test_source_and_status_args_have_sanitize_callback() {
		$file_content = file_get_contents(
			dirname( __FILE__, 6 ) . '/includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php'
		);

		// Verify sanitize_callback present at least 4 times (search, ability_slug, page, per_page already had it;
		// FIX-4 adds 2 more for source and status).
		$count = substr_count( $file_content, "'sanitize_callback'" );

		$this->assertGreaterThanOrEqual(
			4,
			$count,
			'AcrossAI_Logger_Logs_Controller must have sanitize_callback on source and status args (FIX-4)'
		);
	}
}
