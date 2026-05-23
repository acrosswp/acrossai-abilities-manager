# Spec 008 — Unified Abilities Table

**Branch**: `008-unified-abilities-table`
**Depends on**: nothing (first in the 008-010 series)
**Blocks**: Spec 009

> **Dev-only note**: Plugin has not launched — no backward compatibility or data migration needed.
> Drop the old table manually before running: `wp db query "DROP TABLE IF EXISTS wp_acrossai_abilities_overwrite;"`

---

## What this spec does

- Updates 5 existing files — **no new files created**
- Renames DB table from `acrossai_abilities_overwrite` → `acrossai_abilities` (Sitewide_Table)
- Expands schema from 16 → 24 columns (Sitewide_Schema + Sitewide_Table DDL)
- Adds typed properties + `get_json_fields()` static method + filter-driven JSON decode loop (Sitewide_Row)
- Switches `$table_name` + adds `by_source()` + filter-driven JSON encode loop (Sitewide_Query)
- Updates uninstall.php to drop the new table name

Zero code duplication: the 4 existing Sitewide classes become the unified layer.
`AcrossAI_Abilities_Query` (new in Spec 009) will also call `AcrossAI_Sitewide_Row::get_json_fields()`.

---

## Database Table: `{prefix}acrossai_abilities` — 24 columns

**New table** — created fresh by `AcrossAI_Sitewide_Table::maybe_upgrade()` on plugin (de)activate.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | PK |
| `ability_slug` | varchar(255) | NO | `''` | UNIQUE — `namespace/name` |
| `label` | varchar(255) | YES | NULL | Display name — populated for source=db, NULL for overrides |
| `description` | longtext | YES | NULL | |
| `category` | varchar(100) | YES | NULL | WP Abilities API category slug (required by `wp_register_ability()`) |
| `status` | varchar(20) | NO | `'draft'` | `draft` \| `publish` — `publish` always registers, no separate flag |
| `provider` | varchar(100) | YES | NULL | Plugin/theme slug that originally owns the ability |
| `source` | varchar(50) | YES | `'db'` | `db` \| `plugin` \| `theme` \| `core` |
| `site_allowed` | tinyint(1) | YES | NULL | NULL=registry / 1=force-allow / 0=force-block (source≠db only) |
| `callback_type` | varchar(50) | NO | `'noop'` | `noop` \| `filter_hook` \| `wp_remote_post` \| `php_code` |
| `callback_config` | longtext | YES | NULL | JSON: type-specific config |
| `input_schema` | longtext | YES | NULL | JSON Schema Draft 7 |
| `output_schema` | longtext | YES | NULL | JSON Schema Draft 7 |
| `show_in_rest` | tinyint(1) | YES | NULL | NULL=registry default / 1=yes / 0=no |
| `show_in_mcp` | tinyint(1) | YES | NULL | NULL=registry default / 1=yes / 0=no |
| `mcp_type` | varchar(100) | YES | NULL | `tool` \| `resource` \| `prompt` |
| `mcp_servers` | longtext | YES | NULL | JSON array of server IDs; NULL=all servers |
| `readonly` | tinyint(1) | YES | NULL | Tri-state: NULL=inherit / 0=false / 1=true |
| `destructive` | tinyint(1) | YES | NULL | Tri-state |
| `idempotent` | tinyint(1) | YES | NULL | Tri-state |
| `created_at` | datetime | NO | CURRENT_TIMESTAMP | |
| `updated_at` | datetime | NO | CURRENT_TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP |
| `created_by` | bigint unsigned | YES | NULL | |
| `updated_by` | bigint unsigned | YES | NULL | |

**Indexes**: `PRIMARY KEY (id)`, `UNIQUE KEY ability_slug (ability_slug(191))`, `KEY idx_status (status)`, `KEY idx_source (source)`, `KEY idx_updated_at (updated_at)`

### `status` column semantics

| status | meaning | registered at wp_abilities_api_init? |
|---|---|---|
| `draft` | Saved but incomplete — not live | No |
| `publish` | Complete and active | Yes — always |

