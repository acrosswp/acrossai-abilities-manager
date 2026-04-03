<?php
/**
 * Runtime override application.
 *
 * @package Abilities_Editor
 */

declare( strict_types=1 );

namespace Abilities_Editor\Runtime;

use Abilities_Editor\Database\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Applies stored metadata overrides to abilities as WordPress registers them.
 */
class Override_Applier {
	/**
	 * Cached override rows keyed by ability slug.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $overrides = null;

	/**
	 * Tracks whether runtime bootstrap has already completed for the request.
	 *
	 * @var bool
	 */
	private static bool $bootstrapped = false;

	/**
	 * Initializes the runtime override layer for the current request.
	 *
	 * This method is intended to run from the `wp_abilities_api_init` action.
	 * It decides whether the plugin should attach the core
	 * `wp_register_ability_args` filter for this request.
	 *
	 * The method checks three things in order:
	 * - whether bootstrap already ran in this request
	 * - whether the current request should skip runtime overrides entirely
	 * - whether any saved override rows exist to apply
	 *
	 * @return void
	 */
	public static function bootstrap(): void {
		// This condition looks for either of two reasons to exit immediately:
		// 1. bootstrap already ran in this request
		// 2. the current request is the Abilities Editor screen, where runtime
		//    overrides should not mutate abilities while the UI is rendering
		if ( self::$bootstrapped || self::should_skip_request() ) {
			return;
		}

		self::$bootstrapped = true;
		self::prime_overrides();

		// If the cache is empty, there is nothing to apply, so the plugin avoids
		// registering the filter and leaves the core registration path untouched.
		if ( array() === self::$overrides ) {
			return;
		}

		// This is the key runtime hook. WordPress calls this filter before it
		// instantiates each ability, which makes it the safest and cheapest place
		// to inject saved metadata overrides.
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'apply' ), 10, 2 );
	}

	/**
	 * Filters ability registration arguments for a single ability slug.
	 *
	 * Core passes the original ability args and slug into this callback through
	 * `wp_register_ability_args`. The method checks whether a saved override row
	 * exists for that slug and, if so, merges the supported metadata fields.
	 *
	 * The try/catch block exists so a bad override payload does not stop WordPress
	 * from registering the ability with its original arguments.
	 *
	 * @param array<string, mixed> $args Original ability registration arguments.
	 * @param string               $slug Ability slug currently being registered.
	 * @return array<string, mixed> Final ability registration arguments.
	 */
	public static function apply( array $args, string $slug ): array {
		$override = self::$overrides[ $slug ] ?? null;

		// This condition checks the slug-keyed cache. If no override row exists
		// for the current ability, the original args are returned unchanged.
		if ( null === $override ) {
			return $args;
		}

		try {
			// Merge only the supported override fields into the original args.
			return self::merge_override( $args, $override );
		} catch ( \Throwable $throwable ) {
			// On any runtime failure, emit the diagnostic hook and fall back to the
			// original args so registration can continue safely.
			self::notify_failure( $slug, $throwable->getMessage() );
			return $args;
		}
	}

	/**
	 * Determines whether overrides should be skipped for the current request.
	 *
	 * The method currently looks for two conditions:
	 * - whether the request is running in wp-admin
	 * - whether the current `page` query parameter targets `abilities-editor`
	 *
	 * When both are true, the plugin skips installing the runtime filter so the
	 * editor screen can inspect and save data without mutating registrations in
	 * the same request.
	 *
	 * @return bool True when the current request should bypass override application.
	 */
	private static function should_skip_request(): bool {
		// If the request is outside wp-admin, runtime overrides should still run,
		// so the method returns false immediately.
		if ( ! is_admin() ) {
			return false;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// This equality check is what identifies the plugin's own editor page.
		return 'abilities-editor' === $page;
	}

	/**
	 * Loads persisted overrides into a request-local slug-keyed cache.
	 *
	 * The repository returns normalized rows from the custom table. This method
	 * converts that list into an associative array keyed by `ability_slug` so
	 * later lookups in apply() are constant-time array reads.
	 *
	 * @return void
	 */
	private static function prime_overrides(): void {
		if ( null !== self::$overrides ) {
			return;
		}

		self::$overrides = array();
		$result          = Repository::get_all( array( 'per_page' => 0 ) );

		foreach ( $result['items'] as $override ) {
			// This condition checks that the row has a usable slug before it is added
			// to the cache. Rows without a slug cannot be matched safely.
			if ( ! empty( $override['ability_slug'] ) ) {
				self::$overrides[ (string) $override['ability_slug'] ] = $override;
			}
		}
	}

	/**
	 * Merges a stored override row into ability registration arguments.
	 *
	 * Only metadata is modified here. The original label, description, callbacks,
	 * category, and schemas stay intact while the plugin selectively updates the
	 * supported metadata fields.
	 *
	 * Before any conditional override is applied, the method normalizes the
	 * nested `meta`, `annotations`, and `mcp` arrays so later writes are safe.
	 *
	 * @param array<string, mixed> $args     Original ability registration arguments.
	 * @param array<string, mixed> $override Normalized override row from storage.
	 * @return array<string, mixed> Ability registration arguments with overrides applied.
	 */
	private static function merge_override( array $args, array $override ): array {
		$args['meta'] = wp_parse_args(
			is_array( $args['meta'] ?? null ) ? $args['meta'] : array(),
			array(
				'annotations'  => array(),
				'show_in_rest' => false,
				'mcp'          => array(),
			)
		);

		$args['meta']['annotations'] = is_array( $args['meta']['annotations'] ) ? $args['meta']['annotations'] : array();
		$args['meta']['mcp']         = is_array( $args['meta']['mcp'] ) ? $args['meta']['mcp'] : array();

		// These flags are tri-state in storage. The condition inside the loop checks
		// both that the key exists and that its value is not null, which means only
		// explicit overrides replace the original annotation value.
		foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $key ) {
			if ( array_key_exists( $key, $override ) && null !== $override[ $key ] ) {
				$args['meta']['annotations'][ $key ] = (bool) $override[ $key ];
			}
		}

		// Apply `show_in_rest` only when the override row explicitly defines it.
		if ( array_key_exists( 'show_in_rest', $override ) && null !== $override['show_in_rest'] ) {
			$args['meta']['show_in_rest'] = (bool) $override['show_in_rest'];
		}

		// Apply MCP public visibility only when the override row explicitly defines it.
		if ( array_key_exists( 'mcp_public', $override ) && null !== $override['mcp_public'] ) {
			$args['meta']['mcp']['public'] = (bool) $override['mcp_public'];
		}

		// MCP type is applied only when the stored override contains a non-empty value.
		if ( ! empty( $override['mcp_type'] ) ) {
			$args['meta']['mcp']['type'] = sanitize_text_field( (string) $override['mcp_type'] );
		}

		// Custom meta is merged last so user-defined nested keys can extend the
		// normalized structure after the plugin has prepared the standard meta tree.
		if ( ! empty( $override['custom_meta'] ) && is_array( $override['custom_meta'] ) ) {
			$args['meta'] = array_replace_recursive( $args['meta'], $override['custom_meta'] );
		}

		return $args;
	}

	/**
	 * Emits an action when override application fails.
	 *
	 * The `abilities_editor_override_error` hook is an action instead of a filter
	 * because the plugin is announcing that a runtime failure occurred. Callers can
	 * listen for this event to add logging, debugging, metrics, or notifications.
	 *
	 * @param string $slug    Ability slug whose override failed to apply.
	 * @param string $message Human-readable failure message.
	 * @return void
	 */
	private static function notify_failure( string $slug, string $message ): void {
		do_action( 'abilities_editor_override_error', $slug, $message );
	}
}
