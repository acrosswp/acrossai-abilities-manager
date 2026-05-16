# Implementation Plan: Ability Override Processor

**Branch**: `004-ability-override-processor` | **Date**: 2026-05-16 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/004-ability-override-processor/spec.md`

---

## Summary

Build `AcrossAI_Ability_Override_Processor` — a stateless PHP class that bridges the DB-stored ability
overrides (managed via the Abilities Manager UI) into live WordPress ability registrations at runtime.
The class intercepts `wp_register_ability_args` (P20–999) to inject non-null override fields, and fires
`wp_unregister_ability()` for all abilities with `site_allowed = false` at `wp_abilities_api_init` P100001,
after all plugin registrations are complete. Manager REST requests skip all override hooks (PATH A) so the
Manager UI always sees the pure registry. All other requests get full override injection (PATH B). Override
rows are loaded in a single transient-cached DB query per request (12h TTL) and the cache is busted on any
override save or reset.

---

## Technical Context

**Language/Version**: PHP 7.4+, WordPress 7.0+
**Primary Dependencies**:
- PHP: `AcrossAI_Sitewide_Query` (BerlinDB — all DB access delegated here), WordPress Transients API
- No new Composer or npm dependencies
**Storage**: Read-only from `{prefix}acrossai_abilities_overwrite` via `AcrossAI_Sitewide_Query`;
  transient key `acrossai_ability_overrides_cache` (TTL: 12 × HOUR_IN_SECONDS)
**Testing**: PHPUnit — unit tests for each public method; WP_Mock or integration test against live WP
**Target Platform**: WordPress 7.0+ admin + public (single-site and multisite)
**Project Type**: WordPress plugin — PHP-only feature module
**Performance Goals**: Zero per-request DB queries on PATH B after first cache population; one DB query on cache miss. PATH A skips all hooks (zero overhead on Manager requests).
**Constraints**:
  - Stateless class — static properties only; no instance properties; no `new` instantiation
  - PHP 7.4 compatible (no match expression, no named args, no `readonly` properties)
  - Never writes to DB directly — all DB access via `AcrossAI_Sitewide_Query::instance()`
  - `is_manager_rest_request()` is a performance/registration hint ONLY — never an access-control gate
  - Multisite-compatible (transient is per-site; `AcrossAI_Sitewide_Query` uses `$wpdb->prefix`)

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked against Constitution v1.4.1.*

### ✅ PASS — I. Modular Architecture
Class lives at `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`, scoped to the
Sitewide module — the feature it serves. No abstract base class. No `register_hooks()` delegation.
All WordPress hooks wired by `includes/Main.php::define_public_hooks()` via the Loader.
No dependencies on sibling modules; reads only from shared DB layer (`AcrossAI_Sitewide_Query`) and
WordPress core. Shared utility reuse checked (no duplication of sanitization or merge logic needed
for this class — it reads pre-validated DB rows and writes directly to WP registry).

> **SUPERSEDED DEVIATION — Singleton + Instance Wrapper Pattern (SEC-PLAN-002)**
> An earlier version of this plan proposed a static-only class without `instance()`. **This approach
> is superseded** because `Loader::run()` passes `array($component, $callback)` to WordPress, where
> `$component` is typed as `object` in the PHPDoc. Passing a class-name string would fail PHPStan L8.
>
> **Resolved pattern**: `AcrossAI_Ability_Override_Processor` MUST implement the singleton `instance()`
> pattern. All logic remains static. Public instance wrapper methods delegate to the static
> implementations and provide Loader-compatible object callbacks:
> ```php
> protected static $_instance = null;
> public static function instance(): self {
>     if ( null === self::$_instance ) { self::$_instance = new self(); }
>     return self::$_instance;
> }
> private function __construct() {}
> // Loader-compatible instance wrappers:
> public function boot_hook(): void        { static::boot(); }
> public function bust_cache_hook(): void  { static::bust_cache(); }
> ```
> `Main.php` wires via named variable (Boot Flow Rule — named variable before Loader call):
> ```php
> $override_processor = AcrossAI_Ability_Override_Processor::instance();
> $this->loader->add_action( 'plugins_loaded', $override_processor, 'boot_hook', 20 );
> $this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );
> ```
> All static methods (`boot()`, `inject_override_args()`, etc.) remain static and callable directly
> (e.g., `bust_cache()` from REST controllers). The singleton is a Loader-compatibility shim only.

### ✅ PASS — II. WordPress Standards Compliance
PHPCS strict + PHPStan L8 gates in DoD. WP 7.0+ / PHP 7.4+.
Uses `get_transient()` / `set_transient()` / `delete_transient()` (WP core — not deprecated).
Uses `wp_register_ability_args` filter and `wp_abilities_api_init` action (WP 7.0+ Abilities API).
Uses `add_filter()` / `remove_filter()` / `wp_unregister_ability()` — all current WP functions.
All DB access via BerlinDB `AcrossAI_Sitewide_Query` (wraps `$wpdb->prepare()` internally).
No raw SQL in this class.

### ✅ PASS — III. User-Centric Design
This is a PHP-only backend class. No admin UI, no forms, no tables. Principle III does not apply.

### ✅ PASS — IV. Security First (NON-NEGOTIABLE)
`is_manager_rest_request()` is explicitly documented as a **performance optimisation only** — never
used for access control. The Manager's own security is enforced by `AcrossAI_Sitewide_Rest_Controller::check_permission()` (manage_options + nonce) which runs independently.
All DB-read values from `AcrossAI_Sitewide_Row` are cast by `AcrossAI_Sanitizer::cast_tri_state()`
at query time (Row constructor). No additional sanitization required in the processor.
The `inject_override_args()` filter callback assigns DB values into `$args` array which flows into
WP core's `wp_register_ability()` internals — no output to HTML, no SQL. No escaping needed.
`is_manager_rest_request()` checks `$_SERVER` values but does NOT sanitize them (used only for
boolean detection — ROUTE and METHOD are compared with `strpos`/strict equality, never echoed).
**No OWASP surface opened**: no new REST endpoints, no input boundaries, no file operations.

### ✅ PASS — V. Extensibility Without Core Modification
Feature is implemented as new hooks and filters on the WP Abilities API — no existing plugin files
modified except:
  - `includes/Main.php` — adds one `add_action` hook (Loader pattern, canonical)
  - `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` — adds `get_all_overrides()` method
  - `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php` — adds `bust_cache()` call
  - `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php` — adds `bust_cache()` call
All changes are backward-compatible. If WP Abilities API is absent (WP < 7.0), hooks register but
never fire — graceful degradation, no error.

### ✅ PASS — VI. Reusability & DRY
`inject_override_args()` reuses DB rows already read by `AcrossAI_Sitewide_Query`. No duplication
of tri-state cast logic — `AcrossAI_Sitewide_Row` already casts tinyint to bool/null in constructor.
No new npm packages. No new Composer packages.

### ✅ PASS — VII. Definition of Done
All 8 DoD gates listed in tasks. Feature complete only when PHPCS, PHPStan L8, security review,
and unit tests pass.

---

## Pre-Plan Architecture Watchpoints

The following gaps were identified during architecture review of `includes/Modules/Sitewide/`
**before** writing this plan. They MUST be resolved as part of this feature's implementation tasks.

### W-001 (BLOCKER): Missing hook on `delete_override()` and bulk `reset`

`AcrossAI_Sitewide_Override_Controller::delete_override()` deletes the DB row successfully but fires
**no action hook** after deletion. `AcrossAI_Sitewide_Bulk_Controller::bulk_action()` fires
`acrossai_abilities_sitewide_after_save` only on `allow`/`disallow`, not on `reset`.

**Impact**: If `bust_cache()` were only hooked to `acrossai_abilities_sitewide_after_save`, resetting
an override through the Manager would leave the stale cache intact for up to 12 hours.

**Resolution chosen**: Rather than adding a new action hook to the delete path (which would require
patching both controllers and the hook inventory), the plan calls `AcrossAI_Ability_Override_Processor::bust_cache()` **directly** inside both controllers at the call site, after the DB operation succeeds. This is consistent with how override controllers already handle their own response logic. Direct call, no new hook required.

```php
// AcrossAI_Sitewide_Override_Controller::delete_override() — after $deleted check
if ( $deleted ) {
    AcrossAI_Ability_Override_Processor::bust_cache();
}

