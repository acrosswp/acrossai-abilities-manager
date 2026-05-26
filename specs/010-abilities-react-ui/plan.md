# Implementation Plan: Abilities React UI + Admin Shell

**Branch**: `010-abilities-react-ui` | **Date**: 2026-05-23 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/010-abilities-react-ui/spec.md`

---

## Summary

Register a **Custom Abilities** WordPress admin submenu under Abilities Manager and mount a
React application that lets administrators create, edit, and override all abilities on the site.
The PHP shell follows the `LogsMenu.php` singleton pattern exactly. The React layer uses
`@wordpress/dataviews` `DataViews` for the list and `DataForm` for all forms — the Constitution
§III mandate — with custom `Edit:` field renderers supplying the design-specific controls
(callback-type chips, prefix slug input, dark-code textarea). Asset enqueuing follows the
Feature-006 logger pattern: a separate webpack entry, a new `$abilities_asset_file` property
in `Admin\Main`, and an `is_abilities_custom_page()` guard.

---

## Technical Context

**Language/Version**: PHP 7.4+ / JavaScript (ES2020) / React 18  
**Primary Dependencies**: `@wordpress/dataviews` (DataViews + DataForm), `@wordpress/data`, `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`  
**Storage**: `wp_acrossai_abilities` (Spec 008) — accessed via REST only  
**Testing**: PHPStan 8, PHPCS strict, ESLint, Jest (mirroring sitewide patterns)  
**Target Platform**: WordPress 6.9+ admin panel (desktop only, per spec Assumptions)  
**Performance Goals**: List interactive within 2 s (SC-002); next-page within 1 s  
**Constraints**: All interactive elements must use `var(--wp-admin-theme-color)` — never hardcode `#007cba`. No auto-save; explicit-save model with unsaved-changes indicator.  
**Scale/Scope**: Server-side pagination 20/page; REST endpoints from Spec 009

---

## Constitution Check

| Rule | Status | Notes |
|------|--------|-------|
| §III DataForm for ALL forms | ✅ COMPLIANT | Both Variant A and Variant B use `DataForm` with custom `Edit:` field renderers |
| §III DataViews for ALL lists | ✅ COMPLIANT | `AbilitiesList` uses `DataViews` from `@wordpress/dataviews` |
| §I AC-HOOKS-MAIN | ✅ COMPLIANT | `AcrossAI_Abilities_Menu` wired in `includes/Main.php::define_admin_hooks()` only |
| §I AC-ENQUEUE-ADMIN | ✅ COMPLIANT | All `wp_enqueue_*` in `Admin\Main::enqueue_scripts/styles` |
| §I Boot Flow variable-first | ✅ COMPLIANT | `$abilities_menu = AcrossAI_Abilities_Menu::instance();` before `add_action` |
| §II WPCS + PHPStan 8 | ✅ REQUIRED | Applied to all new PHP files |
| §II ESLint zero errors | ✅ REQUIRED | Applied to all new JS/JSX files |
| §IV Nonce + capability | ✅ COMPLIANT | `wp_create_nonce('wp_rest')` inline script; all REST endpoints require `manage_options` |
| §VI DRY — reuse existing | ✅ COMPLIANT | Reuses `McpVisibilityControl`, `ts2s`/`s2ts`, `AbilityTable` patterns |
| DEV1 (Accepted) | ✅ RETAINED | `McpVisibilityControl.jsx` reused as-is; no DataForm wrapper applied |

> **Constitution §III Resolution**: The spec's design uses custom WP-HTML classes for visual
> styling (`.tchips`, `.ecfg`, `.px-wrap`, etc.). These are implemented as custom `Edit:` field
> renderers passed to `DataForm`, not as free-standing HTML. `DataForm` handles field rendering,
> validation feedback, and submission state; the custom renderers supply the design-specific
> controls. This satisfies §III without discarding the design's visual language.

---

## Project Structure

