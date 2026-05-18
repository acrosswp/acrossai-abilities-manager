# Tasks: Ability Execution Logger (Feature 006)

**Feature Branch**: `006-ability-execution-logger`  
**Generated**: 2026-05-19  
**Status**: Ready for Phase 2 Implementation  
**Spec**: [spec.md](spec.md) | [Plan](plan.md) | [Memory](memory-synthesis.md)

---

## Overview

This artifact decomposes Feature 006's plan.md into 24 granular implementation tasks organized by 8 phases. Each phase builds on prior phases; strict sequencing ensures independent testability at each milestone.

**Total Tasks**: 24 granular tasks  
**Estimated Duration**: ~48 working hours (Phase A: 2h, Phase B: 5h, Phase C: 4h, Phase D: 5h, Phase E: 12h, Phase F: 3h, Phase G: 2h, Phase H: 10h)  
**MVP Scope**: Phases A–D + Phase G (core logging infrastructure). Phases E–H optional but recommended for production release.

**Key Constraints Applied**:
- ARCH-ADV-001: Boot() direct hook registration allowed (not via Loader)
- AC-HOOKS-MAIN: Main.php wires singleton instances, not inline ::instance()
- AC-QUERY-LAYER-FILTERING: All REST filtering in query builder, not controller
- DEC-EARLY-404-REST-CHECK: Permission checks before DB queries
- SEC-03: Per-site table isolation (BerlinDB $global = false)
- SEC-04: Strict type comparison (in_array(..., true))

---

## Phase A: Database Layer Setup

**Sequencing**: Must complete before Phases B–G (logger depends on schema)

### T001 – Create Ability Logs Schema Definition

- [x] T001 Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Schema.php` schema definition file

**Description**: Define BerlinDB column types, indexes, and constraints for the execution logs table. This schema is consumed by the Table registration class.

**Acceptance Criteria**:
- [ ] File defines all 10 columns: id (BIGINT), ability_slug (VARCHAR 255), source (VARCHAR 20), mcp_server_id (VARCHAR 255 NULL), user_id (BIGINT NULL), input (LONGTEXT NULL), output (LONGTEXT NULL), status (VARCHAR 20), duration_ms (INT), created_at (DATETIME)
- [ ] Column types match BerlinDB conventions and MySQL best practices
- [ ] Four indexes defined: (ability_slug, created_at), (source, created_at), (user_id, created_at), (status, created_at) — for FR-003 query performance (SC-002)
- [ ] Schema is exported as array or method for consumption by T004 Table class
- [ ] No PHP syntax errors; PHPCS compliant

**User Stories / FR Mapping**: 
- FR-003: Capture 10 fields per execution ✅
- FR-004: Store logs in BerlinDB table ✅

**Success Criteria Addressed**: SC-002 (indexes enable <500ms queries)

**Edge Cases Covered**: EC-006 (large table 30d) — indexes prevent full table scans on date range queries

**Time Estimate**: 1 hour

**Dependencies**: None (foundational task)

**Testing**: Verify column types match spec, indexes are created, no errors on schema validation

---

### T002 – Create Ability Logs Row Model

- [x] T002 Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Row.php` BerlinDB Row hydration class

**Description**: Implement BerlinDB Row class for single log entry hydration, serialization, and output formatting. This class maps raw database rows to PHP objects.

**Acceptance Criteria**:
- [ ] Extends BerlinDB `Row` base class
- [ ] Implements `__get()` and `__set()` for all 10 column properties (id, ability_slug, source, mcp_server_id, user_id, input, output, status, duration_ms, created_at)
- [ ] Implements `to_array()` method returning all properties as flat array
- [ ] Implements `to_json()` method returning JSON-encoded representation (reuses AcrossAI_Logger_Formatter::format_log_entry() from T007)
- [ ] Constructor accepts database row object and hydrates all properties
- [ ] All properties are type-hinted (int, string, null) per PHP 7.4+ standard
- [ ] Handles null values correctly for nullable columns (mcp_server_id, user_id, input, output)
- [ ] PHPCS compliant, PHPStan L8 compliant (when linted in T021)

**User Stories / FR Mapping**: 
- FR-003: Capture 10 fields per execution ✅
- FR-004: Store logs in BerlinDB table ✅

**Success Criteria Addressed**: SC-002 (Row class enables efficient object mapping)

**Time Estimate**: 1.5 hours

