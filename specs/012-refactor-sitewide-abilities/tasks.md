# Tasks: Refactor Sitewide Module into Abilities Module

**Input**: Design documents from `/specs/012-refactor-sitewide-abilities/`
**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md) | **Security**: [security-constraints.md](./security-constraints.md)
**Branch**: `012-refactor-sitewide-abilities`

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[US1]**: User Story 1 — Plugin Continues to Work After Module Consolidation
- **[US2]**: User Story 2 — Decommissioned API Routes Return "Not Found"
- **[US3]**: User Story 3 — Codebase Has a Single Module for Abilities Logic

---

## Phase 1: Setup

**Purpose**: Establish baseline and confirm pre-conditions before any file changes

- [x] T001 Capture baseline: run `grep -rn "AcrossAI_Sitewide" includes/ admin/ --include="*.php"` and record file list to confirm expected scope before implementation begins

---

## Phase 2: Foundational — New Abilities Database Classes

**Purpose**: Create the three renamed DB class files that everything else depends on. No logic changes — pure namespace/class renames with updated file headers.

**⚠️ CRITICAL**: All User Story implementation depends on these three files. Complete and verify before beginning Phase 3.

- [x] T002 Create `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php` — copy from `AcrossAI_Sitewide_Table.php`, rename class to `AcrossAI_Abilities_Table`, namespace to `AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database`, update `$schema` → `AcrossAI_Abilities_Schema::class`, preserve `protected $global = false` (SEC-03), preserve soft singleton without `private __construct()` (DEC-TABLE-SOFT-SINGLETON), update `@subpackage` header
- [x] T003 [P] Create `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` — copy from `AcrossAI_Sitewide_Schema.php`, rename class to `AcrossAI_Abilities_Schema`, namespace to `AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database`, update `@subpackage` header; no logic changes
- [x] T004 [P] Create `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php` — copy from `AcrossAI_Sitewide_Row.php`, rename class to `AcrossAI_Abilities_Row`, namespace to `AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database`, update `@subpackage` header, preserve `get_json_fields()` static method; no logic changes

**Checkpoint**: Three new files exist at `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php`, `AcrossAI_Abilities_Schema.php`, `AcrossAI_Abilities_Row.php`. Run `php -l` on each to confirm no parse errors.

---

## Phase 3: User Story 1 — Plugin Continues to Work After Module Consolidation (Priority: P1) 🎯 MVP

**Goal**: All plugin functionality — override persistence, enforcement, admin UI, and activation — continues to work identically after the refactor.

**Independent Test**: Activate the refactored plugin, visit the Abilities Manager admin page, create an ability override, verify it persists and enforcement applies on a non-admin request.

### Implementation for User Story 1