```text
admin/
└── Partials/
    └── AcrossAI_Abilities_Menu.php   (NEW — singleton submenu page)

src/
├── js/abilities/
│   ├── index.js                      (NEW — apiFetch init + createRoot mount)
│   ├── api/
│   │   └── client.js                 (NEW — getAbilities, getAbility, createAbility,
│   │                                         updateAbility, deleteAbility, getCategories)
│   ├── store/
│   │   └── index.js                  (NEW — Redux store: state, thunks, selectors)
│   └── components/
│       ├── AbilitiesManager.jsx      (NEW — root; view routing list|create|edit|override)
│       ├── AbilitiesList.jsx         (NEW — DataViews table)
│       ├── AbilityForm.jsx           (NEW — DataForm form; Variant A + Variant B)
│       ├── CallbackConfigField.jsx   (NEW — dynamic config block Edit: renderer)
│       └── cells/
│           └── SourceBadge.jsx       (NEW — source → colored badge span)
└── scss/abilities/
    └── admin.scss                    (NEW — abilities-specific admin styles)

MODIFIED:
├── webpack.config.js                 (+2 entries: js/abilities, css/abilities)
├── admin/Main.php                    (+$abilities_asset_file property, +is_abilities_custom_page(),
│                                      +enqueue block in enqueue_scripts/styles)
└── includes/Main.php                 (+$abilities_menu wiring in define_admin_hooks())
```

---

## Phase 0 — Research (already done)

Key existing files examined:
- `admin/Partials/LogsMenu.php` — exact singleton pattern to mirror
- `admin/Main.php` — `$logger_asset_file` property + `is_logs_page()` guard to mirror
- `includes/Main.php:274` — `$logs_menu = LogsMenu::instance();` wiring pattern
- `src/js/sitewide/components/AbilityTable.jsx` — DataViews `fields`, `DEFAULT_VIEW`, `elements`
- `src/js/sitewide/components/AbilityEditPanel.jsx` — DataForm with custom `Edit:` field, `ts2s`/`s2ts`, `McpVisibilityControl` reuse
- `webpack.config.js` — existing `js/sitewide` + `css/sitewide` + `js/logger` + `css/logger` entries

---

## Phase 1 — PHP Admin Shell

### 1A. `admin/Partials/AcrossAI_Abilities_Menu.php` (NEW)

Mirror `LogsMenu.php` exactly:

```php
namespace AcrossAI_Abilities_Manager\Admin\Partials;
defined( 'ABSPATH' ) || exit;

class AcrossAI_Abilities_Menu {
    protected static $_instance = null;
    private $hook_suffix = '';

    public static function instance(): self { ... }
    private function __construct() { $this->hook_suffix = ''; }

    public function register_submenu(): void {
        $this->hook_suffix = add_submenu_page(
            'acrossai-abilities-manager',
            __( 'Custom Abilities', 'acrossai-abilities-manager' ),
            __( 'Custom Abilities', 'acrossai-abilities-manager' ),
            'manage_options',
            'acrossai-abilities-custom',
            array( $this, 'render' )
        );
    }

    public function render(): void {
        // SEC-010-01: Defense-in-depth capability check (FINDING-010-02).
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
        }
        ?>
        <div class="wrap">
            <div id="acrossai-abilities-root"></div>
        </div>
        <?php
    }

    public function get_hook_suffix(): string { return $this->hook_suffix; }
}
```

### 1B. `admin/Main.php` (MODIFY)

**Add** `use AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu;` to imports.

**Add** property:
```php
/** @var array|null */
private $abilities_asset_file;
```

**In constructor**, after the logger guard block:
```php
$abilities_asset_path = \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/abilities.asset.php';
if ( file_exists( $abilities_asset_path ) ) {
    $this->abilities_asset_file = include $abilities_asset_path;
}
```

**In `enqueue_styles()`**, after the logger block:
```php
if ( $this->abilities_asset_file && $this->is_abilities_custom_page( $hook_suffix ) ) {
    wp_register_style(
        'acrossai-abilities-manager-abilities',
        \ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/css/abilities.css',
        array(),
        $this->abilities_asset_file['version']
    );
    wp_enqueue_style( 'acrossai-abilities-manager-abilities' );
}
```

