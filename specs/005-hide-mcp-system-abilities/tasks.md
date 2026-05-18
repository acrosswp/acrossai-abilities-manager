# Implementation Tasks: Hide MCP Adapter System Abilities

**Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)
**Branch**: `005-hide-mcp-system-abilities` | **Generated**: 2026-05-19

---

## T001: Research & Verification

**Objective**: Understand the current query builder API and REST controller structure to ensure seamless integration of protected-slug filtering.

**Tasks**:

- [ ] Review spec.md requirements (FR-001 through FR-007)
- [ ] Review plan.md technical context and phases
- [ ] Open `includes/Utilities/AcrossAI_Ability_Registry_Query.php` and document:
  - Constructor parameters
  - Query builder method chain (if fluent)
  - How `get_results()` is called (final execution)
  - Whether `WHERE` clause is built via method or direct SQL
  - How pagination is calculated (`count()` or `X-WP-Total` header logic)
- [ ] Open `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php` and document:
  - `get_ability()` method signature
  - Where slug parameter is extracted from `$request`
  - Current error response format (find existing 404 or 400 responses)
  - Flow: query → sanitize → lookup → return
- [ ] Check if `sanitize_ability_slug()` exists and where it's used (SEC-01 compliance)
- [ ] Search for multisite-specific handling in query builder (e.g., `$wpdb->prefix` usage)
- [ ] Identify test bootstrap file (if PHPUnit available) or document why tests are blocked (pre-existing)

**Definition of Done**:
- File signatures and method flows documented (paste into session memory)
- No architectural blockers identified
- Sanitization and multisite handling verified

**Estimate**: 1 session (context gathering only, no code changes)

---

## T002: Create `AcrossAI_Protected_Abilities` Utility Class

**Objective**: Build a centralized, extensible utility for managing the protected-abilities list.

**Subtasks**:

- [ ] Create new file: `includes/Utilities/AcrossAI_Protected_Abilities.php`
- [ ] Add file header with plugin name, description, license (follow plugin boilerplate style)
- [ ] Declare class: `final class AcrossAI_Protected_Abilities`
- [ ] Add PHPDoc class docstring:
  ```php
  /**
   * Manages protected (system) ability slugs that are hidden from the Manager UI.
   *
   * Protected abilities are system infrastructure abilities that should not be
   * managed by site administrators through the Abilities Manager. This class provides
   * a single source of truth for protected slugs and allows third-party plugins to
   * register custom protected slugs via the acrossai_abilities_manager_protected_slugs filter.
   *
   * @package AcrossAI\AbilitiesManager\Utilities
   * @since   1.3.0
   */
  ```

- [ ] Implement `public static function get_protected_slugs(): array`
  - Hard-coded defaults:
    ```php
    $defaults = [
        'mcp-adapter/discover-abilities',
        'mcp-adapter/execute-ability',
        'mcp-adapter/get-ability-info',
    ];
    ```
  - Call filter:
    ```php
    /**
     * Filters the list of protected ability slugs.
     *
     * Protected abilities are hidden from the Abilities Manager UI and inaccessible
     * via the REST API. Use this filter to register custom internal abilities that
     * should not be managed by site administrators.
     *
     * @since 1.3.0
     *
     * @param array $protected_slugs List of ability slugs to protect. Default: MCP adapter abilities.
     */
    return apply_filters( 'acrossai_abilities_manager_protected_slugs', $defaults );
    ```
  - Optional: Cache result in static property within single request (no transient needed)

- [ ] Implement `public static function is_protected( string $slug ): bool`
  ```php
  $protected = self::get_protected_slugs();
  return in_array( $slug, $protected, true );
  ```

- [ ] Add inline usage documentation as a comment block (example for third-party plugin developers):
  ```php
  /*
   * Example: To register a custom protected slug, add this to your plugin:
   *
   * add_filter( 'acrossai_abilities_manager_protected_slugs', function( $slugs ) {
   *     $slugs[] = 'my-plugin/internal-helper';
   *     return $slugs;
   * } );
  */
  ```

- [ ] Run PHPCS: `composer phpcs -- includes/Utilities/AcrossAI_Protected_Abilities.php`
  - Pass: 0 errors (no suppressions unless justified)
  
- [ ] Run PHPStan L8: `composer phpstan -- includes/Utilities/AcrossAI_Protected_Abilities.php`
  - Pass: exit code 0

