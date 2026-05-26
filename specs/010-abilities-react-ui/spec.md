# Feature Specification: Abilities React UI + Admin Shell

**Feature Branch**: `010-abilities-react-ui`
**Created**: 2026-05-23
**Status**: Implemented
**Input**: User description: "Abilities React UI and admin shell for the Custom Abilities admin page."

## Clarifications

### Session 2026-05-23

- Q: How should the abilities list and form handle REST API failures (network error, 403, 500)? → A: Inline dismissible WP-style error notices — list stays visible with last-loaded data; form prevents save while in error state.
- Q: How should the abilities list handle large datasets — all at once or paginated? → A: Server-side pagination, 20 per page default, page navigation controls in tablenav (matches WP list table standard).
- Q: After a successful "✓ Save Changes" on the Edit form, where does the user land? → A: Stay on edit form — dirty indicator clears, form reflects saved state, no navigation occurs.

### Session 2026-05-24

- Implementation note: `@wordpress/dataviews` DataViews was replaced with a custom HTML `.wptable` to match the Final Design.html prototype. Design requirement overrides Constitution §III DataViews mandate for this feature.
- Implementation note: `@wordpress/dataforms` DataForm was replaced with plain HTML form sections to match the Edit Form Wireframe structure (unified `.panel`/`.sect` pattern). No DataForm used anywhere in the abilities UI.
- Implementation note: All interactive/primary colors use `var(--wp-admin-theme-color)` — never hardcoded `#007cba`. Allows WP admin color scheme switching (Ocean, Midnight, Sunrise, Ectoplasm).
- Implementation note: Slug field sends `slug_suffix` (user-typed suffix only) on create. REST write controller prepends `acrossai-abilities/` prefix server-side. Previously sent full `ability_slug` causing validation error "Slug suffix is required".
- Implementation note: Build requires Node 20. Node 16 throws `TypeError: [...].toSorted is not a function`. Use `nvm use 20 && npm run build`.
- Implementation note: Form layout uses unified `.panel`/`.sect` structure from the wireframe instead of multiple `.postbox` elements from the original spec. `.sect-num` shows section numbers; `.sect-opt` marks optional sections.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Browse and Manage Custom Abilities (Priority: P1)

A WordPress administrator navigates to the new **Custom Abilities** submenu under the Abilities Manager. They see a full-page list of all abilities on the site — both custom (source=db) and inherited (source=plugin/theme/core) — with source badges, status indicators, callback type, and MCP visibility. They can filter by source, status, and search by slug/label. Custom rows show Edit/Delete actions and an inline status dropdown for quick publish/draft toggling. Inherited rows show Edit/Override actions.

**Why this priority**: The list view is the entry point for all ability management. Without it, no other CRUD operation is reachable. Delivers immediate value by surfacing all registered abilities in one place.

**Independent Test**: Navigate to Abilities Manager → Custom Abilities. Table renders with rows from the REST API. Source badges, status indicators, and row actions display correctly. Filtering and search work.

**Acceptance Scenarios**:

1. **Given** the admin visits Custom Abilities, **When** the page loads, **Then** a table shows all abilities with columns: Slug (prefix dimmed), Label, Category, Source badge, Status, Type badge, MCP visibility, and Actions.
2. **Given** the table is loaded, **When** the admin types in the search box, **Then** the list filters to matching slug/label rows.
3. **Given** the table is loaded, **When** the admin selects "Custom" from the Source filter, **Then** only source=db rows are visible.
4. **Given** the table is loaded, **When** the admin selects "Published" from the Status filter, **Then** only status=publish rows are visible.
5. **Given** a custom row, **When** the admin changes the inline status dropdown to "Draft", **Then** the ability's status updates immediately.
6. **Given** custom rows are selected via checkboxes, **When** the admin applies "Publish" bulk action, **Then** all selected rows are published.

---

### User Story 2 — Create a New Custom Ability (Priority: P1)

An administrator clicks "Add New Ability" and fills in a form with the ability's slug suffix, label, category, description, and execution callback type. They choose the callback type (noop, filter_hook, wp_remote_post, or php_code) and configure it in the dynamic config block below the type chips. They set MCP exposure, annotations, and access control, then save. The ability is created with the full slug prefix `acrossai-abilities/<suffix>`.

**Why this priority**: Creating custom abilities is the core MVP of the entire feature — without it, the module delivers no net-new value over the existing sitewide overrides.

