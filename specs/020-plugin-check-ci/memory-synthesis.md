# Memory Synthesis

## Current Scope
Feature 020 adds a GitHub Actions CI workflow (WordPress Plugin Check Action), fixes all Plugin Check
compliance issues in the codebase (12 `error_log()` guards across 5 PHP files, plugin header field,
eval PHPCS suppression), and updates `AGENTS.md` and `CONSTITUTION.md`. Affected files: `admin/Main.php`,
`includes/Utilities/AcrossAI_Sanitizer.php`, `includes/Utilities/AcrossAI_Logger_Formatter.php`,
`includes/Modules/Logger/AcrossAI_Ability_Logger.php`,
`includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`, `.specify/memory/CONSTITUTION.md`,
`AGENTS.md`. One new file: `.github/workflows/plugin-check.yml`.

## Relevant Decisions
- **DEC-UTILITY-STATIC-ONLY**: Utility classes are 100% static; only orchestrators use singleton.
  (Reason Included: `AcrossAI_Sanitizer` and `AcrossAI_Logger_Formatter` are utilities — only adding
  an `if` guard block, no structural class changes. Status: Active, Source: DECISIONS.md)
- **DEC-SETTINGS-API-DEVIATION**: Constitution was last bumped to `1.4.2` in Feature 019 when this
  deviation was added. (Reason Included: establishes current version baseline before the `1.4.2 → 1.4.3`
  bump in this feature. Status: Active, Source: DECISIONS.md)

## Active Architecture Constraints
- **AC-FILE-HEADER-PATTERN**: All PHP files must carry `@package AcrossAI_Abilities_Manager`,
  `@subpackage full/path`, `@since 0.1.0`. Do not disturb file headers in any of the 5 files touched.
  (Reason Included: 5 PHP files modified; an accidental header change would fail PHPCS.
  Source: ARCHITECTURE.md)
- **AC-HOOKS-MAIN**: Only `Main.php` registers hooks via the Loader. Confirmed: this feature adds no
  hooks — constraint is not violated. (Reason Included: sanity-check for any new wiring.
  Source: CONSTITUTION.md §I)

## Accepted Deviations
- **ARCH-ADV-001**: `boot()` conditional hook wiring — scope: Override Processor only.
  (Reason Included: none of the 5 target PHP files use `boot()`; deviation is non-applicable here.
  Status: Accepted-Deviation)

## Relevant Security Constraints
- None of the four SEC-0x constraints (slug sanitisation, before_save hook cast, multisite table prefix,
  strict type comparison) apply to CI workflow creation or `error_log()` guard wrapping.

## Related Historical Lessons
- **BUG-UNCONDITIONAL-ASSET-INCLUDE** (admin/Main.php context): The `error_log()` at line 123 is the
  fallback branch of a `file_exists()` guard — the correct pattern already exists around it. When
  wrapping with `WP_DEBUG_LOG`, do NOT remove or restructure the surrounding `if ( ! file_exists(...) )`
  conditional — only wrap the `error_log()` call inside that block.
  (Reason Included: `admin/Main.php` is one of the 5 target files and this bug documents the exact
  context of the line 123 call.)
- **BUG-PHPCBF-TABS**: PHP files use tab indentation enforced by phpcbf. The new `if ( defined(...) )`
  block and its body must use `\t` characters, not spaces. Any `str_replace` targeting an indented line
  must match tabs. Drafting the if-block with spaces then running phpcbf will reindent correctly, but
  subsequent string searches will miss the string.
  (Reason Included: all 5 modified PHP files are subject to PHPCS tab enforcement; wrong indentation
  will cause immediate PHPCS failure.)
- **CONSTITUTION-VERSION-PATTERN**: Every version bump to CONSTITUTION.md must update the
  `<!-- SYNC IMPACT REPORT -->` HTML comment at the top, listing: version change, modified sections,
  rationale, templates reviewed, and deferred TODOs.
  (Reason Included: version bump `1.4.2 → 1.4.3` is in scope; the sync report must match.)

## Conflict Warnings
- None. All spec requirements are consistent with project memory, architecture constraints, and accepted
  deviations.
- Soft note: The spec correctly directs the `phpcs:ignore` comment to move INSIDE the `if` block —
  this is consistent with PHPCS behaviour where the ignore applies to the specific statement line.

## Retrieval Notes
- Index entries considered: 20 scanned; 2 decisions selected; 2 architecture constraints; 1 deviation
  (non-applicable, confirmed); 0 security constraints; 3 bug patterns (BUG-UNCONDITIONAL-ASSET-INCLUDE,
  BUG-PHPCBF-TABS, BUG-PHPCS-DOCBLOCK-CAPITAL); 1 worklog item (2026-05-29 Feature 019 confirms
  CONSTITUTION.md at v1.4.2).
- Source sections read: BUGS.md (3 entries), ARCHITECTURE.md (AC-FILE-HEADER-PATTERN snippet),
  WORKLOG.md (2026-05-29 entry).
- Budget status: Within all limits. Synthesis < 500 words.
