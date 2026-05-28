# Feature Specification: Logger Module — Constitution Compliance

**Feature Branch**: `017-logger-constitution-fix`
**Created**: 2026-05-28
**Status**: Draft
**Input**: Fix five Constitution violations and two warnings in `includes/Modules/Logger/`

---

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Boot Flow Restored (Priority: P1)

A site administrator or plugin developer auditing the codebase observes that all WordPress hook
registrations for the Logger module are centrally declared in `includes/Main.php`
`define_public_hooks()`, with no hooks registered inside the Logger class itself.

**Why this priority**: The Boot Flow Rule is a NON-NEGOTIABLE architectural constraint. Hook
registrations inside feature classes bypass the Loader, create invisible coupling, and are the
highest-severity violation category in the Constitution. Fixing this first ensures all subsequent
fixes are layered on a compliant foundation.

**Independent Test**: Can be verified by inspecting `AcrossAI_Ability_Logger` for the absence of
`add_filter()`/`add_action()` calls and confirming all five Logger hooks appear in
`Main::define_public_hooks()` wired via `$this->loader`. Delivers compliant hook architecture
without requiring any other fix to be present.

**Acceptance Scenarios**:

1. **Given** `AcrossAI_Ability_Logger::boot()` exists with direct `add_filter`/`add_action` calls, **When** the fix is applied, **Then** `boot()` is deleted and no `add_filter`/`add_action` calls remain anywhere in the Logger class.
2. **Given** `Main.php` has a `plugins_loaded → boot` Loader line, **When** the fix is applied, **Then** that line is removed and replaced with the six Loader calls in `define_public_hooks()` using the named `$logger` variable.
3. **Given** the ARCH-ADV-001 Exception comment exists in the codebase, **When** the fix is applied, **Then** the comment is deleted.
4. **Given** the plugin is active, **When** an ability is executed, **Then** the Logger still captures MCP server IDs, starts/finishes pending entries, wraps permission callbacks, and runs cleanup — behaviour is unchanged.

---

### User Story 2 — Correct Text Domain Across Logger REST Controllers (Priority: P2)

A developer running PHPCS on the Logger REST controllers sees zero `WordPress.WP.I18n` violations
because all translatable strings use the correct text domain `'acrossai-abilities-manager'`.

**Why this priority**: Wrong text domain strings cause silent localisation failures and break PHPCS
i18n checks. It is a simple, contained fix with no functional risk. Fixed second because it is a
WordPress Standards (§II) violation, which is a hard DoD gate.

**Independent Test**: Running `grep -r "acrossai-abilities'" includes/Modules/Logger/Rest/` returns
no results. PHPCS passes on both files with zero I18n errors.

**Acceptance Scenarios**:

1. **Given** `AcrossAI_Logger_Controller.php` contains `'acrossai-abilities'` text domains, **When** the fix is applied, **Then** every `__()` call uses `'acrossai-abilities-manager'`.
2. **Given** `AcrossAI_Logger_Logs_Controller.php` contains `'acrossai-abilities'` text domains, **When** the fix is applied, **Then** every `__()` call uses `'acrossai-abilities-manager'`.

---

### User Story 3 — Singleton-Only Access to Logger Query (Priority: P3)

A developer using `AcrossAI_Logger_Query` can only call `get_logs()` through the singleton
`::instance()`, consistent with the Module Contract that forbids static method bypass.

**Why this priority**: Static methods that bypass the singleton pattern violate the Module Contract
(§Architecture) and make the class harder to mock and extend. Fix is contained to removing one
`static` keyword and updating one call site.

**Independent Test**: `grep -n 'public static function get_logs' includes/Modules/Logger/AcrossAI_Logger_Query.php` returns no results. The logs endpoint continues to return correct results through `AcrossAI_Logger_Query::instance()->get_logs( $args )`.

**Acceptance Scenarios**:

1. **Given** `get_logs()` is declared `public static`, **When** the fix is applied, **Then** the method is declared `public function get_logs()` (no `static` keyword).
2. **Given** `AcrossAI_Logger_Logs_Controller` calls `AcrossAI_Logger_Query::get_logs( $args )`, **When** the fix is applied, **Then** the call site reads `AcrossAI_Logger_Query::instance()->get_logs( $args )`.
3. **Given** both files are changed, **When** PHPStan level 8 is run, **Then** zero errors are reported.

---

