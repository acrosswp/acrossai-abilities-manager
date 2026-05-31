# Feature Specification: Rebrand, Cleanup, and Namespace Fix

**Feature Branch**: `023-fix-public-namespace-reserved-keyword`
**Created**: 2026-05-31
**Status**: In Progress
**GitHub Issue**: #28
**Input**: Multiple manual changes made on `main`: full rebrand from WPBoilerplate → AcrossWP, plugin header updates, uninstall.php gate fix, Logger query refactor, plugin-check.yml removal, and the pending namespace reserved-keyword fix.

## User Scenarios & Testing

### User Story 1 — Plugin identity reflects the correct owner (Priority: P1)

All file headers, plugin metadata, and URLs reference `acrosswp` and `AcrossWP` instead of the old `WPBoilerplate` / `wpboilerplate` values.

**Acceptance Scenarios**:

1. **Given** any production PHP file, **When** the `@author` tag is read, **Then** it shows `AcrossWP <deepak@acrosswp.com>`.
2. **Given** the plugin header in `acrossai-abilities-manager.php`, **When** displayed in WordPress admin, **Then** it shows the correct Plugin URI, Description, Author, and Author URI.
3. **Given** `composer.json`, **When** the support URL is read, **Then** it points to `https://github.com/acrosswp/acrossai-abilities-manager/issues`.

---

### User Story 2 — Uninstall only removes options when delete-data gate is set (Priority: P1)

When a user uninstalls the plugin without the delete-data option set, plugin settings options are preserved. Options are removed only when the user has explicitly opted in to data deletion.

**Acceptance Scenarios**:

1. **Given** `acrossai_abilities_uninstall_delete_data` is falsy, **When** the plugin is uninstalled, **Then** `acrossai_abilities_log_retention_days` and `acrossai_abilities_uninstall_delete_data` are NOT deleted.
2. **Given** `acrossai_abilities_uninstall_delete_data` is truthy, **When** the plugin is uninstalled, **Then** tables are dropped AND both options are deleted.

---

### User Story 3 — PHPCompatibility CI passes with no ignored files (Priority: P1)

`public/Main.php` namespace is renamed to avoid the reserved keyword `public`. The `--ignore=public/Main.php` workaround in `phpcompat.yml` is removed.

**Acceptance Scenarios**:

1. **Given** the namespace is renamed, **When** PHP Compatibility CI runs, **Then** it passes with no `--ignore` flags needed.
2. **Given** the namespace is renamed, **When** `includes/Main.php` instantiates the public-facing class, **Then** the plugin loads correctly.

---

### User Story 4 — Logger query uses spread operator for $wpdb->prepare() (Priority: P2)

`AcrossAI_Logger_Query.php` passes query parameters via spread operator instead of array, improving linter clarity.

---

## Success Criteria

- All `@author` and `@link` tags updated to AcrossWP/acrosswp across all modified files
- Plugin header in `acrossai-abilities-manager.php` reflects correct Plugin URI, Description, Author
- `uninstall.php` — `delete_option` calls inside `$acrossai_delete_data` gate only
- `namespace AcrossAI_Abilities_Manager\Public` no longer exists anywhere
- `--ignore=public/Main.php` removed from `phpcompat.yml`
- `composer run phpcs` exits 0
- `vendor/bin/phpstan analyse --level=8` exits 0
- All CI checks pass on the PR

## Scope

**In scope**:
- Rebrand: `@author`, `@link`, `@plugin-uri` across 10 PHP files + `composer.json` + `README.txt`
- `uninstall.php`: move `delete_option` calls inside delete-data gate
- `includes/Modules/Logger/AcrossAI_Logger_Query.php`: spread operator refactor
- `.github/workflows/plugin-check.yml`: deleted
- `public/Main.php`: namespace rename (`\Public` → `\PublicFacing`)
- `includes/Main.php`: update instantiation + define() param names
- `composer.json`: autoload key rename + support URL
- `.github/workflows/phpcompat.yml`: remove `--ignore=public/Main.php`

**Out of scope**: REST endpoints, DB schema, new features.
