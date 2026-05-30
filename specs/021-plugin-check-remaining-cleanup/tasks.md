---
description: "Task list for Feature 021 — Plugin Check Remaining Cleanup"
---

# Tasks: Plugin Check Remaining Cleanup (Feature 021)

**Input**: `specs/021-plugin-check-remaining-cleanup/plan.md`, `spec.md`
**Branch**: `021-plugin-check-cleanup`
**Memory**: `specs/021-plugin-check-remaining-cleanup/memory-synthesis.md`
**Security**: `specs/021-plugin-check-remaining-cleanup/security-constraints.md`

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel with other [P] tasks in the same phase (different files, no shared dependencies)
- **[Story]**: US1 = CI scan surface, US2 = Production PHP clean, US3 = Governance

---

## Phase 1: Pre-flight Checks (Blocking)

**Purpose**: Verify references before any deletions or refactors.

- [x] T001 [US2] Read `includes/Main.php` to confirm `set_locale()` call location and find the `define()` helper method — establishes exact line targets for CHANGE-6 and CHANGE-8
- [x] T002 [US2] Run `grep -rn "AcrossAI_I18n\|set_locale" includes/ admin/ acrossai-abilities-manager.php` — confirm only `Main.php` references the class before deletion
- [x] T003 [US2] Read `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` to locate `make_php_code_callback()`, the `php_code` switch branch, and the `eval()` call line — establishes exact targets for CHANGE-4a
- [x] T004 [US2] [P] Read `uninstall.php` to locate all `$delete_data` references and both DROP TABLE blocks — establishes exact targets for CHANGE-7
- [x] T005 [US1] Read `.github/workflows/plugin-check.yml` to locate the "Run Plugin Check" step and confirm exact `wp plugin check` CLI call shape — establishes exact target for CHANGE-1

**Checkpoint**: Pre-flight complete — exact file targets confirmed for all changes

---

## Phase 2: US1 — CI Scan Surface (CHANGE-1)

**Goal**: Plugin Check CI evaluates production runtime files only; hidden/dev/test findings no longer appear.

**Independent Test**: Open a PR; confirm CI does not flag `.specify/**`, `tests/**`, or `docs/**`. Confirm CI does fail on a bare `error_log()` in a production PHP file.

- [x] T006 [US1] Edit `.github/workflows/plugin-check.yml` — update the "Run Plugin Check" step:
  1. Remove the existing `--ignore-codes=WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` flag — this suppression covered the intentional `eval()` which CHANGE-4 removes; it must not remain in the workflow after eval() is gone.
  2. Append `--exclude-directories` and `--exclude-files` flags:
  ```
  --include-experimental \
  --exclude-directories=.agents,.claude,.github,.specify,docs,node_modules,scripts,specs,src,tests \
  --exclude-files=phpcs.xml.dist,phpstan.neon.dist,phpunit.xml.dist,composer.json,composer.lock,package.json,package-lock.json
  ```
  Do NOT add a new `--ignore-codes`. Do NOT add `--ignore-warnings` or `--ignore-errors`. Keep `wp-env run cli` inlined — do NOT use `npx wp-env` (wp-env is globally installed; PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT).
- [x] T007 [US1] Verify: `grep -c "exclude-directories\|exclude-files" .github/workflows/plugin-check.yml` → 2; `grep "ignore-codes\|ignore-warnings\|ignore-errors" .github/workflows/plugin-check.yml` → 0 matches

---

## Phase 3: US2 — SQL Identifier Escaping (CHANGE-2, CHANGE-3)

**Goal**: All Logger table identifier interpolations replaced with `%i`; `base_prefix` corrected to `prefix`.

These two tasks touch different files and can run in parallel.

- [x] T008 [US2] [P] Edit `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php`:
  - `delete_logs_before_date()`: replace `"DELETE FROM \`{$table}\`…"` + inner `phpcs:ignore InterpolatedNotPrepared` with `$wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $table, $date )`. Remove `InterpolatedNotPrepared` from the outer phpcs:ignore line.
  - `count_logs()`: replace `"SELECT COUNT(*) FROM \`{$table}\`"` single-arg get_var with `$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )`. Remove `InterpolatedNotPrepared` from phpcs:ignore.
  - Do not wrap `%i` in backticks. Do not change method signatures or return values.

- [x] T009 [US2] [P] Edit `includes/Modules/Logger/AcrossAI_Logger_Query.php`:
  - Change `$wpdb->base_prefix` → `$wpdb->prefix` for the `$table` assignment.
  - Rewrite count query: `"SELECT COUNT(*) FROM %i {$where_clause}"` with `array_merge( array( $table ), $where_values )`. Add narrow inline suppress `PluginCheck.Security.DirectDB.UnescapedDBParameter -- WHERE fragments are built from fixed clauses and placeholders only.`
  - Rewrite select query: `"SELECT * FROM %i {$where_clause} ORDER BY \`{$orderby}\` {$order} LIMIT %d OFFSET %d"` with `array_merge( array( $table ), $where_values, array( $per_page, $offset ) )`. Add narrow inline suppress with rationale.
  - Keep `$orderby` and `$order` hardcoded allowlist validation unchanged. Do not change REST response shape (`logs`, `total`, `pages`).

