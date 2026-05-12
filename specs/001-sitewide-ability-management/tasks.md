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

- [x] T001 Add `"berlindb/core": "^2.0"` to `composer.json` require; add Mozart config with `dep_namespace: "AcrossAI_Abilities_Manager\\Vendor\\"` to prefix BerlinDB to `AcrossAI_Abilities_Manager\Vendor\BerlinDB\`; run `composer require berlindb/core:^2.0` and `composer exec mozart compose` and `composer dump-autoload`
- [x] T002 [P] Update `webpack.config.js` to spread `defaultConfig.entry` and add entries `'js/sitewide' → './src/js/sitewide/index.js'` and `'css/sitewide' → './src/scss/sitewide/admin.scss'` — key format MUST match existing project convention (`'js/frontend'`, `'css/frontend'`, etc.; see `webpack.config.js` lines 65–76)
- [x] T003 [P] Create empty-file directory scaffolding for all new directories: `includes/Base/`, `includes/Utilities/`, `includes/Modules/Sitewide/Database/`, `src/js/sitewide/api/`, `src/js/sitewide/store/`, `src/js/sitewide/components/`, `src/scss/sitewide/`, `tests/phpunit/sitewide/`, `tests/jest/sitewide/`

**Checkpoint**: Composer and npm dependencies resolved; webpack configured; directory structure ready

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core PHP infrastructure and JS skeleton that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T004 Create `includes/Base/AcrossAI_Module_Base.php` — abstract class `AcrossAI_Module_Base` with `abstract public function register_hooks( Loader $loader ): void` (add `use AcrossAI_Abilities_Manager\Includes\Loader;` at top of file — required for PHPStan L8 type inference) and `abstract public function get_name(): string`; PHP 7.4 compatible; full `acrossai_` prefix; WPCS-compliant docblock
- [x] T005 [P] Create `includes/Utilities/AcrossAI_Sanitizer.php` — static class with `sanitize_ability_slug( string $slug ): string` (sanitize_text_field + validate against registered slugs), `sanitize_tri_state( $value ): ?bool` (**critical null-safety rule**: MUST use strict `===` comparisons — NEVER PHP loose equality — so that `null` (Inherit) is never coerced to `false` (No); exact mapping: `true`/`1`/`"1"` → PHP `true`; `false`/`0`/`"0"` → PHP `false`; `null`/`"null"`/`"inherit"` → PHP `null`; JSON `null` decoded from REST body → PHP `null`; any other value → return `null` with a logged notice — do NOT return `false` as fallback), `sanitize_mcp_type( $value ): ?string` (allows 'tool'|'resource'|'prompt'|null), `sanitize_mcp_servers_array( $value ): ?array` (validate array of non-empty strings or null), `cast_tri_state( $value ): ?bool` (casts tinyint DB values read back from MySQL: `1` → PHP `true`; `0` → PHP `false`; SQL `NULL`/PHP `null` → PHP `null` — shared casting utility for ALL BerlinDB Row classes; MUST use strict `=== 1` / `=== 0` / `=== null` checks, NOT loose `==`; must NOT be duplicated on individual Row classes — RF-02). **The distinction between `false` (explicit "No" override) and `null` (Inherit / no override) is the core invariant of the tri-state system. Losing this distinction corrupts governance semantics.**
- [x] T006 [P] Create `includes/Utilities/AcrossAI_Ability_Merger.php` — static class with `merge( array $registry, ?object $override ): array` (returns Effective Ability shape per data-model.md §3; for each overridable tri-state field: **`null` in the override row means "no override for this field" — use the registry default**; **`false` in the override row means "explicitly set to No" — this IS an override and MUST win over the registry default**; the merge condition is `override[F] !== null` — only non-null override values win; sets `has_override`, `updated_at`, `updated_by`, `_registry` keys) and `is_all_default( array $payload, array $registry ): bool` (returns true only when every field in payload matches the corresponding registry default — `null` payload field matches registry default `null`; `false` payload field does NOT match registry default `true`, and vice versa — used to skip unnecessary DB writes per FR-024). **Do NOT use `!empty()` or `isset()` to check override values — these lose the `false` state. Always use `!== null`.**
- [x] T006b [P] Create `includes/Utilities/AcrossAI_Ability_Registry_Query.php` — static class with `query( array $params, AcrossAI_Sitewide_Query $db_query ): array`; accepts `search`, `orderby`, `order`, `source`, `has_override`, `page`, `per_page`; applies all filter/sort/pagination logic over `wp_get_abilities()`; calls `AcrossAI_Ability_Merger::merge()` per item; returns `[ 'abilities' => array, 'total' => int, 'pages' => int ]`; see `architecture-migration-plan.md` — RF-03: REST controller MUST NOT own filter/sort/paginate logic; this utility is the canonical home reused by all future module list endpoints
- [x] T006c [P] Create `includes/Utilities/AcrossAI_Ability_Source_Detector.php` — static class with `detect( array $ability ): string`; maps `wp_get_ability()` array to one of four enum values: `'core'` when provider equals `'wordpress-core'` or `'core'`; `'theme'` when ability is registered by active theme (check against `wp_get_theme()->get_stylesheet()`); `'db'` when provider is null or empty (custom ability with no registered provider); `'plugin'` as the fallback for all other registered abilities; MUST be called from REST controller handlers (T025, T028, T037) before `save_override()` — pass result as `$fields['source']` in the fields array; do NOT call from `save_override()` inside `AcrossAI_Sitewide_Query` — the DB Query class must remain a pure data access object (RF-04: Constitution §I single-responsibility; business logic belongs in the controller/utility layer, not in the persistence layer; U1: closes the source detection algorithm gap)
- [x] T007 Create `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php` — extends `AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\Schema`; defines all 17 columns from data-model.md §1 (`id`, `ability_slug`, `provider`, `source`, `site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `created_at`, `updated_at`, `created_by`, `updated_by`) with correct BerlinDB column array shapes (type, length, unsigned, null, default, sortable). **Critical for all five tri-state tinyint columns (`site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`)**: each MUST have `'null' => true` AND `'default' => null` in its column array so BerlinDB allows SQL `NULL` to be stored. A column defined as NOT NULL or with a non-null default will silently convert PHP `null` to `0`, making "Inherit" and "No" indistinguishable in the DB. The three valid DB states are: `1` = Yes (explicit override), `0` = No (explicit override), `NULL` = Inherit (no override for this field).
- [x] T008 Create `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php` — extends `AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\Table`; sets `$name = 'acrossai_abilities_overwrite'`; sets `$version = '1.0.0'`; references `AcrossAI_Sitewide_Schema::class` via `$schema`; implements `maybe_upgrade()` to call parent and create/update table
- [x] T009 [P] Create `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php` — extends `AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\Row`; declares all typed properties matching data-model.md §3 Override Row shape; use `AcrossAI_Sanitizer::cast_tri_state( $value )` (from T005) for tinyint → PHP bool/null casting on all tri-state columns — do NOT add a private `cast_tri_state()` method to this class (RF-02); **`mcp_servers` column must be JSON-decoded on read**: in the Row constructor (or `__set` / `init` override), after BerlinDB populates properties, decode `mcp_servers`: `$this->mcp_servers = ( null !== $this->mcp_servers ) ? json_decode( $this->mcp_servers, true ) : null` — this converts the stored JSON string back to a PHP array (or null) so callers receive an array, matching the Effective Ability shape in data-model.md §3; without this decode the REST response returns a raw JSON string instead of an array, breaking the JS client
- [x] T010 Create `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` — extends `AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\Query`; sets `$table_schema = AcrossAI_Sitewide_Schema::class` and `$item_shape = AcrossAI_Sitewide_Row::class` (MUST use `::class` constants, not bare strings per research.md Decision 1); implements: `get_override_by_slug( string $slug ): ?AcrossAI_Sitewide_Row` (query by `ability_slug` column); `save_override( string $slug, array $fields ): bool` — **upsert with correct BerlinDB return-value handling (root cause of "rest_save_failed" 500)**: (1) call `get_override_by_slug( $slug )` to check for existing row; (2) **before passing `$fields` to BerlinDB, JSON-encode `mcp_servers`**: if `isset( $fields['mcp_servers'] ) && is_array( $fields['mcp_servers'] )` then `$fields['mcp_servers'] = wp_json_encode( $fields['mcp_servers'] )` — BerlinDB stores this as `longtext` and does NOT auto-encode PHP arrays; (3) **INSERT path** (no existing row): `$result = $this->add_item( array_merge( $fields, [ 'ability_slug' => $slug, 'created_at' => gmdate('Y-m-d H:i:s'), 'created_by' => get_current_user_id() ] ) )` — `add_item()` returns the new **integer ID** on success or `false` on failure; check success with `$result !== false && (int) $result > 0`; (4) **UPDATE path** (existing row found): `$result = $this->update_item( $existing->id, array_merge( $fields, [ 'updated_at' => gmdate('Y-m-d H:i:s'), 'updated_by' => get_current_user_id() ] ) )` — the first arg MUST be `$existing->id` (the numeric primary key integer from the Row object, NOT the slug string) — `update_item()` returns the updated **item object** on success or `false` on failure; check success with `$result !== false`; (5) do NOT overwrite `created_at` or `created_by` on UPDATE (A1/A2 fix); `delete_override_by_slug( string $slug ): bool` — query for existing row first; if not found return `false`; call `$this->delete_item( $existing->id )` and return `$result !== false`
- [x] T011 Update `includes/Activator.php` — in the activation callback add `( new AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Table() )->maybe_upgrade();` to create/upgrade the `{prefix}acrossai_abilities_overwrite` table on plugin activation
- [x] T012 Create `admin/Partials/SitewideAbilityPage.php` — class `AcrossAI_Abilities_Manager\Admin\Partials\SitewideAbilityPage` with `enqueue_assets()` method: hook `admin_enqueue_scripts`, scope to plugin page slug `acrossai-abilities-manager`, load `build/js/sitewide.asset.php` manifest for dependency array and version (never hardcode), register and enqueue `sitewide.js` and `sitewide.css`; localize via `wp_add_inline_script` to set `window.acrossaiAbilitiesSitewide = { nonce: wp_create_nonce('wp_rest'), rest_url: get_rest_url(), current_user_id: get_current_user_id() }`; add `render_page()` method that outputs `<div id="acrossai-abilities-manager-root"></div>`. `render_page()` MUST wrap the React root in the standard WordPress admin wrapper: `<div class="wrap acrossai-abilities-manager-wrap"><div id="acrossai-abilities-manager-root"></div></div>` so the page inherits the standard WP admin gutters (the bare `<div id="...">` without `.wrap` is why the UI hugs the left edge instead of filling the admin content area).
- [x] T013 Update `admin/Partials/Menu.php` in-place — `main_menu()` must pass `'dashicons-admin-tools'` as icon and `99` as position to `add_menu_page()`; `contents()` method must call `SitewideAbilityPage::render_page()` to output the React mount point; no new menu class may be created (FR-020, research Decision 8)
- [x] T014 Create `includes/Modules/Sitewide/index.php` (one-line directory sentinel: `<?php // Silence is golden.`) and create `includes/Modules/Sitewide/AcrossAI_Sitewide_Module.php` — class `AcrossAI_Sitewide_Module` extends `AcrossAI_Module_Base`; `get_name(): string` returns `'sitewide'`; `register_hooks( Loader $loader ): void` (add `use AcrossAI_Abilities_Manager\Includes\Loader;` at top — matches T004 abstract signature; required for PHPStan L8) calls `$loader->add_action( 'rest_api_init', $controller, 'register_routes' )` where `$controller = new AcrossAI_Sitewide_Rest_Controller( new AcrossAI_Sitewide_Query() )` (U2: passes the query object required by T015 constructor)
- [x] T015 Create `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` skeleton — constructor `__construct( AcrossAI_Sitewide_Query $db_query )` storing `$this->db_query` as a private property (U2: required by T019/T025/T028/T034/T037 which all call `$this->db_query`; missing constructor causes PHPStan L8 undefined-property error and runtime failure); constant `REST_NAMESPACE = 'acrossai-abilities-manager/v1'`; `register_routes()` stub (to be populated per user story phase); `check_permission()` (NO native return type — PHP 7.4 does not support union return types; use PHPDoc `@return bool|\WP_Error` only — PHPStan L8 infers from docblock without a native hint) — returns `WP_Error( 'rest_forbidden', ..., [ 'status' => 403 ] )` when `current_user_can( 'manage_options' )` fails OR nonce invalid via `wp_verify_nonce( $request->get_header('X-WP-Nonce'), 'wp_rest' )`; returns `true` on success; C1 fix: `: bool|\WP_Error` is PHP 8.0+ syntax and will fatal on PHP 7.4 — docblock only
- [x] T016 Update `includes/Main.php` — in `define_admin_hooks()` instantiate `SitewideAbilityPage` and call its `enqueue_assets()` via the loader; instantiate `AcrossAI_Sitewide_Module` and call `register_hooks( $this->loader )` on it
- [x] T017 [P] Create `src/js/sitewide/store/index.js` — `createReduxStore( 'acrossai-abilities/sitewide', { reducer, actions, selectors } )` registered via `register()` from `@wordpress/data`; initial state per data-model.md §5 (`abilities: []`, `total: 0`, `pages: 0`, `currentPage: 1`, `isLoading: false`, `error: null`, `editingSlug: null`, `mcpServers: []`); define all action type constants (`SET_ABILITIES`, `SET_LOADING`, `SET_ERROR`, `SET_EDITING_SLUG`, `SET_MCP_SERVERS`); define all selectors (`getAbilities`, `getTotal`, `getPages`, `getCurrentPage`, `isLoading`, `getError`, `getEditingSlug`, `getMcpServers`)
- [x] T018 [P] Create `src/js/sitewide/api/client.js` — `@wordpress/api-fetch` wrappers for all 7 REST endpoints using `window.acrossaiAbilitiesSitewide.rest_url` and nonce middleware set once at app init; export: `fetchAbilities( params )`, `fetchAbility( slug )`, `saveOverride( slug, data )`, `deleteOverride( slug )`, `toggleAbility( slug, siteAllowed )`, `bulkAction( slugs, action )`, `fetchMcpServers()`

