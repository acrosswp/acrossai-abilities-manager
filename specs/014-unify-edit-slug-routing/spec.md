# Feature Specification: Unified Ability Editing — Slug-Based Navigation & Publish Default

**Feature Branch**: `014-unify-edit-override-slug-routing`
**Created**: 2026-05-25
**Status**: Draft

## User Scenarios & Testing

### User Story 1 — Edit Any Ability With One Button (Priority: P1)

An admin looking at the Abilities list sees a single "Edit" button on every row — whether the ability is a custom (db) ability, a plugin ability, a core ability, or a theme ability. Clicking Edit always opens a pre-populated edit form. The form is never blank or stale, even for abilities that have no saved override record yet.

**Why this priority**: The current two-button layout (Edit + Override) causes confusion, and Edit is broken for plugin/core/theme abilities that have no DB row (form opens blank). This is the most visible and disruptive UX failure.

**Independent Test**: Navigate to the Abilities list, find `core/get-environment-info` (a core ability with no override), click Edit. The form opens with all fields pre-filled from the registry. Save works and no 404 error appears.

**Acceptance Scenarios**:

1. **Given** the Abilities list is loaded, **When** viewing any row regardless of source, **Then** exactly one "Edit" action button is visible — no "Override" button appears anywhere.
2. **Given** a plugin ability with no saved override record, **When** clicking Edit, **Then** the form opens immediately with label, description, category, and slug visible and pre-populated from the plugin registration.
3. **Given** a db-source (custom) ability, **When** clicking Edit, **Then** the full edit form opens with all fields visible, including Callback and Schema sections.
4. **Given** any ability edit form is open, **When** the form is loading server-fresh data, **Then** the form shows the list data immediately (no blank flash) and updates silently when the server response arrives.

---

### User Story 2 — Override Metadata for Plugin/Core/Theme Abilities (Priority: P1)

An admin opens a plugin ability (e.g., `ai/get-post-details`) in the edit form. They can edit the label, description, and category. When they save, those values are stored in the database and the merged ability list shows the overridden values. The Callback and Schema sections are not shown (those are defined by the plugin and cannot be changed). A small "Registered by…" line shows the provider and source.

**Why this priority**: Currently these fields are silently stripped by the backend — admins can type values but saving does nothing. This is a data-loss bug.

**Independent Test**: Open `ai/get-post-details`, change the label to "Get Post Details (Custom)", save. Reload the page — the label shows "Get Post Details (Custom)" in both the list and the edit form.

**Acceptance Scenarios**:

1. **Given** a plugin ability edit form is open, **When** label, description, or category is changed and saved, **Then** the saved values persist and appear in the list and edit form on next load.
2. **Given** a plugin ability has an overridden label, **When** the admin clicks "Clear All Overrides", **Then** the label reverts to the plugin-registered value in the list and edit form.
3. **Given** a plugin/core/theme ability edit form, **When** the form is rendered, **Then** the Callback section and Schema section are not visible.
4. **Given** a plugin/core/theme ability edit form, **When** the form is rendered, **Then** the Auto-register toggle is not visible (status has no effect for non-custom abilities).
5. **Given** a plugin/core/theme ability edit form, **When** the form is rendered, **Then** a "Registered by [provider] [source badge]" info line is visible at the top of the Identity section.

---

### User Story 3 — Add Ability Publishes by Default (Priority: P2)

An admin fills in the "Add New Ability" form and clicks the primary "✓ Add Ability" button (not "Save as Draft"). The new ability is created with `status: publish` and appears immediately in the Published tab. Clicking "Save as Draft" still creates a draft.

**Why this priority**: The current default (draft) surprises users — they add an ability and it does not appear in the active list. The published default is the expected behavior for a "create" action.

**Independent Test**: Click "+ Add New Ability", fill in all four required fields, click "✓ Add Ability". Navigate to the Published tab — the ability is listed there, not in Draft.

**Acceptance Scenarios**:

1. **Given** the Add Ability form is filled with valid data, **When** the "✓ Add Ability" (primary) button is clicked, **Then** the created ability has status `publish` and appears in the Published tab.
2. **Given** the Add Ability form is filled with valid data, **When** "Save as Draft" is clicked, **Then** the created ability has status `draft` and appears in the Draft tab.

---

### User Story 4 — Slug Is Permanently Locked After Creation (Priority: P2)

An admin sees the slug field in edit mode and it is clearly read-only — no warning message suggests it was ever changeable. On the create form, a note reads "Once saved, this slug cannot be changed."

**Why this priority**: The current "⚠ Changing the slug will break existing integrations." warning in edit mode implies the field is editable, causing confusion. The slug must be immutable and the UI must make that clear.

**Independent Test**: Open any ability in edit mode. The slug input is disabled/read-only. The "⚠ Changing the slug" warning text is absent. On the Add New form, the text "Once saved, this slug cannot be changed." is visible below the slug input.

**Acceptance Scenarios**:

1. **Given** any ability in edit mode, **When** the Identity section is rendered, **Then** the slug input is read-only and the "⚠ Changing the slug will break existing integrations." warning text does not appear.
2. **Given** the Add Ability (create) form, **When** the Identity section is rendered, **Then** the note "Once saved, this slug cannot be changed." appears below the slug input.