**Definition of Done**:
- Class fully implemented with all methods
- All inline documentation complete
- PHPCS 0 errors
- PHPStan L8 exit 0
- No external dependencies

**Estimate**: 30 minutes

---

## T003: Update `AcrossAI_Ability_Registry_Query` to Filter Protected Slugs

**Objective**: Exclude protected slugs from all GET /sitewide/abilities list queries (search, filter, sort, paginate).

**Subtasks**:

- [ ] Open `includes/Utilities/AcrossAI_Ability_Registry_Query.php`
- [ ] Add use statement at top of class file:
  ```php
  use AcrossAI\AbilitiesManager\Utilities\AcrossAI_Protected_Abilities;
  ```
  (or adjust namespace based on actual code structure)

- [ ] Locate the `query()` method (or equivalent entry point for building queries)
- [ ] Identify where the WHERE clause is built (e.g., BerlinDB query builder API or direct `$wpdb` usage)
- [ ] Add filtering before `get_results()` is called:
  ```php
  // Get protected slugs and add NOT IN clause
  $protected_slugs = AcrossAI_Protected_Abilities::get_protected_slugs();
  if ( ! empty( $protected_slugs ) ) {
      // Exact BerlinDB API TBD from T001 research
      // Example for common patterns:
      $this->where_not_in( 'slug', $protected_slugs );
      // OR
      $this->where( 'slug', 'NOT IN', $protected_slugs );
  }
  ```

- [ ] Verify that the WHERE clause is applied to ALL query paths:
  - List query (no filters)
  - Search query (with search term)
  - Source filter query (filter by plugin/theme/core)
  - Sort query (any sort order)
  - Paginated query (verify totals include WHERE clause)

- [ ] Test query: Add inline check or debug output to verify WHERE clause is present (remove before commit)

- [ ] Verify pagination headers:
  - `X-WP-Total`: Should return count excluding protected slugs
  - `X-WP-TotalPages`: Should be calculated from adjusted total
  - No regression in pagination logic

- [ ] Run PHPCS: `composer phpcs -- includes/Utilities/AcrossAI_Ability_Registry_Query.php`
  - Pass: 0 errors

- [ ] Run PHPStan L8: `composer phpstan -- includes/Utilities/AcrossAI_Ability_Registry_Query.php`
  - Pass: exit code 0

**Definition of Done**:
- WHERE clause added to query builder
- Applied to all query paths (list, search, filter, sort, paginate)
- Pagination totals correct
- PHPCS 0 errors
- PHPStan L8 exit 0

**Estimate**: 1 hour

---

## T004: Update `AcrossAI_Sitewide_Abilities_Controller` for 404 Check

**Objective**: Return HTTP 404 when a protected slug is requested via the single-ability endpoint.

**Subtasks**:

- [ ] Open `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php`
- [ ] Add use statement at top:
  ```php
  use AcrossAI\AbilitiesManager\Utilities\AcrossAI_Protected_Abilities;
  ```

- [ ] Locate the `get_ability( $request )` method (or similar single-ability endpoint handler)
- [ ] Identify where the slug is extracted from `$request`:
  ```php
  $slug = isset( $request['slug'] ) ? $request['slug'] : '';
  // OR
  $slug = $request->get_param( 'slug' );
  // etc. — exact pattern from T001 research
  ```

- [ ] Add 404 check immediately after slug extraction, BEFORE any DB lookup:
  ```php
  if ( AcrossAI_Protected_Abilities::is_protected( $slug ) ) {
      return new WP_Error(
          'ability_not_found',
          __( 'Ability not found.', 'acrossai-abilities-manager' ),
          [ 'status' => 404 ]
      );
  }
  ```
  (Adjust error message format to match existing 404 patterns in codebase)

- [ ] Verify error response format:
  - Check other controllers for consistent 404 responses
  - Use same format (ensure i18n string if not already done)

- [ ] Test locally (manual or unit test):
  - `GET /wp-json/acrossai/v1/sitewide/abilities/mcp-adapter/discover-abilities` → HTTP 404
  - `GET /wp-json/acrossai/v1/sitewide/abilities/some-real-ability` → HTTP 200

- [ ] Run PHPCS: `composer phpcs -- includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php`
  - Pass: 0 errors

- [ ] Run PHPStan L8: `composer phpstan -- includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php`
  - Pass: exit code 0

