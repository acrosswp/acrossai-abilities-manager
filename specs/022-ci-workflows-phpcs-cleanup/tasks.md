---
description: "Task list for Feature 022 — CI Workflows Split and PHPCS Config Cleanup"
---

# Tasks: CI Workflows Split and PHPCS Config Cleanup (Feature 022)

**Input**: `specs/022-ci-workflows-phpcs-cleanup/plan.md`, `spec.md`
**Branch**: `022-ci-workflows-phpcs-cleanup`
**Memory**: `specs/022-ci-workflows-phpcs-cleanup/memory-synthesis.md`
**Security**: `specs/022-ci-workflows-phpcs-cleanup/security-constraints.md`

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel with other [P] tasks in the same phase
- **[Story]**: US1 = CI workflows, US2 = PHPCS config, US3 = Code quality fixes

---

## Phase 1: CI Workflows (US1)

- [x] T001 [US1] Create `.github/workflows/phpcs.yml` — PHPCS CI gate with `cs2pr` inline annotation, path filters on PHP/phpcs.xml.dist/composer.*, SHA-pinned actions
- [x] T002 [US1] Create `.github/workflows/phpstan.yml` — PHPStan level 8 CI gate with `--error-format=github`, path filters on PHP/phpstan.neon.dist/composer.*
- [x] T003 [US1] Create `.github/workflows/phpcompat.yml` — PHPCompatibility 7.4+ CI gate scoped to production directories only (`includes/`, `admin/`, `public/`, root PHP files)
- [x] T004 [US1] Verify: `ls .github/workflows/` → `phpcs.yml`, `phpstan.yml`, `phpcompat.yml`, `plugin-check.yml` all present

---

## Phase 2: `phpcs.xml.dist` Refactor (US2)

- [x] T005 [US2] Remove `<rule ref="PHPCompatibility"/>` and `<config name="testVersion" value="7.4-"/>` — moved to `phpcompat.yml`
- [x] T006 [US2] Add 8 exclude-patterns: `tests/`, `.specify/`, `docs/`, `specs/`, `src/`, `.claude/`, `.agents/`, `.github/`
- [x] T007 [US2] Remove dead exclusions: `MissingPackageTag`, `ClassComment.Missing`, `MissingParamComment`, `DocComment.MissingShort` (code already satisfies these rules)
- [x] T008 [US2] Remove `Universal.NamingConventions.NoReservedKeywordParameterNames` severity-0 block (root cause fixed in T013)
- [x] T009 [US2] Remove `WordPress.WP.GlobalVariablesOverride` admin/ exclusion (admin files confirmed to not override any globals)
- [x] T010 [US2] Add `Squiz.Commenting.FileComment.WrongStyle` exclusion (security blank `index.php` files use standard `// Silence is golden.` pattern)

---

## Phase 3: Code Quality Fixes (US3)

- [x] T011 [US3] [P] Fix inline comment punctuation in all 7 `index.php` boilerplate files: `// Silence is golden` → `// Silence is golden.`
- [x] T012 [US3] [P] Fix inline comment and add file docblock in `AcrossAI_Loader.php`, `public/Main.php`, `AcrossAI_Deactivator.php`, `public/Partials/display.php`
- [x] T013 [US3] [P] Rename `$array` → `$items` in `array_depth()` method in `AcrossAI_Abilities_Validator.php`
- [x] T014 [US3] Rename `$_instance` → `$instance` in all 21 production classes using the singleton pattern
- [x] T015 [US3] Rename `$_overrides_cache` → `$overrides_cache`, `$_checked` → `$checked`, `$_is_manager` → `$is_manager` in `AcrossAI_Ability_Override_Processor.php`
- [x] T016 [US3] Update 6 reflection property-name strings in `AbilityOverrideProcessorTest.php` to match renamed properties
- [x] T017 [US3] Run `vendor/bin/phpcbf` to auto-fix alignment violations introduced by property renames

---

## Phase 4: Validation

- [x] T018 [US1] [US2] [US3] Run `composer run phpcs` → exit 0, 49 files, no errors or warnings
- [x] T019 [US2] [US3] Verify: `grep -rn "\$_instance\|\$_overrides_cache\|\$_checked\|\$_is_manager" includes/ admin/ public/` → 0 matches
- [x] T020 [US2] Verify: `grep "PHPCompatibility\|testVersion" phpcs.xml.dist` → 0 matches
