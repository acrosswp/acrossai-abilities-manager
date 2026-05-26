# Spec 010 — Abilities React UI + Admin Shell

**Branch**: `010-abilities-react-ui`
**Depends on**: Spec 009 (REST endpoints must exist and return data)
**Blocks**: nothing (final spec in 008-010 series)
**Design reference**: `Abilities Manager - Final Design.html` (4-screen hi-fi mockup)

> **Slug note**: The design prototype uses `acrossai-custom-abilities/` as the slug prefix.
> Implementation uses **`acrossai-abilities/`** (from spec 009). Use the implementation slug everywhere.

---

## What this spec does

- `AcrossAI_Abilities_Menu` — registers "Custom Abilities" submenu under Abilities Manager, renders React mount point
- React app (`src/js/abilities/`) — full CRUD UI for custom abilities + override UI for inherited abilities
- Webpack entry `js/abilities` + `css/abilities`
- `admin/Main.php` — conditional enqueue on abilities page
- `includes/Main.php` — `admin_menu` hook

---

## Save Model — EXPLICIT SAVE (not per-field auto-save)

The design uses **explicit save buttons** with an unsaved-changes indicator.

| Screen | Primary action | Secondary |
|---|---|---|
| Add New | "✓ Add Ability" → `POST /abilities` | "Save as Draft" (forces `status=draft`) |
| Edit Custom | "✓ Save Changes" → `POST /abilities/{id}` | "Save as Draft" |
| Override Inherited | "✓ Save Overrides" → `POST /abilities/{id}` | "↩ Clear All Overrides" |

**Unsaved state tracking:**
- React tracks `savedAbility` (server state) and `draftAbility` (current form state)
- `isDirty = JSON.stringify(draft) !== JSON.stringify(saved)`
- When dirty: show `<span class="unsaved">● Unsaved changes</span>` in page title
- Sticky bar note shows "● Unsaved changes — leaving this page will discard them." when dirty
- `beforeunload` event + React navigation guard fires confirm dialog when navigating away dirty

**Auto-register toggle maps to status:**
- Toggle ON → `status=publish` (registered at every page load)
- Toggle OFF → `status=draft` (saved but not registered)
- "Save as Draft" button always forces `status=draft` regardless of toggle state

---

## Component Architecture

```
AbilitiesManager (root — manages view state)
  view: 'list' | { mode:'create' } | { mode:'edit', id } | { mode:'override', id }
  ├── AbilitiesList   (view='list')
  │     WP-style table, 9 columns, filters, bulk actions
  └── AbilityForm     (view=create/edit/override)
        Variant A (source=db):  full editable form
        Variant B (source≠db):  locked card + override fields
        ├── CallbackConfigField  — dynamic config block per callback_type
        └── SidePanel           — sidebar boxes (Publish/Update/Actions + Preview + What Happens on Save)
```

---

## Redux Store Shape

```js
{
    // List state
    abilities:      [],       // AcrossAI_Sitewide_Row[] formatted
    total:          0,
    pages:          1,
    categories:     [],       // [{ slug, label }]
    isLoading:      false,
    error:          null,

    // View routing
    view:           'list',   // 'list' | { mode:'create' } | { mode:'edit', id } | { mode:'override', id }

    // Form state
    savedAbility:   null,     // last fetched server row (null = create mode)
    draftAbility:   {},       // current form values
    isDirty:        false,    // computed from saved vs draft
    isSaving:       false,
}
```

---

## Global Config Object (via `wp_add_inline_script`)

```js
window.acrossaiAbilitiesManager = {
    nonce:           '<?= wp_create_nonce("wp_rest") ?>',
    rest_url:        '<?= untrailingslashit(rest_url()) ?>',
    rest_namespace:  'acrossai-abilities-manager/v1',
    current_user_id: <?= get_current_user_id() ?>,
}
```

---

## API Client (`src/js/abilities/api/client.js`)

All calls use `wp.apiFetch` with nonce middleware set in `index.js`.