**Independent Test**: Click "Add New Ability", fill the form with a label, category, and slug suffix, click "✓ Add Ability". Verify the new row appears in the list with the full `acrossai-abilities/` prefix and status=publish.

**Acceptance Scenarios**:

1. **Given** the admin clicks "Add New Ability", **When** the form opens, **Then** the noop chip is selected by default and the config block shows an informational notice.
2. **Given** the form is open, **When** the admin selects the `filter_hook` chip, **Then** a hook_name text input appears in the config block.
3. **Given** the form is open, **When** the admin selects `wp_remote_post`, **Then** URL, Method, and Timeout fields appear inline.
4. **Given** the form is open, **When** the admin selects `php_code`, **Then** a dark monospace textarea appears (dark background, green text).
5. **Given** the form is filled, **When** the admin clicks "✓ Add Ability", **Then** the ability is created via POST, the slug is stored as `acrossai-abilities/<suffix>`, and the admin is redirected to the Edit screen.
6. **Given** the form is filled, **When** the admin clicks "Save as Draft", **Then** the ability is created with status=draft.
7. **Given** an unsaved form, **When** the admin tries to navigate away, **Then** a browser confirmation dialog warns about discarding changes.

---

### User Story 3 — Edit an Existing Custom Ability (Priority: P2)

An administrator clicks "Edit" on a custom ability row. A full-page form opens pre-populated with all saved values. The admin modifies any fields. An "Unsaved changes" indicator appears in the page title. They click "✓ Save Changes" to persist. The sticky bottom bar shows the unsaved-changes note while there are pending edits. They can also delete the ability from the sidebar.

**Why this priority**: Edit is essential for updating abilities after creation, but depends on the create flow being in place first.

**Independent Test**: Edit an existing ability, change the label, observe the unsaved indicator, click Save Changes, confirm the label updated in the list.

**Acceptance Scenarios**:

1. **Given** the admin clicks Edit on a custom ability, **When** the form opens, **Then** all fields are pre-populated from the server state.
2. **Given** the edit form, **When** the admin changes any field, **Then** the "● Unsaved changes" indicator appears in the page title.
3. **Given** the edit form with unsaved changes, **When** the admin clicks "✓ Save Changes", **Then** the changes are saved via POST, the dirty indicator clears, the form reflects the saved state, and the admin remains on the edit screen.
4. **Given** the edit form with unsaved changes, **When** the admin clicks "Save as Draft", **Then** the ability's status is set to draft.
5. **Given** the edit form, **When** the admin clicks "🗑 Delete Ability" and confirms, **Then** the ability is deleted and the admin is returned to the list.
6. **Given** the edit form with unsaved changes, **When** the admin tries to navigate away, **Then** a confirmation dialog fires.
7. **Given** the edit sidebar, **When** the form loads, **Then** an Activity box shows the created and last-updated timestamps.

---

### User Story 4 — Override an Inherited Ability (Priority: P2)

An administrator clicks "Override" on an inherited (source=plugin/theme/core) ability row. A form opens showing a locked card with the identity fields (read-only) and editable override sections: site permission (Force Block / Inherit / Force Allow), MCP exposure override, annotation overrides with "Plugin declares: X" hints, and access control override. Saving only writes the override fields.

**Why this priority**: Override management is the bridge between the existing sitewide overrides feature and the new custom abilities. Essential for the admin to control third-party ability behavior.

**Independent Test**: Edit an inherited ability, change the site permission to "Force Block", save, verify site_allowed=0 in the database.

**Acceptance Scenarios**:

1. **Given** the admin clicks Edit/Override on an inherited ability, **When** the form opens, **Then** the locked card shows slug, label, category, and callback type as read-only, with a "🔒 Registered by plugin: X" header.
2. **Given** the override form, **When** the admin selects "Force Block" chip, **Then** site_allowed is set to 0 on save.
3. **Given** the override form, **When** the admin selects "Inherit (plugin default)", **Then** site_allowed is set to null on save.
4. **Given** the override form, **When** the admin selects "Force Allow", **Then** site_allowed is set to 1 on save.
5. **Given** the MCP Exposure Override section, **When** the admin changes MCP Type, **Then** a hint shows the plugin's declared value.
6. **Given** the Access Control Override with "inherit" selected, **When** the form renders, **Then** the role checkboxes are grayed/disabled with a note "Inheriting from plugin".
7. **Given** the admin clicks "↩ Clear All Overrides", **Then** all override fields are reset to null/inherit.

