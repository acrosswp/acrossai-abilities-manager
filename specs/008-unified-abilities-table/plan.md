# Implementation Plan: Unified Abilities Table (008)

**Branch**: `008-unified-abilities-table` | **Date**: 2026-05-22 | **Status**: Ready for Implementation

> **Plan Corrections Applied (2026-05-22)**: V1 — method renamed to `by_source()` per FR-019; V2 — `status` column narrowed to `varchar(20)` per FR-020; V3 — `label` made nullable per FR-021.
>
> **Security Re-Review Corrections (2026-05-22)**: N1 — F1 guard changed from allowlist to blocklist (restores filter extensibility per SC-005/FR-009); N2 — `$status` docblock corrected to `draft|publish`; N3 — `by_source()` docblock gains `manage_options` caller-responsibility note.

---

## Summary

Rename the BerlinDB sitewide override table from `acrossai_abilities_overwrite` (16 columns) to `acrossai_abilities` (24 columns) and extend it with 8 new first-class record fields. Add a single filterable JSON field registry and a by-source query method. **Exactly 5 existing files are modified; zero new files are created.**

**Technical Approach**:
- Table rename + 8-column extension in Schema + Table (raw SQL schema)
- JSON field registry (`get_json_fields()`) as `public static` on Row; called by both Row constructor and Query save method
- `by_source()` added to Query; returns `[]` immediately for empty/null source (FR-019)
- `save_override()` JSON encoding loop replaces the single `mcp_servers` hardcoded block; 3 existing blocks remain unchanged (FR-018)
- `uninstall.php` gains DROP + delete_option for new names; old lines remain for backward-compat cleanup

---

## Constitution & Memory Check

✅ **FR-015**: All changes confined to 5 files — Schema, Table, Row, Query, uninstall.php. Main.php, Activator, test files untouched.  
✅ **FR-017**: `AcrossAI_Sitewide_Table::$version` stays `'1.0.0'` — never bumped.  
✅ **SEC-03**: `$global = false` retained in Table (multisite isolation).  
✅ **BUG-BERLINDB-UNLIMITED**: `by_source()` uses `'number' => 0` (not `-1`).  
✅ **DEC-UTILITY-STATIC-ONLY**: `get_json_fields()` is `public static`; no instance state.  
✅ **AC-FILE-HEADER-PATTERN**: All file headers retain `@package`, `@subpackage`, `@since 0.1.0`.  
✅ **FR-010 failure path**: If `wp_json_encode()` returns `false`, field is stored as `null`; save continues for remaining fields.  

No hard conflicts with existing decisions. No Constitution violations detected.

---

## Data Model

### Table: `{prefix}acrossai_abilities` (24 columns)

| # | Column | Type | Default | Notes |
|---|--------|------|---------|-------|
| 1 | `id` | `bigint(20) unsigned` | — | AUTO_INCREMENT, PRIMARY KEY |
| 2 | `ability_slug` | `varchar(255)` | `''` | UNIQUE KEY (191 prefix) |
| 3 | `label` | `varchar(255)` | NULL | NEW — display name; nullable (FR-021) |
| 4 | `description` | `longtext` | NULL | NEW |
| 5 | `category` | `varchar(100)` | NULL | NEW |
| 6 | `status` | `varchar(20)` | `'draft'` | NEW — FR-004, FR-020. publish=always live, draft=not live |
| 7 | `provider` | `varchar(100)` | NULL | existing |
| 8 | `source` | `varchar(50)` | `'db'` | existing; default updated (FR-006) |
| 9 | `site_allowed` | `tinyint(1)` | NULL | existing tri-state |
| 10 | `callback_type` | `varchar(50)` | `'noop'` | NEW — FR-007 |
| 11 | `callback_config` | `longtext` | NULL | NEW — JSON (FR-008) |
| 12 | `input_schema` | `longtext` | NULL | NEW — JSON (FR-008) |
| 13 | `output_schema` | `longtext` | NULL | NEW — JSON (FR-008) |
| 14 | `show_in_rest` | `tinyint(1)` | NULL | existing tri-state |
| 15 | `show_in_mcp` | `tinyint(1)` | NULL | existing tri-state |
| 16 | `mcp_type` | `varchar(100)` | NULL | existing |
| 17 | `mcp_servers` | `longtext` | NULL | existing — JSON (FR-008) |
| 18 | `readonly` | `tinyint(1)` | NULL | existing tri-state |
| 19 | `destructive` | `tinyint(1)` | NULL | existing tri-state |
| 20 | `idempotent` | `tinyint(1)` | NULL | existing tri-state |
| 21 | `created_at` | `datetime` | `CURRENT_TIMESTAMP` | existing |
| 22 | `updated_at` | `datetime` | `CURRENT_TIMESTAMP` | existing |
| 23 | `created_by` | `bigint(20) unsigned` | NULL | existing |
| 24 | `updated_by` | `bigint(20) unsigned` | NULL | existing |

