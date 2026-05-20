<?php
/**
 * Protected namespace prefixes for custom abilities
 *
 * @package AcrossAI_Abilities_Manager
 * @subpackage Includes\Utilities
 * @since 0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

/**
 * Class AcrossAI_Protected_Custom_Abilities
 *
 * Manages protected namespace prefixes that cannot be used for custom abilities.
 * All static methods (Memory DEC-UTILITY-STATIC-ONLY).
 *
 * @since 0.0.1
 */
class AcrossAI_Protected_Custom_Abilities {

	/**
	 * Get list of protected namespace prefixes.
	 *
	 * Extensible via apply_filters() (Memory DEC-PROTECTED-SLUGS-PATTERN).
	 * These prefixes cannot be used for custom ability slugs.
	 *
	 * @since 0.0.1
	 * @param string $context Context for filtering (e.g., 'custom_abilities').
	 * @return array Array of protected prefixes.
	 */
	public static function get_protected_prefixes( $context = 'custom_abilities' ) {
		$default_prefixes = array(
			'acrossai',
			'mcp',
			'wp',
			'system',
			'core',
		);

		/**
		 * Filter protected ability namespace prefixes.
		 *
		 * @since 0.0.1
		 * @param array  $prefixes Default list of protected prefixes.
		 * @param string $context  Context for the filter.
		 */
		return apply_filters( 'acrossai_protected_ability_prefixes', $default_prefixes, $context );
	}

	/**
	 * Check if a slug uses a protected prefix.
	 *
	 * @since 0.0.1
	 * @param string $slug Ability slug (format: "namespace/name").
	 * @param string $context Context for filtering.
	 * @return bool True if slug uses protected prefix, false otherwise.
	 */
	public static function is_protected_prefix( $slug = '', $context = 'custom_abilities' ) {
		if ( empty( $slug ) || strpos( $slug, '/' ) === false ) {
			return false;
		}

		$parts     = explode( '/', $slug );
		$namespace = $parts[0];
		$prefixes  = self::get_protected_prefixes( $context );

		return in_array( $namespace, $prefixes, true );
	}
}
