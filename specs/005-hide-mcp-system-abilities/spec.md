# Feature Specification: Hide MCP Adapter System Abilities

**Feature Branch**: `005-hide-mcp-system-abilities`  
**Created**: 2026-05-19  
**Status**: Ready for Planning  

## User Scenarios & Testing

### User Story 1 — Site Admin Excludes System Abilities from Manager (Priority: P1)

A WordPress site administrator uses the Abilities Manager to view and manage ability overrides. However, MCP adapter system abilities (`mcp-adapter/discover-abilities`, `mcp-adapter/execute-ability`, `mcp-adapter/get-ability-info`) are internal infrastructure and should not appear in the admin UI — they clutter the list and confuse users who are trying to manage application-facing abilities.

**Why this priority**: Core P1 requirement — hiding system infrastructure from the UI improves user experience and prevents accidental misconfiguration of internal abilities.

**Independent Test**: Load the Abilities Manager page (`/wp-admin/admin.php?page=acrossai-abilities-manager`); search for "mcp-adapter"; verify no results are returned and the total count is unaffected.

**Acceptance Scenarios**:

1. **Given** a fresh WordPress site with Abilities Manager installed, **When** an admin navigates to the Abilities Manager page, **Then** the list does NOT contain `mcp-adapter/discover-abilities`, `mcp-adapter/execute-ability`, or `mcp-adapter/get-ability-info`, AND the total ability count reflects the exclusion.
2. **Given** an admin using the search feature, **When** they search for "mcp-adapter" or "discover", **Then** zero results are returned.
3. **Given** an admin using the source filter (plugin/theme/core/db), **When** they filter by source, **Then** the filtered results do NOT include protected abilities, AND pagination counts reflect the exclusion.

---

### User Story 2 — Plugin Developer Extends Protected Abilities List (Priority: P2)

A third-party plugin developer builds integrations with the Abilities Manager and wants to mark additional internal abilities (e.g., private `my-plugin/internal-helper`) as protected so they don't clutter the UI.

**Why this priority**: P2 extensibility — allows plugin ecosystem to maintain clean separation between user-facing and system abilities without requiring core changes.

**Independent Test**: Add a custom filter listener for `acrossai_abilities_manager_protected_slugs`; verify a custom slug is excluded from list and single-ability endpoints.

**Acceptance Scenarios**:

1. **Given** a plugin adds a filter to `acrossai_abilities_manager_protected_slugs`, **When** that filter returns a custom slug list, **Then** the custom slugs are merged with defaults and respected by all REST endpoints.
2. **Given** REST clients query `GET /sitewide/abilities`, **When** custom protected slugs are registered, **Then** custom slugs are absent from results.

---

### User Story 3 — REST Client Receives 404 for Protected Abilities (Priority: P1)

A client application (e.g., script or CLI tool) attempts to retrieve a single MCP adapter ability via the REST API. The API must clearly reject access by returning HTTP 404, not exposing internal infrastructure details.

**Why this priority**: Critical for API consistency and security posture — 404 signals "not available" without leaking implementation.

**Independent Test**: Call `GET /wp-json/acrossai/v1/sitewide/abilities/mcp-adapter/discover-abilities`; verify HTTP 404 response.

**Acceptance Scenarios**:

1. **Given** a REST client calls `GET /sitewide/abilities/{slug}` where `slug` is a protected ability, **When** the request is processed, **Then** the response is HTTP 404 with an appropriate error message.
2. **Given** a protected ability slug, **When** a REST client includes it in search parameters or filters, **Then** it is absent from results AND the response does not expose any indication that the ability exists.

---

## Requirements

### Functional Requirements

- **FR-001**: System MUST exclude `mcp-adapter/discover-abilities`, `mcp-adapter/execute-ability`, and `mcp-adapter/get-ability-info` from all `GET /sitewide/abilities` list responses (results, totals, pagination).
- **FR-002**: System MUST return HTTP 404 when `GET /sitewide/abilities/{slug}` is called with a protected ability slug.
- **FR-003**: Filtering (search, source filter, sort, page) MUST treat protected abilities as non-existent — they do not appear in filtered results AND do not affect total counts or pagination.
- **FR-004**: Protected slugs list MUST be defined in a single centralized location (no duplication across files).
- **FR-005**: Protected slugs list MUST be extensible via a WordPress filter (`acrossai_abilities_manager_protected_slugs`) so third-party plugins can add custom protected slugs.
- **FR-006**: Write endpoints (save override, bulk action, delete override) are NOT affected — write operations MUST continue to function normally (out of scope for this feature).
- **FR-007**: Filtering MUST occur server-side in the REST layer — the frontend UI must not implement duplicate filtering logic.

### Key Entities

- **Protected Ability Slug**: A WordPress ability name (e.g., `mcp-adapter/discover-abilities`) marked as internal infrastructure and excluded from the Manager UI.
- **Protected Slugs List**: Centralized, extensible array maintained in `AcrossAI_Protected_Abilities` class.

---

## Edge Cases

- **Large Ability Registry**: When a site has 1000+ registered abilities, 3 of which are protected — pagination must correctly exclude protected abilities from `X-WP-Total` and `X-WP-TotalPages` headers.
- **Search Term Partial Match**: If an admin searches for "adapter", protected "mcp-adapter/*" abilities must still be excluded.
- **Custom Protected Slugs**: If a plugin registers custom protected slugs via filter, verify they are correctly excluded alongside defaults.
- **Filter Applied + Protected Slugs**: If admin applies a source filter ("plugin") AND search term, protected abilities must be absent even if they would match both criteria.

---

## Success Criteria

✅ **User Visibility**: MCP adapter system abilities are completely absent from the Abilities Manager UI — users cannot see, search, or filter them.

✅ **REST API Compliance**: `GET /sitewide/abilities` list excludes protected slugs; `GET /sitewide/abilities/{protected-slug}` returns HTTP 404.

✅ **Pagination Accuracy**: Total counts, page counts, and pagination reflect only non-protected abilities.

✅ **Extensibility**: Third-party plugins can extend the protected list via `acrossai_abilities_manager_protected_slugs` filter.

✅ **Server-Side Only**: All filtering logic lives in PHP REST controllers — no frontend filtering workarounds.

✅ **Backward Compatibility**: Write endpoints and other manager functionality are unaffected.

✅ **Code Quality**: Zero PHPCS violations, PHPStan level 8 pass, inline documentation for all functions.

---

## Assumptions

1. The three MCP adapter abilities (`mcp-adapter/discover-abilities`, `mcp-adapter/execute-ability`, `mcp-adapter/get-ability-info`) are always registered by the MCP adapter plugin and will exist in all environments.
2. Sites using the Abilities Manager already have MCP adapter installed (not a new dependency).
3. Write operations (save, bulk reset, delete) do not need to change — they will naturally exclude protected slugs because protected abilities are not surfaced to the UI.

---

## Known Constraints

- Read-only operations: The feature only affects read endpoints (`GET`). Write endpoints (`POST`, `DELETE`, `PATCH`) are out of scope.
- No UI changes: The feature does not add configuration UI for managing protected slugs — it's filter-driven for developers.

