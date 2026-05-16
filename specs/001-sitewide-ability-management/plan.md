# Implementation Plan: Sitewide Ability Management

**Branch**: `001-sitewide-ability-management` | **Date**: 2026-05-11 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/001-sitewide-ability-management/spec.md`

---

## Summary

Build a WordPress admin interface that lets site administrators view, search, sort, filter, and
override the metadata of every ability registered via the WordPress Abilities API (`wp_get_ability()`).
Overrides are stored in a dedicated `{prefix}acrossai_abilities_overwrite` table via BerlinDB;
the registry is the source of truth — only fields that differ from registry defaults are persisted.
The admin UI is a React-powered DataViews table with a slide-in DataForms drawer for per-ability editing.
All endpoints are secured with `manage_options` + nonce verification.

---

## Technical Context

**Language/Version**: PHP 7.4+, JavaScript ESNext/JSX, WordPress 6.9+
**Primary Dependencies**:
- PHP: `berlindb/core ^2.0` (used directly at `BerlinDB\Database\*` — no Mozart prefixing), `automattic/jetpack-autoloader`, `wpboilerplate/wpb-mcp-servers-list` (MCP server listing — encapsulates McpAdapter timing and serialization)
- JS: `@wordpress/dataviews`, `@wordpress/data` (createReduxStore), `@wordpress/api-fetch`, `@wordpress/components`, `@wordpress/element`, `@wordpress/i18n`, `@wordpress/icons`, `@wordpress/compose`
- Build: `@wordpress/scripts` (webpack)
**Storage**: MySQL — `{prefix}acrossai_abilities_overwrite` via BerlinDB Table/Query/Row/Schema classes
**Testing**: PHPUnit (PHP unit + integration), Jest (JS unit)
**Target Platform**: WordPress wp-admin (single-site and multisite compatible)
**Project Type**: WordPress plugin feature module
**Performance Goals**: Page load < 2 s; toggle < 1 s; search (500 ms debounce + render) < 600 ms; bulk (50 items) < 5 s
**Constraints**: `manage_options` required everywhere; override-only storage (no registry duplication); PHP 7.4 compatible (no `match`, no named args, no `readonly` properties); multisite-compatible
**Scale/Scope**: Up to 500 registered abilities per site; up to 100 abilities per page; max 100 per_page API limit

---

## Constitution Check

*Gates MUST pass before implementation proceeds. Violations are flagged here.*

### ✅ PASS — I. Modular Architecture
Module lives at `includes/Modules/Sitewide/`. No `AcrossAI_Module_Base` abstract class; no `register_hooks()` delegation — all hooks wired directly in `includes/Main.php::define_admin_hooks()` via the Loader singleton.
Feature classes (`AcrossAI_Sitewide_Rest_Controller`, `AcrossAI_Sitewide_Table`, `AcrossAI_Sitewide_Query`) use the plugin-wide singleton `instance()` pattern.
Shared utilities in `includes/Utilities/`. Admin enqueue in `admin/Main.php`; page render (`contents()`) in `admin/Partials/Menu.php`. No separate `SitewideAbilityPage` class. No `AcrossAI_Sitewide_Module` orchestrator. No sibling-module dependencies.

### ✅ PASS — II. WordPress Standards Compliance
PHPCS strict + PHPStan L8 gates enforced in Definition of Done. WP 6.9+ / PHP 7.4+ targeted.
No deprecated WP functions used.

### ✅ PASS — III. User-Centric Design (NON-NEGOTIABLE)
DataViews (`@wordpress/dataviews`) used for the ability table ✅
DataForms: `DataForm` from `@wordpress/dataviews` used for General tab field rendering ✅
**Constitution override applied**: The user arguments suggested `@wordpress/components Modal`. This is
**prohibited** by spec FR-021 (slide-in drawer, non-blocking) and constitution Principle III (DataForms
for forms, not Modal). The edit panel MUST use `ReactDOM.createPortal` slide-in drawer pattern.
> **Note (2026-05-16)**: Initial implementation used bare `RadioControl`. **RT-01 remediated (2026-05-16)**:
> General tab migrated to `DataForm` from `@wordpress/dataviews` with `TriStateEditField` custom `Edit`
> adapter. `McpVisibilityControl.jsx` is a **justified exception**: its 4-state compound control encodes
> 3 interdependent fields with complex conditional rendering that cannot map to independent DataForm fields.

### ✅ PASS — IV. Security First (NON-NEGOTIABLE)
Nonces on all mutating endpoints. `manage_options` in every `permission_callback` AND handler.
BerlinDB internal query builder (wraps `$wpdb->prepare()`). All input sanitized at REST boundary.
All output escaped via `rest_ensure_response()` + `esc_html()` / `esc_attr()`.

### ✅ PASS — V. Extensibility Without Core Modification
MCP adapter guarded with `class_exists('WP\\MCP\\Core\\McpAdapter')`. Absent = empty server list + admin notice.
New hooks exposed: `acrossai_abilities_sitewide_before_save`, `acrossai_abilities_sitewide_after_save`,
`acrossai_abilities_sitewide_rest_response`.

### ✅ PASS — VI. Reusability & DRY
`AcrossAI_Sanitizer` (input sanitization), `AcrossAI_Ability_Merger` (registry + override merge) go in
`includes/utilities/`. Checked that no duplicates exist in current boilerplate (none found). JS API
client centralised in `src/js/sitewide/api/client.js`. Redux store in `src/js/sitewide/store/index.js`.

### ✅ PASS — VII. Definition of Done
All gates listed in tasks.md. Feature is complete only when all 8 DoD gates pass.

**⚠️ CORRECTED VIOLATIONS in submitted arguments (resolved in this plan)**:
1. `includes/features/sitewide/` → corrected to `includes/Modules/Sitewide/` (PSR-4 autoloader casing + constitution directory layout)
2. `@wordpress/components Modal` for edit form → corrected to slide-in drawer via `createPortal` (FR-021 + constitution)
3. Store name `acrossai-abilities-sitewide` → corrected to `acrossai-abilities/sitewide` (WP convention: slash-namespaced)
4. `admin/Partials/Menu.php` must be updated in-place — no new menu class may be created (clarification C5)
5. `includes/modules/sitewide/` → corrected to `includes/Modules/Sitewide/` (PSR-4 autoloader: lowercase dirs fail on case-sensitive Linux filesystems)
6. Admin enqueue + page template inside `AcrossAI_Sitewide_Module` → enqueue moved to `admin/Main.php::enqueue_styles()/enqueue_scripts()` (skill step 3–4: manifest loaded in constructor, scoped enqueue in the two methods); render moved to `admin/Partials/Menu.php::contents()` (the `add_menu_page` callback); `admin/Partials/SitewideAbilityPage.php` was created and then deleted — its responsibilities are split between `Admin\Main` and `Menu`
7. Webpack entry + `sitewide.asset.php` manifest loading added explicitly (skill step 12 — dependency array and version must never be hardcoded)
8. `boot()` call from `load_dependencies()` → clarified: `define_admin_hooks()` wires all hooks directly via `$this->loader->add_action()`; no module `register_hooks()` delegation
9. **PHP bool-to-int cast in `save_override()`** — PHP `false` is not detected as an integer by `$wpdb` format auto-detection (`is_int(false) === false`), so `$wpdb` assigns `%s`, which produces `''` on `sprintf`. MySQL 8+ strict mode rejects `''` for a `tinyint` column silently. Fix: cast all PHP boolean tri-state values to `(int)` before passing to BerlinDB — `true → 1`, `false → 0`, `null` left as null. Applied in `AcrossAI_Sitewide_Query::save_override()`.
10. **Partial-field save via `has_param()` in REST controller** — the per-tab save architecture (General tab / MCP tab each save independently) requires the PHP handler to only write fields that were explicitly sent. `$request->get_param()` returns `null` for absent optional params, so unconditional collection of all 8 fields overwrites the other tab's DB values with NULL silently. Fix: use `$request->has_param($field)` to gate collection; `has_param()` returns `true` even when the field is explicitly sent as `null` (user intends to clear it). Applied in `AcrossAI_Sitewide_Rest_Controller::save_override()`. **`is_all_default()` guard must be gated on `!$existing`** — the original rule compared only against registry defaults, so if the DB had `show_in_mcp: true` and the user chose "Keep as Default" (sending null), `is_all_default()` returned true (null == registry null) and skipped the write, leaving the old value intact. Fix: only return `unchanged: true` when there is no existing DB row AND all submitted fields match registry defaults — `if ( ! $existing && AcrossAI_Ability_Merger::is_all_default( $fields, $registry ) )`. If a row already exists, always write so explicit nulls reach the DB. Full-row cleanup (deleting the row when appropriate) still belongs to the DELETE endpoint only.
11. **`useEffect([slug])` dep in `AbilityEditPanel`** — using `[ability]` as the `useEffect` dep re-seeds the draft on every `UPDATE_ABILITY` dispatch (which fires after every save). Any timing gap where `ability._override` arrives as `null` before the component re-reads it silently resets the user's selection back to "Inherit". Fix: use `[slug]` as the dep so the draft only re-seeds when the panel opens for a different ability. Individual save handlers (`saveGeneral`, `saveMcp`) own updating `savedDraft` state via `setGeneralSaved({...generalDraft})` / `setMcpSaved({...mcpDraft})`.
12. **`_override: nullOverride` in `deleteOverride` optimistic dispatch** — the `UPDATE_ABILITY` reducer does a shallow spread (`{ ...ability, ...action.ability }`). Without explicitly setting `_override` in the dispatch payload, the old stale `_override` survives in the store. When the edit panel opens (slug-change `useEffect`), it seeds draft state from the stale `_override` and shows Yes/No instead of Inherit after Reset Override. Fix: include `_override: { site_allowed: null, readonly: null, ... }` (all 8 fields null) in the `deleteOverride` optimistic dispatch.
13. **Singleton pattern + hook centralization** — the original plan used an `AcrossAI_Module_Base` abstract class with `register_hooks( Loader $loader )` delegation. This is not the plugin-wide convention: WPBoilerplate requires all `$loader->add_action()` calls to originate directly in `includes/Main.php::define_admin_hooks()` / `define_public_hooks()`. The module base and module orchestrator classes (`AcrossAI_Module_Base`, `AcrossAI_Sitewide_Module`) are deleted (T004, T014). All feature classes instead use a `protected static $_instance = null` + `public static function instance(): self` singleton; `includes/Main.php` instantiates them via `::instance()` and wires their hooks directly through `$this->loader`. This keeps the hook registry centralized, auditable, and consistent with every other class in the plugin.
14. **`McpVisibilityControl` radio snap-back + server list hidden** — a `useEffect([showInMcp, mcpServers])` was added to re-sync `radioSelection` when the panel opened for a different ability. It also fired immediately after the user clicked "Allow in specific MCP servers" because `onChange` updated `mcpDraft.show_in_mcp → true` while `mcpDraft.mcp_servers` stayed `null`, causing `toRadioOption(true, null)` to return `'all'` and overwrite the user's selection. Since `showSpecificServers` was derived from `radioSelection`, the server list never appeared. Fix: remove the `useEffect` from `McpVisibilityControl` entirely and instead pass `key={slug}` from `AbilityEditPanel` — React unmounts and remounts the component when the slug changes, re-running `useState` from fresh props, which is the correct re-init mechanism without the snap-back side effect.

---

## Project Structure

### Documentation (this feature)

```text
specs/001-sitewide-ability-management/
├── plan.md              # This file
├── research.md          # Phase 0 — decisions and rationale
├── data-model.md        # Phase 1 — DB schema, PHP entities, JS state shape
├── contracts/
│   └── rest-api.md      # Phase 1 — REST endpoint contracts
├── quickstart.md        # Phase 1 — developer setup guide
├── checklists/
│   └── requirements.md  # Spec quality checklist
└── tasks.md             # Phase 2 — ordered implementation tasks (not yet created)
```

### Source Code (repository root)

```text
webpack.config.js                          # UPDATE: add entry 'sitewide' → './src/js/sitewide/index.js'