**In `enqueue_scripts()`**, after the logger block:
```php
if ( $this->abilities_asset_file && $this->is_abilities_custom_page( $hook_suffix ) ) {
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

**Add** private helper (after `is_logs_page()`):
```php
private function is_abilities_custom_page( string $hook_suffix ): bool {
    $abilities_menu = AcrossAI_Abilities_Menu::instance();
    return $hook_suffix === $abilities_menu->get_hook_suffix();
}
```

> **SEC-04**: Use `===` strict comparison — `get_hook_suffix()` returns a string; no `==` or `strpos`.

### 1C. `includes/Main.php` (MODIFY)

In `define_admin_hooks()`, immediately after the `$logs_menu` block (line ~276):

```php
// Custom Abilities submenu page (Feature 010).
$abilities_menu = \AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu::instance();
$this->loader->add_action( 'admin_menu', $abilities_menu, 'register_submenu' );
```

### 1D. `webpack.config.js` (MODIFY)

In the `entry` object, after the logger entries:
```js
'js/abilities': path.resolve( process.cwd(), 'src/js/abilities', 'index.js' ),
'css/abilities': path.resolve( process.cwd(), 'src/scss/abilities', 'admin.scss' ),
```

---

## Phase 2 — React Application

### 2A. `src/js/abilities/index.js`

```js
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import AbilitiesManager from './components/AbilitiesManager';
import { store } from './store';
import { Provider } from '@wordpress/data';

const { nonce } = window.acrossaiAbilitiesManager;

apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );

const root = document.getElementById( 'acrossai-abilities-root' );
if ( root ) {
    createRoot( root ).render(
        <Provider store={ store }>
            <AbilitiesManager />
        </Provider>
    );
}
```

### 2B. `src/js/abilities/api/client.js`

All calls use `wp.apiFetch` with `response.clone().json()` error handling pattern from
`src/js/sitewide/api/client.js`.

```js
const BASE = `${ window.acrossaiAbilitiesManager.rest_namespace }/abilities`;

