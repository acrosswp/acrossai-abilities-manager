# Memory Synthesis: Ability Execution Logger (Feature 006)

**Generated**: 2026-05-19  
**Purpose**: Targeted knowledge for planning phase (Phase: Plan/Implement)

## Current Scope

Feature 006 builds a comprehensive execution logger for all WordPress abilities. The logger:
- Captures 10 fields per execution (slug, source, server_id, user_id, input, output, status, duration_ms, created_at)
- Logs to new BerlinDB table `acrossai_ability_logs` with per-site isolation
- Hooks into 4 execution points: `mcp_adapter_pre_tool_call`, `wp_before_execute_ability`, `wp_after_execute_ability`, and permission_callback wrapper
- Displays logs in REST endpoint + admin Logs tab using @wordpress/dataviews
- Uses Action Scheduler (Composer dependency) for daily auto-pruning

**Affected modules**: Logger (new), Database (new table), REST (new endpoint), Admin/UI (new Logs tab)  
**Integration points**: Core hooks, BerlinDB, Action Scheduler, Dataviews

## Relevant Decisions

### DEC-EARLY-404-REST-CHECK (2026-05-19)
Fail-fast pattern: exclusion checks BEFORE database lookups. Prevents DB queries for excluded resources, eliminates information disclosure, consistent 404 response.

**Application to Feature 006**: When wrapping permission_callback at `wp_register_ability_args` P100001, the early permission denial check must occur before ANY logger processing. Log the permission denial, but ensure the denial is detected and logged without extra DB overhead.

---

### ARCH-ADV-001 (2026-05-16)
Boot() conditional hook registration deviation: Direct add_filter/add_action allowed in boot() for hooks that cannot be conditionally wired through Loader. Prevents false Boot Flow Rule violations.

**Application to Feature 006**: Logger's boot() method registers 4 hooks:
- `mcp_adapter_pre_tool_call` P5 (stash server_id)
- `wp_before_execute_ability` P10 (start pending entry)
- `wp_after_execute_ability` P10 (pop pending entry, write to DB)
- `wp_register_ability_args` P100001 (wrap permission_callback for permission_denied logging)

These hooks are registered conditionally inside boot() because they depend on other processors' registration state. This is an accepted deviation from Boot Flow Rule.

---

### DEC-PROTECTED-SLUGS-PATTERN (2026-05-19)
Centralized exclusion utilities with filter extensibility for reusable access control logic. Single utility class with static methods, filter hook for third-party extensibility, strict type comparison.

**Application to Feature 006**: Not directly applicable (no internal ability slugs are being excluded here). However, the pattern is instructive: if future features want to exclude certain log types (e.g., "hide system logger logs from audit trail"), extract that logic to a utility using the same pattern.

---

## Active Architecture Constraints

### AC-HOOKS-MAIN (Constitution §I)
Only Main.php calls `loader->add_action()/add_filter()`. Wired instance methods use named variable pattern (never inline ::instance()).

**Application to Feature 006**: Main.php boots the logger singleton at plugins_loaded P20:
```php
$logger = AcrossAI_Ability_Logger::instance();
$this->loader->add_action( 'plugins_loaded', $logger, 'boot', 20 );
```

Logger's boot() then wires the 4 core hooks via direct add_filter/add_action (per ARCH-ADV-001 deviation).

---

### AC-QUERY-LAYER-FILTERING (Feature 005)
List endpoint filtering (search, sort, pagination, filtering) MUST occur in query builder layer, not REST controller. Pagination headers (X-WP-Total, X-WP-TotalPages) MUST reflect filtered results.

**Application to Feature 006**: Logger REST endpoint GET /logger/logs receives params (search, orderby, order, source, status, ability_slug). All filtering and sorting MUST happen in `AcrossAI_Logger_Query::get_logs()`, not in the REST controller. This ensures X-WP-Total header reflects only the filtered results.

---

## Relevant Security Constraints

### SEC-04 (Feature 005)
Strict type comparison for access control checks. All array membership checks for access control MUST use `in_array(..., true)` with strict=true.

**Application to Feature 006**: If logger later adds any filtering logic based on ability properties (e.g., "is this ability a system ability?"), always use strict comparison: `in_array($slug, $protected_slugs, true)`.

---

### SEC-03 (Feature 004)
Per-site table isolation for multisite: `AcrossAI_*_Table::$global = false`. Explicit multisite prefix handling via BerlinDB.

**Application to Feature 006**: Logger table MUST have `$global = false` to ensure logs are scoped per-site in multisite installations. BerlinDB automatically handles per-site prefixing.

---

## Related Historical Lessons

### PATTERN-SINGLE-SOURCE-UTILITY (Feature 005)
When logic is used in 2+ places, extract to a single utility class with public static methods. Enables DRY principle, single edit point, easier testing, self-documenting.

**Application to Feature 006**: If log filtering logic (e.g., "filter logs by source") is needed in both query builder AND REST controller, extract to a utility. In this feature, filtering happens entirely in query layer, so no duplication exists. But if future features reference logger-specific filtering logic, extract it.

---

## Conflict Warnings

**None identified**. Feature 006 is orthogonal to Features 004–005. No architecture boundaries violated. ARCH-ADV-001 is already established; Feature 006 can follow the same boot() pattern.

---

## Retrieval Notes

**Index entries considered**: 14 entries scanned  
**Source sections read**: 
- DECISIONS.md: ARCH-ADV-001, DEC-PROTECTED-SLUGS-PATTERN, DEC-EARLY-404-REST-CHECK (3 decisions)
- INDEX.md: 6 architecture constraints, 1 pattern, 5 bug patterns, 4 security constraints (16 index rows)

**Active decisions selected**: 3 (ARCH-ADV-001, DEC-PROTECTED-SLUGS-PATTERN, DEC-EARLY-404-REST-CHECK)  
**Architecture constraints selected**: AC-HOOKS-MAIN, AC-QUERY-LAYER-FILTERING  
**Security constraints selected**: SEC-04, SEC-03  
**Implementation patterns selected**: PATTERN-SINGLE-SOURCE-UTILITY  
**Bug patterns reviewed**: BUG-BERLINDB-UNLIMITED (relevant for query builder), others not directly applicable

**Budget status**: Within limits (3 decisions, 2 arch constraints, 2 security constraints, 1 pattern selected of max 5/5/3/1 budgets)

