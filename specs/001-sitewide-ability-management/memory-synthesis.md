# Memory Synthesis

feature: 001-sitewide-ability-management
status: complete
hard_conflicts: 0
soft_conflicts: 2
assumptions_to_confirm: 0

<!-- Keep metadata keys in this order. Keep every section below, even when empty. -->
<!-- Use stable item IDs like [C1], [D1], [B1], [A1], [Q1], [W1], [V1]. -->
<!-- Use "- [none]" for empty sections, and keep conflict counts aligned with listed conflicts. -->
<!-- Keep this file within retrieval.max_synthesis_words, default 900 words. -->

## Current Scope
Feature 001 is **fully implemented** (T001–T048 all complete). Five user stories delivered:
DataViews ability table (US1), Allow/Disallow toggle (US2), slide-in DataForms edit panel
with per-tab save (US3), Reset Override (US4), Bulk actions (US5). REST controller
decomposed per spec-002 into orchestrator + 4 sub-controllers in `includes/Modules/Sitewide/Rest/`.
Synthesis purpose: preserve implementation learnings and constraints for downstream features
(spec-002 modularization, spec-003 access control UI, spec-004 override processor).

## Relevant Decisions
- [D1] BerlinDB upsert return values: `add_item()` returns integer ID (check `!== false && > 0`);
  `update_item()` returns object (check `!== false`). (Reason Included: all future BerlinDB upsert
  calls must use the same pattern, Status: Active, Source: plan.md Decision 9)
- [D2] PHP bool→int cast before BerlinDB: cast all tri-state PHP booleans (`true → 1`, `false → 0`)
  before passing to BerlinDB to prevent `$wpdb` format `%s` producing empty string `''` that MySQL
  strict mode rejects on tinyint columns. Leave `null` unchanged. (Reason Included: applies to every
  nullable tinyint column in any BerlinDB class, Status: Active, Source: plan.md Decision 9b)
- [D3] `has_param()` for partial-field saves: REST handlers must use `$request->has_param($field)` not
  `get_param()` to collect only explicitly sent fields — prevents silently overwriting other-tab DB
  values with null. (Reason Included: any multi-tab or partial-save REST endpoint must follow this
  pattern, Status: Active, Source: plan.md Decision 10)
- [D4] `is_all_default()` gated on `!$existing`: skip DB write only when no existing row AND all fields
  match registry defaults. If a row exists, always write so null explicitly clears the override.
  (Reason Included: governs "No changes made" logic in override-save endpoints, Status: Active,
  Source: plan.md Decision 10 / FR-024)
- [D5] DEC-PERM-CB: AC rule-gated `permission_callback` injection runs independently of the override
  row. Fail-open when `get_manager()` returns null. Do not guard inside `isset($_overrides_cache)`.
  (Reason Included: Access Control tab in spec-003 will save rules that this processor enforces,
  Status: Active, Source: DECISIONS.md)

## Active Architecture Constraints
- [A1] `includes/Main.php` is the ONLY file that calls `$this->loader->add_action/add_filter`.
  All hooks wired in `define_admin_hooks()` / `define_public_hooks()` with variable-first pattern —
  never inline `::instance()` as the hook object. (Reason Included: affects all new module wiring,
  Source: CONSTITUTION.md §I Boot Flow Rule v1.4.1)
- [A2] Admin asset enqueue (`wp_enqueue_script/style`) lives ONLY in `Admin\Main::enqueue_scripts()`
  / `enqueue_styles()` — never in Partials page classes or module classes. (Reason Included:
  any future admin page must follow this boundary, Source: CONSTITUTION.md §I + plan.md Decision 6)
- [A3] REST controller split: when a controller exceeds ~400 lines or spans more than one user story,
  it MUST be decomposed into orchestrator + per-domain sub-controllers in `includes/Modules/<Feature>/Rest/`.
  Orchestrator owns `REST_NAMESPACE`, `register_routes()`, `check_permission()` only. `Main.php`
  wires only the orchestrator. (Reason Included: governs all future REST work in this plugin,
  Source: CONSTITUTION.md §I REST Controller Pattern v1.4.0)
