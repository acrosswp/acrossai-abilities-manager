# Feature Specification: Library Card Toggle + Optional Sub-Group Display

**Feature Branch**: `032-library-card-toggle-and-subgroup-display`
**Created**: 2026-06-14
**Status**: Draft
**Input**: User description: "Two display-only changes to the Ability Library page. (A) On each LibraryCard, the slug-checkbox panel must be rendered only when mode === 'specific'. When mode === 'all', the slug-checkbox panel must NOT be in the DOM. The current code already implements this; this feature codifies it as a behavior contract and adds regression coverage. (B) Add an OPTIONAL sub-group display layer to Ability_Definition. Subclasses MAY return a 'sub_group' key inside the ability() spec's 'args' array. When present, the Library page renders an h-level heading above the slug checkbox(es) belonging to that sub-group inside the Specific panel. Sub-group is display-only — it does NOT change the saved config shape, the sub_keys map (keyed by slug), or any execution path."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Hide checkboxes under "All" mode (Priority: P1)

A site administrator opens the Ability Library admin page. For each category card, they want a clear, unambiguous control: when the card is set to "All", every ability in that category is enabled and the per-ability checkbox list is hidden entirely (no empty rows, no greyed-out checkboxes). When they switch the card to "Specific", the per-ability checkbox list appears so they can pick exactly which abilities are enabled.

**Why this priority**: This is the foundational visibility contract for the Library page. Without a deterministic show/hide rule, administrators see leftover checkboxes when toggling between modes, the saved configuration can drift away from the visible UI state, and screen-reader users encounter unreachable controls. This is the primary UX promise of the page.

**Independent Test**: An administrator opens any category card, clicks "All" — the checkbox list disappears from the page (not just visually hidden). They click "Specific" — the checkbox list reappears. They tick a checkbox under "Specific", click "All" again — when they return to "Specific", no leftover ticks remain. No code-side knowledge is required to validate.

**Acceptance Scenarios**:

1. **Given** a category card is enabled with mode "All", **When** the page renders, **Then** the ability list is visible as **read-only label rows** (no checkbox controls), so the administrator can see what abilities the card covers without flipping the radio.
2. **Given** a category card is enabled with mode "Specific" and the category has at least one ability, **When** the page renders, **Then** one checkbox control per ability appears inside the card.
3. **Given** a category card is enabled with mode "Specific" and the administrator has ticked some checkboxes, **When** the administrator switches the mode to "All", **Then** the rows collapse from checkboxes to read-only labels AND the previously-ticked selections are cleared from saved configuration.
4. **Given** a category card has the master toggle off, **When** the page renders, **Then** neither the "All/Specific" radio nor any checkbox list is visible inside the card.
5. **Given** a category card is enabled with mode "Specific" but the category has zero registered abilities, **When** the page renders, **Then** no empty checkbox container is rendered.

---

### User Story 2 — Optional sub-headings inside the "Specific" panel (Priority: P2)

An add-on author publishes a category (for example, a File Manager add-on) that contains many abilities covering several conceptual areas — file CRUD, plugin file operations, theme file operations, debug log, and configuration files. Today these abilities show as a single flat checklist, making it hard for administrators to find the right one. The author wants to declare an optional sub-heading on each ability so that the Library page renders the checklist with small dividers labelled "Core", "Plugins", "Themes", "Debug Log", and "Config". Abilities that do not declare a sub-heading continue to render in a single ungrouped list at the top of the panel.

**Why this priority**: It improves scannability of large categories without changing any saved configuration, REST contract, or execution path. It is a non-breaking add-on capability — every existing add-on continues to work unchanged.

**Independent Test**: An add-on author updates a File Manager add-on so that File / Create / Edit / Delete File declare the "Core" sub-group, plugin-file abilities declare "Plugins", and so on. An administrator opens the Library page and sees five labelled sub-headings inside the "Specific" panel of the File Manager card. The author then removes the sub-group declarations and reloads; the panel falls back to a flat list with no errors.

**Acceptance Scenarios**:

1. **Given** an ability declares a sub-group, **When** the Library page renders the parent category's "Specific" panel, **Then** a heading row appears immediately above that ability and any sibling abilities sharing the same sub-group.
2. **Given** several abilities in the same category share the same sub-group, **When** the panel renders, **Then** they appear under one heading row, in the same registration order they were declared in.
3. **Given** an ability does not declare a sub-group, **When** the panel renders, **Then** the ability appears under no heading and before any abilities that do declare a sub-group.
4. **Given** an ability declares a sub-group and the administrator ticks its checkbox, **When** the configuration is saved and the page reloads, **Then** the saved configuration is identical in shape to a configuration produced without sub-groups (the sub-group never appears in the saved data).
5. **Given** the administrator switches a card with sub-groups from "Specific" to "All", **When** the panel re-renders, **Then** every checkbox row is replaced by a read-only label row and the sub-group headings are re-rendered above their groups; the interactive checkbox controls themselves are gone.

---

### User Story 3 — Existing add-ons keep their saved-state and interactive behavior (Priority: P3)

The plugin already ships several add-on packages whose abilities do not declare sub-groups. After this feature lands, every existing add-on must keep working: its saved configuration must continue to load and round-trip in the exact same shape (`{ enabled, mode, sub_keys }`); its interactive "Specific"-mode checkboxes must look and behave exactly as before; and the per-card master toggle and All/Specific radio must continue to drive saved state unchanged. The card layout itself is **additively enhanced** by Feature 033 (a chevron disclosure button is present; under "All" mode the same abilities are now visible as a read-only label list rather than being hidden) — these are visual additions, not regressions.

**Why this priority**: Saved-state and interactive behavior must not regress. Lower priority than the new behavior because it is a "do no harm" requirement; failure here would surface immediately during smoke testing of any existing add-on.

**Independent Test**: With this feature in place but no sub-group declarations added to any add-on, (a) every saved `acrossai_library_config` entry round-trips byte-for-byte; (b) every "Specific"-mode checkbox renders with the same label and order as before; (c) no add-on card emits a console error or warning.

**Acceptance Scenarios**:

1. **Given** an add-on whose abilities do not declare sub-groups, **When** the Library page renders in "Specific" mode, **Then** each card shows the same checkboxes in the same order with the same labels as the prior release (no sub-group headings).
2. **Given** a previously-saved configuration in the database, **When** an administrator opens the Library page after this feature lands, **Then** every toggle, radio, and checkbox reflects the saved state with no spurious "sub_group" keys appearing in the JSON.
3. **Given** an add-on whose abilities do not declare sub-groups, **When** the Library page renders in "All" mode, **Then** the card shows the ability list as read-only label rows (turn-2 addition) — this is an additive enhancement over the prior release and MUST NOT alter saved state.

---

### Edge Cases

