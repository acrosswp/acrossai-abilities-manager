# Memory Synthesis

## Current Scope
Spec 009 covers the Abilities module business logic and REST API for database-managed abilities. The affected areas are `includes/Modules/Abilities/Database/`, `includes/Modules/Abilities/Rest/`, runtime registration for published DB abilities, shared utilities under `includes/Utilities/`, and minimal hook wiring in `includes/Main.php`. The feature sits on the unified abilities table shared with Sitewide wrappers rather than introducing a separate storage layer.

## Relevant Decisions
- Project namespaces stay on the `AcrossAI_Abilities_Manager\Includes\*` underscore convention, not ad hoc PSR-4 variants (Reason Included: every new Abilities, Rest, Database, and Utilities class in Spec 009 must match the plugin-wide namespace contract, Status: Active, Source: DEC-NAMESPACE-CONVENTION in DECISIONS.md)
- Utility classes remain static-only and should not grow singleton boilerplate unless they own state or orchestrate hooks (Reason Included: Spec 009 explicitly introduces validator, sanitizer, and formatter utilities, Status: Active, Source: DEC-UTILITY-STATIC-ONLY in DECISIONS.md)
- BerlinDB `Table` subclasses use a soft singleton and must not add a private constructor if activation or tests may instantiate them directly (Reason Included: Spec 009 introduces an Abilities table/query layer and should avoid repeating the activation fatal seen in Feature 008, Status: Active, Source: DEC-TABLE-SOFT-SINGLETON in DECISIONS.md)
- Public query helpers remain authorization-free; callers own permission checks at REST/admin boundaries (Reason Included: Spec 009 needs list/filter/read helpers plus runtime registration paths that may run without a user context, Status: Active, Source: DEC-BY-SOURCE-AUTHZ in DECISIONS.md)
- Permission callback injection is an established pattern, but any fail-open behavior must be a deliberate design choice rather than accidental defaulting (Reason Included: Spec 009 runtime registration builds execution behavior and authenticated-user gating, Status: Active, Source: DEC-PERM-CB in DECISIONS.md)

## Active Architecture Constraints
- `includes/Main.php` is the only Loader hook-registration surface; feature singletons must be resolved to named variables before passing them to the Loader (Reason Included: Spec 009 adds only minimal hook wiring and must not introduce module self-registration, Source: CONSTITUTION.md Boot Flow Rule / AC-HOOKS-MAIN)
- REST controllers should follow the thin orchestrator plus sub-controller split when the feature spans multiple handler groups (Reason Included: Spec 009 already covers read, write, MCP, and categories endpoints, Source: CONSTITUTION.md REST Controller Pattern / AC-REST-SPLIT)
- List filtering, search, sort, and pagination belong in the query-builder layer, not in REST controllers (Reason Included: Spec 009 includes ability list/search/filter behavior and accurate pagination metadata, Source: ARCHITECTURE.md AC-QUERY-LAYER-FILTERING)
- Abilities and Sitewide wrappers share the unified `wp_acrossai_abilities` table; source semantics, not separate tables, distinguish row ownership (Reason Included: Spec 009 must operate on the shared unified storage boundary instead of creating a parallel abilities table, Source: INDEX.md ARCH-UNIFIED-ABILITIES-STORAGE plus ARCHITECTURE.md unified table overview)

## Accepted Deviations
- None selected for this scope (Reason Included: no accepted deviation is required to satisfy the current spec, Status: Accepted-Deviation)

## Relevant Security Constraints
- Ability slugs must be sanitized at every REST entry point and kept within the documented length limit before any lookup or persistence (Reason Included: Spec 009 creates, updates, reads, and filters by ability identity, Source: security-constraints.md SEC-01)
- `before_save` hooks must receive sanitized field payloads, and bool-to-int casting must be re-applied before BerlinDB writes (Reason Included: Spec 009 introduces create/update save flows and post-save hooks, Source: security-constraints.md SEC-02)
- Access-control and permission logic must use strict comparisons and strict membership checks (Reason Included: Spec 009 adds runtime execution gating and REST permission enforcement, Source: security-constraints.md SEC-04)