---

### Edge Cases

- What happens when the abilities REST endpoint returns an empty array? → Show a "No abilities found" message; "Add New Ability" button remains prominent.
- What if `getCategories()` returns an empty array? → Category dropdown shows only the "— choose —" placeholder; form is still submittable.
- What happens when the user saves with an invalid JSON schema field? → Show an inline validation error below the field; prevent submission until fixed.
- What if a `php_code` ability has a callback_config that was rejected by the server (blocked function)? → Show the server validation error inline below the code textarea.
- What if the user navigates away during an in-flight save? → The save completes in the background; any navigation after completion shows the updated state.
- What if a REST call fails (network, 403, 500)? → An inline dismissible WP-style error notice is shown above the affected area; the list retains its last-loaded data; save/delete forms remain open with edits intact and a re-enabled retry button.
- What happens when deleting an ability that is currently registered (status=publish)? → The delete succeeds; WordPress deregisters the ability on the next request cycle.
- What if `build/js/abilities.asset.php` is missing (build not run)? → PHP falls back gracefully; the submenu page renders but the React app does not mount. Log a PHP notice.

---

## Requirements *(mandatory)*

### Functional Requirements

**Admin Shell**

- **FR-001**: The system MUST register a "Custom Abilities" submenu under the "Abilities Manager" top-level menu, accessible only to users with the `manage_options` capability.
- **FR-002**: The submenu page MUST mount a React application inside a `<div id="acrossai-abilities-root">` element.
- **FR-003**: The page MUST enqueue the compiled JS and CSS assets only on the Custom Abilities page (not globally on all admin pages).
- **FR-004**: The page MUST pass a JavaScript config object (`window.acrossaiAbilitiesManager`) containing the REST nonce, REST base URL, REST namespace, and current user ID.

**List View**

- **FR-005**: The list view MUST display all abilities (custom and inherited) in a sortable, filterable table.
- **FR-006**: Each row MUST show: slug (with prefix dimmed), label, category pill, source badge, status indicator, type badge, MCP visibility, and row actions.
- **FR-007**: Custom rows (source=db) MUST show Edit, inline status dropdown, and Delete actions.
- **FR-008**: Inherited rows (source≠db) MUST show Edit and Override actions; Delete MUST NOT appear.
- **FR-009**: The list MUST provide a tablenav with: bulk actions (Publish / Unpublish / Delete), source filter, status filter, search input, and page navigation controls (previous/next + page number input).
- **FR-009a**: The list MUST use server-side pagination with a default of 20 abilities per page; the total item count and page count MUST be read from the `X-WP-Total` and `X-WP-TotalPages` REST response headers.
- **FR-010**: Quick-links MUST show counts for All / Published / Draft.
- **FR-011**: Inherited rows MUST be visually distinguished with a lighter background.
- **FR-012**: The inline status dropdown on custom rows MUST immediately call the update API on change (no extra confirm step).

**Add New Form (Variant A)**

- **FR-013**: The Add New form MUST display a slug prefix field showing `acrossai-abilities/` as read-only text with an editable suffix input.
- **FR-014**: The callback type selector MUST use chip-style buttons; selecting a chip MUST show the corresponding config block immediately below.
- **FR-015**: The `php_code` config block MUST render a monospace textarea with a dark background and bright green text.
- **FR-016**: Schema fields MUST validate as JSON on blur and display an inline error for invalid JSON; they MUST NOT block typing.
- **FR-017**: "✓ Add Ability" MUST POST to the create endpoint and redirect to the Edit screen on success.
- **FR-018**: "Save as Draft" MUST override the Auto-register toggle and always save with `status=draft`.
- **FR-019**: The Auto-register toggle ON state MUST map to `status=publish`; OFF state MUST map to `status=draft`.

**Edit Form (Variant A Edit)**

- **FR-020**: The Edit form MUST track saved state vs. draft state; any change MUST show the "● Unsaved changes" indicator in the page title.
- **FR-021**: The sticky bar note MUST only appear when there are unsaved changes.
- **FR-022**: "✓ Save Changes" MUST POST only the fields that differ from the saved state (sparse update); on success the admin remains on the edit form with the dirty indicator cleared and `savedAbility` updated to the server response.
- **FR-023**: The Activity sidebar box MUST display the `updated_at` and `created_at` timestamps from the server.
- **FR-024**: "🗑 Delete Ability" MUST trigger a confirmation dialog before calling the DELETE endpoint.
- **FR-025**: Navigating away with unsaved changes MUST trigger a `beforeunload` browser confirmation.

