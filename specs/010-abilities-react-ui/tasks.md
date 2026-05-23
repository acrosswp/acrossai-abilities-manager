# Tasks: Abilities React UI + Admin Shell

**Input**: Design documents from `specs/010-abilities-react-ui/`
**Prerequisites**: plan.md, spec.md, memory-synthesis.md, security-constraints.md
**Depends on**: Spec 008 (table), Spec 009 (REST endpoints — all must be deployed and responding)

**Organization**: Tasks are grouped by user story so each story can be implemented and validated
independently after the shared foundation is complete.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to
- Security task IDs (SEC-010-xx) appear inline at the task where the constraint is enforced

---

## Phase 1: Setup (Directory Structure + Build)

**Purpose**: Create all new directories and wire the webpack entries so the build produces
`build/js/abilities.js`, `build/css/abilities.css`, and `build/js/abilities.asset.php`.

- [x] T001 Create directory scaffolds: `admin/Partials/` (already exists), `src/js/abilities/api/`, `src/js/abilities/store/`, `src/js/abilities/components/cells/`, `src/scss/abilities/` — add `.gitkeep` to empty subdirs

- [x] T002 Add two new webpack entries to `webpack.config.js` in the `entry` object after the logger entries:
  ```js
  'js/abilities': path.resolve( process.cwd(), 'src/js/abilities', 'index.js' ),
  'css/abilities': path.resolve( process.cwd(), 'src/scss/abilities', 'admin.scss' ),
  ```
  Create stub entry files (`index.js` with a single comment, `admin.scss` empty) so the build does not error before components exist.

- [x] T003 Run `npm run build` and confirm `build/js/abilities.js`, `build/css/abilities.css`, and `build/js/abilities.asset.php` are generated (non-zero size). Fix any build errors before proceeding.

---

## Phase 2: Foundational — PHP Admin Shell

**Purpose**: Register the submenu, enqueue assets only on the correct page, and wire the hook.
**⚠️ CRITICAL**: The React app cannot mount until this phase is complete.

- [x] T004 Create `admin/Partials/AcrossAI_Abilities_Menu.php` — singleton following `LogsMenu.php` exactly:
  - Namespace: `AcrossAI_Abilities_Manager\Admin\Partials`
  - `ABSPATH` guard
  - `protected static $_instance = null` + `public static function instance(): self`
  - `private $hook_suffix = ''`
  - `register_submenu()`: calls `add_submenu_page('acrossai-abilities-manager', 'Custom Abilities', 'Custom Abilities', 'manage_options', 'acrossai-abilities-custom', [$this, 'render'])` and stores return value in `$this->hook_suffix`
  - `render()`: **SEC-010-01** — add `if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) ); }` before any output; then output `<div class="wrap"><div id="acrossai-abilities-root"></div></div>`
  - `get_hook_suffix(): string` — returns `$this->hook_suffix`

- [x] T005 Modify `admin/Main.php`:
  - Add `use AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu;` to imports
  - Add `private $abilities_asset_file;` property with `@var array|null` docblock
  - In constructor, after the logger guard: load `build/js/abilities.asset.php` with `file_exists()` guard (same pattern as `$logger_asset_file`)
  - In `enqueue_styles()`, after the logger block: conditionally enqueue `acrossai-abilities-manager-abilities` CSS only when `$this->abilities_asset_file && $this->is_abilities_custom_page($hook_suffix)`
  - In `enqueue_scripts()`, after the logger block: conditionally enqueue `acrossai-abilities-manager-abilities` JS + `wp_add_inline_script` with `window.acrossaiAbilitiesManager = { nonce: wp_create_nonce('wp_rest'), rest_url: untrailingslashit(rest_url()), rest_namespace: 'acrossai-abilities-manager/v1', current_user_id: get_current_user_id() }` only when `$this->abilities_asset_file && $this->is_abilities_custom_page($hook_suffix)`
  - Add `private function is_abilities_custom_page( string $hook_suffix ): bool` — uses `===` strict comparison: `return $hook_suffix === AcrossAI_Abilities_Menu::instance()->get_hook_suffix();` (**SEC-04**)

