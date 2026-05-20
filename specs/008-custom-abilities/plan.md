# Implementation Plan: Custom Abilities Module

**Branch**: `008-custom-abilities` | **Date**: 2026-05-20 | **Status**: Ready for Phase 0 Research

## Summary

The Custom Abilities module enables non-technical WordPress administrators to create, manage, and configure WordPress abilities directly via admin UI without writing PHP code. Abilities are stored in a BerlinDB table, auto-registered at the `wp_abilities_api_init` hook, exposed via REST CRUD endpoints, and optionally exposed to MCP servers. The module includes a DataForm admin form for creation/editing and DataViews table for management.

**Technical Approach**:
- BerlinDB 4-file pattern (Schema, Row, Query, Table) for database abstraction
- REST controller split: Orchestrator + 3 domain-specific sub-controllers (Read, Write, MCP)
- Admin UI: DataForm for ability creation/editing, DataViews for list/management
- WordPress Abilities API integration: Auto-registration at `wp_abilities_api_init` via processor
- Security: `manage_options` capability check on all endpoints and admin pages
- Namespace: `AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability` (underscore convention)

## Technical Context

**Language/Version**: PHP 7.4+ (target WordPress 6.9+)

**Primary Dependencies**:
- WordPress 6.9+ (Abilities API, REST infrastructure)
- BerlinDB 4.0+ (database abstraction layer)
- @wordpress/dataviews (DataViews component for admin table UI)
- @wordpress/dataforms (DataForm component for admin form UI - exported from dataviews)
- @wordpress/scripts (build tooling)
- wpboilerplate/wpb-mcp-servers-list (MCP server discovery)

**Storage**:
- BerlinDB-managed MySQL/MariaDB table: `{prefix}acrossai_custom_abilities`
- 20 columns: id, ability_slug, label, description, category, enabled, callback_type, callback_config, permission_type, permission_config, input_schema, output_schema, show_in_rest, show_in_mcp, mcp_type, mcp_servers, readonly, destructive, idempotent, created_at, updated_at

**Testing**: PHPUnit for database/REST/processor, Jest for React admin UI, security audit

**Target Platform**: WordPress admin and REST API

**Performance Goals**:
- List endpoint (1000+ records): <500ms response
- Admin form render: <200ms
- Ability registration: <1000ms for 10k abilities

**Constraints**: Multisite-compatible, per-site table prefix, callback execution is stub/TODO, readonly flag is metadata-only

## Constitution Check

✅ **All Gates Pass**: Modular Architecture, WordPress Standards, User-Centric Design, Security First, Extensibility, Reusability, Definition of Done.

No Constitution violations. Ready to proceed.

## Project Structure

### Documentation

```
specs/008-custom-abilities/
├── spec.md
├── memory-synthesis.md
├── clarifications.md
├── plan.md (this file)
├── research.md (Phase 0)
├── data-model.md (Phase 1)
├── contracts/ (Phase 1)
│   ├── rest-api.md
│   ├── ability-schema.md
│   └── mcp-exposure.md
├── quickstart.md (Phase 1)
└── tasks.md (Phase 2)
```

### Source Code

```
includes/Modules/Custom_Ability/
├── AcrossAI_Custom_Ability_Processor.php (wp_abilities_api_init registration)
├── Database/
│   ├── AcrossAI_Custom_Ability_Schema.php
│   ├── AcrossAI_Custom_Ability_Row.php
│   ├── AcrossAI_Custom_Ability_Query.php
│   └── AcrossAI_Custom_Ability_Table.php
├── Rest/
│   ├── AcrossAI_Custom_Ability_Rest_Controller.php (orchestrator)
│   ├── AcrossAI_Custom_Ability_Read_Controller.php
│   ├── AcrossAI_Custom_Ability_Write_Controller.php
│   └── AcrossAI_Custom_Ability_Mcp_Controller.php

includes/Utilities/
├── AcrossAI_Custom_Ability_Validator.php (static validation)
├── AcrossAI_Custom_Ability_Sanitizer.php (static sanitization)
├── AcrossAI_Custom_Ability_Callback_Executor.php (static callback logic)
├── AcrossAI_Protected_Custom_Abilities.php (static namespace filtering)
└── AcrossAI_Custom_Ability_Formatter.php (static response formatting)

admin/Partials/
├── AcrossAI_Custom_Ability_Menu.php (add_menu_page)
├── AcrossAI_Custom_Ability_Page.php (page render)
└── AcrossAI_Custom_Ability_Assets.php (wp_enqueue_*)

src/js/admin/custom-abilities/
├── index.js (main entry)
├── components/
│   ├── AbilityForm.js (DataForm wrapper)
│   └── AbilitiesList.js (DataViews wrapper)
└── api/
    └── useCustomAbilities.js (React hook)

src/scss/admin/custom-abilities/
├── form.scss
├── list.scss
└── index.scss

tests/
├── phpunit/integration/
│   ├── test-custom-ability-database.php
│   ├── test-custom-ability-rest-crud.php
│   ├── test-custom-ability-processor.php
│   └── test-custom-ability-validation.php
└── jest/admin/custom-abilities/
    ├── AbilityForm.test.js
    └── AbilitiesList.test.js
```