**Definition of Done**:
- 404 check added before DB lookup
- Check uses `AcrossAI_Protected_Abilities::is_protected()`
- Error format consistent with codebase
- PHPCS 0 errors
- PHPStan L8 exit 0

**Estimate**: 45 minutes

---

## T005: Unit Tests for `AcrossAI_Protected_Abilities`

**Objective**: Verify utility class behaves correctly for default and custom protected slugs.

**Subtasks**:

- [ ] Create test file (or extend existing): `tests/phpunit/test-protected-abilities.php`
- [ ] Add test class with setup/teardown:
  ```php
  class Test_Protected_Abilities extends WP_UnitTestCase {
      protected function setUp() {
          parent::setUp();
          // Reset filters if needed
      }
  }
  ```

- [ ] Write test: `test_get_protected_slugs_returns_defaults()`
  ```php
  $slugs = AcrossAI_Protected_Abilities::get_protected_slugs();
  $this->assertIsArray( $slugs );
  $this->assertContains( 'mcp-adapter/discover-abilities', $slugs );
  $this->assertContains( 'mcp-adapter/execute-ability', $slugs );
  $this->assertContains( 'mcp-adapter/get-ability-info', $slugs );
  $this->assertCount( 3, $slugs );
  ```

- [ ] Write test: `test_is_protected_true_for_system_slugs()`
  ```php
  $this->assertTrue( AcrossAI_Protected_Abilities::is_protected( 'mcp-adapter/discover-abilities' ) );
  $this->assertTrue( AcrossAI_Protected_Abilities::is_protected( 'mcp-adapter/execute-ability' ) );
  $this->assertTrue( AcrossAI_Protected_Abilities::is_protected( 'mcp-adapter/get-ability-info' ) );
  ```

- [ ] Write test: `test_is_protected_false_for_other_slugs()`
  ```php
  $this->assertFalse( AcrossAI_Protected_Abilities::is_protected( 'some-plugin/ability' ) );
  $this->assertFalse( AcrossAI_Protected_Abilities::is_protected( '' ) );
  ```

- [ ] Write test: `test_filter_adds_custom_protected_slug()`
  ```php
  add_filter( 'acrossai_abilities_manager_protected_slugs', function( $slugs ) {
      $slugs[] = 'my-plugin/internal-helper';
      return $slugs;
  } );
  
  $this->assertTrue( AcrossAI_Protected_Abilities::is_protected( 'my-plugin/internal-helper' ) );
  // Verify defaults still present
  $this->assertTrue( AcrossAI_Protected_Abilities::is_protected( 'mcp-adapter/discover-abilities' ) );
  ```

- [ ] Write test: `test_filter_can_replace_protected_list()`
  ```php
  add_filter( 'acrossai_abilities_manager_protected_slugs', function( $slugs ) {
      return [ 'custom/internal' ];
  } );
  
  $this->assertTrue( AcrossAI_Protected_Abilities::is_protected( 'custom/internal' ) );
  $this->assertFalse( AcrossAI_Protected_Abilities::is_protected( 'mcp-adapter/discover-abilities' ) );
  // (If spec says filter replaces; adjust if spec says filter merges)
  ```

- [ ] Run tests (if bootstrap available):
  ```bash
  composer test:phpunit -- tests/phpunit/test-protected-abilities.php
  ```
  - Pass: All tests pass
  - If PHPUnit blocked (pre-existing), document as known limitation

**Definition of Done**:
- All 5 test cases pass
- Filter behavior documented (merge vs. replace)
- Tests cover happy path + edge cases

**Estimate**: 1 hour (or 15 minutes if tests are blocked by missing bootstrap)

---

## T006: Integration Tests for Query-Layer Filtering

**Objective**: Verify protected slugs are excluded from REST list queries.

**Subtasks**:

- [ ] Create or extend test file: `tests/phpunit/test-abilities-registry-query-protected.php`
  (or add test cases to existing query test file)

- [ ] Write test: `test_query_excludes_protected_slugs()`
  - Mock or use live registry with known abilities
  - Call `AcrossAI_Ability_Registry_Query::instance()->query( [ ... ] )`
  - Verify results do NOT include any of:
    - `mcp-adapter/discover-abilities`
    - `mcp-adapter/execute-ability`
    - `mcp-adapter/get-ability-info`

