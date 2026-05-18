# Feature Specification: Ability Execution Logger

**Feature Branch**: `006-ability-execution-logger`  
**Created**: 2026-05-19  
**Status**: Draft  
**Input**: User description: "Build an ability execution logger for the Abilities Manager plugin capturing slug, source, server ID, user ID, input, output, status, duration, and timestamp for all executions including failures and permission errors"

## User Scenarios & Testing

### User Story 1 - Admin Audits Ability Execution History (Priority: P1)

As an administrator managing WordPress abilities across multiple sources (MCP, REST API, CLI, cron), I need to view a complete log of all ability executions in one place so I can audit which abilities were called, by whom, from where, and whether they succeeded or failed.

**Why this priority**: Execution visibility is essential for compliance, debugging, and security monitoring. Without centralized logging, admins have no way to track ability usage across the plugin.

**Independent Test**: Can be fully tested by loading the Logs tab in Abilities Manager admin page and verifying execution records appear as abilities are called from different sources (REST, CLI, direct).

**Acceptance Scenarios**:

1. **Given** an admin viewing the Abilities Manager admin page, **When** clicking the "Logs" tab, **Then** a sortable, filterable table displays showing all ability executions with columns for slug, source, user, status, duration, and timestamp
2. **Given** execution logs exist, **When** filtering by status "success", **Then** only successful executions display
3. **Given** multiple ability executions, **When** sorting by "Duration (ms)" descending, **Then** slowest executions appear first
4. **Given** execution logs exist, **When** searching for a specific ability slug, **Then** only matching records display
5. **Given** large execution log dataset (100+ records), **When** admin navigates to page 2 of results, **Then** pagination loads next set of records without page reload

---

### User Story 2 - System Automatically Captures All Ability Executions (Priority: P1)

As the system, I need to automatically capture every ability execution—including successes, failures, and permission errors—so that no important execution events are missed from the audit trail.

**Why this priority**: Completeness is critical. Any execution that escapes logging creates audit gaps. Capturing failures and permission denials is especially important for security investigation.

**Independent Test**: Can be fully tested by triggering ability executions from different sources (direct PHP, REST API, WP-CLI, AJAX, cron) and verifying corresponding log records appear in the database immediately after execution.

**Acceptance Scenarios**:

1. **Given** a direct PHP ability execution, **When** the ability completes, **Then** a log record is created capturing slug, user ID, input, output, status, duration, and timestamp
2. **Given** a failed ability execution, **When** the ability raises an error, **Then** the log record captures status "error" with the error message in the output field
3. **Given** a permission-denied ability execution, **When** the ability fails permission checks, **Then** the log record captures status "permission_denied" with reason in output field
4. **Given** an MCP ability execution, **When** called from MCP adapter, **Then** source is detected as "mcp" and MCP server ID is captured
5. **Given** ability execution duration, **When** recorded, **Then** duration in milliseconds is accurate to ±10ms

---

### User Story 3 - Admin Filters Execution Logs by Source (Priority: P2)

As an admin troubleshooting a specific integration (e.g., MCP adapter issues), I need to filter execution logs by source so I can isolate executions from that specific integration without wading through unrelated log entries.

**Why this priority**: Source filtering enables rapid troubleshooting. When multiple integrations are running, filtering is essential to focus on the problematic area.

**Independent Test**: Can be fully tested by filtering logs by each source type (mcp, rest, cli, cron, ajax, direct) and verifying that only records with that source appear.

**Acceptance Scenarios**:

1. **Given** execution logs from multiple sources, **When** filtering by source "mcp", **Then** only MCP-sourced executions display
2. **Given** an MCP source filter applied, **When** records are shown, **Then** each record includes the MCP server ID field populated
3. **Given** multiple source filters applied simultaneously, **When** applying filters, **Then** results show only executions matching ALL selected sources
4. **Given** execution logs from REST API, **When** filtering by source "rest", **Then** only REST-sourced executions display with REST context preserved

---

