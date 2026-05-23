# Implementation Plan: Merge Abilities UI & Decommission Sitewide App

**Branch**: `011-merge-abilities-ui` | **Date**: 2026-05-24 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/011-merge-abilities-ui/spec.md`
**Memory Synthesis**: [memory-synthesis.md](./memory-synthesis.md)

---

## Summary

Collapse the Custom Abilities submenu page into the top-level Abilities Manager page. The abilities React app (`src/js/abilities/`, mount: `#acrossai-abilities-root`) moves from the now-deleted submenu to the existing main manager page. The obsolete sitewide React app (`src/js/sitewide/`) and its CSS companion are fully removed from source, build pipeline, PHP enqueue logic, and admin wiring. Six discrete surgical changes: source deletion, webpack config, enqueue refactor, HTML mount point, PHP class deletion, and hook wiring removal. No new classes, no new patterns, no REST changes.

---

## Technical Context

**Language/Version**: PHP 7.4+ / JavaScript ES2020+ (React 18 via `@wordpress/scripts`)
**Primary Dependencies**: `@wordpress/scripts` (webpack), WordPress 6.9+, `nvm` (Node 20 required — DEC-NODE-20-BUILD-REQUIRED)
**Storage**: N/A — no database or option changes
**Testing**: PHPCS strict, PHPStan level 8, ESLint, `npm run build` output inspection
**Target Platform**: WordPress single-site admin (multisite explicitly out of scope — Q1 clarification)
**Performance Goals**: No performance impact; fewer assets loaded = marginal improvement
**Constraints**: Must not touch REST controllers, DB classes, or business logic (FR-008). Logger assets and page must be unaffected (FR-007).
**Scale/Scope**: 6 files changed, 2 directories deleted, 1 file deleted, 1 webpack config entry block removed

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Rule | Compliance Status | Notes |
|---|---|---|
| §I Modular Architecture | ✓ PASS | No new modules; existing module boundaries unchanged |
| §II WordPress Standards (PHPCS/PHPStan/ESLint) | ✓ REQUIRED | Must verify after changes |
| §III DataViews/DataForm UI mandate | ✓ EXEMPT | DEC-DESIGN-OVERRIDES-DATAVIEWS accepted deviation from spec-010; abilities UI is custom React prototype |
| §IV Security First | ✓ PASS — with watchpoint | See Security Design §below: add defense-in-depth `current_user_can()` to `Menu.php::contents()` |
| §V Extensibility (no core modification) | ✓ PASS | Only removing dead code; no hook changes to other features |
| §VI DRY / No duplication | ✓ PASS | Removing duplication (two pages → one) |
| §VII DoD gates | ✓ REQUIRED | All gates must pass before Done |
| AC-HOOKS-MAIN Boot Flow Rule | ✓ PASS | Hook removal in `includes/Main.php` only |
| AC-ENQUEUE-ADMIN | ✓ PASS | All asset changes in `admin/Main.php` only |
| AC-MENU-IN-PLACE | ✓ PASS | `admin/Partials/Menu.php` updated in-place |
| DEC-NODE-20-BUILD-REQUIRED | ✓ REQUIRED | `nvm use 20 && npm run build` |

**No Constitution violations. No Complexity Tracking table required.**

---

## Project Structure

### Documentation (this feature)

```text
specs/011-merge-abilities-ui/
├── spec.md                  # Feature requirements
├── plan.md                  # This file
├── memory-synthesis.md      # Memory context (pre-plan)
├── checklists/
│   └── requirements.md
└── tasks.md                 # Phase 2 output (/speckit.tasks)
```

### Source Code Affected

```text
webpack.config.js                               # CHANGE: remove sitewide entries
admin/
├── Main.php                                    # CHANGE: enqueue refactor
└── Partials/
    ├── Menu.php                                # CHANGE: mount point ID
    └── AcrossAI_Abilities_Menu.php             # DELETE: entire file
includes/
└── Main.php                                    # CHANGE: remove hook wiring
src/
├── js/
│   └── sitewide/                               # DELETE: entire directory
└── scss/
    └── sitewide/                               # DELETE: entire directory
```

---

## Implementation Design

### Change 1 — Source Code Deletion

**Files**: `src/js/sitewide/` (directory), `src/scss/sitewide/` (directory)
**Action**: Delete both directories in full.
**Risk**: Low — no other source file imports from these directories.
**Verification**: `git status` shows deletions; directories absent from file system.

---

### Change 2 — Webpack Configuration

