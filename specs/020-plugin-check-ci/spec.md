# Feature Specification: Plugin Check CI + Compliance Fixes

**Feature Branch**: `020-plugin-check-ci`  
**Created**: 2026-05-30  
**Status**: Implemented  
**Input**: User description: "Add GitHub Actions workflow for WordPress Plugin Check Action on PRs. Fix all compliance issues the check flags in the current codebase."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - CI blocks non-compliant pull requests (Priority: P1)

A developer opens a pull request against `main`. The GitHub Actions workflow automatically runs the WordPress Plugin Check tool against the plugin. If the plugin has any compliance errors or warnings, the check fails and the PR cannot be merged until all issues are fixed.

**Why this priority**: This is the core gate — without it, non-compliant code can reach `main`. All other changes exist to make the plugin pass this gate cleanly.

**Independent Test**: Create a PR on the `020-plugin-check-ci` branch. The "WordPress Plugin Check" job should appear in the PR checks list and complete with a green status (zero errors, zero warnings) once all compliance fixes are applied.

**Acceptance Scenarios**:

1. **Given** a pull request is opened against `main`, **When** the CI runs, **Then** a "WordPress Plugin Check" job appears in the PR checks list.
2. **Given** a plugin with zero compliance errors and warnings, **When** the Plugin Check job runs, **Then** it completes green (exit code 0).
3. **Given** a plugin with a bare `error_log()` call without a `WP_DEBUG_LOG` guard, **When** the Plugin Check job runs, **Then** it fails and blocks the PR merge.
4. **Given** the intentional `eval()` call in `AcrossAI_Abilities_Processor.php`, **When** the Plugin Check job runs, **Then** it does NOT flag that call as an error (suppressed via `ignore-codes`).

---

### User Story 2 - Plugin passes Plugin Check with zero issues (Priority: P1)

All currently-flagged compliance issues are fixed so that `wp plugin check acrossai-abilities-manager` returns zero errors and zero warnings when run against the current codebase.

**Why this priority**: The CI gate is only useful if the codebase is already clean. Both stories must be delivered together.

**Independent Test**: Run `wp plugin check acrossai-abilities-manager` on a local WordPress install with the Plugin Check plugin active. Expected: 0 errors, 0 warnings.

**Acceptance Scenarios**:

1. **Given** the plugin is installed on a WordPress site, **When** `wp plugin check acrossai-abilities-manager` is run, **Then** the output shows 0 errors and 0 warnings.
2. **Given** all 12 `error_log()` calls are wrapped in `WP_DEBUG_LOG` guards, **When** Plugin Check inspects the PHP files, **Then** it does not flag any development function usage.
3. **Given** the plugin header has a `Tested up to: 7.0` field, **When** Plugin Check inspects the main plugin file, **Then** it does not flag a missing `Tested up to` header.

---

### User Story 3 - Future developers automatically maintain compliance (Priority: P2)

The `AGENTS.md` commit checklist and the project constitution are updated so that all future feature work in this repository automatically includes Plugin Check compliance as a mandatory quality gate.

**Why this priority**: Prevents regression — without updating these governance documents, future contributors may not know Plugin Check compliance is required.

**Independent Test**: Read `AGENTS.md` and confirm "Plugin Check pass" appears in the Before Commit Checklist. Read `.specify/memory/CONSTITUTION.md` and confirm §II includes a Plugin Check bullet.

**Acceptance Scenarios**:

1. **Given** a developer reads `AGENTS.md`, **When** they reach the "Before Commit Checklist" section, **Then** they see "Plugin Check pass (WordPress/plugin-check-action)" as a checklist item.
2. **Given** a spec-kit session for a new feature, **When** the constitution is read, **Then** §II WordPress Standards Compliance lists Plugin Check as a mandatory gate.

---

### Edge Cases

