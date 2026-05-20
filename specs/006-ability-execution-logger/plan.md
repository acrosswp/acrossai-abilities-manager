# Implementation Plan: Ability Execution Logger

**Branch**: `006-ability-execution-logger` | **Date**: 2026-05-19 | **Spec**: [spec.md](spec.md)  
**Input**: Feature 006 specification (4 user stories, 10 FR, 6 SC) + Memory Synthesis (3 decisions, 2 arch constraints, 2 security constraints, 1 pattern)

## Summary

Feature 006 builds a comprehensive execution logger for all WordPress abilities. The logger captures 10 fields per execution (slug, source, server_id, user_id, input, output, status, duration_ms, created_at) and stores logs in a new BerlinDB table `acrossai_ability_logs` with per-site isolation. Logs are accessible via:

1. **REST Endpoint** (`GET /wp-json/acrossai-abilities/v1/logger/logs`) with filtering (status, source, slug, user_id), sorting (any column), pagination (20 records/page default), and search (slug match)
2. **Admin UI** (new "Logs" tab in Abilities Manager admin page) using @wordpress/dataviews for sortable, filterable table display
3. **Automatic Capture** via 4 hook points: `mcp_adapter_pre_tool_call` (stash server_id), `wp_before_execute_ability` (start pending), `wp_after_execute_ability` (pop and write to DB), `wp_register_ability_args` P100001 (wrap permission_callback for permission_denied logging)
4. **Daily Auto-Cleanup** using Action Scheduler (Composer dependency) to prune logs older than 30 days (configurable via `acrossai_ability_log_retention_days` filter)

**Technical Approach**: 
- New module `includes/Modules/Logger/` (non-feature-specific, utilities layer)
- Database layer: `includes/Modules/Logger/Database/` (BerlinDB table, query, schema, row classes)
- Logger class: `AcrossAI_Ability_Logger` singleton with stack-based pending entry management
- REST controller split: `AcrossAI_Logger_Controller` (orchestrator) + `AcrossAI_Logger_Logs_Controller` (logs read-only endpoint)
- Admin UI: Dedicated "Logs" submenu page via `admin/Partials/LogsMenu.php` + REST-powered React component using @wordpress/dataviews

---

## Technical Context

**Language/Version**: PHP 7.4+, WordPress 6.9+  
**Primary Dependencies**: 
- BerlinDB (already in use for `acrossai_abilities_overwrite` table)
- Action Scheduler (Composer: `woocommerce/action-scheduler`)
- `@wordpress/dataviews` (already installed for admin UI)
- `@wordpress/data` (for client-side REST calls)

**Storage**: BerlinDB custom table `acrossai_ability_logs` (per-site isolation via `$global = false`)  
**Testing**: PHPUnit (blocked pending bootstrap shim — T014), Jest for React component  
**Target Platform**: WordPress admin backend, REST API  
**Performance Goals**: 
- List queries return results in <500ms even with 100K+ records (SC-002)
- Pagination headers reflect filtered results (X-WP-Total, X-WP-TotalPages)
- No execution overhead >10ms per ability call (logging is async relative to ability execution)

**Constraints**: 
- Multisite per-site table isolation required (SEC-03 constraint)
- Strict type comparison for any access control checks (SEC-04 constraint)
- Early permission checks before DB lookups (DEC-EARLY-404-REST-CHECK)
- Hooks registered via ARCH-ADV-001 deviation (boot() direct wiring, not Loader)
- Query layer filtering only, no REST controller filtering (AC-QUERY-LAYER-FILTERING)

**Scale/Scope**: 
- 4 user stories (P1/P2 priorities)
- 10 functional requirements
- 6 success criteria
- 6 hook/execution points (mcp, rest, cli, cron, ajax, direct)
- Retention: 30 days (auto-purged), configurable

---

## Constitution Check

✅ **All 7 Constitution principles verified**:

| Principle | Alignment | Notes |
|-----------|-----------|-------|
| **I. Modular Architecture** | ✅ PASS | New standalone `includes/Modules/Logger/` module. Independently testable; non-feature-specific utilities-layer module. No code duplication with Features 003–005. |
| **II. WordPress Standards Compliance** | ✅ PASS | All PHP code WPCS-strict; PHPStan L8 required. No deprecated functions. Multisite-compatible. WP 6.9+ minimum enforced. |
| **III. User-Centric Design** | ✅ PASS | Admin UI uses @wordpress/dataviews (sortable, filterable, searchable table). No custom form/table rendering. DataForm not needed (read-only logs endpoint). |
| **IV. Security First** | ✅ PASS | Input sanitized at REST boundaries (`absint()` for user_id, slug sanitization). Output escaped in React component. Nonce verification on REST endpoints. `manage_options` capability check required (FR-010). Strict type comparison in any access checks (SEC-04). |
| **V. Extensibility Without Core Modification** | ✅ PASS | Logger hook registration via ARCH-ADV-001 deviation (boot() direct add_filter/add_action). No modification to existing module files. Auto-discovery of sources via `wp_doing_cron()`, `wp_doing_ajax()`, etc. (transparent, no external dependency). Extensibility hooks: `acrossai_ability_log_entry` (filter to modify log data before save), `acrossai_ability_log_retention_days` (filter for retention policy). |
| **VI. Reusability & DRY Principle** | ✅ PASS | Source detection logic extracted to `AcrossAI_Logger_Source_Detector` utility (reusable across future features). Query filtering extracted to `AcrossAI_Logger_Query` (DRY with REST controller). @wordpress/dataviews used (Tier 1 package). No duplication of existing utilities. |
| **VII. Definition of Done** | ✅ PASS | All gates applicable: PHPCS zero errors, PHPStan L8 zero errors, ESLint zero errors, security review (sanitization/escaping/nonces/capabilities), unit tests (Logger class, query builder, source detector), DataViews for admin UI, no code duplication, `acrossai_` prefix, AGENTS.md standards met, `npm run validate-packages` pass. |

---

## Project Structure

### Documentation (this feature)

```text
specs/006-ability-execution-logger/
├── plan.md              # This file
├── spec.md              # Feature specification
├── memory-synthesis.md  # Memory context (decisions, constraints, patterns)
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
includes/Modules/Logger/                          # NEW: Logger module (utilities layer)
├── AcrossAI_Ability_Logger.php                   # Logger singleton, hook registration, stack management
├── AcrossAI_Logger_Source_Detector.php           # Utility: detect execution source (mcp, rest, cli, cron, ajax, direct)
├── AcrossAI_Logger_Query.php                     # Query builder: filtering, sorting, pagination, search
├── Database/
│   ├── AcrossAI_Ability_Logs_Table.php          # BerlinDB table schema + registration
│   ├── AcrossAI_Ability_Logs_Schema.php         # Column definitions, indexes
│   ├── AcrossAI_Ability_Logs_Row.php            # Row model (data hydration)
│   └── AcrossAI_Ability_Logs_Query.php          # BerlinDB query wrapper
└── Rest/
    ├── AcrossAI_Logger_Controller.php            # REST orchestrator (namespace, shared permission check)
    └── AcrossAI_Logger_Logs_Controller.php       # REST read-only logs endpoint (GET /logger/logs)

admin/Partials/LogsMenu.php                        # NEW: Logs submenu page (register_submenu, render, get_hook_suffix)
admin/Partials/Menu.php                           # MODIFIED: Logs tab removed — submenu page replaces it

includes/Utilities/AcrossAI_Logger_Formatter.php  # REUSE: Format log entry output (JSON encoding, truncation)
includes/Utilities/AcrossAI_Sanitizer.php         # REUSE: Existing sanitizer for slug validation

tests/phpunit/FeatureLogger/
├── LoggerTest.php                               # Logger class: hook registration, stack management
├── SourceDetectorTest.php                       # Source detection accuracy
├── QueryBuilderTest.php                         # Filtering, sorting, pagination
└── DatabaseTest.php                             # BerlinDB table operations (if bootstrap available)

tests/jest/components/
└── LogsTable.test.js                            # React component: DataViews rendering, filtering

src/js/components/LogsTable.js                   # React component: Admin logs table (uses DataViews)
src/scss/logs-table.scss                         # Styles for logs tab
```

**Structure Decision**: Single module (`Logger/`) housed in `includes/Modules/` for organizational clarity, even though it is utilities-layer (not feature-specific). REST controller split: orchestrator + logs-read-only sub-controller (follows AC-REST-SPLIT pattern from Feature 005). Database layer isolated in `Database/` subdirectory (BerlinDB pattern established in Feature 003).

---

## Phase 0: Research

### Dependencies & Availability Check