**File**: `webpack.config.js`

**Remove** these two entry point blocks from the `entry` object:
```js
// REMOVE:
'js/sitewide': path.resolve(process.cwd(), 'src/js/sitewide', 'index.js'),
'css/sitewide': path.resolve(process.cwd(), 'src/scss/sitewide', 'admin.scss'),
```

**Keep** all other entries unchanged: `js/frontend`, `js/backend`, `css/frontend`, `css/backend`, `js/logger`, `css/logger`, `js/abilities`, `css/abilities`, `...blockStylesheets()`, `...blockEntries`, `...getWebpackEntryPoints()`.

**Verification**: `nvm use 20 && npm run build` (clean output dir first). Output must contain `abilities.js`, `abilities.css`, `logger.js`, `logger.css`. Must NOT contain `sitewide.js`, `sitewide.css`.

---

### Change 3 — Admin Enqueue Logic (`admin/Main.php`)

**Sub-change 3a — Remove `use` statement (line 12)**:
```php
// REMOVE:
use AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu;
```

**Sub-change 3b — Remove `$sitewide_asset_file` property + PHPDoc (lines ~78–85)**:
```php
// REMOVE: entire property declaration block
/**
 * Asset manifest for the sitewide ability manager JS/CSS bundle.
 * @since 0.1.0
 * @var array
 */
private $sitewide_asset_file;
```

**Sub-change 3c — Remove sitewide asset load in constructor (line ~118)**:
```php
// REMOVE:
$this->sitewide_asset_file = include \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/sitewide.asset.php';
```

**Sub-change 3d — Refactor `enqueue_styles()`**:

Replace the current early-return guard and sitewide block:
```php
// BEFORE (remove):
$on_abilities = false !== strpos( $hook_suffix, 'acrossai-abilities-manager' );
$on_logs      = false !== strpos( $hook_suffix, 'acrossai-abilities-logs' );
$on_custom    = false !== strpos( $hook_suffix, 'acrossai-abilities-custom' );
if ( ! $on_abilities && ! $on_logs && ! $on_custom ) { return; }

wp_register_style( 'acrossai-abilities-sitewide', ... );
wp_enqueue_style( 'acrossai-abilities-sitewide' );

// ...logger block (KEEP)...

if ( $this->abilities_asset_file && $this->is_abilities_custom_page( $hook_suffix ) ) { ... }
```

```php
// AFTER (replacement):
if ( ! $this->is_manager_page( $hook_suffix ) && ! $this->is_logs_page( $hook_suffix ) ) {
    return;
}

// Enqueue logger styles only on Logs page (T015: feature 006).
if ( $this->logger_asset_file && $this->is_logs_page( $hook_suffix ) ) {
    // ...existing logger block unchanged...
}

// Enqueue Abilities Manager styles on main manager page (feature 011).
if ( $this->abilities_asset_file && $this->is_manager_page( $hook_suffix ) ) {
    wp_register_style(
        'acrossai-abilities-manager-abilities',
        \ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/css/abilities.css',
        array(),
        $this->abilities_asset_file['version']
    );
    wp_enqueue_style( 'acrossai-abilities-manager-abilities' );
}
```

**Sub-change 3e — Refactor `enqueue_scripts()`** (mirror of 3d):

```php
// BEFORE (remove):
$on_abilities = false !== strpos( ... );
$on_logs      = false !== strpos( ... );
$on_custom    = false !== strpos( ... );
if ( ! $on_abilities && ! $on_logs && ! $on_custom ) { return; }

wp_register_script( 'acrossai-abilities-sitewide', ... );
wp_enqueue_script( 'acrossai-abilities-sitewide' );
wp_add_inline_script( 'acrossai-abilities-sitewide', 'window.acrossaiAbilitiesSitewide = ...', 'before' );

// ...logger block (KEEP)...

if ( $this->abilities_asset_file && $this->is_abilities_custom_page( $hook_suffix ) ) { ... }
```

