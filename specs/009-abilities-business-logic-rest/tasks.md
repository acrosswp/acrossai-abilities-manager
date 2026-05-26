# Tasks: Abilities Business Logic and REST API

**Input**: Design documents from `/specs/009-abilities-business-logic-rest/`
**Prerequisites**: plan.md, spec.md, memory-synthesis.md, security-constraints.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: PHPUnit coverage is required for this feature because the specification and plan explicitly call for database/query, REST, validation, and runtime registration testing.

**Organization**: Tasks are grouped by user story so each story can be implemented and validated independently after the shared foundation is complete.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish the Abilities module and test surfaces without creating a second abilities table or any duplicate BerlinDB schema/row/table classes.

- [x] T001 Create the Abilities module and PHPUnit scaffolds in `includes/Modules/Abilities/` and `tests/phpunit/abilities/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Build the shared query and utility layer that all REST and runtime flows depend on.

**⚠️ CRITICAL**: No user story work should begin until this phase is complete.

- [x] T002 Implement unified-table browse, runtime, and exposure query helpers in `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`, reusing `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php` and `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php`, with filtering/search/pagination in the query layer and unlimited fetches using `number => 0`
- [x] T003 [P] Implement static validation for slugs, statuses, categories, schemas, exposure metadata, immutable shared-table write rules, `php_code` token/blocklist hardening, `wp_remote_post` HTTPS/timeout/redirect hardening, and reject-unknown-key rules for all mode-specific callback and exposure payloads in `includes/Utilities/AcrossAI_Abilities_Validator.php`
- [x] T004 [P] Implement static sanitization, sparse-merge, server-controlled field handling, bool-to-int casting, and full-row save/re-read helpers in `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`
- [x] T005 [P] Implement consistent REST item/collection formatting plus nested runtime registry meta builders in `includes/Utilities/AcrossAI_Abilities_Formatter.php`
- [x] T006 [P] Add query coverage for source/status filters, editable filtering, exposure filtering, accurate totals, and unlimited `number => 0` fetches in `tests/phpunit/abilities/AbilitiesQueryTest.php`
- [x] T007 [P] Add validation and sanitization coverage for sparse updates, immutable-field enforcement, reject-unknown-key behavior across `filter_hook`, `php_code`, `wp_remote_post`, and exposure payloads, schema payload size/depth guardrails, and fail-closed server filtering inputs in `tests/phpunit/abilities/AbilitiesValidationTest.php`

**Checkpoint**: Unified-table access, validation, sanitization, and formatting rules are complete and can be reused by every story.

---

## Phase 3: User Story 1 - Manage Database Abilities (Priority: P1) 🎯 MVP

**Goal**: Let administrators create, sparsely update, and delete database-managed ability rows through management endpoints.

**Independent Test**: Create a `source = db` draft ability, sparsely update only a subset of fields, verify untouched fields are preserved, and confirm delete works only for database-managed rows.

### Tests for User Story 1

- [x] T008 [P] [US1] Add create, sparse update, delete, duplicate-slug, invalid-payload, non-db delete protection, and explicit forbidden-path coverage for create/update/delete routes in `tests/phpunit/abilities/AbilitiesWriteControllerTest.php`

### Implementation for User Story 1

- [x] T009 [US1] Implement the thin REST orchestrator and write-route registration in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php` and `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`, keeping the orchestrator limited to namespace, route registration, and shared permission callbacks only
- [x] T010 [US1] Implement create, sparse update, delete, audit-field population, immutable-field matrix enforcement for non-db rows, sanitized before-save payloads, and full saved-row re-reads before after-save hooks in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`
- [x] T011 [US1] Wire the Abilities REST orchestrator only through `includes/Main.php` using named singleton variables so `includes/Main.php` remains the single Loader registration surface

**Checkpoint**: Administrators can manage database-managed abilities through a dedicated API without mutating inherited rows or returning partial save payloads.

---

## Phase 4: User Story 2 - Browse and Filter Abilities (Priority: P2)

**Goal**: Let administrators browse unified abilities with search, pagination, filtering, single-item reads, and category discovery.

**Independent Test**: Request paginated ability collections with search and filters, fetch a single record by ID, fetch categories, and verify totals, page counts, and editability flags are accurate.

### Tests for User Story 2

- [x] T012 [P] [US2] Add browse, single-item, pagination, search, source/status/category/editable filters, category discovery, not-found coverage, and explicit forbidden-path coverage for list/single/categories routes in `tests/phpunit/abilities/AbilitiesReadControllerTest.php`

### Implementation for User Story 2

- [x] T013 [US2] Implement administrator-only list and single-item endpoints in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php` with all filtering/search/pagination delegated to `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` and no post-query business filtering inside the controller
- [x] T014 [US2] Implement the administrator-only categories endpoint in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Category_Controller.php` and complete collection/item pagination formatting in `includes/Utilities/AcrossAI_Abilities_Formatter.php`, preserving the thin REST split and shared permission callback model

**Checkpoint**: Administrators can browse the unified catalog safely and consistently without moving filter logic into REST handlers.

---

## Phase 5: User Story 3 - Register Published Database Abilities at Runtime (Priority: P3)

**Goal**: Publish only valid database-managed abilities into the runtime registry and expose administrator-only discovery collections for published machine-consumable abilities.

**Independent Test**: Publish valid `source = db` abilities, initialize runtime registration, confirm only valid rows register with authenticated-user execution permissions, and verify exposure collections are admin-only and fail closed on unknown server context.

### Tests for User Story 3

- [x] T015 [P] [US3] Add runtime registration, authenticated execution, nested meta payload, `php_code` static-closure wrapping, per-invocation `Throwable` isolation, execution audit logging, `wp_remote_post` HTTPS revalidation, timeout clamping, `redirection => 0`, no caller header/cookie propagation, and fail-closed unknown-server-context coverage in `tests/phpunit/abilities/AbilitiesProcessorTest.php`

- [x] T016 [P] [US3] Add explicit forbidden-path and route-level fail-closed server-context coverage for tool/resource/prompt exposure endpoints in `tests/phpunit/abilities/AbilitiesExposureControllerTest.php`

### Implementation for User Story 3

- [x] T017 [US3] Implement runtime registration and execution-mode mapping for `noop`, `filter_hook`, `php_code`, and `wp_remote_post` in `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`, restricting execution to authenticated users, using static-closure wrapping plus per-invocation `Throwable` isolation for `php_code`, and enforcing HTTPS revalidation, timeout clamping, `redirection => 0`, and no caller header/cookie propagation for `wp_remote_post`
- [x] T018 [US3] Implement administrator-only exposure collections with strict `manage_options` gating, `mcp_type` filtering delegated to the query layer, and fail-closed unknown MCP server handling in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php`
- [x] T019 [US3] Wire the runtime processor only through `includes/Main.php` and ensure registry arguments built in `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` use nested `meta` structure instead of flat top-level args

