# Feature Specification: Plugin Check Remaining Cleanup

**Feature Branch**: `021-plugin-check-cleanup`
**Created**: 2026-05-31
**Status**: Clarified
**Input**: User description: "Fix remaining WordPress Plugin Check findings after Feature 020."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Plugin Check CI passes on production code without false positives (Priority: P1)

A developer opens a pull request. The Plugin Check CI workflow runs automatically. It evaluates only the
installable production plugin files — not hidden development files, Spec Kit artifacts, tests, or local
tooling configs. CI passes when production code is compliant and fails only when a genuine violation
exists in runtime code.

**Why this priority**: This is the primary goal of the feature. Without scoping the scan to the production
surface, every PR is blocked by irrelevant hidden-file findings. Removing those false positives makes the
CI gate actionable and trustworthy.

**Independent Test**: Open a PR on branch `021-plugin-check-cleanup`. Observe the Plugin Check workflow
run. Confirm CI does not report findings for `.specify/`, `.github/`, `tests/`, `docs/`, or hidden
dotfiles. Confirm CI does report a failure if a bare `error_log()` is introduced in a production PHP file.

**Acceptance Scenarios**:

1. **Given** a PR is opened, **When** the Plugin Check CI workflow runs, **Then** hidden files (`.eslintrc`, `.gitmodules`, `.wp-env.json`, `.specify/**`, `.github/**`, `.claude/**`, etc.) produce no findings in the CI output.
2. **Given** a PR is opened, **When** the Plugin Check CI workflow runs, **Then** test files (`tests/**`) produce no findings in the CI output.
3. **Given** a PR is opened, **When** the Plugin Check CI workflow runs, **Then** development tooling files (`phpstan.neon.dist`, `phpcs.xml.dist`, `package.json`, etc.) produce no findings.
4. **Given** production PHP code contains a bare `error_log()` call, **When** Plugin Check CI runs, **Then** CI fails with a relevant violation.

---

### User Story 2 - Production PHP files are Plugin Check clean (Priority: P2)

A developer reviewing the Plugin Check report sees zero production-code findings. The 10 known findings
identified in the production inventory (SQL identifier escaping, REQUEST_URI sanitization, eval()
suppression, uninstall.php variable naming, DROP TABLE suppress codes, Main.php parameter naming, and
manual textdomain loading) are all resolved or have a documented intentional narrow suppression.

**Why this priority**: These findings represent real code-quality and security signals that a WordPress.org
reviewer or automated scanner would flag. Resolving them makes the plugin distributably clean.

**Independent Test**: Run `wp-env run cli wp plugin check acrossai-abilities-manager --include-experimental`
(with the exclusion flags from the workflow) on the branch. Observe zero production-code errors or warnings
with zero production-code errors or warnings; no `eval()` call exists and the `php_code` ability type is replaced by the registered-callback model.

**Acceptance Scenarios**:

1. **Given** the branch is checked out, **When** Plugin Check runs with the production-surface exclusion flags, **Then** `AcrossAI_Ability_Logs_Query.php` reports no `UnescapedDBParameter` findings.
2. **Given** the branch is checked out, **When** Plugin Check runs, **Then** `AcrossAI_Logger_Query.php` reports no `UnescapedDBParameter` findings.
3. **Given** the branch is checked out, **When** Plugin Check runs, **Then** no `eval()` call exists in any production plugin file and no `Generic.PHP.ForbiddenFunctions.Found` finding appears; the `php_code` type is replaced by a registered-callback model.
4. **Given** the branch is checked out, **When** Plugin Check runs, **Then** `AcrossAI_Ability_Override_Processor.php` reports no `MissingUnslash` or `InputNotSanitized` findings.
5. **Given** the branch is checked out, **When** Plugin Check runs, **Then** no `load_plugin_textdomain()` warning appears.
6. **Given** the branch is checked out, **When** Plugin Check runs, **Then** `uninstall.php` reports no `NonPrefixedVariableFound` or `NoCaching` findings.
7. **Given** the branch is checked out, **When** Plugin Check runs, **Then** `Main.php` reports no `VariableConstantNameFound` finding.
8. **Given** an existing ability row has `callback_type = php_code`, **When** the ability is executed, **Then** the processor returns `WP_Error( 'unsupported_callback_type' )` and does not execute the stored PHP code.

