# Implementation Plan: Plugin Check CI + Compliance Fixes

**Branch**: `020-plugin-check-ci` | **Date**: 2026-05-30 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/020-plugin-check-ci/spec.md`
**Memory Context**: [memory-synthesis.md](memory-synthesis.md)

## Summary

Add a GitHub Actions CI workflow (`WordPress/plugin-check-action@v1`) that runs as a blocking
gate on every PR. Fix all currently-flagged compliance issues: wrap 12 bare `error_log()` calls
across 5 PHP files in `WP_DEBUG_LOG` guards, add a missing `Tested up to: 7.0` plugin header
field, and suppress the intentional `eval()` via `ignore-codes` in the workflow config.
Update `AGENTS.md` and `CONSTITUTION.md` (v1.4.2 → v1.4.3) to encode plugin-check compliance
as a mandatory quality gate.

This feature touches **no REST endpoints, no DB schema, no admin menus, and no hooks**.
It is pure CI config + surgical code-quality fixes.

## Technical Context

**Language/Version**: PHP 7.4+, YAML (GitHub Actions)
**Primary Dependencies**: `WordPress/plugin-check-action@v1`, `shivammathur/setup-php@v2`, `actions/checkout@v4`
**Storage**: N/A
**Testing**: `composer run phpcs`, `composer run phpstan` (level 8)
**Target Platform**: GitHub Actions (ubuntu-latest), WordPress 6.9+
**Project Type**: WordPress plugin + CI pipeline
**Performance Goals**: Plugin Check job completes within 5 minutes on PRs
**Constraints**: Zero PHPCS errors, zero PHPStan level 8 errors after all PHP changes; constitution bump PATCH only
**Scale/Scope**: 9 files (1 new, 8 modified); 12 `error_log()` call sites; no new classes

## Constitution Check

**§I Modular Architecture**: ✅ No new modules introduced. All changes are within existing files.
No new `includes/Base/` directory, no abstract classes, no `register_hooks()` pattern.

**§II WordPress Standards Compliance**: ✅ This feature *adds* a compliance requirement bullet to §II.
All PHP changes must pass WPCS strict + PHPStan level 8 — no waivers.

**§III User-Centric Design**: ✅ Not applicable — no admin UI changes.

**§IV Security First**: ✅ Wrapping `error_log()` in `WP_DEBUG_LOG` guards reduces information
disclosure risk in production. No input/output changes, no new endpoints.

**Architecture Constitution P0 checks**: ✅ All P0 violations non-applicable to this feature.
No enqueue calls, no loader wiring, no REST endpoints, no boolean BerlinDB fields, no McpAdapter calls.

**Memory constraint: AC-FILE-HEADER-PATTERN**: ⚠️ WATCHPOINT — do NOT disturb `@package`, `@subpackage`, `@since` docblocks in the 5 PHP files.

## Project Structure

### Documentation (this feature)

```text
specs/020-plugin-check-ci/
├── spec.md                   ✅ complete
├── memory-synthesis.md       ✅ complete
├── memory.md                 (post-implement)
├── plan.md                   ← this file
└── tasks.md                  (next: /speckit.tasks)
```

### Files Changed (exact, complete list)

```text
.github/
└── workflows/
    └── plugin-check.yml       ← NEW (CHANGE-1)

acrossai-abilities-manager.php ← add Tested up to: 7.0 header (CHANGE-2)

admin/
└── Main.php                   ← wrap line-123 error_log() (CHANGE-3)

includes/
├── Utilities/
│   ├── AcrossAI_Sanitizer.php            ← wrap line-102 error_log() (CHANGE-3)
│   └── AcrossAI_Logger_Formatter.php     ← wrap lines 50, 59, 67 error_log() (CHANGE-3)
└── Modules/
    ├── Logger/
    │   └── AcrossAI_Ability_Logger.php   ← wrap lines 167, 215, 225, 363 error_log() (CHANGE-3)
    └── Abilities/
        └── AcrossAI_Abilities_Processor.php ← wrap lines 243, 256, 261 error_log() (CHANGE-3)
                                              ← eval() at line 251: NO CHANGE (CHANGE-4 = workflow-only)

