# Tasks: Ability Override Processor

**Feature Branch**: `004-ability-override-processor`
**Input**: Design documents from `specs/004-ability-override-processor/`
**Prerequisites**: [plan.md](plan.md) | [spec.md](spec.md) | [memory-synthesis.md](memory-synthesis.md) | [security-constraints.md](security-constraints.md)

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no incomplete dependencies)
- **[Story]**: User story label from [spec.md](spec.md)

---

## Phase 1: Setup

**Purpose**: Re-read feature memory and confirm runtime-override boundaries before touching any plugin file.

- [x] T001 Read `specs/004-ability-override-processor/memory-synthesis.md` — confirm Singleton+Wrapper pattern (SEC-PLAN-002), W-001 cache-bust resolution, and hook timing constraints before implementation.
- [x] T002 Read `specs/004-ability-override-processor/plan.md` §W-001, §1A, §1B, and §1D to confirm all integration points: `get_all_overrides()` signature, Loader wiring pattern, and direct `bust_cache()` call-sites in controllers.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Add the DB method, the processor class skeleton, and the Loader hook wiring that all user-story phases depend on.

**⚠️ CRITICAL**: No user story work can begin until T003–T005 are all complete.

- [x] T003 Add `get_all_overrides(): array` to `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` — call `$this->query( array( 'number' => 0 ) )` (`0` = unlimited in BerlinDB; `-1` silently becomes `1` via `absint()`), type-guard each result with `$row instanceof AcrossAI_Sitewide_Row`, return `AcrossAI_Sitewide_Row[]` indexed by `$row->ability_slug`.
- [x] T004 Create `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (namespace `AcrossAI_Abilities_Manager\Includes\Modules\Sitewide`) with full class implementation: singleton boilerplate (`protected static $_instance`, `public static function instance(): self`, `private function __construct()`); static properties (`protected static $_overrides_cache = null`, `protected static $_checked = false`, `protected static $_is_manager = false`); instance wrappers `boot_hook()` → `static::boot()` and `bust_cache_hook()` → `static::bust_cache()`; `boot(): void` with PATH A/B branch; `is_manager_rest_request(): bool` — checks `WP_CLI`, `wp_doing_cron()`, `wp_doing_ajax()`, then `strpos( $uri, '/' . rest_get_url_prefix() . '/' . $namespace . '/' )` where `$namespace` comes from `ACROSSAI_MANAGER_REST_NAMESPACE` constant (filterable via `acrossai_manager_rest_namespace` — exact namespace match, not prefix); `load_overrides_cache(): void` with `is_array()` guard on transient output (SEC-PLAN-003 / SEC-005); `bust_cache(): void`; `inject_override_args( array $args, string $slug ): array`; `unregister_blocked_abilities(): void`.
- [x] T005 Wire processor in `includes/Main.php::define_public_hooks()`: `$override_processor = AcrossAI_Ability_Override_Processor::instance();` then `$this->loader->add_action( 'plugins_loaded', $override_processor, 'boot_hook', 20 );` and `$this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );` (named variable before Loader calls — Boot Flow Rule; SEC-PLAN-002).

**Checkpoint**: Class exists, hooks are wired, `is_manager_rest_request()` returns correct bool. User story implementation can now begin.

---

## Phase 3: User Story 1 — Admin-Saved Overrides Take Effect at Runtime (P1) 🎯 MVP

**Goal**: Non-null DB override values are injected into each ability's arguments before registration completes, on all non-Manager requests.

**Independent Test**: Save a field override (`readonly = true`) for any registered ability in the Manager. Then call `wp_get_ability('slug')` from WP-CLI or a test plugin on a PATH B request. Verify the returned `readonly` matches the saved value.

- [x] T006 [US1] Implement `inject_override_args( array $args, string $slug ): array` in `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`: call `self::load_overrides_cache()`; return `$args` unchanged if no row for `$slug`; for each non-null override field write to the correct nested `$args` path (FR-009): `site_allowed` → `$args['site_allowed']`; `readonly/destructive/idempotent` → `$args['meta']['annotations'][<key>]`; `show_in_rest` → `$args['meta']['show_in_rest']`; `show_in_mcp/mcp_type` → `$args['meta']['mcp']['public'/'type']`; `mcp_servers` (already decoded to `array|null` by `AcrossAI_Sitewide_Row::__construct()`) → guard with `is_array()` only, write to `$args['meta']['mcp']['servers']` (never call `json_decode()`); skip null values to preserve Inherit semantics (FR-006); inject `permission_callback` closure when `RuleQuery::get_rule('acrossai-abilities', $slug)` returns a non-empty key — fail-open when `get_manager()` returns null; register as `wp_register_ability_args` filter at P10 with `$accepted_args = 2` inside `boot()`.
- [x] T016 [US1] Register `mcp_adapter_expose_ability` filter in the PATH B block of `boot()` and implement `filter_mcp_adapter_expose_ability()` in `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` — (1) inside `boot()`, immediately after `add_action( 'wp_abilities_api_init', array( __CLASS__, 'unregister_blocked_abilities' ), 100001 );`, add `add_filter( 'mcp_adapter_expose_ability', array( __CLASS__, 'filter_mcp_adapter_expose_ability' ), 10, 2 );`; (2) implement `public static function filter_mcp_adapter_expose_ability( bool $expose, $ability ): bool` — call `self::load_overrides_cache()`; read the servers allowlist from `$ability->get_meta( 'mcp' )['servers'] ?? null` (already injected into the ability object by `inject_override_args()` at filter P10); if `is_array( $servers )` and the array is non-empty, return `false` when the current MCP server identifier is NOT in `$servers`; return `$expose` unchanged when `$servers` is null, empty, or not an array (no servers override set). Run PHPCS and PHPStan level 8 on the modified file after.

---

## Phase 4: User Story 2 — Disabled Abilities Are Completely Hidden (P1)

**Goal**: Abilities with `site_allowed = false` are completely removed from the WP registry after all abilities have registered, on all non-Manager requests.

**Independent Test**: Set `site_allowed = false` for any registered ability. On the next non-Manager request, call `wp_get_ability('slug')`. Verify it returns null. Verify `wp_get_abilities()` does not include it.

- [x] T007 [US2] Implement `unregister_blocked_abilities(): void` in `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`: call `self::load_overrides_cache()` (already populated from inject phase — no second DB query); iterate all rows; call `wp_unregister_ability( $slug )` for each row where `$row->site_allowed === false`; register at `wp_abilities_api_init` P100001 inside `boot()` (after all plugin registrations complete).

---

## Phase 5: User Story 3 — Manager UI Is Not Affected by Override Injection (P1)

**Goal**: All requests to the Manager's own REST namespace skip override injection entirely (PATH A), so the Manager always shows pure WP registry values for the `_registry` layer.

**Independent Test**: Load the Abilities Manager admin page. For any ability with a DB override, verify the `_registry` column shows the original registration-time value — not the merged override value.

- [x] T008 [US3] Verify the PATH A branch in `boot()` inside `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`: `if ( self::is_manager_rest_request() ) { return; }` prevents both the `wp_register_ability_args` filter and the `wp_abilities_api_init` action from being added; inline comment `// FR-003 / SEC-PLAN-001: PATH A — Manager REST skips override injection entirely.` present.
- [x] T009 [P] [US3] Create `tests/phpunit/sitewide/AbilityOverrideProcessorTest.php` (namespace `AcrossAI_Abilities_Manager\Tests\PHPUnit\Sitewide`) with PHPUnit test cases: (1) Manager GET request URI returns `true` from `is_manager_rest_request()` (SEC-PLAN-001); (2) `WP_CLI` context returns `false`; (3) non-Manager REST URI returns `false`; (4) non-array transient output treated as cache miss (`is_array()` guard — SEC-005 / SEC-PLAN-003); (5) `boot_hook()` delegates to `static::boot()` and `bust_cache_hook()` delegates to `static::bust_cache()` (SEC-PLAN-002).