- [A4] Filter/sort/paginate logic lives in `AcrossAI_Ability_Registry_Query::query()` only — not
  inlined in REST controllers. Any future list endpoint reuses this utility. (Reason Included:
  RF-03 / Constitution §I single-responsibility, Source: plan.md T006b)
- [A5] `admin/Partials/Menu.php` updated in-place — no new menu class created. React root rendered
  in `contents()`. (Reason Included: FR-020 constraint, Source: plan.md clarification C5)

## Accepted Deviations
- [DEV1] `McpVisibilityControl.jsx` is exempt from the DataForms requirement (Constitution §III).
  The 4-state compound control encodes 3 interdependent fields with conditional rendering that cannot
  map to independent DataForm fields. This exception is explicitly documented in plan.md Note (RT-01).
  (Reason Included: any reviewer would flag this; the exception is deliberate,
  Status: Accepted-Deviation, Source: plan.md constitution check §III override)

## Relevant Security Constraints
- [S1] Nonce verified on all 7 REST endpoints inside `check_permission()`. (Source: plan.md §IV)
- [S2] `AcrossAI_Sanitizer::sanitize_ability_slug()` MUST be called on every slug URL parameter
  before use — SEC-01. (Source: plan.md T025/T028/T034/T037)
- [S3] `do_action('acrossai_abilities_sitewide_after_save', $slug, $fields)` fires with sanitized
  `$fields` only — SEC-02. (Source: plan.md T025)

## Related Historical Lessons
- [B1] BUG-BERLINDB-UNLIMITED: `number => -1` → `absint` → LIMIT 1. Always use `number => 0` for
  unlimited. (Reason Included: AcrossAI_Sitewide_Query::get_all_overrides() uses this; feature 004
  also affected, Source: BUGS.md)
- [B2] `useEffect([slug])` not `[ability]`: seeding draft state from `[ability]` re-seeds on every
  `UPDATE_ABILITY` dispatch and silently reverts user selections after save. (Reason Included:
  applies to any future edit panel component, Source: plan.md Decision 11)
- [B3] `key={slug}` on compound control components to trigger remount on ability change — do not use
  `useEffect([prop])` for re-init; it fires on internal state changes and snaps selections back.
  (Reason Included: applies to any stateful form component inside a multi-item drawer,
  Source: plan.md Decision 14)

## Feature-to-Memory Conflicts
- [SC1] Repo memory CONSTITUTION.md (v1.0.0) still includes an RT-01 warning on AbilityEditPanel
  RadioControl. That violation was remediated in plan.md (RT-01 resolved 2026-05-16; General tab
  migrated to DataForm + TriStateEditField). Soft conflict — repo memory is outdated on this point.
- [SC2] INDEX.md Architecture Constraints table is empty — A1–A5 above are not yet indexed. Should
  be populated after this synthesis is reviewed.

## Assumptions Requiring Confirmation
- [none]

## Implementation Watchpoints
- [W1] Access Control UI (spec-003) must wire via `wpb-access-control` library using
  `acrossai_abilities_access_control_providers` filter tag — not a new plugin-owned table or routes.
  Access rules stored in `{prefix}wpb_access_control` / `wpb-ac/v1` (repo memory note 2026-05-16).
- [W2] Any new BerlinDB Query class: cast bool→int (D2), use `has_param()` for partial saves (D3),
  `number => 0` for unlimited (B1).

## Verification Watchpoints
- [V1] On any new REST endpoint: confirm `check_permission()` verifies nonce AND `manage_options`;
  confirm slug param is sanitized via `AcrossAI_Sanitizer::sanitize_ability_slug()` before use.
- [V2] On any new JS edit panel: confirm `useEffect([slug])` dep and `key={slug}` on compound
  stateful children; confirm `_override` is explicitly nulled in optimistic delete dispatch.

## Retrieval Notes
- Index entries considered: 6 (DEC-PERM-CB, ARCH-ADV-001, BUG-BERLINDB-UNLIMITED, BUG-FLAT-ARGS-PATH, access-control-integration-2026-05-16, constitution-sync-impact-2026-05-11)
- Source sections read: DECISIONS.md (2 decisions), BUGS.md (2 patterns), CONSTITUTION.md (§I–IV), plan.md (Decisions 9–14, constitution check), spec.md (FR-001–026), tasks.md (phase checkpoints), repo memory (3 files)
- Budget: within 900-word limit
