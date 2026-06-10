---
description: "Task list for Feature 029 — MCP Tools Pass-through"
---

# Tasks: MCP Tools Pass-through (Feature 029)

**Input**: Design documents from `specs/029-mcp-tools-passthrough/`
**Prerequisites**: plan.md ✅, spec.md ✅, memory-synthesis.md ✅

**Tests**: PHPUnit unit tests are explicitly requested in plan.md for Schema/Row/Query/Sanitizer/Formatter patches and the new filter module. No Jest tests (matches Feature 027 accepted pattern — the React cell is a thin dispatch call).

**Organization**: Tasks are grouped by user story. All four user stories (US1–US3 P1, US4 P2) depend on the Foundational BerlinDB plumbing phase.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no blocking dependencies)
- **[Story]**: Which user story this task belongs to (US1–US4)

---

## Phase 1: Setup (New Module Skeleton)

**Purpose**: Create the new module directory and file stub so autoload registration and parallel Foundational work can proceed.

- [x] T001 Create `includes/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough.php` with `defined('ABSPATH') || exit`, full `@package`/`@subpackage` file header, `namespace AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough`, class declaration, `protected static $instance = null`, private constructor, and public static `instance(): self` method (singleton skeleton only — `inject_tools()` body added in Phase 3)

---

## Phase 2: Foundational — BerlinDB Plumbing

**Purpose**: Add `pass_as_tool` tri-state column through all DB layers. **ALL user stories are blocked until this phase is complete.**

**⚠️ CRITICAL**: No user story implementation can begin until T007–T012 are complete. PHPUnit tests (T002–T006) are written first and must FAIL before the matching implementation task is started.

### PHPUnit Tests — Write First, Ensure Fail

- [x] T002 [P] Write `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Schema_PassAsTool_Test.php` — assert `pass_as_tool` appears in `get_columns()` with `type=tinyint`, `allow_null=true`, `default=null`, and no `primary` key
- [x] T003 [P] Write `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Row_PassAsTool_Test.php` — assert `$pass_as_tool` property defaults to `null`, is cast via `cast_tri_state()` in the constructor `$tri_state_fields` list, and `'pass_as_tool'` appears in `get_json_fields()` blocklist
- [x] T004 [P] Write `tests/phpunit/Utilities/AcrossAI_Abilities_Sanitizer_PassAsTool_Test.php` — assert `'pass_as_tool'` is present in the `$tri_state_fields` array for both `sanitize_create_request()` and `sanitize_update_request()`; also assert that malformed inputs (arrays, floats, out-of-range integers) are normalized to `null` or a valid tri-state value by `cast_tri_state()` — not passed through raw (TSEC-T02)
- [x] T005 [P] Write `tests/phpunit/Utilities/AcrossAI_Abilities_Formatter_PassAsTool_Test.php` — assert `'pass_as_tool'` key is present in output arrays from `format_for_response()`, `format_for_exposure()`, and `format_merged_ability()`
- [x] T006 [P] Write `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_PassAsTool_Test.php` — assert `get_pass_as_tool_slugs()` returns only the slugs of rows where `pass_as_tool = 1`, returns an empty array when no rows are opted in, and uses `'number' => 0` (not `-1`) in the BerlinDB query

### Implementation — BerlinDB Patches