admin/
├── Main.php                               # UPDATE: constructor loads sitewide.asset.php; enqueue_styles()/enqueue_scripts() scoped to plugin page via $hook_suffix guard; wp_add_inline_script sets window.acrossaiAbilitiesSitewide
└── Partials/
    └── Menu.php                           # UPDATE IN-PLACE: add icon, position; contents() renders React root directly

includes/
├── Utilities/
│   ├── AcrossAI_Sanitizer.php             # CREATE: sanitize_ability_slug(), sanitize_tri_state(), etc.
│   ├── AcrossAI_Ability_Merger.php        # CREATE: static merge(registry, override): array
│   ├── AcrossAI_Ability_Registry_Query.php # CREATE: filter/sort/paginate over wp_get_abilities()
│   └── AcrossAI_Ability_Source_Detector.php # CREATE: source detection before save_override()
├── Modules/
│   └── Sitewide/
│       ├── index.php                      # CREATE: directory sentinel
│       ├── AcrossAI_Sitewide_Rest_Controller.php  # UPDATED (spec 002): thin orchestrator; delegates routes to Rest/ sub-controllers; owns REST_NAMESPACE + check_permission()
│       ├── Rest/                          # CREATED (spec 002): per-domain sub-controllers
│       │   ├── index.php                 # directory sentinel
│       │   ├── AcrossAI_Sitewide_Abilities_Controller.php  # GET /abilities, GET /abilities/{slug}
│       │   ├── AcrossAI_Sitewide_Override_Controller.php   # POST/DELETE /abilities/{slug}, POST .../toggle
│       │   ├── AcrossAI_Sitewide_Bulk_Controller.php       # POST /abilities/bulk
│       │   └── AcrossAI_Sitewide_Mcp_Controller.php        # GET /mcp-servers (uses wpboilerplate/wpb-mcp-servers-list)
│       └── Database/
│           ├── AcrossAI_Sitewide_Schema.php  # CREATE: BerlinDB Schema (17 columns) — used by Query for column metadata only; NOT referenced in set_schema()
│           ├── AcrossAI_Sitewide_Table.php   # CREATE: BerlinDB Table (set_schema = raw SQL string, not Schema::class); $db_version_key = 'acrossai_abilities_overwrite_db_version'; singleton pattern
│           ├── AcrossAI_Sitewide_Row.php     # CREATE: BerlinDB Row (typed properties)
│           └── AcrossAI_Sitewide_Query.php   # CREATE: BerlinDB Query (CRUD methods); singleton pattern
├── Activator.php                          # UPDATE: call AcrossAI_Sitewide_Table::instance()->maybe_upgrade() on activate
└── Main.php                               # UPDATE: define_admin_hooks() wires Admin\Main + Menu + resolves $rest_controller = AcrossAI_Sitewide_Rest_Controller::instance(); $this->loader->add_action('rest_api_init', $rest_controller, 'register_routes'); also wires $mcp_servers_list = McpServersList::instance(); $this->loader->add_action('rest_api_init', $mcp_servers_list, 'collect', 20) — NO module orchestrator, NO inline ::instance() as hook object