1. **BerlinDB** — Already in use (`acrossai_abilities_overwrite` table). Confirms:
   - BerlinDB table registration API is available
   - Per-site table prefix handling is proven (`$global = false`)
   - Query builder API is documented and tested
   - Schema + Row classes pattern is established

2. **Action Scheduler** — Must verify Composer dependency is installed:
   ```bash
   composer require woocommerce/action-scheduler
   ```
   Confirms: `as_schedule_recurring_action()`, `as_unschedule_all_actions()` available globally (not dependent on WooCommerce plugin).

3. **@wordpress/dataviews** — Already in package.json (`npm ls @wordpress/dataviews`). Confirms:
   - DataViews component API available
   - Search, sort, pagination, filtering supported
   - No custom table rendering needed

4. **WordPress Hooks** — All documented in WP 6.9+ core:
   - `wp_before_execute_ability` — fires before ability execution (available)
   - `wp_after_execute_ability` — fires after ability execution (available)
   - `wp_register_ability_args` — fires during ability registration (available)
   - `mcp_adapter_pre_tool_call` — MCP adapter hook (external, confirm availability)

5. **MCP Adapter Hook** — Must verify `mcp_adapter_pre_tool_call` hook is fired before ability execution. Inspect MCP adapter codebase or call `has_filter( 'mcp_adapter_pre_tool_call' )` at runtime to confirm.

---

## Phase 1: Design

### Data Model: ExecutionLog Entity

```php
// Field mapping and types (BerlinDB schema)
[
    'id'              => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
    'ability_slug'    => 'VARCHAR(255) NOT NULL',                  // WP slug, indexed
    'source'          => 'VARCHAR(20) NOT NULL',                  // enum: mcp, rest, cli, cron, ajax, direct
    'mcp_server_id'   => 'VARCHAR(255) DEFAULT NULL',             // nullable, populated when source = mcp
    'user_id'         => 'BIGINT(20) UNSIGNED DEFAULT NULL',      // nullable, 0 or null for non-user contexts
    'input'           => 'LONGTEXT DEFAULT NULL',                 // JSON-encoded, truncated to 65535 bytes
    'output'          => 'LONGTEXT DEFAULT NULL',                 // JSON-encoded (success or error), truncated to 65535 bytes
    'status'          => 'VARCHAR(20) NOT NULL',                  // enum: success, error, permission_denied
    'duration_ms'     => 'INT(11) NOT NULL',                      // milliseconds, can be 0
    'created_at'      => 'DATETIME NOT NULL',                     // timestamp when execution occurred
]

// Indexes for query performance (FR-003, SC-002)
[
    PRIMARY KEY (id),
    INDEX idx_ability_slug_created (ability_slug, created_at),
    INDEX idx_source_created (source, created_at),
    INDEX idx_user_id_created (user_id, created_at),
    INDEX idx_status_created (status, created_at),
]

// Per-site isolation
$global = false  // BerlinDB will prepend site prefix (e.g. wp_logs_1, wp_logs_2 on multisite)
```

### REST Routes

**Base Namespace**: `acrossai-abilities/v1`

| Route | Method | Handler | Permissions | Query Params | Notes |
|-------|--------|---------|-------------|--------------|-------|
| `/logger/logs` | GET | `AcrossAI_Logger_Logs_Controller` | `manage_options` | search, orderby, order, source, status, ability_slug, user_id, page, per_page | List logs with filtering + pagination (AC-QUERY-LAYER-FILTERING) |

**Permission Callback**: Shared `AcrossAI_Logger_Controller::check_permission()` — requires `current_user_can('manage_options')` (FR-010).

**Early Permission Check**: `check_permission()` runs at route registration time (DEC-EARLY-404-REST-CHECK pattern). If user lacks capability, return 403 Forbidden without any logger DB queries.

**Query Params** (all optional, applied in `AcrossAI_Logger_Query::get_logs()`):
- `search` (string) — partial match on `ability_slug` (FR-009)
- `orderby` (string) — column name: `ability_slug`, `source`, `user_id`, `status`, `duration_ms`, `created_at` (FR-007, default: `created_at`)
- `order` (string) — `ASC` or `DESC` (FR-007, default: `DESC`)
- `source` (string, comma-separated) — filter by source(s) e.g. `mcp,rest` (FR-006, SC-004)
- `status` (string, comma-separated) — filter by status(es) e.g. `success,error` (FR-006)
- `ability_slug` (string) — exact match filter (FR-006)
- `user_id` (int) — exact match filter (FR-006)
- `page` (int, default: 1) — pagination page number (FR-008)
- `per_page` (int, default: 20, max: 100) — items per page (FR-008)