---

## Phase 6: User Story 4 — Override Cache Invalidates When Overrides Change (P2)

**Goal**: Saving or resetting an override through the Manager immediately clears the override cache so the very next non-Manager request sees the updated values.

**Independent Test**: Save an override, make a non-Manager ability call (populates cache), then reset the override. On the very next non-Manager call, verify the field reverts to the registration-time default without a manual cache flush.

- [x] T010 [US4] Add `use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Ability_Override_Processor;` to `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php`; add `AcrossAI_Ability_Override_Processor::bust_cache();` inside the `if ( $deleted )` block in `delete_override()` — W-001 resolution (delete path fires no `acrossai_abilities_sitewide_after_save` hook).
- [x] T011 [P] [US4] Add `use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Ability_Override_Processor;` to `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php`; add `AcrossAI_Ability_Override_Processor::bust_cache();` for the `reset` branch in `bulk_action()` after `delete_override_by_slug()` returns — W-001 resolution (reset fires no `acrossai_abilities_sitewide_after_save` hook).

---

## Phase 7: Polish & Validation

**Purpose**: Confirm all quality gates pass before marking the feature complete.

- [x] T012 Run PHPCS on all modified files: `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`, `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php`, `includes/Main.php`, `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php`, `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php`. **Result**: 0 errors, 0 warnings (pre-existing `$_instance` PSR2 warning excluded — tracked separately).
- [x] T013 Run PHPStan level 8 on all modified files — verify no type error on Loader wiring (Loader `$component` is `object`, satisfied by singleton instance — SEC-PLAN-002). **Result**: exit 0.
- [ ] T014 Run PHPUnit `tests/phpunit/sitewide/AbilityOverrideProcessorTest.php` — all test cases must pass. **⚠️ BLOCKED**: No WP test bootstrap configured in this project (pre-existing gap, not a feature-004 regression). Unblock by adding `phpunit.xml.dist` + WP bootstrap shim, or run against a local WP install via WP-CLI.
- [ ] T015 Review implementation diff for durable memory candidates before marking the feature complete — propose updates to `docs/memory/ARCHITECTURE.md`, `docs/memory/DECISIONS.md`, and `docs/memory/BUGS.md` for: W-001 cache-bust on delete/reset resolution, singleton+wrapper pattern (SEC-PLAN-002), BerlinDB `number => 0` for unlimited (not `9999` or `-1`), `mcp_servers` pre-decoded in `AcrossAI_Sitewide_Row::__construct()`.