- An ability declares a sub-group value that contains punctuation, mixed case, or spaces. The Library page must display it consistently (sanitized to a key-like form, with a human-readable label) and never echo unsafe markup.
- Multiple add-ons register abilities into the same category but disagree on sub-group naming or order. The Library page must preserve registration order and create one heading per distinct sub-group, in first-seen order.
- An ability declares a sub-group but no other ability in the same category shares it. The single ability appears under its own heading row.
- An administrator's saved configuration contains stale slug keys (abilities that were de-registered after the config was saved). The display layer must not crash; the saved configuration shape stays `{ enabled, mode, sub_keys }`.
- An add-on declares a sub-group on an ability whose parent category has only one ability. The single ability appears under its sub-group heading; no other layout regressions occur.
- An administrator rapidly toggles the All/Specific radio multiple times. The Library page must show the correct panel for the final state with no flicker, no DOM artifacts, and no orphaned sub-group headings without their children.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Library page MUST render an ability **list panel** inside a category card whenever (a) the card's master toggle is on AND (b) at least one ability is registered in that category AND (c) the card's per-card **disclosure** is expanded. The mode setting governs the *shape* of each row in that panel (interactive checkbox vs read-only label), not whether the panel exists at all. **(Turn 3)** Each card has a chevron disclosure button that defaults to expanded on every page load (no persistence) and toggles the panel between expanded and collapsed when clicked.
- **FR-002**: When a category card's mode is "All", the Library page MUST render each ability row as a **read-only label** (a plain text row with a bullet, no checkbox control). Hiding the panel entirely is NOT permitted — admins MUST be able to see what abilities the card covers without having to switch to "Specific" mode. **Revision note**: this requirement was changed from "panel MUST NOT exist in DOM" after a UX review during implementation; the original "no DOM" rule made the "All" mode opaque to administrators.
- **FR-003**: When an administrator switches a category card from "Specific" to "All", the saved configuration for that category MUST have its per-ability selections cleared in the same save action.
- **FR-004**: The Library page MUST continue to use a two-value mode selector (the existing "All" and "Specific" radio choices). The saved mode field MUST continue to store exactly one of these two values.
- **FR-005**: Add-on ability declarations MUST be able to OPTIONALLY include a sub-group identifier. Existing declarations without a sub-group MUST continue to validate and display without change.
- **FR-006**: When an ability declares a sub-group, the Library page MUST render a small heading immediately above the checkbox row for that ability (and for any sibling abilities in the same category that share the same sub-group identifier) inside the "Specific" panel only.
- **FR-007**: Abilities without a declared sub-group MUST appear above any abilities that do declare a sub-group, with no heading prefix.
- **FR-008**: The order of abilities inside a "Specific" panel MUST follow the order in which the abilities are registered with the plugin. Sub-group grouping MUST NOT re-sort or alphabetize the list.
- **FR-009**: Sub-group identifiers received from add-on declarations MUST be sanitized to the same safe key form (lowercase, hyphen-friendly, length-bounded) already used for category and ability identifiers.
- **FR-010**: When an ability declares a sub-group, the Library page MAY auto-derive a human-readable label by capitalizing the sanitized identifier (replacing hyphens with spaces). Add-on authors MAY supply an explicit sub-group label; when supplied, it MUST be preferred over the auto-derived label.
- **FR-011**: The saved configuration option that backs the Library page MUST NOT gain any new top-level or nested key in this feature. Saved entries continue to contain exactly the existing shape: an "enabled" flag, a "mode" string, and a per-ability map. No sub-group key MUST appear anywhere in the saved data.
- **FR-012**: The on-disk per-ability map MUST continue to be keyed by ability slug. Sub-group identifiers MUST NOT participate in any saved configuration lookup, write, or comparison.
- **FR-013**: The REST endpoint(s) that serve the Library page MUST NOT add or remove response keys for this feature. Existing response shapes are preserved.
- **FR-014**: Each ability's runtime resolution (whether it is enabled or disabled at the moment of execution) MUST be unaffected by sub-group declarations. Sub-group is a display-only concept.
- **FR-015**: When an administrator switches a card with declared sub-groups from "Specific" to "All", every interactive `CheckboxControl` row MUST be replaced by a read-only label row within the same UI interaction. Sub-group headings MUST continue to render above their respective groups (now above read-only rows). The administrator MUST NOT see any orphaned heading without rows or any leftover checkbox control.
- **FR-016**: Add-on authors MUST be able to add sub-group declarations to a previously-released add-on without breaking installed sites. Sites that update the add-on first and the plugin second (or vice versa) MUST continue to render the Library page without errors.
- **FR-017**: The sub-group heading MUST be presented as a real semantic heading element in the rendered page (so screen readers announce it as a heading) rather than a styled paragraph or span.
- **FR-018**: Sub-group declarations that survive sanitization to an empty string MUST be treated as if no sub-group was declared.
- **FR-019 (turn 3)**: Each category card MUST present a chevron disclosure button when there is at least one ability registered AND the master toggle is on. Clicking the button MUST toggle the ability list panel between expanded and collapsed states. The disclosure state MUST persist for the lifetime of the page (no reload across navigations is required); a fresh page load MUST default to expanded.
- **FR-020 (turn 3)**: The disclosure button MUST be presented as an accessible control (button element with an `aria-expanded` attribute reflecting the current state and a tooltip / accessible label that distinguishes "Expand ability list" from "Collapse ability list").

### Key Entities