- [x] T007 [P] Add `pass_as_tool` column definition to `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` after the `show_in_mcp` column array: `name=pass_as_tool`, `type=tinyint`, `length=1`, `allow_null=true`, `default=null` — no `'primary' => true` key (BUG-BERLINDB-V3-DOUBLE-PRIMARY guard)
- [x] T008 [P] Add to `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php`: `@property bool|null $pass_as_tool` docblock entry, `public $pass_as_tool = null` property declaration, `'pass_as_tool'` added to `$tri_state_fields` in the constructor (~L284), and `'pass_as_tool'` added to `get_json_fields()` blocklist (~L235)
- [x] T009 [P] Add `'pass_as_tool'` to the `$tri_state_fields` array inside both `sanitize_create_request()` (~L296) and `sanitize_update_request()` (~L322) in `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` (this is `AcrossAI_Abilities_Sanitizer`, not the base `AcrossAI_Sanitizer` — ARCH-SANITIZER-TWO-CLASS)
- [x] T010 [P] Add `pass_as_tool` to all three insertion points in `includes/Utilities/AcrossAI_Abilities_Formatter.php`: `'pass_as_tool' => $row->pass_as_tool` in `format_for_response()` (~L49); `'pass_as_tool' => $merged['pass_as_tool'] ?? null` in both `format_for_exposure()` (~L90) and `format_merged_ability()` (~L139)
- [x] T011 Add `'pass_as_tool'` to the `$tri_state` array inside `prepare_fields_for_write()` (~L619) in `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` so `bool true/false` casts cleanly to DB `1`/`0`
- [x] T012 Add `get_pass_as_tool_slugs(): array` method to `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` adjacent to `by_mcp_type()` (~L469): query with `'pass_as_tool' => 1`, `'fields' => 'ability_slug'`, `'number' => 0`; return `array_values(array_filter((array) $rows))` (BUG-BERLINDB-UNLIMITED: `0` = no LIMIT, never `-1`); add inline comment on the `'pass_as_tool' => 1` filter value: "value 0 (explicit deny) excluded intentionally — currently equivalent to NULL at the filter layer; reserved for future per-server deny semantics" (TSEC-T05)

**Checkpoint**: All DB layer changes complete. REST round-trip `GET/POST pass_as_tool` should work. PHPUnit tests T002–T006 should now pass when the bootstrap is available.

---

## Phase 3: User Story 1 — Opt an ability into every MCP server (Priority: P1) 🎯 MVP

**Goal**: Admin clicks the "Pass as Tool" toggle in the Abilities list and the opted-in ability slug appears in every MCP server's tool list on the next resolution.

**Independent Test**: Call `apply_filters('mcp_adapter_server_config', ['tools' => ['existing/slug']], 'test-server')` with one ability flagged `pass_as_tool = 1` — result must contain both `'existing/slug'` and the flagged slug, with no duplicates. With zero flagged abilities the result must equal the input config byte-for-byte.

### PHPUnit Test — Write First, Ensure Fail

- [x] T013 [US1] Write `tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php` — assert: (a) opted-in slugs are merged into `$config['tools']` with `array_unique`; (b) a non-array `$config['tools']` value falls back to empty array and the result is a flat array of opted-in slugs only; (c) when no abilities are opted in, the returned config is identical to the input (early-return path) — **(depends on T001 class stub existing before this test file can reference the class; TSEC-T03)**

### Implementation

