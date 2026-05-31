---
description: "Task list for Feature 023 — Rebrand, Cleanup, and Namespace Fix"
---

# Tasks: Rebrand, Cleanup, and Namespace Fix (Feature 023)

**Input**: `specs/023-fix-public-namespace-reserved-keyword/plan.md`, `spec.md`
**Branch**: `023-fix-public-namespace-reserved-keyword`
**Issue**: #28
**Memory**: `specs/023-fix-public-namespace-reserved-keyword/memory-synthesis.md`
**Security**: `specs/023-fix-public-namespace-reserved-keyword/security-constraints.md`

---

## Phase 1: Pre-flight Checks (Blocking)

- [x] T001 Confirm rebrand: `grep -rn "WPBoilerplate\|wpboilerplate\|contact@wpboilerplate.com" . --include="*.php" --include="*.json" --include="*.txt" --exclude-dir=vendor` — verify 0 matches (all already replaced in working tree)
- [x] T002 Confirm namespace still pending: `grep -rn "AcrossAI_Abilities_Manager\\\\Public" . --include="*.php" --include="*.json" --exclude-dir=vendor` — verify exactly 2 matches: `public/Main.php:12`, `includes/Main.php:297`

---

## Phase 2: Commit Pre-existing Manual Changes

- [x] T003 Stage all pre-existing working-tree changes (rebrand, uninstall gate, logger cleanup, plugin-check.yml deletion, all files EXCEPT the namespace-fix files)
- [x] T004 Commit staged changes with message covering: rebrand WPBoilerplate→AcrossWP across 10 PHP files + composer.json + README.txt, uninstall gate fix, logger spread operator refactor, plugin-check.yml deletion

---

## Phase 3: Namespace Rename

- [x] T005 Edit `public/Main.php` line 12: `namespace AcrossAI_Abilities_Manager\Public;` → `namespace AcrossAI_Abilities_Manager\Front;`
- [x] T006 Edit `includes/Main.php` line 297: `new \AcrossAI_Abilities_Manager\Public\Main(` → `new \AcrossAI_Abilities_Manager\Front\Main(`
- [x] T007 Edit `composer.json` autoload PSR-4: `"AcrossAI_Abilities_Manager\\Public\\"` → `"AcrossAI_Abilities_Manager\\Front\\"`

---

## Phase 4: Autoload + Workaround Removal

- [x] T008 Run `composer dump-autoload` to regenerate autoload files with the new namespace mapping
- [x] T009 Edit `.github/workflows/phpcompat.yml`: remove `--ignore=public/Main.php` line

---

## Phase 5: Validation

- [x] T010 Verify rebrand complete: `grep -rn "WPBoilerplate\|wpboilerplate\|contact@wpboilerplate.com" . --include="*.php" --include="*.json" --include="*.txt" --exclude-dir=vendor` → 0 matches
- [x] T011 Verify namespace gone: `grep -rn "AcrossAI_Abilities_Manager\\\\Public" . --include="*.php" --include="*.json" --exclude-dir=vendor` → 0 matches
- [x] T012 Run `composer run phpcs` → exit 0
- [x] T013 Run `vendor/bin/phpstan analyse --level=8` → 0 errors
- [x] T014 Verify workaround removed: `grep "ignore=public/Main.php" .github/workflows/phpcompat.yml` → 0 matches
- [x] T015 Open PR — confirm PHP Compatibility CI passes with `public/Main.php` included in scan
