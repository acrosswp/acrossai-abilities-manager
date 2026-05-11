# Feature Specification: Sitewide Ability Management

**Feature Branch**: `001-sitewide-ability-management`
**Created**: 2026-05-11
**Status**: Draft

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Browse All Registered Abilities (Priority: P1)

A site administrator opens the Ability Manager admin page and sees a paginated, sortable table listing every ability registered on the site. Each row shows the ability slug, provider, source, and current allow/disallow status. The admin can search by slug or provider name and change how many rows appear per page.

**Why this priority**: Without the ability list, no other management action is possible. This is the entry point to the entire feature and delivers immediate discoverability value on its own.

**Independent Test**: Activating the plugin and visiting `wp-admin/admin.php?page=acrossai-abilities-manager` must render a populated table of all registered abilities. No other features need to be implemented to verify this.

**Acceptance Scenarios**:

1. **Given** at least one ability is registered on the site, **When** an admin with `manage_options` visits the Ability Manager page, **Then** a table displays each ability with columns: Slug, Provider, Source, and Status.
2. **Given** the ability table is loaded, **When** the admin types a partial slug or provider name into the search field and stops typing, **Then** the table updates to show only matching abilities without a page reload.
3. **Given** the ability table is loaded, **When** the admin clicks a column header, **Then** the table is sorted by that column and the sort direction indicator updates accordingly.
4. **Given** the ability table is loaded, **When** the admin changes the per-page selector to 50, **Then** up to 50 abilities appear and the pagination updates.
5. **Given** no abilities are registered, **When** the admin visits the Ability Manager, **Then** an empty-state message is shown instead of a table.
6. **Given** a non-admin user tries to access the page, **When** WordPress evaluates the capability check, **Then** the user receives an access denied response.

---

### User Story 2 — Allow or Disallow an Ability Site-wide (Priority: P2)

An administrator clicks the Allow/Disallow toggle in an ability row. The change is saved immediately without a page reload. The Status column reflects the new value. The admin can toggle it back at any time.

**Why this priority**: Disabling abilities is the primary governance action. It delivers security value on its own — an admin can turn off unwanted abilities without needing the full edit form.

**Independent Test**: Click the Disallow button on any ability. Reload the page. The ability should still show as Disallowed. Click Allow. Reload. Should show as Allowed.

**Acceptance Scenarios**:

1. **Given** an ability showing as Allowed, **When** the admin clicks Disallow, **Then** the status changes to Disallowed immediately and the change persists after a page reload.
2. **Given** an ability showing as Disallowed, **When** the admin clicks Allow, **Then** the status changes to Allowed immediately and persists.
3. **Given** the admin clicks Disallow on a core WordPress ability, **When** the action completes, **Then** the source column still shows "core" and the status shows "Disallowed".
4. **Given** any network error during the save, **When** the toggle request fails, **Then** the status reverts to its previous value and an inline error message is shown.

---

### User Story 3 — Edit Per-Ability Settings via Form (Priority: P3)

An admin clicks the Edit button on an ability row. A slide-in panel opens on the right side of the screen showing the ability details and a two-tab form (General and MCP). The admin can change override values for metadata fields (readonly, destructive, idempotent, show_in_rest, MCP visibility, mcp_type) and save them. Registry defaults are shown alongside current override values for reference. Only values that actually differ from the registry default are saved to the database; unchanged values are never written.

**Why this priority**: Granular metadata control is the primary differentiator beyond simple allow/disallow. It enables REST and MCP governance per ability.

**Independent Test**: Click Edit on any ability. The slide-in panel opens. Change the "Readonly" value. Save. Reopen the edit panel. The changed value should appear. The original registry value should still be visible as a reference. Then open Edit on a second ability, make no changes, click Save — the system should respond "No changes made" without touching the database.

**Acceptance Scenarios**:

1. **Given** the admin clicks Edit on an ability, **When** the slide-in panel opens, **Then** both the General and MCP tabs are visible and the General tab is selected by default.
2. **Given** the edit panel is open, **When** the admin views the General tab, **Then** the current override values (or null/default indicators) for Allowed, Readonly, Destructive, and Idempotent are shown alongside the registry defaults.
3. **Given** the edit panel is open, **When** the admin toggles "Show in REST", saves, and reopens the panel, **Then** the "Show in REST" field reflects the saved value.
4. **Given** the edit panel is open on the MCP tab, **When** the admin sets MCP Visibility to "Allow in specific MCP servers" and saves, **Then** the selection and chosen server IDs are persisted and visible on reopen.
5. **Given** the admin presses Escape or clicks the backdrop, **When** no save has occurred, **Then** the panel closes without saving any changes.
6. **Given** the edit panel is open, **When** the admin clicks Save and at least one field differs from the registry default, **Then** the panel remains open, a "Settings saved" notice appears, and changes are persisted.
7. **Given** the edit panel is open, **When** the admin clicks Save but no fields differ from registry defaults, **Then** no database write occurs and a "No changes made" notice is displayed.
8. **Given** the edit panel is open, **When** the admin clicks Cancel, **Then** the panel closes and no changes are saved.

---

### User Story 4 — Reset Override to Registry Defaults (Priority: P4)

An admin finds an ability with a stored override and wants to revert it to its original registry values. They click Reset Override in the row action menu. All override fields are cleared and the ability reverts to its registry defaults.

**Why this priority**: The ability to undo changes is essential for maintaining confidence in the UI. Without reset, admins are hesitant to make changes.

**Independent Test**: Save an override on any ability. Click Reset Override. Reload. The ability should show no override values — it should appear as if never modified.

**Acceptance Scenarios**:

1. **Given** an ability with a stored override, **When** the admin clicks Reset Override, **Then** all override fields are deleted and the ability shows its registry default values.
2. **Given** an ability without any override, **When** Reset Override is shown in the action menu, **Then** it is either hidden or disabled with an appropriate tooltip.
3. **Given** the reset completes, **When** the admin opens the edit panel, **Then** all fields show default (null/registry) values.

---

### User Story 5 — Bulk Allow, Disallow, or Reset Multiple Abilities (Priority: P5)

An admin selects multiple abilities using checkboxes and applies a bulk action (Allow, Disallow, or Reset Override) to all selected abilities at once.

**Why this priority**: Batch operations reduce friction when managing many abilities, but the feature is fully usable without bulk actions.

**Independent Test**: Select 3 abilities. Click Bulk Disallow. All 3 should show as Disallowed. Select the same 3. Click Bulk Reset. All 3 should revert to defaults.

**Acceptance Scenarios**:

1. **Given** the admin selects 3 abilities and clicks Bulk Disallow, **When** the action completes, **Then** all 3 show as Disallowed and the selection is cleared.
2. **Given** the admin selects abilities and clicks Bulk Reset, **When** the action completes, **Then** all selected overrides are deleted.
3. **Given** some bulk actions succeed and some fail, **When** the action completes, **Then** a summary report shows how many succeeded and lists any that failed, without hiding partial success.
4. **Given** the admin selects 0 abilities, **When** the bulk action toolbar is visible, **Then** the action buttons are disabled.

---

### Edge Cases

