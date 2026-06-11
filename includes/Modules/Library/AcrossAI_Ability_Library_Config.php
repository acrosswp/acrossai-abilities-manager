<?php
/**
 * Library config service — static utility for reading/writing library config.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Modules/Library
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Static utility. No instantiation needed.
 */
class AcrossAI_Ability_Library_Config {

	const OPTION_KEY     = 'acrossai_library_config';
	const MAX_KEY_LENGTH = 100;
	const MAX_KEYS       = 50;
	const MAX_SUB_KEYS   = 50;
	const MAX_SLUGS      = self::MAX_SUB_KEYS;
	const VALID_MODES    = array( 'all', 'specific' );

	/**
	 * Returns the full saved config array.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_config(): array {
		$raw = get_site_option( self::OPTION_KEY, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Sanitizes and stores a new config. Returns true on success.
	 *
	 * @param array<string, mixed> $raw Raw POST payload.
	 * @return bool
	 */
	public static function save_config( array $raw ): bool {
		$sanitized = array();
		$count     = 0;
		foreach ( $raw as $key => $entry ) {
			if ( $count >= self::MAX_KEYS ) {
				break;
			}
			$clean_key = self::sanitize_key_field( (string) $key );
			if ( '' === $clean_key ) {
				continue;
			}
			$sanitized[ $clean_key ] = self::sanitize_entry( is_array( $entry ) ? $entry : array() );
			++$count;
		}

		// Sparse storage (FR-017): strip entries that are at their default state.
		// Absent key in get_config() already means enabled=true / mode='all', so storing
		// that state explicitly adds noise without adding information.
		foreach ( $sanitized as $key => $entry ) {
			if ( true === $entry['enabled'] && 'all' === $entry['mode'] && empty( $entry['sub_keys'] ) ) {
				unset( $sanitized[ $key ] );
			}
		}

		return update_site_option( self::OPTION_KEY, $sanitized );
	}

	/**
	 * Sanitizes a single category entry.
	 *
	 * On-disk shape uses sub_keys as the inner map key for backwards compatibility
	 * with saved configs written before Feature 031.
	 *
	 * @param array<string, mixed> $raw Raw entry.
	 * @return array<string, mixed>
	 */
	public static function sanitize_entry( array $raw ): array {
		$enabled = isset( $raw['enabled'] ) ? (bool) $raw['enabled'] : true;
		$mode    = isset( $raw['mode'] ) && in_array( $raw['mode'], self::VALID_MODES, true )
			? $raw['mode']
			: 'all';

		$sub_keys = array();
		if ( isset( $raw['sub_keys'] ) && is_array( $raw['sub_keys'] ) ) {
			$count = 0;
			foreach ( $raw['sub_keys'] as $sk => $sv ) {
				if ( $count >= self::MAX_SUB_KEYS ) {
					break;
				}
				$clean_sk = self::sanitize_key_field( (string) $sk );
				if ( '' === $clean_sk ) {
					continue;
				}
				$sub_keys[ $clean_sk ] = (bool) $sv;
				++$count;
			}
		}

		return array(
			'enabled'  => $enabled,
			'mode'     => $mode,
			'sub_keys' => $sub_keys,
		);
	}

	/**
	 * Sanitizes a raw key string: sanitize_key() + max length guard.
	 *
	 * @param string $key Raw key string.
	 * @return string
	 */
	public static function sanitize_key_field( string $key ): string {
		$clean = sanitize_key( $key );
		return substr( $clean, 0, self::MAX_KEY_LENGTH );
	}
}