**Checkpoint**: All PHP infrastructure in place; DB table auto-creates on plugin activation; admin menu registered; JS store skeleton ready

---

## Phase 3: User Story 1 — Browse All Registered Abilities (Priority: P1) 🎯 MVP

**Goal**: Admin visits the Ability Manager page and sees a paginated, searchable, sortable DataViews table of all registered abilities

**Independent Test**: Activate the plugin, visit `/wp-admin/admin.php?page=acrossai-abilities-manager`. A table of all abilities registered via `wp_get_abilities()` renders with Slug, Provider, Source, and Status columns. Search, sort, and page-size controls work. Empty-state message appears when no abilities are registered. Non-admin users receive 403.

- [x] T019 [US1] Add `get_abilities()` handler and `GET /sitewide/abilities` route to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — register route in `register_routes()` with args validation for `page` (int ≥1), `per_page` (int 1–100), `search` (string), `orderby` (enum: slug/provider/source/status), `order` (enum: asc/desc), `source` (enum: plugin/theme/core/db), `has_override` (bool); `get_abilities()` MUST: (1) validate and sanitize all request args, (2) delegate to `AcrossAI_Ability_Registry_Query::query( $params, $this->db_query )` (from T006b) — do NOT inline filter/sort/paginate logic here, (3) set `X-WP-Total` and `X-WP-TotalPages` response headers from returned `total`/`pages`, (4) return `rest_ensure_response( $result['abilities'] )` with `abilities`, `total`, `pages` per contracts/rest-api.md (RF-03: controller owns validation + delegation + response formatting only)
- [x] T020 [P] [US1] Create `src/js/sitewide/index.js` — import `createRoot` from `@wordpress/element`; register apiFetch nonce middleware using `window.acrossaiAbilitiesSitewide.nonce`; register Redux store from `store/index.js`; call `createRoot( document.getElementById('acrossai-abilities-manager-root') ).render( <AbilityManager /> )`
- [x] T021 [P] [US1] Create `src/js/sitewide/components/AbilityManager.jsx` — page root component; manages DataViews `view` state (type, search, page, perPage, sort, fields) initialized from localStorage key `acrossai_ability_table_view_{current_user_id}`; persists `view` changes to localStorage; renders `AbilityTable` passing view state and dispatch handlers; reads `abilities`, `total`, `pages`, `isLoading`, `error` from Redux store via `useSelect`. `DEFAULT_VIEW.fields` MUST list the default-visible columns in this exact order: `[ 'slug', 'provider', 'source', 'status', 'show_in_rest', 'show_in_mcp', 'mcp_type', 'mcp_servers', 'destructive', 'updated_at' ]`. The fields `readonly` and `idempotent` MUST be defined in T022's fields array but OMITTED from `DEFAULT_VIEW.fields` so they ship hidden by default — admins can re-enable them via the DataViews column-visibility menu. This satisfies the requirement that readonly/idempotent are available but not cluttering the default view.
- [x] T022 [US1] Create `src/js/sitewide/components/AbilityTable.jsx` — `DataViews` component from `@wordpress/dataviews`; defines **12 fields** in this order: `slug` (sortable, searchable), `provider` (sortable, filterable), `source` (sortable, filterable by plugin/theme/core/db), `status` (displays `site_allowed` as "Allowed"/"Disallowed" or "Allowed (Default)"/"Disallowed (Default)" per FR-002/FR-026 using `has_override` and registry value), `readonly` (hidden by default — not in DEFAULT_VIEW.fields — `enableHiding: true`, rendered via `TriStateBadgeCell`), `destructive` (`enableHiding: true`, rendered via `TriStateBadgeCell`), `idempotent` (hidden by default — `enableHiding: true`, rendered via `TriStateBadgeCell`), `show_in_rest` (`enableHiding: true`, rendered via `TriStateBadgeCell`), `show_in_mcp` (`enableHiding: true`, rendered via `TriStateBadgeCell`), `mcp_type` (`enableHiding: true`, render raw string `tool`/`resource`/`prompt` or `—` when null, add `elements` filter array for filter-by-type), `mcp_servers` (`enableHiding: true`, `enableSorting: false`, rendered via `McpServersCell` — shows `All` when value is null AND `show_in_mcp === true`; comma-joined server IDs truncated at 3 with `+N more` overflow chip when non-empty array; `—` otherwise), `updated_at` (sortable). `TriStateBadgeCell` and `McpServersCell` are imported from `./cells/` (see T022a). `TriStateBadgeCell` renders: `Yes` (green `.acrossai-tri-badge--yes`) when `true`, `No` (red `.acrossai-tri-badge--no`) when `false`, `—` (muted) when `null`; when the value originates from the registry (`has_override === false` for that field), append a `(Default)` suffix in italics. Search debounced 500ms via `useDebounce( view.search, 500 )` from `@wordpress/compose`; perPage options [10, 20, 50, 100]; column visibility from view state; dispatches `fetchAbilities` when view changes; renders empty-state message when `total === 0` and not loading (FR-022). **Row actions deduplication**: The `toggle` action MUST have `isPrimary: true` so it renders inline; it MUST NOT also appear in the 3-dot kebab dropdown. To achieve this, build the `actions` array with toggle first, then pass only the non-primary subset to the dropdown: `const dropdownActions = actions.filter( a => !a.isPrimary )`. If the installed `@wordpress/dataviews` version natively omits primary actions from the dropdown, rely on that; otherwise explicitly filter. The acceptance check: **the 3-dot dropdown on each row MUST contain exactly `Edit` and (when `has_override === true`) `Reset Override` — never `Allow` or `Disallow`.**
- [x] T022a [P] [US1] Create `src/js/sitewide/components/cells/TriStateBadgeCell.jsx` and `src/js/sitewide/components/cells/McpServersCell.jsx` — small presentational components consumed by T022 field `render` callbacks. `TriStateBadgeCell` accepts `{ value, hasOverride, registryValue }` and renders Yes (green)/No (red)/— (muted) with a `(Default)` suffix in italics when the value originates from the registry (mirrors `StatusCell` pattern in `AbilityTable.jsx`). `McpServersCell` accepts `{ value, showInMcp }` and renders: `All` when `value` is null and `showInMcp === true`; comma-joined server IDs truncated at 3 with a `+N more` chip; `—` otherwise. Keeping these as separate files keeps `AbilityTable.jsx` lean and allows reuse if the edit panel adds inline previews later.
- [x] T023 [P] [US1] Create `src/scss/sitewide/admin.scss` with the following requirements: (1) **Full-page layout** — `.acrossai-abilities-manager` root MUST set `max-width: none; width: 100%; padding: 16px 20px 40px; box-sizing: border-box;` so the page fills the WP admin content area instead of hugging the left edge. `.acrossai-abilities-manager > h1` MUST match `.wp-heading-inline` conventions: `margin: 0 0 16px; font-size: 23px; font-weight: 400; line-height: 1.3;`. `.acrossai-abilities-table-wrap` MUST set `width: 100%` and wrap the DataViews surface in a card style: `background: #fff; border: 1px solid #c3c4c7; border-radius: 4px;`. (2) **Status badges** — `.acrossai-status-badge--allowed` (green `#dff0d8` / `#3a6b2e`), `.acrossai-status-badge--disallowed` (red `#f2dede` / `#9e3a3a`), `.acrossai-status-badge--default` suffix muted (`opacity: .7; font-style: italic;`). (3) **Tri-state badges** for new metadata columns — `.acrossai-tri-badge--yes` (green, reuse status-allowed tokens), `.acrossai-tri-badge--no` (red, reuse status-disallowed tokens), neutral `—` (muted grey `#72777c`). (4) **Drawer animation** — `transform: translateX(100%)` to `translateX(0)`, 300ms ease, applied to `.acrossai-ability-edit-panel`. (5) **Responsive** — at `max-width: 782px` (WP mobile breakpoint) reduce horizontal padding to `12px` so the table still fits. (6) WPDS design token references for spacing/color/typography where available.
- [x] T024 [US1] Add `fetchAbilities( viewParams )` async action creator to `src/js/sitewide/store/index.js` — dispatches `SET_LOADING`, calls `client.fetchAbilities()`, dispatches `SET_ABILITIES` with response `{ abilities, total, pages }`, dispatches `SET_ERROR` on failure; `useDispatch` consumer in `AbilityManager.jsx` calls this when view changes

