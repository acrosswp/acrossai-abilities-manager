# Implementation Plan: Plugin Check Remaining Cleanup (Feature 021)

**Branch**: `021-plugin-check-cleanup` | **Date**: 2026-05-31 | **Spec**: [spec.md](spec.md)
**Input**: `specs/021-plugin-check-remaining-cleanup/spec.md`
**Memory**: `specs/021-plugin-check-remaining-cleanup/memory-synthesis.md`

## Summary

Feature 021 resolves all remaining production WordPress Plugin Check findings after the CI gate was established in Feature 020. The 9 production changes address: workflow scan-surface exclusions, SQL identifier escaping (`%i`), removal of `eval()` via a registered-callback model, `REQUEST_URI` sanitization, manual textdomain removal, `uninstall.php` cleanup, and `Main.php` parameter renaming. The feature also updates AGENTS.md, CONSTITUTION.md (v1.4.4), DECISIONS.md, and INDEX.md to encode the discovered patterns as durable governance rules.

## Technical Context

**Language/Version**: PHP 7.4+, JavaScript (ES2020+), WordPress 6.9+
**Primary Dependencies**: `$wpdb`, WPBoilerplate Loader, `apply_filters`, `call_user_func`, `wp-env run cli`
**Storage**: MySQL (wpdb); no schema changes in this feature
**Testing**: PHPStan level 8, PHPCS (changed-file scope), Plugin Check (production surface)
**Target Platform**: WordPress plugin (WordPress.org distribution target)
**Project Type**: WordPress plugin — Abilities Manager
**Constraints**: No new PHPCS errors on changed files; PHPStan 0 errors; Plugin Check 0 production errors/warnings; no broad workflow-level suppressions; admin UI changes deferred (FR-010e)

## Constitution Check

### §II — WordPress Standards Compliance

| Rule | Status |
|------|--------|
| WPCS strict profile on all PHP | ✅ Enforced — changed-file PHPCS required |
| PHPStan level 8, zero errors | ✅ Required gate |
| Plugin Check zero errors/warnings (production surface) | ✅ Required gate |
| No `eval()` in production code | ✅ FR-010 removes it |
| No broad workflow suppression for forbidden functions | ✅ CHANGE-1 uses `--exclude-directories` not `ignore-codes` |

**Soft conflict resolved**: Constitution v1.4.3 §II references the intentional `eval()` suppression. Feature 021 owns updating §II to v1.4.4 which supersedes that reference (FR-019). No blocking conflict.

### §I — Modular Architecture

| Rule | Status |
|------|--------|
| `Main.php` is sole hook-wiring location | ✅ CHANGE-6 `set_locale()` removed in Main.php |
| Utilities (`Sanitizer`, `Validator`) remain 100% static | ✅ FR-010a/010b: static method edits only |
| No cross-module direct instantiation | ✅ No cross-module changes |

### §IV — Security First

| Rule | Status |
|------|--------|
| Input sanitized at system entry points | ✅ CHANGE-5: `wp_unslash()` + `sanitize_text_field()` |
| All DB queries via `$wpdb->prepare()` | ✅ CHANGE-2/3/7: `%i` + value placeholders |
| Callback key sanitized before lookup | ✅ `sanitize_key()` before `$callbacks[$callback]` |

## Project Structure

### Documentation (this feature)

```text
specs/021-plugin-check-remaining-cleanup/
├── spec.md                  ← Clarified spec (FR-001–FR-021 + FR-010a–010e)
├── plan.md                  ← This file
├── memory-synthesis.md      ← Memory retrieval output
└── checklists/
    └── requirements.md      ← All items verified ✅
```

### Source Code (repository root)