```php
// AFTER (replacement):
if ( ! $this->is_manager_page( $hook_suffix ) && ! $this->is_logs_page( $hook_suffix ) ) {
    return;
}

// Enqueue logger scripts only on Logs page (T015: feature 006).
if ( $this->logger_asset_file && $this->is_logs_page( $hook_suffix ) ) {
    // ...existing logger block unchanged...
}

// Enqueue Abilities Manager scripts on main manager page (feature 011).
if ( $this->abilities_asset_file && $this->is_manager_page( $hook_suffix ) ) {
    wp_register_script(
        'acrossai-abilities-manager-abilities',
        \ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/js/abilities.js',
        $this->abilities_asset_file['dependencies'],
        $this->abilities_asset_file['version'],
        true
    );
    wp_enqueue_script( 'acrossai-abilities-manager-abilities' );

    wp_add_inline_script(
        'acrossai-abilities-manager-abilities',
        'window.acrossaiAbilitiesManager = ' . wp_json_encode(
            array(
                'nonce'           => wp_create_nonce( 'wp_rest' ),
                'rest_url'        => untrailingslashit( rest_url() ),
                'rest_namespace'  => 'acrossai-abilities-manager/v1',
                'current_user_id' => get_current_user_id(),
            )
        ) . ';',
        'before'
    );
}
```

**Sub-change 3f — Rename and rewrite `is_abilities_custom_page()` → `is_manager_page()`**:

```php
// BEFORE (remove):
/**
 * Check if currently viewing the Custom Abilities submenu page.
 * SEC-04: Uses === strict comparison to prevent type-coercion bypass.
 * @since 0.2.0
 */
private function is_abilities_custom_page( string $hook_suffix ): bool {
    $abilities_menu = AcrossAI_Abilities_Menu::instance();
    return $hook_suffix === $abilities_menu->get_hook_suffix();
}
```

```php
// AFTER:
/**
 * Check if currently viewing the main Abilities Manager page.
 *
 * SEC-04: Uses === strict comparison to prevent type-coercion bypass.
 *
 * @since 0.3.0
 * @param string $hook_suffix Current admin page hook suffix.
 * @return bool True if on the main Abilities Manager page.
 */
private function is_manager_page( string $hook_suffix ): bool {
    return $hook_suffix === 'toplevel_page_acrossai-abilities-manager';
}
```

**Note**: The hook suffix `toplevel_page_acrossai-abilities-manager` is generated by WordPress from `add_menu_page()` with slug `acrossai-abilities-manager`. This is a stable, static value — no dependency on `AcrossAI_Abilities_Menu` singleton needed.

---

### Change 4 — Admin Menu Template (`admin/Partials/Menu.php`)

**File**: `admin/Partials/Menu.php`, `contents()` method

```php
// BEFORE:
<div id="acrossai-abilities-manager-root"></div>
```

```php
// AFTER:
<div id="acrossai-abilities-root"></div>
```

**Security addition** (defense-in-depth, matching SEC-010-01 pattern from deleted class):
```php
public function contents() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
    }
    ?>
    <div class="wrap acrossai-abilities-manager-wrap">
        <!-- Abilities Manager React app -->
        <div id="acrossai-abilities-root"></div>
    </div>
    <?php
}
```

Note: WordPress already gates `contents()` via `add_menu_page( ... 'manage_options' ... )`. The `current_user_can()` check is defense-in-depth only, consistent with the deleted `AcrossAI_Abilities_Menu::render()` pattern.

---

### Change 5 — PHP Class Deletion (`admin/Partials/AcrossAI_Abilities_Menu.php`)

**Action**: Delete file `admin/Partials/AcrossAI_Abilities_Menu.php` in full.

**All references that must also be removed (complete map from code analysis)**:

| File | Line(s) | Reference Type | Action |
|---|---|---|---|
| `admin/Main.php` | 12 | `use` statement | Remove line |
| `admin/Main.php` | ~288 | `AcrossAI_Abilities_Menu::instance()` call in `is_abilities_custom_page()` | Remove entire method (Change 3f replaces it) |
| `includes/Main.php` | 278–279 | FQN instantiation + `add_action` call | Remove both lines (Change 6) |

No other references found in the codebase.

---

### Change 6 — Hook Wiring (`includes/Main.php`)

**File**: `includes/Main.php`, `define_admin_hooks()` method

```php
// REMOVE these two lines (currently ~278–279):
$abilities_menu = \AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu::instance();
$this->loader->add_action( 'admin_menu', $abilities_menu, 'register_submenu' );
```

Note: No `use` statement for `AcrossAI_Abilities_Menu` exists in `includes/Main.php` (it uses the fully-qualified class name inline). Only these two lines require removal.

---

## Security Design

