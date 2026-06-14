# Memory Synthesis

## Current Scope

Feature 033 — Library Card Toggle + Optional Sub-Group Display. Two display-only edits:
(A) lock down the `mode === 'specific'` checkbox-panel visibility contract on each `LibraryCard`;
(B) add an OPTIONAL `sub_group` pass-through to `Ability_Definition::push_definition()` and the Library Registry, surface it via the localized JS data, and render an `<h4>` sub-heading inside the Specific panel.

Affected modules: `includes/Modules/Library/*` (Ability_Definition, Registry), `src/js/ability-library/components/{LibraryPage,LibraryCard}.js`, one CSS rule, PHPUnit Library tests, `AGENTS.md`. Saved-config option, REST contract, and execution paths are explicitly untouched.

## Relevant Decisions

- **DEC-LIBRARY-CATEGORY-SLUG-REBRAND** (Active, DECISIONS.md). Reason Included: Feature 033 extends the same `Ability_Definition::push_definition()` simplified by Feature 031. The decision guarantees external subclasses with old methods stay compatible — adding an OPTIONAL `args['sub_group']` pass-through preserves that guarantee. `sub_keys` on-disk key MUST stay preserved (it is in the feature's "must NOT change" list).
- **DEC-NAMESPACE-CONVENTION** (Active). Reason Included: Library Registry edits must keep the `AcrossAI_Abilities_Manager\Includes\Modules\Library` underscore convention. No new `use` statement needs reformatting.
- **DEC-UTILITY-STATIC-ONLY** (Active). Reason Included: Registry stays singleton orchestrator; the new `sanitize_sub_group()` helper should be `private static` (consistent with existing `sanitize_key_field()` style on `AcrossAI_Ability_Library_Config`).
- **DEC-PLUGIN-CHECK-PRODUCTION-SURFACE** (Active, supersedes DEC-EVAL-PHP-CODE). Reason Included: All new PHP touches `includes/` — production-surface paths that Plugin Check scans. Zero new errors/warnings allowed; no broad workflow ignore-codes.

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (CONSTITUTION.md §I). Reason Included: This feature adds no new hooks; the existing `acrossai_abilities_api_init` filter wiring stays in `Main.php`. Don't introduce ad-hoc `add_filter` calls in the Library module.
- **AC-FILE-HEADER-PATTERN** (ARCHITECTURE.md). Reason Included: Existing `Ability_Definition.php` and `AcrossAI_Ability_Library_Registry.php` already match `@package AcrossAI_Abilities_Manager / @subpackage includes/Modules/Library / @since 0.1.0`. New docblocks must match.
- **PATTERN-ADDON-FILTER-LATE-INIT** (ARCHITECTURE.md). Reason Included: Add-ons hook `acrossai_abilities_api_init` at standard priority and Registry collects at init P99. Sub-group declarations follow this contract — no change to timing.
- **PATTERN-PROTECTED-SLUGS-JS-LOCALIZE** (ARCHITECTURE.md). Reason Included: `sub_group` flows from PHP → JS via `window.acrossaiAbilityLibraryData`. Do NOT hardcode group labels in JSX; everything is pushed by the Registry.
- **PATTERN-FEATURE-ASSET-SEPARATION** (ARCHITECTURE.md). Reason Included: Library has its own `ability-library.js` bundle and (separate) admin stylesheet. CSS rule goes wherever existing `.acrossai-library-card__*` selectors live — do NOT create a new SCSS file for one rule.

## Accepted Deviations

- None applicable to this feature. (`DEC-SETTINGS-API-DEVIATION`, `ARCH-ADV-001`, and `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` are scoped to other modules.)

## Relevant Security Constraints

- No new REST endpoint, no new user input boundary, no DB write surface. SEC-01/SEC-02/SEC-03/SEC-04 do not gain new touchpoints. The only added input is `args['sub_group']`, sourced from trusted add-on PHP code (same trust boundary as `args['category']`/`args['label']`); it is sanitized through the existing `AcrossAI_Ability_Library_Config::sanitize_key_field()` (the same 100-char `sanitize_key()` rule already proven on category/slug). Reason Included: documenting the boundary so the security review can confirm no new attack surface.

## Related Historical Lessons

- **PATTERN-NAMED-EXPORT-JEST** (ARCHITECTURE.md). Reason Included: The new `groupBySubGroupPreservingOrder()` helper in `LibraryCard.js` SHOULD be a **named export** (alongside the default `LibraryCard` export) so a Jest test can assert grouping order without rendering React. Pure helper, no `useState` — perfect fit.
- **PATTERN-JESTENV-WPSCRIPTS** (ARCHITECTURE.md). Reason Included: If JS tests for CHANGE-A's mount/unmount behavior land, run via `npx wp-scripts test-unit-js`, not bare `npx jest`.
- **BUG-PHPUNIT-TYPED-PROPERTY-SETUP** (BUGS.md). Reason Included: New Registry/Ability_Definition PHPUnit cases must call the singleton inline per test, not assign to a typed class property inside `set_up()`.
- **BUG-PHPCS-DOCBLOCK-CAPITAL** (BUGS.md). Reason Included: New `/** Sanitizes ... */` style docblocks risk PHPCS rejection — prefix with "The " or restructure ("Sanitizer for ...").
- **PATTERN-WP-DEBUG-LOG-GUARD** (ARCHITECTURE.md). Reason Included: If a new `error_log()` is needed (e.g. for a rejected sub_group), wrap in `if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG )` with the `phpcs:ignore` comment inside the guard — same pattern Registry already uses at `log_invalid()`.

## Out-of-Scope Cross-References

These existing memory entries were checked during planning because spec.md / plan.md acknowledge them, but they do NOT bind any task in Feature 033:

- **DEC-MCP-INJECT-REFLECTION-PATTERN** (Active, 2026-06-11, Feature 029). Reason Cross-Referenced: An AI scan flagged `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php:715` (Reflection on `McpServer::$component_registry`). spec.md "Out of Scope — Known Plugin Concerns" and plan.md "Known Plugin Concerns Outside This Feature's Scope" both link to this decision. No remediation in Feature 033 — the Reflection use is intentional, fail-soft (`try/catch` no-ops on `ReflectionException`), and gated on upstream `wordpress/mcp-adapter` publishing a public injection API. File at `includes/Modules/Abilities/`; Feature 033 touches only `includes/Modules/Library/` + `src/js/ability-library/`.

## Conflict Warnings

None. The feature is purely additive (new optional field) and display-only:

- No conflict with **DEC-LIBRARY-CATEGORY-SLUG-REBRAND**: `sub_group` follows the same `args['…']` hoist pattern as `label`/`category`.
- No conflict with **REQUIRED_FIELDS** invariant (still 6 entries): `sub_group` is OPTIONAL, validated through a separate code path.
- No conflict with `sub_keys` on-disk wire key: sub-group never participates in `sub_keys` writes or reads.
- No conflict with **DEC-MCP-INJECT-REFLECTION-PATTERN**: cross-module file path; Feature 033 never opens `AcrossAI_Ability_Override_Processor.php`.

## Retrieval Notes

- Index entries considered: ~20 (tags: library, ability-definition, react, jest, localize-script, phpunit, plugin-check, phpcs, docblock); 1 cross-reference added on refresh (`DEC-MCP-INJECT-REFLECTION-PATTERN`) for the AI-flagged finding now documented in spec/plan.
- Source sections read: spec.md, plan.md (post-edit), Ability_Definition.php, AcrossAI_Ability_Library_Registry.php, AcrossAI_Ability_Library_Config.php, LibraryCard.js, LibraryPage.js, CONSTITUTION.md sync-impact header (v1.4.6), AcrossAI_Ability_Override_Processor.php:700–730 (only to confirm DEC-MCP-INJECT-REFLECTION-PATTERN still matches line 715).
- Files NOT opened: ARCHITECTURE.md, BUGS.md, full DECISIONS.md, security-constraints.md (durable), WORKLOG.md — INDEX hits sufficient.
- Budget status: ≤ 900 words; under all retrieval caps (5/5/3/0/5/0).
- No `[NEEDS CLARIFICATION]` markers; no hard conflict blocks `/speckit-tasks` or implementation.
