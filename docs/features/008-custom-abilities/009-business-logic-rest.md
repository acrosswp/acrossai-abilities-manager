# Spec 009 — Abilities Business Logic + REST

**Branch**: `009-abilities-business-logic-rest`
**Depends on**: Spec 008 (`wp_acrossai_abilities` table must exist with 24 columns)
**Blocks**: Spec 010

---

## What this spec does

- `AcrossAI_Abilities_Processor` — registers `source=db, status=publish` abilities at `wp_abilities_api_init`
- `AcrossAI_Abilities_Query` — new BerlinDB Query class; sole DB entry point for all Abilities module reads/writes; reuses `AcrossAI_Sitewide_Row::get_json_fields()` for JSON encode/decode
- `AcrossAI_Abilities_Validator` — validates all fields including `php_code` syntax check
- `AcrossAI_Abilities_Sanitizer` — sanitizes and casts all fields for DB storage
- `AcrossAI_Abilities_Formatter` — formats DB rows for REST responses
- 5 REST controllers under `/wp-json/acrossai-abilities-manager/v1/abilities`
- `GET /ability-categories` endpoint returning registered WP ability categories
- Wires Processor + REST orchestrator into `includes/Main.php`

---

## New file: `AcrossAI_Abilities_Query`

```
includes/Modules/Abilities/Database/
└── AcrossAI_Abilities_Query.php   — BerlinDB Query: singleton
                                     $table_name   = 'acrossai_abilities'
                                     $table_schema = AcrossAI_Sitewide_Schema::class
                                     $item_shape   = AcrossAI_Sitewide_Row::class
```

**CRUD methods**: `insert_ability()`, `get_ability_by_id()`, `update_ability()`, `delete_ability()`, `slug_exists()`

**Filter methods** (each returns `AcrossAI_Sitewide_Row[]`):
`by_source()`, `published_only()`, `search()`, `with_pagination()`, `order_by()`

**JSON encode/decode**: uses `AcrossAI_Sitewide_Row::get_json_fields()` — the same filter-driven list as the Sitewide_Query. No duplication.

```php
// Example — encode JSON fields before insert/update
foreach ( AcrossAI_Sitewide_Row::get_json_fields() as $field ) {
    if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
        $data[ $field ] = wp_json_encode( $data[ $field ] );
    }
}
```

---

## Processor: `AcrossAI_Abilities_Processor`

Hooks into `wp_abilities_api_init` at priority 10.

**Registration filter**: `source = 'db' AND status = 'publish'`

For each qualifying row, calls `wp_register_ability()` with:
- `label` — from row
- `description` — from row
- `category` — from row (required by WP Abilities API)
- `execute_callback` — closure built by `build_execute_callback()` based on `callback_type`
- `permission_callback` — `'__return_true'` unless overridden

### Callback execution per `callback_type`

| `callback_type` | Execution |
|---|---|
| `noop` | Returns `[]` immediately |
| `filter_hook` | `apply_filters('acrossai_ability_execute_{hook_name}', [], $input)` |
| `wp_remote_post` | HTTP POST `$input` as JSON body; returns decoded response |
| `php_code` | Wrapped closure: `$fn = function($input) { <code> }; return $fn($input);` |

### `php_code` details

- **`callback_config.code`**: raw PHP — no `<?php` tag. Receives `$input` and must `return` the result.
- **Execution**: wrapped in an isolated closure
- **Access**: `manage_options` only — same trust level as editing `functions.php`
- **Size limit**: 64 KB
- **Blocked functions**: `eval`, `exec`, `system`, `passthru`, `shell_exec`, `popen`, `proc_open`, `file_put_contents`, `unlink`
- **Validation**: `token_get_all()` syntax check + blocked-function scan (enforced in Validator before save)

---

## Slug Convention

- Fixed prefix: `acrossai-abilities/`
- User sends suffix only → Write controller prepends `acrossai-abilities/` before DB insert
- All `source=db` rows must have the `acrossai-abilities/` prefix
- List strips prefix for display; form shows prefix read-only

---

## Instant Save (Sparse Update)

There is **no save button** for editing an existing ability. Every field change triggers an immediate `POST /abilities/{id}` with only the changed field(s) in the request body. The Write controller calls `AcrossAI_Abilities_Query::update_ability($id, $fields)` which calls BerlinDB's `update_item()` — only the passed columns are written; untouched columns keep their current DB value.

