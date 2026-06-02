# Tasks: Abilities List UX Improvements (Feature 025)

**Branch**: `025-abilities-list-ux-improvements`
**Input**: `specs/025-abilities-list-ux-improvements/plan.md`, `spec.md`
**Format**: `[ID] [P?] [Story?] Description — file path`

## Format Reference

- **[P]**: Can run in parallel (different files, no pending dependencies)
- **[USn]**: Maps to user story n from spec.md
- No test tasks — not requested in spec

---

## Phase 1: Setup

**Purpose**: Verify build toolchain and establish a clean baseline before any edits.

- [X] T001 Verify Node ≥ 20 is active (`node --version`) and run `npm run build` to confirm a clean baseline build — repo root
- [X] T002 [P] Run `composer run phpstan` to confirm PHPStan baseline is clean before PHP edits — repo root

**Checkpoint**: Clean build and PHPStan baseline confirmed.

---

## Phase 2: Foundational — perPage Injection (admin/Main.php)

**Purpose**: Add `perPage` to the existing `window.acrossaiAbilitiesManager` inline script so the JS component can read the setting on page load. This single-line PHP change unblocks US1 and US2.

- [X] T003 Add `'perPage' => (int) get_option( 'acrossai_abilities_per_page', 20 ),` to the existing `window.acrossaiAbilitiesManager` array inside the `is_manager_page()` block of `enqueue_scripts()` — `admin/Main.php` (lines 210–222)

**Checkpoint**: `window.acrossaiAbilitiesManager.perPage` is now available to the React component on the abilities manager page.

---

## Phase 3: User Story 1 — Stateful Pagination (Priority: P1) 🎯 MVP

**Goal**: Replace the frozen page-1 state with working prev/next/first/last pagination controls so all 100+ abilities are reachable.

**Independent Test**: Load the abilities manager on a site with 100+ abilities. Confirm only the first `perPage` abilities appear, and that pagination controls navigate to subsequent pages and back. Prev/First disabled on page 1; Next/Last disabled on last page.

- [X] T004 [US1] Replace `const [page] = useState(1)` with `const [page, setPage] = useState(1)` and replace `const PER_PAGE = 20` with `const perPage = Math.min(200, Math.max(1, parseInt(window.acrossaiAbilitiesManager?.perPage, 10) || 20))` (SEC hardening: FINDING-SEC-01 clamp) — `src/js/abilities/components/AbilitiesList.jsx` (lines 139–140)
- [X] T005 [US1] Add `const totalPages = Math.ceil(total / perPage) || 1;` derived value after the `perPage` line — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T006 [US1] Update `dispatch.fetchAbilities` call: replace `per_page: PER_PAGE` with `per_page: perPage` and add `page` to the params object — `src/js/abilities/components/AbilitiesList.jsx` (lines 160–170)
- [X] T007 [US1] Add a dedicated `useEffect` that calls `setPage(1)` whenever `search`, `sourceFilter`, `statusFilter`, or `sortDir` changes (separate from the fetch effect to avoid circular resets) — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T008 [US1] Add pagination nav JSX (`«`, `‹`, `N of M`, `›`, `»` buttons with `disabled` when at boundary) inside the existing `.tablenav` div, replacing or augmenting the current `.tn-pages` span — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T009 [US1] Add a second identical pagination nav as a `<div className="tablenav-pages">` block immediately after the closing `</table>` tag — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T010 [US1] Add `.tablenav-pages` CSS rule (pagination nav below table: flexbox, button sizing, disabled opacity) to the existing Tablenav section — `src/scss/abilities/admin.scss`

**Checkpoint**: Pagination fully navigable. Page state resets on filter/search change.

---

## Phase 4: User Story 2 — Per-page Setting (Priority: P2)

**Goal**: Site admin can control how many abilities are shown per page via the Settings page.