**Dependencies**: T001 (schema definition)

**Testing**: Instantiate Row with mock data, verify all properties map correctly, test null handling, test to_array() and to_json() output format

---

### T003 – Create Ability Logs Query Wrapper

- [x] T003 Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php` BerlinDB query wrapper with CRUD operations

**Description**: Implement query wrapper class that provides CRUD methods, bulk operations, and date-based deletion for the execution logs table. This is a low-level database access layer.

**Acceptance Criteria**:
- [ ] Extends BerlinDB `Query` base class
- [ ] Implements `insert( $entry_array )` — validates 10-field array, inserts row, returns inserted ID
- [ ] Implements `get_logs( $args )` — returns array of Row objects (used by Phase C query layer); does NOT perform filtering here (filtering moved to T008)
- [ ] Implements `get_by_id( $id )` — returns single Row object by primary key
- [ ] Implements `delete_logs_before_date( $date )` — deletes rows where created_at < $date (used for retention cleanup in T016)
- [ ] Implements `count()` — returns total count of logs in table
- [ ] All methods use prepared statements (no SQL injection risk)
- [ ] Strict type comparison where applicable (SEC-04): `in_array( $status, $valid_statuses, true )`
- [ ] PHPCS compliant

**User Stories / FR Mapping**: 
- FR-001: Capture and store every execution ✅
- FR-004: Store logs in BerlinDB table ✅

**Success Criteria Addressed**: SC-002 (efficient CRUD operations), SC-005 (delete_logs_before_date for retention)

**Edge Cases Covered**: EC-005 (large input) — insertion validates truncation before write

**Time Estimate**: 2 hours

**Dependencies**: T001 (schema), T002 (Row class)

**Testing**: Test insert/get/delete/count operations with mock data; verify prepared statements are used; test edge cases (null values, large inputs, invalid IDs)

---

### T004 – Create Ability Logs Table Registration

- [x] T004 Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php` BerlinDB table registration class

**Description**: Register the custom BerlinDB table for execution logs. This class is instantiated at plugins_loaded to create the table schema if it doesn't exist.

**Acceptance Criteria**:
- [ ] Extends BerlinDB `Table` base class
- [ ] Sets `$name = 'acrossai_ability_logs'` (table name without prefix)
- [ ] Sets `$version = 1` (schema version for future migrations)
- [ ] Sets `$global = false` (per-site isolation for multisite compliance — SEC-03)
- [ ] Binds schema from T001 (via `set_schema()` or equivalent BerlinDB method)
- [ ] Binds Query class from T003 (via `set_query_class()` or equivalent)
- [ ] Binds Row class from T002 (via `set_row_class()` or equivalent)
- [ ] Registers table in singleton pattern: `Table::instance()` with no arguments
- [ ] Table is registered at `plugins_loaded` hook P20 (via Main.php T018) — fires before REST API init
- [ ] Multisite table prefix is correct (e.g wp_1_acrossai_ability_logs on site 1, wp_2_acrossai_ability_logs on site 2)
- [ ] PHPCS compliant

**User Stories / FR Mapping**: 
- FR-004: Store logs in BerlinDB table ✅

**Success Criteria Addressed**: SC-002 (table setup enables indexing), SC-005 (per-site isolation ensures no cross-site log leakage)

**Security Constraints**: SEC-03 (per-site isolation via $global = false)

**Time Estimate**: 1.5 hours

**Dependencies**: T001 (schema), T002 (Row), T003 (Query)

**Testing**: Verify table is created on plugins_loaded; check for correct column definitions and indexes in database; verify per-site prefix on multisite install; test table existence check (idempotent)

---

## Phase B: Logger Core & Utilities

**Sequencing**: Depends on Phase A (database schema exists). Must complete before Phases C–G.

### T005 – Create Source Detection Utility

- [ ] T005 Create `includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php` static utility class

**Description**: Extract source detection logic into a reusable utility class. Provides static methods to detect execution source (mcp, rest, cli, cron, ajax, direct) based on request context.

**Acceptance Criteria**:
- [ ] Static class with no instance methods (all public static functions)
- [ ] Implements `detect_source()` — returns one of: 'mcp', 'rest', 'cli', 'cron', 'ajax', 'direct' based on context checks
  - [ ] Check is_mcp_context() first (P1 priority, most specific)
  - [ ] Check is_rest_context() (REST API), uses `defined( 'REST_REQUEST' )` && REST_REQUEST
  - [ ] Check is_cli_context() (WP-CLI), uses `defined( 'WP_CLI' )` && WP_CLI
  - [ ] Check is_cron_context() (WP-Cron), uses `wp_doing_cron()`
  - [ ] Check is_ajax_context() (AJAX), uses `wp_doing_ajax()`
  - [ ] Default to 'direct' if none match