// AcrossAI_Sitewide_Bulk_Controller::bulk_action() — after delete_override_by_slug() for reset
// (ok is forced true for reset; bust cache unconditionally on reset)
AcrossAI_Ability_Override_Processor::bust_cache();
```

`save_override()` and `toggle_ability()` in `AcrossAI_Sitewide_Override_Controller` and save in
`AcrossAI_Sitewide_Bulk_Controller` already fire `acrossai_abilities_sitewide_after_save` — wire
`bust_cache_hook()` to that action in `Main.php` via the singleton instance (Boot Flow Rule):

```php
// In Main.php::define_public_hooks() — resolved pattern after SEC-PLAN-002 amendment:
$override_processor = AcrossAI_Ability_Override_Processor::instance();
$this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );
```

`bust_cache_hook()` is the public instance wrapper that delegates to the static `bust_cache()` method.
Direct calls to `AcrossAI_Ability_Override_Processor::bust_cache()` from REST controllers remain valid.

### W-002 (NOTE): `get_all_overrides()` missing from `AcrossAI_Sitewide_Query`

`AcrossAI_Sitewide_Query` currently has `get_override_by_slug()`, `save_override()`, and
`delete_override_by_slug()`. The processor needs `get_all_overrides()` to load the full table in one
query, which it then indexes by slug in memory. This must be added to `AcrossAI_Sitewide_Query`.

---

## Implementation Phases

### Phase 0 — Research & Decisions

**Decision 1: Hook approach for `boot()`**
`boot()` is called at `plugins_loaded` P20. WP core registers abilities inside `wp_abilities_api_init`
(P10 for core, P20–999 for plugins). The processor registers:
- `wp_register_ability_args` filter at `boot()` — fires per-ability during plugin registrations
- `wp_abilities_api_init` action at P100001 at `boot()` — fires after all registrations

**Decision 2: Transient key scoping**
Transient key: `acrossai_ability_overrides_cache`. WordPress transients are per-site on multisite
(if set via `set_transient()` without network prefix). This is correct — overrides are per-site.
TTL: `12 * HOUR_IN_SECONDS`. This matches the spec requirement.

**Decision 3: `$_SERVER` detection safety (amended — SEC-PLAN-001)**
`is_manager_rest_request()` checks in order:
1. `defined('WP_CLI') && WP_CLI` → `false` immediately (CLI has no `$_SERVER` request context)
2. `wp_doing_cron()` → `false` immediately
3. `wp_doing_ajax()` → `false` immediately
4. URI path check: `strpos( $uri, '/' . rest_get_url_prefix() . '/acrossai-abilities/' ) !== false`
   where `$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : ''`

**SECURITY NOTE**: `REQUEST_METHOD` is NOT used as a gate. The URI path check applies to ALL
HTTP methods — GET, POST, DELETE — equally. This is required so that Manager GET requests (e.g.
`GET /wp-json/acrossai-abilities-manager/v1/sitewide/abilities`) are correctly classified as PATH A.
Using `REQUEST_METHOD !== 'GET'` as a shortcut would incorrectly route Manager GET requests to PATH B,
causing override injection to corrupt the `_registry` layer shown in the Manager UI.

Uses `isset()` guard before accessing `$_SERVER['REQUEST_URI']`. No sanitization required (boolean
result only — URI string is consumed by `strpos()` and never echoed or used in SQL).

**Decision 4: `mcp_servers` JSON decode in `inject_override_args()`**
DB stores `mcp_servers` as JSON string (`wp_json_encode()` in `save_override()`). The processor reads
the raw row value (string) and must `json_decode()` before writing to `$args['meta']['mcp_servers']`.
Pattern:
```php
if ( is_string( $row->mcp_servers ) ) {
    $decoded = json_decode( $row->mcp_servers, true );
    if ( is_array( $decoded ) ) {
        $args['meta']['mcp_servers'] = $decoded;
    }
}
```

**Decision 5: `site_allowed` unregistration vs. arg injection**
Abilities with `site_allowed = false` are **unregistered** at P100001 (after all registrations), not
simply injected with `site_allowed = false` in `inject_override_args()`. The inject filter fires during
registration so `site_allowed = false` would still leave the ability in the registry. Complete removal
requires calling `wp_unregister_ability()` after all registrations are complete.
`inject_override_args()` DOES still inject `site_allowed = false` into the args (for consumers that
read individual ability args directly) AND the ability is unregistered at P100001.

---

### Phase 1 — Design Artifacts

#### 1A. Class Architecture

```
AcrossAI_Ability_Override_Processor         (new)
  File: includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php
  
  Static properties:
    protected static $_overrides_cache = null;   // null = not loaded; array = loaded (may be empty)
    protected static $_checked = false;          // is_manager_rest_request() result memoized

  Static methods:
    + boot(): void                               // called at plugins_loaded P20
    + is_manager_rest_request(): bool            // memoized via static $checked + $is_manager
    # load_overrides_cache(): array              // transient-backed, returns slug→row index
    + inject_override_args(array $args, string $name): array  // wp_register_ability_args filter
    + unregister_blocked_abilities(): void       // wp_abilities_api_init P100001
    + bust_cache(): void                         // public static — clears transient + in-memory

  No instance properties. No public constructor. Class is final.