- [x] T010 [US2] Verify: `grep -n "base_prefix\|InterpolatedNotPrepared" includes/Modules/Logger/AcrossAI_Logger_Query.php includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php` → 0 matches

---

## Phase 4: US2 — eval() Removal and Registered-Callback Model (CHANGE-4)

**Goal**: No `eval()` in production code; `php_code` rows fail closed; registered-callback model in place.

Execute in sub-order (a → b → c → d) since they form a logical chain, though b/c/d can overlap after (a) is done.

- [x] T011 [US2] Edit `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` (CHANGE-4a):
  - Remove `make_php_code_callback()` method entirely.
  - Replace the `php_code` switch case with two explicit arms (SEC-01 fix):
    ```php
    case 'registered_callback':
        // Trust boundary: only version-controlled plugin/theme code registers callables here.
        // $input is caller-controlled (untrusted) — registered callbacks must treat it as untrusted.
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
        return new \WP_Error( 'unsupported_callback_type', 'Unsupported ability callback type.' );
    ```
  - Remove the old `// phpcs:ignore … runtime_configuration_eval` inline comment.

- [x] T012 [US2] [P] Edit `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` (CHANGE-4b — static utility):
  - Remove `'php_code'` from the allowed callback types array.
  - Remove any `code` field sanitization branch specific to `php_code`.
  - Do NOT add singleton, `instance()`, or constructor (DEC-UTILITY-STATIC-ONLY).

- [x] T013 [US2] [P] Edit `includes/Utilities/AcrossAI_Abilities_Validator.php` (CHANGE-4c — static utility):
  - Remove the `php_code` validation path: blocked-function array + PHP syntax-check branch.
  - Keep any `call_user_func` at line ~468 that is an internal `$validator` callable (not a Plugin Check finding).
  - Do NOT add singleton or constructor (DEC-UTILITY-STATIC-ONLY).

