# Memory Synthesis

## Current Scope

Feature 008 renames the BerlinDB sitewide override table from `acrossai_abilities_overwrite` (16 columns) to `acrossai_abilities` (25 columns) and extends it with 9 new first-class record columns. It also adds a filterable JSON field registry and a by-source query method. Exactly 5 files are modified: the 4 Sitewide Database files + `uninstall.php`. No new files are created.

---

## Relevant Decisions

- **DEC-NAMESPACE-CONVENTION** (Reason Included: All PHP files must use `AcrossAI_Abilities_Manager\Includes\*` underscore convention and ABSPATH guard, Status: Active, Source: DECISIONS.md)
- **DEC-UTILITY-STATIC-ONLY** (Reason Included: JSON field registry must be a public static method; only orchestrators use singleton, Status: Active, Source: DECISIONS.md)
- **ARCH-ADV-001** (Reason Included: boot() conditional hook deviation — out of scope for this feature but must not be disturbed by file edits, Status: Active-Deviation, Source: DECISIONS.md)
- **BUG-BERLINDB-UNLIMITED** (Reason Included: `get_all_by_source()` must use `number => 0`; `absint(-1)=1` silently limits to 1 row, Status: Active, Source: BUGS.md)
- **DEC-PROTECTED-SLUGS-PATTERN** (Reason Included: protected prefix filtering is out of scope for this feature but AC-QUERY-LAYER-FILTERING constraint applies to any future query extensions, Status: Active, Source: DECISIONS.md)

---

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason Included: `includes/Main.php` MUST NOT be modified per FR-015, Source: CONSTITUTION.md §I)
- **AC-FILE-HEADER-PATTERN** (Reason Included: All 5 updated files must keep `@package AcrossAI_Abilities_Manager`, `@subpackage` full path, `@since 0.1.0`, Source: ARCHITECTURE.md)
- **SEC-03** (Reason Included: `AcrossAI_Sitewide_Table::$global = false` is an explicit multisite safety contract and MUST remain set in the updated Table class, Source: security-constraints.md)

---

## Accepted Deviations

- **ARCH-ADV-001** (Reason Included: boot() conditional hook deviation is pre-existing and must not be disturbed, Status: Accepted-Deviation)
- **FR-017 / FR-018** (Reason Included: Spec explicitly prohibits bumping `$version` and prohibits editing 3 blocks in `save_override()` — these are intentional constraints not deviations, Status: Spec-Mandated)

---

## Relevant Security Constraints

- **SEC-03** (Reason Included: `$global = false` per-site prefix — MUST remain in Table; ensures multisite isolation, Source: security-constraints.md)
- **SEC-02** (Reason Included: `before_save` hook fires on sanitized `$fields` only; the JSON encoding loop in `save_override()` runs before the hook so the encoded fields are sanitized, Source: security-constraints.md)
- **SEC-04** (Reason Included: strict type comparison — `is_array()` used for JSON encode guard, not loose `==`, Source: security-constraints.md)

---

## Related Historical Lessons

- **BUG-BERLINDB-UNLIMITED** (Reason Included: `get_all_by_source()` is an unlimited query; must use `'number' => 0`, never `'number' => -1` or `'number' => 9999`)
- **BUG-PARTIAL-HOOK-FIELDS** (Reason Included: `save_override()` fires hooks; fetch complete row after save for full 25-field payload, not local `$fields` subset)
- **FR-010 JSON encode failure path** (Reason Included: If `wp_json_encode()` returns `false` for a JSON field, store `null` and continue — do not abort the entire save)

---

## Conflict Warnings

- None. The 5-file constraint (FR-015) is a hard boundary — no hooks in Main.php, no Activator changes, no test modifications. No conflict with existing decisions.
- ARCHITECTURE.md already describes the post-feature state (`acrossai_abilities`, Sitewide as thin wrapper) — this is prospective documentation, not a conflict.

---

## Retrieval Notes

- Index entries considered: DEC-NAMESPACE-CONVENTION, DEC-UTILITY-STATIC-ONLY, ARCH-ADV-001, BUG-BERLINDB-UNLIMITED, BUG-PARTIAL-HOOK-FIELDS, AC-HOOKS-MAIN, AC-FILE-HEADER-PATTERN, SEC-03, SEC-02, SEC-04
- Source sections read: DECISIONS.md (first 80 lines), BUGS.md (first 80 lines), ARCHITECTURE.md (full), INDEX.md
- Budget status: Within limits (5 decisions, 3 constraints, 2 deviations, 3 bug patterns selected)
