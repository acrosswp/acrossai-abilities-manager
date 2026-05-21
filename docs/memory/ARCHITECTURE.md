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
- **`includes/Modules/Abilities/Database/`**: BerlinDB table, query, schema,
  and row classes for the unified `wp_acrossai_abilities` table (24 columns).
  `AcrossAI_Abilities_Row::__construct()` decodes `callback_config`, `input_schema`,
  `output_schema`, and `mcp_servers` JSON fields to `array|null`.
- **`includes/Modules/Sitewide/Database/`**: BerlinDB Sitewide classes — thin
  wrappers that now point at `wp_acrossai_abilities` (same table as Abilities module).
  `source != 'db'` rows are the sitewide override rows managed by this module.
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

## PATTERN-STAGE-NAMING

In modules with multi-stage data transformations (raw → processed → formatted → stored), use distinct variable names for each transformation stage. This improves code clarity and prevents accidental overwrites.

**Pattern**:
```php
// Stage 1: Raw extraction
$output_value = $result;

// Status detection based on raw value
if ( is_wp_error( $result ) ) {
    $output_value = $result->get_error_message();
}

// Stage 2: Formatting/truncation
$formatted_output = AcrossAI_Logger_Formatter::format_value( $output_value );

// Stage 3: Storage
$entry['output'] = $formatted_output;
```

**Why this matters**:
- Reader immediately sees which stage each variable represents
- Prevents conditional overwrites from affecting later logic
- Self-documents the transformation pipeline
- Easier to debug (set breakpoints at each stage)

**When to Apply**: Any class processing data through 3+ stages (raw, validated, transformed, formatted, stored).

**Reference**: `AcrossAI_Ability_Logger::finish_pending_entry()` (feature 006, lines 195–220), where `$output_value` (raw) vs. `$formatted_output` (stage 2) enables clear status detection logic without confusion.

**Evidence**:
Feature 006 (2026-05-20): Refactored logger to use `$output_value` (raw), `$formatted_output` (formatted). Code review confirmed improved readability. PHPCS 0 errors.

---

## PATTERN-FEATURE-ASSET-SEPARATION

When a feature module has its own admin UI, separate its assets from the main manager assets. Use feature-specific asset handles instead of generic names to prevent coupling and enable independent rebuild/versioning.

**Pattern**:
```
build/
  css/
    index.css              # main manager assets
    logger.css             # feature-specific: Feature 006
  js/
    index.js               # main manager assets
    logger.js              # feature-specific: Feature 006
```

**In Admin/Main.php**:
```php
public function enqueue_styles( string $hook_suffix ) {
    $on_abilities = false !== strpos( $hook_suffix, 'acrossai-abilities-manager' );
    $on_logs      = false !== strpos( $hook_suffix, 'acrossai-abilities-logs' );
    
    if ( ! $on_abilities && ! $on_logs ) {
        return;
    }
    
    // Main assets
    if ( $on_abilities ) {
        wp_enqueue_style( 'acrossai-abilities-manager', ... );
    }
    
    // Feature-specific assets
    if ( $on_logs && $this->logger_asset_file ) {
        wp_enqueue_style( 'acrossai-abilities-logger', ... );
    }
}
```

**Why this matters**:
- Each feature can be built/deployed independently
- No cross-feature asset conflicts
- Clear ownership of which CSS/JS belongs to which feature
- `webpack.config.js` can define separate entry points

**When to Apply**: When a feature module adds new admin pages or tabs with dedicated UI.

**Reference**: Feature 006 logger (2026-05-20): Assets named `logger.css`, `logger.js`, `logger.asset.php` (not `index.*`). Admin/Main.php extended hook suffix detection to load logger assets only on `acrossai-abilities-logs` page.

**Evidence**:
Old pattern: `build/js/index.css` + `build/js/index.js` used for all admin UI (coupled).
New pattern: `build/css/logger.css` + `build/js/logger.js` isolated to logger tab (decoupled).
Admin/Main.php enqueue_scripts() now checks both `acrossai-abilities-manager` and `acrossai-abilities-logs` hook suffixes before enqueueing.


## Keep Here
- stable system boundaries (PATH A/B, Manager namespace, BerlinDB layer)
- ownership lines between modules or services
- integration constraints that affect many features (ARCH-ADV-001, W-001)

## Never Store Here
- step-by-step implementation plans
- one-off feature details
- stale diagrams without current boundaries

Update the review date when boundaries, ownership, or integrations materially change.

## AC-FILE-HEADER-PATTERN

