# Feature Specification: Unified Abilities Table

**Feature Branch**: `008-unified-abilities-table`
**Created**: 2026-05-22
**Status**: Implemented
**Input**: User description: "Unified abilities table — update 5 existing files, create no new files."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Abilities Stored as First-Class Records (Priority: P1)

The system stores every ability as a complete, self-describing record rather than as a partial override patch. Each record carries its own label, description, category, lifecycle status, callback configuration, and JSON schemas, making the abilities store the single authoritative source for all ability metadata.

**Why this priority**: Without this, abilities defined in the database lack the metadata needed by the admin UI, REST consumers, and MCP clients to describe or execute them. This is the foundation all other stories depend on.

**Independent Test**: Insert one ability record with a label, description, category, and `status = 'draft'`. Retrieve it. Verify all fields are present, correctly typed, and round-trip without data loss.

**Acceptance Scenarios**:

1. **Given** an empty abilities table, **When** a record is inserted with label, description, category, status, callback type, callback configuration, input schema, and output schema, **Then** all 24 fields are persisted and retrievable with correct types and values.
2. **Given** an existing ability record, **When** it is updated with a new status and callback configuration, **Then** only `updated_at` and `updated_by` change; `created_at` and `created_by` remain unchanged.
3. **Given** an ability record whose callback configuration contains a nested JSON object, **When** the record is read, **Then** the configuration is returned as a structured object (not a raw JSON string).

---

### User Story 2 — Abilities Queryable by Source (Priority: P2)

The system allows consumers to retrieve all abilities belonging to a specific source (e.g., abilities created via the database UI vs. abilities discovered from plugins). This enables the admin list and REST endpoints to partition abilities by origin without loading the full abilities set.

**Why this priority**: Without source-based querying, callers must fetch all records and filter in memory — wasteful at scale and a prerequisite for admin UI filtering.

**Independent Test**: Insert 3 records with `source = 'db'` and 2 with `source = 'plugin'`. Call the by-source query for `'db'`. Verify exactly 3 records are returned and none have `source = 'plugin'`.

**Acceptance Scenarios**:

1. **Given** a mix of abilities with different sources, **When** the by-source query is called with `source = 'db'`, **Then** only abilities with that exact source value are returned.
2. **Given** no abilities with the requested source, **When** the by-source query is called, **Then** an empty array is returned (no error).

---

### User Story 3 — Unified Table Replaces Override-Only Table (Priority: P3)

The database table is renamed from its previous "overwrite"-scoped name to a general-purpose name that reflects its expanded role as the unified store for all managed abilities. Uninstalling the plugin removes the correctly-named table and its associated metadata option.

**Why this priority**: The rename is a prerequisite for communicating the expanded scope to developers and for accurate uninstall cleanup, but it does not change runtime query behaviour that other stories depend on.

**Independent Test**: Activate the plugin on a fresh database. Verify that the table `{prefix}acrossai_abilities` exists. Deactivate and uninstall the plugin. Verify the table is dropped and its db-version option is deleted.

**Acceptance Scenarios**:

1. **Given** a fresh WordPress installation, **When** the plugin is activated, **Then** a table named `{prefix}acrossai_abilities` is created with 24 columns.
2. **Given** an installed plugin, **When** the plugin is uninstalled, **Then** `{prefix}acrossai_abilities` is dropped and the `acrossai_abilities_db_version` option is deleted.
3. **Given** the plugin was previously installed with the old table name, **When** a migration runs, **Then** existing data is preserved (migration path is out of scope for this spec; new installs use the new name only).

---

### Edge Cases