src/
├── js/
│   └── sitewide/
│       ├── index.js                       # CREATE: React entry point, createRoot, apiFetch setup
│       ├── api/
│       │   └── client.js                  # CREATE: @wordpress/api-fetch wrappers
│       ├── store/
│       │   └── index.js                   # CREATE: createReduxStore('acrossai-abilities/sitewide')
│       └── components/
│           ├── AbilityManager.jsx         # CREATE: page root, view state, localStorage
│           ├── AbilityTable.jsx           # CREATE: @wordpress/dataviews DataViews table (13 fields)
│           ├── AbilityEditPanel.jsx       # CREATE: slide-in drawer via createPortal; per-tab save; useEffect([slug]) dep; pass key={slug} to McpVisibilityControl so it remounts on ability change
│           ├── McpVisibilityControl.jsx   # CREATE: MCP radio group + conditional server multi-select; useState for radio state (NO useEffect([showInMcp,mcpServers]) — fires after onChange and snaps radio back to 'all'); remounted via key={slug} from AbilityEditPanel
│           ├── BulkActionToolbar.jsx      # CREATE: bulk allow/disallow/reset toolbar
│           └── cells/
│               ├── TriStateBadgeCell.jsx  # CREATE: Yes/No/— badge with (Default) suffix
│               └── McpServersCell.jsx     # CREATE: All / truncated server list / — renderer
└── scss/
    └── sitewide/
        └── admin.scss                     # CREATE: drawer animation, status badges, WPDS tokens