---

### User Story 3 - Future Spec Kit plans generate Plugin Check-compliant code automatically (Priority: P3)

A future developer (or AI agent) reads `AGENTS.md` and the project constitution before planning new
features. They see explicit rules about Plugin Check production-surface scoping, `%i` SQL identifier
escaping, and local-only suppressions. The project memory documents why these rules exist. Future plans
do not reproduce the mistakes fixed in this feature.

**Why this priority**: This is the long-term value. Without capturing the patterns, the same findings will
reappear in future features that add SQL queries, uninstall logic, or CI changes.

**Independent Test**: Read `AGENTS.md`, `.specify/memory/CONSTITUTION.md` §II, and `docs/memory/DECISIONS.md`
after this feature is merged. Confirm the three rules are present: production-surface scoping, `%i` for
SQL identifiers, and local-exact suppressions.

**Acceptance Scenarios**:

1. **Given** the branch is merged, **When** `AGENTS.md` is read, **Then** it contains rules for production-surface Plugin Check scanning, `%i` SQL identifiers, forbidden-function removal/replacement, local-exact suppressions, and the PHPCS baseline gate.
2. **Given** the branch is merged, **When** the constitution §II is read, **Then** it includes the Plugin Check production-surface rule, the `%i` rule, the forbidden-function removal rule, the local-suppression-only rule, and the PHPCS baseline constraint at version 1.4.4.
3. **Given** the branch is merged, **When** `docs/memory/DECISIONS.md` is read, **Then** it contains `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE`.

---

### Edge Cases

- What if a future PR adds a new hidden file? The `--exclude-files` list is explicit; the new file will be scanned unless added to the exclusion list. Document the pattern so developers know where to add new exclusions.
- What if `wp plugin check` does not support `--exclude-directories` for a given version installed via WP-CLI? The CI step should fail with a clear error; no silent false-pass. The fix is to ensure Plugin Check is updated.
- What if the `AcrossAI_I18n.php` class is referenced by code other than `Main.php`? Before deleting it, grep for all references. If references exist outside `Main.php`, keep the file and add the narrow suppression instead.
- What if PHPCS reports new errors after the `AcrossAI_Logger_Query.php` SQL refactor? The `%i` placeholder in `$wpdb->prepare()` requires WordPress 6.2+. The plugin already targets WP 6.9+, so this is not a concern.
- What if `composer run phpcs` is added as a required CI check while the baseline is broken? It will block all PRs on pre-existing failures unrelated to the PR changes. The fix is either to scope `phpcs.xml.dist` to the production plugin surface or fix the baseline before making it required.
- What if existing ability rows have `callback_type = php_code` when this feature is deployed? The processor returns `WP_Error( 'unsupported_callback_type' )`. Site operators must recreate affected abilities using a supported callback type. A follow-up task may add an admin notice, but silent execution of stored PHP snippets is never acceptable.

## Requirements *(mandatory)*

### Functional Requirements

**Workflow surface (CHANGE-1)**