### Indexes (FR-016 — exactly these 5)

```sql
PRIMARY KEY (`id`)
UNIQUE KEY `ability_slug` (`ability_slug`(191))
KEY `idx_status` (`status`)
KEY `idx_source` (`source`)
KEY `idx_updated_at` (`updated_at`)
```

> **Note**: The existing `KEY ability_slug (ability_slug(191))` becomes `UNIQUE KEY`. The old `KEY updated_at` does not exist; `idx_updated_at` is new.

### JSON Field Registry

A single `public static` method on `AcrossAI_Sitewide_Row` governs which fields are stored as JSON and decoded on read / encoded on save:

```php
/**
 * Filterable list of fields stored as JSON in the database (FR-009).
 *
 * F1 SECURITY GUARD: Injected field names are validated against a blocklist of
 * protected scalar columns before use. Non-longtext scalar columns (id, ability_slug,
 * status, provider, etc.) cannot be injected via the filter — doing so would silently
 * corrupt Row properties in __construct() by running json_decode() on non-JSON scalar
 * values. Third parties can safely add NEW longtext/JSON columns via the filter;
 * only the 21 protected scalar column names are rejected.
 *
 * @since 0.1.0
 * @return string[]
 */
public static function get_json_fields(): array {
    // Blocked: all non-JSON scalar columns — must never be processed with json_decode().
    // Running json_decode() on these would corrupt their values (e.g. json_decode('draft') = null).
    $blocked_scalar_columns = array(
        'id', 'ability_slug', 'label', 'description', 'category', 'status',
        'provider', 'source', 'site_allowed', 'callback_type',
        'show_in_rest', 'show_in_mcp', 'mcp_type', 'readonly', 'destructive',
        'idempotent', 'created_at', 'updated_at', 'created_by', 'updated_by',
    );

    $fields = apply_filters(
        'acrossai_abilities_json_fields',
        array( 'mcp_servers', 'callback_config', 'input_schema', 'output_schema' )
    );

    // F1: Reject any field name that is a known non-JSON scalar column.
    // Prevents filter injection of protected column names that would be corrupted by json_decode().
    // Third parties can add NEW longtext/JSON columns safely via this filter.
    return array_values(
        array_filter(
            (array) $fields,
            static function ( $field ) use ( $blocked_scalar_columns ) {
                return ! in_array( $field, $blocked_scalar_columns, true );
            }
        )
    );
}
```

Called from:
- `AcrossAI_Sitewide_Row::__construct()` — decode each field: `json_decode → array|null`
- `AcrossAI_Sitewide_Query::save_override()` — encode each field: `array → wp_json_encode → string|null`

> **Security note (F1)**: The blocklist inside `get_json_fields()` blocks all 21 known scalar columns from being treated as JSON fields. Third parties can safely add genuinely new longtext/JSON columns by returning them from the `acrossai_abilities_json_fields` filter — only blocked scalar column names are rejected. This prevents silent Row property corruption from injected scalar field names.

---

## File-by-File Implementation Plan

### File 1: `AcrossAI_Sitewide_Table.php`

**Purpose**: Table manager class — drives `CREATE TABLE` DDL + upgrade lifecycle.

**Changes**:

