<?php
/**
 * Tests for AcrossAI_Abilities_Validator and AcrossAI_Abilities_Sanitizer.
 *
 * Covers: slug validation, php_code blocked-function + T_EVAL scan, syntax
 * errors caught via ParseError, unknown-key rejection across all callback
 * modes, schema size/depth guards, fail-closed server filtering inputs,
 * immutable-field stripping for non-db rows, and sparse-update pass-through.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Validator;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Abilities_Sanitizer;

/**
 * Class AbilitiesValidationTest
 *
 * @since 0.1.0
 */
class AbilitiesValidationTest extends WP_UnitTestCase {

	// =========================================================================
	// Slug validation
	// =========================================================================

	/**
	 * validate_slug_suffix passes for a valid alphanumeric-hyphen suffix.
	 *
	 * @return void
	 */
	public function test_validate_slug_suffix_passes_valid() {
		$result = AcrossAI_Abilities_Validator::validate_slug_suffix( 'my-ability_01' );
		$this->assertTrue( $result );
	}

	/**
	 * validate_slug_suffix rejects empty string.
	 *
	 * @return void
	 */
	public function test_validate_slug_suffix_rejects_empty() {
		$result = AcrossAI_Abilities_Validator::validate_slug_suffix( '' );
		$this->assertWPError( $result );
	}

	/**
	 * validate_slug_suffix rejects a forward-slash (namespace separator).
	 *
	 * @return void
	 */
	public function test_validate_slug_suffix_rejects_slash() {
		$result = AcrossAI_Abilities_Validator::validate_slug_suffix( 'my/ability' );
		$this->assertWPError( $result );
	}

	/**
	 * validate_slug_suffix rejects invalid characters (e.g. spaces, dots).
	 *
	 * @return void
	 */
	public function test_validate_slug_suffix_rejects_invalid_chars() {
		$result = AcrossAI_Abilities_Validator::validate_slug_suffix( 'my ability.test' );
		$this->assertWPError( $result );
	}

	/**
	 * validate_slug_suffix rejects a suffix that makes the full slug exceed 255 chars.
	 *
	 * @return void
	 */
	public function test_validate_slug_suffix_rejects_overlong() {
		$result = AcrossAI_Abilities_Validator::validate_slug_suffix( str_repeat( 'a', 237 ) );
		$this->assertWPError( $result );
	}

	// =========================================================================
	// php_code: blocked functions and eval
	// =========================================================================

