<?php
/**
 * Runtime override application.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types=1 );

namespace AcrossAI_Abilities_Manager\Runtime;

use AcrossAI_Abilities_Manager\Database\Repository;
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
	 * It handles both metadata overrides for provider abilities and registration
	 * of user-defined custom abilities.
	 *
	 * The method checks three things in order:
	 * - whether bootstrap already ran in this request
	 * - whether the current request should skip runtime overrides entirely
	 * - whether any saved override rows or custom abilities exist to apply/register
	 *
	 * @return void
	 */
	public static function bootstrap(): void {
		// This condition looks for either of two reasons to exit immediately:
		// 1. bootstrap already ran in this request
		// 2. the current request is the Abilities Editor screen, where runtime
		// overrides should not mutate abilities while the UI is rendering.
		if ( self::$bootstrapped || self::should_skip_request() ) {
			return;
		}

		self::$bootstrapped = true;
		self::prime_overrides();

		// If the overrides cache is empty there is nothing to apply; leave the core
		// registration path untouched.
		if ( array() === self::$overrides ) {
			return;
		}

		// Site-level disallow behaves like an audit/governance pass: after abilities
		// finish registering, the disabled ones are removed from the live registry.
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'unregister_disallowed' ), 999 );

		// Some abilities use `ability_class` whose constructor ignores `meta` passed
		// in registration args, calling their own meta() method instead. The action
		// below runs after all abilities are registered and patches the meta property
		// of those ability objects directly, ensuring mcp_public overrides take effect.
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'patch_mcp_overrides' ), 1000 );

		// This is the key runtime hook. WordPress calls this filter before it
		// instantiates each ability, which makes it the safest and cheapest place
		// to inject saved metadata overrides.
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'apply' ), 10, 2 );

		// Register custom abilities defined by site admins. This runs at high priority
		// on wp_abilities_api_init so custom abilities are available alongside provider abilities.
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_custom_abilities' ), 5 );
	}

	/**
	 * Removes site-disallowed abilities from the live registry.
	 *
	 * This mirrors the working pattern used in the audit plugin: let providers
	 * register their abilities first, then unregister any slugs that the site has
	 * explicitly disabled.
	 *
	 * @return void
	 */
	public static function unregister_disallowed(): void {
		if ( self::should_skip_request() || ! function_exists( 'wp_unregister_ability' ) || ! function_exists( 'wp_has_ability' ) ) {
			return;
		}

		self::prime_overrides();

		foreach ( self::$overrides as $slug => $override ) {
			if ( false === ( $override['site_allowed'] ?? null ) && wp_has_ability( $slug ) ) {
				wp_unregister_ability( $slug );
			}
		}
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

		// If there is no override row for this slug, return the original args unchanged.
		if ( null !== $override ) {
			try {
				// Merge only the supported override fields into the original args.
				$args = self::merge_override( $args, $override );
			} catch ( \Throwable $throwable ) {
				// On any runtime failure, emit the diagnostic hook and fall back to the
				// original args so registration can continue safely.
				self::notify_failure( $slug, $throwable->getMessage() );
			}
		}

		return $args;
	}

	/**
	 * Determines whether overrides should be skipped for the current request.
	 *
	 * The method currently looks for two conditions:
	 * - whether the request is running in wp-admin
	 * - whether the current `page` query parameter targets `acrossai-abilities-manager`
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
		return 'acrossai-abilities-manager' === $page;
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
		// When mcp_public is false but mcp_servers is non-empty the user chose
		// "specific servers" mode — the ability must still be marked public so the
		// MCP adapter registers it as a tool on every server. Mcp_Server_Filter then
		// removes it from servers not in the allowlist at request time.
		if ( array_key_exists( 'mcp_public', $override ) && null !== $override['mcp_public'] ) {
			$has_specific_servers          = is_array( $override['mcp_servers'] ?? null ) && ! empty( $override['mcp_servers'] );
			$args['meta']['mcp']['public'] = (bool) $override['mcp_public'] || $has_specific_servers;
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
	 * Patches MCP public visibility on registered abilities after all registration is complete.
	 *
	 * Some ability classes (e.g. those extending Abstract_Ability from the WordPress AI plugin)
	 * build their own args array inside their constructor and call their own meta() method,
	 * completely ignoring any `meta` key present in the registration $args. Because of this,
	 * the `wp_register_ability_args` filter applied in apply() has no effect on the meta
	 * stored by such abilities.
	 *
	 * This method runs at priority 1000 on `wp_abilities_api_init`, after all abilities have
	 * been registered and after unregister_disallowed() has already removed site-disabled
	 * abilities. For each override that explicitly sets mcp_public, it uses a bound Closure
	 * to write directly to the protected `meta` property of the WP_Ability object, ensuring
	 * that the MCP adapter's is_ability_mcp_public() check returns the correct value.
	 *
	 * @return void
	 */
	public static function patch_mcp_overrides(): void {
		if ( self::should_skip_request() || ! function_exists( 'wp_has_ability' ) || ! function_exists( 'wp_get_ability' ) ) {
			return;
		}

		self::prime_overrides();

		foreach ( self::$overrides as $slug => $override ) {
			// Only patch when the override row has an explicit non-null mcp_public value.
			if ( ! array_key_exists( 'mcp_public', $override ) || null === $override['mcp_public'] ) {
				continue;
			}

			// Skip abilities that were already removed by unregister_disallowed().
			if ( ! wp_has_ability( $slug ) ) {
				continue;
			}

			$ability = wp_get_ability( $slug );
			if ( ! $ability instanceof \WP_Ability ) {
				continue;
			}

			try {
				// "Specific servers" mode stores mcp_public=false with a non-empty
				// mcp_servers list. The ability must still be globally registered as a
				// tool (mcp.public=true) so the MCP adapter includes it on all servers;
				// Mcp_Server_Filter removes it from non-allowed servers at request time.
				$has_specific_servers = is_array( $override['mcp_servers'] ?? null ) && ! empty( $override['mcp_servers'] );
				$mcp_public_val       = (bool) $override['mcp_public'] || $has_specific_servers;

				// Use ReflectionProperty to directly modify the protected $meta property
				// of the WP_Ability object, since no public setter exists.
				$reflection = new \ReflectionProperty( \WP_Ability::class, 'meta' );
				$current_meta = (array) $reflection->getValue( $ability );

				if ( ! isset( $current_meta['mcp'] ) || ! is_array( $current_meta['mcp'] ) ) {
					$current_meta['mcp'] = array();
				}
				$current_meta['mcp']['public'] = $mcp_public_val;

				$reflection->setValue( $ability, $current_meta );
			} catch ( \Throwable $throwable ) {
				self::notify_failure( $slug, $throwable->getMessage() );
			}
		}
	}

	/**
	 * Emits an action when override application fails.
	 *
	 * The `acrossai_abilities_manager_override_error` hook is an action instead of a filter
	 * because the plugin is announcing that a runtime failure occurred. Callers can
	 * listen for this event to add logging, debugging, metrics, or notifications.
	 *
	 * @param string $slug    Ability slug whose override failed to apply.
	 * @param string $message Human-readable failure message.
	 * @return void
	 */
	private static function notify_failure( string $slug, string $message ): void {
		do_action( 'acrossai_abilities_manager_override_error', $slug, $message );
	}

	/**
	 * Returns true when an ability is configured for "specific servers" mode.
	 *
	 * An ability has a server restriction when its override row stores mcp_public=false
	 * (or null) with a non-empty mcp_servers list. In all other cases (no override, or
	 * mcp_public=true) there is no per-server restriction and the ability passes through
	 * MCP list filters untouched.
	 *
	 * @param string $slug Ability slug to check.
	 * @return bool True when a server allowlist is active for this ability.
	 */
	public static function has_server_restriction( string $slug ): bool {
		self::prime_overrides();

		$override = self::$overrides[ $slug ] ?? null;

		if ( ! is_array( $override ) ) {
			return false;
		}

		$mcp_public  = $override['mcp_public'] ?? null;
		$mcp_servers = $override['mcp_servers'] ?? null;

		return ( true !== $mcp_public ) && is_array( $mcp_servers ) && ! empty( $mcp_servers );
	}

	/**
	 * Checks whether an ability should be exposed to a specific MCP server.
	 *
	 * Uses the stored mcp_public and mcp_servers values from an override to determine
	 * visibility. Returns false when no override row exists. Callers that want
	 * "pass-through on no override" should call has_server_restriction() first and
	 * skip this method when it returns false.
	 *
	 * @param string $slug      Ability slug to check.
	 * @param string $server_id MCP server ID to check visibility for.
	 * @return bool True if ability should be exposed to server, false otherwise.
	 */
	public static function should_expose_to_mcp_server( string $slug, string $server_id ): bool {
		self::prime_overrides();

		$override = self::$overrides[ $slug ] ?? null;

		// If no override exists, use the default (not exposed).
		if ( ! is_array( $override ) ) {
			return false;
		}

		$mcp_public  = $override['mcp_public'] ?? null;
		$mcp_servers = $override['mcp_servers'] ?? null;

		// If mcp_public is true, expose to all servers.
		if ( true === $mcp_public ) {
			return true;
		}

		// If mcp_public is false/null and servers list is empty/null, don't expose.
		if ( ! is_array( $mcp_servers ) || empty( $mcp_servers ) ) {
			return false;
		}

		// Expose if the current server is in the allowed list.
		return in_array( sanitize_text_field( $server_id ), array_map( 'sanitize_text_field', $mcp_servers ), true );
	}

	/**
	 * Registers all active custom abilities defined by site admins.
	 *
	 * This method is called at priority 5 on `wp_abilities_api_init` (before provider
	 * registrations are processed by the filter at priority 10) to register custom abilities.
	 * Only custom abilities with status='active' are registered.
	 *
	 * @return void
	 */
	public static function register_custom_abilities(): void {
		if ( self::should_skip_request() || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$result = Repository::get_all_custom_abilities(
			array(
				'status'   => 'active',
				'per_page' => 0,
			)
		);

		foreach ( $result['items'] as $custom_ability ) {
			try {
				self::register_custom_ability( $custom_ability );
			} catch ( \Throwable $throwable ) {
				self::notify_failure( $custom_ability['ability_slug'], 'Custom ability registration failed: ' . $throwable->getMessage() );
			}
		}
	}

	/**
	 * Registers a single custom ability with WordPress.
	 *
	 * Converts custom ability database record to WordPress Abilities API format,
	 * including converting stored callback strings to callable functions when possible.
	 *
	 * @param array<string, mixed> $custom_ability Normalized custom ability record from Repository.
	 * @return void
	 */
	private static function register_custom_ability( array $custom_ability ): void {
		$slug = (string) $custom_ability['ability_slug'];
		$args = array(
			'label'       => (string) $custom_ability['label'],
			'description' => (string) $custom_ability['description'],
			'category'    => ! empty( $custom_ability['category'] ) ? (string) $custom_ability['category'] : 'general',
		);

		// Include input_schema if present.
		if ( ! empty( $custom_ability['input_schema'] ) && is_array( $custom_ability['input_schema'] ) ) {
			$args['input_schema'] = $custom_ability['input_schema'];
		}

		// Include output_schema if present.
		if ( ! empty( $custom_ability['output_schema'] ) && is_array( $custom_ability['output_schema'] ) ) {
			$args['output_schema'] = $custom_ability['output_schema'];
		}

		// Convert execute_callback string to callable.
		if ( ! empty( $custom_ability['execute_callback'] ) ) {
			$args['execute_callback'] = self::get_callable_from_string(
				(string) $custom_ability['execute_callback']
			);
		}

		// Convert permission_callback string to callable.
		if ( ! empty( $custom_ability['permission_callback'] ) ) {
			$args['permission_callback'] = self::get_callable_from_string(
				(string) $custom_ability['permission_callback']
			);
		}

		// Build metadata flags and visibility settings.
		$args['meta'] = array(
			'annotations' => array(),
			'mcp'         => array(),
		);

		// Apply tri-state metadata flags (true/false/null).
		foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $flag ) {
			if ( isset( $custom_ability[ $flag ] ) && null !== $custom_ability[ $flag ] ) {
				$args['meta']['annotations'][ $flag ] = (bool) $custom_ability[ $flag ];
			}
		}

		// Apply REST visibility.
		if ( isset( $custom_ability['show_in_rest'] ) && null !== $custom_ability['show_in_rest'] ) {
			$args['meta']['show_in_rest'] = (bool) $custom_ability['show_in_rest'];
		}

		// Apply MCP visibility.
		if ( isset( $custom_ability['mcp_public'] ) && null !== $custom_ability['mcp_public'] ) {
			$args['meta']['mcp']['public'] = (bool) $custom_ability['mcp_public'];
		}

		// Apply MCP type if present.
		if ( ! empty( $custom_ability['mcp_type'] ) ) {
			$args['meta']['mcp']['type'] = sanitize_text_field( (string) $custom_ability['mcp_type'] );
		}

		// Merge custom metadata if present.
		if ( ! empty( $custom_ability['custom_meta'] ) && is_array( $custom_ability['custom_meta'] ) ) {
			$args['meta'] = array_replace_recursive( $args['meta'], $custom_ability['custom_meta'] );
		}

		wp_register_ability( $slug, $args );
	}

	/**
	 * Converts a callback string (function name or class::method) to a callable.
	 *
	 * This helper is needed because custom abilities store callbacks as strings in the database.
	 * It attempts to resolve the string to a PHP callable function or method.
	 *
	 * Supports formats:
	 * - 'function_name' → callable function
	 * - 'ClassName::method_name' → static method
	 * - '__invoke()' → callable object (if stored as 'ClassName::__invoke')
	 *
	 * @param string $callback_string Callback string to resolve.
	 * @return callable|null Callable if found, null otherwise.
	 */
	private static function get_callable_from_string( string $callback_string ): ?callable {
		$callback_string = trim( (string) $callback_string );

		if ( empty( $callback_string ) ) {
			return null;
		}

		// Check if it's a simple function name.
		if ( function_exists( $callback_string ) ) {
			return $callback_string;
		}

		// Check if it's a static method (ClassName::method_name).
		if ( strpos( $callback_string, '::' ) !== false ) {
			list( $class, $method ) = explode( '::', $callback_string, 2 );
			$class                  = trim( $class );
			$method                 = trim( $method );

			if ( class_exists( $class ) && method_exists( $class, $method ) ) {
				return array( $class, $method );
			}
		}

		// If the string doesn't resolve, return null and log the error at registration time.
		return null;
	}
}
