# Feature Specification: Add-ons Page Integration

**Feature Branch**: `026-addons-page-integration`  
**Created**: 2026-06-04  
**Status**: Draft  
**Input**: Integrate wpboilerplate/addons-page into the acrossai-abilities-manager plugin via a local path repository, instantiate AddonsPage inside define_admin_hooks(), and append required WordPress.org README sections from the package template.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - View Add-ons Submenu (Priority: P1)

A site administrator navigating the Abilities Manager top-level menu in wp-admin sees an "Add-ons" submenu entry. Clicking it opens the Add-ons page rendered by the wpboilerplate/addons-page package.

**Why this priority**: This is the primary deliverable — without the submenu entry there is no Add-ons page at all. All other stories depend on it.

**Independent Test**: Can be fully tested by navigating to wp-admin → Abilities Manager → Add-ons and confirming the page loads without errors.

**Acceptance Scenarios**:

1. **Given** the plugin is active, **When** an admin visits the Abilities Manager menu in wp-admin, **Then** an "Add-ons" submenu item is visible beneath the existing Logs and Settings entries.
2. **Given** the admin clicks "Add-ons", **When** the page loads, **Then** the Add-ons page renders without PHP fatal errors or JavaScript console errors.
3. **Given** existing submenus (Logs, Settings) were present before this change, **When** the plugin is activated with the new code, **Then** those submenus still appear and function correctly.

---

### User Story 2 - Existing Submenus Unaffected (Priority: P2)

A site administrator continues to access the Logs and Settings submenus without disruption after the Add-ons page is introduced.

**Why this priority**: Regression protection is critical; breaking existing navigation would immediately impact all users of the plugin.

**Independent Test**: Navigate to each existing submenu (Logs, Settings) after the change is deployed and confirm each page still loads and functions as before.

**Acceptance Scenarios**:

1. **Given** the updated plugin is active, **When** an admin navigates to Abilities Manager → Logs, **Then** the Logs page renders correctly with no errors.
2. **Given** the updated plugin is active, **When** an admin navigates to Abilities Manager → Settings, **Then** the Settings page renders correctly and settings can be saved.

---

### User Story 3 - README Sections Present for WordPress.org Compliance (Priority: P3)

A plugin reviewer on WordPress.org can read the Installation, External Services, and Privacy Policy sections in README.txt, satisfying the plugin directory guidelines for plugins that connect to external services.

**Why this priority**: Required for WordPress.org publication compliance but does not affect runtime behaviour of the plugin.

**Independent Test**: Open README.txt and confirm the three sections are present with content copied verbatim from the package template.

**Acceptance Scenarios**:

1. **Given** the updated README.txt, **When** a reviewer reads it, **Then** it contains `== Installation ==`, `== External Services ==`, and `== Privacy Policy ==` sections with content from the package template.
2. **Given** the README.txt already has existing sections, **When** the three new sections are appended, **Then** the existing content is not altered.

---

### Edge Cases

- What happens when the wpboilerplate/addons-page package is not installed (e.g., vendor directory missing)? The plugin should produce a clear error or fail gracefully rather than a silent white screen.
- How does the system handle a name collision if another plugin registers a submenu with the same slug? The Add-ons page package should use its own unique submenu slug derived from the parent slug.
- What if `ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE` constant is undefined at the point of instantiation? This should not happen given it is defined in the main plugin file before `Main.php` is loaded, but any PHP notice should be caught in testing.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST declare a Composer path repository pointing at the local clone of the wpboilerplate/addons-page package so the package can be installed without a Packagist release.
- **FR-002**: The plugin MUST include `wpboilerplate/addons-page` in its Composer `require` block using the `@dev` stability flag.
- **FR-003**: The `composer update wpboilerplate/addons-page` command MUST complete without error and produce a `vendor/wpboilerplate/addons-page/` directory.
- **FR-004**: `define_admin_hooks()` in `includes/Main.php` MUST instantiate `\WPBoilerplate\AddonsPage\AddonsPage` with the plugin menu slug (`acrossai-abilities-manager`) and the plugin file constant (`ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE`).
- **FR-005**: The instantiation in `Main.php` MUST be placed after the Settings submenu block and before the BerlinDB/abilities registration lines, grouped with other admin-menu registrations.
- **FR-006**: No additional loader `add_action` calls MUST be made for the AddonsPage instance — the constructor handles its own hook registration.
- **FR-007**: `README.txt` MUST contain an `== Installation ==` section copied verbatim from `vendor/wpboilerplate/addons-page/docs/readme-template.txt`.
- **FR-008**: `README.txt` MUST contain an `== External Services ==` section copied verbatim from the same template file.
- **FR-009**: `README.txt` MUST contain a `== Privacy Policy ==` section copied verbatim from the same template file.
- **FR-010**: Existing content in `README.txt` MUST remain unchanged after the three sections are appended.
- **FR-011**: No changes MUST be made to existing admin menus, hooks, REST endpoints, or Freemius SDK configuration beyond what the AddonsPage constructor performs automatically.

### Key Entities

- **AddonsPage**: The package class (`\WPBoilerplate\AddonsPage\AddonsPage`) that registers the submenu, enqueues assets, and wires AJAX/Freemius hooks. Receives the plugin menu slug and plugin file path as constructor arguments.
- **composer.json path repository**: A Composer repository declaration of type `"path"` pointing at the local package clone, enabling local development without a Packagist release.
- **ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE**: The PHP constant defined in the main plugin file (`__FILE__`) that identifies the plugin's entry point — passed as the second argument to AddonsPage.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An "Add-ons" submenu entry appears in the Abilities Manager admin menu within 100% of wp-admin page loads after the plugin is updated — zero instances where the submenu is absent.
- **SC-002**: Zero PHP fatal errors, warnings, or notices are introduced on any wp-admin page as a result of this change.
- **SC-003**: All three existing submenus (top-level menu, Logs, Settings) continue to function without regression in 100% of navigation attempts.
- **SC-004**: The `composer run phpstan` static analysis command passes with no new errors after the change.
- **SC-005**: PHPCS reports no new errors on the changed production PHP files.
- **SC-006**: README.txt contains all three required WordPress.org sections (`== Installation ==`, `== External Services ==`, `== Privacy Policy ==`) with content matching the package template verbatim.

## Assumptions

- The local clone of the package is present at the path specified in the spec (`/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/wpb-addons-page/`) before `composer update` is run.
- The relative Composer path `../../../wpb-addons-page` resolves correctly from the plugin directory to the local clone.
- The `automattic/jetpack-autoloader` (already at `^5.0`) is compatible with the autoloading requirements of `wpboilerplate/addons-page`.
- `ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE` is defined in the main plugin entry file before `Main.php` is loaded, so it is safe to reference inside `define_admin_hooks()`.
- The AddonsPage constructor self-registers all required WordPress hooks; no manual `add_action` wiring is needed in the plugin's loader.
- The package's `docs/readme-template.txt` file exists after `composer update` and contains the three required sections.
- No Freemius SDK credentials or account configuration are required for the Add-ons page to render (the page works without a Freemius account, showing an upsell UI or empty state).
