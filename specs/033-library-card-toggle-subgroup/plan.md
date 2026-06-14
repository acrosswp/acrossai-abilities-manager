# Implementation Plan: Library Card Toggle + Optional Sub-Group Display

**Branch**: `032-library-card-toggle-and-subgroup-display` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `specs/033-library-card-toggle-subgroup/spec.md`

> Generated inline (skill orchestrator) — the `/speckit-plan` skill was not auto-invoked per project policy ("user runs spec-kit commands themselves"). All planning was performed in-context against `spec.md`, the project Constitution (v1.4.5+), and `memory-synthesis.md`.

## Summary

A purely additive, display-only feature on the Ability Library admin page in two parts:

1. **Visibility contract (CHANGE-A, revised turn 3)**: The slug-list panel inside each `LibraryCard` is rendered whenever `enabled && slugs.length > 0 && expanded`. The mode setting governs the *row shape* inside the panel:
   - `mode === 'specific'`: each row is an interactive `CheckboxControl` (the existing behavior).
   - `mode === 'all'`: each row is a **read-only label** (a `<div className="acrossai-library-card__slug-readonly">` with a bullet), so admins can see what abilities the card covers without flipping the radio.

   A per-card chevron disclosure button (`@wordpress/icons` `chevronUp` / `chevronDown` inside a `Button` component) lets admins collapse cards once they've reviewed them. The disclosure state is held in a `useState(true)` on the card — default expanded on every fresh page load, no persistence (resets per reload).

   **Revision history**:
   - **Turn 1**: original plan was to codify the existing "panel does not exist in the DOM under `mode === 'all'`" guard at `LibraryCard.js:72`.
   - **Turn 2**: UX review revealed admins couldn't see what abilities the "All" cards covered. Contract revised to "always render the panel; mode governs row shape."
   - **Turn 3**: admins requested an explicit collapse control to keep the page compact once cards were reviewed. Added per-card disclosure (`expanded` state in `useState`).

   The `sub_keys` reset on radio change (`sub_keys: value === 'all' ? {} : slugsConfig`) is preserved across all revisions — switching to "All" still clears the saved selection map, since under "All" every ability is implicitly included.
2. **Sub-group display (CHANGE-B)**: An OPTIONAL `args['sub_group']` field on `Ability_Definition` declarations is hoisted by `push_definition()`, validated by `AcrossAI_Ability_Library_Registry`, surfaced to the JS app via the existing `window.acrossaiAbilityLibraryData` localization, and rendered as a small `<h4>` heading above its matching slug checkboxes inside the Specific panel. Saved-config option shape (`{ enabled, mode, sub_keys }`) and the `sub_keys` slug-keyed map are untouched. No DB migration, no REST schema change, no execution-path change.

## Technical Context

**Language/Version**: PHP 8.1+, JavaScript (ES2022 / JSX), SCSS
**Primary Dependencies**: WordPress 6.9+ core (`@wordpress/components`, `@wordpress/element`, `@wordpress/i18n`), `@wordpress/scripts` for build, existing `AcrossAI_Ability_Library_*` family of classes
**Storage**: Existing `acrossai_library_config` site option — shape **unchanged** (`{ enabled, mode, sub_keys }`), `sub_keys` keyed by slug. No migration.
**Testing**: PHPUnit (PHP-side Registry + Ability_Definition); `@wordpress/scripts test-unit-js` (Jest, optional, JS-side helper unit test)
**Target Platform**: WordPress 6.9+ admin (single-site + multisite), PHP 8.1+
**Project Type**: WordPress plugin (single source tree — `includes/`, `src/js/`, `tests/`)
**Performance Goals**: No measurable change. The added grouping step is `O(slugs.length)` in a single category card; categories typically hold ≤ 20 abilities.
**Constraints**: No REST contract change, no DB-shape change, no new endpoints. Existing Library page render time unchanged.
**Scale/Scope**: ~6 files modified, ≤ 200 LOC net. Single feature module (`Library`).

## Constitution Check

*Gate: Must pass before tasks. Re-check after implementation.*