export const getAbilities  = ( params )    => apiFetch( { path: BASE, data: params } );
export const getAbility    = ( id )        => apiFetch( { path: `${ BASE }/${ id }` } );
export const createAbility = ( data )      => apiFetch( { path: BASE, method: 'POST', data } );
export const updateAbility = ( id, data )  => apiFetch( { path: `${ BASE }/${ id }`, method: 'POST', data } );
export const deleteAbility = ( id )        => apiFetch( { path: `${ BASE }/${ id }`, method: 'DELETE' } );
export const getCategories = ()            => apiFetch( { path: `${ BASE }/categories` } );
```

Pagination: `getAbilities` reads `X-WP-Total` and `X-WP-TotalPages` from the response headers;
pass `{ parse: false }` to `apiFetch` so the raw `Response` object is returned, extract headers,
then parse the body — mirrors `src/js/sitewide/api/client.js`.

### 2C. `src/js/abilities/store/index.js`

Register a `@wordpress/data` store (`STORE_NAME = 'acrossai/abilities'`):

**State shape:**
```js
{
    abilities:    [],       // AcrossAI_Sitewide_Row[] formatted
    total:        0,
    pages:        1,
    categories:   [],       // [{ slug, label }]
    isLoading:    false,
    isSaving:     false,
    error:        null,
    view:         'list',   // 'list' | { mode: 'create' } | { mode: 'edit', id } | { mode: 'override', id }
    savedAbility: null,     // last server state (null = Add New)
    draftAbility: {},       // current form state
    isDirty:      false,    // JSON.stringify(draft) !== JSON.stringify(saved)
}
```

**Thunks**: `fetchAbilities`, `fetchAbility`, `createAbility`, `updateAbility`, `deleteAbility`,
`fetchCategories`, `setView`, `updateDraft`, `clearDraft`.

**`isDirty` computation**: computed in `setDraft` action — `JSON.stringify( draft ) !== JSON.stringify( saved )`.

**Error handling**: On REST failure, dispatch `setError` (string message); list keeps last-loaded
data (`abilities` array untouched). On save/delete failure, dispatch `setSaveError`; `isSaving`
resets to false; form stays open with edits intact (FR-035–037).

### 2D. `src/js/abilities/components/AbilitiesManager.jsx`

Root component. Reads `view` from store. Renders:
- `view === 'list'` → `<AbilitiesList />`
- `view.mode === 'create'` → `<AbilityForm mode="create" />`
- `view.mode === 'edit'` → `<AbilityForm mode="edit" id={ view.id } />`
- `view.mode === 'override'` → `<AbilityForm mode="override" id={ view.id } />`

`beforeunload` guard: registers/unregisters window listener when `isDirty` changes.

### 2E. `src/js/abilities/components/AbilitiesList.jsx`

**Constitution §III compliance**: Uses `DataViews` from `@wordpress/dataviews`.

Field config (`fields` array) — mirrors `AbilityTable.jsx` pattern but for the abilities data:

| id | label | getValue | enableSorting | elements |
|----|-------|----------|---------------|---------|
| `ability_slug` | Slug | slug after prefix strip | ✅ | — |
| `label` | Label | `item.label` | ✅ | — |
| `category` | Category | `item.category` | — | categories from store |
| `source` | Source | `item.source` | — | db/plugin/theme/core |
| `status` | Status | `item.status` | — | draft/publish |
| `callback_type` | Type | `item.callback_type` | — | noop/filter_hook/wp_remote_post/php_code |
| `show_in_mcp` | MCP | `item.show_in_mcp` | — | — |
| `updated_at` | Updated | formatted date | ✅ | — |

Custom `render` callbacks supply the visual badge markup (`.src-c`, `.tbadge`, `.mcp-y`, etc.).

**Row actions** via DataViews `actions` prop:
- `edit` — dispatches `setView({ mode:'edit', id })`
- `override` — dispatches `setView({ mode:'override', id })` (inherited only)
- `status-toggle` — inline `<select>` for quick publish/draft (db rows only)
- `delete` — confirm dialog → `deleteAbility(id)` (db rows only)

**Tablenav / quick-links**: Rendered above the DataViews component as WP-style HTML
(subsubsub quick-links, bulk-action `<select>`, source/status filter `<select>`, search `<input>`);
these are standard WordPress admin UI controls that feed into DataViews `view` state.

**Bulk action handler** — SEC-010-02 (FINDING-010-01): the bulk-apply handler MUST show a
`window.confirm()` dialog before dispatching a bulk delete operation:
```jsx
function handleBulkApply() {
    if ( 'delete' === bulkAction ) {
        const ok = window.confirm(
            `Delete ${ selectedIds.length } ${ selectedIds.length === 1 ? 'ability' : 'abilities' }? This cannot be undone.`
        );
        if ( ! ok ) return;
    }
    dispatch( bulkAction === 'delete'
        ? bulkDeleteAbilities( selectedIds )
        : bulkUpdateStatus( selectedIds, bulkAction ) );
}
```
This matches the individual-row delete confirmation (FR-024) extended to bulk operations.

**Pagination**: DataViews built-in `paginationInfo` + `onChangeView` — reads `total`/`pages`
from store (populated from REST `X-WP-Total`/`X-WP-TotalPages`).

**DEFAULT_VIEW**:
```js
{ type: 'table', perPage: 20, page: 1, sort: { field: 'ability_slug', direction: 'asc' },
  filters: [], search: '' }
```
View layout prefs persisted to `localStorage` (same pattern as `AbilityTable.jsx`).

**Inherited row styling**: DataViews `className` field callback returns `'inh-row'` for
`source !== 'db'` rows; CSS applies `background: #fafafa`.

### 2F. `src/js/abilities/components/AbilityForm.jsx`

**Constitution §III compliance**: Uses `DataForm` from `@wordpress/dataviews` for all
field groups. Complex controls are provided as custom `Edit:` field renderers.

**DataForm usage pattern** (follows `AbilityEditPanel.jsx`):
```jsx
<DataForm
    data={ draft }
    fields={ identityFields }
    form={ IDENTITY_FORM }
    onChange={ ( patch ) => dispatch( updateDraft( patch ) ) }
/>
```

#### Variant A — source=db (create or edit)

**Field groups** (each a separate `DataForm` instance or logically grouped):