```js
getAbilities(params)       // GET  /acrossai-abilities-manager/v1/abilities
                            //      returns flat array + X-WP-Total / X-WP-TotalPages headers
getAbility(id)             // GET  /acrossai-abilities-manager/v1/abilities/{id}
createAbility(data)        // POST /acrossai-abilities-manager/v1/abilities       → 201
updateAbility(id, data)    // POST /acrossai-abilities-manager/v1/abilities/{id}  → 200 (sparse)
deleteAbility(id)          // DELETE /acrossai-abilities-manager/v1/abilities/{id} → 204
getCategories()            // GET  /acrossai-abilities-manager/v1/abilities/categories
```

Error handling: `response.clone().json()` pattern from `src/js/sitewide/api/client.js`.

---

## Screen 1 — List View (`AbilitiesList`)

### Page header
- `<h1>Custom Abilities</h1>` + `<a class="title-action">+ Add New Ability</a>`
- Subtitle: "Manage abilities created on this site and override how plugin, theme and core abilities behave."

### Quick-links (subsubsub)
`All (N)` | `Published (N)` | `Draft (N)` — counts fetched from REST

### Tablenav (above table)
- Bulk Actions select + Apply button
- Source filter: All Sources / Custom / Plugin / Core / Theme
- Status filter: All Statuses / Published / Draft
- Search input (🔍 icon, flex:0 0 240px)
- Item count right-aligned

### Table columns (`.wptable`)

| CSS width | Column | Display |
|---|---|---|
| 24% `.col-slug` | Slug | `<span class="slug-dim">acrossai-abilities/</span><span class="slug-name">suffix</span>` monospace 11px |
| 17% `.col-lbl` | Label | Bold 13px; inherited rows add `<div class="lbl-by">by provider</div>` |
| 9% `.col-cat` | Category | `<span class="cpill">` gray rounded pill |
| 8% `.col-src` | Source | `<span class="src src-c/src-p/src-k/src-t">` |
| 10% `.col-sta` | Status | Custom: `.sta-on/.sta-off` dot + "Enabled/Disabled"; Inherited: `.ibadge .ib-a/.ib-d/.ib-b` |
| 12% `.col-typ` | Type | `.tbadge .tb-r/.tb-f/.tb-n/.tb-p` monospace pill |
| 7% `.col-mcp` | MCP | `.mcp-y` ✓ Yes / `.mcp-n` ○ No |
| auto `.col-act` | Actions | see below |

### Row actions
- **Custom rows**: `Edit` | `<select class="sdd">Published / Draft</select>` | `Delete` (red)
- **Inherited rows**: `Edit` | `Override`

**Inherited row style**: `tr.inh-row td { background: #fafafa; }` hover `#f3f5f7`

### Status badge mappings

| `status` DB value | `site_allowed` | List display |
|---|---|---|
| `publish` (source=db) | — | `.sta-on` green dot + "Enabled" |
| `draft` (source=db) | — | `.sta-off` gray ring + "Disabled" |
| any (source≠db) | `1` | `.ibadge.ib-a` "Allowed" |
| any (source≠db) | `null` | `.ibadge.ib-d` "Default" |
| any (source≠db) | `0` | `.ibadge.ib-b` "Blocked" |

### Inline status dropdown (Custom rows only)
Quick-publish: change to "Published" → `updateAbility(id, {status:'publish'})`; change to "Draft" → `updateAbility(id, {status:'draft'})`

---

## Screen 2 — Add New Ability (Variant A Create)

### Page header
- Breadcrumb: `All Abilities › Add New`
- `<h1>Add New Ability</h1>`

### Form sections

#### Identity (`.postbox`)
| Field | Control |
|---|---|
| Slug * | `.px-wrap`: `.px-txt` = `acrossai-abilities/` (read-only); `.px-inp` monospace input for suffix |
| Label * | `<input class="rt">` |
| Category * | `<select class="rs">` max-width 260px, populated from `getCategories()` |
| Description | `<textarea class="rt lt">` |
| Auto-register | `.wptog` toggle: ON=`status=publish`, OFF=`status=draft` |