### User Story 4 - Developer Investigates Slow Ability Executions (Priority: P2)

As a developer, I need to identify which ability executions are slow so I can optimize performance bottlenecks or investigate hanging processes.

**Why this priority**: Performance visibility helps developers identify optimization opportunities and prevent resource exhaustion.

**Independent Test**: Can be fully tested by sorting logs by Duration descending and verifying that slowest executions appear at the top of the list.

**Acceptance Scenarios**:

1. **Given** multiple ability executions with varying durations, **When** sorting by duration descending, **Then** slowest ability appears first
2. **Given** multiple executions with different durations, **When** sorting by duration descending, **Then** slowest executions appear first without needing special visual highlighting

---

### Edge Cases

- What happens when an ability execution completes very quickly (<1ms)? Duration is recorded as 0ms or rounded appropriately
- How does system handle concurrent ability executions? Each execution gets its own log record; duration is measured per-execution
- What happens when user ID is unknown (e.g., cron or direct PHP without user context)? User ID is logged as null/0 and source context explains the execution origin
- What happens when MCP server ID is unknown or not available? Field is logged as null and source is still "mcp"
- What happens when ability input is very large (nested JSON, file content)? Input is truncated to 65535 bytes (64KB) to prevent database bloat
- What happens when execution logs table grows very large (100K+ records)? Default: auto-prune logs older than 30 days (configurable via `acrossai_ability_log_retention_days` filter)

## Requirements

### Functional Requirements

- **FR-001**: System MUST capture and store a log record for every ability execution (success or failure) using wp_before_execute_ability and wp_after_execute_ability hooks
- **FR-002**: System MUST detect execution source from request context: "mcp" (from MCP adapter), "rest" (REST API namespace), "cli" (WP_CLI constant), "cron" (wp_doing_cron()), "ajax" (wp_doing_ajax()), "direct" (all other PHP calls)
- **FR-003**: System MUST capture for each execution: ability slug, source, MCP server ID (when applicable), user ID, input (JSON-encoded), output or error (JSON-encoded), status (success/error/permission_denied), duration in milliseconds, and created_at timestamp
- **FR-004**: System MUST store logs in a custom BerlinDB table following the pattern of acrossai_abilities_overwrite table with per-site table prefix
- **FR-005**: System MUST display execution logs in a new "Logs" tab on the Abilities Manager admin page with a sortable, filterable, paginated table using @wordpress/dataviews
- **FR-006**: System MUST support filtering logs by status, source, ability slug, and user ID
- **FR-007**: System MUST support sorting logs by any column (slug, source, user, status, duration, timestamp)
- **FR-008**: System MUST support pagination with configurable page size (default 20 records per page)
- **FR-009**: System MUST include search functionality to find ability slugs by partial match in the logs table
- **FR-010**: System MUST enforce permission checks; only users with manage_options capability can view execution logs

### Key Entities

- **ExecutionLog**: Represents a single ability execution record
  - `id` (int): Primary key, auto-increment
  - `ability_slug` (string, 255): The WordPress ability slug that was executed
  - `source` (enum): Origin of execution—one of: mcp, rest, cli, cron, ajax, direct
  - `mcp_server_id` (string, 255, nullable): MCP server ID when source is "mcp"
  - `user_id` (int, nullable): WordPress user ID of executor, or null for non-user contexts
  - `input` (longtext, nullable): JSON-encoded input arguments
  - `output` (longtext, nullable): JSON-encoded output result or error message
  - `status` (enum): Execution outcome—one of: success, error, permission_denied
  - `duration_ms` (int): Execution time in milliseconds
  - `created_at` (datetime): Timestamp when execution occurred
  - Indexes: (ability_slug, created_at), (source, created_at), (user_id, created_at), (status, created_at) for query performance

## Success Criteria

### Measurable Outcomes

