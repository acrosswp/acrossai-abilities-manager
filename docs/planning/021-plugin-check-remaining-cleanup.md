# Planning: Plugin Check Remaining Cleanup (Feature 021)

Resolve the remaining WordPress Plugin Check findings after Feature 020.
Hidden files and test files are explicitly out of scope for code fixes. The CI
workflow must check a production plugin surface, not the full development repo.

This plan is based on the Plugin Check JSON report generated on 2026-05-30
18:59:53.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "021-plugin-check-remaining-cleanup"

# 2. Specify
/speckit.specify "Fix remaining WordPress Plugin Check findings after Feature 020.
Ignore hidden files and tests by changing the Plugin Check workflow to scan only the production plugin surface.
Note: the workflow no longer uses WordPress/plugin-check-action@v1. It runs wp-env run cli wp plugin check directly.
Exclusions must be passed as --exclude-directories and --exclude-files CLI flags to wp plugin check, not as action inputs.

Forbidden-function inventory (verified against production code before this feature):
- eval() — ONLY actual forbidden-function finding. Located at AcrossAI_Abilities_Processor.php line 253 inside the php_code callback builder.
- extract() — NOT present in production code. Mentioned as policy only; no code change needed for it.
- call_user_func() — present at AcrossAI_Abilities_Validator.php line 468, but this is the plugin calling its own internal validator callables (not user-supplied code). Not a Plugin Check finding. Can optionally be simplified to direct callable invocation ($validator($fields[$field])) but must not be confused with the forbidden eval() path.
- create_function, exec, shell_exec, system, passthru, popen, proc_open — appear only as strings inside the php_code blocklist array in AcrossAI_Abilities_Validator.php, not as actual function calls. They are detected patterns for user-submitted PHP code, not production plugin code.