#### Callback (`.postbox`)
- **Type chips** (`.tchips`): `noop` [selected] / `filter_hook` / `wp_remote_post` / `php_code`
  - Active chip: `.tc.on` — `border-color: var(--wp-admin-theme-color); background: #e8f4fb;`
- **Config block** (`.ecfg`) shown directly below chips:
  - **noop**: info text "No code runs. Useful for declarative or schema-only abilities..."
  - **filter_hook**: hook_name text input + desc
  - **wp_remote_post**: URL input (full width) + inline row with Method select (90px) + Timeout input (80px, 1–30s)
  - **php_code**: `<textarea class="rt lt code-lt dark-code">` — `background:#1e1e1e;color:#a8ff60;min-height:72px;font-family:monospace` — desc: "Variable `$input` contains the ability input."

#### Schema — optional (`.postbox`)
- Input Schema: `.rt.lt.code-lt` textarea, placeholder JSON
- Output Schema: `.rt.lt.code-lt` textarea, placeholder JSON
- JSON validation on blur (`JSON.parse()`); inline error if invalid; stored as string in draft, parsed on submit

#### MCP Exposure (`.postbox`)
- Show in MCP: `.wptog`
- MCP Type: `<select>` tool / resource / prompt
- Allowed Servers: `<input>` default `*`, desc "Comma-separate server IDs to restrict."

#### Annotations (`.postbox`)
- Readonly / Destructive / Idempotent: `<select>` inherit / yes / no (max-width 160px)

#### Access Control (`.postbox`)
- Who can access: `<select>` WordPress Role / Specific Users / Everyone (public) / No one (disabled)
- WordPress Role checkboxes: Administrator (always checked, disabled) / Editor / Author / Contributor / Subscriber

### Sidebar (`.form-side` sticky)
1. **Publish** box: "✓ Add Ability" (primary, full width) + "Save as Draft" (secondary)
2. **Preview** box: Slug (italic/muted until filled) / Source Custom badge / Callback type badge
3. **What Happens on Save** box (teal header): bullets about `wp_acrossai_abilities`, registration, MCP

### Sticky bar (`.sbar`)
- Left: "Fill in required fields (*) before saving."
- Right: Cancel + "✓ Add Ability" (primary)

---

## Screen 3 — Edit Custom Ability (Variant A Edit)

Same form sections as Add New, pre-populated. Key differences:

### Page header
- Breadcrumb: `All Abilities › acrossai-abilities/<slug>` (monospace 11px)
- `<h1>Edit Ability</h1>` + `.src.src-c Custom` badge + `<span class="unsaved"><span class="udot"></span>Unsaved changes</span>` (shown when `isDirty`)

### Slug field note
Description changes to: `"⚠ Changing the slug will break existing integrations that reference this ability."`

### Sidebar (Edit mode)
1. **Update** box: "✓ Save Changes" (primary) + "Save as Draft" + dashed divider + "🗑 Delete Ability" (`.button-link.button-link-delete`)
2. **Preview** box: Slug / Label / Source / Callback / MCP type
3. **What Happens on Save** box
4. **Activity** box: timeline dots (Updated: green dot + timestamp / Created: gray ring + timestamp)

### Sticky bar
- Left: `"● Unsaved changes — leaving this page will discard them."` (shown only when `isDirty`)
- Right: Cancel + "✓ Save Changes" (primary)

---

## Screen 4 — Override Inherited Ability (Variant B)

### Page header
- Breadcrumb: `All Abilities › <full-slug>` (monospace 11px)
- `<h1>Override Ability</h1>` + source badge (`.src.src-p/src-k/src-t`)
- Subtitle: "Identity defined by **[Provider]** — read-only. You can only override site permission, MCP exposure, and annotations."

