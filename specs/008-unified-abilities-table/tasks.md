# Tasks: Unified Abilities Table (008)

**Feature**: Unified Abilities Table
**Branch**: `008-unified-abilities-table`
**Status**: Ready for Implementation — Security Re-Review Corrections Applied (2026-05-22)
**Input**: plan.md (N1/N2/N3 corrected), spec.md, memory-synthesis.md, security-constraints.md

> **Governed Tasks Update (2026-05-22)**: Applied N1 (blocklist guard), N2 ($status docblock), N3 (by_source() capability note), N4 advisory (JSON size step). N5 advisory (enabled cast) removed — `enabled` column dropped; `status='publish'` is the sole registration control. Tasks are aligned with corrected plan.md.

---

## Overview

This feature modifies exactly **5 existing files** — no new files are created. Tasks follow the critical-path order imposed by inter-file dependencies.

**Critical path**: T001 → T002 → T003 → T004
**Parallel**: T005 is independent of T001–T004 and may run at any point
**Quality gate**: T006 depends on T001–T005

### User Story Map

| Story | Priority | Covered By |
|-------|----------|------------|
| US1 — Abilities as first-class records | P1 | T001, T002, T003, T004 |
| US2 — Abilities queryable by source | P2 | T004 (`by_source()`) |
| US3 — Unified table replaces override-only table | P3 | T001 (rename), T005 (uninstall) |

---

## Phase 1: Foundational Database Layer (US1, US3)

**Checkpoint**: T001 and T002 must be complete before T003; T003 before T004.