```text
.github/workflows/
└── plugin-check.yml          ← CHANGE-1: add --exclude-directories / --exclude-files

includes/
├── Main.php                  ← CHANGE-6 (set_locale removal) + CHANGE-8 (define params)
├── AcrossAI_I18n.php         ← CHANGE-6: delete (preferred) after reference check
├── Modules/
│   ├── Abilities/
│   │   ├── AcrossAI_Abilities_Processor.php   ← CHANGE-4: remove eval(), add registered-callback
│   │   ├── AcrossAI_Ability_Override_Processor.php ← CHANGE-5: REQUEST_URI sanitization
│   │   └── Database/
│   │       └── AcrossAI_Abilities_Query.php    ← CHANGE-4d: remove php_code from enum
│   └── Logger/
│       ├── AcrossAI_Logger_Query.php           ← CHANGE-3: prefix→%i, narrow suppressions
│       └── Database/
│           └── AcrossAI_Ability_Logs_Query.php ← CHANGE-2: %i for DELETE + COUNT
└── Utilities/
    ├── AcrossAI_Abilities_Sanitizer.php        ← CHANGE-4b: remove php_code type (static)
    └── AcrossAI_Abilities_Validator.php        ← CHANGE-4c: remove php_code validation (static)

uninstall.php                 ← CHANGE-7: rename $delete_data, NoCaching, %i

AGENTS.md                     ← CHANGE-9a: 5 Plugin Check governance rules
.specify/memory/CONSTITUTION.md ← CHANGE-9b: bump v1.4.4, replace §II eval reference
docs/memory/DECISIONS.md      ← CHANGE-9c: append DEC-PLUGIN-CHECK-PRODUCTION-SURFACE; supersede DEC-EVAL-PHP-CODE
docs/memory/INDEX.md          ← CHANGE-9d: add routing row for DEC-PLUGIN-CHECK-PRODUCTION-SURFACE
```

---

## Implementation Phases

### Phase 1 — Workflow Scan Surface (CHANGE-1)

**File**: `.github/workflows/plugin-check.yml`

