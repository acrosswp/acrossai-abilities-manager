# Memory Synthesis

## Current Scope

Feature 028 makes five parallel changes: (1) upgrade `berlindb/core` to ^3.0.0 via `wpb-access-control v1.2.0`, (2) bump the PHP minimum floor from 7.4 to 8.1 across all declaration sites, (3) audit REST `permission_callback` compliance, (4) remove the `X of Y items` label from the abilities table top tablenav, (5) right-align the bottom pagination. Affected modules: Composer/vendor, CI workflows (`.github/workflows/`), plugin header + README, CONSTITUTION.md, AGENTS.md, agent skill file, `src/js/abilities/components/AbilitiesList.jsx`, `src/scss/abilities/admin.scss`.

## Relevant Decisions

- **DEC-TABLE-SOFT-SINGLETON** — BerlinDB Table subclasses use soft singleton (no private `__construct`); `AcrossAI_Abilities_Table` must retain this after the v3 upgrade. (Reason: Activator and tests instantiate via `new`; blocking constructor causes fatal. Source: DECISIONS.md)
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE** — After upgrading any library that affects security-critical code, re-run SEC-04 (strict comparison in access checks), SEC-03 (`$global = false` table isolation), and DEC-PERM-CB (permission callback injection). (Reason: directly triggered by wpb-access-control + BerlinDB upgrade. Source: DECISIONS.md)
- **DEC-STABLE-UPGRADE-WINDOW** — Target the first stable release in the current/prior week. `v1.2.0` was released 2026-06-09 — correct target; no need to wait for v1.2.1. (Reason: upgrade risk guidance. Source: DECISIONS.md)
- **DEC-PLUGIN-CHECK-PRODUCTION-SURFACE** — Every `register_rest_route()` must have a non-trivial `permission_callback`; REST audit is a direct Plugin Check compliance check. (Reason: Feature 028 CHANGE-3 is a Plugin Check audit. Source: DECISIONS.md)
- **DEC-PERM-CB** — `permission_callback` injected via Override Processor must use a fail-open → admin-notice pattern for missing AC library. Audit must confirm this wrapper is still intact after BerlinDB upgrade. (Reason: security-critical; affected by wpb-access-control changes. Source: DECISIONS.md)

## Active Architecture Constraints

- **PATTERN-CI-WORKFLOW-HARDENING** — New `phpunit.yml` MUST: SHA-pin all `uses:` references (copy same pinned hashes from `phpstan.yml`/`phpcompat.yml`), declare `permissions: {}` at workflow level, set `timeout-minutes: 15`. (Reason: Feature 028 creates a new CI workflow. Source: ARCHITECTURE.md)
- **PATTERN-CI-QUALITY-GATE-SPLIT** — Existing split is phpcs/phpstan/phpcompat; `phpunit.yml` adds a fourth orthogonal gate. Keep concerns separated — do not merge PHPUnit into phpcompat or phpstan jobs. (Reason: Feature 028 adds phpunit.yml. Source: ARCHITECTURE.md)
- **ARCH-PHPUNIT-BOOTSTRAP** — `tests/bootstrap.php` must define `ABSPATH` before `require_once vendor/autoload.php`. `phpunit.xml.dist` must exclude files that transitively load BerlinDB Table subclasses (`AbilitiesQueryTest`, `AbilitiesWriteControllerTest`, etc.) because Table constructors call `add_action()`/`get_option()` unavailable in stub bootstrap. This applies to ALL five PHP matrix versions. (Reason: PHP 8.1–8.5 matrix; BerlinDB upgrade changes Table constructor behaviour. Source: ARCHITECTURE.md)
- **PATTERN-ENQUEUE-PAGE-GUARD** — `is_*_page()` helpers + Yoda `===`; relevant if any enqueue or admin hook is touched during the PHP-version-bump edits. (Reason: DEC-MENU-HOOK-SUFFIX. Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEC-FEATURE-027-NO-TESTS** — Feature 027 shipped without unit tests for `AcrossAI_Ability_API_Config` and Processor; these remain tech-debt test candidates. Feature 028 does not add tests for those classes — consistent with the accepted deviation. (Reason: no new test scope added in 028. Source: DECISIONS.md)

## Relevant Security Constraints

- **SEC-03** — `AcrossAI_Abilities_Table::$global = false` — verify this property survives the BerlinDB v3 namespace refactor in `wpb-access-control`. (Reason: DEC-REVALIDATE-SECURITY-POST-UPGRADE triggered by BerlinDB upgrade. Source: security-constraints.md)
- **SEC-04** — Strict type comparison in access checks — verify `wpb-access-control v1.2.0` `user_has_access()` still uses `===`/`!==`, not loose `==`. (Reason: post-upgrade revalidation. Source: security-constraints.md)
- **DEC-PERM-CB** — REST permission callbacks injected by Override Processor must remain intact and fail-open with admin notice when AC library is absent. Audit CHANGE-3 must verify the wrapper survives the upgrade. (Reason: any change to wpb-access-control class signatures could break the callback injection. Source: security-constraints.md)

## Related Historical Lessons

- **BUG-PHPUNIT-BERLINDDB-SCOPE** — `phpunit.xml.dist` must be narrowly scoped; BerlinDB Table constructors fatal under stub bootstrap. The PHP 8.1–8.5 matrix will surface this as a fatal on all five runners if scope is wrong — fix before matrix runs. (Reason: directly relevant to phpunit matrix addition.)
- **BUG-PHPUNIT-ABSPATH-SILENT-EXIT** — ABSPATH define must precede Composer autoloader in `tests/bootstrap.php`; wrong order produces 0 tests silently. Verify order is correct before the matrix goes live. (Reason: silent failure mode invisible in matrix output.)
- **BUG-PHPSTAN-SILENT-PASS** — PHPStan exit 0 + no output = clean pass; do not interpret silence as an error after BerlinDB upgrade. (Reason: validates the PHPStan gate for CHANGE-1.)

## Conflict Warnings

None. All five changes align with active decisions and constitution rules. The upgrade to `wpb-access-control v1.2.0` / `berlindb/core 3.0.0` triggers **DEC-REVALIDATE-SECURITY-POST-UPGRADE** (soft advisory, not a blocker): run the SEC-03, SEC-04, and DEC-PERM-CB validation checks as part of CHANGE-1 acceptance — document the result in the PR.

## Retrieval Notes

- 20 index entries considered; 5 decisions selected (DEC-TABLE-SOFT-SINGLETON, DEC-REVALIDATE-SECURITY-POST-UPGRADE, DEC-STABLE-UPGRADE-WINDOW, DEC-PLUGIN-CHECK-PRODUCTION-SURFACE, DEC-PERM-CB); 4 architecture constraints selected; 1 accepted deviation; 3 security constraints; 3 bug patterns.
- Source sections read: DECISIONS.md (upgrade/BerlinDB), ARCHITECTURE.md (CI patterns), BUGS.md (PHPUnit bootstrap).
- Budget status: within 900-word limit.
- Entries not selected (low relevance to 028): React/UI decisions, AbilityForm section order, Logger namespace, DataViews renderer patterns, uninstall gate patterns.
