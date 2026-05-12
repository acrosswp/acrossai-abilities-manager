<?php
/**
 * Merges registry ability data with stored override data.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Utilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Static utility for merging registry ability data with stored overrides.
 *
 * @since 0.1.0
 */
class AcrossAI_Ability_Merger {

	/**
	 * Fields that can be overridden (nullable columns in the override table).
	 *
	 * @var string[]
	 */
	private static $overridable_fields = array(
		'site_allowed',
		'readonly',
		'destructive',
		'idempotent',
		'show_in_rest',
		'show_in_mcp',
		'mcp_type',
		'mcp_servers',
	);

	/**
	 * Merge registry ability data with a stored override row into an Effective Ability shape.
	 *
	 * For each overridable field, a non-null override value wins over the registry default.
	 * The returned array includes `has_override`, `updated_at`, `updated_by`, and `_registry` keys.
	 *
	 * @since  0.1.0
	 * @param  array       $registry Raw ability array from wp_get_ability().
	 * @param  object|null $override BerlinDB Row object or null if no override exists.
	 * @return array
	 */
	public static function merge( array $registry, $override ): array {
		$result = $registry;

		$has_override = null !== $override;

		foreach ( self::$overridable_fields as $field ) {
			if ( $has_override && null !== $override->{$field} ) {
				$result[ $field ] = $override->{$field};
			} else {
				// Fall back to registry value; use null if registry does not define it.
				$result[ $field ] = isset( $registry[ $field ] ) ? $registry[ $field ] : null;
			}
		}

		$result['has_override'] = $has_override;
		$result['updated_at']   = $has_override ? $override->updated_at : null;
		$result['updated_by']   = $has_override ? $override->updated_by : null;
		$result['_registry']    = $registry;

		return $result;
	}

	/**
	 * Check whether every field in a payload matches the corresponding registry default.
	 *
	 * Returns true only when NO field in the payload differs from the registry value,
	 * meaning a DB write would be pointless (FR-024).
	 *
	 * @since  0.1.0
	 * @param  array $payload  Submitted field values (keyed by field name).
	 * @param  array $registry Raw ability array from wp_get_ability().
	 * @return bool
	 */
	public static function is_all_default( array $payload, array $registry ): bool {
		foreach ( self::$overridable_fields as $field ) {
			if ( ! array_key_exists( $field, $payload ) ) {
				continue;
			}
			$registry_value = isset( $registry[ $field ] ) ? $registry[ $field ] : null;
			// Strict comparison is REQUIRED here. PHP loose equality treats false == null
			// as true, which would conflate "explicit No override" (false) with "no override
			// set/Inherit" (null). That would silently discard a valid admin governance decision.
			if ( $payload[ $field ] !== $registry_value ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Normalize a WP_Ability object or raw array into a flat array shape.
	 *
	 * Uses wp_get_abilities() and wp_get_ability() which return WP_Ability objects (WP 6.9+).
	 * All internal utilities (Merger, SourceDetector, RegistryQuery) expect a flat
	 * array. Call this once at the boundary before passing registry data anywhere.
	 *
	 * @since  0.1.0
	 * @param  \WP_Ability|array $ability WP_Ability object or already-normalized array.
	 * @return array Flat ability array with slug, label, description, category,
	 *               provider, and all overridable fields defaulted to null.
	 */
	public static function normalize_registry( $ability ): array {
		if ( is_array( $ability ) ) {
			return $ability;
		}

		if ( ! ( $ability instanceof \WP_Ability ) ) {
			return array();
		}

		$name        = $ability->get_name();
		$slash_pos   = strpos( $name, '/' );
		$provider    = false !== $slash_pos ? substr( $name, 0, $slash_pos ) : '';
		$annotations = $ability->get_meta_item( 'annotations', array() );

		return array(
			'slug'         => $name,
			'label'        => $ability->get_label(),
			'description'  => $ability->get_description(),
			'category'     => $ability->get_category(),
			'provider'     => $provider,
			'show_in_rest' => $ability->get_meta_item( 'show_in_rest', false ),
			'readonly'     => isset( $annotations['readonly'] ) ? $annotations['readonly'] : null,
			'destructive'  => isset( $annotations['destructive'] ) ? $annotations['destructive'] : null,
			'idempotent'   => isset( $annotations['idempotent'] ) ? $annotations['idempotent'] : null,
			// Fields that only exist in the override table; null = "use registry default".
			'site_allowed' => null,
			'show_in_mcp'  => null,
			'mcp_type'     => null,
			'mcp_servers'  => null,
		);
	}
}