```

#### 1B. Hook Wiring (in `includes/Main.php::define_public_hooks()`)

```php
// Resolve singleton before passing to Loader (Boot Flow Rule — named variable).
$override_processor = AcrossAI_Ability_Override_Processor::instance();

// Boot the override processor at plugins_loaded P20 (instance wrapper delegates to static::boot()).
$this->loader->add_action( 'plugins_loaded', $override_processor, 'boot_hook', 20 );

// Bust cache whenever an override is saved via the Manager REST API.
$this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );
```

`boot()` internally registers the per-ability filter and the post-registration unregistration hook.
`Main.php` wires only `boot_hook()` and `bust_cache_hook()` (instance wrappers) through the Loader.
Direct static calls (`bust_cache()`) from REST controllers remain unchanged.

#### 1C. DB Layer Change (`AcrossAI_Sitewide_Query`)

```php
/**
 * Retrieve all override rows indexed by ability_slug.
 *
 * @return AcrossAI_Sitewide_Row[]  Indexed by ability_slug string.
 */
public function get_all_overrides(): array {
    $results = $this->query( array( 'number' => 9999 ) );
    $indexed = array();
    foreach ( $results as $row ) {
        if ( $row instanceof AcrossAI_Sitewide_Row ) {
            $indexed[ $row->ability_slug ] = $row;
        }
    }
    return $indexed;
}
```

BerlinDB `query()` with `number => 9999` is an upper-bound fetch of all rows. This is a deliberate
limit (9999 abilities is far beyond any realistic site) to prevent unbounded memory use.

#### 1D. Cache-Bust Integration (in existing controllers)

**`AcrossAI_Sitewide_Override_Controller::delete_override()`** — add after successful deletion:
```php
AcrossAI_Ability_Override_Processor::bust_cache();
```

**`AcrossAI_Sitewide_Bulk_Controller::bulk_action()`** — add for `reset` action, after `$ok = true`:
```php
if ( 'reset' === $action ) {
    AcrossAI_Ability_Override_Processor::bust_cache();
}
```
(Save paths already bust via the `acrossai_abilities_sitewide_after_save` hook action wired in Main.php.)

#### 1E. Execution Flow Diagram

```
plugins_loaded (P20)
  └── AcrossAI_Ability_Override_Processor::boot()
        ├── is_manager_rest_request() → true  (PATH A: Manager REST)
        │     └── return — do NOT register any hooks
        └── is_manager_rest_request() → false  (PATH B: all other requests)
              ├── add_filter('wp_register_ability_args', [..., 'inject_override_args'], 10, 2)
              └── add_action('wp_abilities_api_init', [..., 'unregister_blocked_abilities'], 100001)