- [x] T006 Modify `includes/Main.php` — in `define_admin_hooks()` immediately after the `$logs_menu` block (~line 276), add:
  ```php
  // Custom Abilities submenu page (Feature 010).
  $abilities_menu = \AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu::instance();
  $this->loader->add_action( 'admin_menu', $abilities_menu, 'register_submenu' );
  ```
  **AC-HOOKS-MAIN**: variable-first singleton pattern — never inline `::instance()` inside `add_action`.

- [ ] T007 Verify PHP admin shell end-to-end:
  - "Custom Abilities" submenu appears under Abilities Manager
  - Page URL resolves to `?page=acrossai-abilities-custom`
  - `<div id="acrossai-abilities-root">` present in page source
  - `console.log(window.acrossaiAbilitiesManager)` logs `{nonce, rest_url, rest_namespace, current_user_id}`
  - Assets NOT loaded on Sitewide or Logs pages
  - PHPCS zero errors, PHPStan level 8 zero errors on all modified PHP files

**Checkpoint**: Admin shell complete. React app mount point is live. User story work can now begin.

---

## Phase 3: React Foundation (Shared Infrastructure — blocks all User Stories)

**Purpose**: Build the API client, Redux store, root component, and shared SCSS.
No user story component can be completed until this phase is done.

- [x] T008 [P] Create `src/js/abilities/api/client.js`:
  - Use `apiFetch` with `{ parse: false }` for `getAbilities` to access response headers (`X-WP-Total`, `X-WP-TotalPages`); clone body and parse JSON separately
  - `getAbilities(params)`, `getAbility(id)`, `createAbility(data)`, `updateAbility(id, data)`, `deleteAbility(id)`, `getCategories()`
  - Error handling: `response.clone().json()` pattern from `src/js/sitewide/api/client.js`
  - `BASE` derived from `window.acrossaiAbilitiesManager.rest_namespace + '/abilities'`

- [x] T009 [P] Create `src/js/abilities/store/index.js` — `@wordpress/data` store (`STORE_NAME = 'acrossai/abilities'`):
  - State shape: `{ abilities, total, pages, categories, isLoading, isSaving, error, saveError, view, savedAbility, draftAbility, isDirty }`
  - `view` shape: `'list' | { mode: 'create' } | { mode: 'edit', id } | { mode: 'override', id }`
  - `isDirty` computed: `JSON.stringify(draftAbility) !== JSON.stringify(savedAbility)`
  - Thunks: `fetchAbilities(params)`, `fetchAbility(id)`, `createAbility(data)`, `updateAbility(id, data)`, `deleteAbility(id)`, `bulkDeleteAbilities(ids)`, `bulkUpdateStatus(ids, status)`, `fetchCategories()`, `clearOverrides(id)`
  - Actions: `setView`, `setSavedAbility`, `updateDraft`, `clearDraft`, `setError`, `setSaveError`, `clearError`
  - On REST failure in fetch: `setError(message)` — leaves `abilities` untouched (FR-036)
  - On REST failure in save/delete: `setSaveError(message)` — `isSaving` reset to false, form stays open (FR-037)
  - Selectors: `getAbilities`, `getTotal`, `getPages`, `getCategories`, `getIsLoading`, `getIsSaving`, `getError`, `getSaveError`, `getView`, `getSavedAbility`, `getDraftAbility`, `getIsDirty`

- [x] T010 [P] Create `src/js/abilities/index.js`:
  - Import `apiFetch` + `createNonceMiddleware` from `@wordpress/api-fetch`
  - `apiFetch.use( apiFetch.createNonceMiddleware( window.acrossaiAbilitiesManager.nonce ) )`
  - Register store (import side-effect from `./store`)
  - `createRoot( document.getElementById('acrossai-abilities-root') ).render( <Provider store={store}><AbilitiesManager /></Provider> )` guarded by `if (root)`