**Checkpoint**: Published database-managed abilities register safely for runtime use, anonymous execution is denied, and exposure collections stay on the admin boundary.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Validate the full feature against the spec, contracts, and quality gates.

- [x] T020 [P] Run targeted PHPUnit, PHPCS, and PHPStan validation for `includes/Modules/Abilities/`, `includes/Utilities/AcrossAI_Abilities_*.php`, `includes/Main.php`, and `tests/phpunit/abilities/`
- [x] T021 [P] Validate the create, browse, publish, and exposure flows against `specs/009-abilities-business-logic-rest/quickstart.md` and update any implementation-driven contract deltas in `specs/009-abilities-business-logic-rest/contracts/rest-api.md`, `specs/009-abilities-business-logic-rest/contracts/runtime-registration.md`, and `specs/009-abilities-business-logic-rest/contracts/exposure-collections.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: starts immediately.
- **Foundational (Phase 2)**: depends on Setup and blocks all user stories.
- **User Story 1 (Phase 3)**: depends on Foundational and delivers the MVP management API.
- **User Story 2 (Phase 4)**: depends on Foundational and can proceed after the REST scaffold is in place.
- **User Story 3 (Phase 5)**: depends on Foundational and reuses the shared query/validation/formatting layer for runtime publication.
- **Polish (Phase 6)**: depends on the user stories you intend to ship.

### User Story Dependencies

- **US1**: no dependency on other user stories after Foundational.
- **US2**: no dependency on US1 behavior, but it reuses the same orchestrator and foundational query/formatter utilities.
- **US3**: no dependency on US1 or US2 behavior, but it depends on the foundational validation/query rules and the Abilities REST/runtime module scaffold.

### Within Each User Story

- Write or extend the PHPUnit coverage first.
- Implement or extend controller/processor logic second.
- Wire through `includes/Main.php` only after the implementation surface exists.
- Validate each story independently before moving on.

### Parallel Opportunities

- T003, T004, and T005 can run in parallel once T002 is underway.
- T006 and T007 can run in parallel once their target foundational files exist.
- T012 and T015 can be prepared in parallel after the foundational layer is stable.
- T020 and T021 can run in parallel after implementation is complete.

---

## Parallel Example: User Story 1

```bash
# Parallelizable test and implementation preparation for US1:
Task: "Add create, sparse update, delete, duplicate-slug, invalid-payload, non-db delete protection, and explicit forbidden-path coverage for create/update/delete routes in tests/phpunit/abilities/AbilitiesWriteControllerTest.php"
Task: "Implement the thin REST orchestrator and write-route registration in includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php and includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php"
```

## Parallel Example: User Story 2

```bash
# Parallelizable browse/category work for US2:
Task: "Add browse, single-item, pagination, search, source/status/category/editable filters, category discovery, not-found coverage, and explicit forbidden-path coverage for list/single/categories routes in tests/phpunit/abilities/AbilitiesReadControllerTest.php"
Task: "Implement the administrator-only categories endpoint in includes/Modules/Abilities/Rest/AcrossAI_Abilities_Category_Controller.php and complete collection/item pagination formatting in includes/Utilities/AcrossAI_Abilities_Formatter.php"
```

## Parallel Example: User Story 3

```bash
# Parallelizable runtime/exposure work for US3:
Task: "Add runtime registration, authenticated execution, nested meta payload, php_code static-closure wrapping, per-invocation Throwable isolation, execution audit logging, wp_remote_post HTTPS revalidation, timeout clamping, redirection => 0, no caller header/cookie propagation, and fail-closed unknown-server-context coverage in tests/phpunit/abilities/AbilitiesProcessorTest.php"
Task: "Add explicit forbidden-path and route-level fail-closed server-context coverage for tool/resource/prompt exposure endpoints in tests/phpunit/abilities/AbilitiesExposureControllerTest.php"
Task: "Implement administrator-only exposure collections with strict manage_options gating, mcp_type filtering delegated to the query layer, and fail-closed unknown MCP server handling in includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational.
3. Complete Phase 3: User Story 1.
4. Validate CRUD, sparse updates, immutable-field enforcement, and delete rules.

