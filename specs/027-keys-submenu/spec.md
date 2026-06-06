# Feature Specification: Abilities Keys Admin Submenu

**Feature Branch**: `027-keys-submenu`  
**Created**: 2026-06-06  
**Status**: Draft  
**Input**: User description: "Add an Abilities admin submenu under the acrossai-abilities-manager parent that renders a DataViews grid of main_key cards…"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin Enables or Disables an Ability Group (Priority: P1)

As a site administrator, I want to toggle entire ability groups on or off from the Abilities admin page, so that I can control which AI capabilities are exposed through the plugin's abilities API.

**Why this priority**: Disabling an entire group is the most common governance action — an admin typically wants to allow or block all abilities from a given add-on at once.

**Independent Test**: Navigate to the Abilities admin page, find any ability group card, flip the master toggle to OFF, and confirm that all abilities within that group are no longer active. This is testable without any sub-ability configuration.

**Acceptance Scenarios**:

1. **Given** the admin is on the Abilities page, **When** they toggle a group's master switch to OFF and wait for auto-save, **Then** that group's abilities are disabled and the saved state persists on page reload.
2. **Given** a group is disabled, **When** the admin toggles it back to ON, **Then** the group's abilities become active again with their previously saved mode and sub-ability selections intact.
3. **Given** the admin has no previous configuration, **When** they visit the Abilities page, **Then** all groups are displayed as enabled by default.

---

### User Story 2 - Admin Selects "All" or "Specific" Mode for a Group (Priority: P2)

As a site administrator, I want to choose between enabling all sub-abilities in a group or only specific ones, so that I can provide precise control over what capabilities are available.

**Why this priority**: Fine-grained sub-ability control is the second most common governance action and is the reason the Specific mode exists.

**Independent Test**: Find an enabled ability group, switch it from "All" to "Specific" mode, check a subset of sub-ability checkboxes, and verify that only the selected sub-abilities remain active.

**Acceptance Scenarios**:

1. **Given** a group is enabled in "All" mode, **When** the admin switches to "Specific" mode, **Then** per-sub-ability checkboxes appear and the admin can individually select which sub-abilities to enable.
2. **Given** a group is in "Specific" mode with some sub-abilities checked, **When** the admin switches back to "All" mode, **Then** the checkbox selections are preserved (not cleared) for future use, and all sub-abilities in the group are treated as active.
3. **Given** a group is in "Specific" mode with no sub-abilities checked, **When** auto-save runs, **Then** no abilities from that group are registered.

---

### User Story 3 - Add-on Registers Abilities That Appear in the Admin UI (Priority: P3)

As an add-on developer, I want my add-on's abilities to appear automatically in the Abilities admin UI once I register them via a WordPress filter hook, without requiring my add-on to depend on plugin-specific PHP classes.

**Why this priority**: The integration contract for add-ons must be stable and loosely coupled; it is foundational for multi-add-on deployments.

**Independent Test**: Install a sample add-on that registers abilities via the `acrossai_abilities_api_init` filter, load the Abilities admin page, and confirm the add-on's ability groups and sub-abilities appear in the grid.

**Acceptance Scenarios**:

1. **Given** an add-on registers ability definitions via the `acrossai_abilities_api_init` filter, **When** an admin views the Abilities page, **Then** those abilities appear grouped under their declared main_key label with their declared sub_key entries listed.
2. **Given** an add-on that was active is deactivated, **When** the admin views the Abilities page, **Then** the add-on's groups no longer appear, but previously saved toggle configuration for those groups is preserved.
3. **Given** a deactivated add-on is reactivated, **When** the admin views the Abilities page, **Then** the add-on's groups reappear with the previously saved toggle configuration restored.

---

### User Story 4 - Auto-Save Configuration Without Manual Action (Priority: P2)

As a site administrator, I want my toggle changes saved automatically after I stop interacting with the UI, so that I never have to click a "Save" button.

**Why this priority**: Auto-save removes a friction point and reduces the risk of lost changes.

**Independent Test**: Make a toggle change, wait briefly, navigate away, return to the page, and confirm the change persisted.

**Acceptance Scenarios**:

1. **Given** the admin changes any toggle or checkbox, **When** they pause interaction, **Then** the change is automatically saved within approximately one second.
2. **Given** a save is in progress, **When** the admin makes another change, **Then** the save is debounced and only the latest state is persisted.
3. **Given** the initial page load, **When** the abilities config is fetched, **Then** no save is triggered (initial load does not overwrite stored config).

---

### Edge Cases