AGENTS.md                      ← add Plugin Check checklist item (CHANGE-5)
.specify/memory/CONSTITUTION.md ← add §II bullet + v1.4.2→1.4.3 bump (CHANGE-6)
```

**Total: 9 files (1 new, 8 modified). No other files change.**

## Phase 0 — Pre-implementation Verification

Before writing any code, confirm these facts match the live codebase:

| Check | Command | Expected |
|-------|---------|----------|
| .github/workflows/ missing | `ls .github/` | no `workflows/` dir |
| build/ not gitignored | `grep build .gitignore` | no `build` line |
| vendor/ is gitignored | `grep vendor .gitignore` | `vendor/` present |
| Missing "Tested up to" | `grep "Tested up to" acrossai-abilities-manager.php` | no output |
| 12 bare error_log calls | `grep -rn "error_log(" includes/ admin/` | 12 results |
| eval() at line ~251 | `grep -n "eval(" includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` | 1 result |
| ignore-codes is valid input | Confirmed in clarification step | `action.yml` verified ✅ |

## Phase 1 — CHANGE-1: GitHub Actions Workflow

**File**: `.github/workflows/plugin-check.yml` (NEW)
**Action**: Create `.github/workflows/` directory; create the workflow file.

```yaml
name: Plugin Check

on:
  push:
    branches: [main]
  pull_request:

jobs:
  plugin-check:
    name: WordPress Plugin Check
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'

      - run: composer install --no-dev --prefer-dist --no-progress

      - uses: WordPress/plugin-check-action@v1
        with:
          build-dir: '.'
          ignore-codes: 'WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval'
          include-experimental: true
          ignore-warnings: false
```

**Rationale**:
- `composer install --no-dev`: `vendor/` is git-ignored; PHP deps must be installed before plugin-check runs.
- `build-dir: '.'`: `build/` is committed; the plugin root is the target directory.
- `ignore-codes`: Suppresses the intentional `eval()` in `AcrossAI_Abilities_Processor.php`. This is a PHPCS error code token — not a Plugin Check check slug. Using `exclude-checks` would be incorrect here.
- `include-experimental: true` / `ignore-warnings: false`: Maximum strictness on PRs.
- No `npm run build` step needed: `build/` is committed.

## Phase 2 — CHANGE-2: Plugin Header

**File**: `acrossai-abilities-manager.php`
**Action**: Insert one line after `Requires at least: 6.9` (currently line 28):

```
 * Tested up to:      7.0
```

**Exact resulting block** (lines 27–30 after change):
```
 * Requires PHP:      7.4
 * Requires at least: 6.9
 * Tested up to:      7.0
 * Author:            WPBoilerplate
```

No other lines in this file change.

## Phase 3 — CHANGE-3: WP_DEBUG_LOG Guards (12 call sites, 5 files)

**Pattern** (identical across all 12 sites):

```php
// BEFORE:
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
error_log( '...' );