- [ ] Implements `is_mcp_context()` — checks for MCP adapter context via internal flag (set by T006 capture_mcp_server_id handler)
- [ ] Implements `detect_mcp_server_id()` — returns MCP server ID when available, null otherwise
- [ ] Implements `is_rest_context()`, `is_cli_context()`, `is_cron_context()`, `is_ajax_context()` helper methods
- [ ] All methods return consistent, validated values (strings or null)
- [ ] Detection logic is deterministic (same context always returns same source)
- [ ] FR-002 acceptance: All 6 sources are detectable and tested

**User Stories / FR Mapping**: 
- FR-002: Detect execution source (6 sources: mcp, rest, cli, cron, ajax, direct) ✅

**Success Criteria Addressed**: SC-004 (logs UI functional for all 6 sources)

**Edge Cases Covered**: EC-004 (unknown MCP server) — fallback to 'mcp' source even if server_id is null

**Time Estimate**: 1.5 hours

**Dependencies**: None (Phase B foundational task)

**Testing**: Trigger source detection from each of 6 contexts; verify correct source is returned each time; test source priority (MCP before REST before CLI, etc.)

---

### T006 – Create Ability Logger Singleton (Core)

- [ ] T006 Create `includes/Modules/Logger/AcrossAI_Ability_Logger.php` logger singleton class

**Description**: Core logger class that manages pending log entries, registers hooks at boot, and writes completed entries to database. This is the central orchestrator for all logging operations.

**Acceptance Criteria**:
- [ ] Implements singleton pattern: `protected static $_instance = null`, `public static function instance()`, `private function __construct()`
- [ ] Constructor initializes `$this->pending_entries = []` (stack for concurrent execution handling)
- [ ] Constructor initializes `$this->mcp_server_id = null` (stashed MCP context)
- [ ] Implements `boot()` method — registers 4 hooks at plugins_loaded P20:
  - [ ] `add_filter( 'mcp_adapter_pre_tool_call', [ $this, 'capture_mcp_server_id' ], 5 )` (P5: stash server_id before any execution hook)
  - [ ] `add_action( 'wp_before_execute_ability', [ $this, 'start_pending_entry' ], 10, 2 )` (P10: initialize pending entry, start timer)
  - [ ] `add_action( 'wp_after_execute_ability', [ $this, 'finish_pending_entry' ], 10, 3 )` (P10: pop pending, record result, insert to DB)
  - [ ] `add_filter( 'wp_register_ability_args', [ $this, 'wrap_permission_callback' ], 100001, 2 )` (P100001: inject permission_denied logging)
- [ ] Hook registration follows ARCH-ADV-001 pattern (direct add_filter/add_action in boot(), not via Loader) — acceptable deviation documented
- [ ] Implements `capture_mcp_server_id( $tool_name, $server_id, $args )` hook handler (stores server_id)
- [ ] Implements `start_pending_entry( $ability_slug, $args )` hook handler (initializes pending, uses source detector)
- [ ] Implements `finish_pending_entry( $ability_slug, $result, $execution_time_ms )` hook handler (pops pending, writes to DB)
- [ ] Implements `wrap_permission_callback( $args, $ability_slug )` hook handler (wraps for permission_denied logging)
- [ ] PHPCS compliant, PHPStan L8 compliant
- [ ] Handles concurrent executions: stack-based design supports nested/parallel ability calls

**User Stories / FR Mapping**: 
- US-001: Audit execution history ✅
- US-002: Automatically capture all executions ✅
- FR-001: Capture and store every execution ✅
- FR-002: Detect execution source ✅
- FR-003: Capture 10 fields ✅

**Success Criteria Addressed**: SC-001, SC-004, SC-005

**Time Estimate**: 3.5 hours

**Dependencies**: T005 (source detector utility), T007 (formatter utility), T003 (query wrapper)

**Testing**: Trigger abilities from each execution context; verify all hooks fire in correct order; test stack management with concurrent executions; test permission_denied logging

---

### T007 – Create Log Entry Formatter Utility