---

### User Story 5 — Bulk Operations Use Slug as Identifier (Priority: P3)

An admin selects multiple custom abilities using the bulk checkboxes and applies "Delete" or a status change. The operations complete successfully. The selection state is keyed by slug, so it is consistent with the slug-based API.

**Why this priority**: This is an internal consistency fix. Without it, bulk operations would break after the slug-based API switch. It does not directly impact end-user-visible behavior beyond correctness.

**Independent Test**: Select three custom abilities, choose "Delete" from Bulk Actions, click Apply. All three are removed. No 404 or type errors appear in the browser console.

**Acceptance Scenarios**:

1. **Given** multiple custom abilities are selected via bulk checkboxes, **When** bulk delete is applied, **Then** all selected abilities are deleted and the list refreshes correctly.
2. **Given** multiple custom abilities are selected, **When** bulk status change is applied, **Then** all selected abilities update to the new status.
3. **Given** a selection contains both custom and non-custom abilities, **When** bulk delete is applied, **Then** the entire operation is blocked and a warning message is shown: "Your selection includes abilities that cannot be deleted. Remove non-custom abilities from your selection and try again." No items are deleted.

---

### Edge Cases

- What happens when an admin tries to save a plugin ability with an empty label (cleared the field)? The field is optional for non-custom abilities — it falls back to the plugin-registered value; the save should succeed.
- What happens when a plugin ability has never been edited (no DB row) and the admin clicks Edit for the first time? The form must populate from the WP registry and first save must create a new DB override record transparently.
- What happens when the slug in the URL contains a forward slash (e.g., `core/get-user-info`)? The URL must encode the slash so the REST route matches exactly one path segment; the backend decodes it correctly.
- What happens when the admin clicks Edit for an ability that exists in the list but no longer exists in the WP registry and has no DB row? The backend returns 404. `AbilityForm.jsx` navigates back to the list view automatically and displays a dismissible error notice: "Ability not found. It may have been removed or the plugin deactivated."
- What happens when bulk operations are applied to a mixed selection containing both custom and non-custom abilities? The entire bulk delete operation is blocked with a warning: "Remove non-custom abilities from your selection and try again." No items are deleted. Bulk status change is unaffected (status changes apply only to custom abilities and non-custom abilities in the selection are silently skipped for status operations since they have no `status` field).
- What happens when a non-custom ability has no DB override record yet and the admin opens the edit form? The Activity sidebar (created_at, updated_at, created_by) is hidden because there is no DB row. It becomes visible after the admin saves for the first time, creating the override record.

---

## Requirements

### Functional Requirements

- **FR-001**: The Abilities list MUST show exactly one "Edit" action button per row for all ability sources (db, plugin, core, theme). The "Override" button MUST be removed.
- **FR-002**: Clicking "Edit" on any ability MUST open the edit form pre-populated with the ability's current data, never blank, regardless of whether a DB override record exists. If `fetchAbility` returns a 404 error, the form MUST navigate back to the list view and display a dismissible error notice: "Ability not found. It may have been removed or the plugin deactivated."
- **FR-003**: The edit form for non-custom (plugin/core/theme) abilities MUST allow label, description, and category fields to be edited and saved to the database.
- **FR-004**: Saved label, description, and category overrides for non-custom abilities MUST be reflected in the abilities list and edit form on subsequent loads.
- **FR-005**: The edit form for non-custom abilities MUST hide the Callback section, Schema section, and Auto-register toggle. These fields are defined by the provider and are not editable.
- **FR-006**: The edit form for non-custom abilities MUST show a "Registered by [provider] [source]" info row at the top of the Identity section. The Activity sidebar (showing `created_at`, `updated_at`, `created_by`) MUST be hidden for non-custom abilities that have no DB override record yet (`savedAbility?.created_at` is null/undefined). It becomes visible after the first save.
- **FR-007**: Saving a non-custom ability that has no existing DB override record MUST create a new override record (upsert). No separate UI action or endpoint is needed.
- **FR-008**: All single-ability API operations (get, update, delete) MUST use the ability's slug as the URL identifier, not an integer id.
- **FR-009**: All bulk operations (bulk delete, bulk status change) MUST use the ability slug as the item identifier. The selection set in the list MUST be keyed by slug. If a bulk delete selection contains any non-custom ability, the operation MUST be blocked entirely and a warning MUST be displayed instructing the admin to remove non-custom abilities from the selection before retrying.
- **FR-010**: Creating a new custom ability via the primary "Add Ability" button MUST default to `status: publish`. The "Save as Draft" button MUST still produce `status: draft`.
- **FR-011**: The ability slug field MUST be read-only in all edit modes (any source). The create form MUST display the text "Once saved, this slug cannot be changed." below the slug input. The edit-mode "⚠ Changing the slug" warning MUST be removed.
- **FR-012**: The edit form sidebar for non-custom abilities MUST include a "Clear All Overrides" button. Clicking it MUST restore all override fields (including label, description, category) to their plugin/core/theme registry values. The button MUST only be visible when `has_override` is `true` (i.e., at least one overridable field differs from the registry default). After a successful clear, `has_override` becomes `false` and the button is hidden.
- **FR-013**: The Delete button in the edit form sidebar MUST only be visible for custom (db-source) abilities. Non-custom abilities cannot be deleted via the UI.
- **FR-014**: The ability read endpoint MUST return merged registry+override data for non-custom abilities (slug-based lookup), and pure DB data for custom abilities.