## Data Model

### BerlinDB Table: `{prefix}acrossai_custom_abilities`

20 columns capturing ability definition, callback config, permissions, schemas, and metadata:

- **id**: bigint unsigned PRIMARY KEY AUTO_INCREMENT
- **ability_slug**: varchar(255) UNIQUE NOT NULL (format: "namespace/name")
- **label**: varchar(255) NOT NULL (display name)
- **description**: longtext (full description)
- **category**: varchar(100) (organizational category)
- **enabled**: tinyint(1) DEFAULT 1 (auto-register flag)
- **callback_type**: varchar(50) NOT NULL ("noop", "filter_hook", "wp_remote_post")
- **callback_config**: json (type-specific configuration)
- **permission_type**: varchar(50) NOT NULL ("always_allow", "logged_in", "capability")
- **permission_config**: json (type-specific configuration)
- **input_schema**: json (JSON Schema Draft 7)
- **output_schema**: json (JSON Schema Draft 7)
- **show_in_rest**: tinyint(1) DEFAULT 1 (REST exposure)
- **show_in_mcp**: tinyint(1) DEFAULT 0 (MCP exposure)
- **mcp_type**: varchar(50) ("tool", "resource", "prompt")
- **mcp_servers**: json (array of server slugs for filtering)
- **readonly**: tinyint(1) (tri-state: NULL/0/1, metadata-only)
- **destructive**: tinyint(1) (tri-state: NULL/0/1)
- **idempotent**: tinyint(1) (tri-state: NULL/0/1)
- **created_at**: datetime DEFAULT CURRENT_TIMESTAMP
- **updated_at**: datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

### BerlinDB 4-File Pattern

1. **Schema** - Defines table structure, columns, keys
2. **Row** - Represents single ability record, JSON casting
3. **Query** - Query builder with filtering (by_slug, enabled_only, by_category, search)
4. **Table** - Manager class, $global = false for per-site prefix

## REST API Architecture

### Orchestrator + Sub-Controller Split

**Namespace**: `acrossai-abilities-manager/v1`

**Routes**: `/wp-json/acrossai-abilities-manager/v1/custom-abilities`

**Orchestrator**: `AcrossAI_Custom_Ability_Rest_Controller`
- Handles: route registration, shared permission check
- Delegates: to Read, Write, MCP sub-controllers

**Read Controller** (GET list, GET/:id):
- Query parameters: search, order, orderby, per_page, page, category, enabled, show_in_mcp
- Response: paginated list + single ability object
- Status: 200, 400, 403, 500

**Write Controller** (POST, POST/:id, DELETE/:id):
- Validation: slug uniqueness, callback config validation, permission config validation
- Before save: sanitization, validation, hooks
- After save: fetch complete row, fire hooks
- Status: 201, 200, 204, 400, 403, 409, 500

**MCP Controller** (GET /mcp/tools, /mcp/resources, /mcp/prompts):
- Filters: show_in_mcp=true, mcp_type matching route, mcp_servers filtering
- Response: MCP-compatible ability list
- Status: 200, 403, 500

### Key Patterns

- Cast bool→int before save (SEC-02)
- Fetch complete row after save for hooks (BUG-PARTIAL-HOOK-FIELDS)
- Strict `===` comparison for access control (SEC-04)
- Prepared statements via BerlinDB (no raw SQL)

## Admin UI Architecture

### DataForm Component (AbilityForm.js)

20 form fields:
- ability_slug (required, pattern validation, uniqueness check)
- label (required, max 255)
- description (textarea)
- category (select, dynamic from categories)
- enabled (checkbox)
- callback_type (select: noop/filter_hook/wp_remote_post)
- callback_config (conditional fields based on type)
- permission_type (select: always_allow/logged_in/capability)
- permission_config (conditional fields based on type)
- input_schema (textarea, JSON validation)
- output_schema (textarea, JSON validation)
- show_in_rest (checkbox)
- show_in_mcp (checkbox)
- mcp_type (select, conditional on show_in_mcp)
- mcp_servers (multiselect, conditional on show_in_mcp)
- readonly (select: inherit/false/true)
- destructive (select: inherit/false/true)
- idempotent (select: inherit/false/true)

**Behavior**: Create mode (empty form, POST), Edit mode (pre-populate, POST with ID), conditional fields, real-time validation, error display, loading state.

### DataViews Component (AbilitiesList.js)