wp_abilities_api_init (P10)
  └── WP core registers abilities

wp_abilities_api_init (P20–999)
  └── Plugins register abilities
        └── Each wp_register_ability() triggers wp_register_ability_args filter
              └── inject_override_args($args, $name)
                    ├── load_overrides_cache()  → returns slug→row map (transient or DB)
                    ├── if no row for $name → return $args unchanged
                    └── foreach non-null DB field → overwrite $args field

wp_abilities_api_init (P100001)
  └── unregister_blocked_abilities()
        ├── load_overrides_cache()  → returns from in-memory (already populated above)
        └── foreach row where site_allowed === false → wp_unregister_ability($slug)

REST/MCP/execution request
  └── wp_get_ability($slug) or wp_get_abilities()
        └── Returns registry with injected args (PATH B) or pure registry (PATH A)
```

---

## Project Structure

### Documentation (this feature)

```text
specs/004-ability-override-processor/
├── spec.md               # Feature specification (written)
├── plan.md               # This file
├── memory-synthesis.md   # Created by memory-md.plan-with-memory (this session)
└── checklists/
    └── requirements.md   # Spec quality checklist (written)
```

### Source Code Changes

```text
includes/
├── Main.php                                                          # UPDATE: add 2 hook wires in define_public_hooks()
└── Modules/
    └── Sitewide/
        ├── AcrossAI_Ability_Override_Processor.php             # CREATE: stateless processor class
        ├── Database/
        │   └── AcrossAI_Sitewide_Query.php                          # UPDATE: add get_all_overrides() method
        └── Rest/
            ├── AcrossAI_Sitewide_Override_Controller.php            # UPDATE: add bust_cache() call in delete_override()
            └── AcrossAI_Sitewide_Bulk_Controller.php                # UPDATE: add bust_cache() call on reset
