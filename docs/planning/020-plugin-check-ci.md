# Planning: Plugin Check CI + Compliance Fixes (Feature 020)

Add a GitHub Actions CI workflow that runs the **WordPress Plugin Check Action**
(`WordPress/plugin-check-action@v1`) as a blocking gate on every pull request.
Fix every issue the check currently flags in the codebase.
Update `AGENTS.md` and the Spec-Kit CONSTITUTION so future spec-kit sessions
automatically treat plugin-check compliance as a mandatory quality gate.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "020-plugin-check-ci"

# 2. Specify
/speckit.specify "Add GitHub Actions workflow for WordPress Plugin Check Action on PRs.
Fix all compliance issues the check flags in the current codebase.
Six changes total:
(1) NEW .github/workflows/plugin-check.yml — CI workflow using WordPress/plugin-check-action@v1,
(2) acrossai-abilities-manager.php — add missing Tested up to header field,
(3) Five PHP files — wrap every bare error_log() call in a WP_DEBUG_LOG conditional guard,
(4) .github/workflows/plugin-check.yml — suppress eval PHPCS code via ignore-codes in workflow config
    for the intentional php_code ability type in AcrossAI_Abilities_Processor.php,
(5) AGENTS.md — add plugin-check to Before Commit Checklist,
(6) .specify/memory/CONSTITUTION.md — add plugin-check as mandatory gate in §II WordPress Standards."
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | `.github/` directory already exists (contains `agents/`, `prompts/`, `copilot-instructions.md`); only `.github/workflows/` is missing and must be created by this feature | `ls .github/` |
| B-2 | `build/` directory IS committed to git (not in `.gitignore`); no `npm run build` step is needed in CI | `grep build .gitignore` |
| B-3 | `vendor/` is git-ignored; `composer install --no-dev` MUST run before plugin-check | `grep vendor .gitignore` |
| B-4 | Main plugin file header is missing `Tested up to:` field; `README.txt` already has `Tested up to: 7.0` | `grep "Tested up to" acrossai-abilities-manager.php README.txt` |
| B-5 | All 12 bare `error_log()` calls already carry `// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log` on the preceding line — PHPCS is already suppressed; Plugin Check runs independently and will still flag them | `grep -rn "error_log(" includes/ admin/` |
| B-6 | The single `eval()` call at `AcrossAI_Abilities_Processor.php:251` already carries PHPCS ignores (`Squiz.PHP.Eval.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval`) and is intentional (executes admin-defined `php_code` ability callbacks); the workflow must suppress this specific PHPCS error code via `ignore-codes`, not remove the code | `grep -n "eval(" includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` |
| B-7 | Two `$wpdb->query()` DROP TABLE calls in `uninstall.php` already have `phpcs:ignore` for `DirectQuery,SchemaChange`; Plugin Check treats DDL in `uninstall.php` as an acceptable schema-change context and does NOT flag these as errors | read `uninstall.php` |
| B-8 | `AcrossAI_Ability_Logs_Query.php` has one `$wpdb->get_var()` with a table-name interpolation; it carries a phpcs:ignore and Plugin Check does not flag this as an error | `grep -n "get_var" includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php` |
| B-9 | `npm run build` script is `wp-scripts build`; the built `build/` output is already committed | `grep build package.json` |
| B-10 | `AGENTS.md` "Before Commit Checklist" currently has 8 items and does NOT include plugin-check | read `AGENTS.md` lines 62–70 |

---

## Error Log Inventory — every call that needs a guard (CHANGE-3)

| File | Line | Current bare call |
|------|------|-------------------|
| `admin/Main.php` | 123 | `error_log( 'acrossai-abilities-manager: build/js/abilities.asset.php not found — run npm run build.' )` |
| `includes/Utilities/AcrossAI_Sanitizer.php` | 102 | `error_log( '[AcrossAI] sanitize_tri_state: unexpected value type ' . gettype( $value ) . ', coercing to null.' )` |
| `includes/Utilities/AcrossAI_Logger_Formatter.php` | 50 | `error_log( "Logger: missing required field '{$field}'" )` |
| `includes/Utilities/AcrossAI_Logger_Formatter.php` | 59 | `error_log( 'Logger: invalid source value' )` |
| `includes/Utilities/AcrossAI_Logger_Formatter.php` | 67 | `error_log( 'Logger: invalid status value' )` |
| `includes/Modules/Logger/AcrossAI_Ability_Logger.php` | 167 | `error_log( 'Logger: attempted to finish pending entry but stack is empty' )` |
| `includes/Modules/Logger/AcrossAI_Ability_Logger.php` | 215 | `error_log( 'Logger: entry validation failed' )` |
| `includes/Modules/Logger/AcrossAI_Ability_Logger.php` | 225 | `error_log( 'Logger: failed to insert log entry to database' )` |
| `includes/Modules/Logger/AcrossAI_Ability_Logger.php` | 363 | `error_log( "Logger: Deleted {$result} log entries older than {$cutoff_date}" )` |
| `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` | 243 | `error_log( sprintf( 'acrossai: php_code ability "%s" has empty code — returning [].', $slug ) )` |
| `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` | 256 | `error_log( sprintf( 'acrossai: php_code ability "%s" executed successfully.', $slug ) )` |
| `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` | 261 | `error_log( sprintf( 'acrossai: php_code ability "%s" threw %s: %s', ...) )` |