### User Story 4 — Sanitize Callbacks at REST Entry Point (Priority: P2)

A security auditor reviewing the Logger REST endpoint confirms that the `source` and `status`
parameters are sanitized before reaching any filtering or query logic.

**Why this priority**: §IV Security First is marked NON-NEGOTIABLE. Missing sanitize callbacks at
REST entry points are a direct OWASP A03 (Injection) risk. P2 because it is a security fix sharing
the same file as the text-domain fix, but is classified separately as it has security implications.

**Independent Test**: The `register_routes()` arg definitions for `source` and `status` both include
`'sanitize_callback' => 'sanitize_text_field'`. The enum allowlist inside `get_logs()` is
unchanged. REST endpoint returns unchanged results for valid inputs.

**Acceptance Scenarios**:

1. **Given** `source` arg has no `sanitize_callback`, **When** the fix is applied, **Then** `'sanitize_callback' => 'sanitize_text_field'` appears in the `source` arg definition in `register_routes()`.
2. **Given** `status` arg has no `sanitize_callback`, **When** the fix is applied, **Then** `'sanitize_callback' => 'sanitize_text_field'` appears in the `status` arg definition in `register_routes()`.
3. **Given** a valid `source` and `status` value is sent, **When** the endpoint is called, **Then** the response is identical to pre-fix behaviour.

---

### User Story 5 — Utility Class in Correct Directory (Priority: P3)

A developer adding a new module verifies that `AcrossAI_Logger_Source_Detector` lives in
`includes/Utilities/` (shared logic layer) rather than inside the Logger module directory, as
required by §I and the Directory Layout rule.

**Why this priority**: Directory Layout violations create namespace inconsistencies and make the DRY
principle harder to enforce. The fix requires a `git mv` and namespace update, so it is placed last
among PHP fixes to avoid polluting the diffs of earlier fixes.

**Independent Test**: `ls includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php` returns
"No such file". `ls includes/Utilities/AcrossAI_Logger_Source_Detector.php` succeeds. Composer
autoload resolves the class correctly.

**Acceptance Scenarios**:

1. **Given** the file is at `includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php`, **When** the fix is applied, **Then** the file is at `includes/Utilities/AcrossAI_Logger_Source_Detector.php` and the original path no longer exists.
2. **Given** the file is moved, **When** the namespace is updated, **Then** it reads `AcrossAI_Abilities_Manager\Includes\Utilities`.
3. **Given** the namespace changes, **When** `AcrossAI_Ability_Logger.php` is updated, **Then** its `use` import reads `use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Source_Detector;`.
4. **Given** the move and namespace change, **When** `composer dump-autoload` is run, **Then** it completes without errors and PHPStan reports zero errors.

---

### User Story 6 — BerlinDB Constructor Exception Documented (Priority: P4)

A future compliance auditor reading `AcrossAI_Ability_Logs_Table` understands why the constructor
is not private and does not file a false violation against the Module Contract.

**Why this priority**: Documentation-only change with no runtime impact. Lowest priority among all
fixes.

**Independent Test**: A PHPDoc block above `instance()` in `AcrossAI_Ability_Logs_Table.php` names
the BerlinDB framework constraint, explains the consequence of making the constructor private, and
labels the deviation a "Justified exception to the Module Contract".

**Acceptance Scenarios**:

1. **Given** no PHPDoc exists above `instance()`, **When** the documentation is added, **Then** the PHPDoc includes the BerlinDB rationale and the "@return self" tag.

---

### User Story 7 — Constitution Module List Patched (Priority: P4)

A developer reading the Constitution's Directory Layout sees `Logger/` listed alongside the other
four modules, and §I correctly names five active feature areas.

**Why this priority**: Documentation-only change; committed in a separate commit from all PHP fixes
per the Constitution Amendment Procedure.

**Independent Test**: `grep 'Logger/' .specify/memory/CONSTITUTION.md` returns a result inside the
Directory Layout code block. `grep 'five active feature areas' .specify/memory/CONSTITUTION.md`
returns a result. Version footer reads `1.4.2`.

**Acceptance Scenarios**:

1. **Given** `Logger/` is absent from the Directory Layout, **When** the amendment is applied, **Then** `Logger/` appears between `Abilities/` and `Webmcp/` in the Directory Layout code block.
2. **Given** §I says "four active feature areas", **When** the amendment is applied, **Then** it reads "five active feature areas" and "Ability Execution Logging" is listed.
3. **Given** version is `1.4.1`, **When** the amendment is applied, **Then** version is `1.4.2`, `Last Amended` is `2026-05-28`, and the sync impact HTML comment block is updated.
4. **Given** PHP fixes and Constitution change are staged, **When** committing, **Then** the Constitution change is committed separately from all PHP changes.

