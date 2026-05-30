# Tasks: Plugin Check CI + Compliance Fixes

**Input**: `specs/020-plugin-check-ci/plan.md`, `spec.md`, `memory-synthesis.md`, `security-constraints.md`
**Branch**: `020-plugin-check-ci`

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1, US2, US3 from spec.md
- No tests were requested in the specification.

---

## Phase 1: Foundation

**Purpose**: Create the CI workflow. Required before any PR-gate verification.

- [x] T001 [US1] Create `.github/workflows/plugin-check.yml` — triggers `push: branches: [main]` + `pull_request`; steps: `actions/checkout@v4`, `shivammathur/setup-php@v2` (php-version: 8.1), `composer install --no-dev --prefer-dist --no-progress`, `WordPress/plugin-check-action@v1` with inputs `build-dir: '.'`, `ignore-codes: 'WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval'`, `include-experimental: true`, `ignore-warnings: false`. SECURITY: add `permissions: contents: read` to the job.

**Checkpoint**: `.github/workflows/plugin-check.yml` exists and is valid YAML.

---

## Phase 2: User Story 2 — Plugin passes Plugin Check (Priority: P1) 🎯

**Goal**: Zero errors and zero warnings from `wp plugin check acrossai-abilities-manager`.

**Independent Test**: `grep "Tested up to" acrossai-abilities-manager.php` returns `7.0`; all 12 `error_log()` calls are inside `WP_DEBUG_LOG` guards; PHPCS + PHPStan both pass.

### CHANGE-2 — Plugin header

- [x] T002 [US2] `acrossai-abilities-manager.php` — insert ` * Tested up to:      7.0` after the `Requires at least: 6.9` line (line 28). No other lines change.

### CHANGE-3 — WP_DEBUG_LOG guards (5 files, 12 call sites)

Each task is a single file. All can run in parallel — they touch separate files.

- [x] T003 [P] [US2] `admin/Main.php` — wrap line-123 `error_log()` in `if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {`. The call is nested inside `if ( ! file_exists(...) )` — preserve that outer block; nest the new guard INSIDE it. Move `phpcs:ignore` comment inside the guard, one tab level deeper than the outer if. Use tabs, not spaces. (BUG-UNCONDITIONAL-ASSET-INCLUDE + BUG-PHPCBF-TABS)

- [x] T004 [P] [US2] `includes/Utilities/AcrossAI_Sanitizer.php` — wrap line-102 `error_log()` in `WP_DEBUG_LOG` guard. Move `phpcs:ignore` inside. Use tabs. Do not touch `@package`/`@subpackage`/`@since` docblock. (AC-FILE-HEADER-PATTERN)

- [x] T005 [P] [US2] `includes/Utilities/AcrossAI_Logger_Formatter.php` — wrap lines 50, 59, and 67 `error_log()` calls (3 calls) in `WP_DEBUG_LOG` guard each. Move each `phpcs:ignore` inside its guard. Use tabs. Do not touch file header. (AC-FILE-HEADER-PATTERN)

- [x] T006 [P] [US2] `includes/Modules/Logger/AcrossAI_Ability_Logger.php` — wrap lines 167, 215, 225, and 363 `error_log()` calls (4 calls) in `WP_DEBUG_LOG` guard each. Move each `phpcs:ignore` inside its guard. Use tabs. Do not touch file header. (AC-FILE-HEADER-PATTERN)

- [x] T007 [P] [US2] `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` — wrap lines 243, 256, and 261 `error_log()` calls (3 calls) in `WP_DEBUG_LOG` guard each. Move each `phpcs:ignore` inside its guard. Use tabs. Do not touch file header or the `eval()` at line ~251. (AC-FILE-HEADER-PATTERN + MUST NOT touch eval)

**Guard pattern (identical for all 12 sites)**:
```php
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( '...' );
}
```

**Checkpoint**: `grep -rn "error_log(" includes/ admin/` — 12 results, every one inside `WP_DEBUG_LOG` guard. PHPCS zero errors. PHPStan zero errors.

---

## Phase 3: User Story 3 — Governance docs (Priority: P2)

**Goal**: `AGENTS.md` checklist + `CONSTITUTION.md` encode Plugin Check compliance for future sessions.

**Independent Test**: `grep "Plugin Check" AGENTS.md` returns the bullet; `grep "plugin-check" .specify/memory/CONSTITUTION.md` returns the §II bullet; constitution version = `1.4.3`.

Both tasks are independent (different files) and can run in parallel.

- [x] T008 [P] [US3] `AGENTS.md` — insert `- [ ] Plugin Check pass (WordPress/plugin-check-action)` after `- [ ] Package validation pass (npm run validate-packages)` in the Before Commit Checklist. Checklist item count goes from 8 → 9.

- [x] T009 [P] [US3] `.specify/memory/CONSTITUTION.md` — in §II WordPress Standards Compliance, after "No deprecated WordPress functions are permitted.", insert new bullet: "The plugin MUST pass the WordPress Plugin Check tool with zero errors and zero warnings, with only intentional code suppressions (currently: `ignore-codes: WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` for the intentional `eval()` in the `php_code` ability type). All new code MUST remain plugin-check clean." Bump version `1.4.2` → `1.4.3`. Update the `<!-- SYNC IMPACT REPORT -->` HTML comment at the top with: version `1.4.2 → 1.4.3`, section modified `§II`, rationale `Feature 020 Plugin Check CI`. (CONSTITUTION-VERSION-PATTERN)

**Checkpoint**: Both files updated; version in CONSTITUTION.md reads `1.4.3`.

---

## Phase 4: Validation

Run validation in sequence (PHPCS then PHPStan; each must be zero before proceeding).

- [x] T010 Run `composer run phpcs` — must exit 0 across all 5 modified PHP files. Fix any issue before proceeding.
- [x] T011 Run `composer run phpstan` — must exit 0 at level 8. Fix any issue before proceeding.
- [x] T012 Verify `grep -rn "error_log(" includes/ admin/` — count is 12; every result preceded by `WP_DEBUG_LOG` guard.
- [x] T013 Verify `grep "Tested up to" acrossai-abilities-manager.php` → `Tested up to:      7.0`.
- [x] T014 Verify `grep "Plugin Check" AGENTS.md` returns checklist bullet (9 items total).
- [x] T015 Verify `grep "1.4.3" .specify/memory/CONSTITUTION.md` returns a match.
- [x] T016 Verify `eval()` still present: `grep -n "eval(" includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` → 1 result.

---

## Dependency Order

```
T001 (workflow) ─ independent
T002 (header)   ─ independent
T003–T007       ─ parallel (different files, no deps)
T008–T009       ─ parallel (different files, no deps)
T010–T016       ─ run after T001–T009 all complete
```

**Execution note**: T003–T007 can all be done in a single implementation pass since they apply the same pattern; order within that group does not matter.

## Security Constraints (from security-constraints.md)

- T001: `ignore-codes` value MUST be exactly `WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval`.
- T001: Add `permissions: contents: read` to workflow job.
- T003–T007: All 12 call sites MUST be wrapped. Partial wrapping is not acceptable.
- T007: `eval()` at `AcrossAI_Abilities_Processor.php:~251` MUST NOT be modified.
- Post-implement: record intentional `eval()` risk acceptance in `docs/memory/DECISIONS.md`.
