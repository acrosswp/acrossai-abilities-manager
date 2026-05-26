# Phase 0 Research: Abilities Business Logic and REST API

## Decision 1: Reuse the unified abilities table as the only storage boundary

**Decision**: Spec 009 will use the existing per-site `acrossai_abilities` BerlinDB table as the sole persistence boundary for database-managed abilities.

**Rationale**: Durable memory and the current unified schema already distinguish row ownership through `source` semantics. Creating a second custom-abilities table would conflict with the active architecture boundary and duplicate schema/query logic.

**Alternatives considered**:
- Create a dedicated custom abilities table: rejected because it violates the unified-table boundary and would require duplicate migration/query logic.
- Keep all logic inside Sitewide database classes only: rejected because Spec 009 needs an Abilities-focused business-logic and REST surface, not more Sitewide ownership.

## Decision 2: Split REST by domain behind a thin orchestrator

**Decision**: The Abilities REST module will use a thin orchestrator plus four sub-controllers: read, write, exposure, and categories.

**Rationale**: Spec 009 spans multiple handler groups and the constitution requires a split once one controller would carry multiple user stories. This keeps `Main.php` wiring minimal and consistent with the existing REST modularization pattern.

**Alternatives considered**:
- One large REST controller: rejected because it would collapse multiple stories into one class and drift from the repo’s enforced REST split pattern.
- Multiple controllers each wired directly in `Main.php`: rejected because only the orchestrator should be Loader-wired.

## Decision 3: Keep filtering, search, sort, and pagination in the query-builder layer

**Decision**: The Abilities query-builder layer will own browse semantics, including search, source/status filtering, editable-state filtering, sorting, and pagination.

**Rationale**: Accurate `X-WP-Total` and `X-WP-TotalPages` metadata depends on the filtered result set being defined before REST response formatting. Durable memory also records this as an active architecture constraint.

**Alternatives considered**:
- Filter inside REST controllers after fetching: rejected because it breaks pagination accuracy and duplicates logic across endpoints.
- Add implicit filtering to low-level BerlinDB methods only: rejected because the repo’s query pattern keeps raw BerlinDB helpers thin and pushes browse semantics into query builders.

## Decision 4: Runtime publication only registers valid published database-managed abilities

**Decision**: Runtime registration will read `source = db` and `status = publish` rows from the unified table, validate required identity/category/execution fields, skip invalid rows, and continue the registration pass without fataling the registry bootstrap.

**Rationale**: This matches FR-014 through FR-017 and the historical watchpoint that registration payloads must use nested meta paths rather than flat keys.

**Alternatives considered**:
- Fail the full runtime pass on the first invalid row: rejected because the spec explicitly requires skip-and-continue behavior.
- Register draft or non-db rows: rejected because publication status and source semantics are part of the feature contract.

## Decision 5: Runtime execution for database-managed abilities is authenticated-user only

**Decision**: Published database-managed abilities will be registered with a runtime permission callback that requires `is_user_logged_in()` for all execution modes.

**Rationale**: The feature clarification and FR-017 require authenticated runtime execution only. This is a deliberate runtime rule and should not be left to per-endpoint defaults.

**Alternatives considered**:
- Anonymous runtime execution for public exposure types: rejected because it conflicts with the clarified requirement.
- Administrator-only runtime execution: rejected because the spec allows logged-in users at runtime while reserving management APIs for administrators.

## Decision 6: Sparse writes must merge against stored rows and re-read the saved record

**Decision**: Update paths will sanitize submitted fields, merge them against the stored row, persist only the intended changes, then fetch the full saved row before formatting responses or firing after-save hooks.

**Rationale**: Durable bug memory already documents that sparse write paths are unsafe when hook payloads rely on local partial arrays.

**Alternatives considered**:
- Return the submitted payload as the updated record: rejected because it produces incomplete responses and broken after-save hook payloads.
- Require full PUT-style replacements only: rejected because FR-005 explicitly requires sparse updates.
