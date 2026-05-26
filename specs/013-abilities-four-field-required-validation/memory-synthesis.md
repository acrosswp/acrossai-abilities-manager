# Memory Synthesis

## Current Scope

Feature 013 enforces four required fields (ability_slug, label, description, category) end-to-end: React form validation in `AbilityForm.jsx` (create/edit modes only), PHP validator tightening in `AcrossAI_Abilities_Validator`, processor registrability guard in `AcrossAI_Abilities_Processor`, and REST create-only presence guards in `AcrossAI_Abilities_Write_Controller`. No DB, sanitizer, migration, or webpack changes.

## Relevant Decisions

- **DEC-UTILITY-STATIC-ONLY** (Reason Included: `validate_description()` must be a static method; no singleton on validator utility, Status: Active, Source: DECISIONS.md)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason Included: Custom form UI is the approved pattern; inline `<div className="field-error">` is consistent — no DataForm mandate, Status: Active, Source: DECISIONS.md)
- **DEC-NAMESPACE-CONVENTION** (Reason Included: PHP namespace for new method stays `AcrossAI_Abilities_Manager\Includes\Utilities`, Status: Active, Source: DECISIONS.md)
- **DEC-EARLY-404-REST-CHECK** (Reason Included: Presence guards in create_ability fire after sanitize and before validate_ability — same fail-fast pattern, Status: Active, Source: DECISIONS.md)
- **DEC-NODE-20-BUILD-REQUIRED** (Reason Included: Build validation must use `nvm use 20 && npm run build`, Status: Active, Source: DECISIONS.md)
- **CLARIFY-Q1 (B): Server-strict presence guards** (Reason Included: All creates including draft-status require non-empty label/description/category. T007 guards are unconditional — no `$is_draft` branch. Status: Active, Source: clarify session 2026-05-25)
- **CLARIFY-Q2 (B): No load-time errors in Edit mode** (Reason Included: formErrors initialise empty; errors show only on blur or save attempt, never on page load of an existing ability. Status: Active, Source: clarify session 2026-05-25)
- **CLARIFY-Q5 (A): forceDraft bypass removed** (Reason Included: T010 handleSave() gate applies to ALL save paths (forceDraft=true or false) in create/edit mode. No bypass. Status: Active, Source: clarify session 2026-05-25)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason Included: No new hook wiring needed; all existing hooks in Main.php remain unchanged, Source: CONSTITUTION.md §I)
- **AC-REST-SPLIT** (Reason Included: `AcrossAI_Abilities_Write_Controller` is a sub-controller; only a 3-guard addition — no split threshold crossed, Source: CONSTITUTION.md §I)
- **ARCH-UNIFIED-ABILITIES-STORAGE** (Reason Included: No DB changes; `is_row_registrable()` guard does not touch storage, Source: ARCHITECTURE.md)
- **FR-004 + aria-disabled** (Reason Included: All three primary save button instances in AbilityForm.jsx must carry `aria-disabled={hasRequiredErrors}` alongside the opacity/pointer-events style. Tab order preserved — no HTML disabled attribute. Source: spec.md FR-004, clarify Q3)
- **FR-017 + maxLength** (Reason Included: description `<textarea>` must carry `maxLength={1000}`. No character counter required. Source: spec.md FR-017, clarify Q4)

## Accepted Deviations

- **DEV1 / DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason Included: Custom form UI is the accepted pattern for Abilities Admin; field-level error divs consistent with existing `slugError`/`inputSchemaError` pattern, Status: Accepted-Deviation)

## Relevant Security Constraints

- **SEC-02** (Reason Included: Presence guards fire on sanitized `$fields` — order is sanitize → presence guard → `validate_ability()` → hook, Source: security-constraints.md)
- **SEC-04** (Reason Included: `empty()` vs strict comparison — `'' === trim(...)` throughout T002, T003, T004, T006, T007. No `empty()` misuse., Source: security-constraints.md)
- **PHPCS strict profile** (Reason Included: All new PHP must use `trim()` + strict `=== ''` comparisons; docblocks with correct `@since`, `@param`, `@return`; long descriptions prefixed with "The ", Source: CONSTITUTION.md §II)

## Related Historical Lessons

- **BUG-PHPCS-DOCBLOCK-CAPITAL** (Reason Included: New PHP docblocks — long descriptions starting with function name need "The " prefix; short descriptions need manual capitalization; phpcbf won't fix either)
- **BUG-PHPCBF-TABS** (Reason Included: PHP files use tabs after phpcbf; str_replace edits on PHP must use `\t` not spaces)
- **BUG-PARTIAL-HOOK-FIELDS** (Reason Included: Presence guards affect create_ability only; after_create hook already receives full row — no change needed)

## Conflict Warnings

- **None.** Clarification Q1/B resolved the only hard conflict (draft-save vs server-strict). The plan's T007 unconditional guards are now consistent with FR-013/SC-003.
- **SCSS no-op confirmed**: `.field-error` rule already exists at admin.scss:1258 (`color: $red; font-size: 11px; margin-top: 4px;`). T017 is a no-op.

## Retrieval Notes

- Index entries considered: 18 of 20 budget
- Source sections read: DECISIONS.md (top 100 lines), ARCHITECTURE.md (top 150 lines), BUGS.md (top 100 lines), CONSTITUTION.md (full), AbilityForm.jsx (full), AcrossAI_Abilities_Validator.php (full), AcrossAI_Abilities_Processor.php (is_row_registrable), AcrossAI_Abilities_Write_Controller.php (create_ability), admin.scss (.field-error, .req, .lopt confirmed)
- Clarifications integrated: 5/5 from 2026-05-25 session — all encoded in spec.md §Clarifications and plan.md
- Budget status: within limits
