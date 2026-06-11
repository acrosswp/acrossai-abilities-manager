---
description: "Task list for Feature 030 — Library Page Fix + AddonsPage Package Rebrand"
---

# Tasks: Library Page Fix + AddonsPage Package Rebrand (Feature 030)

**Input**: Design documents from `specs/030-library-fix-addons-rebrand/`
**Prerequisites**: plan.md ✅, spec.md ✅, memory-synthesis.md ✅

**Tests**: No PHPUnit tests — both fixes are structural/configuration changes with no new business logic. Manual smoke tests per validation checklist in plan.md.

**Organization**: Fix A (Library page) and Fix B (AddonsPage rename) are independent. Fix B has an internal dependency chain (audit → composer update → confirm namespace → update Main.php). Fix A tasks can run in parallel with Fix B-1 (audit).

## Format: `[ID] [P?] Description`

- **[P]**: Can run in parallel with other tasks in the same phase

---

## Phase 1 — Fix A: Library Page Data Injection

**Goal**: Move `wp_localize_script()` out of `LibraryMenu::render()` and replace with `wp_add_inline_script()` in `Admin\Main::enqueue_scripts()`, correcting the `AC-ENQUEUE-ADMIN` violation that causes the blank page.

- [ ] T001 [P] Add two `use` statements to `admin/Main.php` immediately after the existing `use AcrossAI_Abilities_Manager\Admin\Partials\LogsMenu;` line:
  ```php
  use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
  use AcrossAI_Abilities_Manager\Includes\Modules\Library\Rest\AcrossAI_Ability_Library_Rest_Controller;
  ```

- [ ] T002 [P] In `admin/Main.php::enqueue_scripts()`, inside the `if ( $this->library_asset_file && $this->is_library_page( $hook_suffix ) )` block, add `wp_add_inline_script()` immediately after the existing `wp_enqueue_script( 'acrossai-ability-library-js' )` call:
  ```php
  wp_add_inline_script(
      'acrossai-ability-library-js',
      'window.acrossaiAbilityLibraryData = ' . wp_json_encode(
          array(
              'definitions' => AcrossAI_Ability_Library_Registry::instance()->get_definitions(),
              'restBase'    => rest_url( AcrossAI_Ability_Library_Rest_Controller::REST_NAMESPACE ),
              'nonce'       => wp_create_nonce( 'wp_rest' ),
          )
      ) . ';',
      'before'
  );
  ```
  `'before'` position ensures `window.acrossaiAbilityLibraryData` exists before `ability-library.js` runs. `admin_enqueue_scripts` fires after `init P99`, so `get_definitions()` has already collected data (AC-ENQUEUE-ADMIN, DEC-ABILITIES-LIST-UX-025).

- [ ] T003 Remove `$this->localize_data();` from `admin/Partials/LibraryMenu.php::render()`. Delete the entire `private function localize_data(): void { ... }` method body. Remove the two `use` statements that were only needed for `localize_data()`:
  ```
  use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
  use AcrossAI_Abilities_Manager\Includes\Modules\Library\Rest\AcrossAI_Ability_Library_Rest_Controller;
  ```
  After this edit `render()` contains only the HTML wrapper (`<div class="wrap">...</div>`).

**Checkpoint**: Navigating to `/wp-admin/admin.php?page=acrossai-abilities-library` shows either ability cards or the "No abilities registered yet" empty-state message — never a blank page. `window.acrossaiAbilityLibraryData` is present in the page source before the `ability-library.js` script tag.

---

## Phase 2 — Fix B: AddonsPage Package Rename

### Phase 2a — Pre-update Audit (MUST complete before any file changes)

**Purpose**: Confirm new package availability, stable version, constructor compatibility, and namespace — per DEC-STABLE-UPGRADE-WINDOW.

- [ ] T004 Locate the `acrossai-co/addons-page` package: check Packagist (`https://packagist.org/packages/acrossai-co/addons-page`) and GitHub (`https://github.com/acrossai-co/addons-page`). Record: (a) whether it is on Packagist or VCS-only; (b) the latest stable version tag; (c) the GitHub URL if VCS-only.

- [ ] T005 Read the new package's `composer.json` (from GitHub or after `composer require --dry-run`). Confirm: (a) PSR-4 namespace key (the FQCN used in Main.php depends on this); (b) constructor signature still matches `__construct( string $menu_slug, string $plugin_file, array $args = [] )`; (c) no new required parameters or breaking changes. Record the confirmed namespace.