- What happens when the WordPress Abilities API returns zero abilities? → Empty-state message shown; no table rendered.
- What happens when an ability is deregistered after an override exists? → Override row remains in the database but the ability does not appear in the table (registry is source of truth for the list).
- What happens when a bulk action is applied to 100+ abilities? → The operation completes (may take several seconds); a loading indicator is shown and the table refreshes after completion.
- What happens when MCP adapter is not present? → MCP tab is shown; the "Allow in specific MCP servers" option is visible but the server multi-select is hidden and a note explains that no MCP servers are configured.
- What happens when override values match registry defaults exactly? → No override row is written; the system treats it as "no override" and displays "No changes made".
- What happens when the admin saves the edit form without modifying anything? → No database write occurs; system displays "No changes made" notice.
- What happens when the admin edits 5 fields but only 2 differ from defaults? → Only those 2 fields are written to the database; the other 3 fields remain NULL in the override record (indicating "use registry default").
- What happens when the admin selects "Keep as Default" on the MCP Visibility radio and saves? → `show_in_mcp` and `mcp_servers` are set to NULL in the override record, reverting those fields to the registry default. If all other override fields are also NULL after this operation, the entire override record is deleted.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST display all abilities returned by the WordPress Abilities API in a paginated table on the Ability Manager admin page.
- **FR-002**: Table MUST show per-row: Ability Slug, Provider, Source (plugin/theme/core/db), and Status. Status MUST reflect the effective value: if an admin override exists, show "Allowed" or "Disallowed"; if no override exists, show the registry default with a "Default" indicator (e.g., "Allowed (Default)" or "Disallowed (Default)").
- **FR-003**: Admin MUST be able to toggle the site_allowed status for any ability directly from the table row without a full page reload.
- **FR-004**: All override values MUST be persisted in a dedicated database table and survive page reloads, server restarts, and plugin deactivation/reactivation.
- **FR-005**: Admin MUST be able to open a slide-in edit panel for any ability to change per-ability override values.
- **FR-006**: Table search MUST be debounced — the table MUST NOT update more than once per 500 ms of user input inactivity. Search MUST filter abilities by ability slug AND provider name simultaneously.
- **FR-007**: Table MUST support sorting by Slug, Provider, Source, Status, and Last Updated columns.
- **FR-008**: Table MUST support configurable pagination with rows-per-page options of 10, 20, 50, and 100.
- **FR-009**: Column visibility preferences MUST persist across browser sessions (stored locally per-user, not in the database).
- **FR-010**: Override records MUST store the user ID that created and last updated each record, plus creation and update timestamps.
- **FR-011**: Admin MUST be able to reset an ability override to delete all stored override fields and restore registry defaults.
- **FR-012**: Admin MUST be able to select multiple abilities on the current page and apply a bulk Allow, Disallow, or Reset action to all selected at once. Bulk selection scope is limited to the currently visible page; cross-page "select all results" is out of scope.
- **FR-013**: Edit panel MUST display the original registry value for each field alongside the current override value.
- **FR-014**: All admin pages and REST API endpoints MUST require the `manage_options` capability; any other user MUST receive a 403 response.
- **FR-015**: System MUST detect and store the source type of each ability: plugin-registered, theme-registered, WordPress core, or database/custom.
- **FR-016**: Admin MUST be able to set or clear a "Show in REST API" override per ability.
- **FR-017**: The MCP tab MUST present a "MCP Visibility" radio group with four mutually exclusive options: (1) **Keep as Default** — no MCP visibility override is stored; saving with this option selected MUST set `show_in_mcp` and `mcp_servers` to NULL in the override record (reverting those fields to registry defaults); (2) **Disable for MCP** — ability is excluded from all MCP servers; (3) **Allow in all MCP servers** — ability is explicitly included in all MCP servers regardless of server configuration; (4) **Allow in specific MCP servers** — ability is restricted to admin-selected server IDs. The MCP Type dropdown (tool/resource/prompt) MUST remain visible for options (3) and (4). The server multi-select control MUST only be visible and editable when option (4) is selected.
- **FR-018**: When the MCP adapter (`WP\MCP\Core\McpAdapter`) is present, the "Allow in specific MCP servers" option MUST display a multi-select list of available server IDs fetched via `McpAdapter::instance()->get_servers()`. When the MCP adapter is absent, the server multi-select MUST be hidden and a notice MUST inform the admin that no MCP servers are configured.
- **FR-019**: Edit panel MUST be organized into two tabs: "General" (core settings: site_allowed, readonly, destructive, idempotent, show_in_rest) and "MCP" (MCP-specific settings: MCP Visibility radio group, MCP Type).
- **FR-020**: WordPress admin navigation MUST include a top-level "Ability Manager" menu at position 99 using the `dashicons-admin-tools` icon and slug `acrossai-abilities-manager`. The existing class `AcrossAI_Abilities_Manager\Admin\Partials\Menu` (`admin/Partials/Menu.php`) is the canonical menu registration point and MUST be updated in-place — no new menu class may be created. The `main_menu()` method must be updated to add the icon and position arguments. The `contents()` method must render the React root container element.
- **FR-021**: The edit panel MUST be rendered as a non-blocking slide-in drawer; it MUST NOT use a blocking dialog/modal that prevents interaction with the rest of the page.
- **FR-022**: When no abilities are registered, the table area MUST show an appropriate empty-state message.
- **FR-023**: Source detection logic MUST differentiate at minimum: `plugin`, `theme`, `core`, and `db` source types.
- **FR-024**: System MUST only create or update a database override record when at least one submitted field value differs from the corresponding registry default. If all submitted values match registry defaults, no record MUST be written or modified.
- **FR-025**: When the admin submits the edit form and no fields differ from registry defaults, the system MUST display a "No changes made" notice without performing any database write. When at least one field is saved, the system MUST display a "Settings saved" notice.
- **FR-026**: When `site_allowed` is NULL (no override stored), the Status column MUST display the ability's registered default allow/disallow value as returned by the WordPress Abilities API (`wp_get_ability()`) for that ability, with a distinct visual indicator (e.g., label suffix "Default") to communicate that this value originates from the registry and has not been overridden by an admin.

