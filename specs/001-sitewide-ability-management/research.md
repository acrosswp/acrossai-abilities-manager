# Research: Sitewide Ability Management

**Phase**: 0 | **Date**: 2026-05-11 | **Plan**: [plan.md](plan.md)

All decisions below were resolved from the user arguments, spec clarifications, and constitution.
No NEEDS CLARIFICATION items remain.

---

## Decision 1: Database Abstraction Layer

**Decision**: BerlinDB `^2.0` via Composer, Mozart-prefixed to `AcrossAI_Abilities_Manager\\Vendor\\BerlinDB\\`

**Rationale**:
- Provides a typed, testable ORM layer over `$wpdb` that wraps `$wpdb->prepare()` — satisfies constitution IV
- `Table::maybe_upgrade()` handles schema migrations without raw ALTER TABLE calls
- Row class provides typed property access; Query class provides pagination-aware `get_items()`
- Mozart prefixing prevents version conflicts with other plugins shipping BerlinDB

**Alternatives considered**:
- Raw `$wpdb` — rejected: no schema migration management, more boilerplate, higher risk of SQL injection
- WordPress meta API — rejected: ability overrides are relational data with their own audit columns, not key-value pairs

**Critical pattern**: `$table_schema` and `$item_shape` in Query class MUST use `::class` constants, not bare string class names. Bare strings cause fatal errors under PSR-4 autoloading.

```php
protected $table_schema = AcrossAI_Sitewide_Schema::class; // CORRECT
protected $item_shape   = AcrossAI_Sitewide_Row::class;    // CORRECT
// NOT: protected $table_schema = 'AcrossAI_Sitewide_Schema'; // WRONG
```

---

## Decision 2: Edit Panel UI Pattern

**Decision**: Slide-in drawer rendered via `ReactDOM.createPortal(content, document.body)`

**Rationale**:
- Spec FR-021 explicitly prohibits a blocking modal/dialog
- Slide-in drawer lets admins reference the table while the panel is open (non-blocking)
- `createPortal` mounts outside the DataViews container, preventing z-index stacking issues
- CSS `position: fixed; right: 0; top: 0; height: 100vh; width: 420px` + transform transition

**Alternatives considered**:
- `@wordpress/components Modal` — rejected: blocked by FR-021 (blocking dialog)
- Inline expand row — rejected: breaks DataViews layout, inaccessible on mobile

**Animation**: `transform: translateX(100%)` at mount → `translateX(0)` when open; 300ms ease transition

---

## Decision 3: Form Rendering in Edit Panel

**Decision**: `@wordpress/dataforms` DataForms component for all edit panel fields

**Rationale**:
- Constitution Principle III (NON-NEGOTIABLE): DataForms MUST be used for all admin form UIs
- Provides built-in field validation, inline error display, and submission state feedback out of the box
- Consistent with WordPress core admin UI patterns

**Alternatives considered**:
- Custom `@wordpress/components` controls (TextControl, SelectControl individually) — rejected: duplicates DataForms functionality, violates constitution

---

## Decision 4: State Management

**Decision**: `createReduxStore('acrossai-abilities/sitewide')` registered via `@wordpress/data`

**Rationale**:
- Slash-namespaced store name follows WordPress core convention (e.g., `core/editor`)
- `createReduxStore` + `register()` is lighter than `registerStore()` (deprecated in WP 6.x)
- Selectors: `getAbilities`, `getTotal`, `getPages`, `getCurrentPage`, `isLoading`, `getError`, `getEditingSlug`, `getMcpServers`
- Actions: `fetchAbilities`, `openEditPanel`, `closeEditPanel`, `saveOverride`, `deleteOverride`, `toggleAllowed`, `bulkAction`, `fetchMcpServers`

**Alternatives considered**:
- Local component state only — rejected: cross-component sharing (table ↔ drawer ↔ toolbar) requires lifted state or context; store is cleaner
- `@wordpress/redux-routine` — rejected: no async thunks needed; plain reducer with side-effects in action creators is sufficient

---

## Decision 5: Search Debounce

**Decision**: `useDebounce(view.search, 500)` from `@wordpress/compose`

**Rationale**:
- FR-006: table MUST NOT update more than once per 500 ms of inactivity
- `useDebounce` from `@wordpress/compose` is the idiomatic WP package approach
- SC-003: search results within 600 ms total (500 ms debounce + up to 100 ms render)

---

## Decision 6: Column Visibility Persistence

**Decision**: `localStorage` key `acrossai_ability_table_view_{userId}`

**Rationale**:
- FR-009: must persist across browser sessions, NOT server-side (no database write)
- Per-user keyed to avoid one admin's view overwriting another's on shared machines
- `window.acrossaiAbilitiesSitewide.current_user_id` provides the userId at runtime

---

## Decision 7: MCP Adapter Availability

**Decision**: `class_exists('WP\\MCP\\Core\\McpAdapter')` guard; `McpAdapter::instance()->get_servers()` when present

**Rationale**:
- Constitution V: all optional integrations MUST degrade gracefully
- When absent: server multi-select hidden, notice shown ("No MCP servers configured")
- REST endpoint `GET /sitewide/mcp-servers` returns `[]` when adapter absent — no PHP fatal

---

## Decision 8: Directory Layout

**Decision**: `includes/modules/sitewide/` (NOT `includes/features/sitewide/`)

**Rationale**:
- Constitution Architecture section defines `includes/modules/` as the canonical location
- The user arguments proposed `includes/features/` — this is a constitution violation; corrected here

---

## Decision 9: Bulk Action Scope

**Decision**: Current page only (no cross-page "select all N results")

**Rationale**: Q3 clarification; FR-012 updated. Standard WordPress list-table pattern; avoids accidental bulk actions on unseen records.

---

## Decision 10: NULL site_allowed Display

**Decision**: Show registry default value + "(Default)" indicator label

**Rationale**: Q1 clarification; FR-002/FR-026. NULL means "no override" — the ability behaves per its registry definition. The UI must surface this distinction so admins know whether a status is admin-set or inherited.

---

## Decision 11: "Keep as Default" MCP Save Behaviour

**Decision**: Saving with "Keep as Default" selected sets `show_in_mcp = NULL` and `mcp_servers = NULL` in the override record. If all fields in the record become NULL, the entire record is deleted.

**Rationale**: Q4 clarification; FR-017. Consistent with FR-024 (override-only storage). NULL = "use registry default" throughout the data model.

---

## Decision 12: REST Namespace and Route Prefix

**Decision**: REST namespace = `acrossai-abilities-manager/v1`; routes begin with `/sitewide/abilities`

**Rationale**: The namespace is the plugin slug + version. `/sitewide/` is a route prefix, NOT part of the namespace. Correct call: `register_rest_route( 'acrossai-abilities-manager/v1', '/sitewide/abilities', ... )`.

---

## Decision 13: React Root Mounting

**Decision**: `createRoot` from `@wordpress/element`; NOT `ReactDOM.render` from `react-dom`

**Rationale**: `ReactDOM.render` is deprecated in React 18. `@wordpress/element` re-exports `createRoot` from React 18 and is the correct WP-idiomatic approach.

---

## Decision 14: composer.json — berlindb/core

**Decision**: Add `"berlindb/core": "^2.0"` to `require` and Mozart config to prefix it

**Rationale**: Currently absent from composer.json. Must be added. Mozart `dep_namespace: AcrossAI_Abilities_Manager\\Vendor\\` ensures the prefixed classes are at `AcrossAI_Abilities_Manager\\Vendor\\BerlinDB\\Database\\{Table,Query,Row,Schema}`.
