# Implementation Plan: Hide MCP Adapter System Abilities

**Branch**: `005-hide-mcp-system-abilities` | **Date**: 2026-05-19 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/005-hide-mcp-system-abilities/spec.md`

---

## Summary

Implement server-side filtering to exclude MCP adapter system abilities (`mcp-adapter/discover-abilities`, `mcp-adapter/execute-ability`, `mcp-adapter/get-ability-info`) from all REST API GET endpoints and the Abilities Manager UI. Create a centralized `AcrossAI_Protected_Abilities` utility class as a single source of truth for protected ability slugs. Apply filtering at the query layer (`AcrossAI_Ability_Registry_Query`) for list endpoints and add 404 checks in the REST controller for single-ability endpoints. Filtering is extensible via the `acrossai_abilities_manager_protected_slugs` WordPress filter, allowing third-party plugins to register custom protected slugs. Write operations are unaffected and out of scope.

---

## Technical Context

**Language/Version**: PHP 7.4+, WordPress 7.0+
**Primary Dependencies**:
- PHP: `AcrossAI_Ability_Registry_Query` (existing query builder), WordPress Filters API
- No new Composer or npm dependencies
**Storage**: Protected slugs list is static/filter-driven — no database changes
**Testing**: PHPUnit unit tests for `AcrossAI_Protected_Abilities` class and query-layer filtering
**Target Platform**: WordPress 7.0+ (single-site and multisite)
**Project Type**: WordPress plugin — PHP-only feature module
**Performance Goals**: Zero overhead per-request filtering (static array operations only; no DB queries)
**Constraints**:
  - Stateless utility class — only public static methods
  - Extensible via WordPress filter (no hardcoded slug list)
  - Read-only feature — write endpoints (`POST`, `DELETE`, `PATCH`) out of scope
  - PHP 7.4 compatible (no match expression, no named args)
  - Multisite-compatible (no transients or site-specific state)

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked against Constitution v1.3.0.*

### ✅ PASS — I. Modular Architecture
New utility class lives at `includes/Utilities/AcrossAI_Protected_Abilities.php` — shared utility scoped to sitewide abilities filtering. No abstract base class. No `register_hooks()` delegation. Integration points are query layer and REST controller (existing modules, no new module creation). Shared logic is a simple static utility with no dependencies on module orchestration.

### ✅ PASS — II. WordPress Standards Compliance
PHPCS strict + PHPStan L8 gates in DoD. WP 7.0+ / PHP 7.4+.
Uses `apply_filters()` (WP core) to allow extensibility via `acrossai_abilities_manager_protected_slugs` filter.
No raw SQL — filtering happens in PHP before query execution.
All REST endpoints already comply (feature 001 established REST structure).

### ✅ PASS — III. User-Centric Design
This is a backend filtering feature. No admin UI changes, no new forms, no DataForm/DataViews integration required. UX is improved by hiding clutter from the existing Manager UI (list auto-reflects filtering).

### ✅ PASS — IV. Security First (NON-NEGOTIABLE)
No sensitive data handling. Filter output is a slug array used in-memory for comparison. No escaping required (slugs are used for `in_array()` checks only, not output to HTML or SQL). Slug validation already enforced by REST layer via `sanitize_ability_slug()` (SEC-01). Protected slugs are hardcoded defaults — not user input.

### ✅ PASS — V. Extensibility Without Core Modification
`acrossai_abilities_manager_protected_slugs` filter allows third-party plugins to extend the protected list without modifying core code. Filter is called via `AcrossAI_Protected_Abilities::get_protected_slugs()` (single source of truth).

### ✅ PASS — VI. Reusability & DRY
`AcrossAI_Protected_Abilities` is a self-contained utility. Its methods are called from two places: (1) `AcrossAI_Ability_Registry_Query::query()` for list filtering, (2) `AcrossAI_Sitewide_Abilities_Controller::get_ability()` for single-ability 404 check. No duplication.

### ✅ PASS — VII. Definition of Done
Will include: PHPCS + PHPStan pass, unit tests for utility class and query-layer integration, security review (no sensitive operations), inline documentation for filter extensibility. No frontend changes required (no ESLint).

---

## Implementation Scope

### Files to Create
1. **`includes/Utilities/AcrossAI_Protected_Abilities.php`** (NEW)
   - Public static method `get_protected_slugs(): array`
   - Public static method `is_protected( string $slug ): bool`
   - Calls `apply_filters( 'acrossai_abilities_manager_protected_slugs', $default_slugs )` for extensibility
   - Default slugs: `['mcp-adapter/discover-abilities', 'mcp-adapter/execute-ability', 'mcp-adapter/get-ability-info']`
   - Caches filter result in a static property within the request (no persistent cache required)

### Files to Modify
1. **`includes/Utilities/AcrossAI_Ability_Registry_Query.php`**
   - Add `use AcrossAI_Protected_Abilities` at top
   - Modify `query()` method (or query builder chain) to exclude protected slugs from WHERE clause
   - Approach: Before calling `get_results()`, add `WHERE slug NOT IN (protected_list)` to query builder
   - Verify pagination totals (`X-WP-Total`, `X-WP-TotalPages`) reflect exclusion
   - Ensure search/filter/sort operations treat protected slugs as non-existent

2. **`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php`**
   - Add `use AcrossAI_Protected_Abilities` at top
   - Modify `get_ability( $request )` method to return 404 if slug is protected
   - Place 404 check BEFORE any DB lookup or registry search
   - Use consistent REST error format (e.g., `new WP_Error( 'ability_not_found', 'Ability not found.', [ 'status' => 404 ] )`)

### Files NOT Modified
- `includes/Main.php` — no hook changes (feature 005 is read-only)
- `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php` — write endpoints out of scope
- `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php` — write endpoints out of scope
- No admin/Partials/* changes — UI filtering happens via REST response filtering

---

## Functional Requirements Mapping

| Requirement | Implementation | File(s) |
|---|---|---|
| **FR-001**: Exclude system abilities from `GET /sitewide/abilities` list | Add `WHERE slug NOT IN (protected_list)` to query builder before `get_results()` | `AcrossAI_Ability_Registry_Query::query()` |
| **FR-002**: Return HTTP 404 for `GET /sitewide/abilities/{slug}` when slug is protected | Add `is_protected()` check before DB lookup; return 404 WP_Error | `AcrossAI_Sitewide_Abilities_Controller::get_ability()` |
| **FR-003**: Filtering treats protected abilities as non-existent (totals, pagination, search) | Query-layer WHERE clause applies to all query modes (search, filter, paginate) | `AcrossAI_Ability_Registry_Query::query()` |
| **FR-004**: Single source of truth for protected slugs | Centralized `AcrossAI_Protected_Abilities` class with static methods | `AcrossAI_Protected_Abilities.php` (NEW) |
| **FR-005**: Extensible via filter | `apply_filters( 'acrossai_abilities_manager_protected_slugs', ... )` in utility | `AcrossAI_Protected_Abilities::get_protected_slugs()` |
| **FR-006**: Write endpoints unaffected | No changes to override/bulk/delete controllers | _(no changes)_ |
| **FR-007**: Server-side filtering only | All filtering in PHP REST layer — no frontend logic | `AcrossAI_Ability_Registry_Query`, `AcrossAI_Sitewide_Abilities_Controller` |

---

## Edge Cases & Handling

| Edge Case | Handling | Test Strategy |
|---|---|---|
| **Large registry (1000+ abilities)** | Pagination excludes protected from `X-WP-Total` and `X-WP-TotalPages`. Use `COUNT(*)` WHERE clause (same as query filter) | Unit test: Mock 1000+ registry; verify count excludes 3 protected |
| **Partial-match search (e.g., "adapter")** | WHERE clause matches exact slug, so "adapter" search won't match "mcp-adapter/*" unless slugs contain "adapter". If partial match desired, responsibility is on search logic (out of scope for 005). Currently: no partial match. | Search test verifies exact-match behavior |
| **Custom protected slugs via filter** | `get_protected_slugs()` returns default + filtered list merged. Controller and query layer both use same list. | Unit test: Add custom slug via filter; verify excluded from query and 404 on GET |
| **Filter applied + protected slugs** | WHERE clause AND search/source filter both apply. Protected slugs excluded regardless of other filters. | Integration test: Apply source filter + search + protected slug; verify protected still absent |
| **Multisite isolation** | No transients or shared state — filtering is request-local. Each site's REST calls see same protected list (hardcoded). | Deploy to multisite; verify both sites exclude same slugs |

---

## Phase-by-Phase Plan

### Phase 1: Research & Verification (T001)
- [ ] Review spec.md for all requirements and user stories
- [ ] Review memory-synthesis.md for architecture constraints and prior decisions
- [ ] Inspect `AcrossAI_Ability_Registry_Query::query()` method signature and query builder interface
- [ ] Inspect `AcrossAI_Sitewide_Abilities_Controller::get_ability()` implementation
- [ ] Verify REST error format used in existing 404 responses (e.g., other controllers)
- [ ] Check if `sanitize_ability_slug()` is used before comparison (SEC-01 compliance)
- [ ] Confirm multisite table/prefix handling in query builder (if relevant)

**Definition of Done**: 
- All file signatures and interfaces documented
- No blocking architectural conflicts identified
- Query builder limitations (if any) understood

### Phase 2: Implementation (T002–T004)

**Task T002: Create `AcrossAI_Protected_Abilities` utility class**
- [ ] Create `includes/Utilities/AcrossAI_Protected_Abilities.php`
- [ ] Implement `get_protected_slugs(): array`
  - Hard-coded default list: `['mcp-adapter/discover-abilities', 'mcp-adapter/execute-ability', 'mcp-adapter/get-ability-info']`
  - Call `apply_filters( 'acrossai_abilities_manager_protected_slugs', $defaults )` to allow extensibility
  - Optionally cache result in static property within request (no database or transient needed)
- [ ] Implement `is_protected( string $slug ): bool`
  - Call `get_protected_slugs()` and check `in_array( $slug, ... )`
- [ ] Add inline documentation for filter extensibility (usage examples for third-party plugins)
- [ ] Run PHPCS + PHPStan L8 checks
- [ ] Pass: PHPCS 0 errors, PHPStan exit 0

**Task T003: Update `AcrossAI_Ability_Registry_Query::query()` to exclude protected slugs**
- [ ] Add `use AcrossAI_Protected_Abilities` at top of file
- [ ] Modify query builder chain to add `WHERE slug NOT IN (protected_list)` before `get_results()`
  - Exact location TBD based on query builder API (inspect in T001)
  - If BerlinDB, use `NOT IN` operator in query args or WHERE method
- [ ] Verify pagination totals reflect exclusion (totals must exclude protected)
- [ ] Verify search/filter/sort queries all respect the WHERE clause
- [ ] Run PHPCS + PHPStan L8 checks
- [ ] Pass: PHPCS 0 errors, PHPStan exit 0

**Task T004: Update `AcrossAI_Sitewide_Abilities_Controller::get_ability()` to return 404 for protected slugs**
- [ ] Add `use AcrossAI_Protected_Abilities` at top of file
- [ ] In `get_ability( $request )` method, add early check:
  ```php
  $slug = $request['slug']; // or however slug is extracted
  if ( AcrossAI_Protected_Abilities::is_protected( $slug ) ) {
      return new WP_Error( 'ability_not_found', 'Ability not found.', [ 'status' => 404 ] );
  }
  ```
  - Place check BEFORE any DB lookup or registry search
- [ ] Use consistent error format (check existing 404 responses for pattern)
- [ ] Test: Call `GET /wp-json/acrossai/v1/sitewide/abilities/mcp-adapter/discover-abilities` → expect 404
- [ ] Run PHPCS + PHPStan L8 checks
- [ ] Pass: PHPCS 0 errors, PHPStan exit 0

### Phase 3: Testing (T005–T006)

**Task T005: Unit tests for `AcrossAI_Protected_Abilities`**
- [ ] Create test file: `tests/phpunit/test-protected-abilities.php` (or extend existing abilities test)
- [ ] Test `get_protected_slugs()` returns default list
- [ ] Test `is_protected()` for default slugs (true for mcp-adapter/*, false for others)
- [ ] Test `acrossai_abilities_manager_protected_slugs` filter callback (custom slug registration)
- [ ] Test filter result is merged with defaults (if feature specifies merge vs. replace)
- [ ] Mock abilities registry; verify no DB calls in utility
- [ ] Run: `npm run test:phpunit -- test-protected-abilities.php` (if PHPUnit bootstrap exists)

**Task T006: Integration tests for query-layer filtering**
- [ ] Create or extend test file: `tests/phpunit/test-abilities-registry-query.php`
- [ ] Test `query()` returns results without protected slugs
- [ ] Test pagination totals (`X-WP-Total`) exclude protected abilities
- [ ] Test search query filters correctly (search term + protected exclusion)
- [ ] Test source filter + protected exclusion combined
- [ ] Mock large registry (100+ abilities); verify query performance

### Phase 4: Security & Code Quality (T007–T008)

**Task T007: Security review checklist**
- [ ] Confirm `sanitize_ability_slug()` applied to request slugs before comparison (SEC-01)
- [ ] Verify no direct SQL injection vectors (query builder used; no raw SQL)
- [ ] Verify no output escaping needed (slugs used in-memory only, not HTML output)
- [ ] Verify multisite isolation (no shared state; each site sees same hardcoded list)
- [ ] Verify filter hook safe (filter receives array, returns array — no injection risk)
- [ ] Confirm no capability checks needed (read-only feature; access controlled by existing REST auth)
- [ ] Check: no hardcoded admin credentials, no unsafe file operations, no eval/exec

**Task T008: Code quality checks**
- [ ] PHPCS strict: `composer phpcs` or `./vendor/bin/phpcs`
- [ ] PHPStan level 8: `composer phpstan` or `./vendor/bin/phpstan`
- [ ] No deprecated functions (WP 7.0+ API only)
- [ ] Inline documentation: function comments, filter description, usage examples
- [ ] Variable naming: `$protected_slugs`, `$default_slugs`, `$slug` — no abbreviated names
- [ ] Error messages clear (user-facing error in 404 response)

### Phase 5: Verification & Acceptance (T009–T010)

**Task T009: Manual acceptance testing (Spec requirements)**
- [ ] Load Abilities Manager page (`/wp-admin/admin.php?page=acrossai-abilities-manager`)
  - Verify MCP adapter abilities NOT visible in list
  - Verify total count reflects exclusion (e.g., 10 abilities − 3 protected = 7 shown)
  - Verify search for "mcp-adapter" or "discover" returns 0 results
- [ ] Test single-ability REST endpoint
  - `GET /wp-json/acrossai/v1/sitewide/abilities/mcp-adapter/discover-abilities` → 404 response
  - `GET /wp-json/acrossai/v1/sitewide/abilities/some-other-ability` → 200 response (if exists)
- [ ] Test filter extensibility (if available in local environment)
  - Add custom filter hook via theme `functions.php` or test plugin
  - Register custom protected slug (e.g., `'my-plugin/internal-helper'`)
  - Verify custom slug excluded from list and 404 on GET
- [ ] Test multisite (if multisite environment available)
  - Both sites exclude same protected slugs

**Task T010: Verify acceptance criteria from spec**
- [ ] ✅ User Visibility: MCP adapter abilities absent from UI
- [ ] ✅ REST API Compliance: 404 on single-ability GET; list excludes protected
- [ ] ✅ Pagination Accuracy: Totals reflect exclusion
- [ ] ✅ Extensibility: Custom filter works
- [ ] ✅ Server-Side Only: No frontend workarounds
- [ ] ✅ Backward Compatibility: Write endpoints work normally
- [ ] ✅ Code Quality: PHPCS 0 errors, PHPStan L8 exit 0

---

## Definition of Done

Feature is NOT complete until:

- [ ] **Code**
  - `AcrossAI_Protected_Abilities.php` created with full implementations
  - `AcrossAI_Ability_Registry_Query.php` updated to filter protected slugs
  - `AcrossAI_Sitewide_Abilities_Controller.php` updated to return 404
  - All inline documentation in place

- [ ] **Quality**
  - PHPCS strict: 0 errors (or documented suppressions with justification)
  - PHPStan level 8: exit code 0
  - No deprecated WordPress functions
  - Multisite-compatible (no per-site state mutations)

- [ ] **Testing**
  - Unit tests for `AcrossAI_Protected_Abilities` pass
  - Integration tests for query-layer filtering pass
  - Manual acceptance tests (all 5 scenarios from spec) pass
  - Spec success criteria verified (✅ all 7)

- [ ] **Security**
  - Security review checklist completed
  - No new vulnerabilities introduced
  - Slug comparison uses sanitized input (SEC-01)
  - Filter hook safe (no injection vectors)

- [ ] **Documentation**
  - Inline comments explain filter extensibility (usage for third-party plugins)
  - Task completion notes in session memory
  - Memory review pending (will update durable memory after approval)

---

## Rollback Plan

If issues arise:

1. **Partial filtering failure**: Revert `AcrossAI_Ability_Registry_Query.php` change; protected abilities briefly visible in UI but REST controller 404 still blocks access.
2. **404 false positives**: Revert `AcrossAI_Sitewide_Abilities_Controller.php` change; protected abilities visible (regression) but no blocker.
3. **Filter extensibility bug**: Temporarily disable `apply_filters()` call and use hardcoded defaults only.
4. **Complete rollback**: Remove `AcrossAI_Protected_Abilities.php`, revert both modified files, re-run tests to confirm baseline.

All rollback steps are non-breaking (no DB changes, no stored configuration affected).

---

## Sign-Off

- **Spec Review**: ✅ Completed 2026-05-19
- **Memory Synthesis**: ✅ Created 2026-05-19
- **Architecture Check**: ✅ Constitution v1.3.0 compliant
- **Ready for Task Planning**: ✅ All phases documented, no blocking conflicts