### Incremental Delivery

1. Ship the foundational unified-table query and utility layer.
2. Add administrator management CRUD (US1).
3. Add browse/read/category discovery (US2).
4. Add runtime publication and exposure collections (US3).
5. Finish with targeted validation and contract reconciliation.

### Parallel Team Strategy

1. One developer completes the foundational query/utility layer.
2. A second developer can prepare US1 PHPUnit coverage while foundational code stabilizes.
3. After the REST scaffold lands, browse/category work and runtime/exposure work can proceed on separate branches with minimal file overlap.

---

## Notes

- This task plan explicitly preserves the unified `acrossai_abilities` table and does not create a second abilities table.
- `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` is the only new BerlinDB database-layer file in this feature.
- `includes/Main.php` remains the only Loader hook-registration surface.
- Query-layer helpers own filtering, search, pagination, exposure selection, and unlimited-fetch behavior.
- Every admin-only endpoint group must ship with explicit forbidden-path coverage: write, list, single-item, categories, and exposure routes.
- Utilities remain static-only unless a class must own runtime state or hook orchestration.
- Exposure collections are admin-only and must fail closed on unknown MCP server context.
- Runtime execution is authenticated-user only.
- Sparse updates must enforce the immutable-field matrix for non-db rows and re-read the full saved row before after-save hooks.
- Runtime registry args must use nested `meta` structure, not flat top-level args.
- Save-time hardening belongs in the validator/sanitizer tasks; execution-time hardening belongs in the processor and processor-test tasks.