- [x] T011 [P] Create `src/js/abilities/components/AbilitiesManager.jsx`:
  - Reads `view` from store with `useSelect`
  - Routes: `view === 'list'` → `<AbilitiesList />` | `view.mode === 'create'` → `<AbilityForm mode="create" />` | `view.mode === 'edit'` → `<AbilityForm mode="edit" id={view.id} />` | `view.mode === 'override'` → `<AbilityForm mode="override" id={view.id} />`
  - Registers `beforeunload` event listener when `isDirty === true`; removes listener when `isDirty === false`

- [x] T012 [P] Create `src/scss/abilities/admin.scss` — all design CSS classes:
  - **Color rule**: all interactive element colors use `var(--wp-admin-theme-color)` — **no hardcoded `#007cba`**
  - Source badge: `.src-c { background:#e0f0ff; color:var(--wp-admin-theme-color); }`, `.src-p { background:#f0e8ff; color:#5a00aa; }`, `.src-k { background:#e8f0e8; color:#1a5c1a; }`, `.src-t { background:#fff4e0; color:#7a4000; }`
  - Table: `.wptable`, `.inh-row td { background:#fafafa; }`, `.slug-dim { color:#646970; }`, `.slug-name { font-weight:600; }`
  - Chips: `.tc { border:1px solid #c3c4c7; background:#fff; color:#646970; }` `.tc.on { border-color:var(--wp-admin-theme-color); background:#e8f4fb; color:var(--wp-admin-theme-color); font-weight:600; }`
  - Config block: `.ecfg { background:#f6f7f7; border:1px solid #e0e0e0; border-radius:3px; padding:12px 14px; }` `.ecfg-lbl { font-size:10px; font-weight:700; text-transform:uppercase; color:var(--wp-admin-theme-color); }`
  - Dark code: `.dark-code { background:#1e1e1e; color:#a8ff60; border-color:#444; min-height:72px; font-family:monospace; }`
  - Prefix input: `.px-wrap { display:flex; border:1px solid #c3c4c7; border-radius:3px; overflow:hidden; }` `.px-txt { background:#f6f7f7; font-family:monospace; font-size:11px; color:#646970; padding:6px 8px; }` `.px-inp { font-family:monospace; font-size:13px; font-weight:600; border:none; }`
  - Unsaved: `.unsaved { font-size:12px; color:#996600; font-style:italic; }`
  - Sticky bar: `.sbar { position:sticky; bottom:0; background:#fff; border-top:1px solid #c3c4c7; padding:10px 16px; display:flex; justify-content:space-between; box-shadow:0 -2px 8px rgba(0,0,0,.07); }`
  - Locked card: `.locked { border-left:4px solid #c3c4c7; background:#fafafa; border:1px solid #c3c4c7; }` `.lhdr { background:#f6f7f7; padding:10px 16px; font-size:13px; font-weight:600; color:#646970; }` `.lgrid { display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; padding:12px 16px; }`
  - Form layout: `.form-layout { display:grid; grid-template-columns:1fr 236px; gap:16px; }` `.postbox { border:1px solid #c3c4c7; background:#fff; margin-bottom:16px; }` `.pbhdr { padding:10px 16px 8px; border-bottom:1px solid #c3c4c7; }` `.fr { display:grid; grid-template-columns:180px 1fr; gap:8px; align-items:start; padding:8px 16px; }`
  - Sidebar: `.sidebox { border:1px solid #c3c4c7; background:#fff; margin-bottom:12px; }` `.sbhdr { font-size:10.5px; font-weight:700; text-transform:uppercase; padding:8px 12px; border-bottom:1px solid #c3c4c7; }`
  - Category pill: `.cpill { background:#e0e0e0; border-radius:10px; font-size:11px; padding:2px 8px; }`
  - PHP warning: `.acrossai-php-warning { color:#996600; background:#fffbe6; border:1px solid #ffe58f; border-radius:3px; padding:6px 10px; margin-bottom:8px; }`