- [ ] Write test: `test_pagination_total_excludes_protected()`
  - Query with protected slugs in registry
  - Check `$query->get_total()` or equivalent
  - Verify total = total_registered − 3 (protected count)

- [ ] Write test: `test_search_with_protected_slug()`
  - Search for "adapter"
  - Verify NO results returned (protected slugs excluded)
  - OR verify search only matches non-protected that contain "adapter"

- [ ] Write test: `test_source_filter_with_protected_exclusion()`
  - Filter by source (e.g., "plugin")
  - Verify protected slugs excluded even if they match source filter

- [ ] Write test: `test_pagination_headers_accurate()`
  - Make REST request to `GET /wp-json/acrossai/v1/sitewide/abilities`
  - Parse response headers: `X-WP-Total`, `X-WP-TotalPages`
  - Verify totals exclude protected slugs

- [ ] Run tests (if available):
  ```bash
  composer test:phpunit -- tests/phpunit/test-abilities-registry-query-protected.php
  ```
  - Pass: All tests pass

**Definition of Done**:
- Query-layer filtering verified
- Pagination totals correct
- All tests pass

**Estimate**: 1.5 hours (or blocked if PHPUnit bootstrap missing)

---

## T007: Security Review

**Objective**: Verify no new security vulnerabilities introduced.

**Checklist**:

- [ ] **Input Sanitization**: Verify `sanitize_ability_slug()` is applied to `$request` slug before comparison
  - Check: Do controllers call sanitize before passing to query/comparison?
  - Pass: Slugs are sanitized per SEC-01 before reaching protected-abilities check

- [ ] **SQL Injection**: Verify no raw SQL in new code
  - Check: All WHERE clauses built via query builder (BerlinDB) or `$wpdb->prepare()`
  - Pass: No raw SQL; query builder handles escaping

- [ ] **Output Escaping**: Verify no unescaped output
  - Check: Slugs used only for in-memory comparison; never echoed to HTML
  - Check: Error messages in REST responses are properly escaped
  - Pass: No unescaped output

- [ ] **Capability Checks**: Verify REST endpoints are protected
  - Check: Does `AcrossAI_Sitewide_Abilities_Controller::check_permission()` still gate access?
  - Pass: Protected-abilities feature does not bypass existing capability checks

- [ ] **Multisite Isolation**: Verify no cross-site data leaks
  - Check: Protected list is hardcoded (same for all sites)
  - Check: No shared state or per-site cache
  - Pass: Multisite safe; each site sees same protected list

- [ ] **Filter Hook Safety**: Verify filter cannot introduce injection
  - Check: Filter input: array of slugs; output: array of slugs
  - Check: No eval, exec, or unsafe expansion of filter output
  - Pass: Filter is safe

- [ ] **No Credential Exposure**: Verify no hardcoded secrets, API keys, or credentials
  - Pass: No credentials in code

- [ ] **File Operations**: Verify no unsafe file handling
  - Pass: No file operations; static utility only

- [ ] **Database Access**: Verify all DB access goes through safe layer
  - Pass: All DB access via `AcrossAI_Ability_Registry_Query` or unchanged controllers

**Sign-Off**:
- [ ] Security review completed (all items checked)
- [ ] No new vulnerabilities identified
- [ ] Risk level: **Low** (read-only feature; no new attack surface)

**Estimate**: 30 minutes (checklist review)

---

## T008: Code Quality & Standards

**Objective**: Ensure all code passes PHPCS, PHPStan, and documentation standards.

**Tasks**:

- [ ] Run full PHPCS scan on affected files:
  ```bash
  composer phpcs -- includes/Utilities/AcrossAI_Protected_Abilities.php includes/Utilities/AcrossAI_Ability_Registry_Query.php includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php
  ```
  - Pass: 0 errors (document any suppressions with justification)

- [ ] Run full PHPStan L8 on affected files:
  ```bash
  composer phpstan -- includes/Utilities/AcrossAI_Protected_Abilities.php includes/Utilities/AcrossAI_Ability_Registry_Query.php includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php
  ```
  - Pass: exit code 0

- [ ] Verify no deprecated functions used (WP 7.0+):
  - Check: `apply_filters()`, `in_array()`, `new WP_Error()` all current
  - Pass: No deprecated functions