- New abilities always start as `draft` — all field changes auto-save instantly as user fills the form
- User explicitly clicks **Publish** button → `status` changes to `'publish'` → ability goes live on next boot
- Override rows (source≠db): default `publish`, status not semantically used
- **No save button for editing existing abilities** — every field change on an existing ability sends `POST /abilities/{id}` with only the changed field. BerlinDB partial update (`update_item($id, $fields)`) means untouched columns are never overwritten.

### Row type semantics

| Column | source=db | source=plugin/theme/core |
|---|---|---|
| `label`, `category`, `description` | Required / editable | Read-only (from WP registry) |
| `status` | `draft`/`publish` — Publish button | N/A (`publish` by default) |
| `callback_type/config`, `input/output_schema` | Editable | Read-only |
| `site_allowed` | N/A | Editable (instant save) |
| `show_in_rest/mcp`, `mcp_type/servers` | Editable (instant save) | Editable (instant save) |
| `readonly`, `destructive`, `idempotent` | Editable (instant save) | Editable (instant save) |

---

## Callback Types

| `callback_type` | `callback_config` shape | Execution |
|---|---|---|
| `noop` | `{}` or NULL | Returns `[]` immediately |
| `filter_hook` | `{"hook_name": "my_hook"}` | `apply_filters('acrossai_ability_execute_{hook_name}', [], $input)` |
| `wp_remote_post` | `{"url": "https://...", "method": "POST", "timeout": 30}` | HTTP POST `$input` as JSON body, returns decoded response |
| `php_code` | `{"code": "return strtoupper($input);"}` | `eval()` the code with `$input` in scope; returns result |

### `php_code` details

- **`callback_config.code`**: raw PHP — no `<?php` tag. Receives `$input` and must `return` the result.
- **Execution**: wrapped in an isolated closure — `$fn = function($input) { <code> }; return $fn($input);`
- **Access**: `manage_options` only — same trust level as editing `functions.php`
- **Size limit**: 64 KB
- **Blocked functions**: `eval`, `exec`, `system`, `passthru`, `shell_exec`, `popen`, `proc_open`, `file_put_contents`, `unlink`
- **Validation**: `token_get_all()` syntax check + blocked-function scan

---

## Slug Convention

- Fixed prefix: `acrossai-abilities/`
- User enters suffix only (e.g. `my-ability`) → stored as `acrossai-abilities/my-ability`
- Write controller prepends prefix; form shows prefix read-only; list strips prefix for display

---

## Architecture (follows plugin AGENTS.md rules)

- **Singleton pattern** on every class
- **Hook wiring** only in `includes/Main.php` via `$this->loader->add_action()`
- **BerlinDB layer**: `AcrossAI_Sitewide_Query` is the only entry point for DB reads/writes — no direct `$wpdb` SQL in any other class
- **ABSPATH guard** on every PHP file

---

## Zero-Duplication Architecture

The 4 existing Sitewide classes are the **single source of truth**. No new Table/Schema/Row classes.
`AcrossAI_Sitewide_Row::get_json_fields()` is called by both `AcrossAI_Sitewide_Query` and (in Spec 009) `AcrossAI_Abilities_Query`.

---

## Files NOT to Touch

| File | Reason |
|---|---|
| `includes/Main.php` | Line 279 `AcrossAI_Sitewide_Table::instance()` stays as-is — class still exists |
| `includes/AcrossAI_Activator.php` | `(new AcrossAI_Sitewide_Table())->maybe_upgrade()` stays as-is |
| `tests/phpunit/sitewide/SitewideQueryTest.php` | No table-name assertions — no change needed |

---

## Files to Modify (5)

### `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php`

- `$name` → `'acrossai_abilities'`
- `$db_version_key` → `'acrossai_abilities_db_version'`
- `$version` stays `'1.0.0'` (pre-launch, fresh install only — do NOT change)
- `set_schema()` → full 24-column DDL + 5 indexes: `PRIMARY KEY (id)`, `UNIQUE KEY ability_slug(191)`, `KEY idx_status (status)`, `KEY idx_source (source)`, `KEY idx_updated_at (updated_at)`

