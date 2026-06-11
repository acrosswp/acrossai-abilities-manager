# Feature Specification: Library Category/Slug Rebrand

**Feature Branch**: `031-library-category-slug-rebrand`
**Created**: 2026-06-11
**Status**: Draft
**Input**: User description: "Update the Ability Library admin page: replace main_key/sub_key/main_key_label/sub_key_label with category/slug/category_label/slug_label across Ability_Definition abstract class, Registry, Config, Processor, REST payload, and JS components. Update the Library admin page to display each ability's name field as the visible row label. On-disk config shape (sub_keys map) is unchanged — no migration needed. Zero concrete subclasses in-repo."

---

## Clarifications

### Session 2026-06-11

- Q: Should a plugin changelog entry and/or semver version bump be a formal deliverable of this feature? → A: Neither — treat as an internal rename; changelog and versioning are handled outside this feature's scope.

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Library page groups abilities by category and shows ability names (Priority: P1)

An administrator opens the Ability Library page. Each card is titled with the add-on's
category label (e.g. "SRE Tools"). When the card is switched to Specific mode, each
individual ability row is labeled by the ability's registered name (e.g.
`acrossai-sre/transient-flush`) rather than an opaque key string. Administrators
immediately understand what each ability does without needing to cross-reference
external documentation.

**Why this priority**: The Library page is the primary discovery surface. Displaying
human-readable ability names instead of raw key strings is the core UX goal of this
feature and unlocks all downstream value.

**Independent Test**: Activate any add-on that registers abilities via `Ability_Definition`.
Navigate to Abilities Manager → Library. Switch one card to Specific mode. Each checkbox
must show the ability's registered name. Can be fully tested without any add-on that uses
the old `main_key`/`sub_key` naming.

**Acceptance Scenarios**:

1. **Given** an add-on has registered abilities, **When** an admin loads the Library page, **Then** each card title displays the human-readable category label (e.g. "Acrossai Core Abilities Plugins") and each ability row displays the human-readable `slug_label` (e.g. "Activate Plugin") — not a raw machine key.
2. **Given** an add-on registered two abilities in the same category, **When** the admin views that card in Specific mode, **Then** both ability names are listed as separate checkboxes.
3. **Given** an ability has no registered name, **When** the admin views its row in Specific mode, **Then** the row falls back to displaying the slug label.

---

### User Story 2 — Saved Library configuration survives the upgrade (Priority: P1)

An administrator who previously configured the Library page (enabling/disabling categories,
switching modes, toggling individual abilities) upgrades the plugin. After the upgrade, their
saved configuration is intact — the same categories are enabled/disabled and the same
individual abilities are checked or unchecked as before.

**Why this priority**: Data loss of admin configuration on upgrade is a P1 regression.
The on-disk config shape uses the same `sub_keys` key format and is deliberately unchanged
by this feature.

**Independent Test**: Using WP-CLI (`wp option get acrossai_library_config --format=json`)
or the admin UI, record the saved config before the feature is deployed. After deployment,
verify the config reads back identically.

**Acceptance Scenarios**:

1. **Given** a Library config was saved before this feature, **When** the plugin is updated, **Then** all enabled/disabled states and mode settings are preserved exactly.
2. **Given** a POST request is made to the Library config REST endpoint using the pre-feature request body format, **When** the response is received, **Then** it returns HTTP 200 and the saved state matches the posted body.

---

### User Story 3 — Breaking contract change fails loudly for external add-ons (Priority: P2)

An external add-on that subclasses `Ability_Definition` with the old method names
(`main_key`, `sub_key`, etc.) fails at PHP's abstract-method contract level — producing
a clear, actionable fatal error — rather than silently misbehaving. Changelog entry and
versioning are handled outside this feature's scope (see Clarifications).

**Why this priority**: No concrete subclasses exist inside this repository, so end-users
are unaffected. The priority is ensuring the failure mode is obvious and diagnosable, not
silent.

**Independent Test**: Install an add-on that extends `Ability_Definition` with the old
method names. Verify WordPress produces a PHP fatal error naming the unimplemented abstract
methods — not a blank page or silent skip.

**Acceptance Scenarios**:

1. **Given** an add-on with the old method names is installed alongside the updated plugin, **When** WordPress loads the plugin, **Then** a clear PHP fatal error (abstract method not implemented) points to the class and method that needs renaming — no silent failure.
2. **Given** the updated plugin is running with no external add-ons installed, **When** WordPress loads, **Then** no errors or warnings are produced.

