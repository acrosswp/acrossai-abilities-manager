# Feature Specification: MCP Tools Pass-through

**Feature Branch**: `029-mcp-tools-passthrough`
**Created**: 2026-06-10
**Status**: Draft
**Input**: User description: "Add a per-ability toggle that lets an admin opt any ability into every MCP server's tool list from a single control in the Abilities admin UI. Storage is a tri-state column (default / opted-in / reserved deny). The filter integration is a no-op when no MCP adapter is present. No new admin pages, no new REST namespaces, no DB migration."

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Opt an ability into every MCP server (Priority: P1)

An administrator opens the Abilities admin list, finds an ability that should be surfaced to every connected MCP client as a callable tool, and flips a single inline toggle. On the next MCP server tool-list resolution, that ability appears as a tool on every connected server — no per-server configuration is required.

**Why this priority**: This is the core value of the feature. Without it, admins must configure each MCP server individually to include an ability, which is repetitive and error-prone.

**Independent Test**: Enable the toggle for one ability, then trigger the MCP server tool-list resolution (e.g., call the discover-abilities tool or equivalent). Confirm the opted-in ability slug appears in the returned tool list.

**Acceptance Scenarios**:

1. **Given** an ability whose pass-as-tool flag is at default (off), **When** the admin clicks the "Pass as Tool" toggle in the Abilities list, **Then** the flag is saved and the ability slug appears in every MCP server's tool list on the next resolution.
2. **Given** an ability with the flag turned on, **When** the admin clicks the toggle to turn it off, **Then** the flag returns to default and the slug no longer appears in MCP server tool lists via this mechanism.
3. **Given** an ability with the flag turned on, **When** that ability is also already listed in a specific server's own tool configuration, **Then** the slug appears only once in that server's tool list (no duplicates).

---

### User Story 2 — Zero-impact on unmodified abilities and servers (Priority: P1)

For every ability that has not been opted in, and for every MCP server that has not changed, the tool list is byte-for-byte identical to what it was before this feature shipped. A site with zero opted-in abilities sees no behavioral change.

**Why this priority**: Any regression in existing MCP tool lists would break connected clients. The feature must be purely additive.

**Independent Test**: Deploy the feature with no abilities opted in, then compare MCP server tool lists before and after deployment. They must be identical.

**Acceptance Scenarios**:

1. **Given** no abilities have the pass-as-tool flag enabled, **When** any MCP server resolves its tool list, **Then** the list is identical to the server's pre-feature behavior.
2. **Given** some abilities are opted in, **When** a different MCP server that already lists one of those ability slugs natively resolves its tool list, **Then** the slug appears only once.

---

### User Story 3 — Protected system abilities cannot be opted in (Priority: P1)

Protected system abilities (the built-in MCP adapter system tools) cannot have their pass-as-tool flag changed. The toggle for these rows is visually disabled in the admin list, and any attempt to set the flag via the API is rejected.

**Why this priority**: System abilities must not be inadvertently duplicated or mis-configured by admins. The guard must be both visible (UI) and enforced (API).

**Independent Test**: Open the Abilities list and locate a protected system ability row. Confirm the toggle cell is rendered in a disabled state and cannot be clicked. Attempt a direct API update for that slug and confirm a rejection response.

**Acceptance Scenarios**:

1. **Given** a protected system ability, **When** the admin views the Abilities list, **Then** the pass-as-tool toggle for that row is rendered disabled and cannot be activated.
2. **Given** a protected system ability, **When** an API request is sent to enable the pass-as-tool flag for that slug, **Then** the API rejects the request with an error response.

---

### User Story 4 — Flag persists across routine plugin lifecycle events (Priority: P2)

Pass-as-tool opt-ins survive routine admin operations such as plugin deactivation and reactivation (without dropping the table). Admins do not need to re-configure the flag after an upgrade or settings change.

**Why this priority**: Volatile flags that reset on routine admin actions erode admin trust and require manual re-configuration work.

**Independent Test**: Opt in two abilities, deactivate the plugin, reactivate, and confirm both abilities are still opted in and still appear in MCP server tool lists.

**Acceptance Scenarios**:

1. **Given** two abilities are opted in, **When** the plugin is deactivated and reactivated without dropping the database table, **Then** both abilities remain opted in and continue to appear in MCP server tool lists.

---

### Edge Cases

