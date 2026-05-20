# Memory Synthesis: Custom Abilities Module (008)

## Current Scope

The Custom Abilities module adds a user-facing creation and management layer for WordPress abilities without requiring PHP code. Admins will create abilities via DataForm UI, store them in BerlinDB, and auto-register them at `wp_abilities_api_init`. The module includes CRUD REST endpoints, DataViews admin tables, and optional MCP exposure. Affected modules: REST API layer, BerlinDB schema, Admin UI, Abilities API integration.

---

## Relevant Decisions

1. **DEC-NAMESPACE-CONVENTION** (2026-05-19) — All PHP namespaces MUST use underscore convention: `AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability`. Never use PSR-4 backslash-only namespaces (e.g., `AcrossAI\Abilities\*`). This applies to all utilities, database classes, REST controllers, and sub-controllers. Status: Active. Reason Included: Prevents "Class not found" integration errors and maintains consistency. Source: DECISIONS.md DEC-NAMESPACE-CONVENTION.

2. **DEC-PROTECTED-SLUGS-PATTERN** (2026-05-19) — Custom abilities with reserved/internal namespace prefixes (e.g., "acrossai/*", "mcp/*") SHOULD be filterable via a centralized utility `AcrossAI_Protected_Custom_Abilities::get_protected_prefixes()`. Pattern: static method returning array, `apply_filters()` at return time, membership check with strict comparison. Status: Active. Reason Included: Enables future feature-gating and prevents namespace collision. Reusable for private/hidden abilities, restricted MCP types. Source: DECISIONS.md DEC-PROTECTED-SLUGS-PATTERN (extended from FR-003 hide-mcp-system-abilities).

3. **AC-REST-SPLIT** (Constitution §I) — REST controllers exceeding ~400 lines MUST split into orchestrator + per-domain sub-controllers in `includes/Modules/Custom_Ability/Rest/`. Sub-controllers handle one user story group (read, write, bulk, MCP). Orchestrator keeps namespace, `register_routes()`, shared `check_permission()`. Main.php wires only orchestrator. Status: Active. Reason Included: Custom Abilities will have write/read/MCP paths that may exceed 400 LOC combined. REST endpoint proliferation (POST create, GET list, GET/ID read, POST/ID update, DELETE/ID delete, + MCP exposure queries) necessitates split early. Source: CONSTITUTION.md §I, ARCHITECTURE.md AC-REST-SPLIT.

4. **DEC-UTILITY-STATIC-ONLY** (Constitution §VI) — Utility classes are 100% static (only static methods and constants). Only module orchestrators use singleton pattern. Status: Active. Reason Included: Custom Abilities validation, field transformation, callback execution helpers should live in `includes/Utilities/` with static methods (e.g., `AcrossAI_Custom_Ability_Validator::validate_slug()`, `AcrossAI_Custom_Ability_Callback::execute()`). Prevents instance state mutations. Source: DECISIONS.md DEC-UTILITY-STATIC-ONLY.

5. **SEC-PLAN-002** (2026-05-17) — If Custom_Ability_Processor or similar needs `wp_abilities_api_init` hook registration, implement singleton pattern with thin `register_hook()` instance wrapper. Main.php wires wrapper via Loader with named variable (never inline `::instance()`). Static methods remain callable directly. Status: Active. Reason Included: Custom Abilities registration at boot time may require conditional injection like Sitewide processor. Source: DECISIONS.md SEC-PLAN-002.

---

## Architectural Patterns (Reusable)

1. **PATTERN-SINGLE-SOURCE-UTILITY** — Extract callback execution logic (`noop`, `filter_hook`, `wp_remote_post`) to `AcrossAI_Custom_Ability_Callback_Executor` static utility. Query layer + REST controller + ability execution engine all call the same static method. Single edit point, testable in isolation, DRY principle. Reference: `AcrossAI_Protected_Abilities` (feature 005, called from 2+ locations).

2. **PATTERN-STAGE-NAMING** — When building ability metadata for registration (raw DB row → sanitized fields → merged with WP registry → injected args), use distinct variable names: `$db_row` (raw), `$sanitized_ability` (validated), `$registered_ability` (merged), `$injected_args` (final). Improves clarity in multi-stage pipeline. Reference: `AcrossAI_Ability_Logger::finish_pending_entry()` (feature 006).

3. **PATTERN-FEATURE-ASSET-SEPARATION** — Custom Abilities admin UI should use separate asset handles `acrossai-abilities-custom` (not generic `index`). Separate entry point in `webpack.config.js`. Admin/Main.php enqueue logic: `if ( strpos( $hook, 'acrossai-custom-abilities' ) ) { wp_enqueue_style( 'acrossai-abilities-custom', ... ) }`. Reference: Feature 006 logger assets.