**Response Schema**:
```json
{
  "logs": [
    {
      "id": 1,
      "ability_slug": "my-ability",
      "source": "rest",
      "mcp_server_id": null,
      "user_id": 1,
      "input": "{...}",
      "output": "{...}",
      "status": "success",
      "duration_ms": 42,
      "created_at": "2026-05-19T15:30:00"
    }
  ],
  "total": 1500,
  "pages": 75
}
```

**Headers**: `X-WP-Total: 1500`, `X-WP-TotalPages: 75` (pagination headers reflect *filtered* results, not all records — AC-QUERY-LAYER-FILTERING)

### Admin UI: Logs Submenu Page

**Component**: React component using `@wordpress/dataviews` DataViews export (not a separate `@wordpress/dataforms` package — Constitution §III clarification).

**Structure**:
- Dedicated submenu page `admin/Partials/LogsMenu.php` at `admin.php?page=acrossai-abilities-logs`
- Registered under parent menu `acrossai-abilities-manager` via `add_submenu_page()`
- Hook suffix stored in `LogsMenu::$hook_suffix` for conditional asset enqueuing in `Admin\Main`
- Mounts React component on `#acrossai-logs-container` div
- DataViews `fields` prop: ability_slug, source, user_id, status, duration_ms, created_at
- View state managed via React `useState` (controlled component)
- Supports: search (slug), sort (any field), filter (status, source), pagination

**Fields** (all visible by default):
- **Ability Slug** (searchable, sortable, primary sort key)
- **Source** (sortable, filterable: mcp, rest, cli, cron, ajax, direct)
- **User ID** (sortable)
- **Status** (sortable, filterable: success, error, permission_denied)
- **Duration (ms)** (sortable, numeric)
- **Timestamp** (sortable, formatted as locale date/time, default sort DESC)

**Build**: webpack entry `js/logger` → `build/js/logger.js` / `build/js/logger.asset.php`; CSS entry `css/logger` → `build/css/logger.css`

**Behaviors** (per user stories):
- US1: Admins can sort/filter/search to audit execution history ✅ (DataViews sort, filter, search)
- US3: Filter by source to troubleshoot integrations ✅ (source dropdown filter)
- US4: Sort by duration descending to identify slow executions ✅ (duration column sort DESC)

### Hook Registration (ARCH-ADV-001 Pattern)

**Boot-time Hook Registration** (in `AcrossAI_Ability_Logger::boot()`):
```php
// P5: Stash MCP server ID for later use
add_filter( 'mcp_adapter_pre_tool_call', [ $this, 'capture_mcp_server_id' ], 5 );

// P10: Start pending entry (before execution)
add_action( 'wp_before_execute_ability', [ $this, 'start_pending_entry' ], 10, 2 );

// P10: Pop pending entry and write to DB (after execution)
add_action( 'wp_after_execute_ability', [ $this, 'finish_pending_entry' ], 10, 3 );

// P100001: Wrap permission_callback to log permission_denied (at registration time)
add_filter( 'wp_register_ability_args', [ $this, 'wrap_permission_callback' ], 100001, 2 );
```

**Main.php Wiring** (via Loader):
```php
$logger = AcrossAI_Ability_Logger::instance();
$this->loader->add_action( 'plugins_loaded', $logger, 'boot', 20 );
```

### Hook Integration Points

| Hook | Priority | Handler | Purpose | Inputs | Outputs |
|------|----------|---------|---------|--------|---------|
| `mcp_adapter_pre_tool_call` | 5 | `capture_mcp_server_id()` | Stash MCP server ID. Hook signature: `($args, $tool_name, $mcp_tool, $server)` — server ID extracted via `$server->get_server_id()`. Accepts 4 args. Returns `$args` unchanged. | args, tool_name, mcp_tool, server | `$args` unchanged |
| `wp_before_execute_ability` | 10 | `start_pending_entry()` | Start a pending log entry, record `microtime(true)` start time and source. Hook: `($name, $input)` | ability_slug, input | (none — push to stack) |
| `wp_after_execute_ability` | 10 | `finish_pending_entry()` | Pop pending entry, calculate duration from `start_time`, record output/status, write to DB. Hook: `($name, $input, $result)` — WP core does NOT pass execution time, duration is self-calculated. | ability_slug, input, result | (none — insert row) |
| `wp_register_ability_args` | 100001 | `wrap_permission_callback()` | Wrap the permission_callback to log permission denials. Wrapper uses `function( ...$cb_args )` + `call_user_func_array()` to forward all arguments to the original callback unchanged. | ability_slug, args | modified `$args` with wrapped callback |

