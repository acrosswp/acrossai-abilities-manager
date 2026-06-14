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

1. **Given** a category card is enabled with mode "All", **When** the page renders, **Then** no per-ability checkbox controls exist anywhere inside that card.
2. **Given** a category card is enabled with mode "Specific" and the category has at least one ability, **When** the page renders, **Then** one checkbox control per ability appears inside the card.
3. **Given** a category card is enabled with mode "Specific" and the administrator has ticked some checkboxes, **When** the administrator switches the mode to "All", **Then** the checkbox list is removed from the page and the previously-ticked selections are cleared from saved configuration.
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
5. **Given** the administrator switches a card with sub-groups from "Specific" to "All", **When** the panel re-renders, **Then** all sub-headings AND all checkboxes disappear from the page together.

---

### User Story 3 — Existing add-ons continue to work unchanged (Priority: P3)

The plugin already ships several add-on packages whose abilities do not declare sub-groups. After this feature lands, every existing add-on must continue to render the same way it did before — a single flat checklist inside "Specific" mode — with no warnings, no broken cards, and no shifts in administrator-visible behavior.

**Why this priority**: Backwards compatibility for previously-shipped add-ons. Lower priority than the new behavior because it is a "do no harm" requirement; failure here would surface immediately during smoke testing of any existing add-on.

**Independent Test**: With this feature in place but no sub-group declarations added to any add-on, the Library page renders identically to the prior release.

**Acceptance Scenarios**:

1. **Given** an add-on whose abilities do not declare sub-groups, **When** the Library page renders, **Then** each category card behaves exactly as it did before this feature (flat checklist under "Specific").
2. **Given** a previously-saved configuration in the database, **When** an administrator opens the Library page after this feature lands, **Then** every toggle, radio, and checkbox reflects the saved state with no spurious "sub_group" keys appearing in the JSON.

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

- **FR-001**: The Library page MUST render the per-ability checkbox list inside a category card only when (a) the card's master toggle is on AND (b) the card's mode is set to "Specific" AND (c) at least one ability is registered in that category.
- **FR-002**: When a category card's mode is "All", the Library page MUST NOT render any per-ability checkbox controls inside that card. Hiding via CSS or a hidden attribute is NOT sufficient — the controls must not exist in the rendered page.
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
- **FR-015**: When an administrator switches a card with declared sub-groups from "Specific" to "All", every sub-heading AND every per-ability checkbox under that card MUST disappear from the page together.
- **FR-016**: Add-on authors MUST be able to add sub-group declarations to a previously-released add-on without breaking installed sites. Sites that update the add-on first and the plugin second (or vice versa) MUST continue to render the Library page without errors.
- **FR-017**: The sub-group heading MUST be presented as a real semantic heading element in the rendered page (so screen readers announce it as a heading) rather than a styled paragraph or span.
- **FR-018**: Sub-group declarations that survive sanitization to an empty string MUST be treated as if no sub-group was declared.

### Key Entities

- **Category card**: Visual unit on the Library page. Owns a master toggle (enabled / disabled), a two-value mode selector ("All" / "Specific"), and zero or more per-ability checkboxes. Maps to one ability category.
- **Ability**: A single registered capability inside a category. Has a stable identifier (slug), a display label, and now an OPTIONAL sub-group attribute that influences display only.
- **Sub-group**: A small, display-only grouping inside a category card's "Specific" panel. Has a sanitized identifier and a human-readable label. Has no representation in saved configuration, REST responses, or execution paths.
- **Library configuration**: The persisted record of per-category enable/disable state, mode, and per-ability selections. Shape is unchanged by this feature.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: For 100% of Library category cards, switching the mode to "All" removes every per-ability checkbox from the rendered page within the same UI interaction (verified by inspecting the rendered page after each toggle).
- **SC-002**: For 100% of Library category cards, switching the mode from "Specific" to "All" with previously-ticked checkboxes results in a saved configuration whose per-ability selection map for that category is empty.
- **SC-003**: After this feature ships, 100% of previously-released add-ons that do not declare sub-groups render their Library cards identically to the prior release (same number of checkboxes, same order, same labels, no headings).
- **SC-004**: When an add-on declares a sub-group on at least one ability, an administrator can identify the heading associated with that ability within 5 seconds of opening the card (qualitative measure: the heading is visually distinct from the checkbox rows).
- **SC-005**: An administrator who saves a Library configuration after this feature ships sees zero "sub_group" keys when inspecting the saved configuration record. Saved configuration size for an unchanged page is identical (byte-for-byte) to a configuration saved before this feature shipped.
- **SC-006**: 0 existing PHPUnit Library tests fail after this feature lands. All new behavior is exercised by added tests; no pre-existing assertion changes meaning.
- **SC-007**: An add-on author can add a sub-group declaration to a single ability and see the new heading on the Library page after one page reload, without touching the saved configuration option.

## Assumptions

- Sub-groups are an opt-in capability for add-on authors. The plugin does not ship default sub-group declarations on any first-party ability in this feature.
- Administrators value scannability inside large categories more than alphabetic sort order; the registration order chosen by add-on authors is the correct order for display.
- The pre-existing two-value mode selector ("All" / "Specific") is the correct interaction shape. A future UX iteration may consolidate the toggle and the radio into a single control, but that is out of scope for this feature.
- The pre-existing master toggle (enabled / disabled) governs whether the entire card body — including the radio and any checkbox / sub-heading content — is visible. This feature does not alter that behavior.
- Sub-group labels are short identifiers (e.g., "Core", "Plugins", "Themes", "Debug Log", "Config"). The Library page does not need pagination, search, or collapse controls inside a single category card for this feature.
- The Library admin page is server-rendered with localized data — administrators reload the page (or navigate away and back) to see newly-declared sub-groups; there is no real-time subscription requirement.
- Existing accessibility patterns on the Library page (focus order, ARIA roles for the toggle and radio) carry over without modification; this feature adds one new heading element per non-empty sub-group only.
- Administrators do not currently expect any change in saved-configuration byte size from a display-only feature; preserving the saved shape exactly is the right guarantee.