- [x] T014 [US1] Implement `inject_tools(array $config, string $server_id): array` in `includes/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough.php`: call `AcrossAI_Abilities_Query::instance()->get_pass_as_tool_slugs()` (**NOT** `new AcrossAI_Abilities_Query()` — the constructor is private; see ARCH-REFACTOR-001), early-return if empty, `is_array()` guard on `$config['tools']`, merge via `array_values(array_unique(array_merge($existing, $extra)))`, return `$config`
- [x] T015 [US1] Wire the filter in `includes/Main.php` inside `define_public_hooks()`: `$mcp_tools_passthrough = \AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough\AcrossAI_Mcp_Tools_Passthrough::instance();` then `$this->loader->add_filter('mcp_adapter_server_config', $mcp_tools_passthrough, 'inject_tools', 10, 2)` — Boot Flow Rule: singleton must be resolved into a named variable before being passed to the Loader (AC-HOOKS-MAIN); add adjacent comment: "mcp_adapter_server_config assembles the server tool list; mcp_adapter_expose_ability (if used) operates at a different enforcement layer — pass-as-tool injection does not bypass per-server mcp_adapter_expose_ability restrictions" (TSEC-T04)
- [x] T016 [US1] Run `composer dump-autoload` from the plugin root to register `AcrossAI_Mcp_Tools_Passthrough` in the autoload classmap
- [x] T017 [US1] Add to `src/js/abilities/components/AbilitiesList.jsx`: (1) `'pass_as_tool'` in `COLUMN_DEFAULTS` array (~L150); (2) `PassAsToolCell` function component with `isOn = item.pass_as_tool === true`, `Button` variant toggle, `aria-label` with `__()`, `disabled` prop; (3) column definition with `id: 'pass_as_tool'`, header `__('Pass as Tool', ...)`, `render` calling `PassAsToolCell` with `disabled={PROTECTED_SLUGS.includes(item.ability_slug)}`, `onToggle` dispatching `updateAbility` with `{ pass_as_tool: nextValue }` and `createErrorNotice` on failure; (4) `enableHiding: true`, `enableSorting: false` — no `0` state in v1 UI, only `null`/`true`
- [x] T018 [US1] Run `npm run build` to regenerate `build/js/abilities.js` and `build/js/abilities.asset.php`

**Checkpoint**: User Story 1 is fully functional. Admin can toggle any non-protected ability. The filter injects opted-in slugs into MCP server tool lists. Zero-impact when nothing is opted in (US2 is covered by the `empty($extra)` early-return in T014).

---

## Phase 4: User Story 2 — Zero-impact on unmodified abilities and servers (Priority: P1)

**Goal**: Sites with zero opted-in abilities see MCP server tool lists that are byte-for-byte identical to pre-feature behavior.

**Independent Test**: With no rows having `pass_as_tool = 1`, call `apply_filters('mcp_adapter_server_config', $original_config, 'any-server')` — result must equal `$original_config` with no modifications.

> **Implementation status**: No new code required. The `empty($extra) → return $config` early-return in `inject_tools()` (T014) covers this entirely.

- [ ] T019 [US2] Manual validation: with no abilities flagged, confirm `(new AcrossAI_Abilities_Query())->get_pass_as_tool_slugs()` returns `[]` and `apply_filters('mcp_adapter_server_config', $config, 'test')` returns the original config unchanged — run validation checklist item "When no rows are flagged, the filter returns `$config` byte-for-byte unchanged"

**Checkpoint**: US2 acceptance scenario confirmed. No regression to existing MCP tool lists.

---

## Phase 5: User Story 3 — Protected system abilities cannot be opted in (Priority: P1)

**Goal**: Protected abilities have a disabled toggle in the Abilities list, and any API attempt to set their `pass_as_tool` flag is rejected with 403.

**Independent Test**: (a) View Abilities list — protected row's Pass as Tool toggle is rendered with `disabled` state and cannot be activated. (b) `POST /wp-json/acrossai-abilities-manager/v1/abilities/{mcp-adapter/protected-slug}` with `{ "pass_as_tool": true }` returns 403.

> **Implementation status**: No new code required. API guard is `AcrossAI_Abilities_Write_Controller::update_ability()` L308 — slug-level, strict `in_array(..., true)`, fires before `sanitize_update_request()` (SEC-001 resolved). UI disabled state is `disabled={PROTECTED_SLUGS.includes(item.ability_slug)}` wired in T017.

- [ ] T020 [US3] Verify in `src/js/abilities/components/AbilitiesList.jsx` (T017 output) that the `PassAsToolCell` `disabled` prop is `PROTECTED_SLUGS.includes(item.ability_slug)` — confirm no duplicate protected-slug list (must reference the central `PROTECTED_SLUGS` constant per DEC-PROTECTED-SLUGS-PATTERN)
- [ ] T021 [US3] REST validation: send `POST /wp-json/acrossai-abilities-manager/v1/abilities/{protected-slug}` with `{ "pass_as_tool": true }` and confirm the response is 403 `rest_protected_ability` — this exercises the existing guard at `AcrossAI_Abilities_Write_Controller::update_ability()` L308; no code change required; also confirm `check_permission()` enforces `manage_options` (or the documented equivalent) as the required capability for the write endpoint, and add a one-line comment in `includes/Main.php` adjacent to T015's filter wire naming the required capability (TSEC-T01)

