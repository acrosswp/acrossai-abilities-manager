# Architecture Violation Detection — Feature 033

> Inline review (architecture-guard.violation-detection equivalent). Inputs: `plan.md`, `memory-synthesis.md`, `security-constraints.md`, `.specify/memory/CONSTITUTION.md` (v1.4.5+).

## Constraint Pass-Through

| ID | Constraint | Verdict |
|----|------------|---------|
| AC-HOOKS-MAIN | Only `Main.php` calls `loader->add_action/add_filter`; variable-first pattern | ✅ Pass — feature adds NO hooks; existing `acrossai_abilities_api_init` wiring unchanged |
| AC-ENQUEUE-ADMIN | `wp_enqueue_script/style` ONLY in `Admin\Main::enqueue_scripts/styles` | ✅ Pass — feature adds no enqueues |
| AC-FILE-HEADER-PATTERN | `@package AcrossAI_Abilities_Manager / @subpackage <path> / @since 0.1.0` | ✅ Pass (gated by impl) — edits stay in existing files whose headers already match |
| AC-REST-SPLIT | Split when > 400 lines | ✅ Pass — no REST work |
| ARCH-UNIFIED-ABILITIES-STORAGE | Abilities module owns the unified table | ✅ Pass — feature does not touch that table |
| Module Contract (singleton + private ctor + Utilities-only deps) | Registry stays singleton; reuses existing `AcrossAI_Ability_Library_Config` static helper | ✅ Pass |
| PATTERN-ADDON-FILTER-LATE-INIT | Add-on registration filter fires at init P99 | ✅ Pass — sub_group inherits the existing timing |
| PATTERN-PROTECTED-SLUGS-JS-LOCALIZE | PHP-managed values flow via `window.acrossai*` localization | ✅ Pass — sub_group flows through existing `window.acrossaiAbilityLibraryData` |
| PATTERN-FEATURE-ASSET-SEPARATION | Reuse the existing Library SCSS file; don't add a new one for one rule | ✅ Pass (gated by impl) |
| DEC-LIBRARY-CATEGORY-SLUG-REBRAND | Backwards compatibility for external `Ability_Definition` subclasses | ✅ Pass — `sub_group` is OPTIONAL; subclasses without it produce identical rows |
| DEC-NAMESPACE-CONVENTION | `AcrossAI_Abilities_Manager\Includes\*` underscore convention | ✅ Pass — no new namespace |
| DEC-UTILITY-STATIC-ONLY | Utility classes 100% static; orchestrators singleton | ✅ Pass — new `sanitize_sub_group()` helper will be `private static` |
| DEC-PLUGIN-CHECK-PRODUCTION-SURFACE | Production files Plugin Check clean | ✅ Pass (gated by impl) |
| REST `permission_callback` Return Type (MUST) | `true \| false \| \WP_Error` only | ✅ Pass — no new REST routes |

## Constitution Principle Verdicts

| Principle | Verdict |
|-----------|---------|
| I. Modular Architecture | ✅ Pass — all PHP changes inside `includes/Modules/Library/` |
| II. WordPress Standards Compliance | ⚠ Conditional — PHPCS/PHPStan/Plugin Check gates enforced during implementation |
| III. User-Centric Design (DataForm/DataViews) | ⚠ Pre-existing deviation, NO new violation — feature inherits Library page's existing `ToggleControl`/`RadioControl`/`CheckboxControl` primitives. **Documented in plan.md "Constitution Check" section.** Not a blocking finding. |
| IV. Security First | ✅ Pass — see `security-constraints.md` SC-033-01 through SC-033-07 |
| V. Extensibility Without Core Modification | ✅ Pass — opt-in field via existing filter |
| VI. Reusability & DRY | ✅ Pass — reuses `sanitize_key_field()` + the `ucwords(str_replace(...))` label-derivation idiom |
| VII. Definition of Done | ⚠ Conditional — enforced during implementation |

## Drift Findings

None. The plan does not introduce:
- New modules
- New REST routes / endpoints / namespaces
- New DB tables, columns, or option keys
- New external dependencies
- New cron jobs, Action Scheduler tasks, or async handlers
- New cross-module reaches (Library does not reach into Abilities / Logger / etc.)

## Security-Architecture Conflicts

None. The security review's 7 constraints all map cleanly to architectural patterns already in use (the `sanitize_key_field()` helper, the localized-data flow, the `wp_kses_post()` escaping idiom, the `PATTERN-WP-DEBUG-LOG-GUARD` for any new error_log).

## Consistency With Constitution

The plan aligns with Constitution v1.4.5+. The single pre-existing deviation (Library page UI primitives vs DataForm/DataViews) is inherited from Feature 031, documented in `plan.md`, and not extended by this feature.

## Recommended Actions

1. **Proceed to** `/speckit-tasks` — generate the task breakdown.
2. **During implementation**, enforce:
   - SC-033-01 through SC-033-07 (security-constraints.md).
   - PHPStan level 8, PHPCS WPCS, ESLint, Plugin Check production-surface clean.
   - PATTERN-NAMED-EXPORT-JEST when wiring the JS grouping helper (named export of `groupBySubGroupPreservingOrder`).
   - BUG-PHPUNIT-TYPED-PROPERTY-SETUP when writing new Registry PHPUnit tests.
   - BUG-PHPCS-DOCBLOCK-CAPITAL on all new docblocks.
3. **No** `/speckit-architecture-guard-refactor-generator` call needed — no architectural refactor required.

## Verdict

**Plan APPROVED for tasks phase.** No P0 violations. One advisory note (R-1 pre-existing UI-primitive deviation) is already documented in `plan.md`.