**Independent Test**: Set "Abilities per page" to 10 in Settings, reload abilities manager, confirm 10 per page. Set to 300 (out of range), confirm value is clamped back to 20.

- [X] T011 [P] [US2] Register `acrossai_abilities_per_page` via `register_setting()` with `sanitize_callback => array( $this, 'sanitize_per_page' )` and `default => 20` inside `register_settings()` — `admin/Partials/SettingsMenu.php`
- [X] T012 [P] [US2] Add `add_settings_section( 'acrossai_display_settings_section', __( 'Display Settings', ... ), '__return_false', 'acrossai-abilities-settings' )` and `add_settings_field()` for `acrossai_abilities_per_page` inside `register_settings()` — `admin/Partials/SettingsMenu.php`
- [X] T013 [US2] Add public method `sanitize_per_page( $value ): int` — `absint()` then clamp to [1, 200], return 20 if out of range — `admin/Partials/SettingsMenu.php`
- [X] T014 [US2] Add public method `render_per_page_field(): void` — `printf()` with `number` input, `esc_attr()` on value, `esc_html__()` on description string, `min="1" max="200"` — `admin/Partials/SettingsMenu.php`

**Checkpoint**: Settings page shows "Display Settings" section with "Abilities per page" field. Saving 50 makes the abilities list show 50 per page on next load.

---

## Phase 5: User Story 3 — Clear All Overrides Row Action (Priority: P3)

**Goal**: Inherited abilities with active overrides show a "Clear All Overrides" button inline in the list.

**Independent Test**: Create an override for a plugin ability. In the list, the Clear All Overrides button appears. Click, confirm, verify ability status reverts. Ability without override shows no button.

- [X] T015 [US3] In the `else` branch of `isCustom ? ... : ...` in the row actions cell (after the `Edit` button), add `{item.has_override && ( <> <span className="ra-sep">|</span> <button ... onClick={confirm+dispatch.clearOverrides}> Clear All Overrides </button> </> )}` with `window.confirm` guard and `eslint-disable-line no-alert` comment — `src/js/abilities/components/AbilitiesList.jsx` (lines 634–652)

**Checkpoint**: "Clear All Overrides" appears only for inherited abilities where `item.has_override === true`. Confirm dialog gates the action.

---

## Phase 6: User Story 4 — Description and Show in REST Columns (Priority: P4)

**Goal**: Two new columns visible at a glance in the abilities table without opening the edit form.

**Independent Test**: Verify Description column shows truncated text (title for full) or "—" for empty. Verify Show in REST shows "✓ Yes" / "○ No". Verify both columns work correctly for db AND non-db (plugin/core/theme) abilities.

- [X] T016 [P] [US4] Add `DescriptionCell` component: reads `item.description || item._registry?.description || ''`; truncates to 80 chars with `title` attribute; renders "—" when empty (DEC-TYPECELL-REGISTRY-FALLBACK) — `src/js/abilities/components/AbilitiesList.jsx` (top of file, after existing cell renderers)
- [X] T017 [P] [US4] Add `ShowInRestCell` component: reads `item.show_in_rest ?? item._registry?.show_in_rest ?? false`; renders `<span className="mcp-y">✓ Yes</span>` or `<span className="mcp-n">○ No</span>` (DEC-TYPECELL-REGISTRY-FALLBACK) — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T018 [US4] Add `<col className="col-desc" />` and `<col className="col-rest" />` to `<colgroup>` between `col-typ` and `col-mcp`; add `<th>Description</th>` and `<th>Show in REST</th>` to `<thead>` in the same position — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T019 [US4] Add `<td><DescriptionCell item={item} /></td>` and `<td><ShowInRestCell item={item} /></td>` to every `<tr>` in `<tbody>` between TypeCell and McpCell — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T020 [US4] Update the hard-coded `colSpan` in the loading-state row and the empty-state row from `9` to `11` (temporary — will become dynamic in T028) — `src/js/abilities/components/AbilitiesList.jsx`