1. **Identity fields** (`identityFields`):
   - `ability_slug_suffix` — custom `Edit:` renders `.px-wrap` prefix input
   - `label` — `TextControl`
   - `category` — custom `Edit:` renders `<select>` populated from store categories
   - `description` — `TextareaControl`
   - `status` — custom `Edit:` renders auto-register `.wptog` toggle (ON=publish / OFF=draft)

2. **Callback fields** (`callbackFields`):
   - `callback_type` — custom `Edit:` renders `.tchips` chip selector; noop selected by default
   - `callback_config` — custom `Edit:` renders `<CallbackConfigField>` (see §2G)

3. **Schema fields** (`schemaFields`):
   - `input_schema` — custom `Edit:` renders `.code-lt` textarea + `JSON.parse` blur validation
   - `output_schema` — same pattern

4. **Exposure fields**: Use `McpVisibilityControl` (DEV1) + `show_in_rest` TriState.
   `show_in_rest` uses the same `TriStateEditField` adapter from `AbilityEditPanel.jsx`.

5. **Annotation fields**: `readonly`, `destructive`, `idempotent` — each reuses `TriStateEditField`.

6. **Access Control fields**: `who_can_access` select + role checkboxes (UI-only per spec Assumptions).

**Sidebar boxes**: Rendered alongside DataForm as sibling JSX (not DataForm fields):
- Add New: Publish box (Add Ability + Save as Draft) + Preview + What Happens on Save
- Edit: Update box (Save Changes + Save as Draft + Delete link) + Preview + WHOS + Activity timeline

**Sticky bar**: Conditionally renders `"● Unsaved changes — leaving this page will discard them."`
when `isDirty`; Cancel + primary save button.

**Unsaved indicator** in page title: `isDirty &&  <span className="unsaved">● Unsaved changes</span>`

**Save flows**:
- Add New "✓ Add Ability": `createAbility(draft)` → on 201 → `setView({ mode:'edit', id })`
- Add New "Save as Draft": same but force `status: 'draft'`
- Edit "✓ Save Changes": `updateAbility(id, changedFields)` → on success → update `savedAbility`, clear dirty, stay on edit
- Edit "Save as Draft": same but force `status: 'draft'`
- Delete: confirm dialog → `deleteAbility(id)` → `setView('list')`

**Slug prefix**: `acrossai-abilities/` shown as read-only `.px-txt` text; user types only the suffix
in `.px-inp`; on submit prefix is prepended.

#### Variant B — source≠db (override)

**Locked card**: Rendered as plain HTML above DataForm (not a DataForm field):
```jsx
<div className="locked">
    <div className="lhdr">🔒 Registered by plugin: <strong>{ ability.provider }</strong>…</div>
    <div className="lgrid">…read-only slug, label, category, callback_type…</div>
</div>
```

**Override DataForm fields**:
- `site_allowed` — custom `Edit:` renders `.tchips` Force Block / Inherit / Force Allow
  (maps to 0 / null / 1)
- MCP exposure fields via `McpVisibilityControl` (DEV1 reuse) + `show_in_rest` TriState
- `readonly`, `destructive`, `idempotent` — `TriStateEditField` with "Plugin declares: X" hints
- Access control override — inherit option + grayed checkboxes while inheriting

**"↩ Clear All Overrides"** button: dispatches `clearOverrides(id)` thunk that PATCHes all
override fields to null.

**Save**: `updateAbility(id, overrideFieldsOnly)` — identity fields are never sent (enforces SC-007).

### 2G. `src/js/abilities/components/CallbackConfigField.jsx`

Custom `Edit:` field renderer used by `AbilityForm` for `callback_config`. Receives
`{ data, field, onChange }` from DataForm. Renders per `data.callback_type`:

- `noop`: info notice text in `.ecfg`
- `filter_hook`: `hook_name` `TextControl` in `.ecfg`
- `wp_remote_post`: URL `TextControl` + Method `<select>` (80px) + Timeout `NumberControl` (30 max) inline in `.ecfg`
- `php_code`: SEC-010-03 (FINDING-010-03) — render an execution warning label **above** the textarea,
  then the dark code editor:
  ```jsx
  <>
      <p className="description acrossai-php-warning">
          ⚠ PHP code runs server-side with plugin-level access.
          Variable <code>$input</code> contains the ability input.
          Blocked: <code>eval, exec, system, shell_exec, file_put_contents, unlink</code>.
      </p>
      <textarea
          className="rt lt dark-code"
          style={{ background: '#1e1e1e', color: '#a8ff60', minHeight: '72px' }}
          value={ data.callback_config?.code ?? '' }
          onChange={ ( e ) => onChange({ callback_config: { code: e.target.value } }) }
      />
  </>
  ```

### 2H. `src/js/abilities/components/cells/SourceBadge.jsx`

```jsx
export default function SourceBadge( { source } ) {
    const cls = { db: 'src-c', plugin: 'src-p', core: 'src-k', theme: 'src-t' }[ source ] ?? 'src-c';
    const label = { db: 'Custom', plugin: 'Plugin', core: 'Core', theme: 'Theme' }[ source ] ?? source;
    return <span className={ `src ${ cls }` }>{ label }</span>;
}
```

### 2I. `src/scss/abilities/admin.scss`

CSS classes from the design (§CSS Classes Reference in the master plan). Key rules:
- All interactive element colors use `var(--wp-admin-theme-color)` — **no hardcoded `#007cba`**
- Source badge colors: Custom `#e0f0ff/theme-color`, Plugin `#f0e8ff/#5a00aa`, Core `#e8f0e8/#1a5c1a`, Theme `#fff4e0/#7a4000`
- `.dark-code`: `background:#1e1e1e; color:#a8ff60`
- `.unsaved`: `font-size:12px; color:#996600; font-style:italic`
- `.locked`: `border-left:4px solid #c3c4c7; background:#fafafa`
- `.form-layout`: `grid-template-columns:1fr 236px; gap:16px`
- `.sbar` (sticky bar): `position:sticky; bottom:0`
- `.inh-row td`: `background:#fafafa`

---

## Security Constraints

- **Nonce**: `wp_create_nonce('wp_rest')` injected via `wp_add_inline_script` — never `wp_localize_script` (inline script nonce pattern from `admin/Main.php:164–174`)
- **Capability**: `manage_options` enforced at the PHP `add_submenu_page` call AND on all REST endpoints (Spec 009)
- **`is_abilities_custom_page()`**: uses `===` strict comparison (SEC-04)
- **REST endpoint permission**: All Spec 009 endpoints require `manage_options`; client-side filtering not needed (DEC-PROTECTED-SLUGS-PATTERN)
- **`php_code`**: Validation + blocked-function list enforced server-side in `AcrossAI_Abilities_Validator`; client shows server error inline (FR-037)
- **Slug prefix**: prepended server-side in Spec 009 Write controller; client sends only suffix (cannot inject arbitrary prefix)
- **Override identity lock**: Write controller strips identity fields from override-row updates (SC-007)
- **Post-save hook integrity** (SEC-010-04 / FINDING-010-04): Spec 009 Write Controller MUST call
  `get_ability_by_id($id)` after every DB write and pass the **full saved row** to any
  `acrossai_abilities_after_save` action hook — never the sparse `$fields` input. This prevents
  the partial-payload bug documented in `docs/memory/BUGS.md` (2026-05-17).

---

## Error Handling Implementation (FR-035–037)

- REST failure in list: `AbilitiesList` shows dismissible WP `Notice` above the table; `abilities` state untouched
- REST failure in save/delete: `AbilityForm` shows dismissible WP `Notice` below the form header; `isDirty` stays true; save button re-enabled
- `abilities.asset.php` missing: PHP logs a `trigger_error()` notice; page renders without the React mount (graceful degradation, spec Assumptions)

---

## Reuse Map