Context: The workflow uses inlined `wp-env` steps since Feature 020 replaced `WordPress/plugin-check-action@v1` due to Node 24.16 silent-exit bug (issue #579, commit d58f487). CLI flags must be appended to the `wp plugin check` call.

**Target change** to the "Run Plugin Check" step:

```yaml
      - name: Run Plugin Check
        run: |
          wp-env run cli wp plugin activate acrossai-abilities-manager
          wp-env run cli wp plugin check acrossai-abilities-manager \
            --include-experimental \
            --exclude-directories=.agents,.claude,.github,.specify,docs,node_modules,scripts,specs,src,tests \
            --exclude-files=phpcs.xml.dist,phpstan.neon.dist,phpunit.xml.dist,composer.json,composer.lock,package.json,package-lock.json
```

Rules:
- Remove the existing `--ignore-codes=WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` line — that suppression covered the intentional eval() which CHANGE-4 removes
- Do not add a new `--ignore-codes` for any forbidden function
- Keep `--include-experimental`
- Do not use `--ignore-warnings` or `--ignore-errors`
- Use `wp-env run cli` (globally installed) — not `npx wp-env run cli`
- Confirm exact current CLI call shape by reading the file before editing

---

### Phase 2 — SQL Identifier Escaping

#### CHANGE-2: `AcrossAI_Ability_Logs_Query.php`

**File**: `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php`

`delete_logs_before_date()` — target shape:

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

`count_logs()` — target shape:

```php
$table = $this->get_table_name();

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
return (int) $wpdb->get_var(
	$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
);
```

Rules:
- Remove `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` from both phpcs:ignore comments
- Do not wrap `%i` in backticks
- Do not change method signatures or return values

---

#### CHANGE-3: `AcrossAI_Logger_Query.php`

**File**: `includes/Modules/Logger/AcrossAI_Logger_Query.php`

1. Change `base_prefix` → `prefix`:

```php
$table = $wpdb->prefix . 'acrossai_ability_logs';
```

2. Count-query target shape:

```php
$count_sql    = "SELECT COUNT(*) FROM %i {$where_clause}";
$count_values = array_merge( array( $table ), $where_values );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$total = (int) $wpdb->get_var(
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments are built from fixed clauses and placeholders only.
	$wpdb->prepare( $count_sql, $count_values )
);
```

3. Select-query target shape:

```php
$select_sql   = "SELECT * FROM %i {$where_clause} ORDER BY `{$orderby}` {$order} LIMIT %d OFFSET %d";
$final_values = array_merge( array( $table ), $where_values, array( $per_page, $offset ) );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$results = $wpdb->get_results(
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL fragments are allowlisted and all values are prepared.
	$wpdb->prepare( $select_sql, $final_values )
);
```

Rules:
- `$orderby` and `$order` remain validated against their respective allowlists
- Do not change REST response shape (`logs`, `total`, `pages`)
- Narrow inline suppression only — not workflow-level

---

### Phase 3 — eval() Removal and Registered-Callback Model (CHANGE-4)

#### CHANGE-4a: `AcrossAI_Abilities_Processor.php`

**File**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`

- Read current file to locate `make_php_code_callback()` and the `php_code` switch branch
- Remove `make_php_code_callback()` method entirely
- Replace `php_code` case with registered-callback logic (SEC-01: separate registered_callback case from default fallthrough to ensure php_code and unknown types always fail closed without entering the dispatch path):

```php
case 'registered_callback':
    /**
     * Registered ability callbacks.
     *
     * Only version-controlled plugin/theme code should register callables here.
     * $input is caller-controlled (untrusted) — registered callbacks must treat it as untrusted.
     *
     * @param callable[] $callbacks Associative array of callback_key => callable.
     */
    $callbacks = apply_filters( 'acrossai_abilities_registered_callbacks', array() );
    $callback  = isset( $row->callback_config['callback'] )
        ? sanitize_key( (string) $row->callback_config['callback'] )
        : '';

    if ( ! isset( $callbacks[ $callback ] ) || ! is_callable( $callbacks[ $callback ] ) ) {
        return new \WP_Error( 'unsupported_callback_type', 'Unsupported ability callback type.' );
    }

    return call_user_func( $callbacks[ $callback ], $input );

case 'php_code':
default:
    // php_code is no longer supported. Existing rows and any unknown future types fail closed.
    return new \WP_Error( 'unsupported_callback_type', 'Unsupported ability callback type.' );
```

> Note: `php_code` is explicit so that existing DB rows return a clear `WP_Error` without touching the
> registered-callback dispatch path. The `default:` branch catches any unrecognised future type and
> also fails closed. This separation prevents future callback types from silently falling into callback
> dispatch if a new type is added to the DB allowlist without a matching `case`.

- Remove any `// phpcs:ignore … runtime_configuration_eval` inline suppression on the old eval line

---

#### CHANGE-4b: `AcrossAI_Abilities_Sanitizer.php` (static utility)

**File**: `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`

- Remove `'php_code'` from the allowed callback types array
- Remove any `code` field sanitization branch that was specific to `php_code`
- Do NOT add singleton, `instance()`, or constructor — this is a static utility class (DEC-UTILITY-STATIC-ONLY)

---

#### CHANGE-4c: `AcrossAI_Abilities_Validator.php` (static utility)

**File**: `includes/Utilities/AcrossAI_Abilities_Validator.php`

- Remove the `php_code` validation path: blocked-function array + PHP syntax-check branch
- Keep `call_user_func` at line 468 if it is an internal `$validator` callable (not a Plugin Check finding — intentional internal usage)
- Do NOT add singleton or constructor — static utility only (DEC-UTILITY-STATIC-ONLY)

---

#### CHANGE-4d: `AcrossAI_Abilities_Query.php`

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`

- Remove `'php_code'` from the `callback_type` DB enum allowlist array
- Do NOT add a new `'registered_callback'` DB enum entry (deferred — clarification Q2 answer A)
- The method-level guard already enforces the boundary at the query layer (DEC-DB-WRITE-BOUNDARY-GUARD)

---

### Phase 4 — REQUEST_URI Sanitization (CHANGE-5)

**File**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`

Replace:

```php
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- URI used for boolean strpos detection only, never echoed or used in SQL.
$uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
```

With:

```php
$uri = isset( $_SERVER['REQUEST_URI'] )
	? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
	: '';
```

- Remove the `phpcs:ignore` comment entirely — no suppression needed after this change
- Do not change the route prefix detection logic or cache behaviour

---

### Phase 5 — Manual Textdomain Removal (CHANGE-6)

**Pre-condition check**: `grep -rn "AcrossAI_I18n\|set_locale" includes/ admin/ acrossai-abilities-manager.php`

If only `Main.php` references it (expected per clarification):

1. Remove `$this->set_locale()` call from `Main::__construct()` (or wherever it is called in Main)
2. Remove the `set_locale()` method body from `Main.php`
3. Delete `includes/AcrossAI_I18n.php`

Architecture constraint: removal must happen in `Main.php` (AC-HOOKS-MAIN) — the Loader unwires it through Main's define hooks methods.

If other files reference `AcrossAI_I18n`: document reason and add narrow inline suppression (fallback only — requires written justification per planning doc).

---

### Phase 6 — uninstall.php Cleanup (CHANGE-7)

**File**: `uninstall.php`

Read current file first, then apply:

1. Rename all instances of `$delete_data` → `$acrossai_delete_data`
2. Add `WordPress.DB.DirectDatabaseQuery.NoCaching` to both DROP TABLE phpcs:ignore lines
3. Replace table identifier interpolation with `%i`:

```php
$acrossai_abilities_table = $wpdb->prefix . 'acrossai_abilities';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query(
	$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_abilities_table )
);
```

Repeat for `wpb_access_control` table.

**Invariant**: The `acrossai_abilities_uninstall_delete_data` option gate MUST be preserved exactly (PATTERN-UNINSTALL-DATA-GATE). Options removal outside the gate also unchanged.

---

### Phase 7 — Main.php Constant Helper Rename (CHANGE-8)

**File**: `includes/Main.php`

Change private `define()` helper parameter names only:

```php
private function define( $constant_name, $constant_value ) {
	if ( ! defined( $constant_name ) ) {
		define( $constant_name, $constant_value );
	}
}
```

Update `@param` docblock tags to `$constant_name` and `$constant_value`.

Verify PHPDoc long description starts with a capital letter or "The " (BUG-PHPCS-DOCBLOCK-CAPITAL).

Do not rename the method itself.

---

### Phase 8 — Governance Artifacts (CHANGE-9)

#### CHANGE-9a: `AGENTS.md`

Add Plugin Check governance block under quality/checklist section:

```markdown
- Plugin Check must scan the production plugin surface, not the full development repository.
  Exclude hidden files, Spec Kit files, docs, tests, source-only assets, and local tooling from
  Plugin Check CI via `--exclude-directories` / `--exclude-files` flags.
- For dynamic SQL table identifiers, use `$wpdb->prepare()` with `%i`; do not interpolate table
  names into SQL strings.
- WordPress forbidden-function findings must be fixed by replacement/removal, not suppressed.
  `eval()`, `extract()`, and shell/process functions are not allowed in production plugin code.
- Plugin Check suppressions must be local and exact, only for cases with no safe WordPress API
  replacement. Never use workflow-level ignore codes for one-line production-code findings.
- PHPCS is configured through Composer (`composer run phpcs`) with WPCS, WordPress-Docs, and
  PHPCompatibility. Do not make repo-wide PHPCS a required PR gate until the existing baseline
  is fixed or scoped to the production plugin surface.
```

#### CHANGE-9b: `.specify/memory/CONSTITUTION.md`

- Bump version: `1.4.3` → `1.4.4`
- Replace the §II eval() suppression paragraph with the 5-rule Plugin Check block per planning doc
- Update SYNC IMPACT REPORT header with v1.4.4 entry
- Update version footer: `**Version**: 1.4.4 | **Ratified**: 2026-05-11 | **Last Amended**: 2026-05-31`

#### CHANGE-9c: `docs/memory/DECISIONS.md`

1. Find `DEC-EVAL-PHP-CODE` entry and add to its Status line: `Superseded by DEC-PLUGIN-CHECK-PRODUCTION-SURFACE (2026-05-31)`

2. Append new entry `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE` per planning doc specification.

#### CHANGE-9d: `docs/memory/INDEX.md`

Add routing row for `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE` under the Decisions section.

---

## Quality Gates

All gates must pass before the feature is considered complete:

| Gate | Command | Pass Criterion |
|------|---------|----------------|
| PHPStan | `vendor/bin/phpstan analyse --level=8` | 0 errors |
| PHPCS (changed files only) | `vendor/bin/phpcs --standard=phpcs.xml.dist <changed-files>` | 0 new errors on changed files |
| eval() removed | `grep -rn "eval(" includes/ admin/ uninstall.php acrossai-abilities-manager.php` | 0 matches |
| php_code removed | `grep -rn "php_code" includes/ admin/` | 0 matches (excluding comments) |
| I18n removed | `grep -rn "AcrossAI_I18n\|set_locale" includes/ admin/` | 0 matches |
| Plugin Check local | `npx wp-env run cli wp plugin check acrossai-abilities-manager --include-experimental` | 0 production errors/warnings |
| No broad suppression | `grep -rn "ignore-codes" .github/workflows/plugin-check.yml` | 0 matches |

---

## Constraints and Guardrails

- **Do not** edit `tests/**`, hidden development files, or Spec Kit files to satisfy Plugin Check
- **Do not** add `--ignore-warnings: true` or `--ignore-errors: true` to the workflow
- **Do not** change REST endpoint paths or response shapes
- **Do not** change database schemas or log retention behaviour
- **Do not** modify files inside `.agents/tools/`
- **Do not** add `registered_callback` as a new DB enum entry (deferred to follow-up spec)
- **Do not** update admin UI JS/SCSS files (FR-010e deferred to follow-up spec)
- **Use tabs** for PHP indentation (BUG-PHPCBF-TABS — phpcbf converts spaces to tabs)
