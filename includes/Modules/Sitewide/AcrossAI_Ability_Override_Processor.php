<?php
/**
 * Runtime override processor for sitewide ability management.
 *
 * Bridges DB-stored ability overrides (managed via the Abilities Manager UI) into live
 * WordPress ability registrations at request boot time.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Sitewide
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Sitewide;

use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// REST namespace used for PATH A (Manager request) detection.
// Override via wp-config.php constant or the 'acrossai_manager_rest_namespace' filter.
defined( 'ACROSSAI_MANAGER_REST_NAMESPACE' ) || define( 'ACROSSAI_MANAGER_REST_NAMESPACE', 'acrossai-abilities-manager/v1' );

/**
 * Bridges DB-stored ability overrides into live WordPress ability registrations.
 *
 * PATH A (Manager REST requests): registers no hooks — Manager UI sees pure WP registry values.
 * PATH B (all other requests): injects non-null DB override fields via wp_register_ability_args
 * filter and unregisters abilities with site_allowed = false after all registrations complete.
 *
 * All logic is static. The singleton instance exists solely as a Loader-compatible hook target.
 * Direct static calls (e.g. AcrossAI_Ability_Override_Processor::bust_cache()) remain valid.
 *
 * @since 0.1.0
 */
final class AcrossAI_Ability_Override_Processor {

	/**
	 * Singleton instance.
	 *
	 * @var AcrossAI_Ability_Override_Processor|null
	 */
	protected static $_instance = null;

	/**
	 * In-memory cache: slug → AcrossAI_Sitewide_Row. Null means not yet loaded.
	 *
	 * @var AcrossAI_Sitewide_Row[]|null
	 */
	protected static $_overrides_cache = null;

	/**
	 * Whether is_manager_rest_request() has already been evaluated this request.
	 *
	 * @var bool
	 */
	protected static $_checked = false;

	/**
	 * Memoized result of is_manager_rest_request().
	 *
	 * @var bool
	 */
	protected static $_is_manager = false;

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Get or create the singleton instance.
	 *
	 * @since  0.1.0
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Private constructor — instantiation via instance() only.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	// -------------------------------------------------------------------------
	// Loader-compatible instance wrappers (SEC-PLAN-002)
	// -------------------------------------------------------------------------

	/**
	 * Loader-compatible wrapper for boot(). Called via add_action().
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function boot_hook(): void {
		self::boot();
	}

	/**
	 * Loader-compatible wrapper for bust_cache(). Called via add_action().
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function bust_cache_hook(): void {
		self::bust_cache();
	}

	// -------------------------------------------------------------------------
	// Static core methods
	// -------------------------------------------------------------------------

	/**
	 * Boot the override processor at plugins_loaded P20.
	 *
	 * On PATH A (Manager REST requests) returns immediately without registering any
	 * hooks — Manager UI always sees pure WP registry values for the _registry layer.
	 * On PATH B registers the per-ability args filter, the post-registration
	 * unregistration action, and the MCP adapter server-allowlist filter.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function boot(): void {
		// FR-003 / SEC-PLAN-001: PATH A — Manager REST skips override injection entirely.
		if ( self::is_manager_rest_request() ) {
			return;
		}

		// PATH B: wire override injection into the WP Abilities API boot sequence.
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'inject_override_args' ), 10, 2 );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'unregister_blocked_abilities' ), 100001 );
		// T016: enforce the mcp_servers allowlist at MCP adapter registration time.
		// accepted_args = 3 captures the optional $server_id passed by the MCP adapter.
		add_filter( 'mcp_adapter_expose_ability', array( __CLASS__, 'filter_mcp_adapter_expose_ability' ), 10, 3 );
	}

	/**
	 * Detect whether the current request targets the Manager's own REST namespace.
	 *
	 * Performance optimisation only — NOT an access-control gate. Even if a spoofed URI
	 * triggers PATH A treatment the only consequence is that override injection is skipped;
	 * all REST routes remain protected by check_permission() independently.
	 *
	 * Detection is URI-path-based only. REQUEST_METHOD is NOT used as a gate (SEC-PLAN-001)
	 * so that Manager GET requests are correctly classified as PATH A.
	 *
	 * Memoized across repeated calls within the same request lifecycle.
	 *
	 * @since  0.1.0
	 * @return bool True when the current request is a Manager REST API request (PATH A).
	 */
	public static function is_manager_rest_request(): bool {
		if ( self::$_checked ) {
			return self::$_is_manager;
		}

		self::$_checked = true;

		// Non-HTTP contexts are never Manager REST requests.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			self::$_is_manager = false;
			return false;
		}

		if ( wp_doing_cron() ) {
			self::$_is_manager = false;
			return false;
		}

