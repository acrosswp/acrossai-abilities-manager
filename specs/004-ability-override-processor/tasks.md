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

- [ ] T001 Read `specs/004-ability-override-processor/memory-synthesis.md` — confirm Singleton+Wrapper pattern (SEC-PLAN-002), W-001 cache-bust resolution, and hook timing constraints before implementation.
- [ ] T002 Read `specs/004-ability-override-processor/plan.md` §W-001, §1A, §1B, and §1D to confirm all integration points: `get_all_overrides()` signature, Loader wiring pattern, and direct `bust_cache()` call-sites in controllers.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Add the DB method, the processor class skeleton, and the Loader hook wiring that all user-story phases depend on.

**⚠️ CRITICAL**: No user story work can begin until T003–T005 are all complete.

- [ ] T003 Add `get_all_overrides(): array` to `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` — call `$this->query( array( 'number' => 9999 ) )`, type-guard each result with `$row instanceof AcrossAI_Sitewide_Row`, return `AcrossAI_Sitewide_Row[]` indexed by `$row->ability_slug`.
- [ ] T004 Create `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (namespace `AcrossAI_Abilities_Manager\Includes\Modules\Sitewide`) with full class scaffold: singleton boilerplate (`protected static $_instance`, `public static function instance(): self`, `private function __construct()`); static properties (`protected static $_overrides_cache = null`, `protected static $_checked = false`, `protected static $_is_manager = false`); instance wrappers (`public function boot_hook(): void` delegating to `static::boot()`, `public function bust_cache_hook(): void` delegating to `static::bust_cache()`); static `boot(): void` stub with PATH A/B branch using `is_manager_rest_request()`; `public static function is_manager_rest_request(): bool` using URI-only detection (no REQUEST_METHOD gate — SEC-PLAN-001): checks `WP_CLI`, `wp_doing_cron()`, `wp_doing_ajax()`, then `strpos( $uri, '/' . rest_get_url_prefix() . '/acrossai-abilities/' )`; `private static function load_overrides_cache(): void` calling `AcrossAI_Sitewide_Query::instance()->get_all_overrides()` with `is_array()` guard on transient output (SEC-PLAN-003); `public static function bust_cache(): void` calling `delete_transient()` and setting `self::$_overrides_cache = null`; stub bodies for `inject_override_args()` and `unregister_blocked_abilities()`.
- [ ] T005 Wire processor in `includes/Main.php::define_public_hooks()`: add `$override_processor = AcrossAI_Ability_Override_Processor::instance();` then `$this->loader->add_action( 'plugins_loaded', $override_processor, 'boot_hook', 20 );` and `$this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );` (named variable before Loader calls — Boot Flow Rule; SEC-PLAN-002).

**Checkpoint**: Class exists, hooks are wired, `is_manager_rest_request()` returns correct bool. User story implementation can now begin.

---

## Phase 3: User Story 1 — Admin-Saved Overrides Take Effect at Runtime (P1) 🎯 MVP

**Goal**: Non-null DB override values are injected into each ability's arguments before registration completes, on all non-Manager requests.

**Independent Test**: Save a field override (`readonly = true`) for any registered ability in the Manager. Then call `wp_get_ability('slug')` from WP-CLI or a test plugin on a PATH B request. Verify the returned `readonly` matches the saved value.

- [ ] T006 [US1] Implement `public static function inject_override_args( array $args, string $slug ): array` in `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`: call `self::load_overrides_cache()` to ensure cache is populated; return `$args` unchanged if no row for `$slug`; for each non-null scalar override field (`site_allowed`, `readonly`, `show_in_mcp`) overwrite the matching `$args` key; for `mcp_servers`: if `is_string( $row->mcp_servers )` decode with `json_decode( $row->mcp_servers, true )` and assign to `$args['meta']['mcp_servers']` only if result is array (FR-009); skip null values to preserve Inherit semantics (FR-006); register this method as the `wp_register_ability_args` filter at P10 with `$accepted_args = 2` inside `boot()`.

---

## Phase 4: User Story 2 — Disabled Abilities Are Completely Hidden (P1)

**Goal**: Abilities with `site_allowed = false` are completely removed from the WP registry after all abilities have registered, on all non-Manager requests.

**Independent Test**: Set `site_allowed = false` for any registered ability. On the next non-Manager request, call `wp_get_ability('slug')`. Verify it returns null. Verify `wp_get_abilities()` does not include it.

- [ ] T007 [US2] Implement `public static function unregister_blocked_abilities(): void` in `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`: call `self::load_overrides_cache()` to get the row map (already in-memory from filter phase); iterate all rows; call `wp_unregister_ability( $slug )` for each row where `$row->site_allowed === false`; register this method at `wp_abilities_api_init` P100001 inside `boot()` (after all plugin registrations complete).

---

## Phase 5: User Story 3 — Manager UI Is Not Affected by Override Injection (P1)

**Goal**: All requests to the Manager's own REST namespace (`acrossai-abilities/`) skip override injection entirely (PATH A), so the Manager always shows pure WP registry values for the `_registry` layer.

**Independent Test**: Load the Abilities Manager admin page. For any ability with a DB override, verify the `_registry` column shows the original registration-time value — not the merged override value.

- [ ] T008 [US3] Verify the PATH A branch in `boot()` inside `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`: confirm `if ( self::is_manager_rest_request() ) { return; }` prevents both the `wp_register_ability_args` filter and the `wp_abilities_api_init` action from being added; add inline comment `// FR-003 / SEC-PLAN-001: PATH A — Manager REST skips override injection entirely.`
- [ ] T009 [P] [US3] Create `tests/phpunit/sitewide/AbilityOverrideProcessorTest.php` (namespace `AcrossAI_Abilities_Manager\Tests\PHPUnit\Sitewide`) with PHPUnit test cases: (1) Manager GET request URI returns `true` from `is_manager_rest_request()` — SEC-PLAN-001; (2) `WP_CLI` context returns `false`; (3) non-Manager REST URI returns `false`; (4) non-array transient output is treated as cache miss (`is_array()` guard — SEC-PLAN-003); (5) `boot_hook()` delegates to `static::boot()` and `bust_cache_hook()` delegates to `static::bust_cache()` — SEC-PLAN-002.