**Exception**: the **Publish** button for new `source=db` abilities. New abilities are auto-saved as `status='draft'`. Clicking Publish sends `POST /abilities/{id}` with `{"status": "publish"}` — a single-field sparse update.

---

## REST Layer

**Namespace**: `acrossai-abilities-manager/v1`
**Permission**: all endpoints require `manage_options`

```
includes/Modules/Abilities/Rest/
├── AcrossAI_Abilities_Rest_Controller.php      — Orchestrator: singleton, register_routes(), check_permission()
├── AcrossAI_Abilities_Read_Controller.php      — GET /abilities (list + search/filter/pagination)
│                                                  GET /abilities/{id}
├── AcrossAI_Abilities_Write_Controller.php     — POST /abilities (create — slug prefix injected)
│                                                  POST /abilities/{id} (update — sparse, source≠db strips identity fields)
│                                                  DELETE /abilities/{id} (204)
├── AcrossAI_Abilities_Exposure_Controller.php  — GET /abilities/exposures/{type}
│                                                  type = tools | resources | prompts (admin-only, PD-001)
└── AcrossAI_Abilities_Category_Controller.php  — GET /abilities/categories → wp_get_ability_categories()
```

### Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/acrossai-abilities-manager/v1/abilities` | Paginated list with search/filter/sort |
| GET | `/acrossai-abilities-manager/v1/abilities/{id}` | Single ability by ID |
| POST | `/acrossai-abilities-manager/v1/abilities` | Create (slug prefix injected; starts as draft) |
| POST | `/acrossai-abilities-manager/v1/abilities/{id}` | Sparse update (only passed fields written) |
| DELETE | `/acrossai-abilities-manager/v1/abilities/{id}` | Delete (204) |
| GET | `/acrossai-abilities-manager/v1/abilities/exposures/{type}` | Exposure collection by type (tools/resources/prompts) |
| GET | `/acrossai-abilities-manager/v1/abilities/categories` | `wp_get_ability_categories()` |

### source≠db update rules

For `source=plugin/theme/core` rows, the Write controller strips identity fields from the update payload before saving:
- **Stripped** (read-only from WP registry): `label`, `description`, `category`, `callback_type`, `callback_config`, `input_schema`, `output_schema`
- **Allowed** (override fields): `site_allowed`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `readonly`, `destructive`, `idempotent`

---

## Reuse from existing code

| Existing file | Reuse |
|---|---|
| `includes/Utilities/AcrossAI_Sanitizer.php` | Call `sanitize_tri_state()`, `sanitize_mcp_type()`, `sanitize_mcp_servers_array()` |
| `includes/Utilities/AcrossAI_Protected_Abilities.php` | Filter protected slug prefixes in Read controller |
| `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row::get_json_fields()` | JSON field list for encode/decode in AcrossAI_Abilities_Query |
| `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` | BerlinDB Query singleton pattern reference |

---

## Files to Create (9)

```
includes/Modules/Abilities/Database/
└── AcrossAI_Abilities_Query.php

includes/Modules/Abilities/
└── AcrossAI_Abilities_Processor.php

includes/Utilities/
├── AcrossAI_Abilities_Validator.php
├── AcrossAI_Abilities_Sanitizer.php
└── AcrossAI_Abilities_Formatter.php

includes/Modules/Abilities/Rest/
├── AcrossAI_Abilities_Rest_Controller.php
├── AcrossAI_Abilities_Read_Controller.php
├── AcrossAI_Abilities_Write_Controller.php
├── AcrossAI_Abilities_Exposure_Controller.php
└── AcrossAI_Abilities_Category_Controller.php
```

## Files to Modify (1)

