# Feature Specification: Ability Access Control Tab

**Feature Branch**: `003-ability-access-control-tab`
**Created**: 2026-05-16
**Status**: Draft
**Input**: User description: "Add a third tab called 'Access Control' to the ability edit slide-in panel (AbilityEditPanel) that already has 'General' and 'MCP' tabs. The tab renders the wpb-access-control React component from the vendor library at vendor/wpboilerplate/wpb-access-control. Wire the existing AcrossAI_Sitewide_Access_Control PHP class to rest_api_init and add a webpack resolve alias for the component import."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Configure Access Control for an Ability (Priority: P1)

A site administrator opens the ability edit panel for any registered ability, navigates to the new "Access Control" tab, and defines who can access that ability — choosing from everyone, no one, specific WordPress roles, or specific users. The selection is saved and persisted without leaving the panel.

**Why this priority**: This is the core value of the feature. Without it, the Access Control tab has no purpose and administrators cannot restrict or open ability access.

**Independent Test**: Can be fully tested by opening any ability in the edit panel, clicking the "Access Control" tab, selecting an access rule, and verifying the change persists after a page reload.

**Acceptance Scenarios**:

1. **Given** the ability edit panel is open on any ability, **When** the administrator clicks the "Access Control" tab, **Then** the Access Control tab becomes active and the access control UI is displayed.
2. **Given** the Access Control tab is active, **When** the administrator selects a rule (e.g., "Specific Roles" and picks "Editor"), **Then** the selection is saved to the ability via the wpb-ac/v1 REST API without requiring a separate save action.
3. **Given** an access rule was previously saved for an ability, **When** the administrator reopens the edit panel and navigates to the Access Control tab, **Then** the previously saved rule is loaded and displayed correctly.

---

### User Story 2 - REST API Routes Are Available (Priority: P2)

The wpb-ac/v1 REST API routes are registered when WordPress initialises, enabling the AccessControl component to fetch and persist access rules for any ability.

**Why this priority**: The Access Control tab is non-functional if the REST routes are not registered. However, this is a purely technical prerequisite and has no visible UI of its own.

**Independent Test**: Can be fully tested by issuing a request to `/wp-json/wpb-ac/v1/` and confirming a valid JSON response is returned, or by checking that the existing `AcrossAI_Sitewide_Access_Control` class's `register_routes` method is invoked on `rest_api_init`.

**Acceptance Scenarios**:

1. **Given** WordPress is initialised, **When** the REST API is bootstrapped, **Then** the wpb-ac/v1 routes defined in `AcrossAI_Sitewide_Access_Control` are registered and accessible.
2. **Given** the wpb-ac/v1 routes are registered, **When** an authenticated administrator calls a route, **Then** the route responds with the expected data and correct HTTP status codes.

---

### Edge Cases

- What happens when the ability has no slug? The `resourceKey` prop should default to an empty string or be omitted; the component must not throw a JS error.
- What happens when the REST API returns an error (e.g., 403 or 500)? The AccessControl component handles this internally and displays an appropriate error state.
- What happens when the user navigates away from the Access Control tab before saving completes? The component manages its own save lifecycle; no data loss should occur.
- What happens if the wpb-access-control vendor library is missing or corrupted? The webpack build fails at compile time, making the issue immediately visible during development.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The ability edit slide-in panel MUST display a third tab labelled "Access Control" alongside the existing "General" and "MCP" tabs.
- **FR-002**: The "Access Control" tab MUST render the `AccessControl` component from the wpb-access-control vendor library.
- **FR-003**: The `AccessControl` component MUST receive `namespace` set to `"acrossai-abilities"`, `resourceKey` set to the ability's slug, `restApiRoot` and `nonce` sourced from `window.acrossaiAbilitiesSitewide`.
- **FR-004**: The `AcrossAI_Sitewide_Access_Control` PHP class MUST be hooked to the `rest_api_init` WordPress action in `includes/Main.php` so that wpb-ac/v1 REST routes are registered on every request.
- **FR-005**: The webpack configuration MUST include a resolve alias `"@wpb/access-control"` pointing to `vendor/wpboilerplate/wpb-access-control/js/index.js` so the component can be imported in the sitewide JS bundle.
- **FR-006**: The `AccessControl` component MUST self-manage all data fetching and saving — the plugin MUST NOT supply a save handler, draft state, or TabFooter for this tab.
- **FR-007**: No new PHP files, JS entry points, or CSS files MUST be created; all changes are confined to `includes/Main.php`, `webpack.config.js`, and `src/js/sitewide/components/AbilityEditPanel.jsx`.

### Key Entities

- **Ability**: A registered site capability identified by a unique slug. The `resourceKey` in the AccessControl component maps directly to this slug.
- **Access Rule**: The policy stored via the wpb-ac/v1 REST API that defines which users or roles can access a given ability (everyone / no one / specific roles / specific users).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A site administrator can view and interact with the "Access Control" tab on 100% of ability edit panels without a JavaScript error.
- **SC-002**: An access rule change made in the "Access Control" tab persists correctly after a full page reload, verifiable in under 30 seconds.
- **SC-003**: The sitewide JS bundle builds without errors after the webpack alias is added, confirming the vendor library is correctly resolved.
- **SC-004**: The wpb-ac/v1 REST routes respond with valid HTTP 200 responses for authenticated administrator requests, confirming hook registration is correct.
- **SC-005**: The total number of modified files does not exceed 3 (Main.php, webpack.config.js, AbilityEditPanel.jsx).

## Assumptions

- The `AcrossAI_Sitewide_Access_Control` class already exists at `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` and fully implements the required `register_routes` method; no changes to that class are needed.
- The vendor library at `vendor/wpboilerplate/wpb-access-control/js/index.js` exports the `AccessControl` component as a named export and is already present on disk.
- `window.acrossaiAbilitiesSitewide` is already populated (with at minimum `restApiRoot` and `nonce` properties) by the existing PHP sitewide enqueue logic before the React bundle runs.
- The nonce-based `apiFetch` middleware is already registered globally in `src/js/sitewide/index.js`; no additional authentication setup is required for the component.
- The AbilityEditPanel component already uses a tab-switching pattern (e.g., active tab state) that can accept a third tab entry without structural changes to the tab container.
- Mobile responsiveness and accessibility enhancements beyond what the AccessControl component provides out of the box are out of scope for this feature.