### Key Entities

- **Custom (db-source) Ability**: An ability created directly in the Abilities Manager admin. It has a DB record with `source = 'db'`. It can be created, updated, and deleted. Slug, Callback, Schema, and Status are all editable.
- **Non-custom Ability**: An ability registered by a plugin, core, or theme (`source` is `plugin`, `core`, or `theme`). It has a corresponding WP registry entry. It may optionally have a DB override record. It cannot be deleted. Its Callback, Schema, and Status are not editable via the Manager. Its label, description, and category can be overridden.
- **Override Record**: A DB row for a non-custom ability that stores the admin's chosen overrides (label, description, category, site_allowed, MCP settings, etc.). Created on first save of a non-custom ability edit.
- **Merged Ability Response**: The API response shape for a non-custom ability that combines the WP registry values with any DB overrides, including `_registry`, `_override`, and `has_override` metadata. `has_override` is `true` only when at least one overridable field (including label, description, category) holds a non-null, non-empty value that differs from the registry default; it is `false` when all overridable fields are null or match registry defaults (i.e., after "Clear All Overrides").

---

## Success Criteria

### Measurable Outcomes

- **SC-001**: Every ability row in the list — regardless of source — shows exactly one action button labelled "Edit". Zero "Override" buttons appear.
- **SC-002**: Clicking Edit on a non-custom ability with no existing DB row opens the form fully populated within one render cycle. Zero blank form states visible to the user.
- **SC-003**: An admin can complete a plugin ability label override (open edit, change label, save, verify in list) in under 60 seconds.
- **SC-004**: A new ability created with the primary "Add Ability" button appears in the Published tab immediately with no additional status-change step needed.
- **SC-005**: All write operations on abilities (create, update, delete) complete with zero 404 errors attributable to id-vs-slug mismatch after the migration.
- **SC-006**: PHPStan level 8 reports zero errors. PHPCS reports zero errors. ESLint reports zero new errors. Webpack build exits clean.
- **SC-007**: Bulk select and bulk delete of any three custom abilities completes successfully with zero browser console errors.

---

## Assumptions

- WordPress 6.9+ is required (plugin minimum is already WP 6.9). `wp_get_ability()` is available without a polyfill.
- All database columns needed for label, description, category, and source overrides already exist in the abilities table schema. No DB migration is needed.
- The existing `AcrossAI_Abilities_Query::save_override()` method already implements upsert semantics (INSERT if not found, UPDATE if found). No new DB method is needed.
- The `manage_options` capability check on all REST endpoints is sufficient for this feature. No new role or permission handling is introduced.
- Label, description, and category overrides are **admin UI display only**. They affect only what the Abilities Manager admin shows in its list and edit form. `AcrossAI_Ability_Override_Processor` (which injects overrides into the live WP registry at boot) is NOT in scope for this feature and MUST NOT be modified.
- The frontend `encodeURIComponent()` call and WordPress REST API's automatic percent-decoding are sufficient to handle slugs containing forward slashes. No special routing middleware is required.
- The `format_merged_ability()` formatter already produces the response shape (`ability_slug`, `label`, `id`, `source`, `status`, `has_override`, `_registry`, `_override`) that the frontend reducers expect. If it does not, aligning it is in scope.
- Mobile/responsive layout is out of scope for this feature. Changes are admin-panel only.
- Multisite is out of scope. The plugin does not support multisite (per AGENTS.md).

---

## Clarifications

### Session 2026-05-25

- Q: When bulk delete is applied to a selection containing both custom and non-custom abilities, what should happen? → A: Fail the entire operation and show a warning to remove non-custom abilities from the selection before retrying. No items are deleted.
- Q: Do label, description, and category overrides need to be reflected in the live WP Abilities registry at runtime (affecting other consumers such as the MCP adapter), or are they admin UI display only? → A: Admin UI display only. `AcrossAI_Ability_Override_Processor` is out of scope for this feature.
- Q: When `fetchAbility` returns a 404 (e.g., the plugin was deactivated), what should the edit form show? → A: Navigate back to the list automatically and show a dismissible error notice: "Ability not found. It may have been removed or the plugin deactivated."
- Q: After "Clear All Overrides" nulls every overridable field, what should `has_override` return and should the "Clear All Overrides" button remain visible? → A: `has_override` becomes `false` when all overridable fields are null/default. The button is hidden when `has_override` is `false`.
- Q: For a non-custom ability with no DB override record yet, what should the Activity sidebar (created_at, updated_at, created_by) show in the edit form? → A: Hide the Activity sidebar entirely until the first save creates a DB record. Guard: `savedAbility?.created_at` must be non-null.