- [ ] T007 Create `includes/Utilities/AcrossAI_Logger_Formatter.php` log entry formatter utility

**Description**: Extract log entry formatting logic into a reusable utility. Handles JSON encoding, input/output truncation, error message formatting, and type casting.

**Acceptance Criteria**:
- [ ] Static class with no instance methods
- [ ] Implements `format_log_entry( $entry )` — returns well-formed 10-field log entry array
  - [ ] Validates all 10 required fields are present
  - [ ] Truncates input and output to 65535 bytes each (EC-005: prevent database bloat)
  - [ ] JSON-encodes input and output or returns as-is if already JSON
  - [ ] Handles non-JSON input gracefully
  - [ ] Handles exceptions and WP_Error objects as error messages
  - [ ] Casts duration_ms to int, user_id to int, created_at to string
  - [ ] Validates status is one of: success, error, permission_denied
  - [ ] Validates source is one of: mcp, rest, cli, cron, ajax, direct
  - [ ] Returns associative array with all 10 fields
- [ ] All public methods are testable; no side effects (pure functions)
- [ ] PHPCS compliant

**User Stories / FR Mapping**: 
- FR-003: Capture 10 fields per execution ✅

**Success Criteria Addressed**: SC-001 (fast formatting <100ms per log)

**Edge Cases Covered**: EC-005 (large input 65535) — truncation tested at boundary

**Time Estimate**: 1 hour

**Dependencies**: None (utility layer)

**Testing**: Format logs with various input sizes, types, and edge cases; verify truncation; test JSON encoding with malformed data

---

## Phase C: Query Layer

**Sequencing**: Depends on Phase B (logger core). Must complete before Phase D (REST controller).

### T008 – Create Query Builder with Filtering & Sorting

- [ ] T008 Create `includes/Modules/Logger/AcrossAI_Logger_Query.php` query builder with filtering, sorting, pagination

**Description**: High-level query builder that applies all filtering, sorting, pagination, and search logic. This layer is consumed by the REST controller (Phase D).

**Acceptance Criteria**:
- [ ] Static or singleton class with `get_logs( $args )` method
- [ ] Accepts args array with keys: search, orderby, order, source, status, ability_slug, user_id, page, per_page
- [ ] Filtering applied in query layer (not REST controller — AC-QUERY-LAYER-FILTERING):
  - [ ] Filter by source, status, ability_slug, user_id
  - [ ] Search by partial slug match via LIKE
- [ ] Sorting supports all columns (ability_slug, source, user_id, status, duration_ms, created_at)
- [ ] Pagination with defaults: page=1, per_page=20, max=100
- [ ] Returns array with keys: 'logs', 'total', 'pages'
- [ ] X-WP-Total header reflects filtered count
- [ ] Performance target: <500ms for queries with 100K+ records (SC-002)
- [ ] PHPCS compliant

**User Stories / FR Mapping**: 
- US-001, US-003, US-004: Audit, filter, sort ✅
- FR-006, FR-007, FR-008, FR-009 ✅

**Success Criteria Addressed**: SC-002, SC-003

**Time Estimate**: 4 hours

**Dependencies**: T003 (query wrapper), T001-T004 (database layer)

**Testing**: Test each filter, sorting, pagination; test with 100K records; verify SQL injection prevention

---

## Phase D: REST Controller

**Sequencing**: Depends on Phase C (query layer). Must complete before Phase E (admin UI).

### T009 – Create REST Logger Orchestrator Controller

- [ ] T009 Create `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` REST orchestrator

**Description**: REST orchestrator class that manages namespace, delegates route registration to sub-controllers, and provides shared permission checks.

**Acceptance Criteria**:
- [ ] Extends `WP_REST_Controller` base class
- [ ] Sets `protected $namespace = 'acrossai-abilities/v1'`
- [ ] Implements `register_routes()` — delegates to AcrossAI_Logger_Logs_Controller
- [ ] Implements `check_permission( $request )` — checks `current_user_can( 'manage_options' )`
- [ ] Early permission check (DEC-EARLY-404-REST-CHECK): returns 403 BEFORE any DB queries
- [ ] PHPCS compliant

**User Stories / FR Mapping**: 
- FR-010: Permission checks ✅

**Success Criteria Addressed**: SC-006 (permission checks prevent non-admin access)

**Time Estimate**: 1 hour

**Dependencies**: T008 (query layer)