---

### Edge Cases

- What if the Loader class does not accept a 5th `$accepted_args` parameter? — Do not pass it; verify the Loader signature before writing.
- What if `composer dump-autoload` fails after FIX-5? — Stop and fix the namespace or autoload configuration before running static analysis.
- What if PHPStan reports errors after an individual fix? — Do not proceed to the next fix; resolve errors for the current fix first.
- `AcrossAI_Logger_Source_Detector` has 6 static call sites, all in `AcrossAI_Ability_Logger.php` (confirmed by code audit). All 6 MUST be converted to `AcrossAI_Logger_Source_Detector::instance()->method()` as part of FIX-5.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `AcrossAI_Ability_Logger::boot()` MUST be deleted; all hook registrations for the Logger MUST appear exclusively in `Main::define_public_hooks()` wired via the Loader using a named `$logger` variable.
- **FR-002**: The `plugins_loaded → boot` Loader line MUST be removed from `Main.php`.
- **FR-003**: All six Logger hooks MUST be registered with correct `$accepted_args` counts matching each callback's real parameter count.
- **FR-004**: Every `__()` call in `AcrossAI_Logger_Controller.php` and `AcrossAI_Logger_Logs_Controller.php` MUST use the text domain `'acrossai-abilities-manager'`.
- **FR-005**: `AcrossAI_Logger_Query::get_logs()` MUST NOT be declared `static`; the call site in `AcrossAI_Logger_Logs_Controller` MUST use `AcrossAI_Logger_Query::instance()->get_logs( $args )`.
- **FR-006**: The `source` and `status` REST route args in `AcrossAI_Logger_Logs_Controller::register_routes()` MUST include `'sanitize_callback' => 'sanitize_text_field'`.
- **FR-007**: The enum allowlist validation inside `AcrossAI_Logger_Query::get_logs()` MUST remain unchanged.
- **FR-008**: `AcrossAI_Logger_Source_Detector` MUST reside at `includes/Utilities/AcrossAI_Logger_Source_Detector.php` with namespace `AcrossAI_Abilities_Manager\Includes\Utilities`. After the move it MUST also adopt the full Module Contract singleton pattern: add `protected static $_instance = null;` and `public static function instance(): self`. All 10 existing `public static` methods MUST have the `static` keyword removed (converted to instance methods); private static state MUST be converted to instance properties. All 6 call sites in `AcrossAI_Ability_Logger.php` MUST be updated from `AcrossAI_Logger_Source_Detector::method()` to `AcrossAI_Logger_Source_Detector::instance()->method()`.
- **FR-009**: A PHPDoc block above `instance()` in `AcrossAI_Ability_Logs_Table.php` MUST document the BerlinDB constructor exception and its justification.
- **FR-010**: `CONSTITUTION.md` MUST include `Logger/` in the Directory Layout, "five active feature areas" in §I, version `1.4.2`, updated `Last Amended` date, and an updated sync impact comment. This change MUST be committed separately from all PHP changes.
- **FR-016**: Each fix (FIX-1, FIX-2, FIX-3, FIX-4, FIX-5, WARNING-1) MUST be committed individually after its own PHPStan + PHPCS + PHPUnit verification passes. WARNING-2 is committed separately. Commit order MUST follow fix order.
- **FR-011**: After FIX-5, `composer dump-autoload` MUST be run before any static analysis check.
- **FR-012**: Each fix MUST individually leave PHPStan level 8 and PHPCS reporting zero errors before the next fix is applied.
- **FR-015**: New or updated unit tests MUST be written and pass for each fix — this is a full Definition-of-Done requirement. Tests for refactored logic MUST cover the post-refactor code paths (e.g., hook registration via Loader for FIX-1; singleton call path for FIX-3; sanitized input path for FIX-4; utility class at new namespace for FIX-5).
- **FR-013**: No public method signatures may be altered except: (a) removing `static` from `AcrossAI_Logger_Query::get_logs()` (FIX-3), and (b) removing `static` from all public methods of `AcrossAI_Logger_Source_Detector` as required by FR-008 (FIX-5 singleton adoption). All other public method signatures MUST remain unchanged.
- **FR-014**: No changes may be made to the DB schema, REST response shape, or filtering logic.

