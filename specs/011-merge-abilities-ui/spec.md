# Feature Specification: Merge Abilities UI & Decommission Sitewide App

**Feature Branch**: `011-merge-abilities-ui`  
**Created**: 2026-05-24  
**Status**: Draft  
**Input**: Decommission sitewide React app, merge abilities React app into main manager page, and update webpack/PHP infrastructure.

## Clarifications

### Session 2026-05-24

- Q: Should multisite support be in scope for this change? → A: Out of scope — this change targets single-site installations only; multisite compatibility is deferred to a future task.
- Q: Must SC-001 verification begin from a clean build output directory? → A: Yes — a clean step removing prior build output is required as a precondition before running `npm run build` for verification.
- Q: Should `AcrossAI_Abilities_Menu.php` be retained as a stub or fully deleted? → A: Fully deleted — the class file and all references to it must be removed as part of this change.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Consolidated Abilities Manager Page (Priority: P1)

As a WordPress administrator, I want all ability management functionality to be available on the main Abilities Manager page, so that I do not need to navigate between two separate admin pages to manage abilities.

**Why this priority**: This is the core goal of the feature — consolidating two pages into one removes user confusion and navigation overhead. Without this, no other story has context.

**Independent Test**: Navigate to `?page=acrossai-abilities-manager`. The abilities UI renders fully and all ability management interactions work correctly. No separate "Custom Abilities" page is needed.

**Acceptance Scenarios**:

1. **Given** a WordPress admin navigates to the Abilities Manager page, **When** the page loads, **Then** the full abilities management interface is visible and functional within that single page.
2. **Given** a WordPress admin views the left-hand admin menu, **When** the plugin menu is expanded, **Then** there is no "Custom Abilities" submenu item — only the top-level Abilities Manager entry exists.
3. **Given** a WordPress admin attempts to access `?page=acrossai-abilities-custom`, **When** the page is requested, **Then** WordPress shows a "Page not found" or equivalent response because the submenu no longer registers that slug.

---

### User Story 2 - Clean Build Output (Priority: P2)

As a developer maintaining the plugin, I want the build process to only produce assets for active applications (abilities and logger), so that dead code from the decommissioned sitewide app is not shipped in the plugin package.

**Why this priority**: Shipping unused compiled assets increases plugin size and creates maintenance confusion. This is a developer-facing quality requirement that supports the broader consolidation.

**Independent Test**: Run `npm run build`. Inspect the build output directory — `sitewide.js` and `sitewide.css` are absent, while `abilities.js`, `abilities.css`, `logger.js`, and `logger.css` are present.

**Acceptance Scenarios**:

1. **Given** the build pipeline is configured, **When** `npm run build` is executed, **Then** the build completes successfully with no errors and no `sitewide.js` or `sitewide.css` files in the output directory.
2. **Given** the build pipeline is configured, **When** `npm run build` is executed, **Then** `abilities.js`, `abilities.css`, `logger.js`, and `logger.css` are present in the output directory.

---

### User Story 3 - Abilities JS/CSS Loads on Manager Page (Priority: P2)

As a WordPress administrator, I want the Abilities Manager page to load the correct JavaScript and stylesheet, so that the abilities interface is styled and interactive.

**Why this priority**: Without the correct assets enqueued on the right page, the React app will not mount and the UI will be non-functional.

**Independent Test**: Open browser DevTools on `?page=acrossai-abilities-manager`. Confirm `abilities.js` and `abilities.css` are loaded. Confirm `sitewide.js` and `sitewide.css` are not loaded anywhere.

**Acceptance Scenarios**:

1. **Given** an admin visits `?page=acrossai-abilities-manager`, **When** the browser loads the page, **Then** `abilities.js` and `abilities.css` are present in the page's loaded resources.
2. **Given** an admin visits any admin page, **When** the browser loads the page, **Then** `sitewide.js` and `sitewide.css` are not loaded in the page's resources.
3. **Given** the abilities page loads, **When** the DOM is inspected, **Then** `window.acrossaiAbilitiesManager` is defined and populated with the expected configuration data.

---

### User Story 4 - Logs Page Unaffected (Priority: P1)

As a WordPress administrator, I want the Logs page to continue functioning exactly as before, so that existing logging workflows are not disrupted by the abilities UI consolidation.

**Why this priority**: This is a non-regression requirement. The logger application is independent and must not be impacted by any changes made to the abilities or sitewide applications.

**Independent Test**: Navigate to the Logs admin page. Verify the logs table renders, filters work, and the correct logger assets are loaded.

**Acceptance Scenarios**:

1. **Given** an admin visits the Logs page, **When** the page loads, **Then** the logs table renders correctly and `logger.js` / `logger.css` are loaded.
2. **Given** the plugin is updated with the new build, **When** all existing log entries are viewed, **Then** all log functionality behaves identically to before the change.

---

### Edge Cases

- What happens if a user has bookmarked `?page=acrossai-abilities-custom`? WordPress will display a standard "page not found" response since the submenu is no longer registered.
- What happens if residual compiled `sitewide.js` or `sitewide.css` files exist in the build directory from a previous build? The new build must not regenerate them; existing stale files should be cleaned manually or via a clean build step.
- What if a cached page still loads the old `sitewide.js`? Browser cache may serve stale assets; this resolves naturally with cache expiration or a forced refresh.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The "Custom Abilities" submenu page (`?page=acrossai-abilities-custom`) MUST no longer be registered or accessible after this change.
- **FR-002**: The main Abilities Manager page (`?page=acrossai-abilities-manager`) MUST render the full abilities management React application.
- **FR-003**: The abilities React application MUST mount to its designated root element on the main manager page.
- **FR-004**: The `window.acrossaiAbilitiesManager` configuration object MUST be available on the main manager page at the time the React application initialises.
- **FR-005**: The build pipeline MUST NOT produce sitewide compiled assets (JS or CSS) after this change.
- **FR-006**: The build pipeline MUST continue to produce abilities and logger compiled assets (JS and CSS).
- **FR-007**: The logger application and its admin page MUST remain fully functional and unmodified in behaviour.
- **FR-008**: All existing PHP business logic, database classes, and REST controllers MUST remain intact and unmodified.
- **FR-009**: The source directories for the sitewide React application (`src/js/sitewide/` and `src/scss/sitewide/`) MUST be removed from the codebase.
- **FR-010**: The `AcrossAI_Abilities_Menu` class file (`admin/Partials/AcrossAI_Abilities_Menu.php`) and all references to it (autoloader entries, hook wiring, instantiation calls) MUST be fully removed from the codebase as part of this change. Note: this class is a UI/admin menu handler, not business logic, so its deletion does not conflict with FR-008.

### Key Entities

- **Abilities Manager Page**: The single top-level WordPress admin page (`?page=acrossai-abilities-manager`) that hosts all ability management functionality after consolidation.
- **Abilities React Application**: The React app located in `src/js/abilities/` that provides the abilities management UI; mounts to `#acrossai-abilities-root`.
- **Sitewide React Application**: The obsolete React app in `src/js/sitewide/` that is being decommissioned and removed.
- **Logger Application**: The independent React app for the Logs page; must remain untouched.
- **Build Pipeline**: The webpack-based asset compilation process that produces JS/CSS bundles from source.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After removing prior build output (clean step), `npm run build` completes with exit code 0 and produces zero files matching `sitewide.*` in the build output directory.
- **SC-002**: The Abilities Manager admin page loads without JavaScript errors and the abilities interface renders within normal page load time (no subjective regression vs. pre-change behaviour; no explicit millisecond target — this is a qualitative non-regression criterion).
- **SC-003**: Zero regressions on the Logs admin page — all log listing, filtering, and display functionality behaves identically to before the change.
- **SC-004**: The WordPress admin sidebar contains exactly one entry for this plugin (no "Custom Abilities" submenu), reducing navigation complexity for administrators.
- **SC-005**: The `src/js/sitewide/` and `src/scss/sitewide/` directories no longer exist in the repository after the change is applied.

## Assumptions

- The abilities React application (`src/js/abilities/`) is already fully functional and ready to serve as the sole UI for the main manager page; no abilities UI feature development is in scope for this change.
- The `#acrossai-abilities-root` mount point is already the correct identifier used by the abilities React app's entry point.
- The logger application is completely independent of both the abilities and sitewide applications; no shared code or state exists between them.
- Browser cache effects on previously-enqueued sitewide assets are outside the scope of this change and resolve naturally through WordPress asset versioning and cache expiration.
- PHPCS and PHPStan validations must pass after PHP changes; no new warnings or errors may be introduced.
- The singleton pattern and "named variable before Loader call" coding convention must be preserved in all PHP changes per agency standards.
- Multisite support is explicitly out of scope for this change; only single-site WordPress installations are targeted. Multisite compatibility is deferred to a future task.
- `AcrossAI_Abilities_Menu` is a UI/admin menu handler class (not business logic); its full deletion satisfies the decommission intent and does not conflict with the constraint to preserve PHP business logic.