**Checkpoint**: 11-column table renders. Description and Show in REST correct for all ability sources including non-db abilities that only have registry values.

---

## Phase 7: User Story 5 — Column Visibility Toggle (Priority: P5)

**Goal**: Admin can show/hide individual columns; preferences persist across page reloads via localStorage.

**Independent Test**: Hide "Category" and "MCP". Reload page. Both columns still hidden. Unhide. Reload. Both visible. New column added in future defaults to visible even with existing saved prefs.

- [X] T021 [US5] Add `COLUMN_DEFAULTS` constant object, `COLUMN_LABELS` map, and `LS_KEY` constant at module level (above the `AbilitiesList` function) — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T022 [US5] Add `loadColumnPrefs()` function that reads localStorage, merges with `COLUMN_DEFAULTS` using spread (defaults first so new columns default to visible), and normalises all values with `!!val` (FINDING-SEC-02) — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T023 [US5] Add `visibleColumns` state (`useState(loadColumnPrefs)`), `columnsOpen` state, `toggleColumn(key)` function (updates state + writes to localStorage with try/catch), and outside-click `useEffect` using `document.addEventListener('mousedown', ...)` — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T024 [US5] Add `const visibleCount = Object.values(visibleColumns).filter(Boolean).length` and `const tableColSpan = visibleCount + 3` derived values (3 = checkbox + Slug + Actions) — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T025 [US5] Add "Columns ▾/▴" button and panel JSX inside a `<div className="columns-toggle">` in the `.tablenav` row (before `.tn-pages`), with checkboxes for each `COLUMN_DEFAULTS` key using `COLUMN_LABELS` — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T026 [US5] Wrap each hideable `<col>`, `<th>`, and `<td>` with `{visibleColumns.KEY && ...}` conditional for all 8 hideable columns: label, category, source, status, type, description, show_in_rest, mcp — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T027 [US5] Replace the temporary hard-coded `colSpan={11}` from T020 with `colSpan={tableColSpan}` in both the loading-state and empty-state rows — `src/js/abilities/components/AbilitiesList.jsx`
- [X] T028 [US5] Add `.columns-toggle`, `.columns-panel`, and `.columns-panel-item` CSS rules (panel position, z-index, border, padding, checkbox label layout) — `src/scss/abilities/admin.scss`

**Checkpoint**: Columns panel opens/closes. Hidden columns fully absent from DOM (`<col>`, `<th>`, `<td>` all removed). Preferences survive full page reload. colSpan updates correctly.

---

## Phase 8: User Story 6 — Hide All/Published/Draft Tabs (Priority: P6)

**Goal**: The `<ul class="subsubsub">` tabs are visually hidden without removing any JSX or state.

**Independent Test**: Inspect DOM — `.subsubsub` element present but invisible. "All Statuses" dropdown still filters correctly. No JS errors.

- [X] T029 [US6] Add `display: none;` to the existing `.subsubsub` rule in `src/scss/abilities/admin.scss` (line ~294) with inline comment `// hidden Feature 025 — JSX preserved for future re-enable`; do NOT touch AbilitiesList.jsx — `src/scss/abilities/admin.scss`

**Checkpoint**: Tabs invisible. All Statuses dropdown unaffected. No console errors.

---

## Phase 9: Polish & Quality Gates

**Purpose**: Cross-cutting validation across all six changes.

- [X] T030 Run ESLint on `src/js/abilities/components/AbilitiesList.jsx` and fix all errors/warnings (`npm run lint` or `npx eslint src/js/abilities/components/AbilitiesList.jsx`) — repo root
- [X] T031 Run `composer run phpstan` and confirm zero errors after SettingsMenu.php and admin/Main.php changes — repo root
- [X] T032 Run `npm run build` (Node ≥ 20) and confirm clean build with no warnings — repo root
- [X] T033 [P] Manual smoke test: load abilities manager, navigate pagination, change per-page, hide columns, confirm Clear All Overrides on an ability with overrides, verify Description/REST columns for both db and non-db abilities — browser
- [X] T034 [P] Manual smoke test: confirm `.subsubsub` tabs invisible in rendered UI; confirm "All Statuses" filter dropdown still works; confirm no console errors — browser