- [ ] Verify consistent naming conventions:
  - Variables: `$protected_slugs`, `$default_slugs`, `$slug` (no abbreviations)
  - Methods: `get_protected_slugs()`, `is_protected()` (verb-first, descriptive)
  - Classes: `AcrossAI_Protected_Abilities` (prefix, PascalCase, no underscores except prefix)

- [ ] Verify inline documentation:
  - [ ] Class docstring: purpose and usage
  - [ ] Method docstrings: @param, @return, @since
  - [ ] Filter docstring: description, @param, @since, usage example
  - [ ] Code comments: explain WHY (not WHAT) for non-obvious logic

- [ ] Verify error messages are clear and user-friendly:
  - 404 message: "Ability not found." (no technical jargon)

- [ ] Verify i18n strings are translatable:
  - Check: All user-facing strings wrapped in `__()`, `_e()`, or `esc_html__()`
  - Pass: All strings properly i18n'd

**Sign-Off**:
- [ ] PHPCS 0 errors
- [ ] PHPStan L8 exit 0
- [ ] Naming conventions consistent
- [ ] Inline documentation complete
- [ ] i18n verified

**Estimate**: 45 minutes

---

## T009: Manual Acceptance Testing

**Objective**: Verify spec requirements are met in a running WordPress environment.

**Test Setup**:
- Local WordPress 7.0+ environment with AcrossAI Abilities Manager installed
- MCP adapter plugin installed (provides system abilities)
- Fresh browser session (no caching)

**Test Cases**:

**Scenario A: Abilities Manager UI — List Excludes Protected Abilities**
- [ ] Navigate to `/wp-admin/admin.php?page=acrossai-abilities-manager`
- [ ] Verify page loads without errors
- [ ] Verify list displays abilities
- [ ] Search or scroll list for "mcp-adapter"
- [ ] **PASS**: No results containing "mcp-adapter/discover-abilities", "mcp-adapter/execute-ability", or "mcp-adapter/get-ability-info"
- [ ] Note total ability count
- [ ] Verify total = (all registered abilities − 3 protected)

**Scenario B: Search for Protected Abilities**
- [ ] In Abilities Manager page, use search box
- [ ] Search for "discover"
- [ ] **PASS**: 0 results returned
- [ ] Search for "adapter"
- [ ] **PASS**: 0 results returned
- [ ] Search for non-protected ability (e.g., "core")
- [ ] **PASS**: Results shown (if any core abilities registered)

**Scenario C: REST Endpoint — List Excludes Protected Abilities**
- [ ] In browser console or cURL:
  ```bash
  curl -s http://localhost/wp-json/acrossai/v1/sitewide/abilities | jq '.[] | .slug' | grep mcp-adapter
  ```
- [ ] **PASS**: No results (protected slugs not in list)
- [ ] Check `X-WP-Total` header:
  ```bash
  curl -i -s http://localhost/wp-json/acrossai/v1/sitewide/abilities | grep X-WP-Total
  ```
- [ ] **PASS**: Total reflects excluded protected slugs

**Scenario D: REST Endpoint — Single Ability 404**
- [ ] In browser console or cURL:
  ```bash
  curl -i http://localhost/wp-json/acrossai/v1/sitewide/abilities/mcp-adapter/discover-abilities
  ```
- [ ] **PASS**: HTTP 404 response with error message "Ability not found."
- [ ] Request another protected slug:
  ```bash
  curl -i http://localhost/wp-json/acrossai/v1/sitewide/abilities/mcp-adapter/execute-ability
  ```
- [ ] **PASS**: HTTP 404
- [ ] Request a non-protected ability:
  ```bash
  curl -i http://localhost/wp-json/acrossai/v1/sitewide/abilities/some-plugin/my-ability
  ```
- [ ] **PASS**: HTTP 200 with ability data (if ability exists)

**Scenario E: Custom Protected Slug (via Filter)**
- [ ] Create a test plugin with filter:
  ```php
  add_filter( 'acrossai_abilities_manager_protected_slugs', function( $slugs ) {
      $slugs[] = 'test-plugin/internal';
      return $slugs;
  } );
  ```
- [ ] Activate test plugin
- [ ] In REST: `curl http://localhost/wp-json/acrossai/v1/sitewide/abilities/test-plugin/internal`
- [ ] **PASS**: HTTP 404 (custom slug protected)
- [ ] In Abilities Manager UI: search for "internal"
- [ ] **PASS**: 0 results

