# Feature Specification: Abilities Business Logic and REST API

**Feature Branch**: `009-abilities-business-logic-rest`  
**Created**: 2026-05-22  
**Status**: Draft  
**Input**: User description: "Abilities business logic and REST API (Spec 009)."

## Clarifications

### Session 2026-05-22

- Q: Who is allowed to execute published database-managed abilities at runtime? → A: Authenticated users only.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Manage Database Abilities (Priority: P1)

As a site administrator, I want to create, update, and remove database-managed abilities through a dedicated management API so that I can maintain custom abilities without changing code.

**Why this priority**: Creating and maintaining database-managed abilities is the core business outcome of this feature and unlocks administrator control over custom ability definitions.

**Independent Test**: Can be fully tested by creating a new database-managed ability, updating selected fields, and deleting it while confirming the saved data and returned responses remain consistent.

**Acceptance Scenarios**:

1. **Given** an authorized administrator wants to add a new custom ability, **When** they submit valid ability details, **Then** the system creates a new database-managed ability in draft status and returns the complete saved record.
2. **Given** an existing database-managed ability, **When** an authorized administrator updates only a subset of fields, **Then** the system preserves untouched fields and returns the updated record.
3. **Given** an existing database-managed ability, **When** an authorized administrator deletes it, **Then** the system removes it and confirms the deletion without returning stale data.
4. **Given** an administrator submits an invalid ability definition, **When** validation fails, **Then** the system rejects the request with a clear error describing the invalid field.

---

### User Story 2 - Browse and Filter Abilities (Priority: P2)

As a site administrator, I want to browse abilities with search, pagination, and filters so that I can quickly find both custom and inherited abilities and understand which ones are editable.

**Why this priority**: Administrative visibility is required to manage large ability catalogs safely and to distinguish editable database abilities from read-only abilities supplied elsewhere.

**Independent Test**: Can be fully tested by requesting paginated lists with search and filters, retrieving a single ability by identifier, and verifying totals, page counts, and editability rules.

**Acceptance Scenarios**:

1. **Given** a catalog of abilities from multiple sources, **When** an authorized administrator requests a filtered list, **Then** the system returns only matching items together with accurate total and pagination metadata.
2. **Given** an ability identifier for an existing item, **When** an authorized administrator requests that item, **Then** the system returns the complete formatted record.
3. **Given** an identifier for a missing item, **When** an authorized administrator requests it, **Then** the system returns a not-found error.

---

### User Story 3 - Register Published Database Abilities at Runtime (Priority: P3)

As a site administrator, I want published database-managed abilities to become available to the runtime ability registry so that approved abilities can be executed and exposed consistently without manual registration steps.

**Why this priority**: Runtime registration turns stored ability definitions into usable application behavior, but it depends on the management and validation flows already working.

**Independent Test**: Can be fully tested by publishing valid database-managed abilities, initializing the ability registry, and confirming that only eligible abilities are registered with the expected execution behavior.

**Acceptance Scenarios**:

1. **Given** a published database-managed ability with valid required metadata, **When** the runtime registry initializes, **Then** the system registers that ability for execution by authenticated users only.
2. **Given** a stored ability that is missing required identity or category data, **When** the runtime registry initializes, **Then** the system skips that ability instead of registering incomplete data.
3. **Given** a published database-managed ability exposed for machine-consumable clients, **When** an authorized administrator requests abilities by exposure type, **Then** the system returns only abilities matching that exposure type for administrative discovery.

---

### Edge Cases