build/                                     # OUTPUT: compiled by @wordpress/scripts
├── js/
│   ├── sitewide.js
│   └── sitewide.asset.php                 # AUTO-GENERATED: loaded in Admin\Main::__construct(); consumed by enqueue_styles()/enqueue_scripts()
└── css/
    └── sitewide.css

tests/
├── phpunit/
│   └── sitewide/
│       ├── RestControllerTest.php         # CREATE: endpoint security + response shape
│       ├── SitewideQueryTest.php          # CREATE: BerlinDB CRUD + NULL override logic
│       └── AbilityMergerTest.php          # CREATE: merge() + is_all_default()
└── jest/
    └── sitewide/
        └── store.test.js                  # CREATE: component + store unit tests
```

---

## Phase 0: Research Decisions

See [research.md](research.md) for full rationale. Key decisions:

| Decision | Choice | Rationale |
|---|---|---|
| DB abstraction | BerlinDB `^2.0` (Mozart-prefixed) | Native WordPress, avoids version conflicts |
| Edit UI pattern | Slide-in drawer via `createPortal` | FR-021 prohibits blocking modal; constitution requires DataForms |
| Form rendering | `@wordpress/dataforms` DataForms | Constitution Principle III (non-negotiable) |
| State management | `createReduxStore` + `register()` | Lighter than full `@wordpress/data` registry for single feature |
| Search debounce | `useDebounce(search, 500)` from `@wordpress/compose` | FR-006: 500 ms inactivity |
| Column visibility | `localStorage` per `acrossai_ability_table_view_{userId}` | FR-009: browser-local, no server round-trip |
| MCP availability | `class_exists('WP\\MCP\\Core\\McpAdapter')` guard | Constitution V: graceful degradation |
| Bulk scope | Current page only | Q3 clarification: current-page selection (FR-012) |
| NULL status display | Registry default + "Default" indicator | Q1 clarification: FR-002, FR-026 |
| "Keep as Default" MCP | Nullify `show_in_mcp` + `mcp_servers` fields | Q4 clarification: FR-017, consistent with FR-024 |

---

## Phase 1: Design Outputs

- **[data-model.md](data-model.md)** — DB schema, PHP class hierarchy, JS state shape, merge algorithm
- **[contracts/rest-api.md](contracts/rest-api.md)** — Full REST API contracts (7 endpoints)
- **[quickstart.md](quickstart.md)** — Developer setup and build instructions