### `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php`

Add 8 new column definitions after the `source` column definition:

| Column | type | length | null | default |
|---|---|---|---|---|
| `label` | varchar | 255 | true | null |
| `description` | longtext | — | true | null |
| `category` | varchar | 100 | true | null |
| `status` | varchar | 20 | false | `'draft'` |
| `callback_type` | varchar | 50 | false | `'noop'` |
| `callback_config` | longtext | — | true | null |
| `input_schema` | longtext | — | true | null |
| `output_schema` | longtext | — | true | null |

`mcp_type` length stays `'100'` — do NOT change.

### `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php`

1. Add 8 new typed properties after `$source`:
   ```php
   public ?string $label         = null;
   public ?string $description   = null;
   public ?string $category      = null;
   public string  $status        = 'draft';
   public string  $callback_type = 'noop';
   public ?string $callback_config = null;
   public ?string $input_schema  = null;
   public ?string $output_schema = null;
   ```

2. Add `public static function get_json_fields(): array` — single source of truth for all JSON-encoded fields:
   ```php
   public static function get_json_fields(): array {
       return apply_filters(
           'acrossai_abilities_json_fields',
           [ 'mcp_servers', 'callback_config', 'input_schema', 'output_schema' ]
       );
   }
   ```

3. Replace hardcoded `mcp_servers` JSON decode in constructor with filter-driven loop:
   ```php
   foreach ( self::get_json_fields() as $field ) {
       if ( property_exists( $this, $field ) && null !== $this->$field ) {
           $decoded      = json_decode( $this->$field, true );
           $this->$field = is_array( $decoded ) ? $decoded : null;
       }
   }
   ```

### `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php`

1. `$table_name` → `'acrossai_abilities'`

2. In `save_override()`: replace ONLY the first `mcp_servers` JSON encode block with a filter-driven loop:
   ```php
   foreach ( AcrossAI_Sitewide_Row::get_json_fields() as $field ) {
       if ( isset( $fields[ $field ] ) && is_array( $fields[ $field ] ) ) {
           $fields[ $field ] = wp_json_encode( $fields[ $field ] );
       }
   }
   ```
   The following 3 blocks MUST remain unchanged:
   - Tri-state bool→int cast for `site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`
   - `mcp_type` value validation guard (blocks invalid values before DB write)
   - `mcp_servers` non-string guard (ensures value is string or null after encoding)

3. Add `by_source()` method:
   ```php
   public function by_source( string $source ): array {
       return $this->query( [ 'source' => $source, 'number' => 0 ] );
   }
   ```

4. All other existing methods (`get_override_by_slug`, `delete_override_by_slug`, `get_all_overrides`) — unchanged.

### `uninstall.php`

- `DROP TABLE IF EXISTS wp_acrossai_abilities_overwrite` → `DROP TABLE IF EXISTS wp_acrossai_abilities`
- `delete_option( 'acrossai_abilities_overwrite_db_version' )` → `delete_option( 'acrossai_abilities_db_version' )`
- Update inline comment: "sitewide ability overrides table" → "unified abilities table"

---

## Speckit commands — run in order

### Step 1 — Create feature branch
```
/speckit.git.feature
```
When prompted, enter: `008-unified-abilities-table`

---

### Step 2 — Write the spec
```
/speckit.specify Unified abilities table: update AcrossAI_Sitewide_Table ($name=acrossai_abilities, $db_version_key=acrossai_abilities_db_version, $version stays 1.0.0, expand set_schema() to 24 columns with 5 indexes — no enabled column). Add 8 new column definitions to AcrossAI_Sitewide_Schema after source: label (varchar/255 null), description (longtext null), category (varchar/100 null), status (varchar/20 not null default draft), callback_type (varchar/50 not null default noop), callback_config (longtext null), input_schema (longtext null), output_schema (longtext null). Keep mcp_type length=100 unchanged. Update AcrossAI_Sitewide_Row: add 8 typed properties after $source (no enabled), add public static get_json_fields() returning apply_filters(acrossai_abilities_json_fields, [mcp_servers, callback_config, input_schema, output_schema]), replace hardcoded mcp_servers JSON decode with filter-driven loop over get_json_fields(). Update AcrossAI_Sitewide_Query: $table_name=acrossai_abilities, in save_override() replace only the first mcp_servers JSON encode block with a filter-driven loop over AcrossAI_Sitewide_Row::get_json_fields() (keep tri-state cast, mcp_type guard, mcp_servers non-string guard unchanged), add by_source(string $source): array method. Update uninstall.php: DROP TABLE wp_acrossai_abilities (not overwrite), delete_option acrossai_abilities_db_version. Do NOT touch includes/Main.php or includes/AcrossAI_Activator.php.
```