### Locked card (`.locked`)
```scss
.locked  { background:#fafafa; border:1px solid $border; border-left:4px solid $muted; }
.lhdr    { display:flex; align-items:center; gap:8px; background:$row; border-bottom:1px solid $border; }
```
- Header: `🔒 Registered by plugin: <strong>Provider Name</strong>` + source badge (right-aligned `margin-left:auto`)
- Grid `.lgrid` (2-col): Full Slug / Label / Category / Callback Type
- Description below grid

### Override form sections

#### Site Permission Override
- `.tchips`: Force Block / **Inherit (plugin default)** [selected default] / Force Allow
- Maps to `site_allowed`: 0 / null / 1

#### MCP Exposure Override
- Show in MCP toggle (with "(plugin default: yes/no)" hint text)
- MCP Type: first option `inherit (resource)` showing plugin's declared value; then tool / resource / prompt
- Allowed Servers input

#### Annotation Overrides
- Readonly / Destructive / Idempotent: `<select>` inherit / yes / no
- Each has desc: "Plugin declares: X"

#### Access Control Override
- Who can access: first option `inherit (WordPress Role)` + others
- Role checkboxes: `opacity:.4` (disabled) when "inherit" selected; desc "Inheriting from plugin — overrides disabled."

### Sidebar (Override mode)
1. **Actions** box: "✓ Save Overrides" (primary) + "↩ Clear All Overrides" (link)
2. **Preview** box: Slug / Label / Source badge / Permission ibadge
3. **What Happens on Save** box: bullets noting overrides saved, plugin not modified
4. **Active Overrides** box: list of fields with non-null override values, or "No overrides set"

### Sticky bar
- Left: "Changes affect this site only — the plugin definition is not modified."
- Right: Cancel + "✓ Save Overrides" (primary)

---

## CSS Classes and SCSS

**Import strategy**:
```scss
// src/scss/abilities/admin.scss
@use '@wordpress/base-styles' as *;  // WP SCSS variables if available
// Use var(--wp-admin-theme-color) for ALL primary/accent interactive elements
```

**Key class reference**:
```scss
// WP Admin Color Scheme — ALWAYS use variables, never hardcode #007cba
// var(--wp-admin-theme-color)           → buttons, links, focus, active chips
// var(--wp-admin-theme-color-darker-10) → hover states

// Fixed WP admin neutrals (safe to hardcode)
$border: #c3c4c7; $muted: #646970; $txt: #1d2327; $bg: #f0f0f1; $row: #f6f7f7;
$green: #00a32a; $red: #d63638; $warn: #dba617;

// Source badges
.src-c { background:#e0f0ff; color:var(--wp-admin-theme-color); }  // Custom
.src-p { background:#f0e8ff; color:#5a00aa; }                       // Plugin
.src-k { background:#e8f0e8; color:#1a5c1a; }                       // Core
.src-t { background:#fff4e0; color:#7a4000; }                       // Theme

// Status
.sta-on  { color:#007017; }  .sta-on  .sta-dot { background:$green; }
.sta-off { color:$muted;  }  .sta-off .sta-dot { border:1.5px solid $muted; }
.ibadge.ib-a { background:#edfaef; color:#007017; border:1px solid #b5e6bc; }
.ibadge.ib-d { background:#f6f7f7; color:$muted;  border:1px solid $border; }
.ibadge.ib-b { background:#fceaea; color:#8a0000; border:1px solid #f0b0b0; }

// Type badges (monospace)
.tbadge     { font-family: "SFMono-Regular",Consolas,monospace; }
.tb-r       { background:#fff3e0; color:#7a3500; border:1px solid #f0c080; }  // wp_remote_post
.tb-f       { background:#e8eeff; color:#2038a0; border:1px solid #b0c0f0; }  // filter_hook
.tb-n       { background:#f0f0f0; color:$muted;  border:1px solid $border; }  // noop
.tb-p       { background:#fff0e0; color:#803000; border:1px solid #f0b080; }  // php_code

// Form chips
.tc.on      { border-color:var(--wp-admin-theme-color); background:#e8f4fb; color:var(--wp-admin-theme-color); }

// PHP code textarea
.dark-code  { background:#1e1e1e; color:#a8ff60; border-color:#444; min-height:72px; }

// Unsaved indicator
.unsaved    { font-size:12px; color:#996600; font-style:italic; margin-left:12px; }
.udot       { width:7px; height:7px; border-radius:50%; background:#cc8800; }

// Locked card
.locked     { border-left:4px solid $muted; }

// Sidebar header teal accent
.sbhdr.teal { color:#007a7a; }
```