```diff
- protected $name = 'acrossai_abilities_overwrite';
+ protected $name = 'acrossai_abilities';

- protected $db_version_key = 'acrossai_abilities_overwrite_db_version';
+ protected $db_version_key = 'acrossai_abilities_db_version';

  protected $version = '1.0.0'; // MUST NOT change (FR-017)
  protected $global  = false;   // MUST remain false (SEC-03)
```

**`set_schema()` full replacement** — 24 columns + 5 indexes (FR-002, FR-016):

```php
protected function set_schema() {
    $this->schema = "
        `id` bigint(20) unsigned NOT NULL auto_increment,
        `ability_slug` varchar(255) NOT NULL DEFAULT '',
        `label` varchar(255) DEFAULT NULL,
        `description` longtext DEFAULT NULL,
        `category` varchar(100) DEFAULT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'draft',
        `provider` varchar(100) DEFAULT NULL,
        `source` varchar(50) NOT NULL DEFAULT 'db',
        `site_allowed` tinyint(1) DEFAULT NULL,
        `callback_type` varchar(50) NOT NULL DEFAULT 'noop',
        `callback_config` longtext DEFAULT NULL,
        `input_schema` longtext DEFAULT NULL,
        `output_schema` longtext DEFAULT NULL,
        `show_in_rest` tinyint(1) DEFAULT NULL,
        `show_in_mcp` tinyint(1) DEFAULT NULL,
        `mcp_type` varchar(100) DEFAULT NULL,
        `mcp_servers` longtext DEFAULT NULL,
        `readonly` tinyint(1) DEFAULT NULL,
        `destructive` tinyint(1) DEFAULT NULL,
        `idempotent` tinyint(1) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_by` bigint(20) unsigned DEFAULT NULL,
        `updated_by` bigint(20) unsigned DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `ability_slug` (`ability_slug`(191)),
        KEY `idx_status` (`status`),
        KEY `idx_source` (`source`),
        KEY `idx_updated_at` (`updated_at`)
    ";
}
```

**Acceptance Criteria**:
- `$name === 'acrossai_abilities'`
- `$db_version_key === 'acrossai_abilities_db_version'`
- `$version === '1.0.0'` (unchanged)
- `$global === false` (unchanged)
- `set_schema()` SQL contains exactly 24 column definitions
- Exactly 5 indexes present: PRIMARY KEY, UNIQUE KEY `ability_slug`, KEY `idx_status`, KEY `idx_source`, KEY `idx_updated_at`

---

### File 2: `AcrossAI_Sitewide_Schema.php`

**Purpose**: BerlinDB Schema subclass — column metadata used by Query for search/sort/filter introspection.

**Changes**: Add 8 new column definitions to `$columns` array; update `source` default to `'db'`.

New columns to insert **after** the `ability_slug` column definition and before `provider`:

```php
// Display fields (NEW).
array(
    'name'       => 'label',
    'type'       => 'varchar',
    'length'     => '255',
    'allow_null' => true,
    'default'    => null,
    'searchable' => true,
    'sortable'   => true,
),
array(
    'name'       => 'description',
    'type'       => 'longtext',
    'allow_null' => true,
    'default'    => null,
    'searchable' => true,
),
array(
    'name'     => 'category',
    'type'     => 'varchar',
    'length'   => '100',
    'allow_null' => true,
    'default'  => null,
    'sortable' => true,
),

// Lifecycle fields (NEW).
array(
    'name'     => 'status',
    'type'     => 'varchar',
    'length'   => '20',
    'null'     => false,
    'default'  => 'draft',
    'sortable' => true,
),
```

Update `source` default (was `null`, must be `'db'` per FR-006):

```diff
  array(
      'name'       => 'source',
      'type'       => 'varchar',
      'length'     => '50',
-     'allow_null' => true,
-     'default'    => null,
+     'null'       => false,
+     'default'    => 'db',
      'sortable'   => true,
  ),
```

New columns to insert **after** the `site_allowed` column definition:

```php
// Callback fields (NEW).
array(
    'name'    => 'callback_type',
    'type'    => 'varchar',
    'length'  => '50',
    'null'    => false,
    'default' => 'noop',
),
array(
    'name'       => 'callback_config',
    'type'       => 'longtext',
    'allow_null' => true,
    'default'    => null,
),

