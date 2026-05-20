# Feature Specification: Custom Abilities Manager

**Feature Branch**: `008-custom-abilities`  
**Created**: 2026-05-20  
**Status**: Clarified  

## User Scenarios & Testing

### User Story 1 - Admin Creates Custom Ability (Priority: P1)

An admin with `manage_options` permission needs to create a new WordPress ability without writing PHP code. They navigate to the Custom Abilities submenu under the Abilities Manager and use a form to define a new ability with all configuration.

**Why this priority**: This is the core MVP feature. Without ability creation, the system cannot function. This unlocks the entire custom abilities workflow.

**Independent Test**: Can be fully tested by: navigating to Custom Abilities > Add New, filling the form (ability slug, label, description, callback config), and clicking Save. Delivers: a new ability stored in the database and registered.

**Acceptance Scenarios**:

1. **Given** an admin user logged in with `manage_options` capability, **When** they navigate to Abilities Manager → Custom Abilities, **Then** they see an "Add New" button and a list of existing custom abilities
2. **Given** the admin clicks "Add New", **When** they fill in all required fields (ability slug, label, description, category, callback type, permission type), **Then** a form is displayed with all configuration options
3. **Given** the admin fills in the form and clicks "Save Ability", **When** form validation passes, **Then** the ability is created in the database and they see a success message
4. **Given** the admin enters duplicate ability slug, **When** they click "Save", **Then** validation fails with error message about uniqueness constraint

---

### User Story 2 - Custom Abilities Appear in Admin Interface (Priority: P1)

Once created, custom abilities must be visible in the Custom Abilities DataViews table so admins can review, edit, enable/disable, and delete them.

**Why this priority**: Admin visibility is essential for managing custom abilities. Without a list view, admins cannot verify their abilities were created or manage them.

**Independent Test**: Can be fully tested by: creating one ability, navigating to Custom Abilities list, and verifying it appears in the table with all columns (slug, label, status, callback type, etc.). Delivers: a manageable list of all custom abilities with edit/delete actions.

**Acceptance Scenarios**:

1. **Given** one or more custom abilities exist in the database, **When** an admin visits Custom Abilities admin page, **Then** a DataViews table displays all abilities with searchable/filterable columns
2. **Given** the admin views the table, **When** they click on an ability row, **Then** the ability's edit form opens, pre-populated with all current settings
3. **Given** the admin has an ability record, **When** they click the "Enable/Disable" toggle, **Then** the ability's `enabled` flag is updated and takes effect immediately
4. **Given** the admin clicks "Delete" on an ability, **When** they confirm deletion, **Then** the ability is removed and no longer registered

---

### User Story 3 - Custom Abilities Integrate with WordPress Abilities API (Priority: P1)

At WordPress initialization (`wp_abilities_api_init` hook), all enabled custom abilities from the database must be automatically registered into the WordPress Abilities API so they can be used by rest of the system.

**Why this priority**: Without API registration, custom abilities are inert database records. Integration is essential for them to function in the ecosystem.

**Independent Test**: Can be fully tested by: creating a custom ability, enabling it, triggering `wp_abilities_api_init`, and verifying the ability is present via `wp_get_registered_abilities()` or REST endpoint.

**Acceptance Scenarios**:

1. **Given** a custom ability with `enabled = true` exists in database, **When** WordPress initializes the Abilities API, **Then** the ability is automatically registered with correct metadata
2. **Given** a custom ability with `enabled = false` exists, **When** WordPress initializes the Abilities API, **Then** the ability is NOT registered
3. **Given** custom abilities are registered, **When** the REST endpoint `/wp-json/wp-abilities/v1/abilities` is called, **Then** custom abilities appear in the response with full metadata
4. **Given** ability registration occurs, **When** systems call `wp_execute_ability()`, **Then** custom abilities execute via their configured callback (noop, filter_hook, or wp_remote_post)

---

### User Story 4 - REST API CRUD Operations (Priority: P2)

Admin and external systems need programmatic access to create, read, update, and delete custom abilities via REST API endpoints under `acrossai-abilities-manager/v1/custom-abilities`.

**Why this priority**: Programmatic API enables automation, integration with other tools, and advanced workflows. This enables external orchestration systems to manage abilities.