---

## Files to Create

### PHP admin shell (1 file — singleton pattern from `admin/Partials/LogsMenu.php`)
```
admin/Partials/
└── AcrossAI_Abilities_Menu.php
      static instance(), register_submenu(), render(), get_hook_suffix()
      add_submenu_page(
          parent_slug: 'acrossai-abilities-manager',
          page_title:  'Custom Abilities',
          menu_title:  'Custom Abilities',
          capability:  'manage_options',
          menu_slug:   'acrossai-abilities-custom',
          callback:    [$this, 'render']
      )
      render(): echo '<div class="wrap"><div id="acrossai-abilities-root"></div></div>'
```

### React app
```
src/js/abilities/
├── index.js                       — apiFetch nonce middleware + createRoot → #acrossai-abilities-root
├── api/
│   └── client.js                  — getAbilities / getAbility / createAbility / updateAbility / deleteAbility / getCategories
├── store/
│   └── index.js                   — Redux: state, actions (thunks), selectors, reducer
└── components/
    ├── AbilitiesManager.jsx        — root: view state routing + renders list or form
    ├── AbilitiesList.jsx           — WP-style table, tablenav, bulk actions, subsubsub
    ├── AbilityForm.jsx             — Variant A (source=db) / Variant B (source≠db)
    ├── CallbackConfigField.jsx     — dynamic config block: noop/filter_hook/wp_remote_post/php_code
    ├── SidePanel.jsx               — sidebar boxes (Publish/Update/Actions + Preview + What Happens on Save)
    └── cells/
        └── SourceBadge.jsx         — source → .src badge with correct class

src/scss/abilities/
└── admin.scss
```

## Files to Modify

| File | Change |
|---|---|
| `webpack.config.js` | Add to entry object: `'js/abilities': path.resolve(cwd, 'src/js/abilities/index.js')` and `'css/abilities': path.resolve(cwd, 'src/scss/abilities/admin.scss')` |
| `admin/Main.php` | Add `$abilities_asset_file` property; load `build/js/abilities.asset.php` in constructor (with `file_exists` guard); add `is_abilities_custom_page(string $hook): bool` helper; enqueue `acrossai-abilities-manager-abilities` script + style + `wp_add_inline_script('acrossai-abilities-manager-abilities', 'window.acrossaiAbilitiesManager = ' . json_encode([...]) . ';', 'before')` only on abilities page |
| `includes/Main.php` | Inside `define_admin_hooks()`, after LogsMenu line: `$abilities_menu = AcrossAI_Abilities_Menu::instance(); $this->loader->add_action('admin_menu', $abilities_menu, 'register_submenu');` |

---

## Reuse from Existing Code

| Existing file | What to reuse |
|---|---|
| `src/js/sitewide/store/index.js` | Redux store shape, thunk pattern, optimistic updates, selector pattern |
| `src/js/sitewide/api/client.js` | apiFetch error handling (`response.clone().json()`), nonce middleware init |
| `src/js/sitewide/components/AbilityTable.jsx` | DataViews field config, DEFAULT_VIEW, localStorage persistence pattern |
| `src/js/sitewide/components/AbilityEditPanel.jsx` | `ts2s()`/`s2ts()` tri-state helpers, `draftsEqual()`, TabFooter pattern |
| `src/js/sitewide/components/McpVisibilityControl.jsx` | MCP visibility section reuse for Variant A MCP Exposure section |
| `src/js/sitewide/components/cells/TriStateBadgeCell.jsx` | Tri-state display for readonly/destructive/idempotent list columns |
| `admin/Partials/LogsMenu.php` | Exact singleton pattern: `static $instance`, `instance()`, `register_submenu()`, `render()`, `get_hook_suffix()` |
| `admin/Main.php` | `file_exists()` guard + `include` for `.asset.php`; `is_logs_page()` → `is_abilities_custom_page()` |