	/**
	 * validate_php_code passes for innocuous code.
	 *
	 * @return void
	 */
	public function test_validate_php_code_passes_valid_code() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( 'return strtoupper( $input );' );
		$this->assertTrue( $result );
	}

	/**
	 * validate_php_code rejects exec() (T_STRING blocked function).
	 *
	 * @return void
	 */
	public function test_validate_php_code_rejects_exec() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( 'exec( $input );' );
		$this->assertWPError( $result );
	}

	/**
	 * validate_php_code rejects system() (T_STRING blocked function).
	 *
	 * @return void
	 */
	public function test_validate_php_code_rejects_system() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( 'system( "ls" );' );
		$this->assertWPError( $result );
	}

	/**
	 * validate_php_code rejects shell_exec() (T_STRING blocked function).
	 *
	 * @return void
	 */
	public function test_validate_php_code_rejects_shell_exec() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( '$out = shell_exec( "id" ); return $out;' );
		$this->assertWPError( $result );
	}

	/**
	 * validate_php_code rejects eval (T_EVAL language construct — TASK-SEC-001).
	 *
	 * @return void
	 */
	public function test_validate_php_code_rejects_eval_construct() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( 'eval( $input );' );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_php_code', $result->get_error_code() );
	}

	/**
	 * validate_php_code rejects call_user_func (indirect invocation — TASK-SEC-002).
	 *
	 * @return void
	 */
	public function test_validate_php_code_rejects_call_user_func() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( 'call_user_func( "system", $input );' );
		$this->assertWPError( $result );
	}

	/**
	 * validate_php_code rejects call_user_func_array (indirect invocation — TASK-SEC-002).
	 *
	 * @return void
	 */
	public function test_validate_php_code_rejects_call_user_func_array() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( 'call_user_func_array( "exec", [ $input ] );' );
		$this->assertWPError( $result );
	}

	/**
	 * validate_php_code rejects code exceeding the 64 KB limit.
	 *
	 * @return void
	 */
	public function test_validate_php_code_rejects_oversized_code() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( str_repeat( 'x', 65537 ) );
		$this->assertWPError( $result );
	}

	/**
	 * validate_php_code returns a clean 400 WP_Error for a syntax error
	 * (covers ParseError handling in PHP 8+  — TASK-SEC-003).
	 *
	 * @return void
	 */
	public function test_validate_php_code_handles_syntax_error_gracefully() {
		$result = AcrossAI_Abilities_Validator::validate_php_code( 'if (' ); // deliberately malformed.
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_php_code', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	// =========================================================================
	// callback_config: unknown-key rejection
	// =========================================================================

	/**
	 * filter_hook config rejects unknown keys.
	 *
	 * @return void
	 */
	public function test_filter_hook_config_rejects_unknown_keys() {
		$result = AcrossAI_Abilities_Validator::validate_callback_config( 'filter_hook', [
			'hook_name'   => 'my_hook',
			'extra_param' => 'bad',
		] );
		$this->assertWPError( $result );
	}

	/**
	 * filter_hook config requires non-empty hook_name.
	 *
	 * @return void
	 */
	public function test_filter_hook_config_requires_hook_name() {
		$result = AcrossAI_Abilities_Validator::validate_callback_config( 'filter_hook', [] );
		$this->assertWPError( $result );
	}

	/**
	 * filter_hook config passes with valid hook_name.
	 *
	 * @return void
	 */
	public function test_filter_hook_config_passes_with_valid_hook_name() {
		$result = AcrossAI_Abilities_Validator::validate_callback_config( 'filter_hook', [
			'hook_name' => 'my_hook',
		] );
		$this->assertTrue( $result );
	}

	/**
	 * wp_remote_post config rejects headers key (PD-002: no caller header propagation).
	 *
	 * @return void
	 */
	public function test_wp_remote_post_config_rejects_headers_key() {
		$result = AcrossAI_Abilities_Validator::validate_callback_config( 'wp_remote_post', [
			'url'     => 'https://example.com/api',
			'headers' => [ 'Authorization' => 'Bearer token' ],
		] );
		$this->assertWPError( $result );
	}

	/**
	 * wp_remote_post config rejects non-HTTPS URL.
	 *
	 * @return void
	 */
	public function test_wp_remote_post_config_rejects_non_https_url() {
		$result = AcrossAI_Abilities_Validator::validate_callback_config( 'wp_remote_post', [
			'url' => 'http://example.com/api',
		] );
		$this->assertWPError( $result );
	}

	/**
	 * wp_remote_post config rejects unknown keys.
	 *
	 * @return void
	 */
	public function test_wp_remote_post_config_rejects_unknown_keys() {
		$result = AcrossAI_Abilities_Validator::validate_callback_config( 'wp_remote_post', [
			'url'    => 'https://example.com/api',
			'method' => 'POST',
		] );
		$this->assertWPError( $result );
	}

	/**
	 * wp_remote_post config passes with valid url and optional timeout.
	 *
	 * @return void
	 */
	public function test_wp_remote_post_config_passes_valid() {
		$result = AcrossAI_Abilities_Validator::validate_callback_config( 'wp_remote_post', [
			'url'     => 'https://example.com/api',
			'timeout' => 15,
		] );
		$this->assertTrue( $result );
	}

	/**
	 * php_code config rejects unknown keys.
	 *
	 * @return void
	 */
	public function test_php_code_config_rejects_unknown_keys() {
		$result = AcrossAI_Abilities_Validator::validate_callback_config( 'php_code', [
			'code'  => 'return 1;',
			'extra' => 'bad',
		] );
		$this->assertWPError( $result );
	}

	/**
	 * noop callback_config always passes regardless of config value.
	 *
	 * @return void
	 */
	public function test_noop_config_always_passes() {
		$this->assertTrue( AcrossAI_Abilities_Validator::validate_callback_config( 'noop', null ) );
		$this->assertTrue( AcrossAI_Abilities_Validator::validate_callback_config( 'noop', [] ) );
	}

	// =========================================================================
	// Schema size/depth guards
	// =========================================================================

	/**
	 * validate_schema rejects a payload exceeding 64 KB.
	 *
	 * @return void
	 */
	public function test_validate_schema_rejects_oversized_payload() {
		// Build a schema array whose JSON encoding exceeds 64 KB.
		$schema = [ 'properties' => [] ];
		for ( $i = 0; $i < 3000; $i++ ) {
			$schema['properties'][ 'field_' . $i ] = [ 'type' => 'string', 'description' => str_repeat( 'x', 10 ) ];
		}
		$result = AcrossAI_Abilities_Validator::validate_schema( $schema );
		$this->assertWPError( $result );
	}

	/**
	 * validate_schema rejects a schema nested deeper than JSON_MAX_DEPTH (10).
	 *
	 * @return void
	 */
	public function test_validate_schema_rejects_excess_depth() {
		$schema = [];
		$ref    = &$schema;
		for ( $i = 0; $i <= 11; $i++ ) {
			$ref['nested'] = [];
			$ref           = &$ref['nested'];
		}
		$result = AcrossAI_Abilities_Validator::validate_schema( $schema );
		$this->assertWPError( $result );
	}

	/**
	 * validate_schema passes for null (optional field).
	 *
	 * @return void
	 */
	public function test_validate_schema_passes_null() {
		$this->assertTrue( AcrossAI_Abilities_Validator::validate_schema( null ) );
	}

	/**
	 * validate_schema passes for a small valid schema.
	 *
	 * @return void
	 */
	public function test_validate_schema_passes_valid() {
		$result = AcrossAI_Abilities_Validator::validate_schema( [
			'type'       => 'object',
			'properties' => [ 'name' => [ 'type' => 'string' ] ],
		] );
		$this->assertTrue( $result );
	}

	// =========================================================================
	// Aggregate validator
	// =========================================================================

	/**
	 * validate_ability requires slug_suffix on create.
	 *
	 * @return void
	 */
	public function test_validate_ability_requires_slug_suffix_on_create() {
		$result = AcrossAI_Abilities_Validator::validate_ability( [], true );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_slug', $result->get_error_code() );
	}

	/**
	 * validate_ability passes minimal valid create payload.
	 *
	 * @return void
	 */
	public function test_validate_ability_passes_minimal_create() {
		$result = AcrossAI_Abilities_Validator::validate_ability( [
			'slug_suffix' => 'my-ability',
		], true );
		$this->assertTrue( $result );
	}

	/**
	 * validate_ability rejects invalid status.
	 *
	 * @return void
	 */
	public function test_validate_ability_rejects_invalid_status() {
		$result = AcrossAI_Abilities_Validator::validate_ability( [
			'slug_suffix' => 'valid',
			'status'      => 'archived',
		], true );
		$this->assertWPError( $result );
		$this->assertSame( 'invalid_status', $result->get_error_code() );
	}

	/**
	 * validate_ability validates callback_config for the correct type.
	 *
	 * @return void
	 */
	public function test_validate_ability_validates_callback_config_for_type() {
		// php_code with a blocked function in config should fail.
		$result = AcrossAI_Abilities_Validator::validate_ability( [
			'slug_suffix'     => 'valid',
			'callback_type'   => 'php_code',
			'callback_config' => [ 'code' => 'exec("ls");' ],
		], true );
		$this->assertWPError( $result );
	}

	// =========================================================================
	// Sanitizer: strip_protected_fields_for_non_db (immutable-field enforcement)
	// =========================================================================

	/**
	 * strip_protected_fields_for_non_db removes identity and execution fields.
	 *
	 * @return void
	 */
	public function test_strip_protected_fields_removes_identity_fields() {
		$fields = [
			'label'           => 'Override Label',
			'description'     => 'Override Desc',
			'category'        => 'Override Cat',
			'callback_type'   => 'php_code',
			'callback_config' => [ 'code' => 'return 1;' ],
			'input_schema'    => [ 'type' => 'object' ],
			'output_schema'   => [ 'type' => 'object' ],
			'status'          => 'publish',
			'ability_slug'    => 'acrossai-abilities/override',
			'slug_suffix'     => 'override',
			'source'          => 'plugin',
			'show_in_mcp'     => true,   // editable — must survive.
			'readonly'        => false,  // editable — must survive.
			'mcp_type'        => 'tool', // editable — must survive.
		];

		$result = AcrossAI_Abilities_Sanitizer::strip_protected_fields_for_non_db( $fields );

		// Protected fields must be gone.
		$this->assertArrayNotHasKey( 'label',           $result );
		$this->assertArrayNotHasKey( 'description',     $result );
		$this->assertArrayNotHasKey( 'category',        $result );
		$this->assertArrayNotHasKey( 'callback_type',   $result );
		$this->assertArrayNotHasKey( 'callback_config', $result );
		$this->assertArrayNotHasKey( 'input_schema',    $result );
		$this->assertArrayNotHasKey( 'output_schema',   $result );
		$this->assertArrayNotHasKey( 'status',          $result );
		$this->assertArrayNotHasKey( 'ability_slug',    $result );
		$this->assertArrayNotHasKey( 'slug_suffix',     $result );
		$this->assertArrayNotHasKey( 'source',          $result );

		// Override-only fields must survive.
		$this->assertArrayHasKey( 'show_in_mcp', $result );
		$this->assertArrayHasKey( 'readonly',    $result );
		$this->assertArrayHasKey( 'mcp_type',    $result );
	}

	/**
	 * strip_protected_fields_for_non_db is a no-op for empty input.
	 *
	 * @return void
	 */
	public function test_strip_protected_fields_noop_for_empty() {
		$result = AcrossAI_Abilities_Sanitizer::strip_protected_fields_for_non_db( [] );
		$this->assertSame( [], $result );
	}

	// =========================================================================
	// Sanitizer: sanitize_callback_config clamps timeout and strips headers
	// =========================================================================

	/**
	 * sanitize_callback_config for wp_remote_post clamps timeout to 1–30.
	 *
	 * @return void
	 */
	public function test_sanitize_callback_config_clamps_timeout() {
		$result = AcrossAI_Abilities_Sanitizer::sanitize_callback_config( 'wp_remote_post', [
			'url'     => 'https://example.com',
			'timeout' => 999,
		] );

		$this->assertNotNull( $result );
		$this->assertSame( 30, $result['timeout'] );
	}

	/**
	 * sanitize_callback_config for wp_remote_post strips unknown keys including headers.
	 *
	 * @return void
	 */
	public function test_sanitize_callback_config_strips_headers_key() {
		$result = AcrossAI_Abilities_Sanitizer::sanitize_callback_config( 'wp_remote_post', [
			'url'     => 'https://example.com',
			'headers' => [ 'Authorization' => 'Bearer token' ],
		] );

		$this->assertNotNull( $result );
		$this->assertArrayNotHasKey( 'headers', $result );
	}

	/**
	 * sanitize_php_code strips PHP opening tags.
	 *
	 * @return void
	 */
	public function test_sanitize_php_code_strips_opening_tags() {
		$result = AcrossAI_Abilities_Sanitizer::sanitize_php_code( '<?php return 1;' );
		$this->assertStringNotContainsString( '<?', $result );
	}
}