---

## Dependencies

```
T001 → T002 → T003 → T004 → T005
                              ↓
             T006 (US1) → T016 (US1)   T007 (US2) → T008 (US3)
             T006 (US1) → T008 (US3)
             T009 [P] (US3 tests — parallel with T008)
             T010 (US4) [P with T011]   T011 [P] (US4)
                              ↓
                    T012 → T013 → T014 → T015
```

### User Story Completion Order

| Story | Priority | Depends on | Independently Testable | State |
|---|---|---|---|---|
| US1 (Override Injection) | P1 | Phase 2 complete | ✅ via `wp_get_ability()` on PATH B | ✅ Done |
| US2 (Block Abilities) | P1 | Phase 2 complete | ✅ via `wp_get_abilities()` on PATH B | ✅ Done |
| US3 (Manager Bypass) | P1 | US1 + US2 complete | ✅ via Manager admin page `_registry` column | ✅ Done |
| US4 (Cache Bust) | P2 | Phase 2 complete | ✅ via save + reset + read cycle | ✅ Done |

### Parallel Execution Opportunities

- T003, T004, T005 must run sequentially (T004 depends on T003's return type; T005 depends on T004's class existing)
- T006 (US1) and T007 (US2) are in the same file — run sequentially to avoid edit conflicts
- T016 (US1) is in the same file as T006/T007 — run after T006 is complete
- T009 (US3 tests) can run in parallel with T008 (same story, different concern)
- T010 (Override Controller) and T011 (Bulk Controller) are in different files — fully parallel
- T012, T013, T014 must run sequentially

### MVP Scope

US1 + US2 + US3 (T003–T009) deliver the complete feature value. US4 (T010–T011) adds cache correctness and should follow immediately — without it, the cache can go stale for up to 12h after admin resets. All stories are P1 or P2; no reasonable deferred scope.

---

## Remaining Work

| Task | Status | Blocker |
|---|---|---|
| T014 — PHPUnit | ⚠️ BLOCKED | No WP test bootstrap in project (pre-existing) |
| T016 — `mcp_adapter_expose_ability` filter | ✅ Done | — |
| T015 — Memory review | ⬜ PENDING | Awaiting approval |