**Checkpoint**: US3 acceptance scenarios confirmed. Protected abilities are guarded at both UI and API layers.

---

## Phase 6: User Story 4 — Flag persists across routine plugin lifecycle events (Priority: P2)

**Goal**: Pass-as-tool opt-ins survive plugin deactivate/reactivate (without dropping the table).

**Independent Test**: Opt in two abilities → deactivate plugin → reactivate without dropping the table → confirm both abilities are still opted in and appear in MCP server tool lists.

- [x] T022 [US4] Manual DB activation flow: deactivate plugin → drop `wp_acrossai_abilities` → reactivate → run `DESCRIBE wp_acrossai_abilities` and confirm `pass_as_tool tinyint(1) DEFAULT NULL` column is present; run `SHOW CREATE TABLE wp_acrossai_abilities` and confirm PRIMARY KEY is declared exactly once (BUG-BERLINDB-V3-DOUBLE-PRIMARY validation)
- [ ] T023 [US4] Manual persistence test: set `pass_as_tool = 1` on two abilities → deactivate plugin → reactivate without dropping table → confirm via `GET /wp-json/acrossai-abilities-manager/v1/abilities/{slug}` that both abilities still return `"pass_as_tool": true`

**Checkpoint**: All four user stories are independently functional and verified.

---

## Phase 3b: Edit-Form Coverage (speckit-analyze finding U1)

**Why added**: `/speckit-analyze` identified that every other tri-state field appears in `AbilityForm.jsx` but `pass_as_tool` did not, causing a discoverability gap (reported by user on first use).

- [x] T029 Add `pass_as_tool` TriChips control to `src/js/abilities/components/AbilityForm.jsx` MCP section (after Show in MCP chip); add `pass_as_tool: data.pass_as_tool` to non-db `overrideData` save path; add `disabled` prop support to `TriChips` component; protected slugs gate via `abilitiesConfig.protected_slugs`
- [x] T030 Run `npm run build` to regenerate `build/js/abilities.js` after AbilityForm changes

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Durable memory updates, Constitution bump, and full quality gate.

- [ ] T024 [P] Append `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` decision block to `docs/memory/DECISIONS.md` (see plan.md CHANGE-9 for exact text)
- [ ] T025 [P] Add `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` routing row to the active-decisions table in `docs/memory/INDEX.md` (see plan.md CHANGE-9 for exact row format)
- [ ] T026 [P] Update `.specify/memory/CONSTITUTION.md`: bump version `1.4.5 → 1.4.6` in the footer, add `McpToolsPassthrough/` to the Directory Layout module list under `includes/Modules/`, and update the SYNC IMPACT REPORT HTML comment at the top (see plan.md CHANGE-10 for exact text)
- [ ] T027 Run full quality gate: `composer phpcs` and `composer phpstan` (level 8) for all touched PHP files; `npm run build` to verify build succeeds and `build/js/abilities.asset.php` is regenerated; `npm run validate-packages` (Constitution §VI + §VII DoD — required before every commit); confirm ESLint clean for `AbilitiesList.jsx`; confirm Plugin Check clean
- [ ] T028 Run all validation checklist items from plan.md: schema describe, REST round-trip (`GET`, `POST true`, `POST null`), protected-slug 403, `get_pass_as_tool_slugs()` return value, filter integration (union, no-op, non-array fallback), admin UI toggle, column visibility localStorage persistence

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 completion — **BLOCKS all user stories**
- **Phase 3 (US1)**: Depends on Phase 2 completion — all BerlinDB plumbing must be in place
- **Phase 4 (US2)**: Depends on Phase 3 (T014 provides the `empty($extra)` early-return); validation only
- **Phase 5 (US3)**: Depends on Phase 3 (T017 provides the `disabled` UI prop); validation only
- **Phase 6 (US4)**: Depends on Phase 2 (DB schema must be deployed)
- **Phase 7 (Polish)**: Depends on Phases 3–6 completion

