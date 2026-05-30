# Feature Specification: CI Workflows Split and PHPCS Config Cleanup

**Feature Branch**: `022-ci-workflows-phpcs-cleanup`
**Created**: 2026-05-31
**Status**: Done
**Input**: Split CI quality gates into dedicated workflows; clean phpcs.xml.dist to eliminate false positives and dead exclusions; fix all pre-existing code-quality violations surfaced by the tighter scan.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Dedicated CI workflows per quality gate (Priority: P1)

A developer opens a pull request. Three separate CI jobs run: PHPCS (WordPress coding standards), PHPStan (static analysis, level 8), and PHPCompatibility (PHP 7.4+ compatibility). Each job is scoped to the files it actually needs to check. A failure in one job does not mask failures in another.

**Why this priority**: Combining all checks into one job hides root causes. Separate jobs give developers instant, targeted feedback.

**Acceptance Scenarios**:

1. **Given** a PR is opened, **When** a PHPCS violation exists in a production PHP file, **Then** the PHPCS workflow fails and identifies the exact file and rule.
2. **Given** a PR is opened, **When** a PHPStan type error exists, **Then** the PHPStan workflow fails independently of PHPCS.
3. **Given** a PR is opened, **When** code uses a PHP 8.0+ API incompatible with 7.4, **Then** the PHPCompatibility workflow fails.
4. **Given** a PR is opened with clean code, **When** all three workflows run, **Then** all three pass independently.

---

### User Story 2 — `composer run phpcs` exits 0 with no violations (Priority: P1)

A developer runs `composer run phpcs` locally before pushing. The command exits 0. No false positives from dev/test/spec-kit paths. No dead exclusions suppressing rules the code already satisfies.

**Why this priority**: If local PHPCS is noisy or exits non-zero, developers stop trusting it. Clean local output makes the standard enforceable.

**Acceptance Scenarios**:

1. **Given** `composer run phpcs` is run, **When** all production PHP files are compliant, **Then** exit code is 0 and output shows only progress dots.
2. **Given** a file in `tests/`, `specs/`, `docs/`, `.specify/`, or `.github/` is modified, **When** `composer run phpcs` runs, **Then** that file is not scanned.
3. **Given** PHPCompatibility is removed from `phpcs.xml.dist`, **When** `composer run phpcs` runs, **Then** no PHPCompatibility findings appear (they now run only in CI via `phpcompat.yml`).

---

### User Story 3 — Code quality violations resolved (Priority: P2)

A developer reads any production PHP file and sees modern, consistent code. No legacy PSR-1 underscore-prefix properties. No missing file docblocks. No inline comments without terminal punctuation.

**Acceptance Scenarios**:

1. **Given** any class in the plugin, **When** it uses the singleton pattern, **Then** the instance property is named `$instance` (not `$_instance`).
2. **Given** any PHP file, **When** it declares a namespace, **Then** it has a file-level docblock immediately after `<?php`.
3. **Given** any inline comment, **When** it appears in production code, **Then** it ends with `.`, `!`, or `?`.

---

## Success Criteria

- `composer run phpcs` exits 0 across all 49 scanned files
- Three separate workflow files exist: `phpcs.yml`, `phpstan.yml`, `phpcompat.yml`
- `phpcs.xml.dist` contains no dead exclusions (all active exclusions protect genuine, intentional code patterns)
- PHPCompatibility check is scoped to production directories only (`includes/`, `admin/`, `public/`, root PHP files)
- `$_instance` and other underscore-prefixed properties removed from all production classes
- Reflection-based tests updated to match renamed properties

## Scope

**In scope**: `.github/workflows/`, `phpcs.xml.dist`, all production PHP files with pre-existing violations, `tests/phpunit/sitewide/AbilityOverrideProcessorTest.php` (reflection string updates only).

**Out of scope**: New features, REST endpoints, DB schema, admin UI, plugin version bump.

## Dependencies

- Feature 020 (Plugin Check CI baseline) — must be merged first
- Feature 021 (Plugin Check Remaining Cleanup) — must be merged first; this feature builds on the clean state 021 left
