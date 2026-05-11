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
- PHP: `berlindb/core ^2.0` (prefixed via Mozart to `AcrossAI_Abilities_Manager\\Vendor\\BerlinDB\\`), `automattic/jetpack-autoloader`
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
Module lives at `includes/modules/sitewide/`, extends `AcrossAI_Module_Base` from `includes/base/`.
Shared utilities in `includes/utilities/`. No sibling-module dependencies.

### ✅ PASS — II. WordPress Standards Compliance
PHPCS strict + PHPStan L8 gates enforced in Definition of Done. WP 6.9+ / PHP 7.4+ targeted.
No deprecated WP functions used.

### ✅ PASS — III. User-Centric Design (NON-NEGOTIABLE)
DataViews (`@wordpress/dataviews`) used for the ability table (search, sort, pagination, filter, column visibility).
DataForms (`@wordpress/dataforms`) used for edit panel field rendering and submission state.
**Constitution override applied**: The user arguments suggested `@wordpress/components Modal`. This is
**prohibited** by spec FR-021 (slide-in drawer, non-blocking) and constitution Principle III (DataForms
for forms, not Modal). The edit panel MUST use `ReactDOM.createPortal` slide-in drawer pattern.

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
1. `includes/features/sitewide/` → corrected to `includes/modules/sitewide/` (constitution directory layout)
2. `@wordpress/components Modal` for edit form → corrected to slide-in drawer via `createPortal` (FR-021 + constitution)
3. Store name `acrossai-abilities-sitewide` → corrected to `acrossai-abilities/sitewide` (WP convention: slash-namespaced)
4. `admin/Partials/Menu.php` must be updated in-place — no new menu class may be created (clarification C5)

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
admin/
└── Partials/
    └── Menu.php                           # UPDATE IN-PLACE: add icon, position, React root in contents()

includes/
├── base/
│   └── AcrossAI_Module_Base.php           # CREATE: abstract module base class
├── utilities/
│   ├── AcrossAI_Sanitizer.php             # CREATE: sanitize_ability_slug(), sanitize_tri_state(), etc.
│   └── AcrossAI_Ability_Merger.php        # CREATE: static merge(registry, override): array
├── modules/
│   └── sitewide/
│       ├── index.php                      # CREATE: directory sentinel
│       ├── AcrossAI_Sitewide_Module.php   # CREATE: extends Module_Base, boots all hooks
│       ├── AcrossAI_Sitewide_Rest_Controller.php  # CREATE: 7 REST endpoints
│       ├── database/
│       │   ├── AcrossAI_Sitewide_Schema.php  # CREATE: BerlinDB Schema (17 columns)
│       │   ├── AcrossAI_Sitewide_Table.php   # CREATE: BerlinDB Table (maybe_upgrade)
│       │   ├── AcrossAI_Sitewide_Row.php     # CREATE: BerlinDB Row (typed properties)
│       │   └── AcrossAI_Sitewide_Query.php   # CREATE: BerlinDB Query (CRUD methods)
│       └── templates/
│           └── admin-page.php             # CREATE: renders React root div
├── Activator.php                          # UPDATE: call table->maybe_upgrade() on activate
└── Main.php                               # UPDATE: instantiate and boot Sitewide_Module

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
│           ├── AbilityTable.jsx           # CREATE: @wordpress/dataviews DataViews table
│           ├── AbilityEditPanel.jsx       # CREATE: slide-in drawer via createPortal + DataForms
│           ├── McpVisibilityControl.jsx   # CREATE: MCP radio group + conditional server multi-select
│           └── BulkActionToolbar.jsx      # CREATE: bulk allow/disallow/reset toolbar
└── scss/
    └── sitewide/
        └── admin.scss                     # CREATE: drawer animation, status badges, WPDS tokens

build/                                     # OUTPUT: compiled by @wordpress/scripts
├── js/
│   └── sitewide.js
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
        └── AbilityManager.test.js         # CREATE: component + store unit tests
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
