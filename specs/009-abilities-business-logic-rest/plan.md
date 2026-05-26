# Implementation Plan: Abilities Business Logic and REST API

**Branch**: `009-abilities-business-logic-rest` | **Date**: 2026-05-22 | **Spec**: [specs/009-abilities-business-logic-rest/spec.md](specs/009-abilities-business-logic-rest/spec.md)
**Input**: Feature specification from `/specs/009-abilities-business-logic-rest/spec.md`

## Summary

Spec 009 adds the Abilities module business-logic layer and management/runtime REST APIs for database-managed abilities stored in the existing unified `wp_acrossai_abilities` table. The implementation will add an Abilities-focused database/query layer, a thin REST orchestrator with per-domain sub-controllers, static validation/sanitization/formatting utilities, and a runtime registration/execution path that only publishes valid `source = db` abilities in `publish` status and only permits authenticated runtime execution.

## Technical Context

**Language/Version**: PHP 7.4+ (WordPress 6.9+), no new frontend scope in this feature  
**Primary Dependencies**: WordPress REST API, WordPress Abilities API, BerlinDB, existing `wpboilerplate/wpb-mcp-servers-list` integration, existing shared utilities under `includes/Utilities/`  
**Storage**: Unified per-site BerlinDB table `{$wpdb->prefix}acrossai_abilities` shared with Sitewide wrappers; no new abilities table  
**Testing**: PHPUnit integration tests for database/query/REST/runtime registration paths; targeted validation of schema payloads and execution modes  
**Target Platform**: WordPress plugin backend, REST API consumers, runtime ability registry  
**Project Type**: Backend WordPress plugin module  
**Performance Goals**: Accurate filtered pagination up to 100 rows per page, browseability for 1,000+ ability rows, registration pass that skips invalid rows without aborting the full runtime bootstrap  
**Constraints**: `includes/Main.php` remains the only Loader wiring surface; variable-first singleton wiring only; REST filtering/search/pagination stays in query builders; static-only utilities unless orchestration/state is required; BerlinDB Table classes follow the soft-singleton rule when direct instantiation is possible; sparse writes must re-read the full row before after-save hooks; registry args must use nested meta paths; unlimited BerlinDB queries use `number => 0`; runtime execution is authenticated-user only  
**Scale/Scope**: CRUD, browse, categories, exposure collections, publication/runtime registration, and execution-mode mapping for database-managed abilities; admin menu/page work remains out of scope for this feature

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Gate | Status | Notes |
|------|--------|-------|
| Modular Architecture | PASS | New work stays in `includes/Modules/Abilities/`, shared helpers stay in `includes/Utilities/`, and Sitewide remains a sibling consumer of the same unified storage boundary. |
| Boot Flow Rule | PASS | `includes/Main.php` is the only Loader wiring surface; only Abilities orchestrators/processors are wired there, using named singleton variables. |
| REST Controller Pattern | PASS | REST design splits into a thin orchestrator plus read, write, exposure, and category sub-controllers. |
| Query-Layer Filtering | PASS | Search, source/status filters, sort, and pagination are planned in the Abilities query-builder layer, not in REST handlers. |
| Storage Boundary | PASS | The plan explicitly reuses the unified `acrossai_abilities` table and forbids a separate custom-abilities table. |
| Security First | PASS | Management endpoints require administrator capability checks; runtime execution uses authenticated-user gating only; sparse-save and registry watchpoints are captured as mandatory requirements. |
| User-Centric Design | PASS WITH DEFERRED UI | This feature is backend-only by spec. It provides backend contracts for a later DataForm/DataViews UI feature instead of introducing admin UI work here. |

**Post-Design Re-check**: Pass. Phase 0 and Phase 1 artifacts keep the unified-table boundary, static utility rule, orchestrator split, and authenticated runtime execution rule intact.

## Project Structure

### Documentation (this feature)

```text
specs/009-abilities-business-logic-rest/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── rest-api.md
│   ├── runtime-registration.md
│   └── exposure-collections.md
└── tasks.md
```

### Source Code (repository root)

```text
includes/
├── Main.php                                               ← MODIFIED: wire Processor + REST orchestrator
├── Modules/
│   ├── Abilities/
│   │   ├── AcrossAI_Abilities_Processor.php              ← NEW
│   │   ├── Database/
│   │   │   └── AcrossAI_Abilities_Query.php              ← NEW (only new DB file)
│   │   └── Rest/
│   │       ├── AcrossAI_Abilities_Rest_Controller.php     ← NEW
│   │       ├── AcrossAI_Abilities_Read_Controller.php     ← NEW
│   │       ├── AcrossAI_Abilities_Write_Controller.php    ← NEW
│   │       ├── AcrossAI_Abilities_Exposure_Controller.php ← NEW
│   │       └── AcrossAI_Abilities_Category_Controller.php ← NEW (GET /abilities/categories)
│   └── Sitewide/
│       └── Database/
│           ├── AcrossAI_Sitewide_Row.php                 ← REUSED (item_shape)
│           ├── AcrossAI_Sitewide_Schema.php              ← REUSED (table_schema)
│           ├── AcrossAI_Sitewide_Query.php               ← REUSED (sibling, sitewide overrides only)
│           └── AcrossAI_Sitewide_Table.php               ← REUSED (table owner, no change)
└── Utilities/
    ├── AcrossAI_Abilities_Validator.php                  ← NEW
    ├── AcrossAI_Abilities_Sanitizer.php                  ← NEW
    └── AcrossAI_Abilities_Formatter.php                  ← NEW

tests/
└── phpunit/
    └── abilities/
        ├── AbilitiesQueryTest.php
        ├── AbilitiesRestControllerTest.php
        ├── AbilitiesProcessorTest.php
        └── AbilitiesValidationTest.php
```