- [x] T005 [US1] Update `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` — sub-changes 4a–4d: (a) replace both Sitewide `use` statements with `use ...Abilities\Database\AcrossAI_Abilities_Schema` and `use ...Abilities\Database\AcrossAI_Abilities_Row`; (b) update `$table_schema = AcrossAI_Abilities_Schema::class` and `$item_shape = AcrossAI_Abilities_Row::class`; (c) update docblock — remove "reused, no duplication" comment, document self-contained Abilities ownership (deliberate supersession per spec clarification Q3); (d) update all existing method return types and closure parameter types from `AcrossAI_Sitewide_Row` → `AcrossAI_Abilities_Row` in `get_ability_by_id()`, `get_ability_by_slug()`, `by_source()` closures, `get_paginated()` closures
- [x] T006 [US1] Port 4 override CRUD methods to `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` — copy from `AcrossAI_Sitewide_Query.php` and update: (1) `get_override_by_slug(string $slug): ?AcrossAI_Abilities_Row` — add `$slug = AcrossAI_Sanitizer::sanitize_ability_slug($slug)` as first statement (SC-IMPL-002, SEC-01), add AUTHORIZATION CONTRACT docblock (SC-IMPL-004); (2) `save_override(string $slug, array $fields): bool` — add `$slug = AcrossAI_Sanitizer::sanitize_ability_slug($slug)` as first statement (SC-IMPL-002, SEC-01); preserve `$max_json_bytes = 65536` JSON guard (DEC-JSON-SIZE-GUARD), all enum guards (status/callback_type/mcp_type), tri-state bool→int cast, update `AcrossAI_Sitewide_Row::get_json_fields()` → `AcrossAI_Abilities_Row::get_json_fields()`, add AUTHORIZATION CONTRACT docblock; (3) `delete_override_by_slug(string $slug): bool` — add `sanitize_ability_slug()` first statement (SC-IMPL-002), add AUTHORIZATION CONTRACT docblock; (4) `get_all_overrides(): array` — use `'number' => 0` NOT -1 (BUG-BERLINDB-UNLIMITED), add AUTHORIZATION CONTRACT docblock. Add `use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer` if not already present
- [x] T007 [P] [US1] Create `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` — copy from `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php`, update namespace to `AcrossAI_Abilities_Manager\Includes\Modules\Abilities`, update any Sitewide DB class `use` statements to Abilities equivalents, update `@subpackage` header to `AcrossAI_Abilities_Manager/includes/Modules/Abilities`; preserve `boot()` conditional PATH-A/PATH-B hook wiring verbatim (ARCH-ADV-001); preserve override cache mechanism without modification
- [x] T008 [P] [US1] Create `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` — copy from `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php`, rename class to `AcrossAI_Abilities_Access_Control`, update namespace to `AcrossAI_Abilities_Manager\Includes\Modules\Abilities`, update any Sitewide DB class `use` statements to Abilities equivalents, update `@subpackage` header; confirm all `===` / `!==` comparisons are preserved with no loose `==` in capability or null checks (SEC-04, SC-IMPL-005)
- [x] T009 [P] [US1] Update `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` — replace `use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row` with `use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Row`; update all `AcrossAI_Sitewide_Row` type hints and docblock references to `AcrossAI_Abilities_Row`
- [x] T010 [P] [US1] Update `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php` — replace `use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Query` with `use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query`; update all `AcrossAI_Sitewide_Query` type hints to `AcrossAI_Abilities_Query`
- [x] T011 [P] [US1] Update `includes/Utilities/AcrossAI_Ability_Registry_Query.php` — replace `use` for `AcrossAI_Sitewide_Query` with `use ...Abilities\Database\AcrossAI_Abilities_Query`; update all method parameter type hints from `AcrossAI_Sitewide_Query` → `AcrossAI_Abilities_Query`; verify `get_override_by_slug()` call site resolves on `AcrossAI_Abilities_Query` (method ported in T006)
- [x] T012 [P] [US1] Update `includes/Utilities/AcrossAI_Abilities_Formatter.php` — replace `use` for `AcrossAI_Sitewide_Row` with `use ...Abilities\Database\AcrossAI_Abilities_Row`; update all 4 method parameter type hints from `AcrossAI_Sitewide_Row` → `AcrossAI_Abilities_Row`; preserve static-only class structure
- [x] T013 [US1] Update `includes/AcrossAI_Activator.php` — replace `use ...Sitewide\Database\AcrossAI_Sitewide_Table` with `use ...Abilities\Database\AcrossAI_Abilities_Table`; update instantiation `new AcrossAI_Sitewide_Table()` → `new AcrossAI_Abilities_Table()` (must work via `new` per DEC-TABLE-SOFT-SINGLETON — no `private __construct()` on Table)
- [x] T014 [US1] Update `includes/Main.php` sub-changes 11a + 11c + 11d: (11a) replace Sitewide_Table `use` and `::instance()` wiring with `AcrossAI_Abilities_Table` named variable; (11c) replace `AcrossAI_Sitewide_Access_Control` `use` and `::instance()` wiring with `AcrossAI_Abilities_Access_Control` named variable; (11d) REMOVE `add_action('acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook')` and ADD three wirings: `add_action('acrossai_abilities_after_create', ...)`, `add_action('acrossai_abilities_after_update', ...)`, `add_action('acrossai_abilities_after_delete', ...)` — CRITICAL-01 fix (SC-IMPL-001). All four changes must use named variable before `$this->loader->add_action()` (AC-HOOKS-MAIN)

**Checkpoint**: At this point, all override CRUD functionality should work end-to-end through the Abilities module. The Sitewide module still exists but its logic has been superseded.

---

## Phase 4: User Story 2 — Decommissioned API Routes Return "Not Found" (Priority: P2)

**Goal**: All `/sitewide/*` REST endpoints return 404. No Sitewide REST routes are registered. Existing Abilities endpoints are unchanged.

**Independent Test**: Call `GET /wp-json/acrossai-abilities-manager/v1/sitewide/abilities` and verify 404. Verify `/abilities` and `/abilities/{slug}` return unchanged correct responses.

### Implementation for User Story 2

