# Implementation Plan: Rebrand, Cleanup, and Namespace Fix

**Branch**: `023-fix-public-namespace-reserved-keyword` | **Date**: 2026-05-31
**Spec**: [spec.md](spec.md) | **Issue**: #28

## Summary

This plan has two parts:

1. **Commit the pre-existing manual changes** already on the working tree (rebrand, uninstall gate, logger cleanup, plugin-check.yml deletion). These are done but uncommitted.
2. **Apply the namespace fix** (`\Public` → `\Front`) — the only change not yet made — then regenerate autoload and remove the CI workaround.

## Files Changed (complete list)

### Already changed — uncommitted
```text
.github/workflows/plugin-check.yml   — deleted
README.txt                            — Donate link: wpboilerplate → acrosswp
acrossai-abilities-manager.php        — Plugin URI, Description, Author, Author URI, @link
admin/Main.php                        — @link, @author
includes/AcrossAI_Activator.php       — @author
includes/AcrossAI_Deactivator.php     — @link, @author
includes/AcrossAI_Loader.php          — @link, @author
includes/Main.php                     — @link, @author, define() param names
includes/Modules/Logger/AcrossAI_Logger_Query.php — spread operator + var renames
public/Main.php                       — @link, @author (namespace still \Public — pending)
public/Partials/display.php           — @link
composer.json                         — support.issues URL
uninstall.php                         — delete_option calls moved inside gate
```

### Still to change — namespace fix
```text
public/Main.php
  Line 12: namespace AcrossAI_Abilities_Manager\Public;
         → namespace AcrossAI_Abilities_Manager\Front;

includes/Main.php
  Line 297: new \AcrossAI_Abilities_Manager\Public\Main(...)
           → new \AcrossAI_Abilities_Manager\Front\Main(...)

composer.json
  "AcrossAI_Abilities_Manager\\Public\\": "public/"
→ "AcrossAI_Abilities_Manager\\Front\\": "public/"

.github/workflows/phpcompat.yml
  Remove: --ignore=public/Main.php
```

## Phase 1 — Commit pre-existing manual changes

Stage and commit all working-tree changes except the namespace files (which are addressed in Phase 2).

## Phase 2 — Namespace Rename

1. Edit `public/Main.php` line 12: `\Public` → `\Front`
2. Edit `includes/Main.php` line 297: `\Public\Main(` → `\Front\Main(`
3. Edit `composer.json` autoload: rename PSR-4 key

## Phase 3 — Autoload Regeneration

Run `composer dump-autoload` to regenerate `vendor/composer/autoload_psr4.php` with the new namespace mapping.

## Phase 4 — Remove CI Workaround

Edit `.github/workflows/phpcompat.yml`: remove the `--ignore=public/Main.php` line.

## Validation Gates

1. `grep -rn "WPBoilerplate\|wpboilerplate\|contact@wpboilerplate.com" . --include="*.php" --include="*.json" --include="*.txt" --exclude-dir=vendor` → 0 matches
2. `grep -rn "AcrossAI_Abilities_Manager\\\\Public" . --include="*.php" --include="*.json" --exclude-dir=vendor` → 0 matches
3. `composer run phpcs` → exit 0
4. `vendor/bin/phpstan analyse --level=8` → 0 errors
5. `grep "ignore=public/Main.php" .github/workflows/phpcompat.yml` → 0 matches
6. CI PHP Compatibility workflow passes with no `--ignore` flag