**Structure Decision**: `AcrossAI_Abilities_Query` is the only new BerlinDB-layer file. It sets `$table_name = 'acrossai_abilities'`, `$table_schema = AcrossAI_Sitewide_Schema::class`, and `$item_shape = AcrossAI_Sitewide_Row::class` — reusing both existing Sitewide classes rather than duplicating them. No new `Row`, `Schema`, or `Table` BerlinDB files are created. `AcrossAI_Ability_Source_Detector` is NOT created — source detection is inline logic in the Write controller. Sitewide remains a sibling module over the same storage boundary; Sitewide Query handles sitewide-override operations, Abilities Query handles all Abilities module CRUD.

## Phase 0: Research Summary

Phase 0 resolved the implementation approach captured in [specs/009-abilities-business-logic-rest/research.md](specs/009-abilities-business-logic-rest/research.md):

1. The Abilities module will use the unified table as the canonical persistence boundary instead of introducing a second abilities table.
2. REST will be split by domain into read, write, exposure, and category controllers behind a thin orchestrator.
3. Query-builder logic will own source/status filtering, search, sort, pagination, and unlimited-query behavior.
4. Runtime registration will only publish valid `source = db` and `status = publish` rows and will use nested registry meta paths.
5. Runtime permission for database-managed abilities will be authenticated-user only, regardless of exposure type.
6. Execution-mode handling will remain explicit and validated per callback type rather than inferred from partial payloads.

## Phase 1: Design

### Data Model

The design artifacts in [specs/009-abilities-business-logic-rest/data-model.md](specs/009-abilities-business-logic-rest/data-model.md) formalize four key entities:

- `Ability` as the canonical unified-table row
- `Ability Category` as a runtime-discovered validation boundary
- `Execution Configuration` as callback-mode-specific structured data
- `Exposure Profile` as machine-consumable visibility metadata

The canonical row shape for Spec 009 uses the existing unified-table columns already present in the shared storage boundary: `id`, `ability_slug`, `label`, `description`, `category`, `status`, `provider`, `source`, `site_allowed`, `callback_type`, `callback_config`, `input_schema`, `output_schema`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `readonly`, `destructive`, `idempotent`, `created_at`, `updated_at`, `created_by`, and `updated_by`.

### REST and Runtime Contracts

Phase 1 contract documents define:

- Management CRUD and browse endpoints in [specs/009-abilities-business-logic-rest/contracts/rest-api.md](specs/009-abilities-business-logic-rest/contracts/rest-api.md)
- Runtime publication behavior in [specs/009-abilities-business-logic-rest/contracts/runtime-registration.md](specs/009-abilities-business-logic-rest/contracts/runtime-registration.md)
- Exposure collection behavior in [specs/009-abilities-business-logic-rest/contracts/exposure-collections.md](specs/009-abilities-business-logic-rest/contracts/exposure-collections.md)

### Planned Implementation Shape

1. Add `AcrossAI_Abilities_Query` — the single new BerlinDB Query file — targeting the shared `acrossai_abilities` table with `$table_schema = AcrossAI_Sitewide_Schema::class` and `$item_shape = AcrossAI_Sitewide_Row::class`. No new Row, Schema, or Table files are created.
2. Add Abilities query helpers for list, single-item, source/status filtering, sparse-write re-read, category validation support, and runtime-publication retrieval.
3. Add static validator, sanitizer, and formatter utilities for payload normalization and consistent response shaping.
4. Add a thin Abilities REST orchestrator and split sub-controllers for read, write, category, and exposure endpoints.
5. Add an Abilities processor that registers published database-managed abilities at runtime and maps execution mode to callable behavior.
6. Wire only the REST orchestrator and processor in `includes/Main.php` using named singleton variables.

## Implementation Strategy

### Policy Decisions (resolved — must not reopen during implementation)

| # | Decision | Choice | Rationale |
|---|---|---|---|
| PD-001 | `GET /abilities/exposures/{type}` permission | **Admin-only (`manage_options`)** | Consistent with all other Spec 009 endpoints; avoids a separate permission tier; full discovery metadata is safe behind admin gate. |
| PD-002 | `php_code` and `wp_remote_post` scope | **Keep in scope with explicit hardening rules** | Both types are fully specced in spec.md and the slug convention doc; removing them defers substantial user value. |