**Execution Flow**:
1. MCP call (if applicable): `mcp_adapter_pre_tool_call` fires → capture `server_id` → stash in instance var
2. Ability registration: `wp_register_ability_args` P100001 fires → wrap permission_callback with permission_denied logging
3. Ability execution:
   - `wp_before_execute_ability` → create pending entry (start timer)
   - Execute ability (may fail, may be permission denied)
   - `wp_after_execute_ability` → pop pending, record result, insert to DB

### Extensibility Hooks (§V)

Three WordPress filters exposed for third-party extensions:

1. **`acrossai_ability_log_entry`** (filter) — Allows plugins to modify log data before DB insert
   ```php
   apply_filters( 'acrossai_ability_log_entry', $entry, $ability_slug, $source );
   // $entry is an array of all 10 fields; return modified array
   // Use case: mask sensitive data in input/output, add custom fields
   ```

2. **`acrossai_ability_log_retention_days`** (filter) — Allows admins to customize log retention policy
   ```php
   apply_filters( 'acrossai_ability_log_retention_days', 30 );
   // Returns number of days to retain logs; default 30
   // Use case: GDPR compliance, enterprise retention policies
   ```

3. **`acrossai_ability_logger_source_map`** (filter) — Allows plugins to add custom execution sources
   ```php
   apply_filters( 'acrossai_ability_logger_source_map', [ 'mcp' => 'MCP Adapter', ... ] );
   // Returns associative array of source => label; extended at runtime
   // Use case: third-party integrations can register custom sources
   ```

---

## Phase 2: Implementation (High-Level Task Breakdown)

### Task Group A: Database Layer Setup

**T001**: Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Schema.php` — Define column types, indexes, constraints per data model.

**T002**: Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Row.php` — BerlinDB Row class for single log entry hydration + output formatting.

**T003**: Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php` — BerlinDB query wrapper (CRUD, bulk operations, cleanup).

**T004**: Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php` — BerlinDB table registration, `$global = false`, schema binding, indexes. Register table in `plugins_loaded` hook.

### Task Group B: Logger Core & Utilities

**T005**: Create `includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php` — Utility static methods: `detect_source()`, `detect_mcp_server_id()`, `is_mcp_context()`, `is_rest_context()`, `is_cli_context()`, `is_cron_context()`, `is_ajax_context()`.

**T006**: Create `includes/Modules/Logger/AcrossAI_Ability_Logger.php` — Singleton logger class:
- `instance()` + `__construct()` per plugin pattern (ARCH-ADV-001, AC-HOOKS-MAIN)
- `boot()` method: register 4 hooks via direct `add_filter`/`add_action` (ARCH-ADV-001 deviation)
- Stack-based pending entry management (`$this->pending_entries` array)
- `capture_mcp_server_id()` hook handler
- `start_pending_entry()` hook handler
- `finish_pending_entry()` hook handler — calls `apply_filters( 'acrossai_ability_log_entry' )` before insert (extensibility)
- `wrap_permission_callback()` hook handler — injects permission_denied logging wrapper
- Direct DB write via `AcrossAI_Ability_Logs_Query::insert()`

**T007**: Create `includes/Utilities/AcrossAI_Logger_Formatter.php` — Shared utility: `format_log_entry()` (JSON encoding, truncation to 65535 bytes, error handling).

### Task Group C: Query Layer

**T008**: Create `includes/Modules/Logger/AcrossAI_Logger_Query.php` — Query builder (applies all filters + sorting + pagination):
- `get_logs( $args )` — Returns filtered, sorted, paginated log array
- Filter by: source, status, ability_slug, user_id, search (partial slug match)
- Sort by: any column (ability_slug, source, user_id, status, duration_ms, created_at)
- Pagination: page, per_page (default 20, max 100)
- All filtering occurs in query layer (AC-QUERY-LAYER-FILTERING) — return both `logs` and `total` count
- **Important**: Return complete result set with count so REST controller can calculate `X-WP-Total`, `X-WP-TotalPages` headers