4. **AC-QUERY-LAYER-FILTERING** — List endpoint filtering (search, sort, pagination, field filtering) MUST occur in `AcrossAI_Custom_Ability_Registry_Query::query()`, not REST controller. Query layer is single source of truth for "what exists". Pagination headers must reflect filtered results. Reference: `AcrossAI_Ability_Registry_Query` (feature 005, excludes protected abilities at query time before appending to results).

---

## Conflicts & Assumptions

1. **Assumption: Callback execution is in scope for v1** — FR-007 specifies three callback types (noop, filter_hook, wp_remote_post), but actual execution (via `wp_execute_ability()` call path) may be deferred. **Verify**: Does the spec expect custom abilities to fire callbacks, or only to register/expose them? If execution is out-of-scope for v1, mark callback executor as a stub/TODO.

2. **Assumption: BerlinDB supports custom JSON columns** — Custom Abilities uses `callback_config` (JSON), `permission_config` (JSON), `input_schema` (JSON), `output_schema` (JSON) columns. **Verify**: BerlinDB schema generation correctly handles JSON type; test with large schema (10KB+). If not, fall back to `LONGTEXT` and apply manual `json_encode/json_decode`.

3. **Assumption: Namespace prefix collision policy** — FR-006 specifies "namespace/name" pattern (e.g., "custom/my-ability"). No explicit policy for preventing admins from creating "core/*" or "wp/*" prefixed abilities that collide with WordPress core abilities. **Verify**: Should we block certain prefixes? Or allow and let admin responsibility decide? Check if Sitewide processor's `inject_override_args` will silently overwrite custom abilities if a core ability uses same slug. If yes, add validation block.

4. **Hard Boundary: Custom Abilities do not override Sitewide overrides** — Custom Abilities are a separate registration layer, not part of the Sitewide Override module (004). If both Sitewide overrides and Custom Abilities register an ability, Sitewide takes precedence (registered later, overwrites). **Verify**: Is this the intended precedence? If not, document the precedence rule explicitly.

---

## Relevant Security Constraints

1. **SEC-01** — `sanitize_ability_slug()` applied at every REST endpoint receiving a slug; max 255 chars. Reference: security-constraints.md.

2. **SEC-02** — `before_save` hook fires on sanitized `$fields` only; re-apply bool→int cast before BerlinDB. Reference: security-constraints.md.

3. **SEC-03** — `AcrossAI_Custom_Ability_Table::$global = false` — per-site prefix; multisite isolation explicit. Reference: security-constraints.md.

4. **SEC-04** — Strict type comparison for access control checks; no loose equality in permission callbacks. Reference: security-constraints.md, BUG-LOOSE-COMPARISON-BYPASS.

---

## Related Historical Lessons (Bug Patterns to Avoid)

1. **BUG-BERLINDB-UNLIMITED** — Never use `number => -1` for unlimited BerlinDB queries. Always use `number => 0` (no LIMIT clause). Reference: BUGS.md, fixed in feature 004.

2. **BUG-FLAT-ARGS-PATH** — When injecting custom ability metadata into WP registry at `wp_abilities_api_init`, confirm write paths against read paths. Custom Abilities metadata should be stored in nested `$args['meta']['*']` structure, not flat top-level keys. Reference: BUGS.md, fixed in feature 004 (inject_override_args).

3. **BUG-PARTIAL-HOOK-FIELDS** — Any REST endpoint performing a partial save MUST fetch the complete saved row after `save_custom_ability()` and pass full 20-field row to hooks, not the local `$fields` subset. Reference: BUGS.md, fixed in feature 004 (bulk/toggle paths).

4. **BUG-UNIMPLEMENTED-HOOK** — Every hook declared in spec.md (extensibility points, filters, actions) MUST have a corresponding `apply_filters()`/`do_action()` call in implementation. Pre-implementation audit: grep the codebase for every hook name listed in spec §V after feature ships. Reference: BUGS.md, fixed in feature 004.

5. **BUG-LOOSE-COMPARISON-BYPASS** — Use strict `===` comparison for all access-control and permission checks. Loose equality (`==`) can bypass type safety. Reference: BUGS.md, related to SEC-04.

---

## Watchpoints (Specific Areas to Validate During Implementation)

### Watchpoint 1: BerlinDB Schema + JSON Validation
**Verify**: 
- BerlinDB schema definition correctly defines JSON columns (`callback_config`, `permission_config`, `input_schema`, `output_schema`).
- `AcrossAI_Custom_Ability_Row::__construct()` applies `json_decode()` to JSON fields; test with valid/invalid/null values.
- Validation fires before `save()` to BerlinDB (catch errors early, not in DB layer).

