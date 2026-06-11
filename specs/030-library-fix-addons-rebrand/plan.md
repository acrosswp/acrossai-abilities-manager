# Implementation Plan: Library Page Fix + AddonsPage Package Rebrand (Feature 030)

**Branch**: `030-library-page-fix-and-addons-page-rebrand` | **Date**: 2026-06-11 | **Spec**: [spec.md](spec.md)

---

## Summary

Two independent fixes:

**Fix A** — The Ability Library admin page data is currently injected via `wp_localize_script()` from inside `LibraryMenu::render()`. This violates `AC-ENQUEUE-ADMIN` (all admin data injection must happen in `Admin\Main::enqueue_scripts()`) and is the root cause of the blank page (data may arrive after the browser runtime boots). The fix moves data injection into `enqueue_scripts()` via `wp_add_inline_script()` — matching the existing pattern used by the Abilities Manager page — and removes the now-redundant `localize_data()` method from `LibraryMenu`.

**Fix B** — The `wpboilerplate/addons-page` Composer package has been rebranded to `acrossai-co/addons-page`. Update `composer.json` (require + repositories), run `composer update`, confirm the new namespace from the installed package, then update the `class_exists()` guard and `new` instantiation in `includes/Main.php`. All guards, Freemius credentials, and the admin-notice catch block are preserved unchanged per `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` and `BUG-EXTERNAL-PACKAGE-CTOR-SILENT`.

---

## Technical Context

**Language/Version**: PHP 8.1+ / no JS changes
**Primary Dependencies**: Composer, WordPress admin hooks
**Storage**: No DB schema changes
**Testing**: Manual smoke tests (admin page navigation); PHPUnit not applicable (no new logic)
**Target Platform**: WordPress 6.9+ multisite-compatible
**Scale/Scope**: 3 files modified (admin/Main.php, admin/Partials/LibraryMenu.php, composer.json), 0 new files
**Performance Goals**: No runtime impact — admin-only enqueue changes

---

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| §I Modular Architecture | PASS | Data injection moved to the correct enqueue location; no new class |
| §II WordPress Standards | PASS | `wp_add_inline_script()` + `wp_json_encode()` are the canonical WP approach; PHPCS/PHPStan must remain clean |
| §III User-Centric Design | PASS | No UI change; fix restores expected admin UX |
| §IV Security First | PASS | Nonce injection path unchanged; `wp_create_nonce('wp_rest')` stays in place |
| §V Extensibility | PASS | `class_exists()` guard preserved for AddonsPage; fix degrades gracefully when package absent |
| §VI DRY | PASS | `localize_data()` removed to eliminate the now-duplicate injection point |
| §VII Definition of Done | TRACKED | Quality gates listed in validation section |

---

## Project Structure

### Changed files

```text
admin/
├── Main.php                            [MOD — Fix A: add use statements + wp_add_inline_script block]
└── Partials/
    └── LibraryMenu.php                 [MOD — Fix A: remove localize_data() call + delete method]
includes/
└── Main.php                            [MOD — Fix B: update FQCN in class_exists guard + new call]
composer.json                           [MOD — Fix B: update require key + repositories entry]
```

---

## Implementation Changes

### FIX-A-1 — Add use statements to `admin/Main.php`

**File**: `admin/Main.php`

After the existing `use AcrossAI_Abilities_Manager\Admin\Partials\LogsMenu;` line, add:

```php
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Rest\AcrossAI_Ability_Library_Rest_Controller;
```

---

### FIX-A-2 — Replace `wp_enqueue_script` with `wp_add_inline_script` in `admin/Main.php`

**File**: `admin/Main.php` — `enqueue_scripts()` method, library block (~L273–282)

After the existing `wp_enqueue_script( 'acrossai-ability-library-js' );` call, add:

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

**Why `'before'`**: The `before` position injects the global before the script itself executes, ensuring `window.acrossaiAbilityLibraryData` is defined at the time the React bootstrap runs.

**Why `admin_enqueue_scripts` is the right timing**: `AcrossAI_Ability_Library_Registry::collect()` is hooked at `init P99`. `admin_enqueue_scripts` fires after `init`, so `get_definitions()` will have already collected data when called here.

---

### FIX-A-3 — Remove `localize_data()` from `LibraryMenu::render()` and delete the method

**File**: `admin/Partials/LibraryMenu.php`

**Edit 1** — Remove `$this->localize_data();` call from `render()`.

**Edit 2** — Delete the entire `private function localize_data(): void { ... }` method.

**Edit 3** — Remove the two `use` statements that were only needed by `localize_data()`:
```php
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Rest\AcrossAI_Ability_Library_Rest_Controller;
```

After these edits, `render()` contains only the HTML wrapper:
```php
public function render(): void {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Ability Library', 'acrossai-abilities-manager' ); ?></h1>
        <div id="acrossai-library-root"></div>
    </div>
    <?php
}
```

---

### FIX-B-1 — Pre-update audit

Before touching any file:

1. Visit `https://github.com/acrossai-co/addons-page` (or search Packagist for `acrossai-co/addons-page`) to confirm:
   - GitHub URL or Packagist availability
   - Latest stable version tag
   - Constructor signature matches `__construct( string $menu_slug, string $plugin_file, array $args = [] )`
   - Namespace declared in the new package's `composer.json` (PSR-4 key)
