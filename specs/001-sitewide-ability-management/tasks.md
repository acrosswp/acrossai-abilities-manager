---
description: "Implementation tasks for Sitewide Ability Management"
---

# Tasks: Sitewide Ability Management

**Input**: Design documents from `specs/001-sitewide-ability-management/`
**Prerequisites**: plan.md ✅, spec.md ✅, data-model.md ✅, contracts/rest-api.md ✅, research.md ✅, quickstart.md ✅

**Branch**: `001-sitewide-ability-management`
**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: User story this task belongs to (US1–US5)
- All file paths are relative to the plugin root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: PHP and JS dependency setup, directory scaffolding, and build configuration

- [ ] T001 Add `"berlindb/core": "^2.0"` to `composer.json` require; add Mozart config with `dep_namespace: "AcrossAI_Abilities_Manager\\Vendor\\"` to prefix BerlinDB to `AcrossAI_Abilities_Manager\Vendor\BerlinDB\`; run `composer require berlindb/core:^2.0` and `composer exec mozart compose` and `composer dump-autoload`
- [ ] T002 [P] Update `webpack.config.js` to spread `defaultConfig.entry` and add entries `'sitewide' → './src/js/sitewide/index.js'` and `'sitewide-style' → './src/scss/sitewide/admin.scss'`
- [ ] T003 [P] Create empty-file directory scaffolding for all new directories: `includes/Base/`, `includes/Utilities/`, `includes/Modules/Sitewide/Database/`, `src/js/sitewide/api/`, `src/js/sitewide/store/`, `src/js/sitewide/components/`, `src/scss/sitewide/`, `tests/phpunit/sitewide/`, `tests/jest/sitewide/`

**Checkpoint**: Composer and npm dependencies resolved; webpack configured; directory structure ready

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core PHP infrastructure and JS skeleton that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [ ] T004 Create `includes/Base/AcrossAI_Module_Base.php` — abstract class `AcrossAI_Module_Base` with `abstract public function register_hooks( $loader ): void` and `abstract public function get_name(): string`; PHP 7.4 compatible; full `acrossai_` prefix; WPCS-compliant docblock
- [ ] T005 [P] Create `includes/Utilities/AcrossAI_Sanitizer.php` — static class with `sanitize_ability_slug( string $slug ): string` (sanitize_text_field + validate against registered slugs), `sanitize_tri_state( $value ): ?bool` (accepts true/false/null, rejects all else), `sanitize_mcp_type( $value ): ?string` (allows 'tool'|'resource'|'prompt'|null), `sanitize_mcp_servers_array( $value ): ?array` (validate array of non-empty strings or null), `cast_tri_state( $value ): ?bool` (casts tinyint DB values: 1→true, 0→false, null→null — shared casting utility for ALL BerlinDB Row classes; must NOT be duplicated on individual Row classes — RF-02)
- [ ] T006 [P] Create `includes/Utilities/AcrossAI_Ability_Merger.php` — static class with `merge( array $registry, ?object $override ): array` (returns Effective Ability shape per data-model.md §3; for each overridable field: non-null override wins over registry default; sets `has_override`, `updated_at`, `updated_by`, `_registry` keys) and `is_all_default( array $payload, array $registry ): bool` (returns true only when every field in payload matches the corresponding registry default — used to skip unnecessary DB writes per FR-024)
- [ ] T006b [P] Create `includes/Utilities/AcrossAI_Ability_Registry_Query.php` — static class with `query( array $params, AcrossAI_Sitewide_Query $db_query ): array`; accepts `search`, `orderby`, `order`, `source`, `has_override`, `page`, `per_page`; applies all filter/sort/pagination logic over `wp_get_abilities()`; calls `AcrossAI_Ability_Merger::merge()` per item; returns `[ 'abilities' => array, 'total' => int, 'pages' => int ]`; see `architecture-migration-plan.md` — RF-03: REST controller MUST NOT own filter/sort/paginate logic; this utility is the canonical home reused by all future module list endpoints
- [ ] T006c [P] Create `includes/Utilities/AcrossAI_Ability_Source_Detector.php` — static class with `detect( array $ability ): string`; maps `wp_get_ability()` array to one of four enum values: `'core'` when provider equals `'wordpress-core'` or `'core'`; `'theme'` when ability is registered by active theme (check against `wp_get_theme()->get_stylesheet()`); `'db'` when provider is null or empty (custom ability with no registered provider); `'plugin'` as the fallback for all other registered abilities; MUST be called from REST controller handlers (T025, T028, T037) before `save_override()` — pass result as `$fields['source']` in the fields array; do NOT call from `save_override()` inside `AcrossAI_Sitewide_Query` — the DB Query class must remain a pure data access object (RF-04: Constitution §I single-responsibility; business logic belongs in the controller/utility layer, not in the persistence layer; U1: closes the source detection algorithm gap)
- [ ] T007 Create `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php` — extends `AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\Schema`; defines all 17 columns from data-model.md §1 (`id`, `ability_slug`, `provider`, `source`, `site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `created_at`, `updated_at`, `created_by`, `updated_by`) with correct BerlinDB column array shapes (type, length, unsigned, null, default, sortable)
- [ ] T008 Create `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php` — extends `AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\Table`; sets `$name = 'acrossai_abilities_overwrite'`; sets `$version = '1.0.0'`; references `AcrossAI_Sitewide_Schema::class` via `$schema`; implements `maybe_upgrade()` to call parent and create/update table
- [ ] T009 [P] Create `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php` — extends `AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\Row`; declares all typed properties matching data-model.md §3 Override Row shape; use `AcrossAI_Sanitizer::cast_tri_state( $value )` (from T005) for tinyint → PHP bool/null casting on all tri-state columns — do NOT add a private `cast_tri_state()` method to this class (RF-02: casting belongs in shared utility, not per-module Row classes)
- [ ] T010 Create `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` — extends `AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\Query`; sets `$table_schema = AcrossAI_Sitewide_Schema::class` and `$item_shape = AcrossAI_Sitewide_Row::class` (MUST use `::class` constants, not bare strings per research.md Decision 1); implements `get_override_by_slug( string $slug ): ?AcrossAI_Sitewide_Row`, `save_override( string $slug, array $fields ): bool` (upsert: check `get_override_by_slug()` first; on INSERT: set `created_at` = current UTC datetime AND `created_by` = `get_current_user_id()`; on UPDATE: set `updated_at` = current UTC datetime AND `updated_by` = `get_current_user_id()` ONLY — do NOT overwrite `created_at` or `created_by` on update; A1/A2 fix: previous wording set both fields on every save, corrupting original creator audit data on subsequent edits), `delete_override_by_slug( string $slug ): bool`
- [ ] T011 Update `includes/Activator.php` — in the activation callback add `( new AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table() )->maybe_upgrade();` to create/upgrade the `{prefix}acrossai_abilities_overwrite` table on plugin activation
- [ ] T012 Create `admin/Partials/SitewideAbilityPage.php` — class `AcrossAI_Abilities_Manager\Admin\Partials\SitewideAbilityPage` with `enqueue_assets()` method: hook `admin_enqueue_scripts`, scope to plugin page slug `acrossai-abilities-manager`, load `build/js/sitewide.asset.php` manifest for dependency array and version (never hardcode), register and enqueue `sitewide.js` and `sitewide.css`; localize via `wp_add_inline_script` to set `window.acrossaiAbilitiesSitewide = { nonce: wp_create_nonce('wp_rest'), rest_url: get_rest_url(), current_user_id: get_current_user_id() }`; add `render_page()` method that outputs `<div id="acrossai-abilities-manager-root"></div>`
- [ ] T013 Update `admin/Partials/Menu.php` in-place — `main_menu()` must pass `'dashicons-admin-tools'` as icon and `99` as position to `add_menu_page()`; `contents()` method must call `SitewideAbilityPage::render_page()` to output the React mount point; no new menu class may be created (FR-020, research Decision 8)
- [ ] T014 Create `includes/Modules/Sitewide/index.php` (one-line directory sentinel: `<?php // Silence is golden.`) and create `includes/Modules/Sitewide/AcrossAI_Sitewide_Module.php` — class `AcrossAI_Sitewide_Module` extends `AcrossAI_Module_Base`; `get_name(): string` returns `'sitewide'`; `register_hooks( $loader ): void` calls `$loader->add_action( 'rest_api_init', $controller, 'register_routes' )` where `$controller = new AcrossAI_Sitewide_Rest_Controller( new AcrossAI_Sitewide_Query() )` (U2: passes the query object required by T015 constructor)
- [ ] T015 Create `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` skeleton — constructor `__construct( AcrossAI_Sitewide_Query $db_query )` storing `$this->db_query` as a private property (U2: required by T019/T025/T028/T034/T037 which all call `$this->db_query`; missing constructor causes PHPStan L8 undefined-property error and runtime failure); constant `REST_NAMESPACE = 'acrossai-abilities-manager/v1'`; `register_routes()` stub (to be populated per user story phase); `check_permission()` (NO native return type — PHP 7.4 does not support union return types; use PHPDoc `@return bool|\WP_Error` only — PHPStan L8 infers from docblock without a native hint) — returns `WP_Error( 'rest_forbidden', ..., [ 'status' => 403 ] )` when `current_user_can( 'manage_options' )` fails OR nonce invalid via `wp_verify_nonce( $request->get_header('X-WP-Nonce'), 'wp_rest' )`; returns `true` on success; C1 fix: `: bool|\WP_Error` is PHP 8.0+ syntax and will fatal on PHP 7.4 — docblock only
- [ ] T016 Update `includes/Main.php` — in `define_admin_hooks()` instantiate `SitewideAbilityPage` and call its `enqueue_assets()` via the loader; instantiate `AcrossAI_Sitewide_Module` and call `register_hooks( $this->loader )` on it
- [ ] T017 [P] Create `src/js/sitewide/store/index.js` — `createReduxStore( 'acrossai-abilities/sitewide', { reducer, actions, selectors } )` registered via `register()` from `@wordpress/data`; initial state per data-model.md §5 (`abilities: []`, `total: 0`, `pages: 0`, `currentPage: 1`, `isLoading: false`, `error: null`, `editingSlug: null`, `mcpServers: []`); define all action type constants (`SET_ABILITIES`, `SET_LOADING`, `SET_ERROR`, `SET_EDITING_SLUG`, `SET_MCP_SERVERS`); define all selectors (`getAbilities`, `getTotal`, `getPages`, `getCurrentPage`, `isLoading`, `getError`, `getEditingSlug`, `getMcpServers`)
- [ ] T018 [P] Create `src/js/sitewide/api/client.js` — `@wordpress/api-fetch` wrappers for all 7 REST endpoints using `window.acrossaiAbilitiesSitewide.rest_url` and nonce middleware set once at app init; export: `fetchAbilities( params )`, `fetchAbility( slug )`, `saveOverride( slug, data )`, `deleteOverride( slug )`, `toggleAbility( slug, siteAllowed )`, `bulkAction( slugs, action )`, `fetchMcpServers()`