- What happens when `WP_DEBUG_LOG` is defined but set to `false`? The guard `defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG` correctly skips the `error_log()` call.
- What happens when the `eval()` call is flagged despite `ignore-codes`? The workflow must use the exact PHPCS error code token `WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` — not a Plugin Check check slug.
- What if `composer install` fails in CI? The workflow will fail at the install step before reaching plugin-check, which is the correct behaviour (dependency errors must be fixed first).
- What if the `build/` directory is missing from a fresh checkout? It is committed to git, so `actions/checkout@v4` will include it — no build step is needed.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The repository MUST have a GitHub Actions workflow that runs `WordPress/plugin-check-action@v1` on every push to `main` and on every pull request event.
- **FR-002**: The workflow MUST run `composer install --no-dev` before the Plugin Check step (vendor directory is git-ignored and required by the plugin).
- **FR-003**: The workflow MUST suppress the PHPCS code `WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` via the `ignore-codes` input (covers the intentional `eval()` in the `php_code` ability type).
- **FR-004**: The workflow MUST enable all experimental checks (`include-experimental: true`) and fail on warnings (`ignore-warnings: false`).
- **FR-005**: The main plugin file (`acrossai-abilities-manager.php`) MUST include a `Tested up to: 7.0` header field.
- **FR-006**: Every `error_log()` call in the codebase MUST be wrapped in `if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG )` — all 12 calls across 5 files.
- **FR-007**: The `phpcs:ignore` comment for each `error_log()` call MUST remain, moved inside the `if` block immediately before the `error_log()` line.
- **FR-008**: The `eval()` call in `AcrossAI_Abilities_Processor.php` MUST NOT be modified — it is intentional and already has the correct PHPCS ignores.
- **FR-009**: `AGENTS.md` "Before Commit Checklist" MUST include "Plugin Check pass (WordPress/plugin-check-action)" as a checklist item.
- **FR-010**: `.specify/memory/CONSTITUTION.md` §II MUST include a bullet requiring zero Plugin Check errors and warnings, and version MUST be bumped from `1.4.2` to `1.4.3`.
- **FR-011**: After all changes, PHPCS and PHPStan level 8 MUST pass with zero errors across all modified PHP files.

### Key Entities

- **GitHub Actions Workflow**: The YAML file at `.github/workflows/plugin-check.yml` that defines the CI pipeline.
- **Plugin Check compliance**: The state where `wp plugin check acrossai-abilities-manager` returns 0 errors, 0 warnings.
- **WP_DEBUG_LOG guard**: The conditional pattern `if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG )` wrapping every `error_log()` call.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every pull request against `main` triggers a "WordPress Plugin Check" job that completes within 5 minutes.
- **SC-002**: The plugin produces 0 errors and 0 warnings when scanned by `wp plugin check` after all compliance fixes are applied.
- **SC-003**: All 12 `error_log()` call sites are wrapped in the `WP_DEBUG_LOG` guard — `grep -c "error_log(" includes/ admin/` still returns 12 (no calls removed, all guarded).
- **SC-004**: PHPCS reports 0 errors across all 5 modified PHP files after the guard wrapping.
- **SC-005**: PHPStan level 8 reports 0 errors across all 5 modified PHP files after the guard wrapping.
- **SC-006**: A PR that intentionally introduces a bare `error_log()` without a guard causes the CI job to fail, confirming the gate is blocking.
- **SC-007**: `AGENTS.md` checklist item count increases from 8 to 9.
- **SC-008**: Constitution version reads `1.4.3` after the patch bump.

## Assumptions

- `build/` is committed to git and does not need to be regenerated in CI (confirmed by `.gitignore` — `build/` is not listed).
- `vendor/` is git-ignored and must be installed via `composer install --no-dev` in CI (confirmed by `.gitignore`).
- The `eval()` at `AcrossAI_Abilities_Processor.php` line 251 is intentional and must be preserved — it executes admin-defined `php_code` ability callbacks.
- `README.txt` already has `Tested up to: 7.0` and does not need to change.
- The `ignore-codes` input is a **confirmed valid** `WordPress/plugin-check-action@v1` input (verified against the action's `action.yml` `main` branch — maps to `--ignore-codes=<value>` in the CLI). The `exclude-checks` fallback is not needed.
- All existing `phpcs:ignore` comments in place across the codebase remain unchanged; only the `error_log()` guards add new structure around existing ignores.
- No JS build step is required for this feature.
- The `WP_DEBUG_LOG` guard pattern is identical across all 12 call sites — no per-site variation.
- Constitution version bump is PATCH only (`1.4.2` → `1.4.3`) — this is a clarification of an existing compliance principle, not a structural change.