| Concern | Treatment |
|---|---|
| `window.acrossaiAbilitiesManager` inline data | `wp_json_encode()` with typed PHP array — safe, existing pattern |
| Manager page capability gate | `add_menu_page(..., 'manage_options', ...)` primary gate + `current_user_can('manage_options')` defense-in-depth in `contents()` |
| Sitewide inline script removal | `window.acrossaiAbilitiesSitewide` (nonce + user ID) will no longer be emitted on any page — reduces surface area |
| Strict type comparison | `is_manager_page()` uses `===` (SEC-04 pattern preserved) |
| No new AJAX/REST endpoints | No new attack surface introduced |

---

## Verification Plan

Matches spec Success Criteria SC-001 through SC-005 and FR requirements FR-001 through FR-010.

### Build Verification (SC-001)
```bash
# Precondition: clean output directory
rm -rf build/js/sitewide.js build/js/sitewide.asset.php build/css/sitewide.css

# Build (Node 20 required — DEC-NODE-20-BUILD-REQUIRED)
nvm use 20 && npm run build

# Assert: these files must NOT exist
ls build/js/sitewide.js 2>/dev/null && echo "FAIL: sitewide.js still built" || echo "PASS"
ls build/css/sitewide.css 2>/dev/null && echo "FAIL: sitewide.css still built" || echo "PASS"

# Assert: these files must exist
ls build/js/abilities.js && ls build/css/abilities.css && ls build/js/logger.js && ls build/css/logger.css && echo "PASS"
```

### Static Analysis
```bash
vendor/bin/phpcs --standard=phpcs.xml.dist admin/Main.php admin/Partials/Menu.php includes/Main.php
vendor/bin/phpstan analyse admin/Main.php admin/Partials/Menu.php includes/Main.php --level=8
npm run lint
npm run validate-packages
```

### Manual Browser Verification (matches spec verification checklist)
1. Navigate to `?page=acrossai-abilities-manager` → DevTools Network tab
2. Confirm `abilities.js` + `abilities.css` loaded ✓
3. Confirm `sitewide.js` not loaded on any page ✓
4. Confirm `#acrossai-abilities-root` div present in DOM ✓
5. Confirm React mounts and abilities interface renders ✓
6. Confirm `window.acrossaiAbilitiesManager` defined in console ✓
7. Confirm "Custom Abilities" menu item absent from sidebar ✓
8. Navigate to Logs page → verify logs table renders, `logger.js` loaded ✓

---

## Implementation Order

Tasks should be executed in this dependency order to prevent broken intermediate states:

1. Delete `src/js/sitewide/` and `src/scss/sitewide/` (no PHP dependencies)
2. Remove `webpack.config.js` sitewide entries
3. Delete `admin/Partials/AcrossAI_Abilities_Menu.php`
4. Remove references in `includes/Main.php` (hook wiring)
5. Refactor `admin/Main.php` (enqueue: removes `use` + `$sitewide_asset_file` + `$on_custom` + `is_abilities_custom_page` + adds `is_manager_page`)
6. Update `admin/Partials/Menu.php` (mount point + capability check)
7. Run build and static analysis

Steps 1–2 are independent of PHP changes; steps 3–6 are PHP-side and can be done together; step 7 is final gate.

---

## Definition of Done

- [ ] `src/js/sitewide/` deleted
- [ ] `src/scss/sitewide/` deleted
- [ ] `admin/Partials/AcrossAI_Abilities_Menu.php` deleted
- [ ] `webpack.config.js` sitewide entries removed
- [ ] `admin/Main.php` fully refactored: `use` removed, `$sitewide_asset_file` removed, `$on_custom` removed, sitewide register/enqueue/inline removed, `is_manager_page()` replacing `is_abilities_custom_page()`
- [ ] `admin/Partials/Menu.php` mount point updated to `#acrossai-abilities-root`, capability check added
- [ ] `includes/Main.php` abilities_menu wiring removed (2 lines)
- [ ] `npm run build` passes (Node 20, clean dir) — no sitewide output
- [ ] PHPCS zero errors/warnings
- [ ] PHPStan level 8 zero errors
- [ ] ESLint zero errors
- [ ] `npm run validate-packages` passes
- [ ] Manual browser verification complete (all 8 checklist items)
- [ ] Unit tests: `is_manager_page()` is a private method with zero branches and a hardcoded string constant (zero cyclomatic complexity); document exemption in tasks.md OR add single PHPUnit assertion for the hook suffix value
- [ ] DataViews gate: N/A per DEC-DESIGN-OVERRIDES-DATAVIEWS (abilities React UI, accepted deviation approved in spec-010)
- [ ] Security constraints SC-011-01 through SC-011-04 verified (see security-constraints.md)