**Checkpoint**: All PHP infrastructure in place; DB table auto-creates on plugin activation; admin menu registered; JS store skeleton ready

---

## Phase 3: User Story 1 — Browse All Registered Abilities (Priority: P1) 🎯 MVP

**Goal**: Admin visits the Ability Manager page and sees a paginated, searchable, sortable DataViews table of all registered abilities

**Independent Test**: Activate the plugin, visit `/wp-admin/admin.php?page=acrossai-abilities-manager`. A table of all abilities registered via `wp_get_abilities()` renders with Slug, Provider, Source, and Status columns. Search, sort, and page-size controls work. Empty-state message appears when no abilities are registered. Non-admin users receive 403.

- [ ] T019 [US1] Add `get_abilities()` handler and `GET /sitewide/abilities` route to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — register route in `register_routes()` with args validation for `page` (int ≥1), `per_page` (int 1–100), `search` (string), `orderby` (enum: slug/provider/source/status), `order` (enum: asc/desc), `source` (enum: plugin/theme/core/db), `has_override` (bool); `get_abilities()` MUST: (1) validate and sanitize all request args, (2) delegate to `AcrossAI_Ability_Registry_Query::query( $params, $this->db_query )` (from T006b) — do NOT inline filter/sort/paginate logic here, (3) set `X-WP-Total` and `X-WP-TotalPages` response headers from returned `total`/`pages`, (4) return `rest_ensure_response( $result['abilities'] )` with `abilities`, `total`, `pages` per contracts/rest-api.md (RF-03: controller owns validation + delegation + response formatting only)
- [ ] T020 [P] [US1] Create `src/js/sitewide/index.js` — import `createRoot` from `@wordpress/element`; register apiFetch nonce middleware using `window.acrossaiAbilitiesSitewide.nonce`; register Redux store from `store/index.js`; call `createRoot( document.getElementById('acrossai-abilities-manager-root') ).render( <AbilityManager /> )`
- [ ] T021 [P] [US1] Create `src/js/sitewide/components/AbilityManager.jsx` — page root component; manages DataViews `view` state (type, search, page, perPage, sort, fields) initialized from localStorage key `acrossai_ability_table_view_{current_user_id}`; persists `view` changes to localStorage; renders `AbilityTable` passing view state and dispatch handlers; reads `abilities`, `total`, `pages`, `isLoading`, `error` from Redux store via `useSelect`
- [ ] T022 [US1] Create `src/js/sitewide/components/AbilityTable.jsx` — `DataViews` component from `@wordpress/dataviews`; defines fields: `slug` (sortable, searchable), `provider` (sortable, filterable), `source` (sortable, filterable by plugin/theme/core/db), `status` (displays `site_allowed` as "Allowed"/"Disallowed" or "Allowed (Default)"/"Disallowed (Default)" per FR-002/FR-026 using `has_override` and registry value), `updated_at` (sortable); search debounced 500ms via `useDebounce( view.search, 500 )` from `@wordpress/compose`; perPage options [10, 20, 50, 100]; column visibility from view state; dispatches `fetchAbilities` when view changes; renders empty-state message when `total === 0` and not loading (FR-022)
- [ ] T023 [P] [US1] Create `src/scss/sitewide/admin.scss` — status badge styles for Allowed (green), Disallowed (red), Default (grey indicator suffix); drawer animation styles (`transform: translateX(100%)` to `translateX(0)`, 300ms ease transition); WPDS design token references for spacing, color, typography
- [ ] T024 [US1] Add `fetchAbilities( viewParams )` async action creator to `src/js/sitewide/store/index.js` — dispatches `SET_LOADING`, calls `client.fetchAbilities()`, dispatches `SET_ABILITIES` with response `{ abilities, total, pages }`, dispatches `SET_ERROR` on failure; `useDispatch` consumer in `AbilityManager.jsx` calls this when view changes