// Schema fields (NEW).
array(
    'name'       => 'input_schema',
    'type'       => 'longtext',
    'allow_null' => true,
    'default'    => null,
),
array(
    'name'       => 'output_schema',
    'type'       => 'longtext',
    'allow_null' => true,
    'default'    => null,
),
```

**Acceptance Criteria**:
- `$columns` array has exactly 24 entries
- `source` default is `'db'`
- `status` sortable column with default `'draft'`
- All 4 JSON field columns (`callback_config`, `input_schema`, `output_schema`, `mcp_servers`) defined as longtext/allow_null

---

### File 3: `AcrossAI_Sitewide_Row.php`

**Purpose**: BerlinDB Row subclass — represents one ability record; casts types on read.

**Changes**:

**A) Update class docblock `@property` list** — add 9 new `@property` entries.

**B) Add 8 new public property declarations**:

```php
/** Display name. @var string|null */
public $label = null;

/** Full description. @var string|null */
public $description = null;

/** Organizational category. @var string|null */
public $category = null;

/** Lifecycle status (draft|publish). @var string */
public $status = 'draft';

/** Callback type (noop|filter_hook|wp_remote_post). @var string */
public $callback_type = 'noop';

/** Decoded callback configuration. @var array|null */
public $callback_config = null;

/** Decoded input JSON Schema Draft 7. @var array|null */
public $input_schema = null;

/** Decoded output JSON Schema Draft 7. @var array|null */
public $output_schema = null;
```

**C) Add `get_json_fields()` static method** (filterable registry — FR-009):

```php
/**
 * Filterable list of fields stored as JSON in the database (FR-009).
 *
 * Both Row::__construct() and Query::save_override() use this single registry.
 * Extend via the acrossai_abilities_json_fields filter; only blocked scalar
 * column names are rejected (F1 guard — see JSON Field Registry section).
 *
 * @since  0.1.0
 * @return string[]
 */
