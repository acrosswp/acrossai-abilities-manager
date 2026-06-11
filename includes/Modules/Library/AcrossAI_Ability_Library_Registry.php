<?php
/**
 * Library Registry — collects definitions via the acrossai_abilities_api_init filter.
 *
 * Add-ons hook into acrossai_abilities_api_init at init priority 10 (before P99 collection).
 * Registry fires at init P99 to ensure all add-ons have registered first.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Modules/Library
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Collects and validates add-on ability definitions at init P99.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Library_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Library_Registry|null
	 */
	protected static $instance = null;

	/**
	 * Cached definitions after collection.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private static $definitions = null;

	/**
	 * Required top-level fields in each definition.
	 *
	 * @var string[]
	 */
	private const REQUIRED_FIELDS = array(
		'category',
		'category_label',
		'slug',
		'slug_label',
		'name',
		'args',
	);

	/**
	 * Allowlist of permitted keys in the definition's args array (SC-027-02).
	 *
	 * @var string[]
	 */
	private const ALLOWED_ARGS_FIELDS = array(
		'label',
		'description',
		'category',
		'execute_callback',
		'permission_callback',
		'input_schema',
		'output_schema',
		'meta',
	);

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since  0.1.0
	 * @return AcrossAI_Ability_Library_Registry
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Fire the acrossai_abilities_api_init filter and cache validated definitions.
	 *
	 * Idempotent: subsequent calls within the same request are no-ops.
	 * Wired at init P99 via includes/Main.php.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function collect(): void {
		if ( ! is_null( self::$definitions ) ) {
			return;
		}

		/**
		 * Filter: collect ability definitions from add-ons.
		 *
		 * Add-ons hook here at standard init priority (10) and push definition arrays
		 * onto the accumulator. Each definition must include category, category_label,
		 * slug, slug_label, name, and args. Unknown args keys are stripped.
		 * Note: the top-level 'category' field is the Library card grouping key; it is
		 * distinct from the 'category' key permitted inside args (the WordPress Abilities
		 * API ability category). These two fields exist at different array depths and are
		 * validated independently.
		 *
		 * @since 0.1.0
		 * @param array<int, array<string, mixed>> $definitions Accumulated definitions.
		 */
		$raw = apply_filters( 'acrossai_abilities_api_init', array() );

		self::$definitions = $this->validate_and_normalize( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Return cached definitions (call after collect()).
	 *
	 * @since  0.1.0
	 * @return array<int, array<string, mixed>>
	 */
	public function get_definitions(): array {
		return self::$definitions ?? array();
	}

	/**
	 * Validate and normalize raw filter output.
	 *
	 * Each entry must pass required-field checks. The args sub-array is filtered
	 * to ALLOWED_ARGS_FIELDS only (SC-027-02). Entries that fail required-field
	 * validation are logged (WP_DEBUG_LOG) and skipped.
	 *
	 * @since  0.1.0
	 * @param  array<int, mixed> $raw Raw filter output.
	 * @return array<int, array<string, mixed>>
	 */
	private function validate_and_normalize( array $raw ): array {
		$valid = array();

		foreach ( $raw as $index => $item ) {
			if ( ! is_array( $item ) ) {
				$this->log_invalid( $index, 'definition is not an array' );
				continue;
			}

			foreach ( self::REQUIRED_FIELDS as $field ) {
				if ( ! isset( $item[ $field ] ) || '' === $item[ $field ] ) {
					$this->log_invalid( $index, "missing or empty required field: {$field}" );
					continue 2;
				}
			}

			if ( ! is_array( $item['args'] ) ) {
				$this->log_invalid( $index, "'args' must be an array" );
				continue;
			}

			// Strip any args keys not in the allowlist (SC-027-02).
			$item['args'] = array_intersect_key(
				$item['args'],
				array_flip( self::ALLOWED_ARGS_FIELDS )
			);

			$category = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $item['category'] );
			$slug     = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $item['slug'] );
			// Preserve the namespace/name slash: sanitize_key() strips '/', corrupting names like 'plugin/ability'.
			$name = preg_replace( '/[^a-z0-9_\-\/]/', '', strtolower( (string) $item['name'] ) );

			if ( '' === $category || '' === $slug || '' === $name ) {
				$this->log_invalid( $index, 'category, slug, or name became empty after sanitization' );
				continue;
			}

			$valid[] = array(
				'category'       => $category,
				'category_label' => wp_kses_post( (string) $item['category_label'] ),
				'slug'           => $slug,
				'slug_label'     => wp_kses_post( (string) $item['slug_label'] ),
				'name'           => $name,
				'args'           => $item['args'],
			);
		}

		return $valid;
	}

	/**
	 * Write a debug log entry for an invalid definition.
	 *
	 * @since  0.1.0
	 * @param  int|string $index Definition index.
	 * @param  string     $reason Reason for rejection.
	 * @return void
	 */
	private function log_invalid( $index, string $reason ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "AcrossAI Library Registry: skipping definition[{$index}] — {$reason}" );
		}
	}
}