- [x] T015 [US2] Update `includes/Main.php` sub-change 11b — remove `use` statement for `AcrossAI_Sitewide_Rest_Controller` and remove all `$this->loader->add_action('rest_api_init', ..., 'register_routes')` calls for the Sitewide REST orchestrator and any directly-wired Sitewide sub-controllers
- [x] T016 [US2] Verify SC-IMPL-006 pre-deletion gate: run `grep -rn "acrossai_abilities_sitewide_after_save" includes/ admin/ --include="*.php"` — must return zero results before proceeding to deletion
- [x] T017 [P] [US2] Delete `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php`
- [x] T018 [P] [US2] Delete `includes/Modules/Sitewide/Rest/` directory and all contents: `AcrossAI_Sitewide_Abilities_Controller.php`, `AcrossAI_Sitewide_Bulk_Controller.php`, `AcrossAI_Sitewide_Mcp_Controller.php`, `AcrossAI_Sitewide_Override_Controller.php`, `index.php`

**Checkpoint**: After T017–T018, all `/sitewide/` routes should return 404. Run `curl -s -o /dev/null -w "%{http_code}" "http://localhost/wp-json/acrossai-abilities-manager/v1/sitewide/abilities"` → expect 404.

---

## Phase 5: User Story 3 — Codebase Has a Single Module for Abilities Logic (Priority: P3)

**Goal**: `includes/Modules/Sitewide/` directory is absent. Zero Sitewide class references remain in any PHP file. Static analysis passes.

**Independent Test**: Verify `includes/Modules/Sitewide/` is absent, PHPStan reports zero errors, and no `AcrossAI_Sitewide` references remain in the PHP source.

### Implementation for User Story 3

- [x] T019 [US3] Run pre-deletion reference check: `grep -rn "AcrossAI_Sitewide" includes/ admin/ --include="*.php"` — results MUST only be files inside `includes/Modules/Sitewide/` itself; any result in other files is a blocker (fix before continuing)
- [x] T020 [US3] Delete remaining `includes/Modules/Sitewide/` files and directory: `Database/AcrossAI_Sitewide_Table.php`, `Database/AcrossAI_Sitewide_Schema.php`, `Database/AcrossAI_Sitewide_Row.php`, `Database/AcrossAI_Sitewide_Query.php`, `AcrossAI_Ability_Override_Processor.php` (Sitewide version), `AcrossAI_Sitewide_Access_Control.php`, `index.php`, and the `includes/Modules/Sitewide/` directory itself
- [x] T021 [US3] Post-deletion verification: `grep -rn "AcrossAI_Sitewide" includes/ admin/ --include="*.php"` — must return **zero results** (SC-007)
- [x] T022 [P] [US3] Run `composer run phpstan` — must pass at level 8 with zero errors (SC-002); fix any type-mismatch errors before marking complete
- [x] T023 [P] [US3] Run `composer run phpcs` — must pass with zero errors and zero warnings (SC-003); fix any coding standards violations before marking complete

**Checkpoint**: `includes/Modules/` contains only `Abilities/` and `Logger/`. No Sitewide references remain. Static analysis clean.

---

## Phase 6: Polish & Cross-Cutting Verification

**Purpose**: Confirm security constraints and integration-level behaviour before considering the feature done.

- [x] T024 Verify SC-IMPL-007 cache bust wiring: `grep -n "bust_cache_hook" includes/Main.php` — must show exactly 3 active `add_action` lines targeting `acrossai_abilities_after_create`, `acrossai_abilities_after_update`, `acrossai_abilities_after_delete` (zero hits on `sitewide_after_save`)
- [x] T025 Verify SC-IMPL-002 slug sanitization: `grep -n "sanitize_ability_slug" includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` — must return **exactly 3 matches** (one in each of `get_override_by_slug`, `save_override`, `delete_override_by_slug`)
- [ ] T026 Manual smoke test — activate plugin on local WordPress install, navigate to Abilities Manager admin page, create an ability override, verify it persists in the list, send a non-admin request and verify override enforcement applies; confirm no PHP errors or warnings in debug log

---

## Phase 7: Architecture Refactor Tasks (Non-Blocking, Post-Implementation)

**Purpose**: Resolve architecture drift in memory and constitution documents made stale by the Sitewide decommission. All tasks are P2–P3 non-blocking; schedule after Change 13 completes.

**Source**: Identified by `/speckit.architecture-guard.refactor-generator` during governed-tasks review (2026-05-24).

