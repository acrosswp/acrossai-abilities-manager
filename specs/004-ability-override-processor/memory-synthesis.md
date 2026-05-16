# Memory Synthesis: Ability Override Processor (004)

**Generated**: 2026-05-17
**Status**: Implementation complete. PHPCS 0 errors. PHPStan L8 exit 0. PHPUnit blocked (no WP bootstrap — pre-existing). Memory review (T015) pending.

---

## Current Scope
Feature 004 implements `AcrossAI_Ability_Override_Processor` — a static processor class that bridges DB-stored overrides into live WP ability registrations at PATH B request boot. Wired in `includes/Main.php` at `plugins_loaded P20`. Also injects `permission_callback` at runtime via the `wpb-access-control` library when an access-control rule exists for the ability slug. Affected modules: `includes/Modules/Sitewide/` (new processor class), `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query` (new `get_all_overrides()`), `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller` and `AcrossAI_Sitewide_Bulk_Controller` (bust_cache calls).

---

## Relevant Decisions

- **DEC-PATH-A/B** (Active, Source: spec.md FR-003): PATH A (Manager REST) skips all override hooks. Detection uses `ACROSSAI_MANAGER_REST_NAMESPACE` constant (default `'acrossai-abilities-manager/v1'`), filterable via `acrossai_manager_rest_namespace` filter, matched with `strpos($uri, '/' . rest_get_url_prefix() . '/' . $namespace . '/')`. Exact match prevents false-positives from third-party namespaces. Detection is a performance hint only — NOT an access-control gate.
- **DEC-SINGLETON-WRAPPER** (Active, Source: memory.md): All logic is static. Instance exists solely for Loader compat. `boot_hook()` / `bust_cache_hook()` delegate to static methods. `Main.php` wires via named variable — no inline `::instance()` in Loader call.
- **DEC-CACHE** (Active, Source: spec.md SC-004): Transient key `acrossai_ability_overrides_cache`, TTL 12h. Bust on save, delete, and bulk reset. `delete_override()` and bulk `reset` call `bust_cache()` directly (those paths do not fire `acrossai_abilities_sitewide_after_save`).
- **DEC-PERM-CB** (Active, Source: spec.md FR-009): `permission_callback` injected independently of override row. Checks `get_query()->get_rule('acrossai-abilities', $slug)` — non-empty key triggers closure injection. Closure calls `user_has_access(get_current_user_id(), 'acrossai-abilities', $slug)`. Fail-open (`true`) if `get_manager()` returns null.
- **DEC-BERLINS-UNLIMITED** (Active, Source: AcrossAI_Sitewide_Query.php): BerlinDB `number => 0` = unlimited rows. `number => -1` becomes `1` via `absint()` — never use for unlimited.

---

## Active Architecture Constraints

- **Main.php sole hook surface**: No other class registers hooks. `define_public_hooks()` wires all Loader entries for this feature. (Source: CONSTITUTION.md Boot Flow Rule)
- **inject_override_args field paths** (CRITICAL): Nested paths only — never flat top-level keys. `site_allowed` → `$args['site_allowed']`; `readonly/destructive/idempotent` → `$args['meta']['annotations']['<key>']`; `show_in_rest` → `$args['meta']['show_in_rest']`; `show_in_mcp/mcp_type/mcp_servers` → `$args['meta']['mcp']['public/type/servers']`; `permission_callback` → `$args['permission_callback']`. (Source: spec.md FR-009; confirmed from AcrossAI_Ability_Merger.php)
- **`meta['mcp']` is plugin-specific**: Not a WP core field. Injection only takes effect if MCP integration reads `$args['meta']['mcp']`. Revisit if MCP integration changes read path.
- **No includes/Base/**: CONSTITUTION.md v1.3.0 removed abstract module base class and `includes/Base/`. Do not create it.
- **PHPUnit blocked (pre-existing)**: No WP test bootstrap in project. Not a feature-004 regression; affects all test files.

---

## Accepted Deviations

- **Static-only processor** (Source: memory.md): All logic in static methods; instance is thin Loader-compat wrapper. Documented deviation — do not generalize.
- **`$_` prefix on static properties** (Source: PHPCS warnings): Codebase-wide suppressed WPCS warning on 4 properties. Tracked as warnings, not errors.

---

## Relevant Security Constraints

- **PATH A is not a gate** (Source: spec.md SEC-PLAN-001): Spoofed URI skips injection but all REST routes still protected by `check_permission()`. Never rely on PATH A for authorization.
- **Transient guard** (SEC-PLAN-003): `load_overrides_cache()` validates transient as `is_array()`; non-array = cache miss. Guards corrupted object-cache entries. Must not be removed.
- **`permission_callback` fail-open**: Intentional — library unavailability preserves access. If deny-by-default on absence is required, this must be revisited explicitly.

---

## Related Historical Lessons

- **BerlinDB unlimited**: `number => -1` silently becomes `number => 1` via `absint()`. Always use `0` for unlimited queries. (Source: session 2026-05-16)
- **mcp_servers already decoded**: `AcrossAI_Sitewide_Row::__construct()` decodes JSON to `array|null` at construction. Never call `json_decode()` in the processor — guard with `is_array()` only.
- **inject_override_args flat-path mistake**: Originally wrote `$args['readonly']` at top level. Correct path is `$args['meta']['annotations']['readonly']`. Confirmed from merger read paths.

---

## Conflict Warnings

- **Repo memory CONSTITUTION.md** lists `includes/base/` in directory layout (v1.0.0 snapshot). CONSTITUTION.md v1.3.0 removed this. Soft conflict — outdated repo memory note. Not relevant to feature 004 but could mislead agents creating new modules. Propose repo memory update after T015 approval.

---

## Retrieval Notes

- INDEX.md: empty (no entries populated). All durable memory files are template stubs.
- Sources: CONSTITUTION.md (full — small), feature memory.md, prior memory-synthesis.md (2026-05-16), repo memory (3 files: CONSTITUTION, access-control-integration, constitution-sync-impact).
- Budget used: Decisions: 5/5. Architecture constraints: 5/5. Accepted deviations: 2/3. Security constraints: 3/3. Bug patterns: 3/3. Worklog: 0/2 (stub).