---

### Edge Cases

- An add-on registers two abilities with the same `category` and `slug` — the second registration must be deduplicated; only one checkbox row appears.
- An ability definition has an empty `name` field — the row label falls back to `slug_label`.
- The Library page loads before any add-on fires the collection filter — the page shows the empty-state message with no PHP errors.
- A site's saved `acrossai_library_config` option contains a category key that no currently-installed add-on registers — the orphaned config entry is silently ignored (no PHP error, no phantom card).

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST remove the four abstract grouping methods (`main_key`, `main_key_label`, `sub_key`, `sub_key_label`) from `Ability_Definition` and derive `category`, `category_label`, `slug`, and `slug_label` automatically inside `push_definition()` from the single `ability()` method's return value — so add-on authors only need to implement `ability()`.
- **FR-002**: The Registry MUST validate and normalize incoming definition arrays against the new field names (`category`, `category_label`, `slug`, `slug_label`) and reject definitions that are missing any of these fields.
- **FR-003**: The Processor MUST read `category` and `slug` from each definition when checking against the saved Library config.
- **FR-004**: The Library admin page MUST display each ability's human-readable `slug_label` (derived from `args['label']`) as the visible row label in Specific mode, with the machine `name` as a fallback when `slug_label` is empty.
- **FR-005**: The on-disk saved Library config shape MUST remain unchanged — category identifiers are used as top-level keys and the inner per-slug map continues to use the `sub_keys` key — so that existing saved configurations are not invalidated.
- **FR-006**: The REST endpoint wire format for the Library config (`/acrossai-abilities-library/v1/abilities/config`) MUST continue to accept and return the same `{enabled, mode, sub_keys}` shape per entry, preserving backwards compatibility with any client that already sends this format.
- **FR-007**: The plugin MUST continue to load and operate normally when no add-on has registered any abilities via `Ability_Definition` (empty Library state is valid).
- **FR-008**: All modified PHP files MUST pass PHPCS (WordPress Coding Standards) and PHPStan level 8 with zero new errors after the rename.
- **FR-009**: All modified JavaScript files MUST build without errors and pass ESLint with zero new errors after the rename.

### Key Entities

- **Ability Definition**: The unit registered by add-ons. Has a `category` (grouping key), `category_label` (card title), `slug` (per-ability key), `slug_label` (per-ability fallback label), `name` (human-readable ability identifier), and `args` (WordPress Abilities API args). Previously used `main_key`/`sub_key`.
- **Library Config**: The saved per-site option (`acrossai_library_config`). Stores a sparse associative array: top-level keys are category identifiers; values are `{enabled, mode, sub_keys}` where `sub_keys` maps slug → bool. The key names `sub_keys` (inner) are intentionally retained on disk.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Every ability row on the Library page displays the ability's human-readable `slug_label` (e.g. "Activate Plugin") as its visible label, with `name` as fallback — zero rows show a raw machine key as the label in 100% of tested scenarios.
- **SC-002**: A saved Library config written by the previous version of the plugin loads and applies correctly after the upgrade — zero configuration loss across upgrade in 100% of tested scenarios.
- **SC-003**: Static analysis (PHPCS + PHPStan level 8) reports zero new errors in all modified files after the rename.
- **SC-004**: A JavaScript build (`npm run build`) completes without errors and the Library admin page renders without browser console errors after the rename.
- **SC-005**: No occurrence of the strings `main_key`, `sub_key`, `mainKey`, or `subKey` (as code identifiers — not as comments explaining the migration or the preserved `sub_keys` storage key) remains in the modified source files after the rename.

---

## Assumptions

- There are zero concrete subclasses of `Ability_Definition` inside *this* plugin repository (verified). 17 concrete subclasses exist in the external `acrossai-core-abilities` plugin — they are backwards-compatible because PHP only fatals on *missing* abstract method implementations, not on extra non-abstract methods. Changelog entry and semver version bump are out of scope — handled separately in the release process.
- The `sub_keys` key in the on-disk config format is intentionally NOT renamed in this feature; only the in-memory / payload field names change.
- The inner `args.category` field in ability definitions (the WordPress Abilities API `category` argument) is at a different array depth than the new top-level `category` grouping field — they do not collide and are independently validated.
- No database schema migration is required: this feature changes only PHP class contracts, PHP array keys in runtime definitions, and JavaScript component props.
- The feature does not require a `npm run build` asset version bump beyond the natural content hash change from the JS component renames.