**Checkpoint**: User Story 1 fully functional — admin can browse, search, sort, and paginate all registered abilities

---

## Phase 4: User Story 2 — Allow or Disallow an Ability Site-wide (Priority: P2)

**Goal**: Admin clicks Allow/Disallow in a table row; the change saves immediately without page reload and persists across reloads

**Independent Test**: Click Disallow on any ability row. Status changes to Disallowed immediately. Reload page — still Disallowed. Click Allow. Reload — shows Allowed. On network failure the status reverts and an inline error appears.

- [x] T025 [US2] Add `toggle_ability()` handler and `POST /sitewide/abilities/{slug}/toggle` route to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — route arg: `slug` (string, required, URL-encoded, sanitize via `AcrossAI_Sanitizer::sanitize_ability_slug( $slug )` before any use — SEC-01); body arg: `site_allowed` (bool, required, sanitized via `AcrossAI_Sanitizer::sanitize_tri_state()`); `toggle_ability()` validates ability exists via `wp_get_ability( $slug )`, returns 404 WP_Error if absent; call `AcrossAI_Ability_Source_Detector::detect( wp_get_ability( $slug ) )` and include result as `$fields['source']` (RF-04); calls `AcrossAI_Sitewide_Query::save_override()` with `[ 'site_allowed' => $site_allowed, 'source' => $source ]`; returns `{ slug, site_allowed, has_override }` per rest-api.md contract; fires `do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields )` passing sanitized `$fields` as second argument (SEC-02)
- [x] T026 [US2] Add `toggleAllowed( slug, siteAllowed )` async action creator to `src/js/sitewide/store/index.js` — performs optimistic update (immediately update ability in `abilities` array), calls `client.toggleAbility()`, on error rolls back to previous state and dispatches `SET_ERROR` with inline error message; on success updates the ability record in state with returned `site_allowed` and `has_override` values
- [x] T027 [US2] Add inline Allow/Disallow toggle button to `src/js/sitewide/components/AbilityTable.jsx` row actions — renders as a `Button` from `@wordpress/components`; label shows "Disallow" when ability is allowed, "Allow" when disallowed; on click dispatches `toggleAllowed`; shows per-row loading spinner during in-flight request; shows inline error notice on failure and reverts displayed status (SC-002: must complete within 1 s). Per T022 dedup rule, this inline button is the ONLY surface for Allow/Disallow — it MUST NOT also appear in the 3-dot kebab dropdown.