**Testing**: Verify route registration; test permission callback with admin and non-admin users

---

### T010 – Create REST Logs Read-Only Endpoint

- [ ] T010 Create `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` REST logs read-only endpoint

**Description**: REST controller for the logs read-only endpoint. Exposes filtered, sorted, paginated logs as JSON via GET /wp-json/acrossai-abilities/v1/logger/logs.

**Acceptance Criteria**:
- [ ] Extends `WP_REST_Controller` base class
- [ ] Registers route: `GET /logger/logs`
- [ ] Route args: search, orderby, order, source, status, ability_slug, user_id, page, per_page
- [ ] Sanitizes all inputs (by type): `absint()` for integers, `sanitize_text_field()` for strings, whitelist validation for enums
- [ ] Calls `AcrossAI_Logger_Query::get_logs( $args )` for filtered results
- [ ] Sets pagination headers: `X-WP-Total`, `X-WP-TotalPages`
- [ ] PHPCS compliant
- [ ] Security: all inputs validated, output JSON (no escaping needed)

**User Stories / FR Mapping**: 
- US-001: Audit via REST endpoint ✅
- FR-005, FR-006, FR-007, FR-008, FR-009, FR-010 ✅

**Success Criteria Addressed**: SC-002, SC-003, SC-006

**Time Estimate**: 4 hours

**Dependencies**: T008 (query layer), T009 (orchestrator controller)

**Testing**: Test all query params; test sanitization; verify response format; test pagination headers; verify permission checks; test with 100K records

---

## Phase E: Admin UI (React + PHP)

**Sequencing**: Depends on Phase D (REST endpoint).

### T011 – Create LogsTable React Component

- [ ] T011 Create `src/js/components/LogsTable.js` React component using @wordpress/dataviews

**Description**: React component that renders a sortable, filterable, searchable logs table using @wordpress/dataviews.

**Acceptance Criteria**:
- [ ] Functional React component with hooks
- [ ] Mounts `<DataViews>` from `@wordpress/dataviews`
- [ ] Defines 6 columns: ability_slug, source, user_id, status, duration_ms, created_at
- [ ] Implements search (ability_slug filter)
- [ ] Implements sort (click column header)
- [ ] Implements filter dropdowns (source, status enums)
- [ ] Implements pagination (page size selector, prev/next buttons)
- [ ] Handles loading, error, empty states
- [ ] All data properly escaped (no XSS)
- [ ] ESLint compliant

**User Stories / FR Mapping**: 
- US-001, US-003, US-004: Admin UI for audit, filter, sort ✅
- FR-005, FR-006, FR-007, FR-008, FR-009 ✅

**Success Criteria Addressed**: SC-003, SC-004

**Time Estimate**: 5 hours

**Dependencies**: T010 (REST endpoint)

**Testing**: Render component; test search, sort, filter, pagination; test with large datasets

---

### T012 – Create Logs Table Styles

- [ ] T012 Create `src/scss/logs-table.scss` stylesheet for logs table container

**Description**: SCSS stylesheet for logs table component.

**Acceptance Criteria**:
- [ ] Styles for `#acrossai-logs-container` wrapper
- [ ] Responsive design (mobile-friendly)
- [ ] Use WordPress design system colors
- [ ] Minimal custom styling (DataViews handles most)
- [ ] Valid SCSS compilable via @wordpress/scripts

**Time Estimate**: 0.5 hours

**Dependencies**: T011 (React component)

**Testing**: Render component; verify layout, colors, responsive behavior

---

### T013 – Create Build Entry Point

- [ ] T013 Create `src/js/index.js` build entry point for admin UI

**Description**: Build entry point that imports and registers the LogsTable React component.

**Acceptance Criteria**:
- [ ] Imports LogsTable component
- [ ] Imports stylesheet
- [ ] Defines webpack entry point
- [ ] Outputs to `build/logger.js` and `build/logger.css`

**Time Estimate**: 0.5 hours

**Dependencies**: T011 (React component), T012 (styles)

**Testing**: Build via `npm run build`; verify output files created

---

### T014 – Modify Admin Menu Partials for Logs Tab

- [ ] T014 Modify `admin/Partials/Menu.php` to add "Logs" tab in-place

**Description**: Add a new "Logs" tab to the Abilities Manager admin page.