### Task Group D: REST Controller

**T009**: Create `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` — REST orchestrator:
- `REST_NAMESPACE = 'acrossai-abilities/v1'`
- `register_routes()` — delegates to `AcrossAI_Logger_Logs_Controller::register_routes()`
- `check_permission()` — requires `current_user_can( 'manage_options' )` (FR-010, early check before DB queries per DEC-EARLY-404-REST-CHECK)

**T010**: Create `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` — REST read-only logs endpoint:
- `register_routes()` — registers `GET /logger/logs` route
- Route args: search, orderby, order, source, status, ability_slug, user_id, page, per_page (all optional)
- Sanitization: `absint()` for page/per_page/user_id, `sanitize_ability_slug()` for slug, `sanitize_text_field()` for search
- Call `AcrossAI_Logger_Query::get_logs( $args )` for filtered, paginated results
- Build response with `logs`, `total`, `pages` keys
- Set headers: `X-WP-Total`, `X-WP-TotalPages`
- Cast return values to expected types (security: prevent type coercion)

### Task Group E: Admin UI (React + PHP)

**T011**: Create `src/js/components/LogsTable.js` — React component:
- Receives REST endpoint URL as prop
- Mounts `<DataViews>` with columns: ability_slug, source, user_id, status, duration_ms, created_at
- Implement search (calls REST with `search` param)
- Implement sort (calls REST with `orderby`, `order` params)
- Implement filter dropdowns: source (mcp, rest, cli, cron, ajax, direct), status (success, error, permission_denied)
- Implement pagination (page, per_page params)
- Display loading state, error state, empty state

**T012**: Create `src/scss/logs-table.scss` — Minimal styles for logs table container.

**T013**: Create `src/js/index.js` — Build entry point that imports `LogsTable.js` and mounts it via `createRoot` on `DOMContentLoaded`. Webpack entry key `js/logger` → outputs `build/js/logger.js` and `build/js/logger.asset.php`. CSS entry `css/logger` → `build/css/logger.css`.

**T014**: Create `admin/Partials/LogsMenu.php` — Dedicated Logs submenu page:
- Singleton pattern (`instance()` / private constructor)
- `register_submenu()` calls `add_submenu_page()` under `acrossai-abilities-manager`; stores returned hook suffix
- `render()` outputs `<div class="wrap">` + `<div id="acrossai-logs-container"></div>`
- `get_hook_suffix()` exposes stored suffix for conditional enqueue in `Admin\Main`
- Wired in `includes/Main.php::define_admin_hooks()` via the Loader

**T015**: Modify `admin/Main.php::enqueue_scripts()` + `enqueue_styles()` — Enqueue `build/js/logger.js` and `build/css/logger.css` conditionally: only when `$hook_suffix === LogsMenu::instance()->get_hook_suffix()`. Asset manifest read from `build/js/logger.asset.php`.

### Task Group F: Action Scheduler Integration

**T016**: Create scheduled cleanup job in `AcrossAI_Ability_Logger::boot()`:
- Use `as_schedule_recurring_action()` to schedule daily cleanup job
- Job fires `'acrossai_ability_logger_cleanup'` action at midnight (or specified time)
- Hook: `add_action( 'acrossai_ability_logger_cleanup', [ $this, 'cleanup_old_logs' ], 10 );`
- `cleanup_old_logs()` calls `AcrossAI_Logger_Query::delete_logs_before_date()` with date 30 days ago
- Allow customization via `apply_filters( 'acrossai_ability_log_retention_days', 30 )`
- On plugin deactivation: `as_unschedule_all_actions( 'acrossai_ability_logger_cleanup' )`

