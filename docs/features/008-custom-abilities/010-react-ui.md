# Spec 010 — Abilities React UI + Admin Shell

**Branch**: `010-abilities-react-ui`
**Depends on**: Spec 009 (REST endpoints must exist and return data)
**Blocks**: nothing (final spec in 008-010 series)

> **UI design**: Finalize the React component design in Claude Artifacts / Claude Design before implementing this spec.
> Reference existing pattern: `src/js/sitewide/` for `@wordpress/dataviews` + REST client patterns.

---

## What this spec does

- `AcrossAI_Abilities_Menu` — registers "Custom Abilities" submenu under Abilities Manager
- `AcrossAI_Abilities_Page` — renders the React mount point `<div id="acrossai-abilities-root"></div>`
- `AcrossAI_Abilities_Assets` — enqueues the built JS bundle + passes `window.acrossaiAbilities` via `wp_add_inline_script()`
- React app under `src/js/abilities/`:
  - `AbilitiesList` — `@wordpress/dataviews` table with search, source filter, status filter, bulk actions
  - `AbilityForm` — `@wordpress/dataforms` form with Variant A (source=db, all fields editable) and Variant B (source≠db, identity fields read-only)
  - `php_code` callback type renders a monospace `<textarea>` for the code body
  - Category field is a `<select>` populated from `GET /ability-categories`
  - **Draft/Publish workflow**: new abilities auto-save as draft; explicit Publish button transitions to `status=publish`
  - **No save button for edits**: every field change on an existing ability saves instantly
- `webpack.config.js` — new entry `'js/abilities': 'src/js/abilities/index.js'`
- Wires `admin_menu` hook in `includes/Main.php`

---

## Draft / Publish Workflow

| State | What it means | UI |
|---|---|---|
| `status=draft` | Ability saved but not live | "Draft" badge in list; Publish button in form |
| `status=publish` | Ability is live — registered at boot | "Published" badge; no Publish button |

### New ability flow
1. User clicks "Add New Ability"
2. Form opens in "new ability" mode — all fields blank
3. User types in any field → `POST /abilities` on first change (creates row with `status=draft`)
4. Subsequent changes → `POST /abilities/{id}` with only the changed field (sparse update)
5. User clicks **Publish** → `POST /abilities/{id}` with `{"status": "publish"}` → ability goes live

### Existing ability editing (no save button)
- Any toggle, select change, or text field blur → `POST /abilities/{id}` with only the changed field
- No confirmation dialog; changes are instant
- No risk of data loss from untouched fields — BerlinDB partial update writes only passed columns

---

## Variant A vs Variant B Forms

### Variant A — source=db (Custom ability)
All fields editable:
- Slug: `acrossai-abilities/` prefix read-only + editable suffix input
- Label, Description, Category (dropdown from REST)
- Status badge + Publish button (if draft)
- Callback Type select: `noop` | `filter_hook` | `wp_remote_post` | `php_code`
- Callback Config: JSON field (or monospace `<textarea>` for `php_code`)
- Input Schema / Output Schema
- show_in_rest, show_in_mcp, mcp_type, mcp_servers
- readonly, destructive, idempotent (tri-state selects)

### Variant B — source=plugin/theme/core (Override row)
Identity section (read-only info panel — not editable inputs):
- Slug, Label, Description, Category, Callback Type, Callback Config

Override fields (fully editable, instant save):
- site_allowed toggle
- show_in_rest, show_in_mcp, mcp_type, mcp_servers
- readonly, destructive, idempotent (tri-state selects)

---

## `AbilitiesList` columns and filters

| Column | Notes |
|---|---|
| Slug | Strip `acrossai-abilities/` prefix for display |
| Label | — |
| Category | — |
| Source | Badge: **Custom** / **Plugin** / **Theme** / **Core** |
| Status | Badge: **Draft** / **Published** (source=db only) |
| Callback Type | — |
| MCP Type | — |
| Actions | Edit / Delete |

**Filters**: search (slug/label), source, status (Draft / Published)
**Bulk actions**: Publish, Unpublish, Delete

---

## Files to Create

### Admin PHP shell
```
admin/Partials/
├── AcrossAI_Abilities_Menu.php    — singleton, add_submenu_page under acrossai-abilities-manager
├── AcrossAI_Abilities_Page.php    — renders <div id="acrossai-abilities-root"></div>
└── AcrossAI_Abilities_Assets.php  — wp_enqueue_script('acrossai-abilities', build/js/abilities.js)
                                      wp_add_inline_script():
                                        window.acrossaiAbilities = { restNamespace, nonce, currentUserId }
```