**Acceptance Criteria**:
- [ ] Add new tab button labeled "Logs"
- [ ] Tab content div: `<div id="acrossai-logs-container"></div>`
- [ ] Tab in logical order
- [ ] PHPCS compliant

**Time Estimate**: 0.5 hours

**Dependencies**: None (Menu.php exists)

**Testing**: Load admin page; verify Logs tab appears

---

### T015 – Enqueue Admin UI Scripts and Styles

- [ ] T015 Modify `admin/Main.php` to enqueue Logger React bundle and stylesheet

**Description**: Register script/style enqueue for the logger React component. Enqueue only on Abilities Manager admin page.

**Acceptance Criteria**:
- [ ] Enqueue `build/logger.js` with appropriate dependencies
- [ ] Enqueue `build/logger.css`
- [ ] Conditional enqueue (only on Abilities Manager page)
- [ ] PHPCS compliant

**Time Estimate**: 1 hour

**Dependencies**: T013 (build entry point), T014 (Menu.php modification)

**Testing**: Load admin page; verify script and style enqueued; verify no console errors

---

## Phase F: Action Scheduler Integration

**Sequencing**: Depends on Phase B (logger core). Can complete in parallel with Phase E.

### T016 – Schedule Daily Log Cleanup Job

- [ ] T016 Create and schedule cleanup job in `includes/Modules/Logger/AcrossAI_Ability_Logger.php::boot()`

**Description**: Schedule a daily Action Scheduler job to prune logs older than 30 days (configurable via filter).

**Acceptance Criteria**:
- [ ] In `boot()` method: schedule recurring action via `as_schedule_recurring_action()`
- [ ] Action hook: `'acrossai_ability_logger_cleanup'`
- [ ] Idempotent scheduling (no duplicate schedules)
- [ ] Register action hook handler for cleanup job
- [ ] Implement `cleanup_old_logs()` method using retention filter
- [ ] Call `AcrossAI_Ability_Logs_Query::delete_logs_before_date()` for deletion
- [ ] On deactivation: unschedule via `as_unschedule_all_actions()`
- [ ] PHPCS compliant

**User Stories / FR Mapping**: Feature 006 technical spec (retention)

**Success Criteria Addressed**: SC-005 (transparent log retention)

**Time Estimate**: 1.5 hours

**Dependencies**: T006 (logger core), T017 (delete query method)

**Testing**: Schedule job; trigger manually; verify old logs deleted; test filter customization

---

### T017 – Create Delete Logs Before Date Query

- [ ] T017 Create `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php::delete_logs_before_date()` method

**Description**: Low-level query method that deletes log entries before a specified date.

**Acceptance Criteria**:
- [ ] Method: `delete_logs_before_date( $date_string )`
- [ ] Date format: 'YYYY-MM-DD HH:MM:SS'
- [ ] Execute DELETE query with prepared statement
- [ ] Returns number of rows deleted (int)
- [ ] Handles errors gracefully
- [ ] PHPCS compliant

**Time Estimate**: 0.5 hours

**Dependencies**: T003 (query wrapper class)

**Testing**: Create mock logs with various dates; call method; verify only old logs deleted

---

## Phase G: Hook Wiring (Main.php)

**Sequencing**: Depends on Phases A–F (all components ready). Must complete before Phase H (validation).

### T018 – Wire Logger to Main.php Hooks

- [ ] T018 Modify `includes/Main.php::define_public_hooks()` to wire logger and REST controller

**Description**: Register logger singleton and REST controller with the Loader hook system.

**Acceptance Criteria**:
- [ ] In `define_public_hooks()`:
  - [ ] Get logger singleton: `$logger = AcrossAI_Ability_Logger::instance();`
  - [ ] Wire to plugins_loaded P20: `$this->loader->add_action( 'plugins_loaded', $logger, 'boot', 20 );`
  - [ ] Get REST controller: `$rest_controller = AcrossAI_Logger_Controller::instance();`
  - [ ] Wire to rest_api_init: `$this->loader->add_action( 'rest_api_init', $rest_controller, 'register_routes' );`
- [ ] Follow AC-HOOKS-MAIN pattern (named variable, never inline ::instance())
- [ ] PHPCS compliant

**Time Estimate**: 0.5 hours

**Dependencies**: T006 (logger), T009 (REST controller)

**Testing**: Load WordPress; verify no fatal errors; verify logger boots; verify REST endpoint registered

---

## Phase H: Validation & Testing