### User Story Dependencies

- **US1 (P1)**: Can start after Foundational (Phase 2) — core implementation story
- **US2 (P1)**: No-code story; validation only after US1 is complete
- **US3 (P1)**: No-code story; validation only after US1 UI is complete
- **US4 (P2)**: Independent of US1–US3 once the DB schema is deployed; requires Phase 2 only

### Within Phase 2 — Parallel Opportunities

T002–T010 (tests + Schema/Row/Sanitizer/Formatter patches) are all in **different files** and can run fully in parallel. T011 and T012 are both in `AcrossAI_Abilities_Query.php` — run T011 then T012 sequentially (same file); they are parallel to T002–T010.

---

## Parallel Example: Phase 2 Foundational

```
# All of these can run simultaneously (different files):
T002  tests/phpunit/…/AcrossAI_Abilities_Schema_PassAsTool_Test.php
T003  tests/phpunit/…/AcrossAI_Abilities_Row_PassAsTool_Test.php
T004  tests/phpunit/…/AcrossAI_Abilities_Sanitizer_PassAsTool_Test.php
T005  tests/phpunit/…/AcrossAI_Abilities_Formatter_PassAsTool_Test.php
T006  tests/phpunit/…/AcrossAI_Abilities_Query_PassAsTool_Test.php
T007  includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php
T008  includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php
T009  includes/Utilities/AcrossAI_Abilities_Sanitizer.php
T010  includes/Utilities/AcrossAI_Abilities_Formatter.php

# Then sequentially (same file):
T011 → T012  includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php
```

## Parallel Example: Phase 3 User Story 1

```
# Write and fail test first:
T013  tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php

# Then implement (T014 must precede T015 — inject_tools() must exist before Main.php wires it):
T014 → T015 → T016  PHP filter implementation + autoload
T017 → T018         JSX toggle + npm build  (parallel to T014–T016 once T001/T012 done)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Test filter integration manually and confirm UI toggle saves
5. US2 and US3 validations are zero-code; run them immediately after US1

### Incremental Delivery

1. Phase 1 + Phase 2 → DB plumbing ready; REST responses include `pass_as_tool`
2. Phase 3 → MCP injection live + UI toggle working → **MVP shipped**
3. Phase 4 + Phase 5 → US2/US3 validated (no code delta)
4. Phase 6 → US4 lifecycle persistence confirmed
5. Phase 7 → Quality gate + memory updates → PR ready

---

## Notes

- `[P]` tasks operate on different files — safe to parallelize
- PHPUnit tests must be written first (TDD) and verified to FAIL before the matching implementation task begins
- PHPUnit bootstrap is currently blocked (see ARCHITECTURE.md T014 pre-existing gap) — tests can be written but not executed until the bootstrap shim is added; this does not block feature shipping
- No Jest tests for `PassAsToolCell` (matches Feature 027 accepted pattern — the cell is a thin dispatch call)
- `composer dump-autoload` (T016) must run before any WordPress request loads the new class
- `npm run build` (T018) must run before the new UI column appears in the browser
- Never use `'' !== (string) $value` to guard `pass_as_tool` off a Row — use `null !== $value` only (BUG-MERGER-BOOL-STRING-CAST)
- `pass_as_tool = 0` (explicit deny) is stored by the sanitizer but not injected — `get_pass_as_tool_slugs()` queries `= 1` only; this state is reserved for future use