### Key Entities

- **Ability Registry Entry**: The read-only ability definition from the WordPress Abilities API (`wp_get_ability()`). Key attributes: slug, provider, category, and metadata defaults (readonly, destructive, idempotent, show_in_rest, show_in_mcp). The registry is the authoritative source of truth for all ability metadata defaults — the plugin never duplicates registry data to the database.
- **Provider**: The identifier of the plugin, theme, or WordPress core component that registered the ability, as returned by the WordPress Abilities API (e.g., `"woocommerce"`, `"my-plugin"`, `"wordpress-core"`). Provider is a read-only registry attribute and is never stored in the override table.
- **Ability Override**: Admin-controlled modifications stored in the database for a specific ability slug. Only fields that differ from the registry default are stored. Each nullable field is NULL when not overridden — NULL means "use registry default". Key fields: site_allowed, readonly, destructive, idempotent, show_in_rest, show_in_mcp, mcp_type, mcp_servers. MCP visibility state is encoded jointly via show_in_mcp (NULL=no override, false=disabled, true=enabled) and mcp_servers (NULL=all servers, []=n/a, [ids]=specific servers).
- **Effective Ability**: The merged view presented to the UI — registry entry values supplemented by any stored override values. For each field: if the override record has a non-NULL value, show the override; otherwise show the registry default. This is what the admin sees and manages.
- **Override Record**: The database row tracking audit data: created_by (user ID), updated_by (user ID), created_at, updated_at. One record per ability slug maximum. A record stores only the fields that were explicitly overridden (changed from default); all other fields are NULL.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The Ability Manager page loads and renders the full ability table within 2 seconds under normal conditions (< 500 registered abilities).
- **SC-002**: Toggling allow/disallow for a single ability completes and reflects in the UI within 1 second of the admin's click.
- **SC-003**: Search results update within 600 ms after the user stops typing (500 ms debounce + up to 100 ms render time).
- **SC-004**: Override changes made through the edit panel are immediately visible on re-opening the panel after saving.
- **SC-005**: An admin can complete the full edit workflow (open panel → change field → save → verify) in under 60 seconds.
- **SC-006**: After a reset, all override fields are cleared and the ability displays registry defaults on the next view.
- **SC-007**: Bulk actions on up to 50 abilities complete within 5 seconds.
- **SC-008**: Non-admin users are denied access with a 403 response; zero admin functionality is accessible without `manage_options`.