| File | Change |
|---|---|
| `includes/Main.php` | Wire `AcrossAI_Abilities_Processor` at `wp_abilities_api_init P10`; wire `AcrossAI_Abilities_Rest_Controller` at `rest_api_init` — **no `admin_menu` hook yet** (that's Spec 010) |

---

## Speckit commands — run in order

### Step 1 — Create feature branch
```
/speckit.git.feature
```
When prompted, enter: `009-abilities-business-logic-rest`

---

### Step 2 — Write the spec
```
/speckit.specify Abilities business logic and REST API: create AcrossAI_Abilities_Query (BerlinDB Query singleton, table_name=acrossai_abilities, table_schema=AcrossAI_Sitewide_Schema, item_shape=AcrossAI_Sitewide_Row, CRUD: insert_ability/get_ability_by_id/update_ability/delete_ability/slug_exists, filters: by_source/published_only/search/with_pagination/order_by, JSON encode/decode via AcrossAI_Sitewide_Row::get_json_fields()). Create AcrossAI_Abilities_Processor that hooks into wp_abilities_api_init priority 10, fetches source=db AND status=publish rows (no enabled flag — publish means always live), and registers each via wp_register_ability() with label/description/category and build_execute_callback() closure (noop=return [], filter_hook=apply_filters, wp_remote_post=HTTP POST JSON, php_code=eval in isolated closure, blocked functions: eval exec system passthru shell_exec popen proc_open file_put_contents unlink). Create Validator (validate_slug, validate_label, validate_category, validate_callback_config all 4 types, validate_php_code token_get_all syntax check + blocked function scan, validate_schema, validate_mcp_type, validate_source, validate_status, validate_ability aggregate). Create Sanitizer (sanitize all fields, sanitize_php_code strips PHP tags, calls AcrossAI_Sanitizer::sanitize_tri_state() and sanitize_mcp_type() and sanitize_mcp_servers_array()). Create Formatter (format_for_response Row to stdClass with ISO 8601 datetimes, format_for_mcp filter show_in_mcp + mcp_type). REST layer: orchestrator singleton + Read (GET /abilities list with search/filter/pagination, GET /abilities/{id}) + Write (POST /abilities create with acrossai-abilities/ slug prefix injection and status=draft default, POST /abilities/{id} sparse update with source!=db identity field stripping, DELETE /abilities/{id} 204) + Mcp (GET /abilities/mcp/tools|resources|prompts) + Categories (GET /ability-categories from wp_get_ability_categories). All endpoints require manage_options. Wire Processor at wp_abilities_api_init P10 and REST orchestrator at rest_api_init in includes/Main.php only. No admin_menu hook.
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
php -l includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php
php -l includes/Modules/Abilities/AcrossAI_Abilities_Processor.php
php -l includes/Utilities/AcrossAI_Abilities_Validator.php
php -l includes/Utilities/AcrossAI_Abilities_Sanitizer.php
php -l includes/Utilities/AcrossAI_Abilities_Formatter.php
php -l includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php
php -l includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php
php -l includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php
php -l includes/Modules/Abilities/Rest/AcrossAI_Abilities_Mcp_Controller.php
php -l includes/Modules/Abilities/Rest/AcrossAI_Abilities_Categories_Controller.php
php -l includes/Main.php

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

### Setup: insert a test ability with status=publish
```bash
wp db query "
INSERT INTO wp_acrossai_abilities
  (ability_slug, label, description, category, status, source, callback_type, show_in_rest)
VALUES
  ('acrossai-abilities/test-noop', 'Test Noop', 'A test ability', 'site', 'publish', 'db', 'noop', 1);
"
# Note: status='publish' required for Processor to register it
```

### 1. Confirm REST routes are registered
```bash
wp eval "do_action('rest_api_init'); \$server = rest_get_server(); print_r(array_filter(array_keys(\$server->get_routes()), function(\$r){ return strpos(\$r, 'abilities') !== false; }));"
# Expected: routes for /acrossai-abilities-manager/v1/abilities and sub-paths
```

### 2. GET ability categories
```bash
wp eval "
\$req = new WP_REST_Request('GET', '/acrossai-abilities-manager/v1/ability-categories');
\$res = rest_do_request(\$req);
echo json_encode(\$res->get_data(), JSON_PRETTY_PRINT);
"
# Expected: JSON array of {slug, label} objects
```

### 3. GET abilities list
```bash
wp eval "
\$req = new WP_REST_Request('GET', '/acrossai-abilities-manager/v1/abilities');
\$req->set_param('per_page', 10);
\$res = rest_do_request(\$req);
echo json_encode(\$res->get_data(), JSON_PRETTY_PRINT);
"
# Expected: array of ability objects including test-noop
```

### 4. POST — create new ability (status defaults to draft)
```bash
wp eval "
\$req = new WP_REST_Request('POST', '/acrossai-abilities-manager/v1/abilities');
\$req->set_body_params([
  'ability_slug'  => 'rest-created',
  'label'         => 'REST Created',
  'description'   => 'Created via REST test',
  'category'      => 'site',
  'source'        => 'db',
  'callback_type' => 'noop',
]);
\$res = rest_do_request(\$req);
echo \$res->get_status() . PHP_EOL;
echo json_encode(\$res->get_data(), JSON_PRETTY_PRINT);
"
# Expected: 201, ability_slug='acrossai-abilities/rest-created', status='draft'
```

### 5. POST — publish ability (sparse update, single field)
```bash
wp eval "
\$row = \$GLOBALS['wpdb']->get_row(\"SELECT id FROM wp_acrossai_abilities WHERE ability_slug = 'acrossai-abilities/rest-created'\");
\$req = new WP_REST_Request('POST', '/acrossai-abilities-manager/v1/abilities/' . \$row->id);
\$req->set_body_params(['status' => 'publish']);
\$res = rest_do_request(\$req);
echo \$res->get_status() . PHP_EOL;
echo json_encode(\$res->get_data(), JSON_PRETTY_PRINT);
"
# Expected: 200, status='publish'
```

### 6. POST — create ability with php_code callback
```bash
wp eval "
\$req = new WP_REST_Request('POST', '/acrossai-abilities-manager/v1/abilities');
\$req->set_body_params([
  'ability_slug'   => 'php-upper',
  'label'          => 'PHP Uppercase',
  'category'       => 'site',
  'source'         => 'db',
  'callback_type'  => 'php_code',
  'callback_config'=> json_encode(['code' => 'return strtoupper(\$input);']),
]);
\$res = rest_do_request(\$req);
echo \$res->get_status() . PHP_EOL;
"
# Expected: 201
```

### 7. POST — blocked function in php_code (security check)
```bash
wp eval "
\$req = new WP_REST_Request('POST', '/acrossai-abilities-manager/v1/abilities');
\$req->set_body_params([
  'ability_slug'   => 'dangerous',
  'label'          => 'Dangerous',
  'category'       => 'site',
  'source'         => 'db',
  'callback_type'  => 'php_code',
  'callback_config'=> json_encode(['code' => 'exec(\"rm -rf /\");']),
]);
\$res = rest_do_request(\$req);
echo \$res->get_status() . PHP_EOL;
"
# Expected: 400 validation error
```

### 8. Confirm Processor registers only published abilities
```bash
wp eval "
do_action('wp_abilities_api_categories_init');
do_action('wp_abilities_api_init');
\$ability = wp_get_ability('acrossai-abilities/test-noop');
echo \$ability ? 'REGISTERED: ' . \$ability->get_label() : 'NOT REGISTERED';
echo PHP_EOL;
\$draft = wp_get_ability('acrossai-abilities/rest-created');
echo \$draft ? 'draft REGISTERED (wrong)' : 'draft NOT REGISTERED (correct)';
"
# Expected: REGISTERED: Test Noop / draft NOT REGISTERED (correct)
```

### 9. Verify slug prefix enforcement
```bash
wp db query "SELECT ability_slug, status FROM wp_acrossai_abilities WHERE source='db';"
# Expected: all source=db rows have the acrossai-abilities/ prefix
```

### 10. Permission check — non-admin user
```bash
wp eval "
wp_set_current_user(0);
\$req = new WP_REST_Request('GET', '/acrossai-abilities-manager/v1/abilities');
\$res = rest_do_request(\$req);
echo \$res->get_status() . PHP_EOL;
"
# Expected: 401 or 403
```

### 11. DELETE ability
```bash
wp eval "
\$row = \$GLOBALS['wpdb']->get_row(\"SELECT id FROM wp_acrossai_abilities WHERE ability_slug = 'acrossai-abilities/rest-created'\");
\$req = new WP_REST_Request('DELETE', '/acrossai-abilities-manager/v1/abilities/' . \$row->id);
\$res = rest_do_request(\$req);
echo \$res->get_status() . PHP_EOL;
"
# Expected: 204
```

### 12. Feature 001 Override Processor still works
```bash
wp eval "
do_action('wp_abilities_api_init');
\$ability = wp_get_ability('core/get-site-info');
echo \$ability ? 'core/get-site-info still registered' : 'BROKEN';
"
# Expected: core/get-site-info still registered
```

---

## What NOT to test yet (spec 010)
- Admin submenu page (not created until spec 010)
- React UI components (not created until spec 010)
- webpack build output (not configured until spec 010)