**Independent Test**: Can be fully tested by: calling REST endpoints (POST to create, GET to list, GET/ID to get one, POST/ID to update, DELETE/ID to delete) with proper authentication and verifying CRUD operations work.

**Acceptance Scenarios**:

1. **Given** an authenticated admin request to `POST /wp-json/acrossai-abilities-manager/v1/custom-abilities` with ability data, **When** validation passes, **Then** a new ability is created and the response includes the new ability object
2. **Given** an authenticated request to `GET /wp-json/acrossai-abilities-manager/v1/custom-abilities`, **When** the request is made, **Then** a list of all custom abilities is returned with pagination
3. **Given** an authenticated request to `POST /wp-json/acrossai-abilities-manager/v1/custom-abilities/{id}` with updated data, **When** validation passes, **Then** the ability is updated and reflects changes
4. **Given** an authenticated request to `DELETE /wp-json/acrossai-abilities-manager/v1/custom-abilities/{id}`, **When** the request is made, **Then** the ability is deleted
5. **Given** an unauthenticated or low-privilege request to any endpoint, **When** the request is made, **Then** a 403 Forbidden response is returned

---

### User Story 5 - MCP Integration for Custom Abilities (Priority: P2)

Admins can optionally configure custom abilities to expose as MCP tools, resources, or prompts to MCP clients by setting `show_in_mcp = true` and specifying `mcp_type` and `mcp_servers`.

**Why this priority**: MCP integration extends the custom ability ecosystem to AI/automation clients. This is a high-value feature but secondary to basic CRUD.

**Independent Test**: Can be fully tested by: creating a custom ability with `show_in_mcp = true` and `mcp_type = 'tool'`, then verifying it appears in MCP server discovery.

**Acceptance Scenarios**:

1. **Given** a custom ability with `show_in_mcp = true` and `mcp_type = 'tool'`, **When** MCP server processes abilities, **Then** the ability is registered as an MCP tool
2. **Given** a custom ability specifies `mcp_servers` array (e.g., `['server1', 'server2']`), **When** the ability is queried by an MCP client, **Then** it is only available to those servers
3. **Given** an ability is marked `destructive = true`, **When** it is exposed via MCP, **Then** MCP clients receive a clear destructive/warning flag
4. **Given** an ability specifies input and output schemas, **When** MCP clients query the ability, **Then** schemas are provided for validation

---

### Edge Cases

- What happens when an admin tries to create two abilities with the same slug? → Validation error, slug must be unique
- What happens if the callback_config references a non-existent filter hook? → Ability registers but callback silently fails to execute
- What happens if a custom ability is deleted while it's in use by another system? → Dependent systems fail gracefully; deletion is allowed
- How does the system handle readonly abilities? → Readonly flag is metadata annotation only; does not prevent mutations in admin UI or REST API
- What happens if `permission_config` references a capability that doesn't exist? → Ability registers; permission check fails for users without capability
- What happens when custom abilities are migrated between WordPress sites? → BerlinDB provides export/import capability; schemas are portable
- What is the noop callback used for? → Placeholder/documentation abilities registered for discoverability (e.g., in MCP for external reference) but not executed via wp_execute_ability()

## Requirements

### Functional Requirements

- **FR-001**: System MUST create a BerlinDB-based database table `{prefix}acrossai_custom_abilities` with all specified columns (id, ability_slug, label, description, category, enabled, callback_type, callback_config, permission_type, permission_config, input_schema, output_schema, show_in_rest, show_in_mcp, mcp_type, mcp_servers, readonly, destructive, idempotent, created_at, updated_at)

- **FR-002**: Admins MUST be able to create custom abilities via DataForm admin UI under "Abilities Manager → Custom Abilities" menu, with all fields for ability definition

- **FR-003**: Admins MUST be able to list all custom abilities in a DataViews table with search, filtering, sorting, and enable/disable/edit/delete actions

- **FR-004**: System MUST automatically register all enabled custom abilities into WordPress Abilities API at `wp_abilities_api_init` hook so they become available system-wide

- **FR-005**: System MUST provide complete REST API CRUD endpoints under `acrossai-abilities-manager/v1/custom-abilities` route with `manage_options` permission requirement