---

## Phase 6: User Story 4 — Override Cache Invalidates When Overrides Change (P2)

**Goal**: Saving or resetting an override through the Manager immediately clears the override cache so the very next non-Manager request sees the updated values.

**Independent Test**: Save an override, make a non-Manager ability call (populates cache), then reset the override. On the very next non-Manager call, verify the field reverts to the registration-time default without a manual cache flush.

- [ ] T010 [US4] Add `use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Ability_Override_Processor;` to `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php`, then add `AcrossAI_Ability_Override_Processor::bust_cache();` inside the `if ( $deleted )` block in `delete_override()` — W-001 resolution (delete path fires no `acrossai_abilities_sitewide_after_save` hook).
- [ ] T011 [P] [US4] Add `use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Ability_Override_Processor;` to `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php`, then add `AcrossAI_Ability_Override_Processor::bust_cache();` for the `reset` branch in `bulk_action()` — after `delete_override_by_slug()` returns for `reset` action (W-001 resolution — reset path fires no `acrossai_abilities_sitewide_after_save` hook).

---

## Phase 7: Polish & Validation

**Purpose**: Confirm all quality gates pass before marking the feature complete.

- [ ] T012 Run PHPCS on all modified files: `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`, `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php`, `includes/Main.php`, `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php`, `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php`. Fix all errors before proceeding.
- [ ] T013 Run PHPStan level 8 on all modified files — verify no type error on Loader wiring (Loader `$component` is `object`, satisfied by singleton instance — SEC-PLAN-002). Fix any type errors before proceeding.
- [ ] T014 Run PHPUnit `tests/phpunit/sitewide/AbilityOverrideProcessorTest.php` — all 5 test cases must pass (SEC-PLAN-001 GET detection, SEC-PLAN-002 wrapper delegation, SEC-PLAN-003 transient guard).
- [ ] T015 Review implementation diff for durable memory candidates before marking the feature complete — propose updates to `docs/memory/ARCHITECTURE.md`, `docs/memory/DECISIONS.md`, and `docs/memory/BUGS.md` for W-001 and the singleton+wrapper pattern.

---

## Dependencies

```
T001 → T002 → T003 → T004 → T005
                              ↓
             T006 (US1) → T008 (US3)   T007 (US2) → T008 (US3)
                                            T009 [P] (US3 tests — parallel with T008)
             T010 (US4) [P with T011]   T011 [P] (US4)
                              ↓
                    T012 → T013 → T014 → T015
```

### User Story Completion Order

| Story | Priority | Depends on | Independently Testable |
|---|---|---|---|
| US1 (Override Injection) | P1 | Phase 2 complete | ✅ via `wp_get_ability()` on PATH B |
| US2 (Block Abilities) | P1 | Phase 2 complete | ✅ via `wp_get_abilities()` on PATH B |
| US3 (Manager Bypass) | P1 | US1 + US2 complete | ✅ via Manager admin page `_registry` column |
| US4 (Cache Bust) | P2 | Phase 2 complete (T010/T011 independent) | ✅ via save + reset + read cycle |

### Parallel Execution Opportunities

- T003, T004, T005 must run sequentially (T004 depends on T003's return type; T005 depends on T004's class existing)
- T006 (US1) and T007 (US2) are in the same file — run sequentially to avoid edit conflicts
- T009 (US3 tests) can run in parallel with T008 (same story, different concern — test file vs implementation verify)
- T010 (Override Controller) and T011 (Bulk Controller) are in different files — fully parallel
- T012, T013, T014 must run sequentially (PHPStan after PHPCS; PHPUnit after PHPStan)

### MVP Scope

US1 + US2 + US3 (T003–T009) deliver the complete feature value. US4 (T010–T011) adds cache correctness and should follow immediately — without it, the cache can go stale for up to 12h after admin resets. All stories are P1 or P2; no reasonable deferred scope.
