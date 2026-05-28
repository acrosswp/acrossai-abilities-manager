# Memory Synthesis

## Current Scope
Feature 017 — Logger Module Constitution Compliance. Affects: `includes/Modules/Logger/` (AcrossAI_Ability_Logger, AcrossAI_Logger_Query, Rest/AcrossAI_Logger_Controller, Rest/AcrossAI_Logger_Logs_Controller, Database/AcrossAI_Ability_Logs_Table), `includes/Modules/Logger/AcrossAI_Logger_Source_Detector` (moving to `includes/Utilities/`), `includes/Main.php`, and `.specify/memory/CONSTITUTION.md`. Seven compliance items: FIX-1 (Boot Flow Rule), FIX-2 (text domain), FIX-3 (static singleton bypass), FIX-4 (sanitize_callback), FIX-5 (utility class location + singleton), WARNING-1 (BerlinDB PHPDoc exception), WARNING-2 (Constitution v1.4.2).

## Relevant Decisions
- **ARCH-ADV-001** — boot() conditional deviation (Reason Included: FIX-1 removes Logger's unqualified ARCH-ADV-001 claim; the deviation is scoped to `AcrossAI_Ability_Override_Processor::boot()` only — Logger has no PATH A/B conditional logic and has no valid claim to this exception. Status: Active for Override Processor only. Source: DECISIONS.md)
- **DEC-UTILITY-STATIC-ONLY** — Utilities are 100% static (Reason Included: FIX-5 moves Source Detector to Utilities/ and adopts singleton. The decision's own criterion is "no mutable state"; Source Detector has mutable `$is_mcp_context`/`$mcp_server_id` state, so it falls outside the decision's scope. Constitution Module Contract supersedes. Decision example entry for this class must be updated post-implementation. Status: Active — partially superseded for stateful classes. Source: DECISIONS.md)
- **DEC-TABLE-SOFT-SINGLETON** — BerlinDB Table subclasses must NOT have a private constructor (Reason Included: WARNING-1 adds PHPDoc exception comment to `AcrossAI_Ability_Logs_Table.instance()`. Status: Active. Source: DECISIONS.md)
- **DEC-HOOK-PARAM-EXTRACTION** — Hook object parameter extraction via method_exists check (Reason Included: Logger's `capture_mcp_server_id` and `finish_pending_entry` use method_exists adaption — MUST be preserved unchanged in FIX-1 refactor. Status: Active. Source: DECISIONS.md)
- **DEC-VARIADIC-CALLBACK-WRAP** — Variadic callback wrapping for permission callbacks (Reason Included: `wrap_permission_callback` forwards-compatibility pattern MUST be preserved unchanged. Status: Active. Source: DECISIONS.md)

## Active Architecture Constraints
- **AC-HOOKS-MAIN** — Only `Main.php` calls `loader->add_action/add_filter`; named variable before Loader call (Reason Included: Core of FIX-1 — all six Logger hooks must be wired in `define_public_hooks()`. Source: CONSTITUTION.md §I)
- **Module Contract** — Every feature class implements singleton `instance()`, private constructor, no register_hooks delegation (Reason Included: Core of FIX-3 and FIX-5. Source: CONSTITUTION.md §Architecture)
- **Boot Flow Rule** — `Main.php` is sole source of hook registration; no hook code in `load_dependencies()` or feature-class constructors (Reason Included: FIX-1 deletes `boot()`. Source: CONSTITUTION.md §Architecture)
- **AC-FILE-HEADER-PATTERN** — `@package AcrossAI_Abilities_Manager`, `@subpackage full/path`, `@since 0.1.0` (Reason Included: Moved file `AcrossAI_Logger_Source_Detector.php` needs header updated to Utilities path. Source: ARCHITECTURE.md)
- **Constitution §IV Security First (NON-NEGOTIABLE)** — sanitize_callback required at every REST entry point (Reason Included: Core of FIX-4. Source: CONSTITUTION.md §IV)

## Accepted Deviations
- **ARCH-ADV-001** — Override Processor boot() PATH A/B conditional hook wiring (Reason Included: Logger falsely claims this; removing boot() is the correct fix. This deviation does NOT cover the Logger. Status: Accepted-Deviation for Override Processor only)
- **DEC-TABLE-SOFT-SINGLETON** — BerlinDB Table subclasses use soft singleton (no private constructor) (Reason Included: WARNING-1 PHPDoc documents this justified exception. Status: Accepted-Deviation)

## Relevant Security Constraints
- **FIX-4 / Constitution §IV** — `sanitize_callback => sanitize_text_field` MUST be added to `source` and `status` REST args. Enum allowlist inside `get_logs()` is a separate concern and MUST remain. Both are required — they are defence-in-depth. (Source: CONSTITUTION.md §IV, spec FR-004 / FR-006)
- **SEC-04** — Strict type comparison for access control (Reason Included: General guard during all PHP changes. Source: security-constraints.md)

## Related Historical Lessons
- **BUG-PHPCS-DOCBLOCK-CAPITAL** — PHPDoc long description starting with a function/class name will fail PHPCS. WARNING-1 PHPDoc block must start with "Note:" (capital) not a bare name. (Reason Included: WARNING-1 adds a PHPDoc block)
- **BUG-PHPCBF-TABS** — phpcbf converts space indentation to tabs; any Python str_replace must use `\t` not spaces (Reason Included: All PHP file edits in this feature)
- **BUG-PHPSTAN-SILENT-PASS** — PHPStan exit 0 + no output = clean pass; silence is correct (Reason Included: FR-012 requires per-fix PHPStan pass verification)

## Conflict Warnings
- **SOFT — `DEC-UTILITY-STATIC-ONLY` vs FR-008/Q3→B**: The decision names `AcrossAI_Logger_Source_Detector` as a static-only utility. However, the class has mutable static state (`$is_mcp_context`, `$mcp_server_id`), which places it outside the decision's own "no mutable state" criterion. The Constitution Module Contract ("Every feature class MUST implement singleton") takes precedence. Q3→B explicitly confirmed singleton adoption. **Resolution: proceed with FIX-5 singleton adoption; update `DEC-UTILITY-STATIC-ONLY` post-implementation to remove the stale example.** Not a hard blocker.
- **NOTE — ARCH-ADV-001 misuse**: Logger's existing `boot()` comment claims `ARCH-ADV-001` exception. The accepted deviation is scoped to `AcrossAI_Ability_Override_Processor` only. Logger has no conditional hook logic. Removing `boot()` and the comment is the correct fix. No conflict with the deviation itself.

## Retrieval Notes
- Index entries considered: 20+ (full index read). Selected 5 decisions (budget = 5), 5 architecture constraints (budget = 5), 2 accepted deviations (budget = 3), 2 security constraints (budget = 3), 3 bug patterns (budget = 3), 0 worklog items loaded (2026-05-20 Logger patterns entry noted but not loaded — sufficient from Index summary).
- Source sections read: DECISIONS.md (ARCH-ADV-001, DEC-TABLE-SOFT-SINGLETON, DEC-UTILITY-STATIC-ONLY); BUGS.md (3 patterns); ARCHITECTURE.md (AC-FILE-HEADER-PATTERN, PATTERN-SINGLE-SOURCE-UTILITY); CONSTITUTION.md (Module Contract, Boot Flow Rule verbatim §§169–210).
- `full_memory_read_allowed: false` — adhered. Feature memory file (`specs/017-logger-constitution-fix/memory.md`) does not exist yet.
- Budget status: within all limits.