---

## Speckit Commands — Run in Order

### Step 1 — Create feature branch
```
/speckit.git.feature
```
Enter: `010-abilities-react-ui`

---

### Step 2 — Write the spec
```
/speckit.specify Abilities React UI and admin shell for the Custom Abilities admin page.

PHP shell: AcrossAI_Abilities_Menu singleton (follows LogsMenu.php pattern exactly) — register_submenu() adds 'Custom Abilities' submenu under parent 'acrossai-abilities-manager' with slug 'acrossai-abilities-custom', render() outputs <div class="wrap"><div id="acrossai-abilities-root"></div></div>, get_hook_suffix() stores the add_submenu_page return value.

admin/Main.php: add $abilities_asset_file property, load build/js/abilities.asset.php with file_exists guard in constructor, add is_abilities_custom_page(string $hook_suffix): bool private helper, enqueue script handle 'acrossai-abilities-manager-abilities' + style + wp_add_inline_script with window.acrossaiAbilitiesManager={nonce,rest_url,rest_namespace,current_user_id} only on abilities custom page.

includes/Main.php: inside define_admin_hooks() add $abilities_menu = AcrossAI_Abilities_Menu::instance(); $this->loader->add_action('admin_menu', $abilities_menu, 'register_submenu'); after the LogsMenu line.

webpack.config.js: add entry 'js/abilities' → src/js/abilities/index.js and 'css/abilities' → src/scss/abilities/admin.scss.

React app in src/js/abilities/:
- index.js: wp.apiFetch nonce middleware init using window.acrossaiAbilitiesManager.nonce, createRoot mount into #acrossai-abilities-root
- api/client.js: getAbilities(params), getAbility(id), createAbility(data), updateAbility(id,data) sparse, deleteAbility(id), getCategories() — all use wp.apiFetch with response.clone().json() error handling
- store/index.js: Redux store with state={abilities:[],total,pages,categories:[],isLoading,error,view:'list',savedAbility:null,draftAbility:{},isDirty:false,isSaving:false}; thunks for fetchAbilities, fetchAbility, createAbility, updateAbility, deleteAbility, fetchCategories; selectors
- components/AbilitiesManager.jsx: root, view routing, renders AbilitiesList (view='list') or AbilityForm (view=create/edit/override)
- components/AbilitiesList.jsx: WP-style table (.wptable) with columns slug(split at prefix), label(+lbl-by for inherited), category(.cpill), source(.src badge src-c/src-p/src-k/src-t), status(sta-on/sta-off for custom, ibadge ib-a/ib-d/ib-b for inherited), type(.tbadge tb-r/tb-f/tb-n/tb-p), mcp(.mcp-y/.mcp-n), actions(Edit+status-dropdown+Delete for custom, Edit+Override for inherited); tablenav with bulk actions(publish/unpublish/delete), source filter, status filter, search; subsubsub quick-links All/Published/Draft; inherited rows get .inh-row class for #fafafa background; inline status dropdown on custom rows for quick publish/draft toggle
- components/AbilityForm.jsx: Variant A (source=db, editable) and Variant B (source!=db, override). EXPLICIT SAVE MODEL: React tracks savedAbility and draftAbility, isDirty=JSON.stringify(draft)!==JSON.stringify(saved), show .unsaved indicator in title when isDirty, sticky bar note only when isDirty, beforeunload confirm when isDirty. Variant A sections: Identity(slug with px-wrap prefix acrossai-abilities/ + suffix input, label, category select, description, auto-register toggle maps to status=publish/draft), Callback(tchips noop/filter_hook/wp_remote_post/php_code with ecfg config block below; php_code uses dark-code textarea background:#1e1e1e color:#a8ff60), Schema optional(input_schema+output_schema code textareas with JSON.parse validation on blur), MCP Exposure(wptog+mcp_type select+servers input), Annotations(readonly/destructive/idempotent selects inherit/yes/no), Access Control(who-can-access select + role checkboxes). Sidebar: Add New=Publish box(Add Ability+Save as Draft)+Preview+What Happens on Save; Edit=Update box(Save Changes+Save as Draft+Delete link)+Preview+WHOS+Activity timeline. Sticky bar with cancel+save buttons. Variant B: locked card(.locked border-left:4px solid muted) with lhdr showing provider name and source badge, lgrid 2-col read-only fields (slug/label/category/callback); then override sections: Site Permission Override(tchips Force Block/Inherit/Force Allow maps to site_allowed 0/null/1), MCP Exposure Override(toggle+type select with inherit option+servers), Annotation Overrides(selects with Plugin declares hint), Access Control Override(inherit option + grayed checkboxes while inheriting). Sidebar: Actions box(Save Overrides+Clear All Overrides)+Preview+WHOS+Active Overrides list.
- components/CallbackConfigField.jsx: renders correct config block for noop(info notice)/filter_hook(hook_name input)/wp_remote_post(url+method+timeout inline)/php_code(dark monospace textarea)
- components/cells/SourceBadge.jsx: source string → span with correct src-c/src-p/src-k/src-t class
- src/scss/abilities/admin.scss: WP-style table styles, form layout, postbox, form rows, prefix input, type chips, exec config block, dark code textarea, unsaved indicator, locked card, sidebar boxes — using var(--wp-admin-theme-color) for all primary/accent/interactive elements instead of hardcoded #007cba
```