		if ( wp_doing_ajax() ) {
			self::$_is_manager = false;
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URI used for boolean strpos detection only, never echoed or used in SQL.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

		$namespace         = (string) apply_filters( 'acrossai_manager_rest_namespace', ACROSSAI_MANAGER_REST_NAMESPACE );
		self::$_is_manager = ( '' !== $uri ) &&
			false !== strpos( $uri, '/' . rest_get_url_prefix() . '/' . $namespace . '/' );

		return self::$_is_manager;
	}

	/**
	 * Load override rows from transient or DB into the in-memory static cache.
	 *
	 * Transient key: acrossai_ability_overrides_cache, TTL: 12h.
	 * Returns immediately if cache is already populated for this request.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	private static function load_overrides_cache(): void {
		if ( null !== self::$_overrides_cache ) {
			return;
		}

		$cached = get_transient( 'acrossai_ability_overrides_cache' );

		// SEC-PLAN-003: Validate transient output before use — treat non-array as cache miss
		// (guards against corrupted object-cache entries or DB row corruption).
		if ( ! is_array( $cached ) ) {
			$cached = null;
		}

		if ( null === $cached ) {
			$cached = AcrossAI_Sitewide_Query::instance()->get_all_overrides();
			set_transient( 'acrossai_ability_overrides_cache', $cached, 12 * HOUR_IN_SECONDS );
		}

		self::$_overrides_cache = $cached;
	}

	/**
	 * Filter callback: inject non-null DB override values into each ability's registration args.
	 *
	 * Registered at wp_register_ability_args P10 on PATH B only. Null DB values are skipped —
	 * null means "Inherit" and the registration-time default is preserved (FR-006).
	 *
	 * Field path map (FR-009):
	 *   site_allowed              → $args['site_allowed']  (top-level WP Abilities API field)
	 *   readonly/destructive/
	 *     idempotent              → $args['meta']['annotations']['<key>']
	 *   show_in_rest              → $args['meta']['show_in_rest']
	 *   show_in_mcp               → $args['meta']['mcp']['public']   (plugin-specific)
	 *   mcp_type                  → $args['meta']['mcp']['type']     (plugin-specific)
	 *   mcp_servers               → $args['meta']['mcp']['servers']  (plugin-specific; already
	 *                               decoded to array|null by AcrossAI_Sitewide_Row::__construct())
	 *   permission_callback       → $args['permission_callback']     (runtime AC enforcement;
	 *                               injected only when an access-control rule is stored in
	 *                               RuleQuery for this slug — checked independently of the
	 *                               override row)
	 *
	 * meta['mcp'] is not a WP core Abilities API field. It is consumed by the MCP integration
	 * layer of this plugin only. See FR-009 and the Constraints Assumption in spec.md.
	 *
	 * @since  0.1.0
	 * @param  array  $args Ability registration args.
	 * @param  string $slug Ability slug.
	 * @return array Modified args.
	 */
	public static function inject_override_args( array $args, string $slug ): array {
		self::load_overrides_cache();

		// Inject DB override fields when a record exists for this slug.
		if ( isset( self::$_overrides_cache[ $slug ] ) ) {
			$row = self::$_overrides_cache[ $slug ];

			// Top-level field — skip null to preserve Inherit semantics (FR-006).
			if ( null !== $row->site_allowed ) {
				$args['site_allowed'] = $row->site_allowed;
			}

			// Initialize meta array once if any nested override is present.
			$needs_meta = null !== $row->readonly || null !== $row->destructive || null !== $row->idempotent
				|| null !== $row->show_in_rest || null !== $row->show_in_mcp || null !== $row->mcp_type
				|| is_array( $row->mcp_servers );

			if ( $needs_meta && ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) ) {
				$args['meta'] = array();
			}

			// Annotations → $args['meta']['annotations']['<key>'].
			if ( null !== $row->readonly || null !== $row->destructive || null !== $row->idempotent ) {
				if ( ! isset( $args['meta']['annotations'] ) || ! is_array( $args['meta']['annotations'] ) ) {
					$args['meta']['annotations'] = array();
				}
				if ( null !== $row->readonly ) {
					$args['meta']['annotations']['readonly'] = $row->readonly;
				}
				if ( null !== $row->destructive ) {
					$args['meta']['annotations']['destructive'] = $row->destructive;
				}
				if ( null !== $row->idempotent ) {
					$args['meta']['annotations']['idempotent'] = $row->idempotent;
				}
			}

			// show_in_rest → $args['meta']['show_in_rest'].
			if ( null !== $row->show_in_rest ) {
				$args['meta']['show_in_rest'] = $row->show_in_rest;
			}

			// MCP block → $args['meta']['mcp']['<key>'] (plugin-specific; not WP core).
			if ( null !== $row->show_in_mcp || null !== $row->mcp_type || is_array( $row->mcp_servers ) ) {
				if ( ! isset( $args['meta']['mcp'] ) || ! is_array( $args['meta']['mcp'] ) ) {
					$args['meta']['mcp'] = array();
				}
				if ( null !== $row->show_in_mcp ) {
					$args['meta']['mcp']['public'] = $row->show_in_mcp;
				}
				if ( null !== $row->mcp_type ) {
					$args['meta']['mcp']['type'] = $row->mcp_type;
				}
				// mcp_servers: already decoded to array|null by AcrossAI_Sitewide_Row::__construct().
				if ( is_array( $row->mcp_servers ) ) {
					$args['meta']['mcp']['servers'] = $row->mcp_servers;
				}
			}
		}