- An ability is opted in but later deleted from the database: the deleted ability must not appear in MCP server tool lists (the lookup returns nothing for a non-existent row).
- The MCP adapter passes a malformed or non-array `tools` value in the server config: the injection treats it as an empty list and produces a fresh valid array containing only the opted-in slugs.
- No MCP adapter plugin is active: the pass-as-tool flag is still stored and the admin UI still shows the toggle; the injection simply never runs.
- The toggle is flipped while an MCP client has a cached tool list: the new state takes effect the next time the server resolves its tool list; cached client state is out of scope.
- Two admins simultaneously flip the same ability's toggle: last-write-wins, consistent with how all other ability fields behave.
- API failure during toggle flip: the admin UI displays a transient error toast and reverts the toggle to its previous visual state. No partial state is left on screen.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Admins MUST be able to opt any non-protected ability into "pass as MCP tool" from the Abilities admin list using a single inline control, without navigating to a separate page or triggering a full page reload.
- **FR-002**: When an ability is opted in, every MCP server on the site MUST include that ability's slug in its advertised tool list the next time the tool list is resolved.
- **FR-003**: When an ability is opted out (returned to default), the slug MUST be absent from all MCP server tool lists resolved after that point via this mechanism.
- **FR-004**: When zero abilities are opted in, MCP server tool lists MUST be identical to their pre-feature behavior.
- **FR-005**: If an opted-in ability slug is already present in a server's own tool configuration, the slug MUST appear only once in that server's final tool list.
- **FR-006**: Protected system abilities MUST have their pass-as-tool toggle rendered in a disabled state in the admin UI and MUST be rejected at the API level if a change is attempted.
- **FR-007**: The opt-in state MUST be stored persistently with the ability record and MUST survive plugin deactivation and reactivation without manual re-entry (assuming the database table is not dropped).
- **FR-008**: The pass-as-tool status MUST be visible in the Abilities admin list as a dedicated column.
- **FR-009**: When the MCP adapter plugin is not installed or inactive, the pass-as-tool feature MUST be inert — the admin UI and storage continue to function, but no tool injection occurs.
- **FR-010**: If the MCP adapter provides a non-array value for the server tool list, the injection MUST treat it as an empty list and produce a valid flat list containing only the opted-in slugs.

### Key Entities

- **Ability**: An atomic, addressable operation registered in the system. Has a unique slug and a collection of per-ability settings. `pass_as_tool` is a new tri-state setting on this entity.
- **Pass-as-tool flag**: A per-ability setting with three states — *default* (no injection), *opted in* (inject into every MCP server's tool list), and a reserved *explicit deny* state not exposed in the v1 UI.
- **MCP server tool list**: The list of ability slugs a connected MCP server advertises as callable tools. Resolved at server initialization; this feature merges opted-in slugs into every server's list.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can change a non-protected ability's pass-as-tool state in a single interaction within the Abilities list (no modal, no extra navigation, no page reload).
- **SC-002**: With N abilities opted in, every MCP server on the site advertises exactly those N additional slugs in its tool list, with zero duplicates and zero loss of tools that were already present before the feature shipped.
- **SC-003**: With zero abilities opted in, MCP server tool lists are indistinguishable from their pre-feature state.
- **SC-004**: The pass-as-tool opt-in state is preserved 100% of the time across plugin deactivate/reactivate cycles (assuming the database is intact).
- **SC-005**: Attempts to change the pass-as-tool flag of a protected system ability are rejected 100% of the time at the API level, regardless of what the UI shows.

---

## Clarifications

### Session 2026-06-10

- Q: Should toggling pass-as-tool, or the MCP injection at runtime, or both, be recorded in the ability activity log? → A: Neither — no log entry for flag changes or for runtime injection.
- Q: What should the UI show when the toggle flip fails? → A: Show a transient error toast and revert the toggle to its previous visual state (matches existing status-select pattern).
- Q: What should the pass-as-tool injection do when the existing `tools` value in the server config is not a valid flat list? → A: Treat a non-array `tools` value as an empty list and inject the opted-in slugs into a fresh array.

---

## Assumptions

- The MCP adapter plugin is the sole producer of MCP server tool lists on the site; this feature does not address other MCP runtimes or protocols.
- Per-server opt-in granularity (e.g., "only pass this ability to server X") is explicitly out of scope for v1. The flag is global: opted-in means every MCP server on the site gets the tool.
- The existing early-stage table-recreation policy stands: when a schema change is required, the site operator manually deactivates the plugin, drops the table, and reactivates. This feature does not introduce an automated migration path.
- The existing Abilities REST update endpoint accepts the new field without requiring a new API version or a new namespace.
- MCP server tool-list caching (if any) is the responsibility of the MCP adapter and its clients. This feature exposes state; it does not control cache invalidation.
- The "reserved explicit deny" state (value 0) is stored in the schema for future use but is not surfaced in the v1 admin UI. Admins can only toggle between default and opted-in.