**Checkpoint**: User Story 1 fully functional — admin can browse, search, sort, and paginate all registered abilities

---

## Phase 4: User Story 2 — Allow or Disallow an Ability Site-wide (Priority: P2)

**Goal**: Admin clicks Allow/Disallow in a table row; the change saves immediately without page reload and persists across reloads

**Independent Test**: Click Disallow on any ability row. Status changes to Disallowed immediately. Reload page — still Disallowed. Click Allow. Reload — shows Allowed. On network failure the status reverts and an inline error appears.

- [ ] T025 [US2] Add `toggle_ability()` handler and `POST /sitewide/abilities/{slug}/toggle` route to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — route arg: `slug` (string, required, URL-encoded, sanitize via `AcrossAI_Sanitizer::sanitize_ability_slug( $slug )` before any use — SEC-01); body arg: `site_allowed` (bool, required, sanitized via `AcrossAI_Sanitizer::sanitize_tri_state()`); `toggle_ability()` validates ability exists via `wp_get_ability( $slug )`, returns 404 WP_Error if absent; call `AcrossAI_Ability_Source_Detector::detect( wp_get_ability( $slug ) )` and include result as `$fields['source']` (RF-04); calls `AcrossAI_Sitewide_Query::save_override()` with `[ 'site_allowed' => $site_allowed, 'source' => $source ]`; returns `{ slug, site_allowed, has_override }` per rest-api.md contract; fires `do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields )` passing sanitized `$fields` as second argument (SEC-02)
- [ ] T026 [US2] Add `toggleAllowed( slug, siteAllowed )` async action creator to `src/js/sitewide/store/index.js` — performs optimistic update (immediately update ability in `abilities` array), calls `client.toggleAbility()`, on error rolls back to previous state and dispatches `SET_ERROR` with inline error message; on success updates the ability record in state with returned `site_allowed` and `has_override` values
- [ ] T027 [US2] Add inline Allow/Disallow toggle button to `src/js/sitewide/components/AbilityTable.jsx` row actions — renders as a `Button` from `@wordpress/components`; label shows "Disallow" when ability is allowed, "Allow" when disallowed; on click dispatches `toggleAllowed`; shows per-row loading spinner during in-flight request; shows inline error notice on failure and reverts displayed status (SC-002: must complete within 1 s)