**Checkpoint**: User Stories 1 and 2 both independently functional

---

## Phase 5: User Story 3 — Edit Per-Ability Settings via Form (Priority: P3)

**Goal**: Admin opens a slide-in drawer for any ability, sees a two-tab DataForms panel (General + MCP), edits override values, and saves. Only changed fields are written to DB; "No changes made" shown when nothing differs from registry defaults.

**Independent Test**: Click Edit on any ability. Slide-in panel opens with General and MCP tabs. Change "Readonly" value. Save — "Settings saved" notice appears. Reopen — changed value persists, registry default shown alongside. Open Edit on a second ability, make no changes, click Save — "No changes made" notice, no DB write.

- [x] T028 [US3] Add `get_ability()`, `save_override()`, and `get_mcp_servers()` handlers and their routes to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — `GET /sitewide/abilities/{slug}`: validate slug, merge registry + override, return Effective Ability shape; `POST /sitewide/abilities/{slug}`: sanitize `$slug` URL parameter via `AcrossAI_Sanitizer::sanitize_ability_slug()` first (SEC-01); the `register_rest_route` call for this POST route MUST declare ALL 8 overridable fields explicitly in `args` — **missing args cause WordPress to return "Invalid parameter(s)" and reject the entire request**: `'site_allowed' => [ 'type' => [ 'boolean', 'null' ], 'required' => false ]`, `'readonly' => [ 'type' => [ 'boolean', 'null' ], 'required' => false ]`, `'destructive' => [ 'type' => [ 'boolean', 'null' ], 'required' => false ]`, `'idempotent' => [ 'type' => [ 'boolean', 'null' ], 'required' => false ]`, `'show_in_rest' => [ 'type' => [ 'boolean', 'null' ], 'required' => false ]`, `'show_in_mcp' => [ 'type' => [ 'boolean', 'null' ], 'required' => false ]`, `'mcp_type' => [ 'type' => [ 'string', 'null' ], 'enum' => [ 'tool', 'resource', 'prompt', null ], 'required' => false ]`, `'mcp_servers' => [ 'type' => [ 'array', 'null' ], 'items' => [ 'type' => 'string' ], 'required' => false ]`; for each tri-state field sanitize via `AcrossAI_Sanitizer::sanitize_tri_state()` preserving `null` as PHP `null` (Inherit = SQL NULL) and `false` as PHP `false` (No = 0) — NEVER conflate these two; sanitize `mcp_type` via `AcrossAI_Sanitizer::sanitize_mcp_type()` (null allowed); sanitize `mcp_servers` via `AcrossAI_Sanitizer::sanitize_mcp_servers_array()` (null allowed); call `AcrossAI_Ability_Source_Detector::detect()` and include as `$fields['source']` (RF-04); call `AcrossAI_Ability_Merger::is_all_default()`, return `unchanged: true` if no diff (FR-024/025), else call `AcrossAI_Sitewide_Query::save_override()`, if `is_all_default()` returns true AND an existing override record exists call `delete_override_by_slug()` (I3); fires `do_action( 'acrossai_abilities_sitewide_before_save', $slug, $fields )` and `do_action( 'acrossai_abilities_sitewide_after_save', $slug, $fields )` when a write/delete occurs (SEC-02); `GET /sitewide/mcp-servers`: guard with `class_exists( 'WP\\MCP\\Core\\McpAdapter' )`, return `McpAdapter::instance()->get_servers()` as `[{ id, label }]` or `[]`
- [x] T029 [US3] Add `openEditPanel( slug )`, `closeEditPanel()`, `saveOverride( slug, data )`, `fetchMcpServers()` async action creators to `src/js/sitewide/store/index.js`; `saveOverride` dispatches optimistic state update, calls `client.saveOverride()`, handles `unchanged: true` response by dispatching a `SAVE_UNCHANGED` signal and NOT updating state; on success dispatches updated ability into `abilities` array; `fetchMcpServers` sets `mcpServers` state
- [x] T030 [P] [US3] Create `src/js/sitewide/components/McpVisibilityControl.jsx` — renders MCP Visibility `RadioControl` from `@wordpress/components` with 4 options (Keep as Default / Disable for MCP / Allow in all MCP servers / Allow in specific MCP servers) per FR-017; conditionally renders MCP Type `SelectControl` (tool/resource/prompt) for options 3 and 4; conditionally renders server `CheckboxControl` multi-select for option 4 only using `mcpServers` from store; shows `Notice` ("No MCP servers configured") when option 4 is selected but server list is empty (FR-018). **Encoding to REST payload — explicit mapping to avoid "Invalid parameter(s)" errors**: the component MUST output `{ show_in_mcp, mcp_type, mcp_servers }` with these exact JS values: option 1 "Keep as Default" → `show_in_mcp: null, mcp_type: null, mcp_servers: null`; option 2 "Disable for MCP" → `show_in_mcp: false, mcp_type: null, mcp_servers: null`; option 3 "Allow in all MCP servers" → `show_in_mcp: true, mcp_type: <selected string|null>, mcp_servers: null`; option 4 "Allow in specific MCP servers" → `show_in_mcp: true, mcp_type: <selected string|null>, mcp_servers: <string[]>`. `mcp_type` MUST be one of `'tool'`/`'resource'`/`'prompt'` or JS `null` (never the string `"null"` or `undefined`). `mcp_servers` MUST be a non-empty string array or JS `null` (never `undefined` or `[]` — send `null` when no servers are selected, not an empty array, to satisfy `sanitize_mcp_servers_array`). The parent component (`AbilityEditPanel`) passes the current `mcp_type`/`mcp_servers`/`show_in_mcp` values and an `onChange` callback; `McpVisibilityControl` translates back from those three fields to the selected radio option on mount.
- [x] T031 [US3] Create `src/js/sitewide/components/AbilityEditPanel.jsx` — renders via `createPortal( content, document.body )` imported as `import { createPortal } from '@wordpress/element'` (I4); fixed-position slide-in drawer (`position: fixed; right: 0; top: 0; height: 100vh; width: 420px`) with CSS transform animation (300ms ease, translateX(100%) hidden → translateX(0) open). **Per-tab save architecture (key requirement)**: each tab manages its own independent local state and has its own "Save" + "Reset to Default" footer. Saving one tab MUST NOT affect or reset another tab's unsaved changes. The panel header has only a close (✕) button — there is NO panel-level Save button. Tab layout: "General" tab (site_allowed/readonly/destructive/idempotent/show_in_rest) with its own footer Save button; "MCP" tab (McpVisibilityControl + mcp_type) with its own footer Save button; future tabs will follow the same pattern. **Per-tab save behaviour**: on clicking a tab's Save button, collect only that tab's fields into the payload, call `saveOverride( slug, tabPayload )`, show "Settings saved" notice inline within the tab on success, "No changes made" if `unchanged: true`, inline error notice on failure — all notices appear inside the tab, not as page-level banners; the panel stays open after save so the user can continue editing other tabs. **Tab local state initialisation**: on panel open (or when `ability` prop changes), each tab initialises its own local draft from the current `ability` values; tabs are independent — switching tabs MUST NOT discard unsaved draft changes in the tab you left. **Close behaviour**: clicking ✕ or pressing Escape closes the panel; if any tab has unsaved draft changes (draft !== saved), show a browser `confirm()` before discarding. **Tri-state field rendering (readonly, destructive, idempotent, show_in_rest, site_allowed)**: each MUST render as a `RadioControl` with exactly THREE options: `{ label: 'Yes', value: 'true' }`, `{ label: 'No', value: 'false' }`, `{ label: 'Inherit (use ability default)', value: 'null' }`. RadioControl value MUST be a string; convert on save: `'true'` → JS `true`, `'false'` → JS `false`, `'null'` → JS `null`. Convert on read: `true` → `'true'`, `false` → `'false'`, `null`/undefined → `'null'`. **Never use `Boolean()` or `!!`** — collapses null and false into the same falsy value. Shows each field's registry default as a read-only reference label beneath the radio group (FR-013).
- [x] T032 [US3] Update `src/js/sitewide/components/AbilityTable.jsx` — add "Edit" action to row actions that dispatches `openEditPanel( ability.slug )` via `useDispatch`; calls `fetchMcpServers()` on first edit panel open (lazy load). The Edit action MUST live ONLY in the 3-dot kebab dropdown — never as an inline primary button.
- [x] T033 [US3] Update `src/js/sitewide/components/AbilityManager.jsx` — conditionally render `AbilityEditPanel` when `editingSlug` is non-null (from `getEditingSlug` selector); pass `editingSlug`, `onClose = () => dispatch( closeEditPanel() )`, and the full ability object from `getAbilities` to the panel