## Related Historical Lessons
- BerlinDB unlimited queries must use `number => 0`; `-1` is coerced through `absint()` and silently limits results to 1 (Reason Included: Spec 009 plans list and source/status retrieval helpers)
- Ability registration writes must target the nested argument/meta structure expected by merger and registry consumers, not flat top-level args (Reason Included: Spec 009 formats stored ability data into runtime registration payloads)
- Partial-save endpoints must fetch the full saved row before firing after-save hooks; local sparse field arrays are not a safe hook payload (Reason Included: Spec 009 supports sparse updates and mixed editable/read-only fields)

## Resolved Policy Decisions (pre-implementation, must not reopen)
- **PD-001** — `GET /abilities/exposures/{type}` is **admin-only (`manage_options`)**. Same gate as all management endpoints. Full metadata is safe behind admin gate. No separate permission tier.
- **PD-002** — `php_code` and `wp_remote_post` are **in scope** with explicit hardening: blocked-function scan + static-closure wrapping + per-invocation `Throwable` isolation for `php_code`; HTTPS-only + no redirects + 30 s timeout cap + no caller header propagation for `wp_remote_post`.
- **Architecture** — `AcrossAI_Abilities_Query` is the **only new BerlinDB file**. No new Row, Schema, or Table classes. `$table_schema = AcrossAI_Sitewide_Schema::class`, `$item_shape = AcrossAI_Sitewide_Row::class`. `AcrossAI_Ability_Source_Detector` is NOT created — source detection is inline.

## Post-Implementation Security Findings (resolved during governed-implement review)

- **TASK-SEC-001 (MEDIUM — FIXED)**: `eval` is a PHP language construct tokenized as `T_EVAL`, not `T_STRING`. The original blocked-function scan only checked `T_STRING`, so `eval` in user code passed silently. Fix: added explicit `T_EVAL` token check before the `T_STRING` gate in `validate_php_code()`.
- **TASK-SEC-002 (MEDIUM — FIXED)**: `call_user_func` and `call_user_func_array` allow indirect invocation of any blocked function. Added to `PHP_CODE_BLOCKED_FUNCTIONS`. Documented that variable-indirection bypass is an accepted limitation at `manage_options` trust level.
- **TASK-SEC-003 (LOW — FIXED)**: `token_get_all()` with `TOKEN_PARSE` throws `ParseError` in PHP 8+ (not caught by `false === $tokens` check). Wrapped in `try/catch(\ParseError $e)` to return a clean 400 response.
- **ARCH-DOC-001 (NON-BLOCKING — FIXED)**: `ARCHITECTURE.md` incorrectly listed "BerlinDB table, query, schema, and row classes" for `Abilities/Database/`. Updated to accurately reflect only `AcrossAI_Abilities_Query` exists there.
- **ARCH-DOC-002 (NON-BLOCKING — FIXED)**: `CONSTITUTION.md` directory layout listed `CustomAbility/` instead of `Abilities/`. Updated to `Abilities/`.

## Conflict Warnings
- No hard conflicts found between the current spec and selected durable memory.
- Watchpoint: if implementation introduces a separate custom abilities table, that would conflict with the active unified-storage boundary.
- Watchpoint: if implementation pushes filtering into REST handlers for convenience, that would conflict with the active query-layer filtering rule.
- Watchpoint: if implementation adds new BerlinDB Row/Schema/Table files, that violates the zero-duplication contract (plan.md Watchpoints, PD architecture decision).

## Retrieval Notes
- Index entries considered: 15, with selected emphasis on namespace, utilities, table lifecycle, query authorization, hook wiring, REST split, unified storage, slug/input security, and known save/query bugs.
- Source sections read: `.specify/memory/CONSTITUTION.md`, `specs/009-abilities-business-logic-rest/spec.md`, `docs/memory/INDEX.md`, targeted sections from `docs/memory/DECISIONS.md`, `docs/memory/ARCHITECTURE.md`, and `docs/memory/BUGS.md`.
- Feature memory file `specs/009-abilities-business-logic-rest/memory.md` was not present.
- Retrieval budget status: within configured limits; no full durable-memory read was needed.