**Override Form (Variant B)**

- **FR-026**: The Override form MUST render a locked card showing the identity fields (slug, label, category, callback type) as read-only.
- **FR-027**: The locked card header MUST show the provider name and source badge.
- **FR-028**: The Site Permission Override MUST use three chips: Force Block / Inherit (plugin default) / Force Allow, mapping to `site_allowed` values 0 / null / 1.
- **FR-029**: The MCP Type override select MUST include an "inherit (X)" first option showing the plugin's declared value.
- **FR-030**: Annotation override fields MUST display "Plugin declares: X" hint text below each select.
- **FR-031**: Access control role checkboxes MUST be visually disabled while "inherit" is selected.
- **FR-032**: "↩ Clear All Overrides" MUST reset all override fields to null/inherit values.

**Error Handling**

- **FR-035**: When any REST call fails (network error, 4xx, 5xx), the system MUST display an inline dismissible WP-style error notice without navigating away or crashing the page.
- **FR-036**: When the abilities list fails to load, the last successfully loaded data MUST remain visible; a dismissible error notice MUST appear above the table.
- **FR-037**: When a save or delete action fails, the form MUST remain open with the user's current edits intact; a dismissible error notice MUST appear and the save button MUST be re-enabled so the user can retry.

**Styling**

- **FR-033**: All primary buttons, links, focus rings, active chip borders/backgrounds, and active nav highlights MUST use `var(--wp-admin-theme-color)` so the UI adapts to the user's chosen WordPress admin color scheme.
- **FR-034**: Source badge colors MUST be: Custom=`#e0f0ff/theme-color`, Plugin=`#f0e8ff/purple`, Core=`#e8f0e8/green`, Theme=`#fff4e0/amber`.

### Key Entities

- **Ability**: A named WordPress capability that AI tools can invoke. Has source (db/plugin/theme/core), status (draft/publish), callback_type, MCP exposure settings, and override fields. Stored in `wp_acrossai_abilities`.
- **Category**: A slug+label pair returned by `GET /abilities/categories`. Used to group abilities.
- **Draft State**: In-memory React form state that differs from the last-fetched server state; drives the unsaved-changes indicator.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can create a new custom ability (fill form + save) in under 2 minutes on a standard broadband connection.
- **SC-002**: The abilities list renders and is interactive within 2 seconds of page load; pagination loads the next page within 1 second for catalogs of any size.
- **SC-003**: 100% of list view actions (edit, override, delete, bulk, filter, search) complete without a full page reload.
- **SC-004**: Switching between list view and form view preserves the list scroll position and active filters.
- **SC-005**: Unsaved changes are never silently lost — the browser confirmation prompt fires on every navigation attempt when the form is dirty.
- **SC-006**: The UI displays correctly and all interactive elements use the correct accent color when the administrator switches between any of the 9 built-in WordPress admin color schemes.
- **SC-007**: Saving an override for an inherited ability never overwrites that ability's identity fields (slug, label, category, callback_type).

---

## Assumptions

- Spec 009 REST endpoints are deployed and return data; the frontend depends entirely on their shape.
- The `wp_acrossai_abilities` table exists (created in Spec 008).
- The `@wordpress/scripts` build pipeline and `webpack.config.js` are in place; a new webpack entry is sufficient to produce `build/js/abilities.js`.
- The `build/js/abilities.asset.php` manifest is generated by the build and contains the correct dependency array; the PHP enqueue reads from it rather than hardcoding dependencies.
- No new REST endpoints are introduced in this spec — all network calls use the routes already registered in Spec 009.
- `wp_get_ability_categories()` is available in the WordPress version targeted (WordPress 6.x+).
- The Access Control fields (who-can-access, role checkboxes) are UI-only in this spec — they do not yet drive server-side execution permission checks (that belongs to a future access-control spec).
- Mobile/responsive layout is out of scope for this spec; the admin panel is desktop-only.
- The slug prefix `acrossai-abilities/` is fixed and cannot be changed by the admin (matches Spec 009 write controller behavior).