- What happens when an administrator submits a slug suffix that would produce an invalid or duplicate managed slug?
- How does the system behave when a non-database ability is updated and the request attempts to change protected identity or execution fields?
- What happens when a published database-managed ability references an unregistered category?
- How does the system respond when callback configuration is incomplete for the selected execution mode?
- What happens when a request asks for all results, very large pages, or a page number beyond the available result set?
- How does the system behave when a remote execution target is missing or returns an error?

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST allow authorized administrators to create new database-managed abilities through an administrative API.
- **FR-002**: The system MUST assign all newly created database-managed abilities to the database source and default them to draft status unless a valid status is explicitly provided.
- **FR-003**: The system MUST allow authorized administrators to retrieve a paginated collection of abilities with search, sorting, and source/status filtering.
- **FR-004**: The system MUST allow authorized administrators to retrieve a single ability by identifier.
- **FR-005**: The system MUST allow authorized administrators to update existing abilities using sparse updates that change only the submitted fields.
- **FR-006**: The system MUST allow deletion only for database-managed abilities and MUST reject deletion requests for abilities originating from other sources.
- **FR-007**: The system MUST treat non-database abilities as partially read-only during updates by rejecting changes to protected identity, descriptive, and execution fields while still allowing permitted fields to change.
- **FR-008**: The system MUST validate ability slugs so they follow a namespace-and-name format, remain within the defined length limit, and are unique when creating new database-managed abilities.
- **FR-009**: The system MUST validate labels, categories, status values, source values, callback types, callback configuration, exposure metadata, and structured schema payloads before persisting changes.
- **FR-010**: The system MUST sanitize all ability inputs before validation and persistence, including descriptive text, callback configuration, exposure metadata, and structured schema payloads.
- **FR-011**: The system MUST provide a formatted response shape for individual abilities and paginated collections so administrative clients receive consistent field names, data types, and pagination metadata.
- **FR-012**: The system MUST expose administrator-only machine-consumable ability collections by exposure type so authorized management clients can request tool, resource, or prompt abilities separately.
- **FR-013**: The system MUST provide an administrative endpoint for retrieving currently available ability categories.
- **FR-014**: The system MUST register only published database-managed abilities during runtime initialization.
- **FR-015**: The runtime registration flow MUST skip abilities that are incomplete, invalid, or tied to unavailable categories instead of failing the full registration pass.
- **FR-016**: The system MUST build execution behavior for each registered database-managed ability according to its configured execution mode.
- **FR-017**: The system MUST restrict runtime execution of published database-managed abilities to authenticated users and deny anonymous execution requests.
- **FR-018**: The system MUST restrict all management and retrieval endpoints in this feature to authorized administrators and return a forbidden response when permission checks fail.
- **FR-019**: The system MUST maintain audit-friendly created and updated ownership/timestamp data for database-managed abilities.

### Key Entities *(include if feature involves data)*

- **Ability**: A managed record representing a callable capability, including identity, label, description, category, source, publication status, execution settings, schema metadata, exposure settings, and audit fields.
- **Ability Category**: A registered grouping that determines whether an ability can be classified and published for runtime use.
- **Execution Configuration**: The structured settings that define how an ability behaves when invoked, including the chosen execution mode and any supporting configuration values.
- **Exposure Profile**: The metadata that determines whether an ability is visible to machine-consumable clients and, if so, under which exposure type and server constraints.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Authorized administrators can create a valid database-managed ability and receive a complete saved record in under 2 minutes without touching plugin code.
- **SC-002**: 100% of invalid create and update requests are rejected with field-specific validation errors before any partial data is persisted.
- **SC-003**: Authorized administrators can find a target ability within a catalog of at least 1,000 abilities using search and filters in no more than 3 requests.
- **SC-004**: Paginated list responses for up to 100 returned items include accurate total and total-page metadata on every request.
- **SC-005**: 100% of published, valid, database-managed abilities are available in the runtime registry after initialization, while invalid or incomplete abilities are skipped without blocking the rest.
- **SC-006**: 100% of non-administrator requests to this feature's management endpoints are denied.
- **SC-007**: 100% of anonymous runtime execution attempts for published database-managed abilities are denied.
- **SC-008**: Machine-consumable clients only receive abilities that match the requested exposure type and are explicitly marked for exposure.

## Assumptions

- Site administrators are the only intended operators of this feature in version 1.
- Published database-managed abilities may be executed by logged-in users, but not by anonymous visitors.
- Existing plugin, theme, and core abilities remain visible for inspection but are not fully editable through this feature.
- Ability categories are registered elsewhere in the system before administrators publish database-managed abilities into those categories.
- Execution support for remote and inline code callbacks is required for stored abilities in this feature, even though admin menu UI work is out of scope here.
- This feature covers business logic and management APIs only; separate admin navigation and page registration work will be handled in a later specification.