#### Hardening rules for `php_code` (PD-002)

- Treated as **trusted-admin-authored stored code** — no sandbox, no AST isolation. Author trust = same as `functions.php` editor, gate = `manage_options`.
- Validator (`AcrossAI_Abilities_Validator`) must run `token_get_all()` syntax check on save. Any parse error → 400.
- Blocked function scan on tokens before DB write. Blocked list: `eval`, `exec`, `system`, `passthru`, `shell_exec`, `popen`, `proc_open`, `file_put_contents`, `unlink`.
- Size limit: 64 KB (same as all JSON fields, enforced in `save_override()` and Validator).
- Execution wrapping: `$fn = static function($input) { <code> }; return $fn($input);` — static closure prevents `$this` capture.
- Execution errors caught with `try/catch(\Throwable $e)` per invocation; exception logged, returns `WP_Error`; does not abort registry bootstrap.
- Audit logging: ability slug + execution outcome logged on each invocation (no input/output data).

#### Hardening rules for `wp_remote_post` (PD-002)

- `callback_config` must contain `url` (string, validated as `FILTER_VALIDATE_URL`). No other protocols accepted: `https://` only.
- Optional `timeout` key: integer, capped at 30 seconds in Processor; values above 30 are silently clamped.
- No caller headers or cookies propagated by default. `callback_config` may not include `headers` key (Validator rejects unknown keys).
- Redirect following: `redirection => 0` (no redirects) via `wp_remote_post` args.
- Response body: decoded as JSON array; if decode fails, wrapped as `['raw' => $body]`.
- SSRF note: no allowlist in Spec 009 (admin-only authors). Allowlist enforcement is a future hardening spec item.

#### Immutable field matrix for shared-table writes (PD-003)

- `source`, `provider`, `created_at`, `updated_at`, `created_by`, and `updated_by` are server-controlled in every write path.
- Create paths always force `source = db`; update paths never accept caller-supplied `source`.
- For `source != db` rows, sparse updates must reject changes to: `ability_slug`, `label`, `description`, `category`, `status`, `provider`, `source`, `callback_type`, `callback_config`, `input_schema`, `output_schema`, `show_in_rest`, `show_in_mcp`, `mcp_type`, and `mcp_servers`.
- Allowed updates on inherited rows are limited to explicitly modeled annotation fields and must be enforced after merge against the persisted row, not only against raw request keys.

### Phase 2 Task Planning Direction

1. Create `AcrossAI_Abilities_Query` targeting the unified table with `$table_schema = AcrossAI_Sitewide_Schema::class` and `$item_shape = AcrossAI_Sitewide_Row::class`. No new Row, Schema, or Table files.
2. Implement source-aware Abilities query helpers that keep filtering/pagination in the query builder and use `number => 0` for unlimited fetches.
3. Implement static validation and sanitization helpers for slug, category, status, callback config (all 4 types with PD-002 rules), exposure metadata, and schema payloads.
4. Implement REST read/write/categories/exposure controllers behind an orchestrator with **admin-only (`manage_options`) permission check on all endpoints including exposure**. Categories endpoint is `GET /abilities/categories` (sub-resource, not a separate top-level route).
5. Implement sparse update logic that merges submitted fields into the stored row, enforces PD-003 against the persisted row, and always fetches the full saved row before after-save hooks.
6. Implement runtime registration/execution mapping for published database-managed abilities using nested `meta` registry args and authenticated-user-only execution permission. Apply PD-002 execution wrapping and error isolation per invocation.
7. Add PHPUnit coverage for query behavior, REST behavior, runtime publication, and validation edge cases.

## Watchpoints

- **Do not create new BerlinDB Row, Schema, or Table files.** `AcrossAI_Abilities_Query` is the only new DB-layer file. It reuses `AcrossAI_Sitewide_Row` and `AcrossAI_Sitewide_Schema`. Creating duplicates breaks the zero-duplication contract and produces two diverging class hierarchies over the same table.
- **Do not create `AcrossAI_Ability_Source_Detector`.** Source detection is inline logic in the Write controller; a separate detector class is unnecessary complexity.
- Do not introduce a second custom-abilities table or repurpose the plan toward Feature 008’s older storage model.
- Do not move filtering, search, or pagination logic into REST handlers for convenience.
- Do not fire after-save hooks with sparse local field arrays; always re-read the saved row first.
- Do not write runtime registration annotations to flat top-level args; use the nested meta structure expected by registry consumers.
- Do not use `number => -1` in BerlinDB unlimited queries.
- Do not allow anonymous runtime execution of published database-managed abilities.
- Do not give BerlinDB Table subclasses a private constructor if activation paths or tests may instantiate them directly.
- **Do not reopen PD-001 or PD-002 during implementation.** Exposure endpoint is admin-only. Both `php_code` and `wp_remote_post` are in scope with the hardening rules above — not a later spec.

## Complexity Tracking

No constitution violations or justified deviations are required for this plan.
