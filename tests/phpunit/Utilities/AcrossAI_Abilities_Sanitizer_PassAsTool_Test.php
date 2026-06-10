<?php
/**
 * Tests: pass_as_tool tri-state sanitization in AcrossAI_Abilities_Sanitizer.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Utilities;

use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Sanitizer;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Verifies pass_as_tool is sanitized through the tri-state path for create and update,
 * and that malformed inputs are normalized (not passed through raw). (TSEC-T02)
 */
class AcrossAI_Abilities_Sanitizer_PassAsTool_Test extends TestCase {

	private function make_request( array $params ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/acrossai-abilities-manager/v1/abilities' );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	public function test_create_request_includes_pass_as_tool_true(): void {
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request(
			$this->make_request( array( 'ability_slug' => 'test/slug', 'pass_as_tool' => true ) )
		);
		$this->assertArrayHasKey( 'pass_as_tool', $fields );
		$this->assertTrue( $fields['pass_as_tool'] );
	}

	public function test_create_request_includes_pass_as_tool_null(): void {
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request(
			$this->make_request( array( 'ability_slug' => 'test/slug', 'pass_as_tool' => null ) )
		);
		$this->assertArrayHasKey( 'pass_as_tool', $fields );
		$this->assertNull( $fields['pass_as_tool'] );
	}

	public function test_update_request_includes_pass_as_tool(): void {
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_update_request(
			$this->make_request( array( 'pass_as_tool' => true ) )
		);
		$this->assertArrayHasKey( 'pass_as_tool', $fields );
		$this->assertTrue( $fields['pass_as_tool'] );
	}

	public function test_array_input_normalized_not_passed_raw(): void {
		// Malformed: array value should not survive as an array (TSEC-T02).
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request(
			$this->make_request( array( 'ability_slug' => 'test/slug', 'pass_as_tool' => array( 'inject' ) ) )
		);
		$this->assertNotIsArray( $fields['pass_as_tool'] ?? null, 'Array input must not be passed through raw' );
	}

	public function test_float_input_normalized(): void {
		// Float 1.7 should not survive as a float.
		$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request(
			$this->make_request( array( 'ability_slug' => 'test/slug', 'pass_as_tool' => 1.7 ) )
		);
		$this->assertNotIsFloat( $fields['pass_as_tool'] ?? null, 'Float input must be normalized' );
	}
}