### Key Entities

- **AcrossAI_Ability_Logger**: Logger module orchestrator; hook callbacks live here. Boot method to be deleted.
- **AcrossAI_Logger_Query**: Data-access layer; `get_logs()` de-staticified.
- **AcrossAI_Logger_Controller**: REST orchestrator controller; text domain corrected.
- **AcrossAI_Logger_Logs_Controller**: REST sub-controller; text domain, sanitize callbacks, and call site updated.
- **AcrossAI_Logger_Source_Detector**: Utility class moved from Logger module to `includes/Utilities/`; adopts singleton `instance()` pattern and converts static methods to instance methods (Module Contract applies to all plugin classes).
- **AcrossAI_Ability_Logs_Table**: BerlinDB table class; constructor exception documented via PHPDoc.
- **Main**: `includes/Main.php`; receives the six Logger Loader registrations in `define_public_hooks()`.
- **CONSTITUTION.md**: Project constitution; amended to v1.4.2 in a separate commit.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: `grep -rn 'add_filter\|add_action' includes/Modules/Logger/AcrossAI_Ability_Logger.php` returns zero results.
- **SC-002**: `grep -n "acrossai-abilities'" includes/Modules/Logger/Rest/` returns zero results across both controller files.
- **SC-003**: `grep -n 'public static function get_logs' includes/Modules/Logger/AcrossAI_Logger_Query.php` returns zero results.
- **SC-004**: `grep -n 'sanitize_callback' includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` returns at least two results (one for `source`, one for `status`).
- **SC-005**: `ls includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php` fails; `ls includes/Utilities/AcrossAI_Logger_Source_Detector.php` succeeds.
- **SC-006**: `composer run phpstan` reports zero errors at level 8.
- **SC-007**: `composer run phpcs` reports zero errors. Pre-existing `PSR2.Classes.PropertyDeclaration.Underscore` warnings on `$_instance` properties are exempt — they existed before Feature 017 and are not introduced by any change in this feature.
- **SC-008**: `grep 'Logger/' .specify/memory/CONSTITUTION.md` returns a result inside the Directory Layout block; `grep '1.4.2' .specify/memory/CONSTITUTION.md` returns a result.
- **SC-009**: Logger behaviour is unchanged — ability execution logs are still created, MCP server IDs captured, and cleanup scheduled correctly.
- **SC-010**: PHPUnit test suite passes with zero failures after all fixes are applied; new/updated tests covering each refactored code path are present in `tests/phpunit/`.

---

## Assumptions

- The Loader class (`includes/Loader.php`) accepts a 5th `$accepted_args` parameter on `add_action`/`add_filter`. If it does not, the 5th argument is omitted.
- `AcrossAI_Logger_Source_Detector` has exactly 6 call sites, all in `AcrossAI_Ability_Logger.php` (confirmed by code audit). No other files reference this class.
- The plugin text domain is `'acrossai-abilities-manager'` as declared in the plugin header. No other text domain is used anywhere in the plugin.
- `includes/Utilities/` already exists; no new directory creation is required for FIX-5.
- The BerlinDB framework is a fixed dependency; the constructor cannot be made private without breaking table registration.
- PHPStan and PHPCS are available via `composer run phpstan` and `composer run phpcs` respectively.
- The Constitution Amendment Procedure in §Governance is followed in full for WARNING-2.

---

## Clarifications

### Session 2026-05-28

- Q: Should new/updated unit tests be written as part of this feature (full DoD), or is PHPStan + PHPCS sufficient for compliance refactors? → A: New/updated unit tests are required for each fix (full DoD — Option A).
- Q: Should each fix be committed individually (one commit per fix), or all PHP fixes in one commit with WARNING-2 separate? → A: One commit per fix — FIX-1 through WARNING-1 each committed individually; WARNING-2 separate (Option A).
- Q: After moving `AcrossAI_Logger_Source_Detector` to `includes/Utilities/`, should it adopt the singleton `instance()` pattern, or remain a pure static utility exempt from the Module Contract? → A: Must adopt the full singleton pattern — Module Contract applies to all plugin classes including Utilities/ (Option B). All 10 public static methods converted to instance methods; 6 call sites in `AcrossAI_Ability_Logger.php` updated accordingly. FR-013 narrowed to allow this.