**Checkpoint**: API client, store, root, and styles complete. User story components can now be built.

---

## Phase 4: User Story 1 — Browse and Manage Abilities (P1) 🎯 MVP

**Goal**: Full-page list of all abilities with filtering, search, source badges, status indicators,
row actions (Edit / Delete / Override), bulk actions, and server-side pagination.

**Independent Test**: Navigate to Custom Abilities; table renders with all 9 columns; source badges
display correctly; search and filter work; inline status dropdown publishes/drafts a custom row.

- [x] T013 [P] [US1] Create `src/js/abilities/components/cells/SourceBadge.jsx`:
  - Maps `source` → CSS class and label: `{ db:'src-c'/'Custom', plugin:'src-p'/'Plugin', core:'src-k'/'Core', theme:'src-t'/'Theme' }`
  - Returns `<span className={`src ${cls}`}>{label}</span>`

- [x] T014 [US1] Create `src/js/abilities/components/AbilitiesList.jsx` — **Constitution §III: uses `DataViews`**:
  - Import `DataViews` from `@wordpress/dataviews`
  - `fields` array: `ability_slug` (strip `acrossai-abilities/` prefix; custom render splits `.slug-dim` prefix + `.slug-name` suffix), `label` (+ `.lbl-by "by {provider}"` for inherited rows), `category` (`.cpill` pill render), `source` (`SourceBadge` render; `elements` for filter), `status` (`.sta-on/.sta-off` dot+text for db rows; `.ibadge .ib-a/.ib-d/.ib-b` for inherited), `callback_type` (`.tbadge` monospace pill), `show_in_mcp` (`.mcp-y`/`.mcp-n`), `updated_at` (formatted date)
  - `actions` prop: `edit` (all rows → `setView({mode:'edit', id})`), `override` (inherited only → `setView({mode:'override', id})`), `status-toggle` (db only — inline `<select>` Published/Draft calling `updateAbility` immediately, FR-012), `delete` (db only — single `window.confirm()` then `deleteAbility(id)`)
  - DataViews `DEFAULT_VIEW`: `{ type:'table', perPage:20, page:1, sort:{field:'ability_slug', direction:'asc'}, filters:[], search:'' }`; persist layout prefs to `localStorage` (same pattern as `AbilityTable.jsx`)
  - `paginationInfo` fed from store `total` / `pages` (populated from `X-WP-Total` / `X-WP-TotalPages`)
  - Inherited rows: DataViews `className` field callback returns `'inh-row'` for `source !== 'db'`
  - **Tablenav** rendered above DataViews as standard WP HTML controls (not DataViews fields):
    - Subsubsub quick-links: All / Published / Draft with counts from REST filter calls
    - Bulk Actions `<select>` (Publish / Unpublish / Delete) + Apply button
    - **SEC-010-02**: `handleBulkApply()` MUST call `window.confirm("Delete N abilities? This cannot be undone.")` before dispatching bulk delete; no confirm needed for Publish/Unpublish
    - Source filter `<select>` (All Sources / Custom / Plugin / Core / Theme)
    - Status filter `<select>` (All Statuses / Published / Draft)
    - Search `<input>` (debounced 500 ms, same pattern as `AbilityTable.jsx`)
    - Item count display (right-aligned)
  - On REST list error: show dismissible WP `<Notice>` above the table; `abilities` state unchanged (FR-036)

**Checkpoint**: US1 fully functional. Administrators can browse, filter, search, and perform row/bulk actions.

---

## Phase 5: User Story 2 — Create a New Custom Ability (P1) 🎯 MVP

**Goal**: Full-page Add New form with slug prefix input, callback type chips, dynamic config block,
schema fields, MCP exposure, annotations, access control, and sidebar. Saves via POST and redirects
to Edit screen on success.

**Independent Test**: Click "Add New Ability", fill slug suffix + label + category, click "✓ Add Ability",
verify new row appears in list with `acrossai-abilities/<suffix>` slug and `status=publish`.