**Total: 12 calls across 5 files.**

---

## CHANGE-1 — NEW `.github/workflows/plugin-check.yml`

Create the directory `.github/workflows/` inside the already-existing `.github/` directory,
and add the workflow file.

**Trigger**: `push` to `main` branch AND all `pull_request` events.

**Steps**:
1. `actions/checkout@v4` — full checkout (no depth limit needed).
2. `shivammathur/setup-php@v2` — PHP 8.1, no extensions beyond defaults.
3. `composer install --no-dev --prefer-dist --no-progress` — install PHP deps; vendor is git-ignored so this is required.
4. `WordPress/plugin-check-action@v1` — run the check.

**Plugin Check Action inputs**:

| Input | Value | Reason |
|-------|-------|--------|
| `build-dir` | `.` (repo root) | `build/` is committed; the plugin root is the correct target |
| `ignore-codes` | `WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` | Suppresses the false-positive on the intentional `eval()` in `AcrossAI_Abilities_Processor.php`; this is a PHPCS error code, not a Plugin Check check slug — `ignore-codes` is the correct input, not `exclude-checks` |
| `include-experimental` | `true` | Enables all experimental checks for maximum strictness on PRs |
| `ignore-warnings` | `false` | Fail on warnings too — enforce the highest bar |

> **Pre-implementation verification required — confirm `ignore-codes` is a valid action input**
> before writing the YAML. Run:
> ```bash
> curl -s https://raw.githubusercontent.com/WordPress/plugin-check-action/main/action.yml \
>   | grep -A2 "ignore-codes\|exclude-checks\|ignore-warnings"
> ```
> If `ignore-codes` appears in the output, proceed as documented.
> If it does NOT appear, the fallback is `exclude-checks` with the Plugin Check check slug
> (not the PHPCS code) — find the correct slug via `wp plugin check --list-checks` on a
> local WP install with the Plugin Check plugin active, then update the workflow and CHANGE-4
> accordingly before implementing.

**Full workflow YAML outline** (implementer fills in exact YAML):

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

---

## CHANGE-2 — `acrossai-abilities-manager.php` — add `Tested up to:` header

The plugin file header block (lines 20–35) is missing the `Tested up to:` field.
`README.txt` already has `Tested up to: 7.0`.

Add one line to the plugin header, after `Requires at least: 6.9`:

```
 * Tested up to:      7.0
```

The final header block order must be:
```
 * Requires PHP:      7.4
 * Requires at least: 6.9
 * Tested up to:      7.0
 * Author:            WPBoilerplate
```

No other lines in the file change.

---

## CHANGE-3 — Wrap all `error_log()` calls in `WP_DEBUG_LOG` guard

**Pattern to apply to every call in the inventory above**:

```php
// Before (current — bare call, Plugin Check flags as ERROR):
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
error_log( '...' );

// After (Plugin Check compliant — only fires when debug logging is active):
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log( '...' );
}
```

**Key rules**:
- The `phpcs:ignore` comment moves INSIDE the `if` block, on the line immediately before the `error_log()` call.
- The condition `defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG` is the canonical WordPress pattern — never use `WP_DEBUG` alone, never use `ini_get`.
- Do NOT change the message text, variable references, or surrounding logic in any of the 5 files.
- After wrapping, `AcrossAI_Ability_Logger.php` line 363 (`Deleted {$result} log entries`) changes from an unconditional info log to a debug-only log. This is intentional and correct for a production plugin.
- PHPStan level 8 must still pass after every file change (the `if` block does not introduce type issues).

**Files touched**: `admin/Main.php`, `includes/Utilities/AcrossAI_Sanitizer.php`,
`includes/Utilities/AcrossAI_Logger_Formatter.php`,
`includes/Modules/Logger/AcrossAI_Ability_Logger.php`,
`includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`.

---

## CHANGE-4 — `eval()` handling (workflow-level, not code-level)

The `eval()` call at `AcrossAI_Abilities_Processor.php:251` is intentional and already
carries the correct PHPCS ignores. It MUST NOT be removed — the `php_code` ability type
depends on it.

The fix is **workflow-side only**: the `ignore-codes: 'WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval'`
input in CHANGE-1 handles this. `ignore-codes` takes PHPCS error codes (the token after the `//` in a
`phpcs:ignore` comment), not Plugin Check check slugs — that distinction is why `exclude-checks`
would be wrong here. No changes are made to `AcrossAI_Abilities_Processor.php` beyond what already exists.

---

## CHANGE-5 — `AGENTS.md` — Before Commit Checklist

Add one bullet to the existing checklist (lines 62–70):