**T017**: Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php::delete_logs_before_date( $date )` — Delete logs older than specified date.

### Task Group G: Hook Wiring (Main.php)

**T018**: Modify `includes/Main.php::define_public_hooks()`:
- Wire logger singleton to `plugins_loaded` P20: `$this->loader->add_action( 'plugins_loaded', $logger, 'boot', 20 );`
- Wire REST controller registration: `$this->loader->add_action( 'rest_api_init', $rest_logger_controller, 'register_routes' );`

### Task Group H: Validation & Testing

**T019**: Create unit tests:
- `tests/phpunit/FeatureLogger/LoggerTest.php` — Logger singleton, hook registration, stack management, pending entry lifecycle
- `tests/phpunit/FeatureLogger/SourceDetectorTest.php` — Source detection for all 6 sources (mcp, rest, cli, cron, ajax, direct)
- `tests/phpunit/FeatureLogger/QueryBuilderTest.php` — Query filtering, sorting, pagination accuracy
- `tests/jest/components/LogsTable.test.js` — React component rendering, search/sort/filter interactions

**T020**: PHPCS validation — `vendor/bin/phpcs includes/Modules/Logger/ admin/Partials/LogsMenu.php includes/Main.php includes/Utilities/AcrossAI_Logger_Formatter.php` (zero errors required)

**T021**: PHPStan validation — `vendor/bin/phpstan analyse includes/Modules/Logger/ admin/Partials/LogsMenu.php includes/Main.php includes/Utilities/AcrossAI_Logger_Formatter.php -l 8` (zero errors required)

**T022**: ESLint validation — `npm run lint src/js/components/LogsTable.js` (zero errors required)

**T023**: Security review — Verify:
- Input sanitization: all REST params sanitized per type
- Output escaping: React component escapes log data
- Nonces: Not applicable (public REST endpoint uses capability check)
- Capabilities: `manage_options` check enforced on all logger endpoints
- Strict comparison: Any array membership checks use `in_array(..., true)` (SEC-04)

**T024**: Package validation — `npm run validate-packages` (ensures no duplicate React/deps)

---

## Complexity Tracking

No Constitution violations identified. All 7 principles align with this feature design.

| Principle | Potential Risk | Mitigation |
|-----------|--------|-----------|
| **Modular Architecture** | Logger isolated properly? | Logger is new utilities-layer module, not feature-specific. Depends only on shared utilities + BerlinDB. No sibling module dependencies. |
| **Standards Compliance** | PHPCS/PHPStan/ESLint pass? | T020–T022 gate before completion. Non-negotiable. |
| **User-Centric Design** | DataViews used correctly? | Admin UI uses only @wordpress/dataviews DataViews export. No custom form/table rendering. Meets UI Contract. |
| **Security First** | Permission checks adequate? | manage_options enforced. Early 404 before DB queries. Sanitization per input type. Escaping at render. T023 security gate. |
| **Extensibility** | Third-party hooks functional? | 3 extensibility hooks (`acrossai_ability_log_entry`, `acrossai_ability_log_retention_days`, `acrossai_ability_logger_source_map`) must fire with `apply_filters()` / `do_action()` verified in code (T019 test coverage). BUG-UNIMPLEMENTED-HOOK pattern prevention. |
| **Reusability & DRY** | Code duplication? | Source detection extracted to utility. Query filtering extracted to query layer (used by REST controller). No duplication of existing utilities. |
| **Definition of Done** | All gates pass? | T020–T024 form the final validation gates. Feature is incomplete until all pass. |

---

## Extensibility & Hook Contract

### Exposed Filters

1. **`acrossai_ability_log_entry`** — `apply_filters( 'acrossai_ability_log_entry', $entry, $ability_slug, $source );`
   - Fired after pending entry is finished but before DB insert
   - Plugins can modify entry (mask data, add fields, etc.)
   - Return value must be a 10-field array (validated in logger)

2. **`acrossai_ability_log_retention_days`** — `apply_filters( 'acrossai_ability_log_retention_days', 30 );`
   - Fired when cleanup job runs
   - Plugins can override retention policy per site
   - Return value must be int (days)

3. **`acrossai_ability_logger_source_map`** — `apply_filters( 'acrossai_ability_logger_source_map', $sources );`
   - Fired when admin UI renders source filter dropdown
   - Plugins can add custom sources
   - Return value must be associative array (source_key => label)

### Internal Actions (debug/monitoring)

- `acrossai_ability_logger_cleanup` — Fired daily by Action Scheduler for log cleanup

---

## Success Criteria Alignment

| Criterion | Implementation | Verification |
|-----------|---|---|
| **SC-001**: Every execution generates exactly one log record within 100ms of completion | Stack-based pending entry with immediate DB insert on `wp_after_execute_ability` (P10). No queueing delay. | T019 test: verify insert timestamp vs execution completion time |
| **SC-002**: Supports 100K+ records; list queries return results in <500ms | BerlinDB indexes on (ability_slug, created_at), (source, created_at), (user_id, created_at), (status, created_at). Query builder uses WHERE + LIMIT. No N+1 queries. | T019 test: benchmark query with 100K mock records; assert <500ms |
| **SC-003**: Admin can sort, filter, search logs; results update in <1 second | React component calls REST endpoint with query params. DataViews handles pagination + sorting client-side. Query layer returns filtered results. | T019 test + manual admin UI testing |
| **SC-004**: Logs UI fully functional for all 6 source types | Source detection utility covers all 6 sources. Admin UI source filter dropdown includes all 6. | T019 test: trigger abilities from each source; verify log records created with correct source |
| **SC-005**: Log retention is transparent; 0% unlogged executions | Hook registration at P10 (standard, catches all executions). Permission_denied logging via P100001 wrapper. Manual audit: grep for all ability execution sites; verify all go through hooks. | T019 test: trigger abilities from all known execution paths; verify all logged |
| **SC-006**: Permission checks prevent non-admin users from viewing logs | manage_options check in `check_permission()`. Early return 403 if user lacks capability. | T019 test: verify non-admin request returns 403 Forbidden |

---

## Technical Notes

### Why Action Scheduler?

**Decision**: Use Action Scheduler (Composer dependency) for daily log cleanup instead of WP-Cron.

**Rationale**:
- WP-Cron depends on site traffic — may not fire on low-traffic sites
- Action Scheduler is more reliable (external process-aware)
- Already available via WooCommerce Composer package
- Consistent with enterprise WordPress practices
- Better observability: can be queried and monitored

### BerlinDB Unlimited Query Bug Prevention

**Pattern**: BerlinDB's `absint()` converts `-1` to `1` (incorrect LIMIT). Always use `0` for unlimited.
```php
// CORRECT
$query->set( 'number', 0 );  // No LIMIT clause