### Watchpoint 2: Namespace Collision Detection
**Verify**:
- When registering custom abilities at `wp_abilities_api_init`, check if ability slug already exists in `wp_get_registered_abilities()`.
- If collision detected, log error; do not silently overwrite registered ability.
- Return early if slug collision occurs; document failure mode for admins (error notice or log entry).

### Watchpoint 3: Permission Callback Injection Pattern
**Verify**:
- When `permission_type = 'capability'`, inject closure into `$args['permission_callback']` following `DEC-PERM-CB` pattern (from Sitewide processor).
- Closure must call `current_user_can()` with admin-provided capability; fail open if capability doesn't exist.
- Test with `permission_type = 'always_allow'`, `'logged_in'`, `'capability'` — all three paths.

### Watchpoint 4: Callback Execution Contract
**Verify**:
- `callback_type = 'noop'` → No execution, documentation only.
- `callback_type = 'filter_hook'` → Fire WordPress filter with custom ability input; collect filter return value.
- `callback_type = 'wp_remote_post'` → Parse `callback_config['url']`, POST with input schema as body, 30-second timeout, handle errors gracefully.
- **Critical**: Confirm `wp_execute_ability()` call path (from WordPress core) will invoke custom ability callbacks correctly. If out-of-scope for v1, stub with TODO comment.

### Watchpoint 5: REST Controller Split Validation
**Verify**:
- Orchestrator: `AcrossAI_Custom_Ability_Rest_Controller` (routes, shared permission).
- Sub-controllers in `Rest/`: `AcrossAI_Custom_Ability_Read_Controller` (GET list, GET/ID), `AcrossAI_Custom_Ability_Write_Controller` (POST create, POST/ID update, DELETE/ID), `AcrossAI_Custom_Ability_Mcp_Controller` (MCP exposure queries).
- Each sub-controller < 400 LOC; no shared business logic scattered across.

### Watchpoint 6: Hook Compliance Audit
**Verify** (post-implementation):
- Grep for `apply_filters()` and `do_action()` calls; verify all hooks listed in spec.md extensibility section are fired.
- Grep for hook names; ensure no typos or undeclared hooks.
- Filter return values cast to expected type (array → `(array)`, bool → `(bool)`).

### Watchpoint 7: Multisite Isolation
**Verify**:
- `AcrossAI_Custom_Ability_Table::$global = false` ensures per-site table prefix.
- Custom abilities created on site A do not leak to site B.
- Test with `wp network activate acrossai-abilities-manager --all`.

### Watchpoint 8: Static Utility Consistency
**Verify**:
- Callback executor, validator, sanitizer utilities are 100% static (no instance state, no `__construct()`).
- Utilities can be called from REST controller, query layer, processor, and logging independently without initialization order dependency.
- No utility instantiation in `includes/Main.php` Loader wiring.

### Watchpoint 9: DataForm + DataViews Integration
**Verify**:
- DataForm component renders all 20 fields for ability creation/editing.
- DataViews table displays searchable/sortable columns; filters work correctly.
- Admin UI inherits WCAG 2.1 A compliance from @wordpress/dataforms components.

### Watchpoint 10: Asset Separation Verification
**Verify**:
- `build/css/custom-abilities.css`, `build/js/custom-abilities.js` exist and are bundled separately.
- Admin/Main.php enqueues custom-abilities assets only on `acrossai-custom-abilities` page (not on general Abilities Manager page).
- No shared-asset coupling between Logger (006) and Custom Abilities (008).

---

## Retrieval Notes

- **Index entries considered** (20 max): ARCH-ADV-001, AC-HOOKS-MAIN, AC-ENQUEUE-ADMIN, AC-REST-SPLIT, DEC-NAMESPACE-CONVENTION, DEC-PROTECTED-SLUGS-PATTERN, DEC-UTILITY-STATIC-ONLY, SEC-PLAN-002, AC-QUERY-LAYER-FILTERING, AC-FILE-HEADER-PATTERN, PATTERN-SINGLE-SOURCE-UTILITY, PATTERN-STAGE-NAMING, PATTERN-FEATURE-ASSET-SEPARATION.
- **Source sections read**: ARCHITECTURE.md (System Overview, Major Components, Boundaries, AC-* constraints, PATTERN-* patterns), DECISIONS.md (DEC-NAMESPACE-CONVENTION, DEC-PROTECTED-SLUGS-PATTERN, SEC-PLAN-002, DEC-FAIL-OPEN-NOTICE), BUGS.md (BUG-BERLINDB-UNLIMITED, BUG-FLAT-ARGS-PATH, BUG-PARTIAL-HOOK-FIELDS, BUG-UNIMPLEMENTED-HOOK, BUG-LOOSE-COMPARISON-BYPASS).
- **Budget status**: ~900 words (within limit); synthesis prioritizes REST split, namespace consistency, BerlinDB patterns, and execution-time watchpoints over speculative feature extensibility.