- [x] T015 [US2] Create `src/js/abilities/components/CallbackConfigField.jsx` — custom `Edit:` field renderer for `callback_config` in DataForm:
  - Receives `{ data, field, onChange }` from DataForm
  - Renders per `data.callback_type`:
    - `noop`: info text in `.ecfg` ("No code runs...")
    - `filter_hook`: `hook_name` `TextControl` in `.ecfg`
    - `wp_remote_post`: URL `TextControl` (full width) + inline row: Method `<select>` GET/POST (90px) + Timeout `NumberControl` 1–30 (80px)
    - `php_code`: **SEC-010-03** — render `.acrossai-php-warning` `<p>` with `⚠` icon + text "PHP code runs server-side with plugin-level access. Variable `$input` contains the ability input. Blocked: eval, exec, system, shell_exec, file_put_contents, unlink." ABOVE the `<textarea className="rt lt dark-code">` with `background:#1e1e1e; color:#a8ff60; min-height:72px`

- [x] T016 [US2] Create `src/js/abilities/components/AbilityForm.jsx` — **Constitution §III: uses `DataForm`**:

  **Variant A — create mode** (this task):
  - Import `DataForm` from `@wordpress/dataviews`; reuse `ts2s`/`s2ts`/`TriStateEditField` from `src/js/sitewide/components/AbilityEditPanel.jsx`; reuse `McpVisibilityControl` (DEV1 — no DataForm wrapping)
  - On mount with `mode='create'`: dispatch `clearDraft()`; fetch categories if not loaded
  - `identityFields` DataForm:
    - `ability_slug_suffix` — custom `Edit:` renders `.px-wrap` with read-only `.px-txt acrossai-abilities/` prefix + `.px-inp` editable suffix
    - `label` — `TextControl`
    - `category` — custom `Edit:` renders `<select>` populated from store `categories`; shows "— choose —" placeholder when empty (edge case)
    - `description` — `TextareaControl`
    - `status` — custom `Edit:` renders `.wptog` toggle; ON=`publish`, OFF=`draft` (FR-019)
  - `callbackFields` DataForm:
    - `callback_type` — custom `Edit:` renders `.tchips` four-chip selector; `noop` chip `.on` by default (FR-014)
    - `callback_config` — custom `Edit:` renders `<CallbackConfigField>` (T015)
  - `schemaFields` DataForm (optional section):
    - `input_schema` — custom `Edit:` renders `.code-lt` textarea; `JSON.parse` on blur, show inline error if invalid (FR-016); store as raw string
    - `output_schema` — same pattern
  - MCP Exposure section: reuse `McpVisibilityControl` component as-is (DEV1); add `show_in_rest` `TriStateEditField`
  - Annotations section: `readonly`, `destructive`, `idempotent` — each reuses `TriStateEditField`
  - Access Control section: `who_can_access` `<select>` (WordPress Role / Specific Users / Everyone / No one) + role checkboxes for Administrator/Editor/Author/Contributor/Subscriber (UI-only, FR-031)
  - **Sidebar** (sibling JSX, not DataForm fields):
    - Publish box: "✓ Add Ability" primary button + "Save as Draft" secondary button
    - Preview box: slug preview (italic/muted until filled) + Source Custom badge + Callback type badge
    - "What Happens on Save" box: bulleted explanation
  - **Sticky bar**: Left = "Fill in required fields (*) before saving." / Right = Cancel + "✓ Add Ability" primary
  - **Save flows**:
    - "✓ Add Ability" → `createAbility({ ...draft, ability_slug: 'acrossai-abilities/' + draft.ability_slug_suffix })` → on 201 → `setView({ mode:'edit', id: response.id })`
    - "Save as Draft" → same but force `status: 'draft'` regardless of toggle state (FR-018)
    - On error: show dismissible `<Notice>` above form; save button re-enabled (FR-037)

