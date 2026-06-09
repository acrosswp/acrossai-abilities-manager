# Feature Specification: BerlinDB Upgrade, PHP 8.1 Minimum, REST Audit, Abilities UI Fixes

**Feature Branch**: `028-berlindb-upgrade-rest-audit-ui-fixes`
**Created**: 2026-06-09
**Status**: Draft
**Input**: User description: "BerlinDB ^3.0.0 upgrade, PHP 8.1 minimum version bump, REST permission_callback compliance audit, remove X of Y items label from abilities table, right-align bottom pagination"

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Dependency Health: BerlinDB 3.0 (Priority: P1)

A plugin maintainer wants the plugin's dependency tree to resolve against BerlinDB 3.0. The current lock file pins BerlinDB 2.0.2, but the coordinating `wpb-access-control` package has released v1.2.0 with full BerlinDB 3.0 support. The maintainer runs a single Composer update command and the plugin installs cleanly with no conflicts.

**Why this priority**: BerlinDB 2.x is no longer the current release series. Staying on it blocks future upgrades and carries unpatched risk. This is the foundational change that unblocks the PHP minimum bump.

**Independent Test**: Can be fully tested by running `composer install` from a clean checkout and verifying `berlindb/core 3.0.0` appears in the lock file. Delivers the value of a current, conflict-free dependency tree independently of any other story.

**Acceptance Scenarios**:

1. **Given** a clean checkout of the branch, **When** `composer install --no-dev` is run, **Then** it exits 0 and `berlindb/core 3.0.0` is present in the installed packages.
2. **Given** the updated lock file, **When** PHPStan level 8 runs against `includes/`, **Then** it reports no errors.
3. **Given** the updated lock file, **When** PHPCS runs against production PHP files, **Then** it reports no new errors.

---

### User Story 2 — PHP Version Floor: Declare 8.1 Minimum (Priority: P2)

A plugin maintainer wants every place that declares the minimum PHP version to say `8.1` instead of `7.4`. This includes the plugin file header, README, Composer constraint, CI compatibility check, CI unit test matrix (PHP 8.1–8.5), governance documents, and agent skill definitions.

**Why this priority**: PHP 7.4 reached end-of-life in November 2022. The existing dev toolchain already requires PHP 8.2+, making the `7.4` declaration misleading and the CI compatibility check redundant. Raising the floor ensures CI enforces modern PHP and the plugin can use modern PHP features going forward.

**Independent Test**: Can be fully tested by checking all declaration files (`grep "Requires PHP" ...`, `grep '"php"' composer.json`, CI workflow content) and confirming each reads `8.1`. Delivers clean, consistent version messaging independently of the dependency upgrade.

**Acceptance Scenarios**:

1. **Given** the updated branch, **When** `grep "Requires PHP" acrossai-abilities-manager.php README.txt` is run, **Then** both return `8.1`.
2. **Given** the updated `phpcompat.yml`, **When** the PHP Compatibility CI job runs, **Then** the job is named "PHP 8.1+ Compatibility" and scans for `8.1` and above.
3. **Given** the new `phpunit.yml`, **When** it is triggered by a pull request, **Then** five parallel jobs named "PHPUnit (PHP 8.1)" through "PHPUnit (PHP 8.5)" run and all pass.
4. **Given** the updated CONSTITUTION.md and agent skill file, **When** a future Spec Kit plan is generated, **Then** it references PHP 8.1 as the minimum version.

---

### User Story 3 — REST Security: Permission Callback Audit (Priority: P3)

A plugin maintainer runs an audit of all REST routes registered by the plugin to confirm every route carries a non-trivial `permission_callback`. The audit either confirms full compliance (recorded in the PR) or surfaces any gap that is fixed before merge.

**Why this priority**: WordPress Plugin Check and the WordPress.org review process flag routes missing a `permission_callback`. Confirming compliance now prevents a future Plugin Check failure and documents the security posture of the API surface.

**Independent Test**: Can be fully tested by running `grep -rn "register_rest_route\|permission_callback\|__return_true" includes/` and confirming every `register_rest_route()` call has a corresponding `permission_callback` key that is not `'__return_true'`. Delivers a documented audit result independently.

**Acceptance Scenarios**:

1. **Given** the plugin codebase, **When** `grep -rn "__return_true" includes/` is run, **Then** it returns no results.
2. **Given** all REST controller files under `includes/Modules/`, **When** reviewed for `register_rest_route()` calls, **Then** every call has a `permission_callback` key set to a proper gating callable (not a bare `true`-returning closure).
3. **Given** a request to any REST endpoint without authentication, **When** the `permission_callback` is evaluated, **Then** it returns `WP_Error` with HTTP 403.

---

### User Story 4 — Abilities Table UI: Remove Redundant Item Count (Priority: P4)

An admin user browsing the abilities table no longer sees the `X of Y items` text in the top navigation bar. The pagination controls already convey the same position information, so the removal makes the top bar less cluttered.

**Why this priority**: Pure UI cleanup. No functional regression risk; it removes a redundant element. Lower priority because it delivers aesthetic value rather than correctness.

**Independent Test**: Can be fully tested by loading the abilities list page and confirming no "of X items" text appears in the top tablenav. Delivers a cleaner UI independently of all other stories.

