# Architecture

Last reviewed: 2026-05-17

## System Overview

AcrossAI Abilities Manager is a WordPress plugin that adds a management UI
and runtime enforcement layer on top of the WordPress Abilities API
(WP 6.9+). Admins configure per-ability overrides in the Manager UI; those
overrides are applied at request boot time on all non-Manager requests.

The plugin follows the WordPress Plugin Boilerplate PSR-4 layout with a
central Loader/Main.php hook surface, BerlinDB for override persistence,
and a REST-API-first admin UI backed by @wordpress/dataviews.

## Major Components

- **`includes/Main.php`**: Sole hook-registration surface. All Loader
  wiring lives here. No other class calls `add_action` / `add_filter`
  through the Loader (exception: ARCH-ADV-001 in boot()).
- **`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor`**:
  Static runtime processor. Bridges DB overrides into WP ability
  registrations at `plugins_loaded P20` via PATH A/B branching. Wires
  `wp_register_ability_args`, `wp_abilities_api_init`, and
  `mcp_adapter_expose_ability` directly in `boot()` (ARCH-ADV-001 deviation).
- **`includes/Modules/Sitewide/Database/`**: BerlinDB table, query, schema,
  and row classes for override persistence. `AcrossAI_Sitewide_Row::__construct()`
  decodes `mcp_servers` JSON to `array|null`.
- **`includes/Modules/Sitewide/Rest/`**: REST sub-controller split:
  `AcrossAI_Sitewide_Abilities_Controller` (read), `AcrossAI_Sitewide_Override_Controller`
  (write), `AcrossAI_Sitewide_Bulk_Controller` (bulk), `AcrossAI_Sitewide_Mcp_Controller`
  (MCP server list). Orchestrated by `AcrossAI_Sitewide_Rest_Controller`.
- **`includes/Modules/Sitewide/AcrossAI_Ability_Merger`**: Merges WP registry
  values with DB overrides for the Manager UI `_registry` / `_override` diff view.
- **`includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control`**: Wraps
  `wpb-access-control` library for per-ability rule storage and permission
  callback injection.
- **`includes/Utilities/`**: Shared sanitization (`AcrossAI_Sanitizer`),
  field utilities.

## Boundaries

- **Manager REST namespace** (`acrossai-abilities-manager/v1`): PATH A —
  override injection is skipped entirely. All Manager UI reads see pure
  WP registry values, never merged override values.
- **All other requests** (PATH B): override injection fires at boot; blocked
  abilities are unregistered after all registrations complete.
- **BerlinDB layer**: `AcrossAI_Sitewide_Query` is the only entry point for
  DB reads/writes. No direct `$wpdb` SQL in module or REST classes.
- **Hook surface**: Only `includes/Main.php` wires hooks through the Loader.
  `boot()` in the processor is the only approved exception (ARCH-ADV-001).

## Integrations

- **WordPress Abilities API** (WP 6.9+): `wp_register_ability_args`,
  `wp_abilities_api_init`, `wp_unregister_ability`.
- **MCP Adapter** (external plugin): `mcp_adapter_expose_ability` filter
  for per-server allowlist enforcement (PATH B only, `accepted_args = 3`).
- **`wpb-access-control` library**: Per-ability permission rules. Injected
  at `$args['permission_callback']` time. Fails open when library absent.
- **`wpb-mcp-servers-list` library**: Collects registered MCP server IDs for
  the admin UI dropdown. Collected at `rest_api_init P20`.
- **Action Scheduler**: Not currently used; prefer for any future async jobs.

## Risks / Complexity Hotspots

- **Override cache TTL (12h transient)**: Stale cache after delete/reset is
  mitigated by direct `bust_cache()` calls in Override + Bulk controllers.
  Any new write path that does not fire `acrossai_abilities_sitewide_after_save`
  MUST call `bust_cache()` directly (W-001 pattern — see DECISIONS.md).
- **PATH A detection is a performance hint, not a security gate**: If the
  Manager REST namespace constant is misconfigured, override injection may
  fire on Manager requests. REST routes remain protected by `check_permission()`
  independently of PATH detection.
- **`mcp_adapter_expose_ability` accepted_args = 3**: Fail-open when
  `$server_id` is empty (MCP adapter passes fewer than 3 args). Update
  `accepted_args` if MCP adapter contract changes.
- **PHPUnit blocked**: No WP test bootstrap in project. All PHPUnit test
  files exist but cannot be run until `phpunit.xml.dist` + WP bootstrap
  shim are added (T014, pre-existing gap).


## AC-QUERY-LAYER-FILTERING

All list-endpoint filtering (search, sort, pagination, field filtering) MUST occur in the query builder layer (`AcrossAI_Ability_Registry_Query`), not in the REST controller. Pagination headers (`X-WP-Total`, `X-WP-TotalPages`) MUST reflect the filtered results.

**Rationale**: Query layer is the single source of truth for "what items exist in the result set". Filtering at query layer ensures pagination counts are accurate, search/sort/filter operations treat filtered items as non-existent, and REST controller doesn't duplicate filtering logic.

**Pattern**: In query builder loop, before adding to results: `if ( condition ) { continue; }` to skip filtered items and prevent them from being added to result set.

**Example**: `AcrossAI_Ability_Registry_Query::query()` excludes protected abilities at line 67–70 before appending to `$results[]`.

**Reference**: Feature 005 (commit `62d25ad`), plan.md FR-001, FR-003.

---

## PATTERN-SINGLE-SOURCE-UTILITY

When a logical concept is used in multiple places (query layer + REST controller), extract it to a single utility class with public static methods. Call the utility from both locations instead of duplicating logic.

**Benefits**:
- DRY principle enforced (Constitution §VI)
- Single edit point (fix once, applies everywhere)
- Easier to test in isolation
- Self-documenting (utility name = concept name)

**Structure**:
```php
// includes/Utilities/AcrossAI_SingleSourceTruth.php
class AcrossAI_SingleSourceTruth {
    public static function get_items(): array { /* return list */ }
    public static function is_member( string $id ): bool {
        return in_array( $id, self::get_items(), true );
    }
}

// Usage location 1: Query layer
if ( AcrossAI_SingleSourceTruth::is_member( $item_id ) ) { ... }

// Usage location 2: REST controller
if ( AcrossAI_SingleSourceTruth::is_member( $slug ) ) { ... }
```

**When to Apply**: Logic used in 2+ locations, small enough for `Utilities/`, stateless (only static methods).

**Reference**: `AcrossAI_Protected_Abilities` (feature 005, commit `62d25ad`), called from query layer + REST controller.

## Keep Here
- stable system boundaries (PATH A/B, Manager namespace, BerlinDB layer)
- ownership lines between modules or services
- integration constraints that affect many features (ARCH-ADV-001, W-001)

## Never Store Here
- step-by-step implementation plans
- one-off feature details
- stale diagrams without current boundaries

Update the review date when boundaries, ownership, or integrations materially change.