**Checkpoint**: US2 fully functional. Administrators can create a new custom ability and land on the Edit screen.

---

## Phase 6: User Story 3 — Edit an Existing Custom Ability (P2)

**Goal**: Edit form pre-populated from server state, dirty tracking, unsaved-changes indicator in
page title, sticky bar note when dirty, sparse save (changed fields only), `beforeunload` guard, delete.

**Independent Test**: Edit an ability, change the label, verify "● Unsaved changes" appears, click
Save Changes, confirm label updated in list and admin stays on edit form.

- [x] T017 [US3] Extend `AbilityForm.jsx` with edit mode:
  - On mount with `mode='edit'`: dispatch `fetchAbility(id)` → sets `savedAbility` + `draftAbility` from server response
  - `isDirty` selector drives:
    - `<span className="unsaved">● Unsaved changes</span>` in page title row (FR-020)
    - Sticky bar note: "● Unsaved changes — leaving this page will discard them." (only when dirty, FR-021)
    - `beforeunload` listener (wired in `AbilitiesManager.jsx`, T011)
  - All form sections pre-populated from `draftAbility` (mirrors create mode fields)
  - Breadcrumb changes to: `All Abilities › {full_slug}` (monospace 11px) with slug warning note "⚠ Changing the slug will break existing integrations."
  - **Sidebar** (edit mode boxes):
    - Update box: "✓ Save Changes" primary + "Save as Draft" secondary + dashed divider + "🗑 Delete Ability" link-delete (red)
    - Preview box: Slug / Label / Source badge / Callback badge / MCP type
    - "What Happens on Save" box
    - Activity box: two timeline dots — Updated (green dot + `updated_at` formatted) / Created (empty circle + `created_at` formatted) (FR-023)
  - **Sticky bar** (edit mode): Left = `"● Unsaved changes — leaving this page will discard them."` (italic, muted, shown only when dirty) / Right = Cancel + "✓ Save Changes" primary

- [x] T018 [US3] Wire save/delete flows in `AbilityForm.jsx` edit mode:
  - "✓ Save Changes" → compute `changedFields` (diff `draftAbility` vs `savedAbility`; only include keys where values differ) → `updateAbility(id, changedFields)` → on success → `setSavedAbility(response)` + `clearDirty` + admin stays on edit (FR-022)
  - "Save as Draft" → same diff but force `status: 'draft'`
  - "🗑 Delete Ability" → `window.confirm("Delete this ability? This cannot be undone.")` → `deleteAbility(id)` → on 204 → `setView('list')` (FR-024)
  - On save/delete error: dismissible `<Notice>` below breadcrumb; save button re-enabled; edits preserved (FR-037)

**Checkpoint**: US3 fully functional. Administrators can edit, save sparse diffs, and delete custom abilities.

---

## Phase 7: User Story 4 — Override an Inherited Ability (P2)

**Goal**: Override form for inherited (source=plugin/theme/core) abilities. Locked identity card.
Editable override sections only. Sparse save (override fields only). Clear All Overrides.

**Independent Test**: Click Edit/Override on an inherited ability, change Site Permission to "Force Block",
save, verify `site_allowed=0` in the database and the list shows the correct ibadge.