- [x] T001 [US1] [US3] Rename `$name`/`$db_version_key` and expand `set_schema()` to 24 columns + 5 indexes in `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php`

  **Changes**:
  1. `$name`: `'acrossai_abilities_overwrite'` → `'acrossai_abilities'` (FR-001)
  2. `$db_version_key`: `'acrossai_abilities_overwrite_db_version'` → `'acrossai_abilities_db_version'` (FR-014)
  3. `$version`: MUST remain `'1.0.0'` — do NOT change (FR-017)
  4. `$global`: MUST remain `false` — do NOT change (SEC-03)
  5. Replace `set_schema()` body entirely with the 24-column + 5-index DDL from plan.md

  **Exact `set_schema()` SQL (must match verbatim)**:
  ```sql
  `id`             bigint(20) unsigned NOT NULL auto_increment,
  `ability_slug`   varchar(255) NOT NULL DEFAULT '',
  `label`          varchar(255) DEFAULT NULL,               -- nullable (FR-021)
  `description`    longtext DEFAULT NULL,
  `category`       varchar(100) DEFAULT NULL,
  `status`         varchar(20) NOT NULL DEFAULT 'draft',   -- varchar(20) NOT 50 (FR-020)
  `provider`       varchar(100) DEFAULT NULL,
  `source`         varchar(50) NOT NULL DEFAULT 'db',
  `site_allowed`   tinyint(1) DEFAULT NULL,
  `callback_type`  varchar(50) NOT NULL DEFAULT 'noop',
  `callback_config` longtext DEFAULT NULL,
  `input_schema`   longtext DEFAULT NULL,
  `output_schema`  longtext DEFAULT NULL,
  `show_in_rest`   tinyint(1) DEFAULT NULL,
  `show_in_mcp`    tinyint(1) DEFAULT NULL,
  `mcp_type`       varchar(100) DEFAULT NULL,
  `mcp_servers`    longtext DEFAULT NULL,
  `readonly`       tinyint(1) DEFAULT NULL,
  `destructive`    tinyint(1) DEFAULT NULL,
  `idempotent`     tinyint(1) DEFAULT NULL,
  `created_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`     bigint(20) unsigned DEFAULT NULL,
  `updated_by`     bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ability_slug` (`ability_slug`(191)),
  KEY `idx_status` (`status`),
  KEY `idx_source` (`source`),
  KEY `idx_updated_at` (`updated_at`)
  ```

  **Acceptance Criteria**:
  - `$name === 'acrossai_abilities'`
  - `$db_version_key === 'acrossai_abilities_db_version'`
  - `$version === '1.0.0'` (verified unchanged)
  - `$global === false` (verified unchanged)
  - `set_schema()` SQL contains exactly 24 column definitions
  - `status` column is `varchar(20)` — NOT `varchar(50)` (FR-020)
  - `label` column is nullable `DEFAULT NULL` — NOT `NOT NULL DEFAULT ''` (FR-021)
  - Exactly 5 indexes: `PRIMARY KEY (id)`, `UNIQUE KEY ability_slug (ability_slug(191))`, `KEY idx_status (status)`, `KEY idx_source (source)`, `KEY idx_updated_at (updated_at)` (FR-016)
  - PHPCS: zero errors

  **Watchpoints**:
  - ⛔ FR-017: `$version` must stay `'1.0.0'` — bumping triggers `maybe_upgrade()` DDL re-run on existing sites
  - ⛔ SEC-03: `$global = false` is a multisite isolation contract
  - ⛔ FR-020: `status` is `varchar(20)` — not `varchar(50)`
  - ⛔ FR-021: `label` is nullable — override rows carry no label
  - ⛔ FR-016: `UNIQUE KEY` (not plain `KEY`) for `ability_slug`
  - ⛔ NO private constructor on `AcrossAI_Sitewide_Table` — `AcrossAI_Activator` calls `new AcrossAI_Sitewide_Table()` directly (FR-015 forbids touching Activator). Singleton is soft: `instance()` method exists but instantiation is not language-enforced. Do NOT add `private function __construct()` to this class.

  **File**: `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php`
  **Dependencies**: None (first task on critical path)

---

- [x] T002 [US1] Add 8 new column definitions to `$columns` and update `source` default in `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php`

  **Changes**:

  1. After the `ability_slug` column definition and before `provider`, insert 5 new entries:
     ```php
     // label (FR-021: nullable)
     array( 'name' => 'label', 'type' => 'varchar', 'length' => '255', 'allow_null' => true, 'default' => null, 'searchable' => true, 'sortable' => true ),
     // description
     array( 'name' => 'description', 'type' => 'longtext', 'allow_null' => true, 'default' => null, 'searchable' => true ),
     // category
     array( 'name' => 'category', 'type' => 'varchar', 'length' => '100', 'allow_null' => true, 'default' => null, 'sortable' => true ),
     // status (FR-020: length '20')
     array( 'name' => 'status', 'type' => 'varchar', 'length' => '20', 'null' => false, 'default' => 'draft', 'sortable' => true ),
     ```

  2. Update the `source` column definition — change `allow_null => true, default => null` to `null => false, default => 'db'` (FR-006).

  3. After the `site_allowed` column definition, insert 4 new entries:
     ```php
     // callback_type
     array( 'name' => 'callback_type', 'type' => 'varchar', 'length' => '50', 'null' => false, 'default' => 'noop' ),
     // callback_config (JSON longtext)
     array( 'name' => 'callback_config', 'type' => 'longtext', 'allow_null' => true, 'default' => null ),
     // input_schema (JSON longtext)
     array( 'name' => 'input_schema', 'type' => 'longtext', 'allow_null' => true, 'default' => null ),
     // output_schema (JSON longtext)
     array( 'name' => 'output_schema', 'type' => 'longtext', 'allow_null' => true, 'default' => null ),
     ```

  **Acceptance Criteria**:
  - `$columns` array has exactly 24 entries (16 existing + 8 new)
  - `source` entry: `null => false`, `default => 'db'` (FR-006)
  - `status` entry: `type => 'varchar'`, `length => '20'` (FR-020), `default => 'draft'` (FR-004)
  - `label` entry: `allow_null => true`, `default => null` (FR-021)
  - All 4 JSON-field columns (`callback_config`, `input_schema`, `output_schema`, `mcp_servers`) defined as `type => 'longtext'`, `allow_null => true`
  - Column insertion positions match the DDL column order in T001's `set_schema()` SQL exactly
  - PHPCS: zero errors

  **Watchpoints**:
  - ⛔ FR-020: `status` `length` must be `'20'` not `'50'`
  - ⛔ FR-021: `label` must use `allow_null => true` + `default => null` — not `null => false`
  - Column order in `$columns` must mirror the column order in `set_schema()` SQL from T001

  **File**: `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php`
  **Dependencies**: T001 (column list must match set_schema() SQL)

---

## Phase 2: Row Object Expansion (US1)

**Checkpoint**: T003 must be complete before T004 (Query calls `AcrossAI_Sitewide_Row::get_json_fields()`).

- [x] T003 [US1] Add 8 new public properties, `get_json_fields()` static method, and update `__construct()` in `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php`

  **Changes**:

  **A) Update `@property` docblock** — add 9 `@property` entries to the class docblock matching the new columns.

  **B) Add 8 new public property declarations** (after existing properties, before `__construct()`):
  ```php
  /** Display name. @var string|null */
  public $label = null;
  /** Full description. @var string|null */
  public $description = null;
  /** Organizational category. @var string|null */
  public $category = null;
  /** Lifecycle status. Valid: 'draft', 'publish'. @var string */
  public $status = 'draft';
  /** Callback type. @var string */
  public $callback_type = 'noop';
  /** Decoded callback configuration. @var array|null */
  public $callback_config = null;
  /** Decoded input JSON Schema. @var array|null */
  public $input_schema = null;
  /** Decoded output JSON Schema. @var array|null */
  public $output_schema = null;
  ```

  **C) Add `get_json_fields()` static method** (FR-009 + F1 security guard):
  ```php
  /**
   * Filterable list of fields stored as JSON in the database (FR-009).
   *
   * F1 SECURITY GUARD: Uses a blocklist of known scalar/computed columns that MUST NOT
   * appear in the JSON decode loop (injecting them would corrupt Row properties in
   * __construct()). Any new longtext/JSON column added via the filter IS allowed through,
   * preserving the SC-005/FR-009 extensibility contract.
   *
   * Security Re-Review Correction N1 (2026-05-22): Changed from allowlist to blocklist.
   *
   * @since  0.1.0
   * @return string[]
   */
  public static function get_json_fields(): array {
      $base = array(
          'mcp_servers', 'callback_config', 'input_schema', 'output_schema',
      );

      $fields = apply_filters( 'acrossai_abilities_json_fields', $base );

      // F1: Protected scalar columns that MUST NOT appear in the JSON field list.
      // Injecting these into the JSON decode loop would silently corrupt Row properties.
      // Blocklist approach allows new longtext/JSON columns via filter (SC-005, FR-009).
      $blocked_scalar_columns = array(
          'id', 'ability_slug', 'label', 'description', 'category', 'status',
          'provider', 'source', 'site_allowed', 'callback_type',
          'show_in_rest', 'show_in_mcp', 'mcp_type', 'readonly', 'destructive',
          'idempotent', 'created_at', 'updated_at', 'created_by', 'updated_by',
      );

      return array_values(
          array_filter(
              (array) $fields,
              static function ( $field ) use ( $blocked_scalar_columns ) {
                  return is_string( $field ) && ! in_array( $field, $blocked_scalar_columns, true );
              }
          )
      );
  }
  ```

  **D) Update `__construct()`** — replace any hardcoded `mcp_servers` JSON decode block with the registry loop:
  ```php
  // Decode JSON-encoded fields using the single filterable registry (FR-009, FR-008).
  // Returns array|null — malformed JSON or non-array value yields null.
  foreach ( self::get_json_fields() as $json_field ) {
      if ( null !== $this->{$json_field} && isset( $this->{$json_field} ) ) {
          $decoded             = json_decode( $this->{$json_field}, true );
          $this->{$json_field} = is_array( $decoded ) ? $decoded : null;
      }
  }
  ```
  The tri-state cast block and integer cast block remain unchanged.

  **Acceptance Criteria**:
  - 24 public property declarations total (16 existing + 8 new)
  - `$label` default is `null` — NOT `''` (FR-021)
  - `$status` default is `'draft'` (FR-004)
  - `$callback_type` default is `'noop'` (FR-007)
  - `get_json_fields()` is `public static` — no instance state (DEC-UTILITY-STATIC-ONLY)
  - `get_json_fields()` applies filter `acrossai_abilities_json_fields` (FR-009)
  - `get_json_fields()` includes F1 blocklist guard — injecting `ability_slug` via filter returns it stripped; injecting a new longtext column name returns it included (SC-005/FR-009 extensibility preserved — Security Re-Review N1)
  - `__construct()` JSON decode loop iterates `self::get_json_fields()` — NOT hardcoded `mcp_servers`
  - Malformed JSON field returns `null`, not a raw string (FR-008)
  - Tri-state cast block unchanged
  - Integer cast block unchanged
  - PHPCS: zero errors; PHPStan L8: zero errors

  **Watchpoints**:
  - ⛔ F1 guard: `get_json_fields()` blocklist MUST strip the 20 known scalar/computed columns; MUST allow new longtext column names added via filter (SC-005/FR-009 — N1 correction)
  - ⛔ JSON decode: `is_array($decoded) ? $decoded : null` — non-array JSON value (e.g. JSON string `"hello"`) returns `null`
  - ⛔ `label` property default: `null`, not `''`

  **File**: `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php`
  **Dependencies**: T001, T002 (column list must be consistent across all 3 DB files)

---

## Phase 3: Query Layer Update (US1, US2)

- [x] T004 [US1] [US2] Rename `$table_name`, replace `mcp_servers` encode block with registry loop, add F2 enum guards, and add `by_source()` method in `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php`

  **Changes**:

  **A) Rename `$table_name`**:
  ```diff
  - protected $table_name = 'acrossai_abilities_overwrite';
  + protected $table_name = 'acrossai_abilities';
  ```

  **B) In `save_override()` — remove the single `mcp_servers` hardcoded encode block and replace with registry loop** (FR-009, FR-010, FR-018):

  Remove:
  ```php
  if ( isset( $fields['mcp_servers'] ) && is_array( $fields['mcp_servers'] ) ) {
      $fields['mcp_servers'] = wp_json_encode( $fields['mcp_servers'] );
  }
  ```

  Replace with:
  ```php
  // JSON-encode all array-valued JSON fields via the single filterable registry (FR-009/FR-010).
  // If wp_json_encode() fails for a field, store null; save continues for remaining fields.
  foreach ( AcrossAI_Sitewide_Row::get_json_fields() as $json_field ) {
      if ( isset( $fields[ $json_field ] ) && is_array( $fields[ $json_field ] ) ) {
          $encoded               = wp_json_encode( $fields[ $json_field ] );
          $fields[ $json_field ] = false !== $encoded ? $encoded : null;
      }
  }
  ```

  **C) In `save_override()` — add F2 enum guards** (security-constraints.md Finding 2) immediately after the tri-state cast block and **before** the JSON registry loop, following the same pattern as the existing `mcp_type` guard:
  ```php
  // F2: Guard status — allow only known lifecycle values (varchar(20) defense-in-depth).
  if ( array_key_exists( 'status', $fields ) && null !== $fields['status'] ) {
      $allowed_statuses = array( 'draft', 'publish' );
      if ( ! in_array( $fields['status'], $allowed_statuses, true ) ) {
          unset( $fields['status'] );
      }
  }

  // F2: Guard callback_type — allow only known callback types.
  if ( array_key_exists( 'callback_type', $fields ) && null !== $fields['callback_type'] ) {
      $allowed_callback_types = array( 'noop', 'filter_hook', 'wp_remote_post', 'php_code' );
      if ( ! in_array( $fields['callback_type'], $allowed_callback_types, true ) ) {
          unset( $fields['callback_type'] );
      }
  }
  ```

  **D) Keep these 3 blocks unchanged** (FR-018 — do NOT modify):
  1. Tri-state bool-to-int cast block (`$tri_state_columns` foreach)
  2. `mcp_type` value validation guard
  3. `mcp_servers` non-string guard (runs after the new JSON loop)

  **E) Add `by_source()` method** (FR-011, FR-019, BUG-BERLINDB-UNLIMITED):
  ```php
  /**
   * Retrieve all ability records matching a given source (FR-019).
   *
   * Method name MUST be by_source() — spec 009 calls it by this exact name.
   * Returns [] immediately when $source is empty or null (FR-011).
   * Uses 'number' => 0 for unlimited results (BUG-BERLINDB-UNLIMITED pattern).
   *
  * Caller responsibility: all public callers MUST verify current_user_can( 'manage_options' )
   * before exposing results to clients. This method performs no capability check (DB layer).
   *
   * @since  0.1.0
   * @param  string|null $source Source value to filter by (e.g. 'db', 'plugin').
   * @return AcrossAI_Sitewide_Row[]
   */
  public function by_source( ?string $source ): array {
      if ( empty( $source ) ) {
          return array();
      }

      $results = $this->query(
          array(
              'source' => $source,
              'number' => 0,
          )
      );

      return array_filter(
          $results,
          static function ( $row ) {
              return $row instanceof AcrossAI_Sitewide_Row;
          }
      );
  }
  ```

  **Acceptance Criteria**:
  - `$table_name === 'acrossai_abilities'`
  - `save_override()` JSON encoding loop calls `AcrossAI_Sitewide_Row::get_json_fields()` (not hardcoded field list)
  - Encoding failure stores `null` (not `false`) and does NOT abort the save (FR-010)
  - F2 `status` guard: `'invalid_long_value'` is unset before DB write; `'draft'` and `'publish'` pass through
  - F2 `callback_type` guard: `'invalid_type'` is unset before DB write; `'noop'`, `'filter_hook'`, `'wp_remote_post'` pass through
  - FR-018 three blocks MUST remain completely unchanged (verify by diff): (1) tri-state cast, (2) `mcp_type` guard, (3) `mcp_servers` non-string guard
  - Method name is exactly `by_source()` — NOT `get_all_by_source()` (FR-019)
  - `by_source(null)` returns `[]` (FR-011)
  - `by_source('')` returns `[]` (FR-011)
  - `by_source('db')` returns all rows where `source = 'db'`, unlimited (BUG-BERLINDB-UNLIMITED)
  - Query uses `'number' => 0` — NOT `-1` or `9999` (BUG-BERLINDB-UNLIMITED)
  - `by_source()` docblock includes explicit `current_user_can( 'manage_options' )` caller-responsibility note (Security Re-Review N3)
  - PHPCS: zero errors; PHPStan L8: zero errors

  **Watchpoints**:
  - ⛔ FR-019: Method name is `by_source()` exactly — `get_all_by_source()` breaks spec 009's call site
  - ⛔ BUG-BERLINDB-UNLIMITED: `'number' => 0`; `absint(-1) = 1` silently limits to 1 row
  - ⛔ FR-018: Do NOT touch the 3 preserved blocks — verify by diff against original file
  - ⛔ F2 guards must use strict `in_array(..., true)` comparison (SEC-04)
  - ⛔ JSON loop placement: after tri-state cast + F2 guards, before `mcp_type`/`mcp_servers` guards

  **File**: `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php`
  **Dependencies**: T003 (`AcrossAI_Sitewide_Row::get_json_fields()` must exist before Query references it)

---

## Phase 4: Uninstall Cleanup (US3) — Parallel

- [x] T005 [P] [US3] Add DROP and `delete_option` for the new table/option names in `uninstall.php`

  **Changes**: Insert 2 new lines **before** the existing `acrossai_abilities_overwrite` DROP so the new name is cleaned first, then the old name:

  ```php
  // Drop the unified abilities table (renamed in feature 008).
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
  $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}acrossai_abilities`" );

  // Remove BerlinDB db-version option for the unified abilities table.
  delete_option( 'acrossai_abilities_db_version' );
  ```

  The existing two lines (old table name) must be kept exactly as-is:
  ```php
  $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}acrossai_abilities_overwrite`" );
  delete_option( 'acrossai_abilities_overwrite_db_version' );
  ```

  **Acceptance Criteria**:
  - `DROP TABLE IF EXISTS {prefix}acrossai_abilities` present (FR-013)
  - `delete_option( 'acrossai_abilities_db_version' )` present (FR-013, FR-014)
  - `DROP TABLE IF EXISTS {prefix}acrossai_abilities_overwrite` still present (backward-compat)
  - `delete_option( 'acrossai_abilities_overwrite_db_version' )` still present (backward-compat)
  - New DROP precedes old DROP (cleanup order: new name first)
  - `WP_UNINSTALL_PLUGIN` guard unchanged
  - PHPCS inline suppression comment on new `$wpdb->query()` line (matching existing file style)
  - PHPCS: zero errors

  **Watchpoints**:
  - ⛔ Do NOT remove the old `acrossai_abilities_overwrite` lines — needed for backward-compat cleanup on existing sites
  - ⛔ Table reference must use `{$wpdb->prefix}` interpolation — not hardcoded prefix

  **File**: `uninstall.php`
  **Dependencies**: None — parallel with T001–T004

---

## Phase 5: Quality Gate

- [x] T006 Run PHPCS, PHPStan L8, and verify FR-015 compliance across all 5 modified files

  **Verification Steps**:

  1. **PHPCS**: `composer phpcs` — zero errors, zero warnings across all 5 files
  2. **PHPStan L8**: `composer phpstan -- --level 8` — zero errors across all 5 files
  3. **FR-015 compliance** — verify these 3 files have zero diff:
     - `includes/Main.php`
     - `includes/AcrossAI_Activator.php`
     - `tests/phpunit/integration/` (all test files)
  4. **FR-017 guard**: `grep -n "version" includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php` — confirm `$version = '1.0.0'`
  5. **SEC-03 guard**: confirm `$global = false` still present in Table
  6. **FR-016 indexes**: inspect `set_schema()` — exactly 5 indexes; `ability_slug` is `UNIQUE KEY`
  7. **FR-018 diff**: diff `save_override()` against original — confirm only the `mcp_servers` hardcoded block was removed and the 3 preserved blocks are byte-for-byte identical
  8. **FR-019 name check**: `grep -n "by_source\|get_all_by_source" includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` — only `by_source` present
  9. **BUG-BERLINDB-UNLIMITED**: `by_source()` passes `'number' => 0` — NOT `-1`
  10. **F1 blocklist guard** (N1): `AcrossAI_Sitewide_Row::get_json_fields()`:
      - Add `'ability_slug'` via filter → must NOT appear in result (scalar blocked)
      - Add `'custom_metadata'` (hypothetical new longtext col) via filter → MUST appear in result (extensibility preserved, SC-005)
  11. **F2 guards**: call `save_override()` with `status = 'invalid_long_status'` — verify `status` is unset before BerlinDB write
  12. **FR-020**: `set_schema()` SQL contains `varchar(20)` for `status` — NOT `varchar(50)`
  12.5 **N4 advisory — JSON caller contract** (advisory): Verify that `save_override()` has a comment: `// JSON field size/depth validation is the caller's responsibility.` If REST controllers exist that call `save_override()` with `callback_config`/`input_schema`/`output_schema`, confirm they enforce max 64KB size and max 10 nesting levels
  13. **FR-021**: `set_schema()` SQL contains `DEFAULT NULL` for `label` — NOT `NOT NULL DEFAULT ''`
  14. **FR-021 Row**: `AcrossAI_Sitewide_Row::$label` default is `null` — NOT `''`
  15. **FR-012 audit-field immutability**: Insert a record. Then call `update_item($id, ['status' => 'publish'])`. Re-read the row and verify `created_at` and `created_by` are byte-for-byte identical to the insert values; verify only `updated_at` and `updated_by` changed. (FR-012 requirement: on update, `created_at`/`created_by` MUST NOT be overwritten.)
  16. **Existing test suite**: run `composer test` — all pre-existing tests must pass (SC-002)

  **Acceptance Criteria**:
  - All 16 verification steps above pass with zero failures
  - PHPCS: zero errors on all 5 files
  - PHPStan L8: zero errors on all 5 files
  - Zero modifications to `Main.php`, `AcrossAI_Activator.php`, or any test file (FR-015)
  - `$version === '1.0.0'` in Table (FR-017)
  - `$global === false` in Table (SEC-03)
  - Existing test suite passes (SC-002)

  **Files**: All 5 modified files + run test suite
  **Dependencies**: T001, T002, T003, T004, T005