- What happens when a JSON field (callback configuration, MCP servers, schemas) contains malformed JSON on read? → The field is returned as `null`; no exception is thrown.
- What happens when a new JSON-encoded field is added by a third-party filter? → The constructor decodes it automatically without requiring code changes, because the JSON field list is driven by a filterable registry.
- What happens when the same ability slug is inserted twice? → The database rejects the second insert (unique constraint); the caller receives a failure result.
- What happens if `status` is omitted on insert? → It defaults to `'draft'`.
- What happens if `source` is omitted on insert? → It defaults to `'db'`.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The abilities table MUST be named `{prefix}acrossai_abilities` (not `{prefix}acrossai_abilities_overwrite`) in all new installations.
- **FR-002**: The abilities table MUST contain exactly 24 columns: `id`, `ability_slug`, `label`, `description`, `category`, `status`, `provider`, `source`, `site_allowed`, `callback_type`, `callback_config`, `input_schema`, `output_schema`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `readonly`, `destructive`, `idempotent`, `created_at`, `updated_at`, `created_by`, `updated_by`.
- **FR-003**: The `ability_slug` column MUST enforce uniqueness; duplicate slug inserts MUST fail.
- **FR-004**: The `status` column MUST default to `'draft'` when not supplied on insert. A `source=db` ability with `status='publish'` is ALWAYS registered — there is no separate enabled flag.
- **FR-006**: The `source` column MUST default to `'db'` when not supplied on insert.
- **FR-007**: The `callback_type` column MUST default to `'noop'` when not supplied on insert.
- **FR-008**: The JSON-encoded fields (`mcp_servers`, `callback_config`, `input_schema`, `output_schema`) MUST be decoded to PHP arrays on read. If the stored value is malformed JSON or not an array, the field MUST be returned as `null`.
- **FR-009**: The list of JSON-encoded fields MUST be governed by a single filterable registry (a static method that applies the filter `acrossai_abilities_json_fields`). No other code path may duplicate or inline this list.
- **FR-010**: When any of the JSON-encoded fields is saved as a PHP array, it MUST be automatically JSON-encoded before reaching the database. This encoding MUST use the same filterable registry as FR-009. If `json_encode()` fails for a field, that field MUST be stored as `null`; the save operation MUST continue for remaining fields.
- **FR-011**: The by-source query MUST accept a source string and return all ability records matching that source, with no row count limit. If the source argument is empty or `null`, the method MUST return an empty array (no match, not all records).
- **FR-012**: On insert, `created_at` and `created_by` MUST be set; on update they MUST NOT be overwritten.
- **FR-013**: The uninstall routine MUST drop `{prefix}acrossai_abilities` and delete the `acrossai_abilities_db_version` option.
- **FR-014**: The schema version option key MUST be `acrossai_abilities_db_version` (not `acrossai_abilities_overwrite_db_version`).
- **FR-015**: All changes MUST be confined to the 5 named files. `includes/Main.php`, `includes/AcrossAI_Activator.php`, and the PHPUnit test file MUST NOT be modified.
- **FR-016**: The table MUST have exactly these indexes: `PRIMARY KEY (id)`, `UNIQUE KEY ability_slug (ability_slug(191))`, `KEY idx_status (status)`, `KEY idx_source (source)`, `KEY idx_updated_at (updated_at)`.
- **FR-017**: `AcrossAI_Sitewide_Table::$version` MUST remain `'1.0.0'` — it MUST NOT be bumped to any other value.
- **FR-018**: In `save_override()`, only the block that JSON-encodes `mcp_servers` is replaced with the filter-driven loop. The following three blocks MUST remain completely unchanged: (1) the tri-state bool-to-int cast block for `site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`; (2) the `mcp_type` value validation guard that blocks invalid values before the DB write; (3) the `mcp_servers` non-string guard that ensures the value is a string or null after encoding.
- **FR-019**: The by-source query method on `AcrossAI_Sitewide_Query` MUST be named `by_source()` — NOT `get_all_by_source()` or any other variant. Spec 009's `AcrossAI_Abilities_Query` calls this method by name.
- **FR-020**: The `status` column MUST be `varchar(20)` — NOT `varchar(50)`. The only valid values (`draft`, `publish`) are well within 20 characters; a tighter constraint prevents invalid long strings.
- **FR-021**: The `label` column MUST be nullable (`DEFAULT NULL`) — NOT `NOT NULL DEFAULT ''`. Override rows (`source != 'db'`) do not carry a label; forcing a non-null default would store empty strings instead of NULL for every plugin/theme override row.

### Key Entities

- **Ability record**: A row in the unified abilities table. Contains identity (`ability_slug`), display metadata (`label`, `description`, `category`), lifecycle state (`status`), origin (`provider`, `source`), override flags (`site_allowed`, `readonly`, `destructive`, `idempotent`), callback definition (`callback_type`, `callback_config`), exposure flags (`show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`), JSON schemas (`input_schema`, `output_schema`), and audit fields (`created_at`, `updated_at`, `created_by`, `updated_by`).
- **JSON field registry**: The authoritative, filterable list of fields that are stored as JSON strings and must be decoded on read and encoded on save. Acts as the single source of truth for JSON serialisation behaviour.
- **Source**: A classification string indicating where an ability originated (e.g., `'db'`, `'plugin'`, `'theme'`, `'core'`). Used to partition abilities by origin.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of the 24 required columns are present in the table created on a fresh install — verified by schema inspection.
- **SC-002**: All existing automated tests continue to pass without modification after the 5 files are updated.
- **SC-003**: A record inserted with all 24 fields round-trips through read without any field loss, type corruption, or malformed JSON.
- **SC-004**: A by-source query for a known source returns only records with that source and no records with a different source (zero false positives, zero false negatives).
- **SC-005**: Adding a new JSON field via the filter (FR-009) causes it to be automatically decoded on read and encoded on save without any further code changes.
- **SC-006**: Uninstalling the plugin on a site where the table exists leaves no trace of `{prefix}acrossai_abilities` or `acrossai_abilities_db_version`.

## Assumptions

- No data migration from the old `acrossai_abilities_overwrite` table is required; this spec covers new installations only. Existing sites that upgrade must manually deactivate, uninstall, and reinstall the plugin — no graceful degradation or silent fallback for the old table is provided.
- The existing test suite (`SitewideQueryTest.php`) already covers the pre-existing behaviour; its passing state is the regression baseline — no new test modifications are needed to satisfy FR-015. All test assertions operate through the Query class API (behavioural); no assertion references the raw table name string `acrossai_abilities_overwrite` directly, so the table rename does not break SC-002.
- `mcp_type` length remains `100` characters (not shortened to `50`).
- The tri-state columns (`site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`) already have correct cast logic via `AcrossAI_Sanitizer::cast_tri_state()` and that logic is unchanged.
- The singleton pattern and ABSPATH guard on every PHP file are required by the project Constitution and apply to all 5 files.

## Clarifications

### Session 2026-05-22

- Q: Do `SitewideQueryTest.php` assertions reference the raw table name `acrossai_abilities_overwrite`, or do they test only through the Query class API (behavioral assertions)? → A: Tests are purely behavioral — all assertions go through the Query class API; no raw table name is hardcoded in any assertion.
- Q: What is the upgrade path for existing sites that have the old `acrossai_abilities_overwrite` table? → A: New-installation-only scope; existing sites must manually uninstall and reinstall (no migration, no graceful degradation).
- Q: What should the WordPress filter name be for the JSON field registry (FR-009)? → A: `acrossai_abilities_json_fields`
- Q: When `json_encode()` fails on a JSON field in `save_override()`, what should happen? → A: Store `null` for that field and continue saving the rest of the record.
- Q: When `get_all_by_source()` is called with an empty or null source string, what should it return? → A: Return an empty array (treat as no match).