- [x] T019 [US4] Extend `AbilityForm.jsx` with override mode (Variant B):
  - On mount with `mode='override'`: dispatch `fetchAbility(id)` → `savedAbility` + `draftAbility` initialized from override fields only (`site_allowed`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `readonly`, `destructive`, `idempotent`)
  - **Locked card** (plain HTML above DataForm — not a DataForm field):
    ```jsx
    <div className="locked">
        <div className="lhdr">🔒 Registered by plugin: <strong>{ability.provider}</strong> <SourceBadge source={ability.source} /></div>
        <div className="lgrid">Full Slug / Label / Category / Callback Type (all read-only)</div>
        <p className="description">{ability.description}</p>
    </div>
    ```
  - Breadcrumb: `All Abilities › {full_slug}` + source badge
  - Subtitle: "Identity defined by **{provider}** — read-only. You can only override site permission, MCP exposure, and annotations."
  - **Override DataForm fields**:
    - `site_allowed` — custom `Edit:` renders `.tchips` three chips: Force Block (0) / **Inherit (plugin default)** (null, selected by default) / Force Allow (1) (FR-028)
    - MCP Exposure: reuse `McpVisibilityControl` (DEV1) + `show_in_rest` TriState with "(plugin default: X)" hint
    - `mcp_type` — `<select>` with `"inherit (X)"` as first option where X = `savedAbility._registry.mcp_type` (FR-029)
    - `readonly`, `destructive`, `idempotent` — `TriStateEditField` with "Plugin declares: X" hint text below each (FR-030)
    - Access Control Override: `who_can_access` `<select>` with "inherit (WordPress Role)" as first option; role checkboxes `disabled` + `opacity:0.4` while "inherit" is selected with desc "Inheriting from plugin — overrides disabled." (FR-031)
  - **Sidebar** (override mode boxes):
    - Actions box: "✓ Save Overrides" primary + "↩ Clear All Overrides" link
    - Preview box: Slug / Label / Source badge / Permission ibadge
    - "What Happens on Save" box (override-specific bullets)
    - Active Overrides box: "No overrides set — all values inherited from plugin." (italic/muted) OR list of currently active override fields
  - **Sticky bar**: Left = "Changes affect this site only — the plugin definition is not modified." / Right = Cancel + "✓ Save Overrides"

- [x] T020 [US4] Wire save and clear flows for override mode:
  - "✓ Save Overrides" → `updateAbility(id, overrideFieldsOnly)` — **SC-007**: NEVER include `ability_slug`, `label`, `category`, `description`, `callback_type`, `callback_config`, `input_schema`, `output_schema` in the payload
  - "↩ Clear All Overrides" → dispatch `clearOverrides(id)` thunk → send `{ site_allowed:null, show_in_rest:null, show_in_mcp:null, mcp_type:null, mcp_servers:null, readonly:null, destructive:null, idempotent:null }` → on success → `setSavedAbility(response)` + `clearDirty` (FR-032)
  - On save error: dismissible `<Notice>` above form; save button re-enabled; edits preserved (FR-037)

**Checkpoint**: US4 fully functional. All four user stories are independently working.

---

## Phase 8: Security Hardening + Error Handling

**Purpose**: Ensure all security requirements from `security-constraints.md` are verified and all
FR-035–037 error paths are complete across every component.

- [ ] T021 [P] Verify SEC-010-01 in `AcrossAI_Abilities_Menu::render()`: confirm `current_user_can('manage_options')` guard and `wp_die()` are present. Add PHPCS annotation `// phpcs:ignore` if needed for `wp_die` usage. Run PHPStan level 8 — zero errors.

- [ ] T022 [P] Verify SEC-010-02 in `AbilitiesList.jsx`: confirm `window.confirm()` appears in `handleBulkApply` before `bulkDeleteAbilities` is dispatched. Manually test: select multiple rows → Bulk Actions → Delete → Apply → confirm dialog appears; clicking Cancel aborts the operation.

- [ ] T023 [P] Verify SEC-010-03 in `CallbackConfigField.jsx`: confirm `.acrossai-php-warning` `<p>` renders above the dark textarea when `callback_type === 'php_code'` and does NOT render for any other callback type.

- [ ] T024 [P] Full error-handling audit across all components (FR-035–037):
  - `AbilitiesList`: simulate network failure on `fetchAbilities` → dismissible `<Notice>` appears above table; table rows remain from last successful load; dismiss button removes notice
  - `AbilityForm` create: simulate 500 on `createAbility` → dismissible `<Notice>` appears; "✓ Add Ability" button re-enabled; draft values preserved
  - `AbilityForm` edit: simulate 500 on `updateAbility` → dismissible `<Notice>` appears; "✓ Save Changes" re-enabled; `isDirty` remains true
  - `AbilityForm` delete: simulate 500 on `deleteAbility` → dismissible `<Notice>` appears; user stays on edit form
  - `AbilityForm` override: simulate 500 on save → same `<Notice>` pattern; "✓ Save Overrides" re-enabled