---

### Step 3 — Generate plan with memory context
```
/speckit.memory-md.plan-with-memory feature=010-abilities-react-ui scope=specs/010-abilities-react-ui,docs/memory decision_limit=5 filter=architecture
```

---

### Step 4 — Architecture + security validation
```
/speckit.architecture-guard.governed-plan
```
```
/speckit.security-review.plan
```

---

### Step 5 — Generate tasks
```
/speckit.architecture-guard.governed-tasks
```

---

### Step 6 — Implement
```
/speckit.architecture-guard.governed-implement
```

---

### Step 7 — Quality gates
```bash
# Build
npm run build

# Confirm outputs
ls -la build/js/abilities.js build/js/abilities.asset.php build/css/abilities.css

# PHP syntax
php -l admin/Partials/AcrossAI_Abilities_Menu.php
php -l admin/Main.php
php -l includes/Main.php

# Standards
composer run phpcs -- --filter=abilities
composer run phpstan -- --paths=admin/Partials/AcrossAI_Abilities_Menu.php,admin/Main.php,includes/Main.php

# JS lint
npm run lint:js -- src/js/abilities/
```

---

### Step 8 — Post-implement review
```
/speckit.analyze
```
```
/speckit.architecture-guard.architecture-review
```
```
/speckit.security-review.staged
```

---

### Step 9 — Save memory + commit
```
/speckit.memory-md.capture-from-diff
```
```
/speckit.git.commit
```

---

## Manual Testing

### Setup — seed test data
```bash
wp db query "SELECT ability_slug, label, status, source FROM wp_acrossai_abilities LIMIT 10;"
# If empty, insert test rows:
wp db query "
INSERT INTO wp_acrossai_abilities
  (ability_slug, label, description, category, status, source, callback_type, show_in_mcp, mcp_type)
VALUES
  ('acrossai-abilities/test-published', 'Test Published', 'A published ability', 'content', 'publish', 'db', 'noop', 1, 'tool'),
  ('acrossai-abilities/test-draft', 'Test Draft', 'A draft ability', 'content', 'draft', 'db', 'filter_hook', 0, NULL),
  ('acrossai-abilities/test-remote', 'Test Remote Post', 'HTTP ability', 'site-admin', 'publish', 'db', 'wp_remote_post', 1, 'tool');
"
```

