<?php
/**
 * Unit tests for AcrossAI_Ability_Logger (Feature 017).
 *
 * Covered assertions:
 *  (a) The class has no `boot` method (FIX-1 — Boot Flow Rule).
 *  (b) All 6 Logger hooks are registered in WordPress via Main Loader (FIX-1).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Modules\Logger;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\AcrossAI_Ability_Logger;

/**
 * Tests for AcrossAI_Ability_Logger constitution compliance.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Logger_Test extends WP_UnitTestCase {

	/**
	 * (a) Logger class must not have a boot() method (FIX-1).
	 *
	 * The Boot Flow Rule forbids feature classes from registering hooks
	 * themselves. All hooks must be wired in Main::define_public_hooks().
	 *
	 * @return void
	 */
	public function test_logger_has_no_boot_method() {
		$this->assertFalse(
			method_exists( AcrossAI_Ability_Logger::class, 'boot' ),
			'AcrossAI_Ability_Logger must not have a boot() method (Boot Flow Rule violation)'
		);
	}

	/**
	 * (b) All 6 Logger hooks must be registered in WordPress via Main Loader.
	 *
	 * Verifies that the Main Loader registration in define_public_hooks()
	 * correctly registers all Logger hooks with WordPress.
	 *
	 * @return void
	 */
	public function test_all_logger_hooks_registered_via_main_loader() {
		$logger = AcrossAI_Ability_Logger::instance();

		$this->assertGreaterThan(
			0,
			has_filter( 'mcp_adapter_pre_tool_call', array( $logger, 'capture_mcp_server_id' ) ),
			'mcp_adapter_pre_tool_call filter must be registered for capture_mcp_server_id'
		);

		$this->assertGreaterThan(
			0,
			has_action( 'wp_before_execute_ability', array( $logger, 'start_pending_entry' ) ),
			'wp_before_execute_ability action must be registered for start_pending_entry'
		);

		$this->assertGreaterThan(
			0,
			has_action( 'wp_after_execute_ability', array( $logger, 'finish_pending_entry' ) ),
			'wp_after_execute_ability action must be registered for finish_pending_entry'
		);

		$this->assertGreaterThan(
			0,
			has_filter( 'wp_register_ability_args', array( $logger, 'wrap_permission_callback' ) ),
			'wp_register_ability_args filter must be registered for wrap_permission_callback'
		);

		$this->assertGreaterThan(
			0,
			has_action( 'acrossai_ability_logger_cleanup', array( $logger, 'cleanup_old_logs' ) ),
			'acrossai_ability_logger_cleanup action must be registered for cleanup_old_logs'
		);

		$this->assertGreaterThan(
			0,
			has_action( 'plugins_loaded', array( $logger, 'schedule_cleanup' ) ),
			'plugins_loaded action must be registered for schedule_cleanup'
		);
	}
}