| Principle | Status | Notes |
|-----------|--------|-------|
| **I — Modular Architecture** | ✅ Pass | All PHP edits stay within `includes/Modules/Library/`. No sibling-module reach. No code duplication — sanitization reuses `AcrossAI_Ability_Library_Config::sanitize_key_field()`. |
| **II — WordPress Standards Compliance** | ✅ Pass (with gate during implement) | PHPCS WPCS, PHPStan level 8, ESLint, Plugin Check must remain clean. No new SQL (production-surface scan covers all edits). No deprecated functions. New docblocks must follow `BUG-PHPCS-DOCBLOCK-CAPITAL` ("The …" prefix). |
| **III — User-Centric Design (DataForm/DataViews)** | ⚠️ Pre-existing deviation, no new violation | The Library page already uses `ToggleControl` / `RadioControl` / `CheckboxControl` primitives — its UI is a categorized toggle-and-checkbox configuration, not a list/table or a form requiring field-level validation. DataViews would be a poor fit (no search / sort / filter / pagination needs); DataForm has no schema for a category card. This feature does NOT introduce new primitives — it inherits the existing pattern from Feature 031. **Action**: document in a one-line "Existing Library page pattern" note in `plan.md` (this section) — no new accepted-deviation entry required. |
| **IV — Security First** | ✅ Pass | No new REST endpoint, form, AJAX handler, or DB write. No new input boundary. The only new input — `args['sub_group']` — is sourced from trusted PHP add-on code (same boundary as `args['category']` and `args['label']`) and sanitized via the existing 100-char `sanitize_key()`-based helper. The Library page does not echo the value into HTML; it is rendered as text content by React, which auto-escapes. |
| **V — Extensibility Without Core Modification** | ✅ Pass | Uses the existing `acrossai_abilities_api_init` filter and the existing `push_definition()` extension point. No new core file is required; add-on authors opt in by adding one key. Feature degrades gracefully — add-ons without `sub_group` continue to work unchanged. |
| **VI — Reusability & DRY** | ✅ Pass | Reuses `AcrossAI_Ability_Library_Config::sanitize_key_field()` for sub_group sanitization. Reuses the same `ucwords(str_replace('-', ' ', …))` label-derivation transform already in `push_definition()` for `category_label`. No new utility class needed. |
| **VII — Definition of Done** | ✅ Pass (gated by implement) | PHPCS / PHPStan-8 / ESLint zero-error gates, PHPUnit tests for new Registry + push_definition paths, optional Jest test for the JS grouping helper, AGENTS.md prefix conformance, `npm run validate-packages` pass. |

### REST `permission_callback` Return Type (MUST)
No new REST routes added. Existing routes that back the Library page are untouched. Constitution gate passes.

### Plugin Check Production Surface
All edited PHP files are within `includes/` (production surface). The optional new error_log call (if added in `validate_and_normalize()` for rejected sub_group) MUST follow `PATTERN-WP-DEBUG-LOG-GUARD` — wrapped in `if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG )` with the `phpcs:ignore` inside the guard. The existing `log_invalid()` method already follows this pattern; the new code path can reuse it.

## Project Structure

### Documentation (this feature)

```text
specs/033-library-card-toggle-subgroup/
├── spec.md                      # Feature specification (✓ exists)
├── memory-synthesis.md          # Memory-md synthesis (✓ exists)
├── plan.md                      # This file (governed-plan output)
├── security-constraints.md      # Inline security review output (this turn)
├── tasks.md                     # /speckit-tasks output (next phase)
└── checklists/
    └── requirements.md          # Spec-quality checklist (✓ exists)
```

### Source Code (repository root)

```text
includes/
└── Modules/
    └── Library/
        ├── Ability_Definition.php                   # CHANGE-B (push_definition pass-through)
        └── AcrossAI_Ability_Library_Registry.php    # CHANGE-B (allowlist + validate optional fields)

src/
└── js/
    └── ability-library/
        └── components/
            ├── LibraryPage.js                        # CHANGE-B (groupDefinitions destructure)
            └── LibraryCard.js                        # CHANGE-A doc comment + CHANGE-B grouping render

src/scss/                                             # CHANGE-B (.acrossai-library-card__subgroup-heading) -- exact file located during impl
                                                      # If no existing file owns .acrossai-library-card__* rules, fold the rule into the existing global admin stylesheet — do NOT create a new SCSS file for one rule.

tests/
└── phpunit/                                          # CHANGE-B: new Registry + Ability_Definition cases (locate via `find tests -name '*Library*'`)
└── jest/                                             # CHANGE-A + CHANGE-B (OPTIONAL): groupBySubGroupPreservingOrder() unit test via named export; LibraryCard mount/unmount assertion via @wordpress/scripts test-unit-js

AGENTS.md                                             # One-line note: "Optional args['sub_group'] on Ability_Definition is display-only and never changes saved config."

build/js/                                             # Regenerated by `npm run build` — no hand edits
```

**Structure Decision**: Single source tree. The feature touches one module (`Library/`) on the PHP side and one bundle entry (`ability-library`) on the JS side. No new directories required.

## Phase 0 — Research (already complete)

Memory synthesis (`memory-synthesis.md`) covered:

- **DEC-LIBRARY-CATEGORY-SLUG-REBRAND** (Feature 031): the simplified `ability()` single-method contract; we extend by adding an OPTIONAL nested field, preserving backwards compatibility for external subclasses.
- **PATTERN-ADDON-FILTER-LATE-INIT**: Registry collects at init P99 — sub-group inherits this timing for free.
- **PATTERN-PROTECTED-SLUGS-JS-LOCALIZE**: PHP-managed values flow to JS through the localized data blob.
- **PATTERN-FEATURE-ASSET-SEPARATION**: Reuse the existing Library SCSS file; don't create a new one for a single rule.
- **PATTERN-NAMED-EXPORT-JEST**: Make `groupBySubGroupPreservingOrder()` a named export so Jest can exercise it without React rendering.
- **BUG-PHPUNIT-TYPED-PROPERTY-SETUP**: Use inline singleton calls in new PHPUnit tests, not typed class properties in `set_up()`.
- **BUG-PHPCS-DOCBLOCK-CAPITAL**: New docblocks must start with "The " or be rephrased.

No outstanding research questions. No `[NEEDS CLARIFICATION]` markers in the spec.

## Phase 1 — Design

### Data Flow

```
add-on Ability_Definition subclass
    └── ability() returns args['sub_group'] (OPTIONAL)
            ↓
Ability_Definition::push_definition()
    └── hoists sub_group to top-level row + auto-derives sub_group_label
            ↓
AcrossAI_Ability_Library_Registry::validate_and_normalize()
    └── sanitize via Config::sanitize_key_field()
    └── strip if survives to empty string (FR-018)
    └── attach to validated $entry alongside category/slug
            ↓
window.acrossaiAbilityLibraryData (existing localized data blob)
            ↓
LibraryPage.js::groupDefinitions()
    └── destructure sub_group / sub_group_label onto slug entries
            ↓
LibraryCard.js
    └── groupBySubGroupPreservingOrder(slugs) (named export)
    └── render <h4 className="acrossai-library-card__subgroup-heading"> per non-empty key
    └── <CheckboxControl> per slug (existing wiring — sub_keys[slug] = bool)
```

The save direction (admin → server) is unchanged: `LibraryCard` writes `sub_keys[slug] = bool`, `AcrossAI_Ability_Library_Config::save_config()` persists it. Sub-group never crosses the save boundary.

### Field Routing Table

| Field | Origin | Carrier | Sanitization | Display | Persisted? |
|-------|--------|---------|--------------|---------|------------|
| `args['sub_group']` | Add-on PHP | `acrossai_abilities_api_init` filter row | `sanitize_key_field()` (100-char + `sanitize_key()`) | Indirect — feeds `sub_group_label` | **No** |
| `args['sub_group_label']` (optional override) | Add-on PHP | Same row | `wp_kses_post()` | `<h4>` text content (React auto-escapes) | **No** |
| (auto-derived) `sub_group_label` | Registry | Same row | `ucwords(str_replace('-', ' ', $clean))` | `<h4>` text content | **No** |
| `enabled` / `mode` / `sub_keys[slug]` | Admin UI | Existing REST save endpoint | Existing `AcrossAI_Ability_Library_Config::sanitize_entry()` | Existing toggle / radio / checkbox | **Yes** (existing option) |

### Edge-Case Handling Decisions

- **Sub-group with whitespace / punctuation**: `sanitize_key()` strips disallowed chars. If the result is `''`, treat as if no sub_group was declared (FR-018). The fail path is silent; no error_log unless `defined( WP_DEBUG_LOG )` — follow `PATTERN-WP-DEBUG-LOG-GUARD`.
- **Multiple add-ons pushing into the same category with disagreeing sub-groups**: Preserve first-seen order. `groupBySubGroupPreservingOrder()` walks slugs once; the order array drives the render order.
- **Single ability under a sub-group**: Still renders the heading + the one checkbox. No "minimum group size" filter — the spec does not require one.
- **Empty `''` sub_group bucket**: Always rendered first, with no `<h4>`. Matches the "Core / Plugins / Themes / Debug Log / Config" example where ungrouped items come first.
- **Stale `sub_keys[slug]` entries** (admin saved a config when the slug existed; slug later de-registered): Display layer never crashes — it iterates over the registered slug list, ignoring the stale entries. Out of scope for this feature; left to a future cleanup task if needed.

### Backwards Compatibility

- Existing add-ons without `args['sub_group']` produce rows without the `sub_group` field. `LibraryCard` renders a flat checklist (the existing path).
- Existing saved `acrossai_library_config` values are unchanged in shape and meaning.
- Existing `Ability_Definition` subclasses (≥17 external subclasses per Feature 031 notes) require no modification.