- **FR-001**: The Plugin Check workflow step `wp-env run cli wp plugin check acrossai-abilities-manager` MUST include `--exclude-directories` listing `.agents,.claude,.github,.specify,docs,node_modules,scripts,specs,src,tests`.
- **FR-002**: The workflow step MUST include `--exclude-files` listing all known hidden dotfiles and development-only configs (`.distignore`, `.editorconfig`, `.eslintignore`, `.eslintrc`, `.gitattributes`, `.gitignore`, `.gitmodules`, `.nvmrc`, `.prettierignore`, `.wp-env.json`, `AGENTS.md`, `LICENSE.md`, `README.md`, `composer.json`, `composer.lock`, `eslint.config.js`, `package.json`, `package-lock.json`, `phpcs.xml.dist`, `phpstan.neon.dist`, `phpunit.xml.dist`, `webpack.config.js`).
- **FR-003**: The workflow MUST continue to use inlined `wp-env` steps (NOT `WordPress/plugin-check-action@v1` — replaced in Feature 020 due to Node 24.16 silent-exit bug, issue #579).
- **FR-004**: `--include-experimental` MUST remain present. No `ignore-warnings` flag may be added.

**Logger SQL escaping (CHANGE-2, CHANGE-3)**

- **FR-005**: `AcrossAI_Ability_Logs_Query::delete_logs_before_date()` MUST use `$wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, $date )` — no string interpolation of `$table`.
- **FR-006**: `AcrossAI_Ability_Logs_Query::count_logs()` MUST use `$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )` — no string interpolation of `$table`.
- **FR-007**: `AcrossAI_Logger_Query` MUST use `$wpdb->prefix . 'acrossai_ability_logs'` (not `base_prefix`) for the table name.
- **FR-008**: `AcrossAI_Logger_Query` MUST pass the table identifier via `%i` in both the count and select queries. If Plugin Check still flags the WHERE fragment or SELECT SQL, a narrow `PluginCheck.Security.DirectDB.UnescapedDBParameter` ignore with inline rationale MUST be used — not a workflow-level ignore-code.
- **FR-009**: REST response keys (`logs`, `total`, `pages`) and method signatures MUST NOT change.

**eval() removal and registered callback replacement (CHANGE-4)**

- **FR-010**: The `eval()` call in `AcrossAI_Abilities_Processor.php` MUST be removed entirely. The `php_code` ability switch branch MUST be replaced with a registered-callback model: load callbacks via `apply_filters( 'acrossai_abilities_registered_callbacks', array() )`, resolve the key from `callback_config['callback']` with `sanitize_key()`, verify the key is present in the allow-list, and invoke via `call_user_func()`. No `phpcs:ignore` for `Generic.PHP.ForbiddenFunctions.Found` may be added anywhere in production code.
- **FR-010a**: `AcrossAI_Abilities_Sanitizer.php` MUST remove `php_code` from its allowed callback type list and add `registered_callback` as the replacement type, sanitizing the `callback` key via `sanitize_key()` instead of raw `code`.
- **FR-010b**: `AcrossAI_Abilities_Validator.php` MUST remove the `php_code` syntax/blocklist validation path. The `call_user_func` at line 468 (which calls an internal `$validator` callable, not user-supplied code) MUST NOT be removed.
- **FR-010c**: `AcrossAI_Abilities_Query.php` MUST remove `php_code` from its database enum allowlist and add `registered_callback` as the replacement, so the storage layer rejects the deprecated type and accepts the new safe model. The UI that exposes the new type is deferred to the follow-up spec (FR-010e).
- **FR-010d**: Existing ability rows with `callback_type = php_code` MUST fail closed: the processor MUST return `WP_Error( 'unsupported_callback_type', ... )`. Silent no-ops or execution of stored PHP snippets are not acceptable.
- **FR-010e**: Admin UI files (`AbilityForm.jsx`, `CallbackConfigField.jsx`, `AbilitiesList.jsx`, `admin.scss`) are **deferred to a separate follow-up feature**. Feature 021 backend rejection per FR-010d is sufficient to prevent forbidden code execution; the UI cleanup will be tracked in a subsequent spec.
- **FR-011**: `AcrossAI_Ability_Override_Processor` MUST apply `wp_unslash()` and `sanitize_text_field()` to `$_SERVER['REQUEST_URI']` before use. No `phpcs:ignore` comment should remain on that line after the fix.

**Manual textdomain loading (CHANGE-6)**

- **FR-012**: The `set_locale()` call in `Main::__construct()` MUST be removed. The `set_locale()` method in `Main.php` MUST be removed. `AcrossAI_I18n.php` MUST be deleted if no remaining code references it.
- **FR-013**: If `AcrossAI_I18n.php` is deleted, the autoloader and all class references MUST be verified clean before committing.

**uninstall.php cleanup (CHANGE-7)**

- **FR-014**: `uninstall.php` MUST rename `$delete_data` to `$acrossai_delete_data` everywhere it appears.
- **FR-015**: Both DROP TABLE ignore comments MUST include `WordPress.DB.DirectDatabaseQuery.NoCaching`.
- **FR-016**: DROP TABLE calls MUST use `$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_variable )`.

**Main.php constant helper (CHANGE-8)**

- **FR-017**: The private `define()` helper parameters MUST be renamed from `$name`/`$value` to `$constant_name`/`$constant_value`. The method name MUST NOT change. The docblock MUST be updated to match.

**Governance patterns (CHANGE-9)**

- **FR-018**: `AGENTS.md` MUST document: (a) Plugin Check scans production surface only via exclusion flags, (b) `%i` for dynamic SQL table identifiers, (c) forbidden-function findings (`eval()`, `extract()`, shell/process functions) MUST be removed or replaced — not suppressed, (d) local-exact Plugin Check suppressions only for cases with no safe WordPress API replacement, (e) the existing Composer PHPCS/WPCS setup and that repo-wide PHPCS (`composer run phpcs`) MUST NOT be made a required PR gate while the existing baseline fails unrelated files.
- **FR-019**: `.specify/memory/CONSTITUTION.md` MUST be bumped to version 1.4.4 with §II updated to include five rules: Plugin Check production-surface scoping, `%i` SQL identifier escaping, forbidden-function removal policy, local-suppression-only policy, and the PHPCS production-surface/baseline gate constraint. The HTML sync impact report and version footer MUST be updated.
- **FR-020**: `docs/memory/DECISIONS.md` MUST contain entry `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE` documenting the production-surface scoping decision, the `%i` SQL identifier rule, the forbidden-function removal policy (superseding `DEC-EVAL-PHP-CODE` — `eval()` replaced with a registered-callback model; arbitrary stored PHP is no longer accepted), the local-suppression-only rule for non-forbidden exceptional cases, and a note that the repo-wide `composer run phpcs` baseline is not clean — required PR checks must run PHPCS against the production plugin surface or wait until the baseline is fixed.
- **FR-021**: `docs/memory/INDEX.md` MUST be updated with a row for `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE` so the entry is discoverable via the routing map.

### Key Entities

- **Plugin Check scan surface**: The set of files and directories that `wp plugin check` evaluates. Controlled by `--exclude-directories` and `--exclude-files` CLI flags in the workflow.
- **Production plugin files**: PHP files under `includes/`, `admin/`, `uninstall.php`, and the main plugin file — runtime code installed into WordPress. These are always in scope for Plugin Check.
- **Development repository artifacts**: `.specify/`, `.github/`, `.agents/`, `tests/`, `docs/`, hidden dotfiles, build configs. These are out of scope for Plugin Check evaluation.
- **Registered callback model**: The replacement for the `php_code` ability type. Trusted plugin or theme code registers callable functions via `apply_filters( 'acrossai_abilities_registered_callbacks', array() )`. Ability rows store a `callback` key; the processor resolves and invokes the registered callable at runtime, preventing execution of arbitrary user-stored PHP.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Plugin Check CI produces zero findings for hidden files, test files, or development tooling configs after this feature is merged.
- **SC-002**: Plugin Check CI produces **zero findings total**. The `eval()` function has been removed from production code (not suppressed); narrow `PluginCheck.Security.DirectDB.UnescapedDBParameter` inline suppressions on internally-built SQL fragments are the only acknowledged suppressions. No findings should remain visible in CI logs.
- **SC-003**: PHPCS reports zero **new** errors on all PHP files modified by this feature. The repo-wide `composer run phpcs` baseline pre-exists this feature and is not required to be clean; only the files changed in this feature must not introduce new errors.
- **SC-004**: PHPStan level 8 passes with zero errors after all PHP changes.
- **SC-005**: `AGENTS.md`, `.specify/memory/CONSTITUTION.md` §II, and `docs/memory/DECISIONS.md` each contain all five Plugin Check governance rules established in this feature: production-surface scanning, `%i` SQL identifiers, forbidden-function removal/replacement, local-exact suppressions, and the PHPCS baseline gate constraint.

## Assumptions

- WordPress 6.9+ is required (already in plugin headers), so `$wpdb->prepare()` with `%i` is supported (introduced in WP 6.2).
- The plugin targets WordPress.org distribution, so removing `load_plugin_textdomain()` is correct; WordPress.org handles translation loading automatically for registered plugin text domains.
- `AcrossAI_I18n.php` is referenced only by `Main.php`. This will be verified before deletion.
- The `--exclude-directories` and `--exclude-files` flags are supported by the version of Plugin Check installed via WP-CLI in the workflow (`wp plugin install plugin-check --activate`).
- The `AcrossAI_Logger_Query` SQL fragments (`$where_clauses`, `$orderby`, `$order`) are internally constructed from fixed strings and validated allowlists. Plugin Check cannot infer this statically, so narrow local suppressions are acceptable for those specific lines only.
- No changes to database schemas, REST endpoint shapes, or log retention behaviour are in scope.
- The repo-wide `composer run phpcs` baseline is a pre-existing failure (exit code 2) affecting `tests/`, stub classes, index files, singleton classes, and other files unrelated to Feature 021. This feature does not fix the baseline; it only ensures changed production PHP files introduce no new PHPCS errors.
- The `apply_filters( 'acrossai_abilities_registered_callbacks', array() )` hook is consumed only by trusted, version-controlled plugin or theme code. Site operators are responsible for ensuring registered callbacks are safe; this is the same trust model as other WordPress plugin hooks.

## Clarifications

### Session 2026-05-31

- Q: Should SC-002 allow one residual finding for eval(), or must Plugin Check CI show zero findings total after all inline suppressions are applied? → A: Zero findings total — all inline phpcs:ignore suppressions (eval(), Logger_Query WHERE fragments) prevent those findings from appearing in Plugin Check output; no findings should remain visible in CI logs.
- Q: Should this feature update docs/memory/INDEX.md with a routing row for DEC-PLUGIN-CHECK-PRODUCTION-SURFACE? → A: Yes — add a row to INDEX.md for DEC-PLUGIN-CHECK-PRODUCTION-SURFACE so the decision is discoverable by future agents reading the routing map (FR-021).
- Context (from updated planning doc): The repo-wide `composer run phpcs` baseline already fails (exit code 2) due to pre-existing issues in `tests/`, index files, singleton classes, and stub files unrelated to Feature 021. SC-003 and FR-018/019/020 updated to reflect that only modified-file PHPCS cleanliness is in scope for this feature, and that the PHPCS gate MUST NOT be made a required PR check while the baseline is broken.
- Context (second planning doc update, 2026-05-31): CHANGE-4 direction changed from "suppress eval() with inline phpcs:ignore" to "remove eval() entirely and replace php_code with a registered-callback model". FR-010 rewritten; FR-010a-FR-010e added (Sanitizer, Validator, Query DB layer, existing rows, admin UI). Forbidden-function removal rule added to FR-018(c), FR-019 (five rules), FR-020 (supersedes DEC-EVAL-PHP-CODE), SC-002, SC-005, and acceptance scenarios.
- Q: Should FR-010e (Admin UI removal of php_code textarea) be in scope for Feature 021 or deferred to a follow-up feature? → A: Deferred to a separate follow-up feature. FR-010d backend rejection is sufficient for security; JS/SCSS files are removed from Feature 021 scope.
- Q: Should Feature 021 also add a new `registered_callback` DB enum type in `AcrossAI_Abilities_Query.php`, or only remove `php_code`? → A (revised during implementation): Add `registered_callback` now, as it is required to support the processor's registered-callback model. `php_code` is removed from Sanitizer, Validator, and DB enum; `registered_callback` replaces it in all three. The UI that exposes this type remains deferred (FR-010e). FR-010c and FR-010a updated accordingly.
- Q: Should `AcrossAI_Abilities_Sanitizer.php` rename `php_code` to a new type or simply remove it from the allowed types list? → A: Remove `php_code` only — no rename. Sanitizer mirrors the DB-remove-only decision; FR-010a updated accordingly.