public static function get_json_fields(): array {
    $blocked_scalar_columns = array(
        'id', 'ability_slug', 'label', 'description', 'category', 'status',
        'provider', 'source', 'site_allowed', 'callback_type',
        'show_in_rest', 'show_in_mcp', 'mcp_type', 'readonly', 'destructive',
        'idempotent', 'created_at', 'updated_at', 'created_by', 'updated_by',
    );

    $fields = apply_filters(
        'acrossai_abilities_json_fields',
        array( 'mcp_servers', 'callback_config', 'input_schema', 'output_schema' )
    );

    return array_values(
        array_filter(
            (array) $fields,
            static function ( $field ) use ( $blocked_scalar_columns ) {
                return ! in_array( $field, $blocked_scalar_columns, true );
            }
        )
    );
}
```

**D) Update `__construct()`** — replace hardcoded `mcp_servers` decode block with registry loop:

```php
public function __construct( $item ) {
    parent::__construct( $item );

    // Cast tinyint columns using the shared sanitizer utility (RF-02).
    $tri_state_fields = array( 'site_allowed', 'readonly', 'destructive', 'idempotent', 'show_in_rest', 'show_in_mcp' );
    foreach ( $tri_state_fields as $field ) {
        $this->{$field} = AcrossAI_Sanitizer::cast_tri_state( $this->{$field} );
    }

    // Decode JSON-encoded fields using the single filterable registry (FR-009).
    // Returns array|null — malformed JSON or non-array value yields null (FR-008).
    foreach ( self::get_json_fields() as $json_field ) {
        if ( null !== $this->{$json_field} && isset( $this->{$json_field} ) ) {
            $decoded             = json_decode( $this->{$json_field}, true );
            $this->{$json_field} = is_array( $decoded ) ? $decoded : null;
        }
    }

    // Cast integer fields.
    $this->id         = (int) $this->id;
    $this->created_by = null !== $this->created_by ? (int) $this->created_by : null;
    $this->updated_by = null !== $this->updated_by ? (int) $this->updated_by : null;
}
```

**Acceptance Criteria**:
- 24 public property declarations (existing 16 + 8 new)
- `get_json_fields()` is `public static`; applies `acrossai_abilities_json_fields` filter
- `__construct()` decodes all 4 JSON fields from registry (not just `mcp_servers`)
- Tri-state cast block unchanged
- Integer cast block unchanged

---

### File 4: `AcrossAI_Sitewide_Query.php`

**Purpose**: BerlinDB Query subclass — all CRUD operations + query methods.

**Changes**:

**A) Rename `$table_name`**:

```diff
- protected $table_name = 'acrossai_abilities_overwrite';
+ protected $table_name = 'acrossai_abilities';
```

**B) Replace the `mcp_servers` hardcoded JSON encode block in `save_override()` with registry loop** (FR-009, FR-010, FR-018):

**REMOVE** this block:
```php
// JSON-encode mcp_servers before passing to BerlinDB — the column is longtext and
// BerlinDB does NOT auto-encode PHP arrays. Without this the DB receives "[Array]"
// instead of valid JSON, and mcp_servers would be lost on read.
if ( isset( $fields['mcp_servers'] ) && is_array( $fields['mcp_servers'] ) ) {
    $fields['mcp_servers'] = wp_json_encode( $fields['mcp_servers'] );
}
```

**REPLACE WITH** (registry-driven loop — FR-010 failure path included):
```php
// JSON-encode all array-valued JSON fields before passing to BerlinDB (FR-009/FR-010).
// Uses the single filterable registry from AcrossAI_Sitewide_Row::get_json_fields().
// If wp_json_encode() fails for a field, that field is stored as null; save continues.
foreach ( AcrossAI_Sitewide_Row::get_json_fields() as $json_field ) {
    if ( isset( $fields[ $json_field ] ) && is_array( $fields[ $json_field ] ) ) {
        $encoded               = wp_json_encode( $fields[ $json_field ] );
        $fields[ $json_field ] = false !== $encoded ? $encoded : null;
    }
}
```

**KEEP UNCHANGED** (FR-018) — all 3 of these existing blocks:
1. Tri-state bool-to-int cast block (`$tri_state_columns` foreach)
2. `mcp_type` value validation guard (`if ( array_key_exists( 'mcp_type', $fields ) ... )`)
3. `mcp_servers` non-string guard (`if ( array_key_exists( 'mcp_servers', $fields ) ... )`)

**ADD** (F2 security guards — extend the existing `mcp_type` guard pattern to new enum fields):

```php
// F2: Guard status field — only allow known lifecycle values.
// Prevents invalid long strings that would exceed the varchar(20) constraint.
if ( array_key_exists( 'status', $fields ) && null !== $fields['status'] ) {
    $allowed_statuses = array( 'draft', 'publish' );
    if ( ! in_array( $fields['status'], $allowed_statuses, true ) ) {
        unset( $fields['status'] );
    }
}