// WRONG
$query->set( 'number', -1 );  // Becomes LIMIT 1 after absint
```
Reference: BUG-BERLINDB-UNLIMITED in durable memory.

### Multisite Table Isolation

**Pattern**: BerlinDB automatically prepends site prefix when `$global = false`.
- Single site: `wp_acrossai_ability_logs`
- Multisite Site 1: `wp_acrossai_ability_logs_1`
- Multisite Site 2: `wp_acrossai_ability_logs_2`

Reference: SEC-03 constraint in memory.

### Partial Hook Fields Bug Prevention

**Pattern**: After partial writes (e.g., toggle or bulk), fetch complete row before firing `after_save` hook.
```php
// CORRECT
$result = save_override( ['status' => 'active'] );
$full_entry = get_override_by_slug( $slug );  // Fetch full row
do_action( 'acrossai_abilities_sitewide_after_save', $full_entry, $slug );

// WRONG
do_action( 'acrossai_abilities_sitewide_after_save', ['status' => 'active'], $slug );  // Incomplete
```
Reference: BUG-PARTIAL-HOOK-FIELDS in durable memory. Not directly applicable to Feature 006 (logs are read-only) but documented for awareness.

### Type Coercion Security

**Pattern**: Use strict comparison in any access control checks.
```php
// CORRECT
if ( in_array( $slug, $protected_slugs, true ) ) { /* strict */ }

// WRONG
if ( in_array( $slug, $protected_slugs ) ) { /* loose comparison */ }
```
Reference: SEC-04 constraint in memory. Apply to any REST filtering by access control logic.

---

## Next Steps

1. **Pre-implementation**: Verify Action Scheduler is installed (`composer show woocommerce/action-scheduler`). Verify MCP adapter exports `mcp_adapter_pre_tool_call` hook.

2. **Task Sequencing**: Database (T001–T004) → Utilities & Logger Core (T005–T007) → Query Layer (T008) → REST Controller (T009–T010) → Admin UI (T011–T015) → Action Scheduler (T016–T017) → Main.php Wiring (T018) → Validation (T019–T024).

3. **Definition of Done**: All gates must pass (PHPCS, PHPStan, ESLint, security review, unit tests, package validation). Feature is incomplete without passing all gates (Constitution §VII).

4. **Memory Capture**: After implementation, update `docs/memory/WORKLOG.md` with lessons learned (decisions made, architecture patterns applied, edge cases encountered).