## Constitution Re-Check (post-design)

| Principle | Status |
|-----------|--------|
| I — Modular Architecture | ✅ Confirmed — all PHP edits inside `Library/`; no sibling-module reach |
| II — WordPress Standards | ✅ Confirmed — no new SQL, no new deprecated functions, no new error_log without WP_DEBUG_LOG guard |
| III — User-Centric Design | ⚠️ As pre-existing deviation — no new primitive introduced |
| IV — Security First | ✅ Confirmed — no new boundary, sanitized via existing helper |
| V — Extensibility | ✅ Confirmed — opt-in field, hook-based |
| VI — DRY | ✅ Confirmed — reuses sanitize_key_field + ucwords transform |
| VII — Definition of Done | ✅ To be enforced during implementation |

## Complexity Tracking

No constitution violations require justification. No new project dependencies, no new modules, no DB migration.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| (none) | — | — |

## Known Plugin Concerns Outside This Feature's Scope

An AI scan flagged the following plugin-wide concern. It is **NOT** in Feature 033's scope and MUST NOT be addressed in this feature's tasks. Documented here so a future spec-kit run does not waste cycles re-investigating it.

| Location | Finding | Status | Reference |
|----------|---------|--------|-----------|
| `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php:715` | "Using Reflection to access a third-party server object's private property bypasses its public API and creates a brittle compatibility risk that can break on upstream updates." (`new \ReflectionClass($server)` to read `McpServer::$component_registry`) | **Intentional — accepted.** Rationale in code at lines 712–713: "Required because `mcp_adapter_server_config` does not exist in installed version." Try/catch silently skips on `ReflectionException` so an upstream rename is non-fatal (injection just no-ops for that server). | `docs/memory/DECISIONS.md` → **DEC-MCP-INJECT-REFLECTION-PATTERN** (Active, 2026-06-11, Feature 029) |

**Why this is out of scope for Feature 033**: the feature changes files inside `includes/Modules/Library/` (PHP), `src/js/ability-library/` (JS), and a single CSS rule. It does not touch `includes/Modules/Abilities/`, MCP injection, or the Override Processor.

**Recommended follow-up (separate feature, not this one)**:
1. Monitor upstream `wordpress/mcp-adapter` for a public injection API — either a stable `mcp_adapter_server_config` filter or an `add_tools()` method on `McpServer`.
2. When such an API ships, replace the Reflection block (lines 712–728) with a public-API call and supersede `DEC-MCP-INJECT-REFLECTION-PATTERN` in `DECISIONS.md`.
3. Until then, the existing implementation is the canonical path. No remediation work is justified by this AI finding alone.

## Open Risks / Soft Conflicts

| ID | Risk | Mitigation |
|----|------|------------|
| R-1 | The constitution wording mandates DataForm/DataViews for "all admin interfaces." The Library page already deviates from this (pre-Feature 031). | Document as a pre-existing deviation in the plan (here) and in the eventual implementation summary. Do NOT introduce a new accepted-deviation entry — Feature 033 changes no UI primitive. |
| R-2 | An add-on author misspells a sub-group identifier and produces TWO sub-headings ("core" and "cores") for related abilities. | Display-only. Not blocking. Documentation in AGENTS.md note + add-on author best practice. |
| R-3 | A future "search inside category" feature would need sub_group in the saved config to remember a per-sub-group collapse state. | Out of scope. If that feature lands, sub_group becomes a save-side concept then — not now. |
| R-4 | The constitution sync impact report at the top of CONSTITUTION.md says v1.4.6, but the footer reads v1.4.5. Cosmetic, not blocking this feature. | Flag to author for a separate housekeeping commit. |

## Phase 2 / 3 / 4

- **Phase 2 (Tasks)**: Generated by `/speckit-tasks` from this plan. Expected scope: ~6–8 tasks covering PHP edits, Registry validation, JS grouping helper + render, CSS rule, PHPUnit tests, optional Jest test, AGENTS.md note.
- **Phase 3 (Implement)**: Per-task; enforce all Definition of Done gates.
- **Phase 4 (Validate)**: Re-run PHPStan / PHPCS / Plugin Check / Jest (if added) / PHPUnit. Manual verification per spec's Acceptance Scenarios.

## References

- [spec.md](./spec.md)
- [memory-synthesis.md](./memory-synthesis.md)
- [Planning narrative](../../docs/planning/032-library-card-toggle-and-subgroup-display.md)
- Constitution: `.specify/memory/CONSTITUTION.md` (v1.4.5+ — Modular Architecture / WP Standards / Security First / Extensibility / DRY / Definition of Done)
