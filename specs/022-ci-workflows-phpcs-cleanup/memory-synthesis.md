# Memory Synthesis

## Current Scope

Feature 022 splits the quality-gate CI surface into three dedicated workflows (PHPCS, PHPStan, PHPCompatibility), refactors `phpcs.xml.dist` to eliminate false positives and dead exclusions, and fixes all pre-existing code-quality violations that the tighter scan surfaced. Affected files: `.github/workflows/` (3 new files), `phpcs.xml.dist`, 21 production PHP classes (singleton property rename), 3 files needing file docblocks, 7 boilerplate `index.php` files, and `AbilityOverrideProcessorTest.php` (reflection string updates).

## Key Patterns Established

- **PATTERN-CI-DEDICATED-WORKFLOWS**: Each quality gate has its own workflow file. PHPCS → `phpcs.yml`, PHPStan → `phpstan.yml`, PHPCompatibility → `phpcompat.yml`. Do not combine these into one job.
- **PATTERN-PHPCOMPAT-SCOPE**: PHPCompatibility scans production directories only (`includes/`, `admin/`, `public/`, root PHP files). It does NOT scan `tests/`, `specs/`, `docs/`, etc.
- **PATTERN-SINGLETON-PROPERTY**: The singleton property in all classes is `$instance` (not `$_instance`). The PSR2 standard does not permit underscore prefixes for visibility indication.
- **PATTERN-FILE-DOCBLOCK-FIRST**: File-level docblock must appear immediately after `<?php`, before the `namespace` declaration. Files that place the docblock after `namespace` will fail `FileComment.Missing`.
- **PATTERN-PHPCS-EXCLUDE-DEV-PATHS**: `phpcs.xml.dist` must exclude `tests/`, `.specify/`, `docs/`, `specs/`, `src/`, `.claude/`, `.agents/`, `.github/` via `<exclude-pattern>` so `composer run phpcs` scans production code only.

## Relevant Decisions

- **DEC-PHPCS-DEAD-EXCLUSIONS**: Four exclusions (`MissingPackageTag`, `ClassComment.Missing`, `MissingParamComment`, `DocComment.MissingShort`) were removed because the codebase already satisfies those rules. Do not re-add them — if a future file breaks one of these rules, fix the file rather than suppressing the rule.
- **DEC-PHPCOMPAT-MOVED-TO-WORKFLOW**: PHPCompatibility is no longer in `phpcs.xml.dist`. It runs only in `phpcompat.yml`. Do not add it back to `phpcs.xml.dist`.

## Active Architecture Constraints

- **AC-FILE-HEADER-PATTERN**: `@package AcrossAI_Abilities_Manager`, `@since` required on all production PHP files. File docblock must come before `namespace`.
- **BUG-PLUGIN-CHECK-ACTION-NODE24**: `WordPress/plugin-check-action@v1` must NOT be used — broken on Node 24.16 (ubuntu-latest ≥ 2026-05-25). `plugin-check.yml` uses inline `wp-env run cli wp plugin check` instead.