		// permission_callback: inject runtime access-control enforcement when a rule is
		// saved for this slug from the Access Control tab (FR-009).
		$ac_manager = AcrossAI_Sitewide_Access_Control::instance()->get_manager();
		if ( null !== $ac_manager ) {
			$rule = $ac_manager->get_query()->get_rule( 'acrossai-abilities', $slug );
			if ( '' !== $rule['key'] ) {
				$args['permission_callback'] = static function () use ( $slug ) {
					$manager = AcrossAI_Sitewide_Access_Control::instance()->get_manager();
					if ( null === $manager ) {
						return true; // Fail-open: library unavailable.
					}
					return $manager->user_has_access( get_current_user_id(), 'acrossai-abilities', $slug );
				};
			}
		}

		return $args;
	}

	/**
	 * Action callback: unregister all abilities with site_allowed = false.
	 *
	 * Registered at wp_abilities_api_init P100001 on PATH B only — fires after all plugin
	 * registrations are complete so no subsequent registration can restore a blocked ability.
	 * Abilities with site_allowed = null (Inherit) are not touched.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function unregister_blocked_abilities(): void {
		self::load_overrides_cache();

		foreach ( self::$_overrides_cache as $slug => $row ) {
			if ( false === $row->site_allowed ) {
				wp_unregister_ability( $slug );
			}
		}
	}

	/**
	 * Filter callback: enforce the mcp_servers allowlist at MCP adapter registration time.
	 *
	 * Fires on PATH B only (registered inside boot()). Reads the servers allowlist from
	 * $args['meta']['mcp']['servers'] already injected into the ability object by
	 * inject_override_args() at wp_register_ability_args P10.
	 *
	 * When no servers override is present ($servers is null, empty, or not an array)
	 * $expose is returned unchanged — no restriction applies (Inherit semantics, FR-006).
	 *
	 * When a non-empty servers allowlist is present and $server_id is provided,
	 * returns false for any MCP server identifier not in the allowlist. When $server_id
	 * is empty (MCP adapter passes fewer than 3 args) the method degrades gracefully by
	 * returning $expose unchanged.
	 *
	 * @since  0.1.0
	 * @param  bool   $expose    Whether to expose this ability to MCP.
	 * @param  mixed  $ability   The WP Ability object being evaluated.
	 * @param  string $server_id The MCP server identifier requesting exposure.
	 * @return bool
	 */
	public static function filter_mcp_adapter_expose_ability( bool $expose, $ability, string $server_id = '' ): bool {
		// Warm the static cache for sibling callbacks sharing this request lifecycle.
		// This method reads overrides from the already-injected $ability object, not the cache directly.
		self::load_overrides_cache();

		$mcp_meta = ( is_object( $ability ) && method_exists( $ability, 'get_meta' ) )
			? $ability->get_meta( 'mcp' )
			: null;

		$servers = ( is_array( $mcp_meta ) && isset( $mcp_meta['servers'] ) )
			? $mcp_meta['servers']
			: null;

		// No servers override — pass through unchanged.
		if ( ! is_array( $servers ) || empty( $servers ) ) {
			return $expose;
		}

		// Server ID not available — degrade gracefully rather than block everything.
		if ( '' === $server_id ) {
			return $expose;
		}

		// Non-empty allowlist: block servers not in the list; preserve $expose for allowed ones.
		if ( ! in_array( $server_id, $servers, true ) ) {
			return false;
		}

		return $expose;
	}

	/**
	 * Clear the in-memory cache and the transient.
	 *
	 * Called directly from REST controllers after delete/reset operations that do not
	 * fire acrossai_abilities_sitewide_after_save (W-001 resolution). Also wired as the
	 * bust_cache_hook() instance wrapper target for the Loader action.
	 *
	 * @internal public by necessity — hook callback + cross-controller direct call.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function bust_cache(): void {
		delete_transient( 'acrossai_ability_overrides_cache' );
		self::$_overrides_cache = null;
	}
}