- What happens when an add-on registers a definition with an ability name that already exists? (The later registration for the same ability name overwrites the earlier one within the filter pass.)
- How does the system handle an ability group toggle when the abilities API is not available? (Registration is silently skipped; the admin UI remains functional.)
- What happens when 300–1000 ability definitions are registered? (The admin grid must support search and pagination so the page remains usable.)
- What happens when a POST to save config fails? (The UI should surface an error notice; the local state should not be discarded.)

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST display an "Abilities" submenu page under the main plugin admin menu.
- **FR-002**: The Abilities page MUST render a grid of ability group cards, one card per distinct main_key registered by active add-ons.
- **FR-003**: Each ability group card MUST include a master ON/OFF toggle that enables or disables all sub-abilities within the group.
- **FR-004**: Each ability group card MUST include a mode selector with two options: "All" and "Specific".
- **FR-005**: When a group's mode is "Specific", the card MUST display individual checkboxes for each sub_key registered under that main_key.
- **FR-006**: Sub-ability checkboxes MUST NOT be visible when a group's mode is "All".
- **FR-007**: The grid MUST support search and pagination to handle 300–1000 definitions without degrading usability.
- **FR-008**: Configuration changes MUST be automatically persisted without a manual save action, debounced so rapid changes produce a single save.
- **FR-009**: The initial page load MUST NOT trigger a save; only user-initiated changes after load may trigger saves.
- **FR-010**: Add-ons MUST be able to register ability definitions using only a WordPress filter hook and a plain array schema, with no dependency on plugin-specific PHP classes.
- **FR-011**: Each add-on ability definition MUST declare exactly four manager-specific fields (`main_key`, `main_key_label`, `sub_key`, `sub_key_label`) in addition to standard ability registration arguments.
- **FR-012**: The system MUST validate each registered definition and silently skip invalid ones without blocking other add-ons.
- **FR-013**: The plugin MUST register only abilities whose main_key is enabled and whose sub_key is permitted by the saved configuration.
- **FR-014**: A disabled main_key MUST cause all its sub-abilities to be skipped at registration time.
- **FR-015**: In "All" mode, all sub-abilities under an enabled main_key MUST be registered.
- **FR-016**: In "Specific" mode, only sub-abilities explicitly marked enabled in the saved config MUST be registered.
- **FR-017**: Configuration MUST default to enabled/all for any main_key not present in the saved config.
- **FR-018**: In "Specific" mode, any sub_key not explicitly present in the saved config MUST default to disabled.
- **FR-019**: Saved configuration MUST be retained when an add-on is deactivated, so reactivation restores prior toggle state.
- **FR-020**: The admin page MUST be accessible only to users with the `manage_options` capability.
- **FR-021**: The configuration read/write endpoint MUST require `manage_options` capability and a valid REST nonce.
- **FR-022**: Add-ons remain responsible for registering their own ability categories; the manager does not register categories.

### Key Entities

- **Ability Definition**: A named AI capability declared by an add-on, containing standard ability registration fields plus `main_key`, `main_key_label`, `sub_key`, and `sub_key_label`.
- **Main Key**: A top-level grouping label for related abilities (e.g., "content", "media"). Maps to a card in the admin grid.
- **Sub Key**: A specific ability slot within a main_key (e.g., "summarize", "generate"). Maps to a checkbox within a card when mode is "Specific".
- **Ability Config**: The persisted admin toggle state for each main_key (enabled, mode, per-sub_key enabled flags). Stored independently from ability definitions.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An admin can enable or disable an ability group and the saved state reflects the change on the next page load.
- **SC-002**: An admin can switch a group between "All" and "Specific" modes; sub-ability checkboxes appear in Specific mode and disappear in All mode.
- **SC-003**: Only abilities whose configuration permits registration are exposed through the abilities API; disabled abilities are never registered.
- **SC-004**: An add-on's ability definitions appear in the admin grid within one page load after the add-on is activated.
- **SC-005**: Saved configuration survives add-on deactivation and reactivation without data loss.
- **SC-006**: The Abilities admin page remains navigable with search and pagination when 300 or more ability definitions are registered.
- **SC-007**: Configuration changes are persisted within approximately one second of the user stopping interaction with the UI.
- **SC-008**: The initial load of the Abilities page does not write to saved configuration.

## Assumptions

- The `manage_options` capability is the correct permission gate for ability toggle administration (administrator-level users only).
- Add-ons register their own ability categories on the appropriate WordPress action; the manager does not infer or create categories from definition data.
- Each ability name submitted by an add-on follows the WordPress naming convention (`namespace/ability-name`); definitions with non-conforming names are silently skipped.
- Ability definitions are cheap static arrays constructed at filter time; expensive services are expected to be lazy-loaded inside execution callbacks, not at definition time.
- The admin grid's search and pagination targets a reasonable response for up to 1,000 ability definitions on standard WordPress hosting.
- Configuration is stored as a site option (not per-user), so toggle changes apply globally.
- Auto-save failure surfaces a visible error notice to the admin; no silent failure is acceptable.
- The configuration read/write REST endpoint is at `/acrossai-abilities/v1/abilities/config`, separate from the existing plugin REST namespace.
- No new Composer or npm packages are required; all dependencies are already declared in the project.