- [x] T014 [US2] [P] Edit `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (CHANGE-4d):
  - Remove `'php_code'` from the `callback_type` DB enum allowlist array.
  - Do NOT add `'registered_callback'` as a new DB enum entry (deferred per clarification Q2).

- [x] T015 [US2] Verify: `grep -rn "eval(" includes/ admin/ uninstall.php acrossai-abilities-manager.php` → 0; `grep -rn "'php_code'" includes/ admin/` → only the fail-closed `case 'php_code': return WP_Error('unsupported_callback_type')` in Processor remains; no execution path

---

## Phase 5: US2 — REQUEST_URI Sanitization (CHANGE-5)

**Goal**: `$_SERVER['REQUEST_URI']` is unslashed and sanitized before boolean detection.

- [x] T016 [US2] [P] Edit `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`:
  - Replace the raw `$_SERVER['REQUEST_URI']` assignment and its `phpcs:ignore InputNotSanitized` comment with:
    ```php
    $uri = isset( $_SERVER['REQUEST_URI'] )
        ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
        : '';
    ```
  - Remove the phpcs:ignore comment entirely.
  - Verify: confirm route prefix detection (`strpos()`) target string contains only plain ASCII — no encoding dependency (ADV-002 check).
  - Do not change route detection logic, namespace filter, or cache behaviour.

---

## Phase 6: US2 — Manual Textdomain Removal (CHANGE-6)

**Goal**: No `load_plugin_textdomain()` warning; `AcrossAI_I18n` class removed.

- [x] T017 [US2] Edit `includes/Main.php`:
  - Remove the `$this->set_locale()` call (location confirmed in T001).
  - Remove the `set_locale()` method body.
  - Verify the Loader no longer receives a hook for `set_locale`.

- [x] T018 [US2] Delete `includes/AcrossAI_I18n.php` (reference check confirmed clean in T002).

- [x] T019 [US2] Verify: `grep -rn "AcrossAI_I18n\|set_locale" includes/ admin/ acrossai-abilities-manager.php` → 0 matches

---

## Phase 7: US2 — uninstall.php Cleanup (CHANGE-7)

**Goal**: `$delete_data` renamed; NoCaching on DROP TABLE; `%i` for table identifiers.

- [x] T020 [US2] [P] Edit `uninstall.php` (exact targets confirmed in T004):
  - Rename all `$delete_data` → `$acrossai_delete_data`.
  - Add `WordPress.DB.DirectDatabaseQuery.NoCaching` to both DROP TABLE phpcs:ignore comments.
  - Replace backtick-interpolated table identifiers with `$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_var )`.
  - Preserve the `acrossai_abilities_uninstall_delete_data` option gate exactly (PATTERN-UNINSTALL-DATA-GATE). Options removal outside the gate unchanged.

- [x] T021 [US2] Verify: `grep "delete_data" uninstall.php` → 0 bare `$delete_data` references; both DROP TABLE phpcs:ignore lines contain `NoCaching`.

---

## Phase 8: US2 — Main.php Constant Helper Rename (CHANGE-8)

**Goal**: `$name`/`$value` parameters renamed; no `VariableConstantNameFound` finding.

- [x] T022 [US2] [P] Edit `includes/Main.php` private `define()` helper (exact location confirmed in T001):
  - Rename `$name` → `$constant_name` and `$value` → `$constant_value` in the method signature and body.
  - Update `@param` docblock tags to match.
  - Verify PHPDoc long description starts with capital letter or "The " (BUG-PHPCS-DOCBLOCK-CAPITAL).
  - Do not rename the method itself.

---

## Phase 9: Quality Gate — PHP (after all US2 tasks)

**Gate**: No new PHPCS errors on changed files; PHPStan 0 errors.

- [x] T023 [US2] Run PHPCS on all changed PHP files:
  ```bash
  vendor/bin/phpcs --standard=phpcs.xml.dist \
    includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php \
    includes/Modules/Logger/AcrossAI_Logger_Query.php \
    includes/Modules/Abilities/AcrossAI_Abilities_Processor.php \
    includes/Utilities/AcrossAI_Abilities_Sanitizer.php \
    includes/Utilities/AcrossAI_Abilities_Validator.php \
    includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php \
    includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php \
    includes/Main.php \
    uninstall.php
  ```
  Resolve any new errors introduced by this feature. Do not claim repo-wide `composer run phpcs` is clean (pre-existing baseline).

- [x] T024 [US2] Run PHPStan: `vendor/bin/phpstan analyse --level=8` → must return 0 errors. Fix any type errors introduced by this feature.

---

## Phase 10: US3 — Governance Artifacts (CHANGE-9)

**Goal**: AGENTS.md, CONSTITUTION.md, DECISIONS.md, and INDEX.md encode the discovered patterns durably.

These four tasks touch different files and can run in parallel.

- [x] T025 [US3] [P] Edit `AGENTS.md` (CHANGE-9a):
  Add 5-bullet Plugin Check governance block under the existing Plugin Check / quality guidance section:
  production-surface scanning, `%i` SQL identifiers, forbidden-function removal/replacement, local-exact suppressions, PHPCS baseline caveat. Keep concise — top-level instruction file only.

- [x] T026 [US3] [P] Edit `.specify/memory/CONSTITUTION.md` (CHANGE-9b):
  - Add new SYNC IMPACT REPORT entry for v1.4.3 → v1.4.4.
  - In §II, replace the `ignore-codes: WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` paragraph with the 5-rule Plugin Check block (production-surface scope, `%i` SQL identifiers, forbidden-function removal, local-exact suppressions, PHPCS baseline constraint).
  - Update version footer to: `**Version**: 1.4.4 | **Ratified**: 2026-05-11 | **Last Amended**: 2026-05-31`

- [x] T027 [US3] [P] Edit `docs/memory/DECISIONS.md` (CHANGE-9c):
  - Find the `DEC-EVAL-PHP-CODE` entry; add `Superseded by DEC-PLUGIN-CHECK-PRODUCTION-SURFACE (2026-05-31)` to its Status line.
  - Append new `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE` entry per planning doc specification.

- [x] T028 [US3] [P] Edit `docs/memory/INDEX.md` (CHANGE-9d):
  Add routing row for `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE` under the Decisions section.

- [x] T029 [US3] Verify governance artifacts:
  - `grep "1.4.4" .specify/memory/CONSTITUTION.md` → present
  - `grep "DEC-PLUGIN-CHECK-PRODUCTION-SURFACE" docs/memory/DECISIONS.md docs/memory/INDEX.md` → present in both
  - `grep "Superseded" docs/memory/DECISIONS.md` → DEC-EVAL-PHP-CODE entry updated

---

## Phase 11: Final Validation

**Goal**: All success criteria met before commit.

- [ ] T030 [US1] [US2] Run Plugin Check locally (optional but recommended):
  ```bash
  wp-env run cli wp plugin activate acrossai-abilities-manager
  wp-env run cli wp plugin check acrossai-abilities-manager \
    --include-experimental \
    --exclude-directories=.agents,.claude,.github,.specify,docs,node_modules,scripts,specs,src,tests \
    --exclude-files=phpcs.xml.dist,phpstan.neon.dist,phpunit.xml.dist,composer.json,composer.lock,package.json,package-lock.json
  ```
  Use `wp-env` (globally installed) — not `npx wp-env`. Confirm 0 production-code errors or warnings.

- [x] T031 [US1] [US2] [US3] Final grep battery:
  ```bash
  grep -rn "eval(" includes/ admin/ uninstall.php acrossai-abilities-manager.php  # → 0
  grep -rn "'php_code'" includes/ admin/                                            # → 0 (non-comment)
  grep -rn "AcrossAI_I18n\|set_locale" includes/ admin/                            # → 0
  grep -rn "ignore-codes" .github/workflows/plugin-check.yml                        # → 0
  grep -rn "base_prefix" includes/Modules/Logger/                                   # → 0
  grep "\$delete_data" uninstall.php                                                # → 0
  ```