---

## Assumptions

- The WordPress Abilities API (`wp_get_abilities()`, `wp_get_ability()`) is available and functional on WordPress 6.9+.
- The plugin acts as a governance/override layer only — it does NOT duplicate ability data from the registry into the database. Only admin-changed values are stored.
- The WP MCP adapter (`WP\MCP\Core\McpAdapter`) may or may not be present; MCP server list degrades gracefully to empty when not available, accessed via `McpAdapter::instance()->get_servers()`.
- Column visibility preferences are stored per-user in browser storage (not server-side), so they do not sync across devices.
- An ability that is deregistered after an override was saved will NOT appear in the table, but its override row remains in the database (no auto-cleanup).
- The edit panel covers per-ability overrides only; site-level global settings are out of scope for this feature iteration.
- Bulk actions on more than 50 abilities at once are expected but not required to complete in under 1 second — up to 5 seconds is acceptable.
- The menu position 99 is the expected final position; conflict resolution with other plugins at position 99 is handled by WordPress core (first registered wins).
- Future sub-menus (Custom Abilities, Per-User Access, MCP Servers, WebMCP) are out of scope for this feature but the menu structure must accommodate them.

---

## Clarifications

### Session 2026-05-11

- Q: Are bulk actions (Allow/Disallow/Reset) and search by name/provider explicitly required? → A: Yes, confirmed. Bulk actions in US5/FR-012; search filters by slug AND provider name per FR-006.
- Q: Should the system only store overrides that differ from registry defaults? → A: Yes. Override-only storage strategy: write a record only when at least one field differs from the registry default; display "No changes made" when nothing differs. See FR-024, FR-025.
- Q: How should MCP visibility be presented in the MCP tab? → A: Option A selected — unified "MCP Visibility" radio group with 4 options (Keep as Default / Disable for MCP / Allow in all MCP servers / Allow in specific MCP servers). Server multi-select appears only when "Allow in specific MCP servers" is chosen. MCP Type dropdown remains visible for options 3 and 4. See updated FR-017/FR-018.
- Q: When site_allowed is NULL (no override), what should the Status column display? → A: Show the ability's registered default value from the WordPress Abilities API with a visual "Default" indicator (e.g., "Allowed (Default)"), so admins can distinguish registry defaults from admin-set overrides. See FR-002 and FR-026.
- Q: What should the search debounce delay be? → A: 500 ms. FR-006 updated to 500 ms inactivity threshold; SC-003 updated to 600 ms total (500 ms debounce + 100 ms render).

- Q: What does "provider" mean? → A: The plugin, theme, or WordPress core identifier that registered the ability, as returned by the WordPress Abilities API (read-only registry attribute). Added to Key Entities as a canonical term.
- Q: Should bulk "select all" apply to current page or all pages? → A: Current page only. Bulk selection is scoped to visible rows on the current page. Cross-page selection is out of scope. FR-012 updated.
- Q: When "Keep as Default" is selected on MCP Visibility and saved, what happens to existing show_in_mcp/mcp_servers overrides? → A: Set show_in_mcp and mcp_servers to NULL (revert to registry defaults), consistent with override-only strategy. If the entire record becomes all-NULL, it is deleted. FR-017 and Edge Cases updated.
- Q: Should a new admin menu class be created for the Ability Manager page? → A: No. The existing `admin/Partials/Menu.php` (`AcrossAI_Abilities_Manager\Admin\Partials\Menu`) is the canonical menu class and must be updated in-place with the correct icon, position, and React root container. No new menu file may be created. FR-020 updated.