2. Skim the CHANGELOG for breaking changes vs `wpboilerplate/addons-page` v0.0.17.

---

### FIX-B-2 — Update `composer.json`

**File**: `composer.json`

**Edit 1** — In the `repositories` array, replace or update the VCS entry:

```json
{
    "type": "vcs",
    "url": "<new GitHub URL for acrossai-co/addons-page>"
}
```

Remove the old entry `https://github.com/WPBoilerplate/wpb-addons-page`. If the package is on Packagist, remove the repositories entry entirely for this package.

**Edit 2** — In the `require` block, replace:
```json
"wpboilerplate/addons-page": "^0.0.17"
```
with:
```json
"acrossai-co/addons-page": "^<confirmed-stable-version>"
```

Then run:
```bash
composer update acrossai-co/addons-page --with-dependencies
```

---

### FIX-B-3 — Confirm namespace from installed package

After `composer update`, read:
```
vendor/acrossai-co/addons-page/composer.json
```

Locate the PSR-4 `autoload` key. Example expected output:
```json
"autoload": {
    "psr-4": {
        "AcrossAI\\AddonsPage\\": "src/"
    }
}
```

**Do not proceed with FIX-B-4 until the namespace is confirmed from this file.**

---

### FIX-B-4 — Update `includes/Main.php` namespace reference

**File**: `includes/Main.php` (~L266)

Replace the old FQCN with the new one confirmed in FIX-B-3:

```php
// Before (Feature 026 original):
if ( class_exists( \WPBoilerplate\AddonsPage\AddonsPage::class ) ) {
    try {
        new \WPBoilerplate\AddonsPage\AddonsPage(
            'acrossai-abilities-manager',
            ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE,
            array(
                'fs_product_id' => '31230',
                'fs_public_key' => 'pk_0f116582ac1b8e608827094024b1f',
                'fs_slug'       => 'acrossai-abilities-manager',
            )
        );

// After (new namespace — substitute actual FQCN from FIX-B-3):
if ( class_exists( \AcrossAI\AddonsPage\AddonsPage::class ) ) {
    try {
        new \AcrossAI\AddonsPage\AddonsPage(
            'acrossai-abilities-manager',
            ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE,
            array(
                'fs_product_id' => '31230',
                'fs_public_key' => 'pk_0f116582ac1b8e608827094024b1f',
                'fs_slug'       => 'acrossai-abilities-manager',
            )
        );
```

**Guards to preserve unchanged** (per `BUG-EXTERNAL-PACKAGE-CTOR-SILENT`):
- The `catch ( \Throwable $e )` block with `add_action('admin_notices', ...)` MUST remain intact.
- Freemius credentials (`fs_product_id`, `fs_public_key`, `fs_slug`) are unchanged.
- The comment citing `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` MUST remain.
- Update the comment to reference the new package name (`acrossai-co/addons-page`).

---

### FIX-B-5 — Update durable memory

**File**: `docs/memory/DECISIONS.md` — `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` entry

Update the **Evidence** line to reference `acrossai-co/addons-page` (Feature 030) alongside the original Feature 026 reference.

---

## What Must NOT Change

- `admin/Partials/LibraryMenu.php` `register_submenu()` — no change to menu registration
- `is_library_page()` hook suffix — no change (DEC-MENU-HOOK-SUFFIX)
- Freemius credentials in `includes/Main.php` — unchanged
- The `catch ( \Throwable $e )` admin-notice block — unchanged
- No JS source changes, no webpack build required
- No DB schema, no REST routes, no new admin pages

---

## Validation Checklist

### Fix A — Library page

- [ ] Navigate to `/wp-admin/admin.php?page=acrossai-abilities-library` — page shows "No abilities registered yet" message or ability cards (never blank).
- [ ] `window.acrossaiAbilityLibraryData` is present in the page source (check via browser DevTools before the `ability-library.js` script tag).
- [ ] No browser console errors produced by the plugin's own scripts.
- [ ] `admin/Partials/LibraryMenu.php` has no `localize_data()` method and no `use` statements for Library classes.

### Fix B — AddonsPage rename

- [ ] `composer update` completes without errors.
- [ ] `vendor/acrossai-co/addons-page/` directory exists; old `vendor/wpboilerplate/addons-page/` is absent.
- [ ] The Add-ons submenu page renders correctly with no PHP fatal errors or warnings.
- [ ] Freemius opt-in state is preserved.
- [ ] `grep -r "WPBoilerplate\\\\AddonsPage" includes/` returns no results.

### Quality gates

- [ ] `composer phpcs` — zero errors for all modified PHP files.
- [ ] `composer phpstan` level 8 — zero errors for all modified PHP files.
- [ ] No `npm run build` needed (no JS changes).

---

## Post-implementation steps

1. Run `composer dump-autoload` after composer update to refresh the autoload map.
2. Deactivate and reactivate the plugin if the Add-ons submenu does not appear immediately.
3. Run quality gates: `composer phpcs && composer phpstan`.
