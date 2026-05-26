# Memory Synthesis

## Current Scope
Feature 014 unifies the ability Edit/Override UI, migrates all single-ability REST operations to slug-based routing, fixes edit for non-custom abilities, allows label/description/category overrides for non-custom abilities, and sets publish as the create default. Affected modules: `Abilities/Rest/` (Write + Read + orchestrator), `Utilities/` (Merger, Sanitizer, Formatter), `src/js/abilities/` (api/client, store, AbilitiesList, AbilitiesManager, AbilityForm).

## Relevant Decisions
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** — Custom HTML form pattern is accepted; AbilityForm.jsx uses plain `.panel`/`.sect` sections, NOT DataForm. Plan MUST NOT introduce DataForm. (Reason: deepened by Feature 013; active deviation) Source: DECISIONS.md
- **DEC-ABILITIES-DUAL-MODE-LIST** — `format_merged_ability()` is canonical for non-db response shapes. `format_for_response()` is for db-only rows. Plan must not collapse these two paths. (Reason: exact formatter methods affect FR-014) Source: DECISIONS.md
- **DEC-EARLY-404-REST-CHECK** — Slug sanitize → exclusion check → DB lookup. Never check after lookup. (Reason: affects FR-011/FR-012 PHP upsert order) Source: DECISIONS.md
- **DEC-UTILITY-STATIC-ONLY** — Merger, Sanitizer, Formatter are 100% static classes. No singletons. (Reason: prevents accidental refactor) Source: DECISIONS.md
- **DEC-PROTECTED-SLUGS-PATTERN** — Slug exclusion check uses centralized utility with filter. Apply at new slug route too. (Reason: slug route replaces id route; check must carry forward) Source: DECISIONS.md

## Active Architecture Constraints
- **AC-HOOKS-MAIN** — No new hook wiring in Main.php needed; orchestrator already wired via `rest_api_init`. Only route pattern and callback logic change inside sub-controllers. (Reason: prevents boot-flow violation) Source: CONSTITUTION.md §I
- **AC-REST-SPLIT** — Sub-controller pattern active. Orchestrator: `AcrossAI_Abilities_Rest_Controller`. Sub-controllers in `includes/Modules/Abilities/Rest/`. Plan modifies Write + Read sub-controllers only. (Reason: structure constraint) Source: CONSTITUTION.md §I
- **ARCH-UNIFIED-ABILITIES-STORAGE** — `AcrossAI_Abilities_Query::save_override($slug, $fields)` is the canonical upsert method. No new DB method needed for first-time non-custom override creation. (Reason: FR-007 upsert path) Source: ARCHITECTURE.md
- **CATEGORY-ROUTE-FIRST (derived)** — `AcrossAI_Abilities_Rest_Controller::register_routes()` currently calls Write first, then Read, then Category. Once `(?P<slug>[^/]+)` replaces `(?P<id>\d+)`, `/abilities/categories` would be swallowed by the slug pattern. **Orchestrator must register Category first.** (Reason: route collision — hard implementation constraint) Source: code analysis `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php:73-77`
- **DEC-NODE-20-BUILD-REQUIRED** — `nvm use 20 && npm run build`. Node 16 fails silently on toSorted. (Reason: JS build constraint) Source: DECISIONS.md

## Accepted Deviations
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** — Custom HTML form/table accepted. DataForm and DataViews are NOT used for AbilityForm or AbilitiesList. This deviation continues for Feature 014. (Status: Accepted-Deviation, origin Feature 010)
- **DEV1** — McpVisibilityControl uses compound-control (not relevant to this feature; noted for completeness).

## Relevant Security Constraints
- **SEC-01** — `AcrossAI_Sanitizer::sanitize_ability_slug()` + `rawurldecode()` applied at every REST endpoint receiving a slug in the route. New slug arg must declare `sanitize_callback` and `validate_callback`. (Source: security-constraints.md)
- **SEC-04** — Strict type comparison (`=== / !==`) for all access/guard checks. `empty()` on strings is forbidden. (Source: security-constraints.md)
- **SEC-02** — `before_save` hook fires on sanitized `$fields` only. Bool→int cast before BerlinDB. (Source: security-constraints.md)

## Related Historical Lessons
- **BUG-ABILITYFORM-JSX-MIXED-DEPTHS** — AbilityForm.jsx has inconsistent tab depths by section/element. Any str_replace or Python edit script MUST verify exact whitespace before replacement. (Reason: directly in scope for Feature 014's largest JS change)
- **BUG-PHPCBF-TABS** — Python str_replace scripts on PHP files must use `\t` not spaces. phpcbf converts remaining spaces to tabs post-edit. (Reason: PHP edit scripts will be used)
- **BUG-SLUG-SUFFIX-MISMATCH** — REST create route expects `slug_suffix` (suffix only). The slug route change must NOT touch the create `POST /abilities` route. (Reason: guard against breaking create)

## Conflict Warnings
- **HARD — Category route collision**: Current orchestrator registers Write before Category. After slug-pattern migration, `/abilities/categories` would match `(?P<slug>[^/]+)` and return a 404/wrong response. **Block implementation until orchestrator reorder is included in task T-PHP-01 or a dedicated task.** Resolution: change orchestrator to call `Category_Controller::register_routes()` first.
- **SOFT — `has_override` computed as `null !== $override`**: Q4 answer requires `has_override: false` when all overridable fields are null/default. Current `merge()` does not implement this. Plan must include updating `merge()` to iterate `$overridable_fields` for nullity check.
- **SOFT — `merge()` does not propagate `created_at`/`created_by`**: `format_merged_ability()` hardcodes `created_at => null`. Q5 requires Activity sidebar to show when `created_at` is non-null. Plan must add `created_at`/`created_by` propagation from override row in `merge()`.
- **SOFT — `format_merged_ability()` drops `_registry`**: `merge()` sets `_registry` but the Formatter doesn't pass it through. Spec expects `_registry` in the response (needed for `TriStateSelect` "Inherit" rendering). Plan must add `_registry` to `format_merged_ability()` output.
- **SOFT — `format_merged_ability()` hardcodes `editable: false`**: Frontend uses `source !== 'db'` for `isNonDb` detection, not `editable`. Low priority; does not block correctness but is misleading.

## Retrieval Notes
- Index entries considered: 20 (all)
- Source sections read: DECISIONS.md (DEC-DESIGN-OVERRIDES-DATAVIEWS, DEC-ABILITIES-DUAL-MODE-LIST, DEC-EARLY-404-REST-CHECK); CONSTITUTION.md §I, §II, §VII; code: Formatter.php, Merger.php, Write/Read/Orchestrator controllers; Query.php (grep), Source Detector (grep)
- Budget: ~870 words — within 900-word limit
- Full durable memory read: false