**Checkpoint**: User Stories 1 and 2 both independently functional

---

## Phase 5: User Story 3 — Edit Per-Ability Settings via Form (Priority: P3)

**Goal**: Admin opens a slide-in drawer for any ability, sees a two-tab DataForms panel (General + MCP), edits override values, and saves. Only changed fields are written to DB; "No changes made" shown when nothing differs from registry defaults.

**Independent Test**: Click Edit on any ability. Slide-in panel opens with General and MCP tabs. Change "Readonly" value. Save — "Settings saved" notice appears. Reopen — changed value persists, registry default shown alongside. Open Edit on a second ability, make no changes, click Save — "No changes made" notice, no DB write.

- [ ] T028 [US3] Add `get_ability()`, `save_override()`, and `get_mcp_servers()` handlers and their routes to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — `GET /sitewide/abilities/{slug}`: validate slug, merge registry + override, return Effective Ability shape; `POST /sitewide/abilities/{slug}`: sanitize `$slug` URL parameter via `AcrossAI_Sanitizer::sanitize_ability_slug()` first (SEC-01); sanitize each field via `AcrossAI_Sanitizer`; call `AcrossAI_Ability_Source_Detector::detect( wp_get_ability( $slug ) )` and include as `$fields['source']` (RF-04); call `AcrossAI_Ability_Merger::is_all_default()`, return `unchanged: true` if no diff (FR-024/025), else call `AcrossAI_Sitewide_Query::save_override()` (sets `show_in_mcp = NULL` and `mcp_servers = NULL` when "Keep as Default" — i.e., both submitted as null — per FR-017), if `is_all_default()` returns true AND an existing override record is found via `get_override_by_slug()`, MUST call `delete_override_by_slug()` to remove the orphaned record before returning `unchanged: true` (I3: prevents stale override rows persisting when all fields revert to defaults — FR-024 says 'no record MUST be modified', which implies removal); fires `do_action( 'acrossai_abilities_sitewide_before_save', $slug, $fields )` and `do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields )` passing sanitized `$fields` as second argument only when a write or delete actually occurs (SEC-02: hooks must pass sanitized data only); `GET /sitewide/mcp-servers`: guard with `class_exists( 'WP\\MCP\\Core\\McpAdapter' )`, return `McpAdapter::instance()->get_servers()` as `[{ id, label }]` array or `[]` when adapter absent
- [ ] T029 [US3] Add `openEditPanel( slug )`, `closeEditPanel()`, `saveOverride( slug, data )`, `fetchMcpServers()` async action creators to `src/js/sitewide/store/index.js`; `saveOverride` dispatches optimistic state update, calls `client.saveOverride()`, handles `unchanged: true` response by dispatching a `SAVE_UNCHANGED` signal and NOT updating state; on success dispatches updated ability into `abilities` array; `fetchMcpServers` sets `mcpServers` state
- [ ] T030 [P] [US3] Create `src/js/sitewide/components/McpVisibilityControl.jsx` — renders MCP Visibility `RadioControl` from `@wordpress/components` with 4 options (Keep as Default / Disable for MCP / Allow in all MCP servers / Allow in specific MCP servers) per FR-017; conditionally renders MCP Type `SelectControl` (tool/resource/prompt) for options 3 and 4; conditionally renders server `CheckboxControl` multi-select for option 4 only using `mcpServers` from store; shows `Notice` ("No MCP servers configured") when option 4 is selected but server list is empty (FR-018); encodes selection to `show_in_mcp`/`mcp_servers` fields per data-model.md §1 MCP visibility encoding table
- [ ] T031 [US3] Create `src/js/sitewide/components/AbilityEditPanel.jsx` — renders via `createPortal( content, document.body )` imported as `import { createPortal } from '@wordpress/element'` (I4: `@wordpress/element` re-exports React's createPortal; direct `ReactDOM` import is prohibited by Constitution §VI and package-strategy — always use the WordPress-bundled re-export); fixed-position slide-in drawer (`position: fixed; right: 0; top: 0; height: 100vh; width: 420px`) with CSS transform animation (300ms ease, translateX(100%) hidden → translateX(0) open); `DataForms` from `@wordpress/dataforms` organized in two tabs ("General": site_allowed/readonly/destructive/idempotent/show_in_rest fields; "MCP": McpVisibilityControl + mcp_type); shows each field's registry default value as read-only reference (FR-013); handles Save: calls `saveOverride`, shows "Settings saved" notice on success, "No changes made" notice when `unchanged: true` (FR-025); handles Cancel and Escape key close without saving; handles backdrop click close without saving; loading state during save
- [ ] T032 [US3] Update `src/js/sitewide/components/AbilityTable.jsx` — add "Edit" action to row actions that dispatches `openEditPanel( ability.slug )` via `useDispatch`; calls `fetchMcpServers()` on first edit panel open (lazy load)
- [ ] T033 [US3] Update `src/js/sitewide/components/AbilityManager.jsx` — conditionally render `AbilityEditPanel` when `editingSlug` is non-null (from `getEditingSlug` selector); pass `editingSlug`, `onClose = () => dispatch( closeEditPanel() )`, and the full ability object from `getAbilities` to the panel

**Checkpoint**: User Stories 1, 2, and 3 all independently functional

---

## Phase 6: User Story 4 — Reset Override to Registry Defaults (Priority: P4)

**Goal**: Admin clicks Reset Override in a row action menu; all stored override fields are deleted and the ability reverts to registry defaults

**Independent Test**: Save any override. Click Reset Override. Reload. Ability shows registry default values with "(Default)" indicator. Open edit panel — all fields show null/default. For an ability with no override, Reset is disabled/hidden.

- [ ] T034 [US4] Add `delete_override()` handler and `DELETE /sitewide/abilities/{slug}` route to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — sanitize `$slug` URL parameter via `AcrossAI_Sanitizer::sanitize_ability_slug()` first (SEC-01); validate slug exists in registry; call `AcrossAI_Sitewide_Query::delete_override_by_slug()`; return `{ slug, deleted: true }` if a record was deleted or `{ slug, deleted: false, message: '...' }` if no override existed (per rest-api.md contract)
- [ ] T035 [US4] Add `deleteOverride( slug )` async action creator to `src/js/sitewide/store/index.js` — optimistic update (set `has_override: false`, clear all non-null override fields back to registry defaults in the local abilities array), call `client.deleteOverride()`, roll back on error
- [ ] T036 [US4] Add Reset Override row action to `src/js/sitewide/components/AbilityTable.jsx` — visible in row action menu only when `ability.has_override === true`; when `has_override` is false the action is either hidden or rendered as disabled with tooltip "No override saved" (FR — spec US4 scenario 2); on click dispatches `deleteOverride( ability.slug )`

**Checkpoint**: User Stories 1–4 all independently functional

---

## Phase 7: User Story 5 — Bulk Allow, Disallow, or Reset Multiple Abilities (Priority: P5)

**Goal**: Admin selects multiple ability rows via checkboxes and applies a bulk Allow, Disallow, or Reset action to all selected at once

**Independent Test**: Select 3 ability rows. Click Bulk Disallow — all 3 show Disallowed, selection cleared. Select same 3. Click Bulk Reset — all 3 revert to defaults. Partial failure shows a summary: N succeeded, M failed.

- [ ] T037 [US5] Add `bulk_action()` handler and `POST /sitewide/abilities/bulk` route to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — body args: `slugs` (array of strings, max 100, each sanitized), `action` (enum: allow/disallow/reset); validate max 100 slugs; iterate each slug: skip unknown slugs (record in `skipped`); reject unknown `action` values immediately with `WP_Error` 400 before processing (SEC-01); each `slug` in `slugs` is already individually sanitized — also sanitize via `AcrossAI_Sanitizer::sanitize_ability_slug()`; apply action: allow/disallow → call `AcrossAI_Ability_Source_Detector::detect( wp_get_ability( $slug ) )` and include as `$fields['source']` (RF-04) → `save_override()` with `[ 'site_allowed' => ..., 'source' => $source ]`; reset → `delete_override_by_slug()`; return `{ succeeded: int, failed: int, skipped: string[], results: [{slug, status}] }` (FR-012, spec US5 scenario 3)
- [ ] T038 [US5] Add `bulkAction( slugs, action )` async action creator to `src/js/sitewide/store/index.js` — dispatches `SET_LOADING`, calls `client.bulkAction()`, dispatches `SET_ABILITIES` slice update for each affected slug based on returned results, dispatches `SET_ERROR` with summary message on partial failure
- [ ] T039 [US5] Create `src/js/sitewide/components/BulkActionToolbar.jsx` — renders "Bulk Allow", "Bulk Disallow", "Bulk Reset" `Button` components; all three disabled (and aria-disabled) when `selectedSlugs.length === 0` (spec US5 scenario 4); shows `selectedSlugs.length` selected count label; on click dispatches `bulkAction( selectedSlugs, action )`; shows `Notice` with partial-success summary when `failed > 0`
- [ ] T040 [US5] Integrate `BulkActionToolbar` into `src/js/sitewide/components/AbilityManager.jsx` — wire DataViews `selection` state (controlled); pass `selectedSlugs` to `BulkActionToolbar`; clear selection after bulk action completes; `BulkActionToolbar` renders above the `AbilityTable`

**Checkpoint**: All 5 user stories fully functional and independently testable

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Tests, static analysis, validation, and edge-case hardening across all stories

- [ ] T041 [P] Create `tests/phpunit/sitewide/RestControllerTest.php` — PHPUnit tests for: permission check (non-admin returns 403, missing nonce returns 403), response shape of GET /sitewide/abilities (correct fields, pagination headers), GET /sitewide/abilities/{slug} 404 for unknown slug, POST save returns `unchanged: true` when payload matches registry, DELETE returns `deleted: false` when no override exists
- [ ] T042 [P] Create `tests/phpunit/sitewide/SitewideQueryTest.php` — PHPUnit tests for: `save_override()` creates new record with correct fields, `save_override()` updates existing record (upsert), `delete_override_by_slug()` deletes existing and returns true, `delete_override_by_slug()` returns false when no record, `get_override_by_slug()` returns null for unknown slug, NULL column semantics verified
- [ ] T043 [P] Create `tests/phpunit/sitewide/AbilityMergerTest.php` — PHPUnit tests for: `merge()` returns registry value when override field is null, `merge()` override field wins when non-null, `is_all_default()` returns true when all payload fields equal registry defaults, `is_all_default()` returns false when at least one field differs, MCP "Keep as Default" sets both `show_in_mcp` and `mcp_servers` to null
- [ ] T044 [P] Create `tests/jest/sitewide/AbilityManager.test.js` — Jest tests for: Redux store initial state shape, `fetchAbilities` dispatches SET_LOADING then SET_ABILITIES, `toggleAllowed` optimistic update and rollback on error, `saveOverride` with `unchanged: true` response triggers no state mutation, `AbilityTable` renders empty-state when abilities array is empty
- [ ] T045 Run PHPCS with WordPress standard against `includes/Modules/Sitewide/`, `includes/Utilities/`, `includes/Base/` — resolve all errors and warnings; confirm all functions and classes prefixed with `acrossai_` / `AcrossAI_`
- [ ] T046 Run PHPStan level 8 against `includes/Modules/Sitewide/`, `includes/Utilities/`, `includes/Base/` — resolve all type errors; confirm no unsafe null dereferences; confirm `::class` constants used on BerlinDB properties
- [ ] T047 Harden edge cases across all PHP and JS: (1) `wp_get_abilities()` returns zero abilities → empty-state message shown, no table rendered (FR-022); (2) ability deregistered after override exists → override row silently remains in DB, absent ability does not appear in table (registry is source of truth); (3) bulk action on 100+ items → loading indicator shown, table refreshes after completion; (4) MCP adapter absent → server multi-select hidden, admin notice shown per FR-018; (5) all-null override record after "Keep as Default" save → entire record deleted per FR-017 / research Decision 11
- [ ] T048 Run quickstart.md validation: `npm run build` (no errors, `build/js/sitewide.js` and `build/css/sitewide.css` emitted), plugin activate (table created via `maybe_upgrade()`), navigate to `/wp-admin/admin.php?page=acrossai-abilities-manager` and confirm React app mounts and ability table renders; run `wp rest route list | grep acrossai` to confirm all 7 routes registered; confirm page load < 2 s (SC-001); manually time the Allow/Disallow toggle: click → status update must complete within 1 s (SC-002); apply bulk Disallow to 10+ abilities and confirm all complete within 5 s total (SC-007)

---

## Definition of Done

Feature is complete only when ALL 8 gates pass:

1. **PHPCS**: Zero violations on WordPress standard for all new PHP files
2. **PHPStan L8**: Zero errors on all new PHP files
3. **PHPUnit**: All tests in `tests/phpunit/sitewide/` pass
4. **Jest**: All tests in `tests/jest/sitewide/` pass
5. **Functional**: All 5 user story Independent Tests pass manually
6. **Security**: `manage_options` enforced on all 7 REST endpoints; nonce validated; all input sanitized; all output escaped
7. **Build**: `npm run build` succeeds with no errors; asset manifest loaded (no hardcoded versions)
8. **Quickstart**: `quickstart.md` validation checklist passes end-to-end

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 — BLOCKS all user stories
- **User Stories (Phase 3–7)**: All depend on Phase 2 completion
  - Stories proceed in priority order (P1 → P2 → P3 → P4 → P5)
  - REST controller is built incrementally: skeleton in Phase 2, endpoints added per story phase
- **Polish (Phase 8)**: Depends on all user story phases completing

### User Story Dependencies

- **US1 (P1)**: Starts after Phase 2 — no dependency on other stories — 🎯 MVP
- **US2 (P2)**: Starts after Phase 2 — depends on US1 table and store foundation
- **US3 (P3)**: Starts after Phase 2 — depends on US1 table and US2 toggle for context
- **US4 (P4)**: Starts after Phase 2 — can be developed in parallel with US3
- **US5 (P5)**: Starts after Phase 2 — depends on US1 table; independent of US3/US4

### Within Each User Story

- REST endpoint tasks come before JS client/store tasks (contract first)
- Store action creators come before component wiring
- Components come before integration into parent (AbilityManager.jsx)
- Core story implementation before edge cases

### Parallel Opportunities

- T002 and T003 can run in parallel with T001 (different files)
- T005, T006, T006b, and T006c can run in parallel (different files, no dependencies)
- T009 can run in parallel with T007/T008 (different files)
- T017 and T018 can run in parallel (different files)
- T020, T021, T023 can run in parallel within US1 (different files)
- T030 can run in parallel within US3 (different file from drawer and store)
- T041, T042, T043, T044 can run in parallel in Phase 8 (different test files)

---

## Parallel Example: User Story 1 (Phase 3)

```bash
# These US1 tasks can run in parallel (independent files):
T020  src/js/sitewide/index.js          (React entry)
T021  src/js/sitewide/components/AbilityManager.jsx   (page root)
T023  src/scss/sitewide/admin.scss      (styles)

# These must run sequentially (dependencies):
T019  → (REST endpoint must exist before JS can call it)
T022  → (depends on T017 store skeleton and T018 client)
T024  → (depends on T022 knowing what params to send)
```

---

## Implementation Strategy

**MVP Scope (Phase 1 + Phase 2 + Phase 3 only)**:
Delivers a read-only browseable table of all registered abilities. Proves the full stack works (BerlinDB, REST endpoint, React DataViews, admin page). Can ship independently with real governance value — admins can see what abilities are registered.

**Incremental Delivery**:
1. MVP: Browse table (US1)
2. + Inline toggle (US2) → primary governance action
3. + Edit panel (US3) → full metadata control
4. + Reset (US4) → confidence to make changes
5. + Bulk actions (US5) → operational efficiency at scale

Each story is independently releasable.