- **FR-006**: System MUST validate ability_slug follows "namespace/name" pattern (e.g., "custom/my-ability") and enforce uniqueness

- **FR-007**: System MUST support three callback types: "noop" (placeholder/documentation), "filter_hook" (trigger WordPress filter), and "wp_remote_post" (HTTP POST to external URL)

- **FR-008**: System MUST support three permission types: "always_allow" (no permission check), "logged_in" (user must be logged in), "capability" (check specific WordPress capability)

- **FR-009**: System MUST store and validate input_schema and output_schema as JSON, following JSON Schema standard

- **FR-010**: System MUST expose custom abilities to MCP servers when `show_in_mcp = true`, respecting `mcp_type` (tool/resource/prompt) and `mcp_servers` configuration

- **FR-011**: System MUST track readonly, destructive, and idempotent flags (tri-state: NULL=inherit, 0=false, 1=true) as metadata annotations and expose them via API/MCP. Readonly flag does not prevent mutations in UI or API

- **FR-012**: System MUST enforce `manage_options` capability check on all REST endpoints and admin UI pages

- **FR-013**: System MUST follow namespace pattern `AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability` exactly, matching existing plugin architecture

- **FR-014**: System MUST use BerlinDB 4-file pattern (Schema, Row, Query, Table classes) for database layer implementation

- **FR-015**: Admin UI MUST be accessible via DataForm and DataViews components, inheriting WCAG 2.1 A compliance from WordPress standard admin UI patterns

### Key Entities

- **Custom Ability**: Represents a user-defined WordPress ability with configuration for callback execution, permissions, API exposure, and MCP integration. Attributes include slug, label, callback type/config, permission type/config, schemas, and visibility flags.

- **Ability Callback**: Configuration for how the ability is executed (noop for documentation, filter_hook, or remote HTTP post). Stored as JSON in callback_config, specifies execution method and parameters.

- **Ability Permission**: Configuration for who can execute the ability (always allow, logged-in users, specific capability). Stored as JSON in permission_config.

- **Ability Schema**: JSON Schema definitions for ability input and output, enabling validation and documentation.

- **MCP Exposure**: Configuration for exposing ability to MCP clients (enabled/disabled, type, server list), enabling AI/automation client access.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Admins can create a custom ability and see it in the Custom Abilities list within 2 minutes without writing any code

- **SC-002**: All enabled custom abilities appear in WordPress Abilities API registry after initialization (100% registration success)

- **SC-003**: REST API endpoints return complete ability CRUD operations with schema validation (all endpoints functional before deployment)

- **SC-004**: Custom abilities with `show_in_mcp = true` are discoverable by MCP clients and properly categorized by `mcp_type`

- **SC-005**: Database queries for listing/filtering custom abilities execute in under 500ms with 1000+ records

- **SC-006**: Permission checks prevent non-admin users from accessing/modifying custom abilities (100% enforcement)

- **SC-007**: Backward compatibility is maintained; existing Abilities Manager features continue to function with custom abilities present

## Assumptions

- Admin users have sufficient understanding of ability configuration to fill form fields (callback type, permission type, schemas)

- External callback URLs (for wp_remote_post type) are responsive and handle timeouts gracefully; system assumes 30-second timeout default

- JSON Schema validation follows standard JSON Schema Draft 7 conventions; complex schema features are supported but not required

- BerlinDB provides sufficient performance for up to 10,000 custom ability records per site

- WordPress Abilities API is already initialized before custom abilities plugin loads, allowing seamless registration

- Database prefix is properly configured and available via `$wpdb->prefix`

- `manage_options` capability is the appropriate permission level for all custom ability admin operations (no granular sub-permissions needed in v1)

- Deleted custom abilities do not require archiving or soft-delete; hard delete is acceptable

- MCP client discovery uses standard ability metadata endpoint and does not require separate MCP schema endpoint

- Custom abilities do not require version/deprecation management in v1; versioning is out of scope

- Admin UI accessibility requirements are satisfied by inheritance from @wordpress/dataforms and @wordpress/dataviews components; no additional accessibility testing/documentation required

- Readonly flag serves as metadata annotation only and does not enforce mutation prevention at UI or API layer