- **SC-001**: Every ability execution generates exactly one log record within 100ms of execution completion (no missing records)
- **SC-002**: Execution logs table supports at least 100,000 records without degradation in query performance (list queries return results in <500ms)
- **SC-003**: Admin can sort, filter, and search logs across all supported fields and see results update in <1 second
- **SC-004**: Logs UI is fully functional and displays accurate data for all six source types (mcp, rest, cli, cron, ajax, direct)
- **SC-005**: Log retention is transparent to admins: unlogged executions = 0% (no executions escape logging)
- **SC-006**: Permission checks prevent non-admin users from viewing any execution logs (no unauthorized data exposure)

## Assumptions

- All ability executions run through wp_before_execute_ability and wp_after_execute_ability hooks (WP 7.0+ guarantees)
- MCP adapter, REST API, CLI, AJAX, and cron integrations are already implemented and calling abilities through the WP core hooks
- BerlinDB library is available and its table registration pattern is already established (per acrossai_abilities_overwrite table)
- @wordpress/dataviews and @wordpress/dataforms packages are already installed and can be used for admin UI (confirmed in package.json)
- Multisite isolation is required; logs are scoped per-site using per-site table prefix (existing BerlinDB convention)
- Input/output data truncation to prevent bloat is acceptable; admins understand that very large payloads may be partially logged
- Log retention is indefinite (no auto-purge); admins are responsible for manual log pruning if needed
- User context may be null/empty for cron and background executions; this is expected and acceptable
- Permission checks use the existing manage_options capability (no new capability system needed)


## Technical Notes (Implementation Context)

### Scheduler Strategy: Action Scheduler vs WP-Cron

**Decision**: Use **Action Scheduler** (available via WooCommerce) instead of WP-Cron for log cleanup scheduling.

**Rationale**:
- Action Scheduler is more reliable than WP-Cron (doesn't depend on site traffic)
- Already available in environment via WooCommerce package
- Provides better observability and control for scheduled tasks
- Consistent with enterprise WordPress practices

**Implementation**:
- Use `as_schedule_recurring_action()` instead of `wp_schedule_event()`
- Hook: Schedule cleanup job in `AcrossAI_Ability_Logger::boot()` at plugins_loaded P20
- On deactivation: Use `as_unschedule_all_actions()` in uninstall.php (not `wp_clear_scheduled_hook()`)
- Cleanup job calls `AcrossAI_Logger_Query::truncate_old_logs()` with retention days from filter

**Dependency**: Action Scheduler is installed via Composer dependency (`composer require woocommerce/action-scheduler`); available globally and not dependent on WooCommerce plugin being active.


## Clarifications

### Session 2026-05-19

**Ambiguity Assessment**: ✅ All critical areas clear — no formal clarification questions required.

**Coverage Validation**:
- Functional Scope & Behavior: ✅ Clear (4 user stories, independently testable, scope bounded)
- Domain & Data Model: ✅ Clear (ExecutionLog entity fully specified, relationships defined)
- Interaction & UX Flow: ✅ Clear (user journey defined, UI integration specified)
- Non-Functional Quality Attributes: ✅ Clear (6 measurable success criteria, performance targets)
- Integration & External Dependencies: ✅ Clear (Action Scheduler, BerlinDB, @wordpress/*)
- Edge Cases & Failure Handling: ✅ Clear (6 edge cases identified and resolved)
- Constraints & Tradeoffs: ✅ Clear (8 assumptions, technical constraints documented)
- Terminology & Consistency: ✅ Clear (canonical terms, no ambiguous synonyms)
- Completion Signals: ✅ Clear (acceptance criteria testable, Definition of Done metrics)

**Resolved Items**:
- Q1: Slow execution highlighting — Answer C: No visual highlighting; sort/filter sufficient
- Q2: Input/output truncation — Answer B: 65535 bytes (64KB) per field
- Q3: Log retention policy — Answer B: Auto-prune logs older than 30 days (configurable via filter)
- Q4: Scheduler strategy — Answer Updated: Use Action Scheduler via Composer dependency (not WP-Cron)

**Decision**: ✅ Specification is **ready for planning**. No clarifications needed.

