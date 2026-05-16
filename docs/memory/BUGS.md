# Bug Patterns


---

## Active Bug Patterns

### 2026-05-16 — BerlinDB unlimited query: `number => -1` silently becomes 1

**Status**: Active

**Symptoms**
`get_all_overrides()` returned exactly one row instead of all rows. No error.

**Root Cause**
BerlinDB's query builder passes `number` through `absint()`. `absint(-1) = 1`. The intended "unlimited" value of `-1` becomes a LIMIT of 1.

**Future mistake prevented**
Never use `number => -1` for unlimited BerlinDB queries. Always use `number => 0` (no LIMIT clause).

**Evidence**
Fixed in `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php::get_all_overrides()` (2026-05-16). Changed from `9999` → `0` after discovering the `absint` behavior.

**Prevention / Detection**
Code review: grep for `'number' => -1` in BerlinDB query() calls.

**Where to look next**
`includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` (get_all_overrides),
`specs/004-ability-override-processor/spec.md` (SC-004).

---

### 2026-05-16 — `inject_override_args` flat-path mistake: writing top-level `$args` keys

**Status**: Active

**Symptoms**
Override values for `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers` were written to flat top-level keys (`$args['readonly']` etc.) that WP core / merger never reads.

**Root Cause**
WP Abilities API uses a nested `$args` structure. Merger reads `$args['meta']['annotations']['readonly']`, `$args['meta']['show_in_rest']`, `$args['meta']['mcp']['public']`, etc. Writing to flat top-level keys has no effect — values are silently ignored.

**Future mistake prevented**
Always confirm `$args` write paths against `AcrossAI_Ability_Merger.php` read paths before implementing any new field injection. See FR-009 field-path table in `specs/004-ability-override-processor/spec.md`.

**Evidence**
Corrected in `AcrossAI_Ability_Override_Processor::inject_override_args()` (2026-05-16). Confirmed from `AcrossAI_Ability_Merger.php` grep of `annotations`, `show_in_rest`, `get_meta_item`.

**Prevention / Detection**
FR-009 field-path table in `specs/004-ability-override-processor/spec.md` is the canonical reference.

**Where to look next**
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (inject_override_args),
`includes/Modules/Sitewide/AcrossAI_Ability_Merger.php` (flatten / get_meta_item),
`specs/004-ability-override-processor/spec.md` (FR-009).


## Template
### YYYY-MM-DD - Bug / Failure Pattern
**Status**
Active | Monitored | Retired

**Symptoms**
What was observed?

**Root Cause**
What actually caused it?

**Future mistake prevented**
What change pattern should future work avoid?

**Evidence**
Failing test, production incident, review finding, or verified fix.

**Prevention / Detection**
How should future work avoid it and how can we catch it sooner?

**Where to look next**
Files, modules, logs, or checks maintainers should inspect.
