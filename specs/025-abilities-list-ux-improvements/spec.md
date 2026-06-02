# Feature Specification: Abilities List UX Improvements

**Feature Branch**: `025-abilities-list-ux-improvements`
**Created**: 2026-06-02
**Status**: Draft

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Navigate Large Ability Sets with Pagination (Priority: P1)

A site admin opens the Abilities Manager with 100+ abilities registered. Currently only the first 20 are visible with no way to reach the rest. After this feature, pagination controls appear above and below the table so the admin can move through all abilities page by page.

**Why this priority**: Without pagination, the majority of registered abilities are unreachable. This is a data-access blocker.

**Independent Test**: Load the abilities manager on a site with more than 20 abilities. Confirm that only the first page of results is shown, and that prev/next/first/last controls allow navigating to subsequent pages and back.

**Acceptance Scenarios**:

1. **Given** the site has 100 abilities and per-page is 20, **When** the admin loads the abilities manager, **Then** only the first 20 abilities are shown and the page indicator reads "1 of 5".
2. **Given** the admin is on page 1, **When** they click Next, **Then** the next 20 abilities load and the indicator reads "2 of 5".
3. **Given** the admin is on page 1, **When** they view the pagination controls, **Then** the First and Prev buttons are disabled (not hidden).
4. **Given** the admin is on the last page, **When** they view pagination controls, **Then** Next and Last buttons are disabled.
5. **Given** the admin changes the source filter, **When** the filter is applied, **Then** the page resets to 1.

---

### User Story 2 — Control How Many Abilities Appear Per Page (Priority: P2)

A site admin with many abilities wants to see 50 per page instead of 20. They go to the plugin Settings page, change "Abilities per page" to 50, save, and immediately see the larger page size reflected in the abilities table.

**Why this priority**: The per-page value is required by pagination to work meaningfully. Hardcoding 20 gives admins no control over density.

**Independent Test**: Set per-page to 10 in Settings. Open the abilities manager and confirm only 10 abilities show per page.

**Acceptance Scenarios**:

1. **Given** the admin opens the Settings page, **When** they view the Display Settings section, **Then** an "Abilities per page" field is present with a default of 20.
2. **Given** the admin sets per-page to 50 and saves, **When** they reload the abilities manager, **Then** up to 50 abilities are shown on the first page.
3. **Given** the admin enters 0 or 300, **When** the form is saved, **Then** the value is sanitized back to 20.

---

### User Story 3 — Clear All Overrides from the List View (Priority: P3)

A site admin sees an inherited ability (plugin/core/theme source) in the table and knows it has active overrides. Without leaving the list, they click "Clear All Overrides" in the Actions column, confirm the dialog, and the ability's status reverts to its default state.

**Why this priority**: Currently clearing overrides requires opening the edit form. Exposing it inline saves navigation steps for admins managing many overrides.

**Independent Test**: Create an override for a plugin ability. From the list, use the Clear All Overrides row action to remove it. Confirm the ability reverts to Default status without opening the edit form.

**Acceptance Scenarios**:

1. **Given** an inherited ability has an active override, **When** the admin views the abilities table, **Then** a "Clear All Overrides" button appears in its Actions column.
2. **Given** an inherited ability has no overrides, **When** the admin views the abilities table, **Then** no "Clear All Overrides" button appears for that row.
3. **Given** the admin clicks "Clear All Overrides", **When** the confirmation dialog appears, **Then** cancelling does nothing; confirming clears overrides and the row updates.

---

### User Story 4 — See Description and REST Visibility at a Glance (Priority: P4)

A site admin scanning the abilities table wants to quickly see what each ability does and whether it is exposed to the REST API, without opening the edit form.

**Why this priority**: Description and Show in REST are useful metadata fields already stored in the DB. Surfacing them in the list reduces the need to open each ability individually.

**Independent Test**: Verify that the Description and Show in REST columns appear in the table and correctly reflect the values set on each ability.

**Acceptance Scenarios**:

1. **Given** an ability has a description longer than 80 characters, **When** the admin views the table, **Then** the Description cell shows a truncated version and the full text is accessible on hover.
2. **Given** an ability has no description, **When** the admin views the table, **Then** the Description cell shows "—".
3. **Given** an ability has Show in REST enabled, **When** the admin views the table, **Then** the Show in REST cell shows "✓ Yes"; otherwise "○ No".

---

### User Story 5 — Show or Hide Table Columns (Priority: P5)

A site admin wants a less cluttered table. They click the "Columns" button in the tablenav, uncheck "Category" and "MCP", and those columns disappear instantly. The next time they load the page, the same columns are still hidden.

**Why this priority**: With 11 columns the table is wide. Letting admins personalise the view improves readability without removing any data.

**Independent Test**: Hide two columns using the Columns panel. Reload the page. Confirm the same columns remain hidden.

**Acceptance Scenarios**:

1. **Given** the admin opens the Columns panel, **When** they uncheck a column, **Then** that column's header, cells, and `<col>` entry are removed from the table immediately.
2. **Given** the admin has hidden columns and reloads the page, **When** the table renders, **Then** the previously hidden columns remain hidden.
3. **Given** all columns have been hidden by a saved preference, **When** a new column is added in a future release, **Then** that column defaults to visible for all users regardless of their saved preference.
4. **Given** the Columns panel is open, **When** the admin clicks anywhere outside the panel, **Then** the panel closes.

---

### User Story 6 — All/Published/Draft Tabs Are Hidden (Priority: P6)

The All / Published / Draft quick-link tabs currently appear below the page title but serve no purpose that the All Statuses dropdown does not already cover. After this feature they are visually hidden, but the underlying state and markup remain intact for future use.

**Why this priority**: Visual cleanup. The tabs are redundant and add noise to the toolbar. The change is CSS-only, so it is safe and trivially reversible.

**Independent Test**: Inspect the DOM and confirm the `.subsubsub` element exists but is not visible. Confirm the All Statuses dropdown still filters correctly.

**Acceptance Scenarios**:

1. **Given** the admin loads the abilities manager, **When** they inspect the page, **Then** the All / Published / Draft tabs are not visible.
2. **Given** the tabs are hidden, **When** the admin uses the All Statuses dropdown, **Then** status filtering still works correctly.

---

### Edge Cases

- What happens when the admin navigates to a page number that no longer exists after a filter is applied? → Reset to page 1.
- What happens when per-page is set to a very large number (e.g., 200) and there are fewer abilities than that? → A single page shows all abilities; pagination controls are effectively disabled.
- What happens when all columns are hidden via the Columns panel? → The Slug and Actions columns (always visible) remain, preventing a completely empty table.
- What happens when `localStorage` is unavailable (private browsing, quota exceeded)? → Column preferences silently fall back to defaults on each page load.
- What happens when `window.acrossaiAbilitiesManager.perPage` is missing (script not yet enqueued)? → Fall back gracefully to 20.

---

## Requirements *(mandatory)*

### Functional Requirements

**Pagination**

- **FR-001**: The abilities table MUST display only the number of abilities equal to the configured per-page value per page.
- **FR-002**: Pagination controls (First, Prev, page indicator, Next, Last) MUST appear above and below the table.
- **FR-003**: First and Prev controls MUST be disabled when the admin is on page 1.
- **FR-004**: Next and Last controls MUST be disabled when the admin is on the last page.
- **FR-005**: Changing any filter or search value MUST reset the current page to 1.

**Per-page Setting**

- **FR-006**: The Settings page MUST include a "Display Settings" section with an "Abilities per page" field (integer, default 20, min 1, max 200).
- **FR-007**: Values outside the 1–200 range submitted to the Settings page MUST be sanitized to 20.
- **FR-008**: The per-page value set in Settings MUST be passed to the front-end abilities manager on page load.

**Hide Tabs**

- **FR-009**: The All / Published / Draft quick-link tabs MUST NOT be visible in the abilities manager UI.
- **FR-010**: The JSX markup, state, and logic for the tabs MUST be preserved in the source code and MUST NOT be deleted.

**Clear All Overrides Row Action**