```
- [ ] Plugin Check pass (WordPress/plugin-check-action)
```

Insert after `- [ ] Package validation pass (npm run validate-packages)` so it is the
final item. The section header and other items do not change.

---

## CHANGE-6 — `.specify/memory/CONSTITUTION.md` — §II WordPress Standards Compliance

In the "§II. WordPress Standards Compliance" section, after the existing bullet:

> No deprecated WordPress functions are permitted.

Add one new bullet:

> The plugin MUST pass the WordPress Plugin Check tool with zero errors and zero warnings,
> with only intentional code suppressions (currently: `ignore-codes: WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval`
> for the intentional `eval()` in the `php_code` ability type). All new code MUST remain plugin-check clean.

Bump constitution version from `1.4.2` → `1.4.3` (PATCH — clarification of existing
compliance principle, no structural change).

Update the HTML comment sync report at the top accordingly.

---

## What must NOT change

- Do not modify `AcrossAI_Abilities_Processor.php` beyond what already exists — `eval()` stays.
- Do not remove or modify any PHPCS ignore comments already in place.
- Do not add `build/` to `.gitignore` — built assets are intentionally committed.
- Do not change any REST endpoint, DB schema, or REST response shape.
- Do not change the `uninstall.php` DROP TABLE logic — it is already properly annotated and plugin-check does not flag DDL in `uninstall.php` as an error.
- Do not change `README.txt` — `Tested up to: 7.0` is already correct there.
- Do not add a new composer script — the workflow calls `composer install` directly.

---

## CONSTRAINTS

- Exactly 9 files change:
  `.github/workflows/plugin-check.yml` (new),
  `acrossai-abilities-manager.php`,
  `admin/Main.php`,
  `includes/Utilities/AcrossAI_Sanitizer.php`,
  `includes/Utilities/AcrossAI_Logger_Formatter.php`,
  `includes/Modules/Logger/AcrossAI_Ability_Logger.php`,
  `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`,
  `AGENTS.md`,
  `.specify/memory/CONSTITUTION.md`.
  *(1 new, 8 modified.)*
- PHPStan level 8 must pass with zero errors after all PHP changes.
- PHPCS must pass with zero errors after all PHP changes.
- No JS build step is required for this feature.
- The `WP_DEBUG_LOG` guard pattern must be identical across all 12 call sites — no variation.
- Constitution version bump must be PATCH only (1.4.2 → 1.4.3).

---

## Spec-kit Commands

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpcs
composer run phpstan

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

### CHANGE-1 — Workflow file

- [ ] `.github/workflows/plugin-check.yml` exists.
- [ ] Workflow triggers on `push` to `main` and on all `pull_request` events.
- [ ] `composer install --no-dev` step is present before the plugin-check step.
- [ ] `ignore-codes` is set to `WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` (not `exclude-checks`).
- [ ] `include-experimental: true` is set.
- [ ] `ignore-warnings: false` is set.
- [ ] On a test PR, the workflow runs and appears in the PR checks list.

### CHANGE-2 — Plugin header

- [ ] `grep "Tested up to" acrossai-abilities-manager.php` returns `Tested up to:      7.0`.
- [ ] No other line in the plugin header was changed.

### CHANGE-3 — error_log guards

- [ ] `grep -rn "error_log(" includes/ admin/` — every result appears inside an `if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG )` block.
- [ ] `grep -c "error_log(" includes/ admin/` — total count is still 12 (no calls removed).
- [ ] `composer run phpstan` — zero errors in all 5 modified files.
- [ ] `composer run phpcs` — zero errors in all 5 modified files.

### CHANGE-4 — eval code suppression

- [ ] `eval()` still exists at `AcrossAI_Abilities_Processor.php:251` (no code removed).
- [ ] Workflow uses `ignore-codes`, NOT `exclude-checks` — confirm the key name in the YAML.
- [ ] `ignore-codes` value is exactly `WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval`.
- [ ] Plugin Check run in CI does NOT report an error or warning for the eval line.

### CHANGE-5 — AGENTS.md

- [ ] `grep "Plugin Check" AGENTS.md` returns the new checklist bullet.
- [ ] Total checklist items in "Before Commit Checklist" is now 9 (was 8).

### CHANGE-6 — Constitution

- [ ] `grep "plugin-check" .specify/memory/CONSTITUTION.md` returns the new bullet in §II.
- [ ] Constitution version line reads `1.4.3`.
- [ ] HTML sync report comment at the top lists `1.4.2 → 1.4.3` and notes the added §II bullet.

### Full Plugin Check gate

- [ ] Run `wp plugin check acrossai-abilities-manager` locally (requires Plugin Check plugin active).
  Expected output: **0 errors, 0 warnings** (after all fixes applied).
- [ ] On a PR branch, the "WordPress Plugin Check" GitHub Actions job completes green.
- [ ] On a PR that intentionally introduces a bare `error_log()` without a guard, the CI job fails
      — confirms the gate is blocking.
