<?php
/**
 * Runtime override processor for Abilities module override management.
 *
 * Bridges DB-stored ability overrides (managed via the Abilities Manager UI) into live
 * WordPress ability registrations at request boot time.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Abilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Abilities;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Row;

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
 * HOOK WIRING PATTERN (ARCH-ADV-001):
 * Only two hooks go through Main.php / Loader — plugins_loaded P20 (boot_hook) and
 * acrossai_abilities_after_create/update/delete (bust_cache_hook). The Loader always registers hooks
 * unconditionally, so it cannot express the PATH A / PATH B split. All downstream hooks
 * (wp_register_ability_args, wp_abilities_api_init, mcp_adapter_tool_call_result,
 * mcp_adapter_pre_tool_call) are registered conditionally inside boot() only when
 * is_manager_rest_request() returns false. This is an accepted deviation from the Boot Flow Rule.
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
	protected static $instance = null;

	/**
	 * In-memory cache: slug → AcrossAI_Abilities_Row. Null means not yet loaded.
	 *
	 * @var AcrossAI_Abilities_Row[]|null
	 */
	protected static $overrides_cache = null;


	/**
	 * Whether is_manager_rest_request() has already been evaluated this request.
	 *
	 * @var bool
	 */
	protected static $checked = false;

	/**
	 * Memoized result of is_manager_rest_request().
	 *
	 * @var bool
	 */
	protected static $is_manager = false;

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
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
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
	//
	// The Loader in Main.php passes array( $component, $callback ) to WordPress where
	// $component must be an object (PHPStan L8 requires this). These two instance methods
	// are the only hooks wired through Main.php/Loader — they delegate immediately to their
	// static counterparts. All other hooks are registered conditionally inside boot() because
	// they must be absent on Manager REST requests (PATH A). See ARCH-ADV-001 in plan.md.

	/**
	 * Loader-compatible wrapper for boot(). Wired at plugins_loaded P20 via Main.php.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function boot_hook(): void {
		self::boot();
	}

	/**
	 * Loader-compatible wrapper for bust_cache(). Wired at acrossai_abilities_after_create/update/delete via Main.php.
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
	 * On PATH A (Manager REST requests) returns immediately without registering any hooks —
	 * the Manager UI always sees pure WP registry values for the _registry layer (FR-003).
	 * On PATH B registers all downstream hooks directly via add_filter()/add_action().
	 *
	 * WHY HOOKS ARE REGISTERED HERE (ARCH-ADV-001):
	 * The Loader in Main.php always registers hooks unconditionally. Because these four hooks
	 * must be completely absent on PATH A (Manager REST), they cannot go through the Loader —
	 * conditional wiring cannot be expressed there. boot() is the only place where the
	 * PATH A / PATH B decision has been made and acted on.
	 *
	 * Hooks registered here (PATH B only):
	 *   wp_register_ability_args P100000   — inject_override_args()
	 *   wp_abilities_api_init    P100001   — unregister_blocked_abilities()
	 *   mcp_adapter_tool_call_result P10   — filter_discover_abilities_result()
	 *   mcp_adapter_pre_tool_call    P10   — block_execute_ability_by_server()
	 *   mcp_adapter_init         P20       — inject_mcp_tools()
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function boot(): void {
		// FR-003 / SEC-PLAN-001: PATH A — Manager REST skips override injection entirely.
		if ( self::is_manager_rest_request() ) {
			return;
		}

		// PATH B — all downstream hooks registered here, not in Main.php, because they must
		// be skipped entirely on PATH A. See ARCH-ADV-001 in plan.md and the docblock above.

		// Inject non-null DB override values into each ability's args during registration.
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'inject_override_args' ), 100000, 2 );

		// Unregister abilities with site_allowed = false after all plugin registrations complete.
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'unregister_blocked_abilities' ), 100001 );

		// T016 — enforce mcp_servers allowlist via real mcp-adapter filter hooks.
		// mcp_adapter_expose_ability does NOT exist in mcp-adapter — these are the real hooks.

		// Remove abilities from DiscoverAbilitiesAbility result when the current server is not
		// in the ability's mcp_servers allowlist.
		add_filter( 'mcp_adapter_tool_call_result', array( __CLASS__, 'filter_discover_abilities_result' ), 10, 5 );

		// Block ExecuteAbilityAbility before it runs when the current server is not allowed.
		// Returning WP_Error from mcp_adapter_pre_tool_call short-circuits execution.
		add_filter( 'mcp_adapter_pre_tool_call', array( __CLASS__, 'block_execute_ability_by_server' ), 10, 4 );

		// Register opted-in ability slugs into every MCP server's callable tool registry.
		// Runs at mcp_adapter_init P20, after DefaultServerFactory (P10) and
		// acrossai-mcp-manager database servers (P11) are both created.
		// Uses Reflection to reach McpServer::$component_registry (private) because
		// mcp_adapter_server_config does not exist in the installed mcp-adapter version.
		add_action( 'mcp_adapter_init', array( __CLASS__, 'inject_mcp_tools' ), 20 );
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
		if ( self::$checked ) {
			return self::$is_manager;
		}

		self::$checked = true;

		// Non-HTTP contexts are never Manager REST requests.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			self::$is_manager = false;
			return false;
		}

		if ( wp_doing_cron() ) {
			self::$is_manager = false;
			return false;
		}

		if ( wp_doing_ajax() ) {
			self::$is_manager = false;
			return false;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '';

		$namespace        = (string) apply_filters( 'acrossai_manager_rest_namespace', ACROSSAI_MANAGER_REST_NAMESPACE );
		self::$is_manager = ( '' !== $uri ) &&
			false !== strpos( $uri, '/' . rest_get_url_prefix() . '/' . $namespace . '/' );

		return self::$is_manager;
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
		if ( null !== self::$overrides_cache ) {
			return;
		}

		$cached = get_transient( 'acrossai_ability_overrides_cache' );

		// SEC-PLAN-003: Validate transient output before use — treat non-array as cache miss
		// (guards against corrupted object-cache entries or DB row corruption).
		if ( ! is_array( $cached ) ) {
			$cached = null;
		}

		if ( null === $cached ) {
			$cached = AcrossAI_Abilities_Query::instance()->get_all_overrides();
			set_transient( 'acrossai_ability_overrides_cache', $cached, 12 * HOUR_IN_SECONDS );
		}

		self::$overrides_cache = $cached;
	}

	/**
	 * Filter callback: inject non-null DB override values into each ability's registration args.
	 *
	 * Registered at wp_register_ability_args P10 on PATH B only. Null DB values are skipped —
	 * null means "Inherit" and the registration-time default is preserved (FR-006).
	 *
	 * Field path map (FR-009):
	 *   site_allowed              → $args['site_allowed']  (top-level WP Abilities API field)
	 *   label                     → $args['label']           (top-level WP Abilities API field)
	 *   description               → $args['description']     (top-level WP Abilities API field)
	 *   category                  → $args['category']        (top-level WP Abilities API field)
	 *   readonly/destructive/
	 *     idempotent              → $args['meta']['annotations']['<key>']
	 *   show_in_rest              → $args['meta']['show_in_rest']
	 *   show_in_mcp               → $args['meta']['mcp']['public']   (plugin-specific)
	 *   mcp_type                  → $args['meta']['mcp']['type']     (plugin-specific)
	 *   mcp_servers               → $args['meta']['mcp']['servers']  (plugin-specific; already
	 *                               decoded to array|null by AcrossAI_Abilities_Row::__construct())
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
		if ( isset( self::$overrides_cache[ $slug ] ) ) {
			$row = self::$overrides_cache[ $slug ];

			// Top-level fields — skip null/empty to preserve Inherit semantics (FR-006).
			if ( null !== $row->site_allowed ) {
				$args['site_allowed'] = $row->site_allowed;
			}
			if ( null !== $row->label && '' !== $row->label ) {
				$args['label'] = $row->label;
			}
			if ( null !== $row->description && '' !== $row->description ) {
				$args['description'] = $row->description;
			}
			if ( null !== $row->category && '' !== $row->category ) {
				$args['category'] = $row->category;
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

				/*
				 * mcp_servers: AcrossAI_Abilities_Row::__construct() already decodes
				 * the JSON string from DB to array|null — no json_decode() needed.
				 * We inject the array into $args['meta']['mcp']['servers'] so the value
				 * travels on the WP_Ability object after registration.
				 *
				 * null  → not injected → key absent in meta → visible to all servers (inherit).
				 * []    → injected as empty array → visible to no servers.
				 * [...] → injected as allowlist → membership check per server ID.
				 *
				 * Enforcement: filter_discover_abilities_result() (mcp_adapter_tool_call_result
				 * P10) reads this value from the WP_Ability object at request time and removes
				 * abilities whose allowlist does not include the current server ID.
				 */
				if ( is_array( $row->mcp_servers ) ) {
					$args['meta']['mcp']['servers'] = $row->mcp_servers;
				}
			}
		}

		// permission_callback: inject runtime AC enforcement when a rule exists for this slug (FR-009).
		$callback = self::build_permission_callback( $slug );
		if ( null !== $callback ) {
			$args['permission_callback'] = $callback;
		}

		return $args;
	}

	/**
	 * Build a permission_callback closure for the given ability slug when an AC rule exists.
	 *
	 * Returns null when no rule is configured — the ability keeps its registration-time callback.
	 * Returns a typed bool closure when a rule is found.
	 *
	 * SECURITY: The closure is fail-open — returns true when the AC library is unavailable at
	 * call time. This is intentional (FR-009): if the library is absent the site has no AC
	 * configuration to enforce. Changing to fail-closed requires an explicit product decision.
	 *
	 * @since  0.1.0
	 * @param  string $slug Ability slug.
	 * @return callable|null Closure returning bool, or null if no rule is configured.
	 */
	private static function build_permission_callback( string $slug ): ?callable {
		$manager = AcrossAI_Abilities_Access_Control::instance()->get_manager();
		if ( null === $manager ) {
			return null;
		}

		$rule = $manager->get_query()->get_rule( 'acrossai-abilities', $slug );
		if ( '' === $rule['key'] ) {
			return null; // No rule configured — no callback needed.
		}

		return static function () use ( $slug ): bool {
			return AcrossAI_Ability_Override_Processor::user_has_ability_access( $slug, \get_current_user_id() );
		};
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

		foreach ( self::$overrides_cache as $slug => $row ) {
			if ( \wp_has_ability( $slug ) && false === $row->site_allowed ) {
				\wp_unregister_ability( $slug );
			}
		}
	}

	// -------------------------------------------------------------------------
	// T016 — mcp_servers enforcement
	// -------------------------------------------------------------------------

	/**
	 * Filter callback: remove abilities from the discover-abilities result that are not
	 * allowed on the current MCP server.
	 *
	 * Hooked at mcp_adapter_tool_call_result P10 (PATH B only). Exits immediately for
	 * every tool except mcp-adapter/discover-abilities.
	 *
	 * @since  0.1.0
	 * @param  mixed  $result    Raw tool execution result.
	 * @param  array  $args      Tool arguments.
	 * @param  string $tool_name Sanitized MCP tool name.
	 * @param  mixed  $mcp_tool  McpTool instance.
	 * @param  mixed  $server    McpServer instance.
	 * @return mixed Filtered result, or original result if not applicable.
	 */
	public static function filter_discover_abilities_result( $result, array $args, string $tool_name, $mcp_tool, $server ) {
		if ( ! is_array( $result ) || ! isset( $result['abilities'] ) || ! is_array( $result['abilities'] ) ) {
			return $result;
		}

		if ( ! self::is_ability_tool( $mcp_tool, 'mcp-adapter/discover-abilities' ) ) {
			return $result;
		}

		if ( ! method_exists( $server, 'get_server_id' ) ) {
			return $result;
		}
		$server_id = $server->get_server_id();

		$filtered = array();
		foreach ( $result['abilities'] as $ability_data ) {
			$name = $ability_data['name'] ?? null;
			if ( ! is_string( $name ) || '' === $name ) {
				$filtered[] = $ability_data;
				continue;
			}
			if ( self::is_ability_allowed_on_server( $name, $server_id ) ) {
				$filtered[] = $ability_data;
			}
		}

		$result['abilities'] = $filtered;
		return $result;
	}

	/**
	 * Filter callback: block ExecuteAbilityAbility when the target ability is not allowed
	 * on the current MCP server.
	 *
	 * Hooked at mcp_adapter_pre_tool_call P10 (PATH B only). Returning WP_Error from this
	 * filter short-circuits execution and returns an error result to the MCP client.
	 * Exits immediately for every tool except mcp-adapter/execute-ability.
	 *
	 * @since  0.1.0
	 * @param  mixed  $args      Tool arguments (WP_Error if a prior filter already blocked).
	 * @param  string $tool_name Sanitized MCP tool name.
	 * @param  mixed  $mcp_tool  McpTool instance.
	 * @param  mixed  $server    McpServer instance.
	 * @return mixed Original $args, or WP_Error to block execution.
	 */
	public static function block_execute_ability_by_server( $args, string $tool_name, $mcp_tool, $server ) {
		// A prior filter may have already returned WP_Error — pass it through.
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		if ( ! self::is_ability_tool( $mcp_tool, 'mcp-adapter/execute-ability' ) ) {
			return $args;
		}

		if ( ! method_exists( $server, 'get_server_id' ) ) {
			return $args;
		}

		$ability_name = is_array( $args ) ? ( $args['ability_name'] ?? '' ) : '';
		if ( '' === $ability_name ) {
			return $args; // Missing ability_name — let execute-ability's own validation handle it.
		}

		$server_id = $server->get_server_id();

		if ( ! self::is_ability_allowed_on_server( $ability_name, $server_id ) ) {
			return new \WP_Error(
				'mcp_server_not_allowed',
				sprintf(
					/* translators: %s: ability name */
					__( "Ability '%s' is not available on this server.", 'acrossai-abilities-manager' ),
					$ability_name
				)
			);
		}

		return $args;
	}

	// -------------------------------------------------------------------------
	// T016 — Shared helpers
	// -------------------------------------------------------------------------

	/**
	 * Check whether a given ability is allowed on the specified MCP server.
	 *
	 * Reads meta.mcp.servers from the registered WP_Ability object — the value is already
	 * injected by inject_override_args() at wp_register_ability_args P100000.
	 *
	 * Allowlist semantics (FR-006):
	 *   Key absent / null  → no DB override → allowed on all servers (inherit).
	 *   []                 → empty allowlist → blocked on all servers.
	 *   [ 'id', ... ]      → allowed only when $server_id is in the list.
	 *
	 * Returns true when the ability is not registered (allows the calling context to handle
	 * the "not found" case independently).
	 *
	 * @since  0.1.0
	 * @param  string $ability_name WordPress ability slug.
	 * @param  string $server_id    MCP server identifier.
	 * @return bool True if allowed, false if blocked.
	 */
	private static function is_ability_allowed_on_server( string $ability_name, string $server_id ): bool {
		$ability = \wp_get_ability( $ability_name );
		if ( ! $ability ) {
			return true; // Not found — let the calling context handle it.
		}

		$ability_meta = $ability->get_meta();
		$mcp_meta     = ( isset( $ability_meta['mcp'] ) && is_array( $ability_meta['mcp'] ) )
			? $ability_meta['mcp']
			: array();

		if ( ! array_key_exists( 'servers', $mcp_meta ) || null === $mcp_meta['servers'] ) {
			return true; // No restriction — all servers (inherit).
		}

		$allowlist = $mcp_meta['servers'];

		if ( ! is_array( $allowlist ) || empty( $allowlist ) ) {
			return false; // Empty allowlist — no servers allowed.
		}

		return in_array( $server_id, $allowlist, true );
	}

	/**
	 * Check whether an McpTool wraps a specific WordPress ability.
	 *
	 * Uses get_adapter_meta()['ability'] which holds the original WordPress ability name,
	 * robust against MCP name sanitization changes.
	 *
	 * @since  0.1.0
	 * @param  mixed  $mcp_tool     McpTool instance (typed mixed for safety).
	 * @param  string $ability_name Expected WordPress ability name.
	 * @return bool
	 */
	private static function is_ability_tool( $mcp_tool, string $ability_name ): bool {
		if ( ! method_exists( $mcp_tool, 'get_adapter_meta' ) ) {
			return false;
		}
		$adapter_meta = $mcp_tool->get_adapter_meta();
		return ( $adapter_meta['ability'] ?? '' ) === $ability_name;
	}

	// -------------------------------------------------------------------------
	// MCP tools pass-through
	// -------------------------------------------------------------------------

	/**
	 * Action callback: register opted-in ability slugs into every MCP server's callable registry.
	 *
	 * Registered at mcp_adapter_init P20 (PATH B only, ARCH-ADV-001). Fires after all servers
	 * are created (DefaultServerFactory P10, acrossai-mcp-manager database servers P11).
	 * Uses Reflection to reach the private McpServer::$component_registry and calls
	 * register_tools() on it — this is necessary because the installed mcp-adapter version
	 * does not expose mcp_adapter_server_config. Adding slugs here ensures both tools/list
	 * and tools/call work; mcp_adapter_tools_list only affects the display list.
	 *
	 * Three checks per ability, applied in order inside the per-server loop:
	 *   1. pass_as_tool = 1   — pre-filtered before the server loop (FR-004 early-exit).
	 *   2. mcp_servers        — null = all servers; [] = deny; [...] = allowlist (strict).
	 *   3. user access (AC)   — uses user_has_ability_access(); fail-open when absent (FR-011).
	 *
	 * Timing note (SEC-003): get_current_user_id() is called at action time (mcp_adapter_init P20).
	 * The MCP adapter initializes inside a REST request after wp_set_current_user() runs, so the
	 * user ID is reliable. If the initialization context changes (e.g. CLI or cron), user_id = 0
	 * and any AC-ruled ability will be denied for that context (correct, fail-safe behavior).
	 *
	 * @since  0.1.0
	 * @param  mixed $adapter McpAdapter singleton instance.
	 * @return void
	 */
	public static function inject_mcp_tools( $adapter ): void {
		if ( ! method_exists( $adapter, 'get_servers' ) ) {
			return;
		}

		self::load_overrides_cache();

		// Check 1: collect only pass_as_tool = 1 rows (FR-004 early-exit).
		// Check 1: collect only pass_as_tool = 1 rows that are tool-typed (FR-004 early-exit).
		// resource/prompt types are registered by DefaultServerFactory via their own paths;
		// injecting them into the tool registry conflicts with their mcp.type contract.
		$non_tool_types = array( 'resource', 'prompt' );
		$pass_rows      = array();
		foreach ( self::$overrides_cache as $slug => $row ) {
			if ( true === $row->pass_as_tool && ! in_array( $row->mcp_type, $non_tool_types, true ) ) {
				$pass_rows[ $slug ] = $row;
			}
		}

		if ( empty( $pass_rows ) ) {
			return;
		}

		$servers = $adapter->get_servers();
		if ( empty( $servers ) ) {
			return;
		}

		$user_id = \get_current_user_id();

		foreach ( $servers as $server ) {
			if ( ! method_exists( $server, 'get_server_id' ) ) {
				continue;
			}

			$server_id   = $server->get_server_id();
			$extra_slugs = array();

			foreach ( $pass_rows as $slug => $row ) {
				// Check 2: mcp_servers allowlist — mirrors is_ability_allowed_on_server().
				if ( is_array( $row->mcp_servers ) ) {
					if ( empty( $row->mcp_servers ) ) {
						continue; // Explicit deny.
					}
					if ( ! in_array( $server_id, $row->mcp_servers, true ) ) {
						continue; // Not in allowlist.
					}
				}

				// Check 3: per-user access via AC rules — fail-open (FR-011).
				if ( ! self::user_has_ability_access( $slug, $user_id ) ) {
					continue; // Current user denied.
				}

				$extra_slugs[] = $slug;
			}

			if ( empty( $extra_slugs ) ) {
				continue;
			}

			// Access McpServer::$component_registry via Reflection (private field).
			// Required because mcp_adapter_server_config does not exist in installed version.
			try {
				$reflection = new \ReflectionClass( $server );
				if ( ! $reflection->hasProperty( 'component_registry' ) ) {
					continue;
				}
				$prop = $reflection->getProperty( 'component_registry' );
				$prop->setAccessible( true );
				$registry = $prop->getValue( $server );
				if ( $registry && method_exists( $registry, 'register_tools' ) ) {
					$registry->register_tools( $extra_slugs );
				}
			} catch ( \ReflectionException $e ) {
				// Silently skip — non-fatal, injection just won't happen for this server.
				continue;
			}
		}
	}

	/**
	 * Check whether the given user has access to an ability per AC rules.
	 *
	 * Fail-open: returns true when the AC library is absent or no rule is configured —
	 * mirrors build_permission_callback() semantics (FR-009, FR-011).
	 *
	 * @since  0.1.0
	 * @param  string $slug    Ability slug.
	 * @param  int    $user_id WordPress user ID.
	 * @return bool True when access is granted or no rule applies.
	 */
	private static function user_has_ability_access( string $slug, int $user_id ): bool {
		$manager = AcrossAI_Abilities_Access_Control::instance()->get_manager();
		if ( null === $manager ) {
			return true; // Fail-open: no AC library.
		}
		$rule = $manager->get_query()->get_rule( 'acrossai-abilities', $slug );
		if ( '' === $rule['key'] ) {
			return true; // No rule configured — allow.
		}
		return $manager->user_has_access( $user_id, 'acrossai-abilities', $slug );
	}

	/**
	 * Clear the in-memory cache and the transient.
	 *
	 * Called directly from REST controllers after delete/reset operations that do not
	 * fire Abilities lifecycle hooks (acrossai_abilities_after_create/update/delete, W-001 resolution). Also wired as the
	 * bust_cache_hook() instance wrapper target for the Loader action.
	 *
	 * @internal public by necessity — hook callback + cross-controller direct call.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function bust_cache(): void {
		delete_transient( 'acrossai_ability_overrides_cache' );
		self::$overrides_cache = null;
	}
}