**Checkpoint**: User Stories 1, 2, and 3 all independently functional

---

## Phase 6: User Story 4 — Reset Override to Registry Defaults (Priority: P4)

**Goal**: Admin clicks Reset Override in a row action menu; all stored override fields are deleted and the ability reverts to registry defaults

**Independent Test**: Save any override. Click Reset Override. Reload. Ability shows registry default values with "(Default)" indicator. Open edit panel — all fields show null/default. For an ability with no override, Reset is disabled/hidden.

- [x] T034 [US4] Add `delete_override()` handler and `DELETE /sitewide/abilities/{slug}` route to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — sanitize `$slug` URL parameter via `AcrossAI_Sanitizer::sanitize_ability_slug()` first (SEC-01); validate slug exists in registry; call `AcrossAI_Sitewide_Query::delete_override_by_slug()`; return `{ slug, deleted: true }` if a record was deleted or `{ slug, deleted: false, message: '...' }` if no override existed (per rest-api.md contract)
- [x] T035 [US4] Add `deleteOverride( slug )` async action creator to `src/js/sitewide/store/index.js` — optimistic update (set `has_override: false`, clear all non-null override fields back to registry defaults in the local abilities array), call `client.deleteOverride()`, roll back on error
- [x] T036 [US4] Add Reset Override row action to `src/js/sitewide/components/AbilityTable.jsx` — visible in row action menu only when `ability.has_override === true`; when `has_override` is false the action is either hidden or rendered as disabled with tooltip "No override saved" (FR — spec US4 scenario 2); on click dispatches `deleteOverride( ability.slug )`. Reset Override MUST live ONLY in the 3-dot kebab dropdown, gated on `has_override === true`.