---

## Phase 9: Polish, Color Scheme + Regression

**Purpose**: Final verification of WordPress Admin Color Scheme compliance, regression of all pre-existing pages, and build validation.

- [ ] T025 [P] Color scheme audit — `admin.scss` and all JSX inline styles:
  - Grep for hardcoded `#007cba` across all new files — must return zero results
  - Switch WP admin color scheme to Ocean (`Admin → Profile → Color Scheme → Ocean`) → verify all primary buttons, active chip borders/backgrounds, links, focus rings update to teal
  - Repeat with Midnight and Sunrise schemes

- [ ] T026 [P] Regression — sitewide page: navigate to `?page=acrossai-abilities-manager` → table renders; override saves work; no console errors

- [ ] T027 [P] Regression — logs page: navigate to `?page=acrossai-abilities-logs` → log table renders; no console errors; abilities assets NOT loaded

- [x] T028 Final build + ESLint:
  - `npm run build` → all four abilities artifacts generated (non-zero) ✅
  - `npm run lint:js` (or equivalent) → zero substantive ESLint errors on all new JS/JSX files ✅ (remaining: systemic import/jsdoc errors identical to sitewide components)
  - `npm run validate-packages` → passes (no duplicate React/ReactDOM) ✅
  - PHPCS strict profile zero new errors on `AcrossAI_Abilities_Menu.php` and `admin/Main.php` changes ✅ (pre-existing filename errors same as LogsMenu.php)
  - PHPStan level 8 zero errors on all modified PHP files ✅

---

## Dependencies & Execution Order

### Phase Dependencies

| Phase | Depends on | Can parallelize within phase? |
|---|---|---|
| Phase 1 (Setup) | Nothing | T001, T002 in parallel; T003 after T002 |
| Phase 2 (PHP Shell) | Phase 1 | T004, T005 in parallel; T006 after T004+T005; T007 after T006 |
| Phase 3 (React Foundation) | Phase 2 (T007 verified) | T008–T012 all in parallel |
| Phase 4 (US1) | Phase 3 complete | T013 in parallel with Phase 3; T014 after T013 |
| Phase 5 (US2) | Phase 3 complete | T015 in parallel with T014; T016 after T015 |
| Phase 6 (US3) | Phase 5 (AbilityForm exists) | T017 then T018 sequentially |
| Phase 7 (US4) | Phase 5 (AbilityForm exists) | T019 then T020 sequentially |
| Phase 8 (Security) | Phases 4–7 complete | T021–T024 all in parallel |
| Phase 9 (Polish) | Phase 8 complete | T025–T027 in parallel; T028 after all |

### User Story Independence

- **US1 (Phase 4)**: Independently testable after Phase 3; no dependency on US2–4
- **US2 (Phase 5)**: Independently testable after Phase 3; no dependency on US3–4
- **US3 (Phase 6)**: Depends on US2 (AbilityForm.jsx must exist); adds edit mode on top
- **US4 (Phase 7)**: Depends on US2 (AbilityForm.jsx must exist); adds override mode on top

US3 and US4 can be developed in parallel (different form modes, no shared state mutations).

---

## SEC-010 Implementation Checklist (from security-constraints.md)

| ID | Enforced in task | Status |
|---|---|---|
| SEC-010-01 `render()` capability check | T004 (implementation) + T021 (verification) | ✅ Implemented |
| SEC-010-02 Bulk delete confirmation | T014 (implementation) + T022 (verification) | ✅ Implemented |
| SEC-010-03 `php_code` execution warning | T015 (implementation) + T023 (verification) | ✅ Implemented |
| SEC-010-04 Spec 009 full-row post-save hook | Spec 009 T010 (already implemented per tasks.md) | ✅ Done |