**Acceptance Scenarios**:

1. **Given** the abilities list page with at least one ability loaded, **When** the top tablenav is inspected, **Then** no `X of Y items` or loading text appears in that area.
2. **Given** the abilities list page while data is loading, **When** the top tablenav is inspected, **Then** no loading placeholder text appears in that area.
3. **Given** the removal, **When** the remaining top pagination controls are used, **Then** they continue to function correctly with no console errors.

---

### User Story 5 — Abilities Table UI: Right-align Bottom Pagination (Priority: P5)

An admin user sees the bottom pagination of the abilities table aligned to the right side, matching the convention used in standard WordPress admin list tables.

**Why this priority**: Pure visual consistency fix. Lowest priority because it is a one-line CSS change with no functional impact.

**Independent Test**: Can be fully tested by loading the abilities list page and visually confirming the bottom pagination is right-aligned. Delivers consistent visual alignment independently.

**Acceptance Scenarios**:

1. **Given** the abilities list page with paginated results, **When** the bottom of the table is inspected, **Then** the pagination links are right-aligned.
2. **Given** the CSS change, **When** the top tablenav pagination is inspected, **Then** its alignment is unchanged.

---

### Edge Cases

- What happens if Composer cannot resolve `wpb-access-control v1.2.0` from the VCS entry? — Composer install must fail with a clear error; no silent fallback to v1.1.1.
- What happens if a PHP 8.5 build is released with breaking changes before this branch merges? — The `fail-fast: false` matrix flag ensures the remaining PHP versions still report; the 8.5 failure is surfaced, not silently skipped.
- What happens if an ability has zero results and the table shows "0 items"? — After removing the `X of Y items` block, this label disappears entirely; the pagination's own item count is the only source of truth.
- What happens if a REST route is found without a `permission_callback`? — The route must be fixed in this feature; the branch cannot be merged with a missing callback.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST declare `berlindb/core ^3.0.0` as a Composer dependency and resolve it without conflicts.
- **FR-002**: The plugin MUST declare `wpboilerplate/wpb-access-control ^1.2.0` as a Composer dependency, resolved via the VCS repository entry.
- **FR-003**: The plugin MUST declare PHP 8.1 as the minimum supported version in all authoritative locations: plugin header, README, `composer.json`, CI, governance documents, and agent configuration.
- **FR-004**: CI MUST run PHPUnit against PHP 8.1, 8.2, 8.3, 8.4, and 8.5 in parallel, with all five jobs required to pass.
- **FR-005**: CI MUST run the PHP Compatibility check with `testVersion 8.1-` (not `7.4-`).
- **FR-006**: Every `register_rest_route()` call in `includes/` MUST have a `permission_callback` that gates on `manage_options` and verifies the REST nonce.
- **FR-007**: No REST route MAY use `'__return_true'` or an unconditionally-true closure as its `permission_callback`.
- **FR-008**: The abilities list page MUST NOT display an `X of Y items` text label in the top tablenav area.
- **FR-009**: The bottom pagination row of the abilities table MUST be right-aligned.
- **FR-010**: PHPStan level 8 MUST pass with no errors after all changes.
- **FR-011**: PHPCS MUST report no new errors on changed production PHP files after all changes.

### Key Entities

- **Composer lock file**: Records the exact resolved versions of all dependencies; `berlindb/core 3.0.0` and `wpboilerplate/wpb-access-control v1.2.0` must appear after CHANGE-1.
- **CI workflow matrix**: The PHPUnit workflow's `strategy.matrix.php` array defines which PHP versions are tested; must include exactly `['8.1', '8.2', '8.3', '8.4', '8.5']`.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: `composer install` completes without conflict errors and installs `berlindb/core 3.0.0`.
- **SC-002**: All five PHPUnit CI jobs (PHP 8.1–8.5) pass green on the first run after the branch is pushed.
- **SC-003**: The PHP Compatibility CI job passes with `testVersion 8.1-` on the first run.
- **SC-004**: Zero REST routes with missing or trivially-open `permission_callback` — confirmed by grep and recorded in PR description.
- **SC-005**: The abilities list page renders with no `X of Y items` text and no JavaScript console errors.
- **SC-006**: Bottom pagination is visually right-aligned on all screen widths used in WordPress admin.
- **SC-007**: PHPStan level 8 passes with zero new errors compared to the base branch.

---

## Assumptions

- `wpb-access-control v1.2.0` is already tagged on GitHub (`WPBoilerplate/wpb-access-control`) and the VCS repository entry in `composer.json` resolves it correctly; no changes to the upstream package source are needed.
- The plugin is distributed via WordPress.org or a standard WordPress install; the PHP 8.1 floor is appropriate for the target deployment environment.
- The abilities table component is the only React component affected by the `X of Y items` removal; no other component renders this string.
- The bottom pagination alignment change is purely cosmetic; no JavaScript logic depends on the current left-alignment.
- All five PHP versions in the matrix (8.1–8.5) are available via the `shivammathur/setup-php` action used in existing CI workflows.
- PHPUnit tests are self-contained and do not require a running WordPress instance (they use stubs/mocks already configured in `phpunit.xml.dist`).