### React UI
```
src/js/abilities/
├── index.js                           — mounts root into #acrossai-abilities-root
├── api/
│   └── useAbilities.js                — fetchList(params), fetchOne(id), create(data),
│                                        update(id, fields), remove(id), fetchCategories()
└── components/
    ├── AbilitiesManager.jsx           — root: toggles list ↔ form
    ├── AbilitiesList.jsx              — @wordpress/dataviews table (columns + filters + bulk)
    └── AbilityForm.jsx                — @wordpress/dataforms
                                         Variant A: all fields editable + Publish button
                                         Variant B: identity read-only panel + override fields
                                         php_code: monospace <textarea>

src/scss/abilities/
└── admin.scss
```

## Files to Modify (3)

| File | Change |
|---|---|
| `webpack.config.js` | Add entry: `'js/abilities': 'src/js/abilities/index.js'` |
| `includes/Main.php` | Add `admin_menu` hook → `AcrossAI_Abilities_Menu::instance()` |
| `admin/Main.php` | Add `is_abilities_page()` helper + conditional enqueue for `acrossai-abilities` page |

---

## Reuse from existing code

| Existing file | Reuse |
|---|---|
| `src/js/sitewide/` | `@wordpress/dataviews` + Redux store + REST client pattern reference |
| `src/js/sitewide/components/AbilityEditPanel.jsx` | Override fields UI pattern reference |
| `admin/Partials/LogsMenu.php` | Admin submenu singleton pattern reference |
| `admin/Partials/LogsAssets.php` | Asset enqueue + `wp_add_inline_script()` pattern reference |

---

## Speckit commands — run in order

### Step 1 — Create feature branch
```
/speckit.git.feature
```
When prompted, enter: `010-abilities-react-ui`

---

### Step 2 — Write the spec
```
/speckit.specify Abilities React UI and admin shell: create admin PHP shell (AcrossAI_Abilities_Menu singleton adds submenu under acrossai-abilities-manager, AcrossAI_Abilities_Page renders div#acrossai-abilities-root, AcrossAI_Abilities_Assets enqueues build/js/abilities.js and passes window.acrossaiAbilities={restNamespace,nonce,currentUserId} via wp_add_inline_script). React app in src/js/abilities/: index.js mounts root; api/useAbilities.js hooks for fetchList(params), fetchOne(id), create(data), update(id,fields) sparse update, remove(id), fetchCategories(); components/AbilitiesManager.jsx toggles between list and form views; components/AbilitiesList.jsx uses @wordpress/dataviews table with columns slug(strip acrossai-abilities/ prefix for display), label, category, source badge(Custom/Plugin/Theme/Core), status badge(Draft/Published for source=db only), callback_type, mcp_type, actions(edit/delete), filters(search, source, status), bulk actions(publish, unpublish, delete — no enable/disable, there is no enabled column); components/AbilityForm.jsx uses @wordpress/dataforms: Variant A (source=db, all fields editable: slug prefix read-only+suffix editable, label, description, category dropdown from REST, status badge+Publish button if draft, callback_type select, callback_config JSON field or monospace textarea for php_code, input_schema/output_schema, show_in_rest/show_in_mcp/mcp_type/mcp_servers, readonly/destructive/idempotent tri-state — NO enabled toggle, publish=always live) and Variant B (source!=db, identity fields label/description/category/callback as read-only info panel, override fields site_allowed/show_in_rest/show_in_mcp/mcp_type/mcp_servers/readonly/destructive/idempotent fully editable); no save button for existing abilities (every field change triggers instant POST /abilities/{id} sparse update); Publish button only for new draft abilities (POST /abilities/{id} with {status:publish}); src/scss/abilities/admin.scss for component styles; webpack entry js/abilities pointing to src/js/abilities/index.js; admin_menu hook in includes/Main.php; admin/Main.php gets is_abilities_page() helper + conditional enqueue.
```

---

### Step 3 — Generate plan with memory context
```
/speckit.memory-md.plan-with-memory
```

---

### Step 4 — Architecture + security validation
```
/speckit.architecture-guard.governed-plan
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

### Step 7 — Quality gates (run after implement)
```bash
# Build JS bundle
npm run build

# Confirm build output exists
ls -la build/js/abilities.js
ls -la build/js/abilities.asset.php

# PHP syntax check all new files
php -l admin/Partials/AcrossAI_Abilities_Menu.php
php -l admin/Partials/AcrossAI_Abilities_Page.php
php -l admin/Partials/AcrossAI_Abilities_Assets.php
php -l includes/Main.php
php -l admin/Main.php

composer run phpcs
composer run phpstan
npm run lint:js
```

---

### Step 8 — Post-implement review
```
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
```

---

### Step 9 — Save memory + commit
```
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual testing