**Checkpoint**: User Stories 1–4 all independently functional

---

## Phase 7: User Story 5 — Bulk Allow, Disallow, or Reset Multiple Abilities (Priority: P5)

**Goal**: Admin selects multiple ability rows via checkboxes and applies a bulk Allow, Disallow, or Reset action to all selected at once

**Independent Test**: Select 3 ability rows. Click Bulk Disallow — all 3 show Disallowed, selection cleared. Select same 3. Click Bulk Reset — all 3 revert to defaults. Partial failure shows a summary: N succeeded, M failed.

- [x] T037 [US5] Add `bulk_action()` handler and `POST /sitewide/abilities/bulk` route to `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` — body args: `slugs` (array of strings, max 100, each sanitized), `action` (enum: allow/disallow/reset); validate max 100 slugs; iterate each slug: skip unknown slugs (record in `skipped`); reject unknown `action` values immediately with `WP_Error` 400 before processing (SEC-01); each `slug` in `slugs` is already individually sanitized — also sanitize via `AcrossAI_Sanitizer::sanitize_ability_slug()`; apply action: allow/disallow → call `AcrossAI_Ability_Source_Detector::detect( wp_get_ability( $slug ) )` and include as `$fields['source']` (RF-04) → `save_override()` with `[ 'site_allowed' => ..., 'source' => $source ]`; reset → `delete_override_by_slug()`; return `{ succeeded: int, failed: int, skipped: string[], results: [{slug, status}] }` (FR-012, spec US5 scenario 3)
- [x] T038 [US5] Add `bulkAction( slugs, action )` async action creator to `src/js/sitewide/store/index.js` — dispatches `SET_LOADING`, calls `client.bulkAction()`, dispatches `SET_ABILITIES` slice update for each affected slug based on returned results, dispatches `SET_ERROR` with summary message on partial failure
- [x] T039 [US5] Create `src/js/sitewide/components/BulkActionToolbar.jsx` — renders "Bulk Allow", "Bulk Disallow", "Bulk Reset" `Button` components; all three disabled (and aria-disabled) when `selectedSlugs.length === 0` (spec US5 scenario 4); shows `selectedSlugs.length` selected count label; on click dispatches `bulkAction( selectedSlugs, action )`; shows `Notice` with partial-success summary when `failed > 0`
- [x] T040 [US5] Integrate `BulkActionToolbar` into `src/js/sitewide/components/AbilityManager.jsx` — wire DataViews `selection` state (controlled); pass `selectedSlugs` to `BulkActionToolbar`; clear selection after bulk action completes; `BulkActionToolbar` renders above the `AbilityTable`

