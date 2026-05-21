# Features

## Sitewide Ability Management

**Spec**: `specs/001-sitewide-ability-management/` | **Status**: Complete

Lets site administrators view, search, sort, filter, and override the metadata of every ability registered via the WordPress Abilities API (`wp_get_ability()`). Overrides are stored per-site in `{prefix}acrossai_abilities` (column `source != 'db'`) via BerlinDB; the registry is the source of truth — only fields that differ from registry defaults are persisted.

### What admins can do

- **Browse** all registered abilities in a searchable, sortable, paginated DataViews table
- **Toggle** per-ability site-wide allow/disallow with a single click (US2)
- **Edit** ability metadata in a slide-in drawer: readonly, destructive, idempotent, show_in_rest, show_in_mcp, mcp_type, mcp_servers — stored as tri-state overrides (Yes / No / Inherit) (US3)
- **Reset** individual overrides to restore registry defaults (US4)
- **Bulk allow / disallow / reset** up to 50 abilities at once (US5)
- **MCP server list** — the MCP tab in the edit drawer shows all registered MCP servers sourced from the `wpboilerplate/wpb-mcp-servers-list` package

### REST endpoints

All endpoints require `manage_options` + nonce (`wp_rest`).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/acrossai-abilities-manager/v1/sitewide/abilities` | Paginated ability list with merged effective values |
| GET | `/acrossai-abilities-manager/v1/sitewide/abilities/{slug}` | Single ability detail |
| POST | `/acrossai-abilities-manager/v1/sitewide/abilities/{slug}` | Save override fields |
| DELETE | `/acrossai-abilities-manager/v1/sitewide/abilities/{slug}` | Delete override row |
| POST | `/acrossai-abilities-manager/v1/sitewide/abilities/{slug}/toggle` | Toggle site_allowed |
| POST | `/acrossai-abilities-manager/v1/sitewide/abilities/bulk` | Bulk allow/disallow/reset |
| GET | `/acrossai-abilities-manager/v1/sitewide/mcp-servers` | List registered MCP servers |

### Key technical decisions

- **Storage**: BerlinDB custom table (`wp_acrossai_abilities`, `source != 'db'` rows) — override-only, no registry duplication
- **Tri-state fields**: PHP `true` / `false` / `null` map to MySQL `1` / `0` / `NULL` (Inherit)
- **MCP server data**: collected via `wpboilerplate/wpb-mcp-servers-list` at `rest_api_init` priority 20
- **UI**: DataViews table + DataForms slide-in drawer (createPortal)

---

## Custom Abilities — Unified Table

**Spec**: `docs/features/008-custom-abilities/008-unified-table.md` | **Status**: Planned (Spec 008)

Creates the `{prefix}acrossai_abilities` table — a single 24-column BerlinDB table that serves both the Sitewide Ability Management module (override rows, `source != 'db'`) and the upcoming Custom Abilities module (new abilities, `source = 'db'`). Replaces the old `{prefix}acrossai_abilities_overwrite` table (plugin is pre-launch; no migration needed).

### What this spec delivers

- **4 new BerlinDB classes** under `includes/Modules/Abilities/Database/`: Schema, Row, Table, Query
- **Query CRUD**: `insert_ability`, `get_ability_by_id`, `update_ability`, `delete_ability`, `slug_exists`
- **Query chainable filters**: `by_source`, `enabled_only`, `search`, `with_pagination`, `order_by`
- **Updated Sitewide classes** (`AcrossAI_Sitewide_Table/Schema/Row/Query`) pointing at the new table name with all 24 columns
- **Updated `AcrossAI_Activator`** calling `AcrossAI_Abilities_Table::instance()->maybe_upgrade()`

### Table: `{prefix}acrossai_abilities` — 24 columns

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint unsigned PK | |
| `ability_slug` | varchar(255) UNIQUE | `namespace/name` format |
| `label` | varchar(255) null | Display name (source=db only) |
| `description` | longtext null | |
| `category` | varchar(100) null | WP Abilities API category slug |
| `enabled` | tinyint(1) default 1 | Auto-register flag (source=db only) |
| `provider` | varchar(100) null | Plugin/theme identifier |
| `source` | varchar(50) default 'db' | `db` \| `plugin` \| `theme` \| `core` |
| `site_allowed` | tinyint(1) null | NULL=inherit / 1=allow / 0=block |
| `callback_type` | varchar(50) default 'noop' | `noop` \| `filter_hook` \| `wp_remote_post` \| `php_code` |
| `callback_config` | longtext null | JSON: type-specific config |
| `input_schema` | longtext null | JSON Schema Draft 7 |
| `output_schema` | longtext null | JSON Schema Draft 7 |
| `show_in_rest` | tinyint(1) null | NULL=registry default |
| `show_in_mcp` | tinyint(1) null | NULL=registry default |
| `mcp_type` | varchar(50) null | `tool` \| `resource` \| `prompt` |
| `mcp_servers` | longtext null | JSON array; NULL=all servers |
| `readonly` | tinyint(1) null | Tri-state |
| `destructive` | tinyint(1) null | Tri-state |
| `idempotent` | tinyint(1) null | Tri-state |
| `created_at` | datetime | |
| `updated_at` | datetime | ON UPDATE CURRENT_TIMESTAMP |
| `created_by` | bigint unsigned null | |
| `updated_by` | bigint unsigned null | |

---

## Custom Abilities — Business Logic + REST

**Spec**: `docs/features/008-custom-abilities/009-business-logic-rest.md` | **Status**: Planned (Spec 009) | **Depends on**: Spec 008

Wires up the processor that auto-registers `source=db` abilities at `wp_abilities_api_init`, plus full REST CRUD under `/wp-json/acrossai-abilities-manager/v1/abilities`. No admin UI yet (that's Spec 010).

### What this spec delivers

- **`AcrossAI_Abilities_Processor`** — fetches enabled `source=db` rows, builds `execute_callback` closures, calls `wp_register_ability()` for each
- **`AcrossAI_Abilities_Validator`** — validates all fields; `php_code` uses `token_get_all()` syntax check + blocked-functions list
- **`AcrossAI_Abilities_Sanitizer`** — sanitizes and casts all fields for DB storage; calls existing `AcrossAI_Sanitizer` tri-state helpers
- **`AcrossAI_Abilities_Formatter`** — formats DB rows for REST responses (ISO 8601 dates, MCP shape)
- **5 REST controllers** under `includes/Modules/Abilities/Rest/`

### Callback types

| Type | Execution |
|------|-----------|
| `noop` | Returns `[]` immediately |
| `filter_hook` | `apply_filters('acrossai_ability_execute_{hook_name}', [], $input)` |
| `wp_remote_post` | HTTP POST `$input` as JSON body; returns decoded response |
| `php_code` | `eval()` code with `$input` in scope; blocked: `eval exec system passthru shell_exec popen proc_open file_put_contents unlink` |

### Slug convention

- Fixed prefix: `acrossai-abilities/` — Write controller prepends it; user sends suffix only
- All `source=db` rows must have the `acrossai-abilities/` prefix

### REST endpoints

All endpoints require `manage_options`.

| Method | Path | Description |
|--------|------|-------------|
| GET | `/acrossai-abilities-manager/v1/abilities` | Paginated list with search/filter |
| GET | `/acrossai-abilities-manager/v1/abilities/{id}` | Single ability |
| POST | `/acrossai-abilities-manager/v1/abilities` | Create (slug prefix injected) |
| POST | `/acrossai-abilities-manager/v1/abilities/{id}` | Update (identity fields stripped for source≠db) |
| DELETE | `/acrossai-abilities-manager/v1/abilities/{id}` | Delete (204) |
| GET | `/acrossai-abilities-manager/v1/abilities/mcp/tools` | MCP tool abilities |
| GET | `/acrossai-abilities-manager/v1/abilities/mcp/resources` | MCP resource abilities |
| GET | `/acrossai-abilities-manager/v1/abilities/mcp/prompts` | MCP prompt abilities |
| GET | `/acrossai-abilities-manager/v1/ability-categories` | `wp_get_ability_categories()` |

---

## Custom Abilities — React UI + Admin Shell

**Spec**: `docs/features/008-custom-abilities/010-react-ui.md` | **Status**: Planned (Spec 010) | **Depends on**: Spec 009

Admin submenu page + React app for creating, editing, and managing custom abilities. Built after Spec 009 because the page enqueues a webpack bundle that doesn't exist until this spec runs.

### What this spec delivers

- **Admin PHP shell**: `AcrossAI_Abilities_Menu` (submenu), `AcrossAI_Abilities_Page` (mount point), `AcrossAI_Abilities_Assets` (enqueue + `wp_add_inline_script`)
- **React app** at `src/js/abilities/` using `@wordpress/dataviews` (list) + `@wordpress/dataforms` (form)
- **AbilitiesList** — searchable/filterable table with source badge (Custom / Plugin / Theme / Core), enabled toggle, bulk actions
- **AbilityForm Variant A** (source=db) — all fields editable; category `<select>` from REST; `php_code` shows monospace `<textarea>`
- **AbilityForm Variant B** (source≠db) — identity fields (label, description, category, callback) read-only; override fields editable
- **webpack entry** `js/abilities` added to `webpack.config.js`
- **`admin_menu` hook** wired in `includes/Main.php`

### Key technical decisions

- **Asset data**: `window.acrossaiAbilities = { restNamespace, nonce, currentUserId }` via `wp_add_inline_script()` (not `wp_localize_script`)
- **Slug display**: prefix `acrossai-abilities/` shown read-only; user edits suffix only
- **Separate webpack entry**: `build/js/abilities.js` + `build/js/abilities.asset.php` — does not share bundle with main manager assets