---

## Dependency Graph

```
T001 (Table)
  └─→ T002 (Schema) [columns must match T001 DDL order]
        └─→ T003 (Row) [get_json_fields() must exist before T004]
              └─→ T004 (Query) [calls AcrossAI_Sitewide_Row::get_json_fields()]
                    └─→ T006 (Quality Gate)

T005 (uninstall.php) [P — parallel with T001–T004]
  └─→ T006 (Quality Gate)
```

---

## Success Criteria Coverage

| SC | Criterion | Task |
|----|-----------|------|
| SC-001 | 24 columns on fresh install | T001 (`set_schema`), T002 (Schema `$columns`) |
| SC-002 | Existing tests pass unmodified | FR-015 guard in T006 |
| SC-003 | 24-field round-trip without data loss | T002 + T003 (Row properties + JSON decode) |
| SC-004 | `by_source()` exact-match query | T004 (`by_source()` method) |
| SC-005 | New JSON field via filter auto-decoded/encoded | T003 (`get_json_fields()`) + T004 (registry loop) |
| SC-006 | Uninstall leaves no trace | T005 (DROP + delete_option) |

---

## Summary

| Task | File | Story | Parallel | Complexity |
|------|------|-------|----------|------------|
| T001 | `AcrossAI_Sitewide_Table.php` | US1, US3 | — | S |
| T002 | `AcrossAI_Sitewide_Schema.php` | US1 | — | S |
| T003 | `AcrossAI_Sitewide_Row.php` | US1 | — | M |
| T004 | `AcrossAI_Sitewide_Query.php` | US1, US2 | — | M |
| T005 | `uninstall.php` | US3 | ✓ | XS |
| T006 | All 5 files (quality gate) | — | — | S |

**Total tasks**: 6 | **MVP scope**: T001–T005 (T006 is quality gate)