**Checkpoint**: All 5 user stories fully functional and independently testable

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Tests, static analysis, validation, and edge-case hardening across all stories

- [x] T041 [P] Create `tests/phpunit/sitewide/RestControllerTest.php` — PHPUnit tests for: permission check (non-admin returns 403, missing nonce returns 403), response shape of GET /sitewide/abilities (correct fields, pagination headers), GET /sitewide/abilities/{slug} 404 for unknown slug, POST save returns `unchanged: true` when payload matches registry, DELETE returns `deleted: false` when no override exists
- [x] T042 [P] Create `tests/phpunit/sitewide/SitewideQueryTest.php` — PHPUnit tests for: `save_override()` creates new record with correct fields, `save_override()` updates existing record (upsert), `delete_override_by_slug()` deletes existing and returns true, `delete_override_by_slug()` returns false when no record, `get_override_by_slug()` returns null for unknown slug, NULL column semantics verified
- [x] T043 [P] Create `tests/phpunit/sitewide/AbilityMergerTest.php` — PHPUnit tests for: `merge()` returns registry value when override field is null, `merge()` override field wins when non-null, `is_all_default()` returns true when all payload fields equal registry defaults, `is_all_default()` returns false when at least one field differs, MCP "Keep as Default" sets both `show_in_mcp` and `mcp_servers` to null
- [x] T044 [P] Create `tests/jest/sitewide/AbilityManager.test.js` — Jest tests for: Redux store initial state shape, `fetchAbilities` dispatches SET_LOADING then SET_ABILITIES, `toggleAllowed` optimistic update and rollback on error, `saveOverride` with `unchanged: true` response triggers no state mutation, `AbilityTable` renders empty-state when abilities array is empty
- [x] T045 Run PHPCS with WordPress standard against `includes/Modules/Sitewide/`, `includes/Utilities/`, `includes/Base/` — resolve all errors and warnings; confirm all functions and classes prefixed with `acrossai_` / `AcrossAI_`
- [x] T046 Run PHPStan level 8 against `includes/Modules/Sitewide/`, `includes/Utilities/`, `includes/Base/` — resolve all type errors; confirm no unsafe null dereferences; confirm `::class` constants used on BerlinDB properties
- [x] T047 Harden edge cases across all PHP and JS: (1) `wp_get_abilities()` returns zero abilities → empty-state message shown, no table rendered (FR-022); (2) ability deregistered after override exists → override row silently remains in DB, absent ability does not appear in table (registry is source of truth); (3) bulk action on 100+ items → loading indicator shown, table refreshes after completion; (4) MCP adapter absent → server multi-select hidden, admin notice shown per FR-018; (5) all-null override record after "Keep as Default" save → entire record deleted per FR-017 / research Decision 11
- [x] T048 Run quickstart.md validation: `npm run build` (no errors, `build/js/sitewide.js` and `build/css/sitewide.css` emitted), plugin activate (table created via `maybe_upgrade()`), navigate to `/wp-admin/admin.php?page=acrossai-abilities-manager` and confirm React app mounts and ability table renders; run `wp rest route list | grep acrossai` to confirm all 7 routes registered; confirm page load < 2 s (SC-001); manually time the Allow/Disallow toggle: click → status update must complete within 1 s (SC-002); apply bulk Disallow to 10+ abilities and confirm all complete within 5 s total (SC-007). Additional UI acceptance checks: (i) the table renders **10 visible columns by default** (`slug`, `provider`, `source`, `status`, `destructive`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `updated_at`) and the column-visibility menu exposes `readonly` and `idempotent` as available-but-hidden toggles; (ii) the 3-dot row dropdown shows ONLY `Edit` and (when override exists) `Reset Override` — never `Allow` or `Disallow`; (iii) the page fills the WP admin content area (no left-edge crush; the React app root is wrapped in `<div class="wrap acrossai-abilities-manager-wrap">`).

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