---

### Step 3 — Generate plan with memory context
```
/speckit.memory-md.plan-with-memory
```

---

### Step 4 — Architecture + security validation
```
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan
```

---

### Step 5 — Generate tasks
```
/speckit.architecture-guard.governed-tasks
```

---

### Step 6 — Implement
```
/speckit.architecture-guard.governed-implement
```

---

### Step 7 — Quality gates (run after implement)
```bash
php -l includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php
php -l includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Schema.php
php -l includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php
php -l includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php
php -l uninstall.php

composer run phpcs
composer run phpstan
```

---

### Step 8 — Post-implement review
```
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
```

---

### Step 9 — Save memory + commit
```
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual testing

### Before running: drop old table + reactivate the plugin
```bash
# Drop the old table (dev only — no migration needed)
wp db query "DROP TABLE IF EXISTS wp_acrossai_abilities_overwrite;"

# Reactivate to trigger maybe_upgrade()
wp plugin deactivate acrossai-abilities-manager
wp plugin activate acrossai-abilities-manager
```

### 1. Confirm new table exists with 24 columns
```bash
wp db query "DESCRIBE wp_acrossai_abilities;"
# Expected: 24 rows — one per column
# Check these columns exist: id, ability_slug, label, description, category,
# status, provider, source, site_allowed, callback_type, callback_config,
# input_schema, output_schema, show_in_rest, show_in_mcp, mcp_type,
# mcp_servers, readonly, destructive, idempotent,
# created_at, updated_at, created_by, updated_by
```

### 2. Confirm old table is gone
```bash
wp db query "SHOW TABLES LIKE 'wp_acrossai_abilities_overwrite';"
# Expected: Empty set (no rows)
```

### 3. Confirm indexes
```bash
wp db query "SHOW INDEX FROM wp_acrossai_abilities;"
# Expected indexes: PRIMARY, ability_slug (UNIQUE), idx_status, idx_source, idx_updated_at
```

### 4. Confirm db_version_key set
```bash
wp option get acrossai_abilities_db_version
# Expected: version number (e.g. "1")
```

### 5. Confirm get_json_fields() returns correct fields
```bash
wp eval "
use AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\Database\AcrossAI_Sitewide_Row;
\$fields = AcrossAI_Sitewide_Row::get_json_fields();
echo implode(', ', \$fields) . PHP_EOL;
"
# Expected: mcp_servers, callback_config, input_schema, output_schema
```

### 6. Confirm feature 001 sitewide UI still works
- Go to WP Admin → Abilities Manager → Sitewide Abilities
- List should load without errors
- Save a new override → confirm it writes to `wp_acrossai_abilities`

```bash
wp db query "SELECT id, ability_slug, source, site_allowed FROM wp_acrossai_abilities LIMIT 5;"
# Expected: any saved overrides appear here with source=plugin/theme/core
```

### 7. Confirm Feature 004 Override Processor still works
```bash
wp eval "
do_action('wp_abilities_api_init');
\$ability = wp_get_ability('core/get-site-info');
echo \$ability ? 'core/get-site-info still registered' : 'BROKEN';
"
# Expected: core/get-site-info still registered
```

---

## What NOT to test yet (spec 009+)
- REST API endpoints (not created until spec 009)
- Custom ability creation via REST (not wired until spec 009)
- Admin submenu page (not created until spec 010)
