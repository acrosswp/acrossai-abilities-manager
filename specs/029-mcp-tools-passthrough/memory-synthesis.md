# Memory Synthesis

## Current Scope

Feature 029 adds a tri-state `pass_as_tool` column to the `acrossai_abilities` BerlinDB table, wires it through the existing tri-state plumbing (Row → Sanitizer → Query → Formatter), creates a new singleton module `AcrossAI_Mcp_Tools_Passthrough` that hooks `mcp_adapter_server_config` to merge opted-in slugs into every MCP server's tool list, and adds an inline toggle column to `AbilitiesList.jsx`.

Affected modules: `Abilities/Database` (Schema, Row, Query), `Utilities` (Sanitizer, Formatter), `Modules/McpToolsPassthrough` (new), `Main.php`, `src/js/abilities/components/AbilitiesList.jsx`.

---

## Relevant Decisions

- **DEC-NAMESPACE-CONVENTION** — New module MUST use `AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough` (underscore convention). No PSR-4 camelCase. (Reason: plugin-wide convention; Source: DECISIONS.md)
- **DEC-SINGLETON-PSR2-PROPERTY** — New class uses `protected static $instance` (not `$_instance`). Only `instance()` may be `public static`. (Reason: PSR-2 enforcement after Feature 022; Source: DECISIONS.md)
- **DEC-UTILITY-STATIC-ONLY** — `AcrossAI_Mcp_Tools_Passthrough` is an orchestrator (stateful singleton with a hook callback), NOT a utility class. Utility classes are 100% static. The new class is correctly a singleton. (Reason: architecture boundary; Source: DECISIONS.md)
- **DEC-COLUMN-VISIBILITY-LOCALSTORAGE** — The new "Pass as Tool" column MUST default to visible and be persisted via the existing `acrossai_abilities_columns` localStorage merge-over-`COLUMN_DEFAULTS` pattern (FR-025). New columns always default visible. (Reason: Feature 025 UX pattern; Source: DECISIONS.md)
- **DEC-ABILITIES-DUAL-MODE-LIST** — `format_merged_ability()` normalises the response shape for registry abilities; `pass_as_tool` must be added there alongside `format_for_response()` and `format_for_exposure()`. (Reason: formatter is single source of truth; Source: DECISIONS.md)
- **DEC-PROTECTED-SLUGS-PATTERN** — Protected slug exclusion is centralised in `AcrossAI_Protected_Abilities`. The disabled UI state in `PassAsToolCell` references this same list; no duplicate guard logic. (Reason: extensibility; Source: DECISIONS.md)
- **DEC-DB-WRITE-BOUNDARY-GUARD** — DB write methods enforce source-discriminant guards. The tri-state sanitization path in `AcrossAI_Abilities_Sanitizer` already carries this; no special guard needed for `pass_as_tool`. (Reason: security boundary; Source: DECISIONS.md)

---

## Active Architecture Constraints

- **AC-HOOKS-MAIN** — ONLY `Main.php` calls `$this->loader->add_action()` / `->add_filter()`. `AcrossAI_Mcp_Tools_Passthrough` MUST NOT call `add_filter()` in its own code. Resolve the singleton into a named variable before passing to the Loader. (Source: CONSTITUTION.md §I)
- **ARCH-UNIFIED-ABILITIES-STORAGE** — Abilities module owns the unified abilities table. `pass_as_tool` column belongs there. No new table, no option. (Source: ARCHITECTURE.md)
- **ARCH-SANITIZER-TWO-CLASS** — `AcrossAI_Sanitizer` (base, owns `sanitize_mcp_servers_array`) ≠ `AcrossAI_Abilities_Sanitizer` (wrapper, owns tri-state field lists). The `$tri_state_fields` arrays live in `AcrossAI_Abilities_Sanitizer`. PHPUnit tests targeting the sanitizer must use the correct FQCN. (Source: ARCHITECTURE.md)
- **PATTERN-CONSTITUTION-SYNC-REPORT** — If `.specify/memory/CONSTITUTION.md` is version-bumped, the SYNC IMPACT REPORT HTML comment at the top must also be updated. (Source: ARCHITECTURE.md)

---

## Accepted Deviations

None applicable to Feature 029.

---

## Relevant Security Constraints

- **SEC-01** — `sanitize_ability_slug()` is already applied at every REST endpoint receiving a slug. The write path for `pass_as_tool` goes through the existing sparse-update endpoint; no new slug handling needed. (Source: security-constraints.md)
- **SEC-02** — `before_save` hook fires on sanitized `$fields` only. The tri-state sanitization path already produces `1 | 0 | NULL` before DB write; the cast in `prepare_fields_for_write()` must also be present for `pass_as_tool`. (Source: security-constraints.md)

---

## Related Historical Lessons

- **BUG-BERLINDB-V3-DOUBLE-PRIMARY** — CRITICAL. Do NOT add `'primary' => true` to the `pass_as_tool` column definition. PRIMARY KEY is declared exclusively in `$indexes`. Having both causes silent DDL generation failure (table never created, no exception). (Source: BUGS.md, Feature 028)
- **BUG-BERLINDB-V3-TIMESTAMP-QUOTING** — Not directly applicable to `pass_as_tool` (tinyint, no timestamp), but reinforces the rule: never set `'default' => 'CURRENT_TIMESTAMP'`. The tinyint column must use `'default' => null`. (Source: BUGS.md, Feature 028)
- **BUG-BERLINDB-UNLIMITED** — `number => -1` becomes `absint(-1) = 1` → LIMIT 1. The finder `get_pass_as_tool_slugs()` MUST use `'number' => 0` (which BerlinDB interprets as no LIMIT), not `-1`. (Source: BUGS.md)
- **BUG-MERGER-BOOL-STRING-CAST** — When reading `pass_as_tool` off a BerlinDB row (`?bool`), never use `'' !== (string) $value` as a guard. Use `null !== $value` only. PHP casts `false` to `''`, silently dropping boolean-false overrides. Affects `AcrossAI_Ability_Merger` if `pass_as_tool` is ever consumed there. (Source: BUGS.md, Feature 024)
- **BUG-STATIC-METHOD-SINGLETON-BYPASS** — `AcrossAI_Mcp_Tools_Passthrough` must not expose any `public static` method other than `instance()`. Adding public static methods bypasses the singleton contract and was flagged in architecture review. (Source: BUGS.md)
- **BUG-ABSPATH-STATIC-CLASS** — The new module file must include `defined('ABSPATH') || exit;` even though it is instantiated only via `Main.php`. Per-file guard is required. (Source: BUGS.md, Feature 027)

---

## Conflict Warnings

None. The feature is purely additive: new column, new module, new UI column. No existing decision is violated by the proposed approach.

---

## Retrieval Notes

- Index entries considered: 37 active decisions + 30+ bug patterns — selected 7 decisions, 4 arch constraints, 6 bug patterns by (Impact × Uncertainty) heuristic.
- Source sections read: BUGS.md L887–910 (BUG-MERGER-BOOL-STRING-CAST), L1129–1174 (BUG-BERLINDB-V3-DOUBLE-PRIMARY, BUG-BERLINDB-V3-TIMESTAMP-QUOTING).
- Budget status: within limits (< 900 words synthesis, 7/20 decisions, 3/3 bug patterns used).
- Optimizer: disabled (markdown-only flow).
