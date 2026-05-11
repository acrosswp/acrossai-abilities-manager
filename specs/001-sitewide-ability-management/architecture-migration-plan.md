# Ability Registry Query Logic Migration Plan

## Current State

```
AcrossAI_Sitewide_Rest_Controller
  get_abilities()
    └── calls wp_get_abilities()
    └── applies search filter (slug + provider + label)
    └── applies source filter
    └── applies sort (slug/provider/source/status)
    └── applies page slice
    └── calls AcrossAI_Sitewide_Query::get_override_by_slug() per item
    └── calls AcrossAI_Ability_Merger::merge() per item
    └── returns paginated response
```

### Problems
- The REST controller owns two distinct responsibilities: HTTP boundary logic AND ability list query/filter business logic.
- The filter/sort/paginate logic over `wp_get_abilities()` cannot be tested without bootstrapping the HTTP layer.
- When the `PerUser` module adds a `GET /per-user/abilities` endpoint, it will need to filter the same ability registry by the same fields. There is no shared home for this logic — it will be duplicated.

## Target State

```
AcrossAI_Ability_Registry_Query          ← NEW: includes/Utilities/
  query( array $params ): array
    └── accepts: search, orderby, order, source, has_override, page, per_page
    └── calls wp_get_abilities()
    └── applies all filter/sort/paginate logic
    └── calls AcrossAI_Sitewide_Query and AcrossAI_Ability_Merger internally
    └── returns { abilities: array, total: int, pages: int }

AcrossAI_Sitewide_Rest_Controller
  get_abilities()
    └── validates request args
    └── delegates to AcrossAI_Ability_Registry_Query::query( $params )
    └── sets response headers
    └── returns rest_ensure_response()
```

### Benefits
- REST controller reduced to: validation + delegation + response formatting.
- `AcrossAI_Ability_Registry_Query` is fully unit-testable without HTTP layer.
- `PerUser`, `McpServer`, and other modules can reuse `AcrossAI_Ability_Registry_Query::query()` for their own ability lists.
- Filter/sort/pagination logic maintained in one place.

## Migration Phases

### Phase 1: Extract registry query logic (Estimated: 0.5 days)
**Goal**: Create `AcrossAI_Ability_Registry_Query` utility before T019 is implemented.

- **Task 1.1**: Create `includes/Utilities/AcrossAI_Ability_Registry_Query.php` — static class with `query( array $params, AcrossAI_Sitewide_Query $db_query ): array` method; accepts `search`, `orderby`, `order`, `source`, `has_override`, `page`, `per_page`; applies all filter/sort/pagination logic; calls `AcrossAI_Ability_Merger::merge()` per item; returns `[ 'abilities' => array, 'total' => int, 'pages' => int ]`
- **Task 1.2**: Update T019 description: `get_abilities()` validates and sanitizes request args, then delegates to `AcrossAI_Ability_Registry_Query::query()`, sets `X-WP-Total` and `X-WP-TotalPages` headers, returns `rest_ensure_response()`

**Coexistence**: N/A — this extraction happens before T019 is implemented. No old code to coexist with.

### Phase 2: Cover with unit tests (Estimated: 0.5 days)
**Goal**: Verify the extracted utility is independently testable.

- **Task 2.1**: Add `AcrossAI_Ability_Registry_Query` test cases to `tests/phpunit/sitewide/AbilityMergerTest.php` or create a dedicated `tests/phpunit/sitewide/AbilityRegistryQueryTest.php` — test: search filter matches slug, search filter matches provider, source filter, sort asc/desc, pagination math, `has_override` filter

**Coexistence**: REST controller delegates to the utility; both are in use. Tests target the utility directly.

## Coexistence Strategy

**Why coexistence?** Not applicable for Phase 1 — this is a pre-implementation extraction (no existing code to migrate). The REST controller is implemented clean from the start.

**Why document it anyway?** When future modules (PerUser, McpServer) add similar ability list endpoints, they MUST import `AcrossAI_Ability_Registry_Query` rather than re-implementing the logic. This plan establishes the shared utility as the canonical location.

## Rollback Plan

If the utility extraction is blocked before T019 is implemented: implement the filter/sort/pagination logic inside `get_abilities()` as a private method of the REST controller (not inline in the handler), then extract to the utility before the PerUser module begins implementation. Leave a `// TODO: extract to AcrossAI_Ability_Registry_Query before per-user module` comment.

## Success Criteria
- [ ] `includes/Utilities/AcrossAI_Ability_Registry_Query.php` exists and contains all filter/sort/pagination logic
- [ ] `AcrossAI_Sitewide_Rest_Controller::get_abilities()` contains no filter, sort, or pagination logic — only validation, delegation, and response formatting
- [ ] `AcrossAI_Ability_Registry_Query::query()` has unit tests that pass without bootstrapping the REST stack
- [ ] No duplication when PerUser module adds a similar endpoint