**Checkpoint**: All quality gates pass. Feature ready for review.

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (Setup)
    └── Phase 2 (Foundational — admin/Main.php)
            ├── Phase 3 (US1 Pagination) ──────────────┐
            ├── Phase 4 (US2 Settings)                  │
            ├── Phase 5 (US3 Clear Overrides)           │ All independent
            ├── Phase 6 (US4 Desc + REST columns) ──────┤
            ├── Phase 7 (US5 Column Visibility)          │
            └── Phase 8 (US6 Hide Tabs) ────────────────┘
                    └── Phase 9 (Polish — all phases complete)
```

### Within-Phase Dependencies

- **Phase 3**: T004 → T005 → T006 (perPage defined before used); T007–T009 can follow T006; T010 is CSS (independent)
- **Phase 4**: T011–T012 can run together [P]; T013–T014 each independent [P]
- **Phase 6**: T016–T017 are independent [P]; T018 depends on T016+T017; T019 depends on T018; T020 follows T019
- **Phase 7**: T021–T022 can run together; T023 depends on T021–T022; T024–T028 depend on T023; T027 supersedes T020

### Cross-Phase Notes

- T020 (temporary colSpan=11) is superseded by T027 (dynamic colSpan) — T027 must be completed to finish US5
- Phase 6 and Phase 7 both touch AbilitiesList.jsx — implement sequentially to avoid merge conflicts
- Phases 3, 5, 6, 7 all edit AbilitiesList.jsx — complete one before starting the next in the same file

---

## Parallel Opportunities

```bash
# Phase 1 — run together:
T001 (build verify) + T002 (phpstan baseline)

# Phase 4 — run together:
T011 (register_setting) + T012 (section+field) + T013 (sanitize_per_page) + T014 (render_per_page_field)

# Phase 6 — run together:
T016 (DescriptionCell) + T017 (ShowInRestCell)

# Phase 9 — run together:
T030 (ESLint) + T031 (PHPStan) + T032 (build)
T033 (smoke test pagination/columns) + T034 (smoke test tabs/filters)
```

---

## Implementation Strategy

### MVP: User Story 1 Only (Pagination)

1. Phase 1: Setup (T001–T002)
2. Phase 2: Foundational — admin/Main.php (T003)
3. Phase 3: US1 Pagination (T004–T010)
4. **Stop and validate**: pagination navigates all 100+ abilities
5. Continue to remaining stories

### Recommended Delivery Order

All user stories share the same primary file (`AbilitiesList.jsx`) — implement sequentially to avoid conflicts:

1. Phase 1 → Phase 2 → Phase 3 (US1 Pagination) — MVP
2. Phase 4 (US2 Settings) — can overlap with Phase 3 since it's a different file
3. Phase 5 (US3 Clear Overrides) — single JSX addition
4. Phase 6 (US4 New Columns) — before Phase 7 (column visibility needs to know which columns exist)
5. Phase 7 (US5 Column Visibility) — wraps columns added in Phase 6
6. Phase 8 (US6 Hide Tabs) — one CSS line, do last
7. Phase 9 (Polish)

---

## Notes

- AbilitiesList.jsx is edited in every phase (3, 5, 6, 7) — complete each phase in that file fully before starting the next
- admin.scss is edited in phases 3, 7, 8 — can batch all CSS changes into a single edit pass at the end of Phase 8 if preferred
- No new files, no new PHP classes, no new REST routes — all changes are additive edits to 4 existing files
- `BUG-ABILITYFORM-JSX-MIXED-DEPTHS`: verify actual indentation at each insertion point before using str_replace on AbilitiesList.jsx
