# Worklog

Use concise high-value entries only.
This is not a changelog. Do not record routine releases, version bumps, or implementation summaries.

## Template

### YYYY-MM-DD - Summary

- why this is durable
- what future mistake it prevents
- evidence
- where future contributors should look

## Example

### 2026-03-15 - Pagination cursor must be opaque to clients

- **Why durable**: three features so far have tried to expose raw database offsets as pagination cursors, each time creating breaking changes when the underlying query changes
- **Future mistake prevented**: next time a feature adds pagination, the implementer will know to use opaque cursors from the start
- **Evidence**: specs 018, 024, and 031 all required pagination rework; see DECISIONS.md entry on API pagination
- **Where to look**: `src/api/pagination.ts`, `docs/memory/DECISIONS.md`

## Counter-Example (do not write entries like this)

> ### 2026-03-15 - Updated pagination
>
> - Changed pagination to use cursors
> - Deployed to staging

This is a changelog entry, not a durable lesson. It records what happened, not what was learned.

---

### 2026-05-20 - Feature 006 logger establishes hook parameter adaptation and duration measurement patterns

- **Why durable**: Future modules that hook into WordPress execution flows will encounter parameter signature changes and timing requirements. The logger's solutions are reusable.
- **Future mistake prevented**: Next feature that needs to extract data from hook-passed objects won't directly call methods without defensive checks. Next feature that needs timing won't rely on hook parameters for duration. Next feature with feature-specific admin UI won't couple assets to main manager builds.
- **Evidence**: Feature 006 logger (commit hash pending) established three decision patterns: DEC-HOOK-PARAM-EXTRACTION (defensive object method calls), DEC-DURATION-CALC-TIMESTAMPS (internal timing via microtime), and two architecture patterns: PATTERN-STAGE-NAMING (variable clarity in multi-stage processing) and PATTERN-FEATURE-ASSET-SEPARATION (independent asset builds per feature module).
- **Where to look**: `docs/memory/DECISIONS.md` (DEC-HOOK-PARAM-EXTRACTION, DEC-DURATION-CALC-TIMESTAMPS, DEC-VARIADIC-CALLBACK-WRAP), `docs/memory/ARCHITECTURE.md` (PATTERN-STAGE-NAMING, PATTERN-FEATURE-ASSET-SEPARATION), `includes/Modules/Logger/AcrossAI_Ability_Logger.php` (implementation), `specs/006-ability-execution-logger/` (design).