// F2: Guard callback_type field — only allow known callback types.
if ( array_key_exists( 'callback_type', $fields ) && null !== $fields['callback_type'] ) {
    $allowed_callback_types = array( 'noop', 'filter_hook', 'wp_remote_post', 'php_code' );
    if ( ! in_array( $fields['callback_type'], $allowed_callback_types, true ) ) {
        unset( $fields['callback_type'] );
    }
}
```

Insert these two guards **after** the tri-state cast block and **before** the JSON registry loop — following the same pattern as the existing `mcp_type` value guard (FR-018 block 2).

**C) Add `by_source()` method** (FR-011, FR-019 — BUG-BERLINDB-UNLIMITED):

```php
/**
 * Retrieve all ability records matching a given source (FR-019).
 *
 * Method name MUST be by_source() — spec 009 calls it by this exact name.
 * Returns an empty array (not all records) when $source is empty or null (FR-011).
 * Uses number => 0 for an unlimited BerlinDB query (BUG-BERLINDB-UNLIMITED pattern).
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
- `save_override()` JSON loop uses `AcrossAI_Sitewide_Row::get_json_fields()`
- Encoding failure stores `null` (not `false`), save continues
- Tri-state cast, mcp_type guard, mcp_servers non-string guard all unchanged (FR-018)
- `by_source(null)` returns `[]`
- `by_source('')` returns `[]`
- `by_source('db')` returns all rows where `source = 'db'`
- Query uses `'number' => 0` (not `-1`)

---

### File 5: `uninstall.php`

**Purpose**: Cleans up plugin artifacts on uninstall.

**Changes**: Add new table DROP and option delete. Keep existing lines for backward-compat cleanup of old table name.

```php
// Drop the unified abilities table (renamed in feature 008: unified-abilities-table).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}acrossai_abilities`" );

// Remove new BerlinDB db-version option.
delete_option( 'acrossai_abilities_db_version' );
```

Insert these two lines **before** the existing `acrossai_abilities_overwrite` DROP (so new name is cleaned first, then old name).

Existing lines remain:
```php
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}acrossai_abilities_overwrite`" );
delete_option( 'acrossai_abilities_overwrite_db_version' );
```

**Acceptance Criteria**:
- Both `acrossai_abilities` and `acrossai_abilities_overwrite` DROP statements present
- Both `acrossai_abilities_db_version` and `acrossai_abilities_overwrite_db_version` delete_option calls present
- `WP_UNINSTALL_PLUGIN` guard unchanged
- PHPCS: zero errors

---

## Task Breakdown

```
T001  AcrossAI_Sitewide_Table.php   — rename $name, $db_version_key; expand set_schema() 24 cols + 5 indexes
T002  AcrossAI_Sitewide_Schema.php  — add 8 column definitions; update source default to 'db'
T003  AcrossAI_Sitewide_Row.php     — add 8 properties + get_json_fields() + update __construct()
T004  AcrossAI_Sitewide_Query.php   — rename $table_name; registry loop in save_override(); by_source() (FR-019)
T005  uninstall.php                  — add DROP + delete_option for acrossai_abilities
```

**Critical-path order**: T001 → T002 → T003 → T004 → T005
- T002 depends on T001 column list (both must define 24 columns consistently)
- T003 must precede T004 (Query calls `AcrossAI_Sitewide_Row::get_json_fields()`)
- T005 is independent; can run in parallel with T001–T004

---

## Success Criteria Mapping

| SC | Criterion | Covered By |
|----|-----------|-----------|
| SC-001 | 24 columns on fresh install | T001 (set_schema), T002 (Schema) |
| SC-002 | Existing tests pass without modification | FR-015 compliance in all 4 DB files |
| SC-003 | 24-field round-trip without data loss | T002 (Schema) + T003 (Row) |
| SC-004 | by-source query: exact match, zero false +/- | T004 (by_source) |
| SC-005 | New JSON field via filter auto-decoded/encoded | T003 (get_json_fields filter) + T004 (registry loop) |
| SC-006 | Uninstall leaves no trace | T005 (uninstall.php) |

---

## Implementation Watchpoints

1. **FR-017**: `$version = '1.0.0'` — never increment. BerlinDB uses this for upgrade detection; bumping would trigger `maybe_upgrade()` which re-runs `set_schema()` DDL on existing sites.
2. **FR-018 three-block rule**: In `save_override()`, only remove the old `mcp_servers` encode block and add the registry loop. Do not touch tri-state cast, mcp_type guard, or non-string `mcp_servers` guard.
3. **BUG-BERLINDB-UNLIMITED**: `by_source()` must pass `'number' => 0` — `absint(-1) = 1` silently limits to 1 row.
4. **JSON registry placement**: `get_json_fields()` lives on `AcrossAI_Sitewide_Row` (not Query, not a new file). Query calls it as `AcrossAI_Sitewide_Row::get_json_fields()`.
5. **UNIQUE KEY**: The old schema had `KEY ability_slug` (non-unique). The new schema specifies `UNIQUE KEY ability_slug`. BerlinDB `maybe_upgrade()` handles DDL changes when schema SQL changes, but since `$version` must NOT be bumped (FR-017), the UNIQUE constraint applies only to new installs.