- [ ] T030 [P2] Write unit tests for the 4 ported override CRUD methods in `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` — create `tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_Override_Test.php`; tests MUST cover: (a) `get_override_by_slug()` calls `sanitize_ability_slug()` before DB query; (b) `save_override()` rejects payloads exceeding 65536 bytes in JSON fields; (c) `delete_override_by_slug()` calls `sanitize_ability_slug()` before delete; (d) `get_all_overrides()` returns all rows without a LIMIT. **Architectural reason**: CONSTITUTION.md §VII Definition of Done requires unit tests for all new logic; the `sanitize_ability_slug()` first-statement is new behavior added during the port that must be regression-tested.
- [x] T027 [P3] Update `.specify/memory/CONSTITUTION.md` directory layout — remove `Sitewide/` entry from the `includes/Modules/` tree (line 143), remove `AcrossAI_Sitewide_Rest_Controller` from the REST module examples (line 161), remove `includes/Modules/Sitewide/Rest/` directory pattern from the REST controller split section (line 192). **Architectural reason**: Constitution directory layout must reflect the live module structure; stale entries create confusion during future feature planning.
- [x] T028 [P2] Update `docs/memory/ARCHITECTURE.md` — remove all Sitewide module descriptions (lines 21–42: BerlinDB Sitewide classes, REST sub-controllers, Override Processor, Access Control entries under `includes/Modules/Sitewide/`); add updated Abilities module description reflecting the consolidated structure (DB classes, Override Processor, Access Control now under `includes/Modules/Abilities/`). **Architectural reason**: ARCHITECTURE.md is the authoritative module map; stale Sitewide module entries would mislead future developers about active modules.
- [x] T029 [P2] Update `docs/memory/DECISIONS.md` — (a) replace `AcrossAI_Sitewide_Table` with `AcrossAI_Abilities_Table` in the soft-singleton decision canonical example (DEC-TABLE-SOFT-SINGLETON); (b) replace `AcrossAI_Sitewide_Query::save_override()` and `AcrossAI_Sitewide_Query::by_source()` with `AcrossAI_Abilities_Query::*` in the decision examples; (c) update W-001 pattern note — `acrossai_abilities_sitewide_after_save` no longer exists; the new hook wiring pattern uses `acrossai_abilities_after_create`, `acrossai_abilities_after_update`, `acrossai_abilities_after_delete` via Loader in `define_public_hooks()`; (d) add a new entry documenting the supersession of the "reuse Sitewide Schema/Row in Abilities_Query" design decision (DEC-012-SUPERSESSION). **Architectural reason**: DECISIONS.md is the canonical decision registry; stale class names and hook pattern documentation create implementation risk for future features.

---

## Dependencies

```
T002, T003, T004  — parallel, no deps (Phase 2)
T005              — needs T002, T003, T004
T006              — needs T005 (same file)
T007              — needs T006 (references Abilities_Query methods)
T008              — needs T004 (Abilities_Row/Schema types)
T009              — needs T004 (Abilities_Row type)
T010              — needs T005 (Abilities_Query type)
T011              — needs T006 (get_override_by_slug must be ported)
T012              — needs T004 (Abilities_Row type)
T013              — needs T002 (Abilities_Table class)
T014              — needs T002, T007, T008 (Table, Processor, AC instances)
T015              — needs T014 (same file Main.php; sequence for safety)
T016              — needs T015 (verify hook removal before file deletion)
T017, T018        — parallel, both need T016
T019              — needs T017, T018 (REST files deleted; now check remaining refs)
T020              — needs T013, T014, T015, T019 (all bootstrap updated, REST gone, refs confirmed clean)
T021              — needs T020 (post-deletion grep)
T022, T023        — parallel, both need T020
T024              — needs T014, T015 (Main.php fully updated)
T025              — needs T006 (CRUD methods ported)
T026              — needs T022, T023 (all static analysis green)
```

## Parallel Execution Guide

**Round 1 (Foundational — run together)**:
- T002, T003, T004 (three new DB class files, no inter-dependencies)

**Round 2 (Query update — sequential)**:
- T005 → T006 (same file; complete T005 before T006)

**Round 3 (Consumers — run together after T006)**:
- T007, T008, T009, T010, T011, T012 (six different files, all parallel)

**Round 4 (Bootstrap — sequential within Main.php)**:
- T013, T014 can run in parallel (different files)
- T015 after T014 (same file Main.php)

**Round 5 (Deletion — gated)**:
- T016 (gate check), then T017 + T018 (parallel deletion)
- T019 (gate check), then T020 (final deletion)

**Round 6 (Verify — run together)**:
- T021, T022, T023 (parallel), then T024, T025, T026

---

## Implementation Strategy

**MVP**: Complete Phases 1–3 (T001–T014) first. This delivers User Story 1 (all functionality preserved) and is independently testable before any Sitewide code is deleted.

**Incremental delivery**:
1. Phase 2 (T002–T004): DB classes created — PHP parse-check each
2. Phase 3 (T005–T014): All consumers updated, bootstrap rewired — run phpstan
3. Phase 4 (T015–T018): REST routes decommissioned — curl-verify 404
4. Phase 5 (T019–T023): Sitewide directory deleted — static analysis green
5. Phase 6 (T024–T026): Smoke test and security verification complete

**Never delete Sitewide files until T019 (pre-deletion grep) passes cleanly.**