- **Category card**: Visual unit on the Library page. Owns a master toggle (enabled / disabled), a two-value mode selector ("All" / "Specific"), and zero or more per-ability checkboxes. Maps to one ability category.
- **Ability**: A single registered capability inside a category. Has a stable identifier (slug), a display label, and now an OPTIONAL sub-group attribute that influences display only.
- **Sub-group**: A small, display-only grouping inside a category card's "Specific" panel. Has a sanitized identifier and a human-readable label. Has no representation in saved configuration, REST responses, or execution paths.
- **Library configuration**: The persisted record of per-category enable/disable state, mode, and per-ability selections. Shape is unchanged by this feature.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For 100% of Library category cards, switching the mode to "All" replaces every interactive per-ability `CheckboxControl` with a non-interactive read-only label row within the same UI interaction (verified by inspecting the rendered page after each toggle — `querySelectorAll('.acrossai-library-card .components-checkbox-control')` MUST be empty for that card; the corresponding read-only labels MUST be present).
- **SC-002**: For 100% of Library category cards, switching the mode from "Specific" to "All" with previously-ticked checkboxes results in a saved configuration whose per-ability selection map for that category is empty.
- **SC-003**: After this feature ships, 100% of previously-released add-ons that do not declare sub-groups continue to render their **interactive Specific-mode checkboxes** identically to the prior release (same number of checkboxes, same order, same labels, no sub-group headings). The new chevron disclosure button and the read-only ability list under "All" mode are additive UI enhancements — they MUST NOT be considered regressions, and SC-003 explicitly excludes them from the "identical to prior release" comparison.
- **SC-004**: When an add-on declares a sub-group on at least one ability, an administrator can identify the heading associated with that ability within 5 seconds of opening the card (qualitative measure: the heading is visually distinct from the checkbox rows).
- **SC-005**: An administrator who saves a Library configuration after this feature ships sees zero "sub_group" keys when inspecting the saved configuration record. Saved configuration size for an unchanged page is identical (byte-for-byte) to a configuration saved before this feature shipped.
- **SC-006**: 0 existing PHPUnit Library tests fail after this feature lands. All new behavior is exercised by added tests; no pre-existing assertion changes meaning.
- **SC-007**: An add-on author can add a sub-group declaration to a single ability and see the new heading on the Library page after one page reload, without touching the saved configuration option.

## Out of Scope — Known Plugin Concerns

The following plugin-wide concern has been flagged by an AI scan. It is NOT addressed by this feature and MUST NOT be conflated with Feature 033's scope. It is documented here so that future spec-kit runs do not re-investigate it.

- **Reflection on third-party `McpServer::$component_registry` at `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php:715`**.
  Flagged: "Using Reflection to access a third-party server object's private property bypasses its public API and creates a brittle compatibility risk that can break on upstream updates."
  **Status**: Intentional and currently the canonical injection path. Documented in:
  - The code itself (lines 712–713 carry the rationale: "Required because `mcp_adapter_server_config` does not exist in installed version").
  - `docs/memory/DECISIONS.md` → **DEC-MCP-INJECT-REFLECTION-PATTERN** (Active, 2026-06-11, Feature 029): canonical injection is `mcp_adapter_init` P20 + Reflection on `McpServer::$component_registry`; `mcp_adapter_tools_list` is display-only and not callable.
  **Why out of scope for Feature 033**: this feature touches only `includes/Modules/Library/` (PHP) and `src/js/ability-library/` (JS). The Abilities module's MCP injection path is unrelated.
  **Suggested follow-up (separate feature)**: monitor upstream `wordpress/mcp-adapter` releases for a public injection API (e.g., a stable `mcp_adapter_server_config` filter or an `add_tools()` method on `McpServer`). When one ships, replace the Reflection block with the public API and supersede `DEC-MCP-INJECT-REFLECTION-PATTERN`.

## Assumptions

- Sub-groups are an opt-in capability for add-on authors. The plugin does not ship default sub-group declarations on any first-party ability in this feature.
- Administrators value scannability inside large categories more than alphabetic sort order; the registration order chosen by add-on authors is the correct order for display.
- The pre-existing two-value mode selector ("All" / "Specific") is the correct interaction shape. A future UX iteration may consolidate the toggle and the radio into a single control, but that is out of scope for this feature.
- The pre-existing master toggle (enabled / disabled) governs whether the entire card body — including the radio and any checkbox / sub-heading content — is visible. This feature does not alter that behavior.
- Sub-group labels are short identifiers (e.g., "Core", "Plugins", "Themes", "Debug Log", "Config"). The Library page does not need pagination, search, or collapse controls inside a single category card for this feature.
- The Library admin page is server-rendered with localized data — administrators reload the page (or navigate away and back) to see newly-declared sub-groups; there is no real-time subscription requirement.
- Existing accessibility patterns on the Library page (focus order, ARIA roles for the toggle and radio) carry over without modification; this feature adds one new heading element per non-empty sub-group only.
- Administrators do not currently expect any change in saved-configuration byte size from a display-only feature; preserving the saved shape exactly is the right guarantee.