### Setup: ensure spec 009 test abilities exist
```bash
wp db query "SELECT ability_slug, label, status, source FROM wp_acrossai_abilities LIMIT 5;"
# Expected: at least one source=db row; ideally one draft and one publish
# If empty, insert:
wp db query "
INSERT INTO wp_acrossai_abilities
  (ability_slug, label, description, category, status, source, callback_type, show_in_rest)
VALUES
  ('acrossai-abilities/ui-test-pub', 'UI Test Published', 'Published ability', 'site', 'publish', 'db', 'noop', 1),
  ('acrossai-abilities/ui-test-draft', 'UI Test Draft', 'Draft ability', 'site', 'draft', 'db', 'noop', 1);
"
```

### 1. Confirm webpack output
```bash
ls -la build/js/abilities.js
ls -la build/js/abilities.asset.php
# Expected: both files exist and abilities.js is non-zero size
```

### 2. Confirm admin submenu appears
- Go to WP Admin → Abilities Manager
- Expected: "Custom Abilities" submenu item visible

### 3. Confirm React app mounts
- Click "Custom Abilities" submenu
- Open browser console — Expected: no JS errors
- Expected: `<div id="acrossai-abilities-root">` contains rendered React content

### 4. Confirm abilities list loads
- Expected: table renders with ability rows
- Expected: columns visible: Slug, Label, Category, Source, Status, Callback Type, MCP Type, Actions
- Expected: source=db rows show "Custom" badge; source=plugin shows "Plugin" badge
- Expected: `status=publish` rows show "Published" badge; `status=draft` rows show "Draft" badge

### 5. Search filter
- Type in the search box
- Expected: list filters by slug/label

### 6. Source filter
- Select "Custom" from the source filter dropdown
- Expected: only source=db rows visible

### 7. Add New Ability — starts as draft
- Click "Add New Ability"
- Expected: form opens in Variant A mode (all fields editable)
- Type a suffix (e.g. `form-test`) in the slug suffix field
- Expected: `POST /abilities` fires immediately, row created with `status=draft`
- Fill in Label → Expected: `POST /abilities/{id}` fires with only `{label: "..."}` (sparse)
- Expected: no save button visible — every change auto-saves

### 9. Publish button
- Expected: "Publish" button visible when `status=draft`
- Click Publish → Expected: `POST /abilities/{id}` with `{status: "publish"}`
- Expected: badge changes from "Draft" to "Published", Publish button disappears

### 10. Category dropdown populated
- In Add New Ability form: Expected category `<select>` contains entries from `GET /ability-categories`
- Expected: not empty

### 11. php_code callback type
- In a new or existing source=db ability, select Callback Type → `php_code`
- Expected: monospace `<textarea>` appears labeled "PHP code (no opening tag). Variable `$input` contains the ability input."
- Type code and change focus → Expected: sparse update fires immediately

### 12. Edit ability — Variant A (source=db)
- Click Edit on a source=db row
- Expected: all fields are editable (no enabled toggle — `status=publish` means always live)
- Change any field → Expected: saves instantly, no save button

### 13. Edit ability — Variant B (source≠db override row)
- If a source=plugin override row exists, click Edit
- Expected: label, description, category, callback_type rendered as read-only info panel
- Expected: site_allowed, show_in_rest, show_in_mcp, mcp_type, mcp_servers, readonly, destructive, idempotent are editable
- Change an override field → Expected: saves instantly

### 14. Slug prefix display
- In the form, slug input shows `acrossai-abilities/` read-only prefix + editable suffix
- Expected: user cannot edit the prefix portion
- Expected: stored slug has full `acrossai-abilities/<suffix>` format

### 15. Delete ability
- Click Delete on `acrossai-abilities/form-test` → confirm dialog
- Expected: row disappears, REST DELETE returns 204

### 16. Bulk actions
- Select multiple source=db rows
- Expected: bulk action toolbar appears with Publish, Unpublish, Delete
- Select "Unpublish" → Expected: all selected rows change to `status=draft` / "Draft" badge

### 17. window.acrossaiAbilities data
```js
// In browser console on Custom Abilities page:
console.log(window.acrossaiAbilities);
// Expected: { restNamespace: 'acrossai-abilities-manager/v1', nonce: '<string>', currentUserId: <number> }
```

### 18. Feature 001 Sitewide Abilities page still works
- Go to WP Admin → Abilities Manager → Sitewide Abilities
- Expected: loads without errors, list renders, overrides save correctly

### 19. Feature 001 Override Processor still works
```bash
wp eval "
do_action('wp_abilities_api_init');
\$ability = wp_get_ability('core/get-site-info');
echo \$ability ? 'STILL WORKING' : 'BROKEN';
"
# Expected: STILL WORKING
```

---

## What NOT to test (future specs)
- Execution logger (Spec 006) — not yet started
- Access control per-ability rules on custom abilities (Spec 003 extension)
