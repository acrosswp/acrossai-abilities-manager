# Memory Synthesis

## Current Scope
Feature 021 resolves all remaining WordPress Plugin Check findings after the CI was established in Feature 020. Affected modules: `Abilities` processor/sanitizer/validator/query, `Logger` query layer, `Main.php`, `uninstall.php`, `AcrossAI_Ability_Override_Processor`, `AcrossAI_I18n`, `.github/workflows/plugin-check.yml`, and governance artifacts (AGENTS.md, CONSTITUTION.md, DECISIONS.md, INDEX.md).

## Relevant Decisions

- **DEC-EVAL-PHP-CODE** (Status: Active → will be Superseded by this feature; Source: DECISIONS.md)
  Reason Included: Feature 021 directly supersedes this decision. The rule "eval() must never be removed without replacing the php_code ability execution mechanism" is satisfied — Feature 021 replaces php_code with the registered-callback model. DEC-PLUGIN-CHECK-PRODUCTION-SURFACE supersedes this entry; implement accordingly.

- **DEC-UTILITY-STATIC-ONLY** (Status: Active; Source: DECISIONS.md)
  Reason Included: `AcrossAI_Abilities_Sanitizer` and `AcrossAI_Abilities_Validator` are utility classes — 100% static methods, no singleton. FR-010a and FR-010b changes must modify static methods only. Do not add a singleton or instance() to these classes.

- **DEC-DB-WRITE-BOUNDARY-GUARD** (Status: Active; Source: DECISIONS.md)
  Reason Included: `AcrossAI_Abilities_Query.php` (FR-010c) removes php_code from enum allowlist. The guard is at method level, not caller level. Removing php_code from the enum enforces the boundary without touching caller logic.

- **PATTERN-UNINSTALL-DATA-GATE** (Status: Active; Source: ARCHITECTURE.md)
  Reason Included: CHANGE-7 modifies uninstall.php. The data-gate pattern (DROP TABLE only when `acrossai_abilities_uninstall_delete_data` is truthy; options always removed) MUST be preserved. Only rename, NoCaching, and %i changes are in scope.

- **BUG-PLUGIN-CHECK-ACTION-NODE24** (Status: Active; Source: BUGS.md)
  Reason Included: Establishes why `WordPress/plugin-check-action@v1` must NOT be reintroduced. Feature 021 CI work builds on the direct `wp-env run cli wp plugin check` pattern from Feature 020 commit d58f487.

## Active Architecture Constraints

- **AC-HOOKS-MAIN**: Only `includes/Main.php` calls `$this->loader->add_action()` / `add_filter()`. (Reason Included: CHANGE-6 removes `set_locale()` which is registered by the Loader in Main. The removal must happen in `Main.php`; do not remove it in any other file. Source: CONSTITUTION.md §I)

- **AC-FILE-HEADER-PATTERN**: `@package AcrossAI_Abilities_Manager`, `@subpackage full/path`, `@since 0.1.0` required on all modified PHP files. (Reason Included: Every PHP file edited in this feature must have compliant file headers or PHPCS will report new errors. Source: ARCHITECTURE.md)

- **CONSTITUTION.md §II (v1.4.3 → v1.4.4)**: Current constitution still contains the eval() CI suppression note. Feature 021 updates §II to v1.4.4 with five new rules. Do not leave §II in a state that still references the eval() suppression as intentional. (Reason Included: Feature 021 owns updating the constitution; the old eval() reference is stale after eval() is removed. Source: CONSTITUTION.md §II)

## Accepted Deviations

- **DEC-SETTINGS-API-DEVIATION**: Settings API accepted for scalar settings pages. (Reason Included: uninstall.php reads `acrossai_abilities_uninstall_delete_data` option — this was set via Settings API in Feature 019. CHANGE-7 must not change the option name or reading logic. Source: DECISIONS.md)

## Relevant Security Constraints

- **eval() removal is a security improvement**: The DEC-EVAL-PHP-CODE OWASP A03 context notes that `$input` at execution time is caller-controlled (not admin-gated). The registered-callback model removes this exposure entirely. No new security risk is introduced by the replacement.

- **Registered-callback trust model**: `apply_filters('acrossai_abilities_registered_callbacks', array())` is consumed only by version-controlled plugin/theme code. This is the same trust model as other WordPress plugin hooks. Existing rows with `callback_type = php_code` must fail closed — return `WP_Error('unsupported_callback_type')` — not execute stored PHP.

- **sanitize_key() on callback key**: The callback key from `callback_config['callback']` MUST be run through `sanitize_key()` before array lookup. This prevents injection via malformed keys.

## Related Historical Lessons

- **BUG-PHPCBF-TABS**: phpcbf converts spaces→tabs. Any Python `str_replace` editing PHP files must use `\t` for indentation, not spaces. (Reason Included: Multiple PHP files will be edited; tab/space mixups will introduce new PHPCS errors.)

- **BUG-PHPCS-DOCBLOCK-CAPITAL**: PHPDoc long descriptions starting with a function name must be manually prefixed with "The " — phpcbf will not capitalize. (Reason Included: CHANGE-8 renames `define()` docblock parameters; verify the long description starts with a capital letter or "The ".)

- **PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT** (from BUGS.md/WORKLOG): Canonical Plugin Check CI: `wp-env start` → `wp-env run cli wp plugin check`. Never reintroduce `WordPress/plugin-check-action@v1`. (Reason Included: FR-003 requires inlined wp-env steps to be preserved.)

## Conflict Warnings

- **Soft conflict — CONSTITUTION.md §II vs spec intent**: Constitution v1.4.3 says the eval() suppression is "intentional" and "All new code MUST remain plugin-check clean." Feature 021 changes the intent: eval() is no longer intentional. Resolution: Feature 021 explicitly owns the §II update to v1.4.4 (FR-019). The old §II text is superseded by the spec. No blocking conflict.

- **Soft conflict — DEC-EVAL-PHP-CODE Rule 4 vs FR-010**: Rule 4 says "eval() must never be removed without replacing the php_code ability execution mechanism." Feature 021 provides the replacement (registered-callback model). The precondition is satisfied. No blocking conflict.

## Retrieval Notes

- Index entries considered: 25 (all active decisions + architecture constraints + bug patterns + security constraints)
- Source sections read: DECISIONS.md DEC-EVAL-PHP-CODE (lines 1020–1045), BUGS.md BUG-PLUGIN-CHECK-ACTION-NODE24, WORKLOG.md last 2 entries, CONSTITUTION.md §I–§IV (lines 1–100)
- Decisions selected (5/5 budget): DEC-EVAL-PHP-CODE, DEC-UTILITY-STATIC-ONLY, DEC-DB-WRITE-BOUNDARY-GUARD, PATTERN-UNINSTALL-DATA-GATE, BUG-PLUGIN-CHECK-ACTION-NODE24
- Architecture constraints selected (3/5 budget): AC-HOOKS-MAIN, AC-FILE-HEADER-PATTERN, CONSTITUTION §II transition
- Accepted deviations (1/3 budget): DEC-SETTINGS-API-DEVIATION
- Security constraints (2/3 budget): registered-callback model, sanitize_key requirement
- Bug patterns (3/3 budget): BUG-PHPCBF-TABS, BUG-PHPCS-DOCBLOCK-CAPITAL, PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT
- Worklog items (2/2 budget): Feature 020 Plugin Check CI, Feature 020 CI fix
- Budget status: Within 900 words