### Build and Admin Shell
1. `npm run build` → confirm `build/js/abilities.js` exists, non-zero size
2. Admin → Abilities Manager → "Custom Abilities" submenu visible
3. Click → page loads, no JS console errors
4. `console.log(window.acrossaiAbilitiesManager)` → `{nonce, rest_url, rest_namespace, current_user_id}`

### List View
5. Table renders with slug (dimmed prefix + bold suffix), source badge, status dot/badge, type badge, MCP
6. Custom rows: Edit | [status dropdown] | Delete; Inherited rows: Edit | Override
7. Inherited rows have `#fafafa` background
8. Source badge colors: Custom=blue, Plugin=purple, Core=green, Theme=amber
9. Search input → filters by slug/label
10. Source filter → shows only selected source
11. Status filter → shows only published/draft
12. Inline status dropdown on custom row → changes status instantly
13. Bulk select → bulk actions toolbar; Publish/Unpublish/Delete fire correctly

### Add New — Variant A
14. "Add New Ability" button → form loads with breadcrumb `All Abilities › Add New`
15. Category dropdown populated from REST
16. `noop` chip selected by default; ecfg shows "no execution" info notice
17. Click `filter_hook` → ecfg shows hook_name input
18. Click `wp_remote_post` → ecfg shows URL (full width) + Method + Timeout (inline row)
19. Click `php_code` → ecfg shows dark textarea `background:#1e1e1e;color:#a8ff60`
20. Type in any field → no unsaved indicator (no prior saved state = create mode)
21. "Save as Draft" → `POST /abilities` with `status=draft` → redirects to Edit view
22. "✓ Add Ability" → `POST /abilities` with Auto-register state → redirects to Edit view
23. Slug stored as `acrossai-abilities/<suffix>`, displayed in list with prefix dimmed

### Edit Custom — Variant A Edit
24. Click Edit on custom row → `<h1>Edit Ability</h1>` + Custom badge
25. Fields pre-populated from server
26. Change any field → `● Unsaved changes` appears in title
27. Sticky bar note shows "● Unsaved changes — leaving this page will discard them."
28. "✓ Save Changes" → saves → dirty indicator clears
29. "Save as Draft" → forces `status=draft`
30. "🗑 Delete Ability" → confirm dialog → DELETE → redirects to list
31. Activity sidebar shows Updated + Created timestamps
32. Navigate away while dirty → `beforeunload` confirmation fires

### Override Inherited — Variant B
33. Click Edit or Override on inherited row → `<h1>Override Ability</h1>` + source badge
34. Locked card shows full slug, label, category, callback type — all non-editable
35. "🔒 Registered by plugin: X" in locked card header
36. Site Permission chips: Force Block / Inherit (plugin default) / Force Allow
37. MCP Type first option shows "inherit (X)" with plugin declared value
38. Annotation selects show "Plugin declares: X" hint
39. Access control checkboxes disabled/grayed at `opacity:.4` while "inherit" selected
40. "✓ Save Overrides" → saves override fields only
41. "↩ Clear All Overrides" → resets overrides to null

### Color scheme test
42. Admin → your Profile → Color Scheme → Ocean → back to Custom Abilities
43. Primary buttons, links, active chip, sorted column header should be teal/ocean, not blue
44. No `#007cba` hardcoded values visible (all via `var(--wp-admin-theme-color)`)

### Regression
45. Sitewide Abilities (Feature 001) still works — table loads, overrides save correctly
46. Override Processor (Feature 004) still injects abilities on `wp_abilities_api_init`

---

## What NOT to test here (future specs)
- Execution logger (Spec 006)
- Per-ability access control fine-grained rules (Spec 007)
- JS execute bridge for PHP-registered abilities (GitHub issue #9)