All PHP files must follow a standardized file header pattern. This ensures consistency across the codebase and enables automated tooling.

**Exact pattern**:
```php
<?php
/**
 * Brief description (one line).
 *
 * Longer description (optional, 1-2 sentences).
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Logger
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Logger;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
```

**Key rules**:
- `@package`: Always `AcrossAI_Abilities_Manager` (underscores, not backslashes)
- `@subpackage`: Full PSR-4 path starting with `AcrossAI_Abilities_Manager`, e.g., `AcrossAI_Abilities_Manager/includes/Modules/Logger`
- `@since`: Always `0.1.0` (not `1.0.0`; 0.1.0 represents initial plugin release)
- ABSPATH check: Use `defined( 'ABSPATH' ) || exit;` format (modern guard, single line with `||`)
- Namespace: Matches @subpackage with backslashes and follows underscore convention

**Reference file**:
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` demonstrates the correct pattern.

**Evidence**:
Feature 006 (2026-05-19): Fixed file headers in 3 logger files to match this pattern. All changed from old-style `if ( ! defined( 'ABSPATH' ) ) { exit; }` to modern guard. All changed from `@package AcrossAI\Abilities\Logger` to `@package AcrossAI_Abilities_Manager`. PHPCS 0 errors, PHPStan L8 exit 0.

**Why this is durable**:
New developers copy-paste headers from existing files. If all files follow one pattern, copy-paste stays consistent. If files vary, inconsistency spreads. This constraint prevents drift.

---

## 2026-05-20 — Enable dependency upgrades without plugin code changes (ARCH-ZERO-CODE-DEPENDENCY-UPGRADE)

**Pattern**: Architecture that allows dependency upgrades (composer constraint changes only) without modifying plugin code

**Conditions** (all required):
1. **Singleton-based service integration** — Services are accessed via `::instance()` static factory, not direct instantiation
2. **Interface-based dependency injection** — Integration points use service locators or abstract interfaces, not concrete class dependencies
3. **No breaking API changes** — Pre-validated via pre-update audit (changelog, API signature review, security scan)
4. **Clean separation of concerns** — Library is isolated from plugin hooks, Main.php, and core architecture

**Implementation Pattern**:
```php
// ✅ Singleton + Service Locator (supports zero-code upgrades)
class AcrossAI_Sitewide_Access_Control {
    private static $_instance = null;
    
    public static function instance() {
        if ( null === self::$_instance ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    private function __construct() {}
    
    public function get_manager() {
        // Service locator pattern — library is encapsulated
        $ac = new wpboilerplate\AccessControlLibrary();
        return $ac->get_manager();
    }
}

// Usage: always via instance()
$ac = AcrossAI_Sitewide_Access_Control::instance();
$manager = $ac->get_manager(); // Works regardless of library version
```

**Benefit**: Allows upgrades to ^X.Y constraints with zero plugin code changes; only composer.json and composer.lock are modified.

**Validation**: All Phase 1 tests must pass without plugin code changes. If code changes are required, the upgrade is NOT zero-code; refactor architecture or escalate as Feature X-N (separate task).

**Evidence**:
Feature 007 (2026-05-20): Upgraded wpb-access-control dev-main → ^1.0 with:
- **0 plugin files modified** (only composer.json, composer.lock)
- **0 code changes to AcrossAI_Sitewide_Access_Control** (pre-existing singleton pattern worked as-is)
- **100% Phase 1 test pass rate** (6/6 tests, no code adaptation needed)
- **All security constraints validated** (DEC-PERM-CB, SEC-04, SEC-03, DEC-FAIL-OPEN-NOTICE)

**Counter-Example** (do NOT do this):
```php
// ❌ Direct instantiation (breaks with API changes)
public function get_manager() {
    return new wpboilerplate\AccessControlManager(); // If constructor signature changes, breaks
}

// ❌ Static method coupling (hard to version)
$manager = AccessControlManager::get_instance(); // Hardcoded class name

// ❌ Concrete class properties (prevents upgrades)
private AccessControlManager $manager; // If interface changes, code breaks
```

**Where to Look Next**:
- `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` (singleton + service locator pattern)
- `specs/007-upgrade-access-control/` (zero-code upgrade example)
- `.specify/memory/CONSTITUTION.md` (singleton pattern requirement)

**Maintenance Rule**:
When adding new library integrations, architect using singleton + service locator pattern to enable future zero-code upgrades. Document public API contracts in code comments. Test integration points with multiple library versions (if available) before locking to a specific constraint.