- **FR-011**: For non-db (inherited) abilities that have at least one active override, the Actions column MUST include a "Clear All Overrides" button.
- **FR-012**: The "Clear All Overrides" button MUST NOT appear for abilities that have no active overrides.
- **FR-013**: Clicking "Clear All Overrides" MUST display a confirmation dialog before any action is taken.
- **FR-014**: Confirming the dialog MUST clear all overrides for that ability and refresh the row's displayed state.
- **FR-015**: The REST API already exposes a `has_override` boolean in every ability response (computed by `AcrossAI_Ability_Merger`, returned by `AcrossAI_Abilities_Formatter`). No new REST field is required; the front-end reads `item.has_override` directly.

**Description and Show in REST Columns**

- **FR-016**: The abilities table MUST include a Description column showing the ability's description, truncated to approximately 80 characters with the full text accessible on hover.
- **FR-017**: Abilities without a description MUST display "—" in the Description column.
- **FR-018**: The abilities table MUST include a "Show in REST" column showing whether the ability is exposed to the REST API, using a Yes/No badge.
- **FR-019**: The Description and Show in REST columns MUST be inserted between the Type and MCP columns.

**Column Visibility Toggle**

- **FR-020**: A "Columns" button in the tablenav MUST open a panel listing all hideable columns with individual checkboxes.
- **FR-021**: Hideable columns are: Label, Category, Source, Status, Type, Description, Show in REST, MCP. Checkbox, Slug, and Actions are always visible and MUST NOT appear in the panel.
- **FR-022**: Checking or unchecking a column checkbox MUST instantly show or hide that column without a page reload.
- **FR-023**: Column visibility preferences MUST persist across page reloads using browser localStorage.
- **FR-024**: When no saved preferences exist, all columns MUST default to visible.
- **FR-025**: Newly added columns MUST default to visible even for users who have existing saved preferences (merge-with-defaults on load).
- **FR-026**: Clicking outside the Columns panel MUST close the panel.
- **FR-027**: The colspan of loading and empty-state rows MUST update dynamically to match the current number of visible columns.

### Key Entities

- **Ability**: A registered capability with slug, label, description, source, status, callback type, show_in_rest flag, show_in_mcp flag, and category. May have zero or more overrides.
- **Ability Override**: A site-level override record for an inherited ability that changes its behaviour from the registered default.
- **Abilities Per Page Setting**: A site option controlling how many abilities are shown per page in the admin list. Integer, 1–200, default 20.
- **Column Preference**: A per-browser record (localStorage) mapping each hideable column key to a boolean visible/hidden state.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: All registered abilities on a site with 100+ abilities are reachable through pagination without scrolling off-screen or losing any rows.
- **SC-002**: Changing the per-page setting to any value between 1 and 200 immediately changes the number of abilities shown per page on the next page load.
- **SC-003**: An admin can clear all overrides for an inherited ability without leaving the list view, completing the action in under 5 seconds.
- **SC-004**: Description and Show in REST data is visible at a glance for every ability in the table without opening the edit form.
- **SC-005**: Column visibility preferences survive a full browser page reload with 100% consistency.
- **SC-006**: The All / Published / Draft tabs are absent from the visible UI on all supported browsers without any JavaScript errors.

---

## Assumptions

- The REST endpoint for abilities (`/wp-json/wp-abilities/v1/abilities`) already supports `page` and `per_page` query parameters; no new REST route is needed for pagination.
- `window.acrossaiAbilitiesManager` is an object already injected by the plugin's admin enqueue logic; adding `perPage` to it is a safe extension.
- The `has_override` field already exists in the REST response: `AcrossAI_Ability_Merger` computes it, `AcrossAI_Abilities_Formatter` returns it, and `AbilityForm.jsx` already reads `savedAbility?.has_override`. No PHP change is required for this field.
- `item.description` and `item.show_in_rest` are already returned in the REST response for all ability sources (db, plugin, core, theme); if any source omits them, they will default to empty string and `false` respectively.
- `localStorage` is available in all supported admin browsers; failure is treated as a silent fallback to defaults, not an error.
- The existing admin SCSS build pipeline compiles `src/scss/abilities/admin.scss` into the enqueued stylesheet; adding one CSS rule there is sufficient to hide the tabs.
- Mobile admin support is out of scope; the abilities manager is a desktop-first admin tool.