Fix production-code findings only:
(1) .github/workflows/plugin-check.yml — add --exclude-directories and --exclude-files flags to the wp plugin check CLI call to exclude hidden files, Spec Kit dirs, docs, tests, and dev tooling from the scan surface,
(2) includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php — escape table identifiers with %i in delete_logs_before_date() and count_logs(),
(3) includes/Modules/Logger/AcrossAI_Logger_Query.php — use %i for the table identifier in both count and select queries; keep ORDER BY allowlist and WHERE clause construction unchanged; add narrow PluginCheck.Security.DirectDB.UnescapedDBParameter inline ignores only for internally-built SQL fragments,
(4) includes/Modules/Abilities/AcrossAI_Abilities_Processor.php — remove the eval() execution path (the only actual forbidden-function finding). Replace the php_code callback type with a registered-callback model: resolve a callback key from callback_config via apply_filters('acrossai_abilities_registered_callbacks', array()) and invoke the registered callable. Also update AcrossAI_Abilities_Sanitizer.php (remove php_code from allowed callback types), AcrossAI_Abilities_Validator.php (remove php_code syntax/blocklist validation; the call_user_func at line 468 calling $validator is not a finding and must not be removed), and AcrossAI_Abilities_Query.php (remove php_code from DB enum allowlists),
(5) includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php — wrap $_SERVER['REQUEST_URI'] with wp_unslash() and sanitize_text_field() before use,
(6) includes/AcrossAI_I18n.php and includes/Main.php — remove the manual load_plugin_textdomain() call and the AcrossAI_I18n class entirely (WordPress.org auto-loads translations for registered text domains); use a narrow inline suppression only if manual loading is provably required for non-dotorg distribution,
(7) uninstall.php — rename $delete_data to $acrossai_delete_data; add WordPress.DB.DirectDatabaseQuery.NoCaching to the DROP TABLE phpcs:ignore comments; use $wpdb->prepare() with %i for table identifiers,
(8) includes/Main.php — rename the private define() helper parameters from $name/$value to $constant_name/$constant_value and update the docblock to match,
(9) AGENTS.md, .specify/memory/CONSTITUTION.md, and docs/memory/DECISIONS.md — record the Plugin Check production-surface scanning pattern, SQL identifier %i escaping rule, forbidden-function removal policy (eval is the only actual finding; extract is absent; call_user_func in Validator is permitted internal use), and local-suppression-only rule so future Spec Kit runs generate compliant code automatically."
```

---

## Scope Rules

### Ignore from this feature

Do not fix or edit files solely because Plugin Check reported them under these groups:

- Hidden files and hidden directories (`.eslintrc`, `.gitmodules`, `.specify/**`, `.github/**`, `.claude/**`, `.gitkeep`, etc.).
- Test files and test fixtures (`tests/**`).
- Development-only project files when they are excluded from the Plugin Check input (`phpstan.neon.dist`, `phpcs.xml.dist`, `phpunit.xml.dist`, `package.json`, `webpack.config.js`, etc.).

These are development repository artifacts, not production plugin runtime code. The correct fix is
to keep Plugin Check pointed at the production plugin surface via workflow configuration.

### Must fix

Fix Plugin Check findings in production PHP files under `includes/` and `uninstall.php`, plus the
workflow configuration that determines what Plugin Check scans.

---

## Background - Current Findings To Keep

| File | Code | Type | Action |
|------|------|------|--------|
| `.eslintrc`, `.gitmodules`, `.prettierignore`, `.editorconfig`, `.nvmrc`, `.gitattributes`, `.eslintignore`, `.wp-env.json`, `.distignore`, `.gitignore`, `.specify/**`, `.github/**`, `.claude/**`, `.gitkeep` | `hidden_files`, `github_directory`, `ai_instruction_directory` | errors/warnings | Ignore by workflow scan exclusions; do not edit or delete for this feature |
| `tests/**` | naming/input/direct DB warnings | warnings/errors | Ignore by workflow scan exclusions; do not edit tests for this feature |
| `phpstan.neon.dist`, `phpcs.xml.dist`, `phpunit.xml.dist`, extension shell scripts | `application_detected` | errors | Ignore by workflow scan exclusions; do not edit for this feature |

---

## Composer / PHPCS Baseline

Composer already installs WordPress coding-standard tooling:

```json
"require-dev": {
  "wp-coding-standards/wpcs": "*",
  "dealerdirect/phpcodesniffer-composer-installer": "^1.2",
  "phpcompatibility/php-compatibility": "^10.0@alpha",
  "phpstan/phpstan": "2.2.x-dev",
  "szepeviktor/phpstan-wordpress": "2.x-dev"
}
```

The existing Composer scripts are:

```json
"scripts": {
  "test": "./vendor/bin/phpunit",
  "phpstan": "vendor/bin/phpstan analyse --level=8",
  "phpcs": "vendor/bin/phpcs --standard=phpcs.xml.dist"
}
```

`phpcs.xml.dist` currently scans the whole repository with `<file>.</file>` and uses:

- `WordPress-Extra`
- `WordPress-Docs`
- `PHPCompatibility` with `testVersion` set to `7.4-`
- additional generic checks for unused parameters and TODO comments

### Current command result

Command run on 2026-05-31:

```bash
composer run phpcs
```

Result:

```text
Script vendor/bin/phpcs --standard=phpcs.xml.dist handling the phpcs event returned with error code 2
```

The failure is not limited to Feature 021. It is an existing repo-wide PHPCS baseline. Examples:

| File / group | Current issue class |
|--------------|---------------------|
| `tests/bootstrap.php` | many WordPress stub/test-helper docblock, PHP 7.4 compatibility, and prefix warnings/errors |
| `index.php`, `admin/index.php`, `admin/Partials/index.php`, `languages/index.php`, `public/index.php`, `public/Partials/index.php` | missing final newline and inline-comment punctuation |
| `includes/AcrossAI_I18n.php`, `includes/AcrossAI_Deactivator.php`, `public/Main.php` | missing file doc comments / inline-comment punctuation |
| many singleton classes | `PSR2.Classes.PropertyDeclaration.Underscore` warnings for the required `$_instance` convention |
| `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` | underscore-property warnings and commented-out-code warnings |

### Required handling for Feature 021

Do not claim `composer run phpcs` is clean until the baseline is addressed.

For this feature, the implementer must do both:

1. Run PHPCS on the changed production PHP files to ensure Feature 021 introduces no new PHPCS errors.
2. Decide how the repo-wide PHPCS gate should work before adding it as a required PR check:
   - **Preferred for production CI**: create a PHPCS workflow/job that scans the production plugin surface only, excluding hidden/dev/test/spec-kit paths consistently with Plugin Check.
   - **Alternative**: fix the full repo-wide PHPCS baseline first, then make `composer run phpcs` required.
   - **Do not** add `composer run phpcs` as a required PR check while it still fails on unrelated baseline files.

If this feature updates `phpcs.xml.dist`, keep the scope explicit and document why test/dev paths are excluded or separately checked.

---

## Production Finding Inventory

| File | Line | Code | Message summary | Planned fix |
|------|------|------|-----------------|-------------|
| `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` | 253 | `Generic.PHP.ForbiddenFunctions.Found` | `eval()` is forbidden | Remove the eval execution path; replace `php_code` with a registered callback model |
| `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` | 222 | `MissingUnslash`, `InputNotSanitized` | `$_SERVER['REQUEST_URI']` not unslashed/sanitized | Use `wp_unslash()` and `sanitize_text_field()` before strpos detection |
| `includes/AcrossAI_I18n.php` | 19 | `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` | Manual `load_plugin_textdomain()` discouraged for WordPress.org plugins | Preferred: remove manual i18n hook/class path; fallback: narrow documented suppression only if manual loading is required |
| `uninstall.php` | 20 | `NonPrefixedVariableFound` | `$delete_data` not prefixed | Rename to `$acrossai_delete_data` |
| `uninstall.php` | 24, 28 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | DROP TABLE direct DB call lacks NoCaching ignore | Add `NoCaching` to the existing schema-change ignores; optionally prepare identifiers with `%i` |
| `includes/Main.php` | 170 | `VariableConstantNameFound` | `$name` in `define()` treated as constant/global naming issue | Rename parameters to `$constant_name` and `$constant_value` |
| `includes/Modules/Logger/AcrossAI_Logger_Query.php` | 203 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | `$table` / `$where_clause` used in `get_var()` | Use `%i` for table identifier; document/suppress only internally built SQL fragments if Plugin Check still cannot infer safety |
| `includes/Modules/Logger/AcrossAI_Logger_Query.php` | 217 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | `$select_sql` used in `get_results()` | Refactor query construction so identifiers are whitelisted and values are prepared; add narrow `PluginCheck.Security.DirectDB.UnescapedDBParameter` ignore only for internally built SQL fragments |
| `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php` | 136 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | `$table` used in `query()` | Use `$wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, $date )` |
| `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php` | 156 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | `$table` used in `get_var()` | Use `$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )` |

---

## CHANGE-1 - Workflow Scan Surface

**File**: `.github/workflows/plugin-check.yml`

Keep `ignore-warnings: false` and `include-experimental: true`.

Add Plugin Check exclusions so the action does not scan hidden files, test fixtures, or development
tooling. The action supports `exclude-files` and `exclude-directories`.

**Context**: As of Feature 020's CI fix (commit d58f487), the workflow no longer uses
`WordPress/plugin-check-action@v1`. It was replaced with inlined `wp-env` steps to work around
issue #579 (Node 24.16 / ubuntu-latest silently exits on URL-plugin downloads). The `wp plugin check`
command is called directly via `wp-env run cli`.

Exclusions must be added as CLI flags to the `wp plugin check` call, not as action inputs.

Recommended change to the "Run Plugin Check" step in `.github/workflows/plugin-check.yml`:

```yaml
      - name: Run Plugin Check
        run: |
          wp-env run cli wp plugin activate acrossai-abilities-manager
          wp-env run cli wp plugin check acrossai-abilities-manager \
            --include-experimental \
            --exclude-directories=.agents,.claude,.github,.specify,docs,node_modules,scripts,specs,src,tests \
            --exclude-files=.distignore,.editorconfig,.eslintignore,.eslintrc,.gitattributes,.gitignore,.gitmodules,.nvmrc,.prettierignore,.wp-env.json,AGENTS.md,LICENSE.md,README.md,composer.json,composer.lock,eslint.config.js,package.json,package-lock.json,phpcs.xml.dist,phpstan.neon.dist,phpunit.xml.dist,webpack.config.js
```

**Why this is acceptable**:
- The reported hidden/dev/test files are not production plugin runtime files.
- `.distignore` already declares many of these as distribution exclusions.
- Plugin Check should evaluate the installable plugin surface, not Spec Kit, tests, or local tooling.

**Alternative**:
Build a production staging directory or plugin zip first, then point `build-dir` at that directory.
Use this alternative only if the explicit exclusions become too brittle.

---

## CHANGE-2 - `AcrossAI_Ability_Logs_Query.php` Table Identifier Escaping

**File**: `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php`

Replace interpolated table identifiers with `%i`.

### `delete_logs_before_date()`

Current shape:

```php
$table = $this->get_table_name();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$result = $wpdb->query(
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < %s", $date )
);
```

Target shape:

```php
$table = $this->get_table_name();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$result = $wpdb->query(
	$wpdb->prepare(
		'DELETE FROM %i WHERE created_at < %s',
		$table,
		$date
	)
);
```

### `count_logs()`

Current shape:

```php
$table = $this->get_table_name();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
```

Target shape:

```php
$table = $this->get_table_name();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
return (int) $wpdb->get_var(
	$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
);
```

Rules:
- Do not wrap `%i` in backticks.
- Keep `$date` as `%s`.
- Do not change method signatures or return values.

---

## CHANGE-3 - `AcrossAI_Logger_Query.php` Direct SQL Cleanup

**File**: `includes/Modules/Logger/AcrossAI_Logger_Query.php`

This query builder constructs SQL from internally controlled fragments:

- `$where_clauses` are fixed fragments with placeholders only.
- `$orderby` is restricted to a hardcoded allowlist.
- `$order` is restricted to `ASC` or `DESC`.
- `$per_page` and `$offset` are integers.

Plugin Check cannot infer all of that, so the implementation must make the safe parts explicit.

Required changes:

1. Use the per-site prefix, not `base_prefix`, so it matches the Logger table class:

```php
$table = $wpdb->prefix . 'acrossai_ability_logs';
```

2. Prepare the table identifier with `%i` in both queries.

3. Keep `ORDER BY` column and direction allowlists exactly as hardcoded validation.

4. If Plugin Check still flags `$where_clause` or `$select_sql`, add the narrow ignore code
   `PluginCheck.Security.DirectDB.UnescapedDBParameter` only on the final `$wpdb->prepare()`
   line and include a short inline rationale. Do not add broad ignore-codes to the workflow for
   this warning.

Target count-query shape:

```php
$count_sql    = "SELECT COUNT(*) FROM %i {$where_clause}";
$count_values = array_merge( array( $table ), $where_values );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$total = (int) $wpdb->get_var(
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments are built from fixed clauses and placeholders only.
	$wpdb->prepare( $count_sql, $count_values )
);
```

Target select-query shape:

```php
$select_sql   = "SELECT * FROM %i {$where_clause} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
$final_values = array_merge( array( $table ), $where_values, array( $per_page, $offset ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$results = $wpdb->get_results(
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL fragments are allowlisted and all values are prepared.
	$wpdb->prepare( $select_sql, $final_values )
);
```

Do not alter the REST response shape: return keys stay `logs`, `total`, and `pages`.

---

## CHANGE-4 - Remove Forbidden Function Execution

**File**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`

WordPress Plugin Check reports `eval()` as `Generic.PHP.ForbiddenFunctions.Found`.
This feature changes the direction from "suppress the risk" to "remove forbidden runtime functions
from production code."

### Required outcome

- No `eval()` call remains in production plugin code.
- No `extract()` call is introduced or retained in production plugin code.
- No broad workflow-level forbidden-function suppression is added.
- Existing ability execution modes (`noop`, `filter_hook`, `wp_remote_post`) continue to work.
- The unsafe `php_code` ability type is replaced by a safe registered-callback model.

### Replacement model

Replace database-stored PHP execution with a callback key that resolves to a callable registered by
trusted plugin/theme code:

```php
$callbacks = apply_filters( 'acrossai_abilities_registered_callbacks', array() );
$callback  = isset( $row->callback_config['callback'] ) ? sanitize_key( (string) $row->callback_config['callback'] ) : '';

if ( ! isset( $callbacks[ $callback ] ) || ! is_callable( $callbacks[ $callback ] ) ) {
	return new \WP_Error( 'invalid_callback', 'Invalid ability callback.' );
}

return call_user_func( $callbacks[ $callback ], $input );
```

The exact method names are implementation details, but the execution model must be allowlisted:
stored config contains a callback key, while executable code lives in version-controlled trusted
plugin/theme files.

### Production files that currently mention `php_code`

The implementation must update all production code paths that currently advertise, validate, store,
or execute `php_code`:

| Area | File(s) | Required direction |
|------|---------|--------------------|
| Runtime execution | `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` | Remove `make_php_code_callback()` and replace the `php_code` switch branch with the registered-callback mode or a fail-closed unsupported mode |
| Sanitization | `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` | Remove `php_code` from allowed callback types, or rename it to the new callback type and sanitize a `callback` key instead of raw `code` |
| Validation | `includes/Utilities/AcrossAI_Abilities_Validator.php` | Remove PHP code syntax/blocklist validation path; validate callback key existence/shape instead |
| DB write guards | `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` | Remove `php_code` from enum allowlists; allow only safe callback types |
| Admin UI | `src/js/abilities/components/AbilityForm.jsx`, `src/js/abilities/components/CallbackConfigField.jsx`, `src/js/abilities/components/AbilitiesList.jsx`, `src/scss/abilities/admin.scss` | Remove raw PHP textarea UI; replace with safe registered-callback key UI or mark legacy `php_code` as unsupported |

If the UI portion is intentionally deferred, the backend must still reject/disable `php_code` so
forbidden execution cannot occur.

### Existing `php_code` rows

Do not execute existing `php_code` rows. Choose one fail-closed migration behavior:

- Preferred: treat `php_code` as unsupported and return `WP_Error( 'unsupported_callback_type', ... )`.
- Alternative: migrate rows to `draft`/inactive state in a dedicated migration task and show an admin-facing notice.

Do not silently map old arbitrary PHP snippets to callbacks.

### Forbidden functions policy

Production code must respect WordPress forbidden-function findings. If Plugin Check reports any
forbidden function such as `eval()`, `extract()`, `exec()`, `shell_exec()`, `system()`,
`passthru()`, `popen()`, or `proc_open()`, the default fix is removal/replacement, not suppression.

Only consider a local, exact suppression if all of the following are true:

1. The function is not executing arbitrary code or shell commands.
2. There is no safe WordPress API replacement.
3. The reason is documented in `docs/memory/DECISIONS.md`.
4. The suppression is inline on the exact line, never workflow-wide.

`eval()` does not qualify for suppression in this feature.

Update memory to supersede the existing `DEC-EVAL-PHP-CODE` risk-acceptance decision.

---

## CHANGE-5 - `REQUEST_URI` Sanitization

**File**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`

Current code reads raw `$_SERVER['REQUEST_URI']` for boolean route detection:

```php
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URI used for boolean strpos detection only, never echoed or used in SQL.
$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
```

Replace with unslash + sanitize:

```php
$uri = isset( $_SERVER['REQUEST_URI'] )
	? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
	: '';
```

No `phpcs:ignore` should be needed after this change.

Do not change the manager namespace filter, route prefix detection, or cache behaviour.

---

## CHANGE-6 - Manual Textdomain Loading

**Files**: `includes/AcrossAI_I18n.php`, `includes/Main.php`

Plugin Check flags `load_plugin_textdomain()` as discouraged for WordPress.org-hosted plugins.

Preferred fix:

1. Remove the `set_locale()` call from `Main::__construct()`.
2. Remove the `set_locale()` method from `includes/Main.php`.
3. Delete `includes/AcrossAI_I18n.php` if no other code references it.

Rationale:
- WordPress.org loads translations automatically for plugins using the plugin slug text domain.
- Text domain is already `acrossai-abilities-manager`.
- Removing the manual loader removes the Plugin Check warning without runtime risk for WordPress.org distribution.

Fallback only if manual loading is intentionally required for non-dotorg distribution:
- Keep the class and hook.
- Add a narrow inline suppression for `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound`.
- Document the reason in `docs/memory/DECISIONS.md`.

Do not choose the fallback without an explicit written reason.

---

## CHANGE-7 - `uninstall.php` Cleanup

**File**: `uninstall.php`

Required changes:

1. Rename `$delete_data` to `$acrossai_delete_data`.

2. Add `WordPress.DB.DirectDatabaseQuery.NoCaching` to both DROP TABLE ignore comments.

3. Prefer `%i` for table identifiers:

```php
$acrossai_abilities_table = $wpdb->prefix . 'acrossai_abilities';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query(
	$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_abilities_table )
);
```

Repeat for `wpb_access_control`.

Do not change the uninstall data-retention setting behaviour:
- data tables are dropped only when `acrossai_abilities_uninstall_delete_data` is truthy;
- settings options are always removed.

---

## CHANGE-8 - `includes/Main.php` Constant Helper Naming

**File**: `includes/Main.php`

Plugin Check reports `$name` in the private `define()` helper as a global constant naming issue.

Change:

```php
private function define( $name, $value ) {
	if ( ! defined( $name ) ) {
		define( $name, $value );
	}
}
```

to:

```php
private function define( $constant_name, $constant_value ) {
	if ( ! defined( $constant_name ) ) {
		define( $constant_name, $constant_value );
	}
}
```

Update the docblock parameter names to match.

Do not rename the method itself; it is private and already locally scoped.

---

## CHANGE-9 - Spec Kit Memory And Governance Pattern

**Files**:
- `AGENTS.md`
- `.specify/memory/CONSTITUTION.md`
- `docs/memory/DECISIONS.md`
- optionally `phpcs.xml.dist` if the implementation chooses to scope PHPCS to production files

This feature must teach future Spec Kit runs the patterns discovered here. Do not leave the learning
only in this planning document.

### `AGENTS.md`

Add a short rule under the existing Plugin Check / quality guidance:

```markdown
- Plugin Check must scan the production plugin surface, not the full development repository.
  Exclude hidden files, Spec Kit files, docs, tests, source-only assets, and local tooling from
  Plugin Check CI via `exclude-files` / `exclude-directories` or a production staging directory.
- For dynamic SQL table identifiers, use `$wpdb->prepare()` with `%i`; do not interpolate table
  names into SQL strings.
- WordPress forbidden-function findings must be fixed by replacement/removal, not suppressed.
  `eval()`, `extract()`, and shell/process functions are not allowed in production plugin code.
- Plugin Check suppressions must be local and exact, and only for cases with no safe WordPress API
  replacement. Never use workflow-level ignore codes for one-line production-code findings.
- PHPCS is already configured through Composer (`composer run phpcs`) with WPCS, WordPress-Docs,
  and PHPCompatibility. Do not make repo-wide PHPCS a required PR gate until the existing baseline
  is fixed or the PHPCS config is scoped to the production plugin surface.
```

Keep this concise; `AGENTS.md` is a top-level instruction file, not a long implementation guide.

### `.specify/memory/CONSTITUTION.md`

Bump constitution version from `1.4.3` to `1.4.4` as a PATCH update.

Add a clarification under `### II. WordPress Standards Compliance`:

```markdown
Plugin Check compliance is evaluated against the installable production plugin surface.
Development-only repository artifacts such as `.github/`, `.specify/`, `.agents/`, `docs/`,
`specs/`, `src/`, `tests/`, hidden dotfiles, and local tooling configs MUST be excluded from
Plugin Check CI by workflow configuration or by checking a production staging directory.
Production PHP code MUST still be Plugin Check clean.

Dynamic SQL identifiers MUST be escaped with `$wpdb->prepare()` and `%i`; values MUST use the
appropriate value placeholders such as `%s` and `%d`. Direct interpolation of table names into SQL
is prohibited unless there is a documented narrow suppression for an internally-built, allowlisted
fragment.

WordPress forbidden-function findings MUST be fixed by replacing or removing the forbidden function.
Production plugin code MUST NOT use `eval()`, `extract()`, shell/process execution functions, or
other functions reported by Plugin Check as forbidden. Suppressions for forbidden functions are not
allowed unless the function is not executing arbitrary code/commands, there is no safe WordPress API
replacement, and the exception is documented in `docs/memory/DECISIONS.md`.

Plugin Check suppressions MUST be local and exact. Do not add broad workflow-level ignore codes for
single-line production-code findings.

PHPCS is part of the quality gate via Composer and WPCS. Required CI checks MUST either run PHPCS
against a clean production plugin surface or first eliminate the repo-wide PHPCS baseline. A failing
baseline MUST NOT be added as a required PR check without a documented remediation plan.
```

Update the HTML sync impact report at the top:

```markdown
Version change: 1.4.3 → 1.4.4
Modified sections: §II WordPress Standards Compliance — clarified Plugin Check production-surface scope,
SQL identifier escaping with %i, forbidden-function removal, and local suppression policy
Rationale: Feature 021 converts remaining Plugin Check findings into durable Spec Kit guidance so future
plans generate compliant SQL, workflow scan surfaces, forbidden-function replacements, and suppressions.
Templates reviewed:
  - .specify/templates/plan-template.md ✅ reviewed — no outdated references
  - .specify/templates/spec-template.md ✅ reviewed — no outdated references
  - .specify/templates/tasks-template.md ✅ reviewed — no outdated references
  - .specify/templates/checklist-template.md ✅ reviewed — no outdated references
Deferred TODOs: None
```

Update the version footer to:

```markdown
**Version**: 1.4.4 | **Ratified**: 2026-05-11 | **Last Amended**: 2026-05-31
```

### `docs/memory/DECISIONS.md`

Append a concise decision entry:

```markdown
### 2026-05-31 — Plugin Check CI scans production surface only (DEC-PLUGIN-CHECK-PRODUCTION-SURFACE)

Plugin Check must evaluate installable plugin runtime files, not the full development repository.
CI must exclude hidden files, Spec Kit artifacts, tests, docs, source-only assets, and local tooling
with `exclude-files` / `exclude-directories`, or run Plugin Check against a production staging
directory. Production PHP files remain fully in scope.

SQL table identifiers in production code use `$wpdb->prepare()` with `%i`; values use `%s`, `%d`,
or other value placeholders. If a query contains internally-built SQL fragments such as allowlisted
ORDER BY or fixed WHERE clauses, any suppression must be local, exact, and documented inline.

Forbidden functions reported by Plugin Check are removed or replaced by safe WordPress/plugin
patterns. `eval()` is replaced with a registered callback model, `extract()` is replaced with
explicit variable assignment, and shell/process functions are not used in production code. This
supersedes `DEC-EVAL-PHP-CODE`; arbitrary database-stored PHP is no longer accepted.

PHPCS is installed through Composer and uses WPCS, WordPress-Docs, and PHPCompatibility. The current
repo-wide `composer run phpcs` baseline is not clean, so required PR checks should run PHPCS against
the production plugin surface or wait until the baseline is fixed.
```

This memory entry is mandatory because it prevents the same Plugin Check mistakes from reappearing
in future Spec Kit-generated plans.

---

## What Must NOT Change

- Do not edit `tests/**` to satisfy Plugin Check.
- Do not delete hidden development files or Spec Kit files to satisfy Plugin Check.
- Do not keep or newly suppress `eval()` in `AcrossAI_Abilities_Processor.php`.
- Do not add broad workflow ignores for `Generic.PHP.ForbiddenFunctions.Found` or other forbidden-function codes.
- Do not weaken the Plugin Check gate with `ignore-warnings: true` or `ignore-errors: true`.
- Do not change REST endpoint paths or response shapes.
- Do not change database schemas.
- Do not change the log retention behaviour.
- Do not modify files inside `.agents/tools/`.

---

## Expected Files Changed

```text
.github/workflows/plugin-check.yml
includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php
includes/Modules/Logger/AcrossAI_Logger_Query.php
includes/Modules/Abilities/AcrossAI_Abilities_Processor.php
includes/Utilities/AcrossAI_Abilities_Sanitizer.php
includes/Utilities/AcrossAI_Abilities_Validator.php
includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php
includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php
includes/AcrossAI_I18n.php              (delete or narrow suppress; preferred delete)
includes/Main.php
uninstall.php
src/js/abilities/components/AbilityForm.jsx       (if UI is updated in this feature)
src/js/abilities/components/CallbackConfigField.jsx
src/js/abilities/components/AbilitiesList.jsx
src/scss/abilities/admin.scss
AGENTS.md
.specify/memory/CONSTITUTION.md
docs/memory/DECISIONS.md
phpcs.xml.dist                            (only if PHPCS scope is intentionally changed)
```

If `includes/AcrossAI_I18n.php` is deleted, confirm the autoloader and references have no stale usage.

---

## Validation Checklist

### Workflow surface

- [ ] `.github/workflows/plugin-check.yml` uses inlined `wp-env` steps with `wp-env run cli wp plugin check` (NOT `WordPress/plugin-check-action@v1` — replaced in Feature 020 due to issue #579).
- [ ] `ignore-warnings: false` remains.
- [ ] `include-experimental: true` remains.
- [ ] Hidden/dev/test directories are excluded from Plugin Check input.
- [ ] Hidden/dev/test findings from the provided JSON are no longer present in CI output.

### Production PHP fixes

- [ ] `grep -rn "eval\\|extract(" includes admin public acrossai-abilities-manager.php uninstall.php` returns no production-code forbidden function usage.
- [ ] `php_code` abilities fail closed or are replaced by a registered callback key model.
- [ ] No workflow-level forbidden-function ignore is present.
- [ ] `grep -n "REQUEST_URI" includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` shows `wp_unslash()` and `sanitize_text_field()`.
- [ ] No `load_plugin_textdomain()` warning remains, either because the manual loader was removed or because a documented narrow suppression exists.
- [ ] `uninstall.php` has no `$delete_data` variable.
- [ ] DROP TABLE comments include `NoCaching`.
- [ ] Logger table identifiers use `%i`.

### Spec Kit governance

- [ ] `AGENTS.md` documents production-surface Plugin Check scanning.
- [ ] `AGENTS.md` documents `%i` for SQL identifiers.
- [ ] `AGENTS.md` documents forbidden-function removal/replacement.
- [ ] `AGENTS.md` documents local exact Plugin Check suppressions for non-forbidden exceptional cases.
- [ ] `AGENTS.md` documents the existing Composer PHPCS/WPCS setup and the repo-wide baseline caveat.
- [ ] `.specify/memory/CONSTITUTION.md` version is bumped to `1.4.4`.
- [ ] Constitution §II includes the Plugin Check production-surface rule.
- [ ] Constitution §II includes the `%i` SQL identifier rule.
- [ ] Constitution §II includes the forbidden-function removal rule.
- [ ] Constitution §II includes the local-suppression-only rule.
- [ ] Constitution §II includes the PHPCS production-surface/baseline rule.
- [ ] `docs/memory/DECISIONS.md` contains `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE`.

### Quality gates

- [ ] `composer run phpstan` passes.
- [ ] Run PHPCS on changed production PHP files; no new errors are introduced.
- [ ] Record that repo-wide `composer run phpcs` currently fails unless the implementation fixes or scopes the baseline.
- [ ] Run the Plugin Check workflow or local equivalent; production-code findings listed in this plan are gone.
- [ ] Confirm remaining Plugin Check output contains no production runtime errors/warnings except explicitly documented intentional suppressions.

---

## Spec-kit Commands

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpstan
composer run phpcs

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```
