# Implementation Plan: Ability Access Control Tab

**Branch**: `003-ability-access-control-tab` | **Date**: 2026-05-16 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/003-ability-access-control-tab/spec.md`

## Summary

Add an "Access Control" third tab to the `AbilityEditPanel` slide-in drawer by:
1. Wiring the existing `AcrossAI_Sitewide_Access_Control` PHP singleton to `rest_api_init` in `Main.php` so the vendor's `wpb-ac/v1` REST routes are registered.
2. Adding a `@wpb/access-control` webpack resolve alias pointing to the vendor copy of the library.
3. Importing the `AccessControl` component and rendering it inside a new `"access-control"` tab in `AbilityEditPanel.jsx`.

Exactly **3 files** change. No new files are created.

## Technical Context

**Language/Version**: PHP 7.4+ / JavaScript ESNext (JSX via `@wordpress/scripts` webpack)
**Primary Dependencies**:
  - `wpboilerplate/wpb-access-control` (Composer vendor) — provides `AccessControlManager` (PHP) and `AccessControl` React component (JS)
  - `@wpb/access-control` — webpack alias mapping to `vendor/wpboilerplate/wpb-access-control/js/index.js`
  - `@wordpress/components` (`TabPanel`) — already imported in `AbilityEditPanel.jsx`
  - `@wordpress/api-fetch` — already registered globally in `src/js/sitewide/index.js`
**Storage**: No new tables. Vendor-owned `{prefix}wpb_access_control` table; accessed exclusively via `wpb-ac/v1` REST routes.
**Testing**: PHPCS (strict WPCS), PHPStan level 8, ESLint, `npm run validate-packages`, manual browser
**Target Platform**: WordPress 6.9+ admin (single-site and multisite)
**Project Type**: WordPress plugin — targeted tab integration into existing slide-in panel
**Performance Goals**: No new PHP overhead; vendor component makes its own REST calls on mount
**Constraints**: Exactly 3 file modifications; no new PHP files; no new JS entry points; no new CSS files; no new REST routes owned by the plugin
**Scale/Scope**: Single tab addition; no data migration; no schema changes

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| I Modular Architecture | ✅ Pass | Change confined to Sitewide module; vendor integration is self-contained |
| II WordPress Standards | ✅ Pass | WPCS strict, PHPStan 8, ESLint; following existing singleton + Loader patterns |
| III User-Centric Design | ⚠️ Justified | Vendor `AccessControl` component manages its own form UI — plugin delegates rather than building a custom form. DataForms/DataViews constraint applies to plugin-authored UI, not vendor-owned components. |
| IV Security First | ✅ Pass | Nonce middleware already global; `AccessControlManager` enforces `manage_options`; no new input/output surfaces in plugin code |
| V Extensibility | ✅ Pass | Feature wired via `rest_api_init` action hook through Loader; namespace filter `acrossai_abilities_access_control_providers` allows extension |
| VI DRY | ✅ Pass | Reuses existing nonce middleware, existing `TabPanel`, existing `window.acrossaiAbilitiesSitewide` |
| VII DoD | ✅ Tracked | PHPCS, PHPStan, ESLint, package validation, manual test — all required before merge |

## Project Structure

### Documentation (this feature)

```text
specs/003-ability-access-control-tab/
├── plan.md              # This file
├── spec.md              # Feature specification
├── research.md          # Phase 0 decisions
├── data-model.md        # Entity mapping
├── quickstart.md        # Build + test guide
├── contracts/
│   └── wpb-ac-v1-rest-api.md   # REST interface contract
└── tasks.md             # Task list (output of /speckit.tasks)
```

### Source Code (files modified — no new files)

```text
includes/
└── Main.php                                          ← ADD: 2 lines in define_admin_hooks()

src/js/sitewide/components/
└── AbilityEditPanel.jsx                              ← ADD: import + third tab + update render

webpack.config.js                                     ← ADD: resolve.alias block
```

## Change Spec

### 1. `includes/Main.php` — Hook Registration

Inside `define_admin_hooks()`, after the `$mcp_servers_list` block, add:

```php
$sitewide_ac = \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Access_Control::instance();
$this->loader->add_action( 'rest_api_init', $sitewide_ac, 'register_rest_api' );
```

**Boot Flow Rule compliance**: `$sitewide_ac` is a named variable; `::instance()` is not inlined.
**Method**: `register_rest_api()` — confirmed in `AcrossAI_Sitewide_Access_Control.php`.

---

### 2. `webpack.config.js` — Resolve Alias

Add a `resolve` key to the exported config object, spreading `defaultConfig.resolve` to preserve existing aliases:

```js
resolve: {
    ...defaultConfig.resolve,
    alias: {
        ...( defaultConfig.resolve?.alias ?? {} ),
        '@wpb/access-control': path.resolve(
            process.cwd(),
            'vendor/wpboilerplate/wpb-access-control/js/index.js'
        ),
    },
},
```

`AccessControl.scss` is imported inside `AccessControl.js` and is automatically bundled by webpack — no separate CSS enqueue is needed.

---

### 3. `src/js/sitewide/components/AbilityEditPanel.jsx` — Third Tab

**a) Add import** (after existing imports):

```js
import { AccessControl } from '@wpb/access-control';
```

**b) Add access-control tab content** (after `mcpTab` const):

```js
const sitewideConfig = window.acrossaiAbilitiesSitewide || {};

const accessControlTab = (
    <div className="acrossai-ability-edit-panel__tab-content">
        <AccessControl
            namespace="acrossai-abilities"
            resourceKey={ slug }
            restApiRoot={ sitewideConfig.rest_url || '/wp-json' }
            nonce={ sitewideConfig.nonce || '' }
        />
    </div>
);
```

**c) Update `TabPanel` tabs array** — add third entry:

```js
{ name: 'access-control', title: __( 'Access Control', 'acrossai-abilities-manager' ) },
```

**d) Update tab render callback** — extend the ternary to a three-way switch:

```js
{ ( tab ) => {
    if ( 'general' === tab.name ) return generalTab;
    if ( 'mcp'     === tab.name ) return mcpTab;
    return accessControlTab;
} }
```

**e) No `hasUnsaved` change** — the `AccessControl` component auto-saves on interaction; there is no plugin-owned draft state for this tab.

---

## Key Constraints & Risks

| Constraint | Detail |
|---|---|
| `rest_url` ≠ `restApiRoot` | `window.acrossaiAbilitiesSitewide` exposes `rest_url`; map it to the `restApiRoot` prop. See research.md Decision 1. |
| Nonce middleware singleton | Registered once in `index.js`; do NOT register again in the tab render. |
| Vendor library dependency | If `vendor/wpboilerplate/wpb-access-control/js/index.js` is absent, the build fails at alias resolution — run `composer install` first. |
| No new files | `webpack.config.js` change may not add a new entry point; only the `resolve.alias` key is modified. |

## Complexity Tracking

No constitution violations. No justification table needed.