**Sequencing**: Depends on Phase G (all code in place).

### T019 – Create and Run Unit Tests

- [ ] T019 Create comprehensive unit tests for all logger components

**Description**: Write PHPUnit and Jest tests covering logger core, source detector, query builder, and React component.

**Acceptance Criteria**:
- [ ] PHPUnit tests: LoggerTest.php, SourceDetectorTest.php, QueryBuilderTest.php
  - [ ] Test singleton pattern, hook registration, source detection (all 6 sources)
  - [ ] Test filtering, sorting, pagination with 100K mock records
  - [ ] Test duration measurement accuracy, stack management, concurrent executions
- [ ] Jest tests: LogsTable.test.js
  - [ ] Test component rendering, search, sort, filter, pagination
  - [ ] Test loading/error/empty states, data escaping (no XSS)
- [ ] All tests pass (zero failures)
- [ ] Target >80% code coverage on core logger, source detector, query builder
- [ ] Tests runnable via `composer test` and `npm test`

**User Stories / FR Mapping**: All (comprehensive test coverage)

**Success Criteria Addressed**: All (tests verify SC-001 through SC-006)

**Time Estimate**: 6 hours

**Dependencies**: All Phases A–G

**Testing**: Run `composer test` and `npm test`; verify all pass; check coverage

---

### T020 – Run PHPCS Validation

- [ ] T020 Run PHPCS validation on all PHP code

**Description**: Run WordPress Coding Standards (PHPCS) validation. Zero errors required.

**Acceptance Criteria**:
- [ ] Run: `vendor/bin/phpcs includes/Modules/Logger/ admin/Partials/Menu.php includes/Main.php includes/Utilities/AcrossAI_Logger_Formatter.php --standard=WordPress-Core --extensions=php`
- [ ] Exit code 0 (zero violations)
- [ ] All files comply with WordPress Coding Standards
- [ ] If violations found, fix and re-run until zero

**Time Estimate**: 1 hour

**Dependencies**: All PHP code (Phases A–G)

**Testing**: Run PHPCS; verify zero violations

---

### T021 – Run PHPStan Level 8 Static Analysis

- [ ] T021 Run PHPStan level 8 static analysis

**Description**: Run PHPStan static analysis at level 8. Zero errors required.

**Acceptance Criteria**:
- [ ] Run: `vendor/bin/phpstan analyse includes/Modules/Logger/ admin/Partials/Menu.php includes/Main.php includes/Utilities/AcrossAI_Logger_Formatter.php -l 8`
- [ ] Exit code 0 (zero violations)
- [ ] All static type errors resolved
- [ ] If violations found, add type hints and re-run until zero

**Time Estimate**: 1.5 hours

**Dependencies**: All PHP code, T020 (PHPCS)

**Testing**: Run PHPStan L8; verify zero violations

---

### T022 – Run ESLint Validation

- [ ] T022 Run ESLint validation on JavaScript code

**Description**: Run ESLint validation on React component and build code. Zero errors required.

**Acceptance Criteria**:
- [ ] Run: `npm run lint src/js/`
- [ ] Exit code 0 (zero violations)
- [ ] All JavaScript complies with ESLint config
- [ ] If violations found, fix and re-run until zero

**Time Estimate**: 0.5 hours

**Dependencies**: T011–T013 (React code)

**Testing**: Run ESLint; verify zero violations

---

### T023 – Perform Security Review

- [ ] T023 Perform security review of all code changes

**Description**: Manual security review to verify input sanitization, output escaping, authentication/authorization, and OWASP compliance.

**Acceptance Criteria**:
- [ ] Input Sanitization: All REST params sanitized by type
- [ ] Output Escaping: React data properly escaped; REST JSON response validated
- [ ] Authentication & Authorization: manage_options check enforced; permission callback early
- [ ] Type Coercion: All array membership checks use `in_array(..., true)` (SEC-04)
- [ ] Data Privacy: Per-site isolation enforced; no sensitive data logged
- [ ] OWASP Compliance: No SQL injection, XSS, CSRF, broken auth, or sensitive data exposure

**Time Estimate**: 2 hours

**Dependencies**: All code (Phases A–G)

**Testing**: Manual code review; document findings; fix issues; re-review until pass

---

### T024 – Run Package Validation

- [ ] T024 Run package validation to ensure no duplicate dependencies

**Description**: Validate npm packages to ensure no duplicate React or conflicting dependencies.

