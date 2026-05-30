# Implementation Plan: CI Workflows Split and PHPCS Config Cleanup

**Branch**: `022-ci-workflows-phpcs-cleanup` | **Date**: 2026-05-31 | **Spec**: [spec.md](spec.md)

## Summary

Split the monolithic quality-gate approach into three dedicated GitHub Actions workflows (PHPCS, PHPStan, PHPCompatibility). Remove PHPCompatibility from `phpcs.xml.dist` so `composer run phpcs` scans only WordPress coding standards. Add exclude-patterns for all non-production paths. Remove dead exclusions whose rules the code already satisfies. Fix all pre-existing code-quality violations surfaced by the tighter PHPCS scan.

This feature touches **no REST endpoints, no DB schema, no admin menus, and no hooks**.

## Files Changed (exact, complete list)

```text
.github/workflows/
├── phpcs.yml            ← NEW — WordPress coding standards CI gate
├── phpstan.yml          ← NEW — PHPStan level 8 CI gate
└── phpcompat.yml        ← NEW — PHPCompatibility 7.4+ CI gate

phpcs.xml.dist           ← remove PHPCompatibility, add 8 exclude-patterns,
                            remove 4 dead exclusions, clean up ruleset

# Code quality fixes (pre-existing violations):
index.php
includes/index.php
admin/index.php
admin/Partials/index.php
languages/index.php
public/index.php
public/Partials/index.php
public/Partials/display.php
includes/AcrossAI_Deactivator.php
includes/AcrossAI_Loader.php
public/Main.php
  ↑ file docblocks and inline comment punctuation

# $_instance → $instance rename (21 production files):
includes/Main.php
includes/AcrossAI_Loader.php
includes/Utilities/AcrossAI_Logger_Source_Detector.php
includes/Modules/Logger/AcrossAI_Logger_Query.php
includes/Modules/Logger/AcrossAI_Ability_Logger.php
includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php
includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php
includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php
includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php
includes/Modules/Abilities/AcrossAI_Abilities_Processor.php
includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php
includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php
includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php
includes/Modules/Abilities/Rest/AcrossAI_Abilities_Category_Controller.php
includes/Modules/Abilities/Rest/AcrossAI_Abilities_Exposure_Controller.php
includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php
includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php
includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php
admin/Partials/SettingsMenu.php
admin/Partials/LogsMenu.php
# also in Override Processor: $_overrides_cache, $_checked, $_is_manager renamed
includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php

# $array → $items rename + test reflection strings:
includes/Utilities/AcrossAI_Abilities_Validator.php
tests/phpunit/sitewide/AbilityOverrideProcessorTest.php
```

## Phase 1 — CI Workflows

**Pattern**: SHA-pinned actions (reuse from `plugin-check.yml`), `timeout-minutes: 10`, `permissions: contents: read`, path filters so workflows only run when relevant files change.

### `phpcs.yml`
- Triggers on PHP/`phpcs.xml.dist`/`composer.*` changes
- Installs deps with `composer install --prefer-dist --no-progress`
- Runs `vendor/bin/phpcs --standard=phpcs.xml.dist --report=checkstyle | cs2pr`
- `cs2pr` annotates PR inline; `tools: cs2pr` installed via `setup-php`

### `phpstan.yml`
- Triggers on PHP/`phpstan.neon.dist`/`composer.*` changes
- Runs `vendor/bin/phpstan analyse --level=8 --error-format=github`

### `phpcompat.yml`
- Triggers on PHP/`composer.*` changes
- Runs PHPCompatibility against production directories only (not tests/specs)
- Uses `--runtime-set testVersion 7.4-` (not phpcs.xml.dist config)

## Phase 2 — `phpcs.xml.dist` Refactor

**Remove**:
- `<rule ref="PHPCompatibility"/>` and `<config name="testVersion" value="7.4-"/>` → moved to `phpcompat.yml`
- Dead exclusions: `MissingPackageTag`, `ClassComment.Missing`, `MissingParamComment`, `DocComment.MissingShort` (code already satisfies these)
- `Universal.NamingConventions.NoReservedKeywordParameterNames` severity-0 block (fix root cause instead)
- `WordPress.WP.GlobalVariablesOverride` admin/ exclusion (admin files don't override globals)

**Add**:
- 8 exclude-patterns: `tests/`, `.specify/`, `docs/`, `specs/`, `src/`, `.claude/`, `.agents/`, `.github/`
- `Squiz.Commenting.FileComment.WrongStyle` exclusion (security blank files use `// Silence is golden.`)

## Phase 3 — Code Quality Fixes

**File docblocks**: Move file-level docblock before `namespace` declaration in `AcrossAI_Loader.php`, `public/Main.php`, `AcrossAI_Deactivator.php`.

**Inline comment punctuation**: Add `.` to `// Silence is golden` in all 7 `index.php` boilerplate files; add `.` to `// Exit if accessed directly` in `display.php`.

**Property rename**: `$_instance` → `$instance` across all 21 classes using the singleton pattern. `$_overrides_cache` → `$overrides_cache`, `$_checked` → `$checked`, `$_is_manager` → `$is_manager` in `AcrossAI_Ability_Override_Processor`.

**Parameter rename**: `$array` → `$items` in `array_depth()` in `AcrossAI_Abilities_Validator`.

**Test update**: Update 6 reflection property-name strings in `AbilityOverrideProcessorTest.php`.

## Validation Gates

1. `composer run phpcs` → exit 0, 49 files, no violations
2. `vendor/bin/phpstan analyse --level=8` → 0 errors
3. `grep -rn "\$_instance\|\$_overrides_cache\|\$_checked\|\$_is_manager" includes/ admin/ public/` → 0 matches
4. All three workflow files present in `.github/workflows/`