| Existing file | What to reuse |
|---|---|
| `admin/Partials/LogsMenu.php` | Exact singleton pattern |
| `admin/Main.php` constructor | `$logger_asset_file` → `$abilities_asset_file` pattern |
| `admin/Main.php::is_logs_page()` | → `is_abilities_custom_page()` pattern |
| `includes/Main.php:274–275` | `$logs_menu` wiring → `$abilities_menu` wiring |
| `src/js/sitewide/components/AbilityTable.jsx` | DataViews `fields`, `elements`, `DEFAULT_VIEW` |
| `src/js/sitewide/components/AbilityEditPanel.jsx` | `ts2s`/`s2ts`, `TriStateEditField`, DataForm pattern |
| `src/js/sitewide/components/McpVisibilityControl.jsx` | Full reuse (DEV1) |
| `src/js/sitewide/api/client.js` | `apiFetch` error handling + header-parse pattern |
| `src/js/sitewide/store/index.js` | Redux thunk + optimistic-update pattern |

---

## Verification Checklist

### Build
1. `npm run build` → `build/js/abilities.js`, `build/css/abilities.css`, `build/js/abilities.asset.php` generated (non-zero)

### Admin Shell
2. "Custom Abilities" submenu appears under Abilities Manager → ⚡ Abilities → Custom Abilities
3. URL: `?page=acrossai-abilities-custom`
4. `<div id="acrossai-abilities-root">` present; React app mounts
5. `console.log(window.acrossaiAbilitiesManager)` → `{nonce, rest_url, rest_namespace, current_user_id}`
6. Assets NOT loaded on Sitewide or Logs pages (feature guard works)

### List View
7. Table renders; Custom rows show Edit + status dropdown + Delete; Inherited rows show Edit + Override
8. Source badges: Custom=blue, Plugin=purple, Core=green, Theme=amber
9. Status: Custom rows show dot+text (Published/Draft); Inherited rows show ibadge
10. Inline status dropdown on Custom row → ability published/drafted immediately
11. Search filters by slug/label; Source/Status dropdowns filter correctly
12. Bulk: Publish / Unpublish work on selected rows; **Bulk Delete shows `window.confirm()` before proceeding** (SEC-010-02)
13. Inherited rows have `#fafafa` background
14. Quick-links (All / Published / Draft) show correct counts
15. Pagination: 20 rows/page; next/prev navigation works; `X-WP-Total` header drives total count

### Add New (Variant A)
16. "Add New Ability" → form with breadcrumb `All Abilities › Add New`
17. Category `<select>` populated from REST `/abilities/categories`
18. `noop` chip selected by default; `.ecfg` shows info notice
19. `filter_hook` chip → `hook_name` TextControl in `.ecfg`
20. `wp_remote_post` chip → URL + Method + Timeout fields
21. `php_code` chip → execution warning label appears above dark monospace textarea (`#1e1e1e / #a8ff60`) (SEC-010-03)
22. "✓ Add Ability" → POST → 201 → redirects to Edit screen
23. "Save as Draft" → creates with `status=draft`
24. Slug stored as `acrossai-abilities/<suffix>`

### Edit Custom (Variant A Edit)
25. Breadcrumb shows full slug; "● Unsaved changes" appears on any field change
26. Sticky bar note appears only when dirty
27. "✓ Save Changes" → POST sparse diff → success → dirty clears; admin stays on edit
28. "Save as Draft" forces `status=draft`
29. "🗑 Delete Ability" → confirm → DELETE → 204 → returns to list
30. Activity sidebar shows Updated + Created timestamps
31. `beforeunload` fires confirm when navigating away with unsaved changes

### Override Inherited (Variant B)
32. Edit inherited row → `<h1>Override Ability</h1>` + source badge
33. Locked card shows identity fields read-only
34. Site Permission chips: Force Block / Inherit / Force Allow → `site_allowed` 0/null/1
35. MCP Type select first option shows "inherit (X)" where X = plugin-declared value
36. Annotation hints show "Plugin declares: X"
37. Access control checkboxes grayed while "inherit" selected
38. "✓ Save Overrides" → sparse update; identity fields NOT sent
39. "↩ Clear All Overrides" → resets all override fields to null

### Color Scheme
40. Switch to Ocean color scheme → primary buttons, active chips, links, focus rings update to teal; no `#007cba` remnants

### Regression
41. Sitewide Abilities page still loads; table renders; override saves work
42. Logs page still loads; log table renders