**Scenario F: Write Operations Unaffected**
- [ ] In Abilities Manager page, find a non-protected ability
- [ ] Attempt to toggle "Allow on this site"
- [ ] **PASS**: Toggle works; no errors
- [ ] Attempt to change override settings
- [ ] **PASS**: Save successful
- [ ] (Protected abilities are not visible in UI, so no need to test write on them)

**Sign-Off**:
- [ ] All 6 scenarios passed
- [ ] No errors in browser console or server logs
- [ ] Spec acceptance criteria verified

**Estimate**: 1 hour (manual testing)

---

## T010: Acceptance Criteria Verification

**Objective**: Final checklist against spec success criteria.

**Criteria**:

- [ ] **✅ User Visibility**: MCP adapter abilities are completely absent from the Abilities Manager UI
  - Test: Search for "mcp-adapter" → 0 results
  - Test: Total count reflects exclusion

- [ ] **✅ REST API Compliance**: GET /sitewide/abilities list excludes protected slugs; GET /sitewide/abilities/{protected-slug} returns 404
  - Test: `curl /wp-json/.../abilities | grep mcp-adapter` → empty
  - Test: `curl /wp-json/.../abilities/mcp-adapter/discover-abilities` → 404

- [ ] **✅ Pagination Accuracy**: Total counts, page counts reflect only non-protected abilities
  - Test: `X-WP-Total` header excludes protected
  - Test: Pagination math correct

- [ ] **✅ Extensibility**: Third-party plugins can extend protected list via filter
  - Test: Custom slug via filter → excluded from list and 404 on GET

- [ ] **✅ Server-Side Only**: All filtering in PHP REST layer; no frontend duplicate logic
  - Test: Query builder applies WHERE; no JavaScript filtering added

- [ ] **✅ Backward Compatibility**: Write endpoints and other functionality unaffected
  - Test: Save override on non-protected ability works
  - Test: No errors in admin

- [ ] **✅ Code Quality**: Zero PHPCS violations, PHPStan L8 pass, inline documentation, security review passed
  - Test: `composer phpcs` → 0 errors
  - Test: `composer phpstan` → exit 0
  - Test: Inline docs complete
  - Test: Security checklist passed

**Sign-Off**:
- [ ] All 7 success criteria verified
- [ ] Feature ready for merge

**Estimate**: 30 minutes (verification checklist)

---

## Summary

| Task | Objective | Estimate | Dependencies |
|---|---|---|---|
| T001 | Research & verify structure | 1 session | None |
| T002 | Create utility class | 30 min | T001 |
| T003 | Update query builder | 1 hour | T001, T002 |
| T004 | Add 404 check to controller | 45 min | T001, T002 |
| T005 | Unit tests for utility | 1 hour | T002 |
| T006 | Integration tests for query | 1.5 hours | T003 |
| T007 | Security review | 30 min | T002, T003, T004 |
| T008 | Code quality checks | 45 min | T002, T003, T004 |
| T009 | Manual acceptance testing | 1 hour | T002, T003, T004 |
| T010 | Acceptance criteria verification | 30 min | T009 |
| **Total** | | **~8 hours** | Sequential phases |

---

## Rollback & Contingency

**If tests fail on T005/T006** (pre-existing PHPUnit bootstrap missing):
- Document as known limitation (pre-existing)
- Proceed to T007 (manual testing replaces unit tests)
- No blocker to merge if manual tests pass

**If query builder API differs from expectations (T003)**:
- Check query builder docs or existing WHERE clause usage in codebase
- Adjust WHERE implementation to match API
- Re-run PHPCS + PHPStan after fix
- No architectural blocker

**If 404 response format conflicts (T004)**:
- Find existing 404 response in codebase (search for 'ability_not_found' or 404 error)
- Match format exactly
- No functional blocker

**If multisite fails (T009)**:
- Likely pre-existing issue (not feature-005 regression)
- Document and escalate
- Feature does not introduce cross-site state

---

## Done Definition

**STOP** if any of these are FALSE:
- [ ] PHPCS: 0 errors
- [ ] PHPStan L8: exit 0
- [ ] Security review: passed (no HIGH vulnerabilities)
- [ ] Manual tests: all 6 scenarios pass
- [ ] Acceptance criteria: all 7 verified

**PROCEED TO MERGE** when:
- [ ] All above TRUE
- [ ] Code review approved (if required)
- [ ] Commit message references spec, branch, and tasks completed