```

### No new files in `src/js/`, `src/scss/`, `admin/`, or `tests/phpunit/jest/` are created by this feature (PHP-only).
Tests live in `tests/phpunit/sitewide/OverrideProcessorTest.php` (new file).

---

## Complexity Tracking

> **RESOLVED DEVIATION — Singleton + Instance Wrapper Pattern** (SEC-PLAN-002; supersedes static-only plan)
>
> | Decision | Reason | Alternative Rejected |
> |---|---|---|
> | Singleton `instance()` + instance wrapper methods (`boot_hook()`, `bust_cache_hook()`) | `Loader::run()` passes `array($component, $callback)` to WordPress where `$component` must be an `object` (PHPDoc). Passing a class-name string would fail PHPStan L8. | Pure static-only class with string-based wiring: fails PHPStan L8 even though it works at PHP runtime. |

---

## Security Architecture Notes

### N-001: `is_manager_rest_request()` is NOT an access control gate

The Manager REST detection (`PATH A`) determines whether to skip override hook registration. It is a
**boolean optimisation only**. Even if an attacker fabricates a `$_SERVER['REQUEST_URI']` that matches
the Manager namespace, the worst outcome is that their request gets PATH A treatment (no override
injection). This is not a security downgrade — the Manager's `check_permission()` enforces
`manage_options` + nonce on every route independently.

Explicitly NOT used for: capability bypass, nonce bypass, or any authorization decision.

### N-002: Transient data is read-only at runtime; no user input reaches the cache

`load_overrides_cache()` reads from the transient (which was populated from BerlinDB rows, which were
written through sanitized REST controllers). No user-supplied request data flows into cache writes during
PATH B execution. Cache writes only happen in `load_overrides_cache()` (from DB rows) and are busted
by `bust_cache()` (triggered by authenticated REST operations).

### N-003: `wp_unregister_ability()` side effects are scoped to the current request

Ability unregistration in the WP registry affects only the in-memory registry for the current PHP
request. Nothing is written to the database. On the next request, abilities re-register from their
original registrations and the processor applies DB overrides again. No permanent registry corruption
is possible from this class.

---

## Definition of Done

- [ ] PHPCS: zero errors, zero warnings
- [ ] PHPStan level 8: zero errors
- [ ] `AcrossAI_Ability_Override_Processor` class passes all unit tests
- [ ] `get_all_overrides()` method tested (empty table, populated table, malformed row handling)
- [ ] `inject_override_args()` tested: null fields skipped, non-null fields applied, mcp_servers decoded
- [ ] `unregister_blocked_abilities()` tested: abilities with `site_allowed = false` removed
- [ ] `bust_cache()` tested: transient deleted, in-memory cache cleared
- [ ] `is_manager_rest_request()` tested: CLI, cron, AJAX, Manager REST, other REST, frontend
- [ ] `delete_override()` + bulk `reset` bust cache on successful deletion
- [ ] `Main.php` hook wiring verified: boot at P20, bust_cache on after_save
- [ ] Security review: no new OWASP surface; `is_manager_rest_request()` scope documented
- [ ] All functions/properties prefixed with `acrossai_` (class name and hook names)
- [ ] `npm run validate-packages` passes (no new packages added — trivially passes)