Columns:
- ability_slug (searchable, sortable, 200px)
- label (searchable, sortable, 250px)
- category (select filter, 150px)
- enabled (status toggle, 100px)
- callback_type (select filter, 150px)
- permission_type (select filter, 150px)
- show_in_mcp (boolean, 80px)
- created_at (date sortable, 150px)
- updated_at (date sortable, 150px)

**Actions**: Edit, Toggle Enable/Disable, Delete (with confirmation), Duplicate

**Bulk Actions**: Enable Selected, Disable Selected, Delete Selected (with confirmation)

**Filtering**: Global search (slug/label/description), column filters, pagination, sort

## WordPress Abilities API Integration

### Processor: `AcrossAI_Custom_Ability_Processor`

**Hook**: `wp_abilities_api_init` (priority 10)

**Process**:
1. Fetch all enabled custom abilities
2. For each: build metadata, apply permission callback, inject via `wp_register_ability()`
3. Fire `acrossai_custom_ability_registered` hook
4. Handle errors gracefully (log, continue)

**Permission Callback**:
- capability: `current_user_can($capability)`
- logged_in: `is_user_logged_in()`
- always_allow: no callback (null)
- Fail open if capability doesn't exist

**Metadata Injection**: Via `$args['meta']` (not flat top-level keys):
- category, callback_type, callback_config, input_schema, output_schema, mcp_type, mcp_servers, database_id

## Admin Menu & Assets

### Menu

**Parent**: "Abilities Manager" (existing)
**Submenu**: "Custom Abilities" (slug: `acrossai-custom-abilities`, permission: `manage_options`)

### Assets

**File**: `admin/Partials/AcrossAI_Custom_Ability_Assets.php`

**Enqueue Logic**: Conditional on `acrossai-custom-abilities` page
- Styles: `acrossai-abilities-custom` (depends on wp-components, wp-dataviews)
- Scripts: `acrossai-abilities-custom` (depends on wp-react, wp-react-dom, wp-dataviews, wp-i18n)
- Localization: REST namespace, nonce via `wp_localize_script()`

## Dependencies

### Composer
- `wpboilerplate/wpb-mcp-servers-list` >= 1.0

### npm (@wordpress packages)
- `@wordpress/dataviews` >= 3.2
- `@wordpress/i18n`, `@wordpress/react`, `@wordpress/api-fetch`, `@wordpress/components` (already included)

**Validation**: `npm run validate-packages` before commit

## Hook Integration Points

### Actions (Custom Hooks)

- `acrossai_custom_ability_registered`: Fired when ability auto-registers at wp_abilities_api_init
- `acrossai_custom_ability_registration_error`: Fired on registration failure
- `acrossai_custom_ability_before_save`: Before REST save
- `acrossai_custom_ability_after_save`: After REST save
- `acrossai_custom_ability_deleted`: On delete
- `acrossai_custom_ability_mcp_query`: When MCP-enabled abilities queried

### Filters (Extension Points)

- `acrossai_protected_ability_prefixes`: Customize protected namespace prefixes
- `acrossai_custom_ability_permission_callback`: Customize permission closure
- `acrossai_custom_ability_wp_args`: Customize ability registration args
- `acrossai_custom_ability_rest_response`: Customize REST response shape
- `acrossai_custom_ability_query_filters`: Customize query layer filtering
- `acrossai_custom_ability_mcp_filter`: Customize MCP exposure filtering

## Key Design Decisions

1. **Namespace Collision**: Allow silently, admin responsibility to avoid
2. **Callback Execution**: Stub/TODO in v1 (registration only, not execution)
3. **Readonly Flag**: Metadata annotation only (no mutation prevention)
4. **Multisite**: Per-site table prefix ($global = false)
5. **Permissions**: manage_options only (no granular permissions in v1)
6. **UI**: DataForm + DataViews (Constitution mandate)
7. **Storage**: BerlinDB table (not options/meta)
8. **REST Response**: Single flat object (all 20 fields)

## Success Criteria

✅ SC-001: Create ability & see in list within 2 minutes
✅ SC-002: 100% registration success at wp_abilities_api_init
✅ SC-003: Complete REST CRUD with schema validation
✅ SC-004: MCP abilities discoverable, categorized by mcp_type
✅ SC-005: <500ms for 1000+ records list query
✅ SC-006: 100% permission enforcement (manage_options)
✅ SC-007: Backward compatibility maintained

## Next Steps

**Phase 0** (Research): No blocking unknowns. Complete.

**Phase 1** (Design & Contracts):
- Generate data-model.md
- Generate /contracts/rest-api.md, ability-schema.md, mcp-exposure.md
- Generate quickstart.md
- Update .github/copilot-instructions.md

**Phase 2** (Tasks): Generate tasks.md (~12-15 tasks covering BerlinDB, REST, UI, processor, utilities)