**Acceptance Criteria**:
- [ ] Run: `npm run validate-packages`
- [ ] Exit code 0 (no validation errors)
- [ ] No duplicate @wordpress/* packages or React
- [ ] No conflicting peer dependencies
- [ ] If errors found, resolve and re-run until pass

**Time Estimate**: 0.5 hours

**Dependencies**: T013 (build setup)

**Testing**: Run package validation; verify no errors

---

## Phase Summary & Dependencies

### Execution Order

```
Phase A (Database)
├─ T001 Schema
├─ T002 Row Model
├─ T003 Query Wrapper
└─ T004 Table Registration
   ↓
Phase B (Logger Core)
├─ T005 Source Detector
├─ T006 Logger Singleton (core)
└─ T007 Formatter Utility
   ↓
Phase C (Query Layer)
└─ T008 Query Builder (filtering, sorting, pagination)
   ↓
Phase D (REST)
├─ T009 Logger Controller (orchestrator)
└─ T010 Logs Endpoint (read-only)
   ├─→ Parallel: Phase E (Admin UI)
   │   ├─ T011 LogsTable Component
   │   ├─ T012 Styles
   │   ├─ T013 Build Entry
   │   ├─ T014 Menu Modification
   │   └─ T015 Enqueue Scripts
   │
   └─→ Parallel: Phase F (Action Scheduler)
       ├─ T016 Schedule Cleanup
       └─ T017 Delete Query
   ↓
Phase G (Wiring)
└─ T018 Main.php Hooks
   ↓
Phase H (Validation)
├─ T019 Unit Tests
├─ T020 PHPCS
├─ T021 PHPStan
├─ T022 ESLint
├─ T023 Security Review
└─ T024 Package Validation
```

### MVP Scope (Minimal Viable Product)

Phases A–D + G = Core logging infrastructure. Estimated 16 hours.

**Recommended for Release**: Add Phase E (Admin UI), Phase F (Auto-cleanup), Phase H (Validation gates).

### Time Breakdown

| Phase | Tasks | Hours | Notes |
|-------|-------|-------|-------|
| A | T001–T004 | 6 | Database setup |
| B | T005–T007 | 6 | Logger core + utilities |
| C | T008 | 4 | Query builder |
| D | T009–T010 | 5 | REST controllers |
| E | T011–T015 | 12 | Admin UI |
| F | T016–T017 | 2 | Action Scheduler |
| G | T018 | 1 | Hook wiring |
| H | T019–T024 | 10 | Tests + validation |
| **Total** | **24** | **~48** | MVP = ~16 hours |

---

## Memory Constraints & Verification Checklist

### Architecture Constraints

- [ ] **ARCH-ADV-001** (T006): Logger boot() registers hooks via direct add_filter/add_action (accepted deviation documented)
- [ ] **AC-HOOKS-MAIN** (T018): Main.php wires instances with named variable pattern
- [ ] **AC-QUERY-LAYER-FILTERING** (T008, T010): All REST filtering in query layer; X-WP-Total reflects filtered results

### Security Constraints

- [ ] **SEC-03** (T004): Per-site table isolation via BerlinDB $global = false
- [ ] **SEC-04** (T003, T010): Strict type comparison (in_array(..., true)) in access control checks

### Decisions Applied

- [ ] **DEC-EARLY-404-REST-CHECK** (T009): Permission check before any DB queries
- [ ] **DEC-PROTECTED-SLUGS-PATTERN** (reference for future features)

### Pattern Implementation

- [ ] **PATTERN-SINGLE-SOURCE-UTILITY** (T005): Source detection extracted to utility class (DRY)

---

## Acceptance Criteria Summary

**All tasks must meet**:
1. Code quality: PHPCS zero errors (T020), PHPStan L8 zero errors (T021), ESLint zero errors (T022)
2. Tests: All unit tests pass (T019)
3. Security: All checks pass (T023)
4. User stories: All 4 user stories independently testable
5. Success criteria: All 6 SC-001 through SC-006 met
6. Edge cases: All 6 edge cases EC-001 through EC-006 handled
7. FRs: All 10 functional requirements FR-001 through FR-010 implemented

**Definition of Done**: All tasks complete, all gates pass, all tests green, ready for merge.

---

**Status**: Ready for Phase 2 Implementation (Phases A–H)  
**Next Step**: Start Phase A (Database Layer) with T001–T004