- [ ] T006 Skim the new package CHANGELOG (or git log) for any breaking changes vs `wpboilerplate/addons-page` v0.0.17. If breaking changes are found, stop and raise with the user before proceeding.

### Phase 2b — Composer Update

- [ ] T007 Update `composer.json`:
  - In `repositories` array: replace the old VCS entry (`https://github.com/WPBoilerplate/wpb-addons-page`) with the new URL confirmed in T004. If the package is on Packagist, remove the repositories entry for this package entirely.
  - In `require` block: replace `"wpboilerplate/addons-page": "^0.0.17"` with `"acrossai-co/addons-page": "^<version-from-T004>"`.

- [ ] T008 Run `composer update acrossai-co/addons-page --with-dependencies` from the plugin root. Verify: (a) exit code 0; (b) `vendor/acrossai-co/addons-page/` directory exists; (c) `vendor/wpboilerplate/addons-page/` directory is absent.

- [ ] T009 Confirm namespace from the installed package: read `vendor/acrossai-co/addons-page/composer.json` and locate the PSR-4 autoload key. This value MUST match what was found in T005. If it differs, stop and investigate before proceeding.

### Phase 2c — Main.php Namespace Update

- [ ] T010 In `includes/Main.php`, update the AddonsPage block (around L266):
  - Replace `class_exists( \WPBoilerplate\AddonsPage\AddonsPage::class )` with the new FQCN confirmed in T009.
  - Replace `new \WPBoilerplate\AddonsPage\AddonsPage(` with the new FQCN.
  - Preserve unchanged: `class_exists()` guard structure, Freemius credentials array (`fs_product_id`, `fs_public_key`, `fs_slug`), the `catch ( \Throwable $e )` block with `add_action('admin_notices', ...)` (BUG-EXTERNAL-PACKAGE-CTOR-SILENT), and the comment citing `DEC-EXTERNAL-PACKAGE-HOOK-CTOR`.
  - Update the comment to reference `acrossai-co/addons-page` (Feature 030) alongside the original `wpboilerplate/addons-page` (Feature 026) reference.

- [ ] T011 Run `composer dump-autoload` to refresh the classmap after the package swap. Verify no autoload warnings.

- [ ] T012 Verify no stale references remain: `grep -r "WPBoilerplate\\\\AddonsPage" includes/ admin/` must return zero results.

### Phase 2d — Memory Update

- [ ] T013 In `docs/memory/DECISIONS.md`, update the `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` **Evidence** line to add `acrossai-co/addons-page` (Feature 030) as a second evidence point.

---

## Phase 3 — Quality Gate

- [ ] T014 Run `composer phpcs` — zero errors for `admin/Main.php`, `admin/Partials/LibraryMenu.php`, `includes/Main.php`.

- [ ] T015 Run `composer phpstan` (level 8) — zero errors for all modified files. Pay special attention to the new FQCN in T010; PHPStan must resolve it from vendor.

- [ ] T016 Manual smoke tests (run in browser):
  - **Fix A**: Navigate to Library page — confirm non-blank content on first load.
  - **Fix B**: Navigate to Add-ons submenu — confirm page renders with no PHP errors or white screen. Check PHP error log for any `AddonsPage` class-not-found warnings.

---

## Dependencies & Execution Order

```
Phase 1 (T001–T003): Fix A — parallel-safe; no dependencies on Phase 2.

Phase 2:
  T004 → T005 → T006   (sequential audit chain)
                ↓
            T007 → T008 → T009   (composer update chain)
                              ↓
                     T010 → T011 → T012   (Main.php update chain)
                                       ↓
                                      T013   (memory update)

Phase 3 (T014–T016): quality gate — depends on all prior tasks complete.
```

Fix A (Phase 1) and Fix B audit (T004–T006) can run simultaneously.

---

## Notes

- No new PHP classes, no new REST routes, no new JS bundles — no Jest or PHPUnit tests required.
- T005/T009 are the critical namespace confirmation gates. Do NOT write the new FQCN in T010 before T009 confirms it from the installed vendor.
- `'before'` in `wp_add_inline_script()` (T002) is mandatory — the React bootstrap reads `window.acrossaiAbilityLibraryData` synchronously at module load time.
- `BUG-EXTERNAL-PACKAGE-CTOR-SILENT` guard in T010: the admin-notice catch block is load-bearing — removing it would make all future AddonsPage constructor failures invisible.