// AFTER:
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( '...' );
}
```

**Implementation rules**:
1. `phpcs:ignore` comment moves INSIDE the `if` block, immediately before `error_log()`.
2. Indentation: use **tabs** (not spaces). The `if` body is indented one tab level deeper than the surrounding scope. (BUG-PHPCBF-TABS watchpoint)
3. Do NOT change message text, variable references, or any surrounding logic.
4. For `admin/Main.php` line 123: the `error_log()` already sits inside an `if ( ! file_exists(...) )` block. Nest the new `if ( defined( 'WP_DEBUG_LOG' ) ... )` guard INSIDE that existing block — do NOT restructure the outer conditional. (BUG-UNCONDITIONAL-ASSET-INCLUDE watchpoint)
5. File headers must remain untouched. (AC-FILE-HEADER-PATTERN watchpoint)

### Call site inventory (exact)

**`admin/Main.php`** (1 call):
- Line 123 (inside `if ( ! file_exists(...) )`): `error_log( 'acrossai-abilities-manager: build/js/abilities.asset.php not found — run npm run build.' )`

**`includes/Utilities/AcrossAI_Sanitizer.php`** (1 call):
- Line 102: `error_log( '[AcrossAI] sanitize_tri_state: unexpected value type ' . gettype( $value ) . ', coercing to null.' )`

**`includes/Utilities/AcrossAI_Logger_Formatter.php`** (3 calls):
- Line 50: `error_log( "Logger: missing required field '{$field}'" )`
- Line 59: `error_log( 'Logger: invalid source value' )`
- Line 67: `error_log( 'Logger: invalid status value' )`

**`includes/Modules/Logger/AcrossAI_Ability_Logger.php`** (4 calls):
- Line 167: `error_log( 'Logger: attempted to finish pending entry but stack is empty' )`
- Line 215: `error_log( 'Logger: entry validation failed' )`
- Line 225: `error_log( 'Logger: failed to insert log entry to database' )`
- Line 363: `error_log( "Logger: Deleted {$result} log entries older than {$cutoff_date}" )`

**`includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`** (3 calls):
- Line 243: `error_log( sprintf( 'acrossai: php_code ability "%s" has empty code — returning [].', $slug ) )`
- Line 256: `error_log( sprintf( 'acrossai: php_code ability "%s" executed successfully.', $slug ) )`
- Line 261: `error_log( sprintf( 'acrossai: php_code ability "%s" threw %s: %s', ... ) )`

**NOT touched** (same file, intentional):
- `AcrossAI_Abilities_Processor.php` line ~251: `eval(...)` — no change.

## Phase 4 — CHANGE-4: eval() suppression

No PHP code changes. CHANGE-1's `ignore-codes: 'WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval'` workflow input handles this at the CI level.

## Phase 5 — CHANGE-5: AGENTS.md Checklist

**File**: `AGENTS.md`
**Action**: Insert one line after `- [ ] Package validation pass (npm run validate-packages)`:

```
- [ ] Plugin Check pass (WordPress/plugin-check-action)
```

The section "Before Commit Checklist" item count increases from 8 to 9.

## Phase 6 — CHANGE-6: CONSTITUTION.md

**File**: `.specify/memory/CONSTITUTION.md`
**Version bump**: `1.4.2` → `1.4.3` (PATCH — new compliance bullet in existing §II, no structural change)

**§II change**: After `No deprecated WordPress functions are permitted.`, insert:

```
The plugin MUST pass the WordPress Plugin Check tool with zero errors and zero warnings,
with only intentional code suppressions (currently: `ignore-codes: WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval`
for the intentional `eval()` in the `php_code` ability type). All new code MUST remain plugin-check clean.
```

**SYNC IMPACT REPORT** HTML comment update (top of file):

```
Version change: 1.4.2 → 1.4.3
Modified sections: §II WordPress Standards Compliance — added Plugin Check mandatory gate bullet
Rationale: Feature 020 adds CI enforcement via WordPress/plugin-check-action@v1; CONSTITUTION updated
to require plugin-check compliance in perpetuity. Only intentional suppression is the eval() in
AcrossAI_Abilities_Processor.php (php_code ability type), recorded via ignore-codes in the workflow.
Templates reviewed:
  - .specify/templates/plan-template.md ✅ reviewed — no outdated references
  - .specify/templates/spec-template.md ✅ reviewed — no outdated references
  - .specify/templates/tasks-template.md ✅ reviewed — no outdated references
  - .specify/templates/checklist-template.md ✅ reviewed — no outdated references
Deferred TODOs: None
```

## Validation Gates

After completing all phases, in order:

1. **PHPCS**: `composer run phpcs` — must exit 0 across all 5 modified PHP files.
2. **PHPStan**: `composer run phpstan` — must exit 0, zero errors at level 8.
3. **Grep guard count**: `grep -c "error_log(" includes/ admin/` — still 12 (no calls removed).
4. **Grep guard presence**: `grep -rn "error_log(" includes/ admin/` — every result preceded by `defined( 'WP_DEBUG_LOG' )`.
5. **Plugin header**: `grep "Tested up to" acrossai-abilities-manager.php` → `Tested up to:      7.0`.
6. **AGENTS.md count**: checklist item count = 9.
7. **Constitution version**: `grep "version\|1.4" .specify/memory/CONSTITUTION.md` → `1.4.3`.
8. **Workflow inputs**: confirm `ignore-codes`, `include-experimental: true`, `ignore-warnings: false` in YAML.

## What Does NOT Change

- `AcrossAI_Abilities_Processor.php`: `eval()` at line ~251 stays, existing PHPCS ignores stay.
- All other existing `phpcs:ignore` comments in all 5 PHP files stay in place and unchanged.
- `README.txt`: already has `Tested up to: 7.0` — do not touch.
- `.gitignore`: `build/` must NOT be added.
- `uninstall.php`: DROP TABLE logic stays unchanged.
- No REST endpoints, DB schema, or any response shapes change.
- No new composer scripts.
- No JS build step.

## Complexity Tracking

No Constitution violations. This plan introduces no new architectural patterns, no new classes,
and no new hooks. All changes are surgical within existing files.
