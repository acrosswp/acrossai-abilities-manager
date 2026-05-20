# Plan: WPB Access Control Stable Release Upgrade (Feature 007)

**Branch**: `007-upgrade-access-control`  
**Created**: 2026-05-20  
**Status**: Ready for Implementation  
**Version**: 1.0

---

## Planning Summary

Feature 007 is a dependency chore that upgrades `wpboilerplate/wpb-access-control` from unstable `dev-main` to stable `^1.0`. The upgrade carries minimal code risk (no plugin classes change) but requires rigorous validation of API compatibility and permission enforcement behavior. The design strategy combines automated test execution (5 test scenarios) with manual verification steps to ensure the upgrade does not introduce silent regressions in access control enforcement—the most critical integration point.

**Scope**: Composer dependency update + integration validation. No new classes, no feature changes, no architecture refactors.

**Key Success Metrics**:
- Composer constraint updates to `^1.0`
- All P1 tests pass (dependency resolution, permission callbacks, user access checks, integration)
- Fail-open admin notice verified working
- No permission enforcement regressions on staging

---

## Design Approach

### API Compatibility Validation Strategy

The upgrade assumes the latest ^1.0 tag is compatible with the current locked dev-main commit (`2a9ddbdf...`). This assumption is validated in three phases:

#### Phase 0: Pre-Update Audit (Risk Mitigation)
**Owner**: Code Reviewer  
**Artifact**: Changelog review document

1. **Inspect wpb-access-control GitHub changelog** between pinned commit and latest 1.x.x release:
   - Scan for breaking changes to `AccessControlManager::get_query()`, `get_rule()`, `user_has_access()`
   - Scan for changes to comparison operators (loose → strict, or vice versa; SEC-04 vulnerability)
   - Scan for changes to multisite table scoping (SEC-03)
   - Document any API changes that require code updates

2. **Verify public API signature compatibility**:
   - `AccessControlManager::get_manager(): ?AccessControlManager` — return type must remain nullable
   - `AccessControlManager::user_has_access( $user_id, $category, $slug ): bool` — must return boolean, not null or mixed
   - `AccessControlManager::get_query(): QueryBuilder` — must return QueryBuilder or compatible

3. **Document findings** in a pre-update checklist. If breaking changes are found, escalate to stakeholder before proceeding (out-of-scope for Feature 007).

#### Phase 1: Automated Dependency Update & Resolution Tests (P1 — Blocking)
**Owner**: Developer  
**Artifacts**: Updated composer.json, composer.lock, test results

Phases 1, 2, 3, 4 run sequentially in priority order. If any P1 test fails, halt and debug before proceeding to P2.

1. **Test Scenario 1** — Dependency Resolution and Lock File Update
   - Update composer.json: change `"dev-main"` to `"^1.0"`
   - Run `composer update wpboilerplate/wpb-access-control` in local environment
   - **Acceptance**: composer.lock contains a 1.x.x version tag (not dev-main), `composer validate` passes, `composer install --no-dev` on clean environment succeeds

2. **Test Scenario 2** — Permission Callback Injection Still Works
   - Load plugin with upgraded library
   - Register a test ability with an AC rule via the Access Control tab in Manager UI
   - Verify `AcrossAI_Sitewide_Access_Control::get_manager()` returns non-null instance
   - Call `inject_override_args()` and confirm `$args['permission_callback']` is set and callable
   - **Acceptance**: permission_callback is injected without errors; DEC-PERM-CB pattern holds

3. **Test Scenario 3** — User Access Checks Still Work
   - Call `$manager->user_has_access( $user_id, 'acrossai-abilities', $slug )` with multiple user roles
   - Verify return value is boolean (not null or mixed)
   - Verify logic is consistent: allowed user returns true, denied user returns false
   - **Acceptance**: Boolean return type holds; access logic is predictable

4. **Test Scenario 5** — Full Integration Tests Pass (P1 subset)
   - Run PHPUnit test suite with upgraded dependency (if applicable)
   - Run any existing integration tests that exercise permission enforcement
   - **Acceptance**: All tests pass; no regressions detected

#### Phase 2: User-Facing Validation (P2 — Informational)
**Owner**: QA / Staging  
**Artifact**: Verification checklist

1. **Test Scenario 4** — Admin Notice Displays When Library Absent
   - Simulate library absence (e.g., move vendor/wpboilerplate/wpb-access-control temporarily)
   - Access WordPress admin dashboard
   - Verify notice displays: "wpb-access-control library is not available; ability access control is inactive"
   - Restore library and verify notice disappears
   - **Acceptance**: DEC-FAIL-OPEN-NOTICE pattern holds; fail-open is paired with visible warning

#### Phase 3: Integration & Deployment Validation
**Owner**: DevOps / Release Manager  
**Artifact**: Deployment checklist

1. **Staging environment validation**:
   - Deploy Feature 007 to staging
   - Verify plugin loads without errors
   - Manually test ability access control with AC rules on staging (e.g., "Can admin see this ability?" checks)
   - Verify no multisite regressions (if applicable)

2. **Production readiness**:
   - Confirm all tests passed
   - Confirm changelog reflects dependency upgrade
   - Confirm no breaking changes documented in migration notes

---

## Phases & Stages

### Stage 1: Pre-Update Audit (Risk Mitigation)
**Duration**: ~30 min  
**Deliverable**: Pre-update checklist

| Task | Owner | Status | Blockers |
|------|-------|--------|----------|
| Inspect wpb-ac changelog on GitHub | Reviewer | Pending | None |
| Verify API signatures (get_query, user_has_access, get_manager) | Reviewer | Pending | Changelog inspection |
| Document breaking changes (if any) | Reviewer | Pending | Changelog inspection |
| Decision: Proceed or escalate? | PM | Pending | Changelog review |

**Success Criteria**: Pre-update checklist signed off; no blocking breaking changes found.

---

### Stage 2: Dependency Resolution & P1 Tests (Blocking)
**Duration**: ~2 hours  
**Deliverable**: Updated composer.json/lock, passing P1 tests

| Task | Owner | Priority | Status | Blockers |
|------|-------|----------|--------|----------|
| **T001**: Update composer.json to `^1.0` | Dev | P1 | Pending | Pre-update checklist passed |
| **T002**: Run `composer update wpboilerplate/wpb-access-control` | Dev | P1 | Pending | T001 |
| **T003**: Verify composer.lock locked to 1.x.x tag | QA | P1 | Pending | T002 |
| **T004**: Verify Test Scenario 1 (dependency resolution) | QA | P1 | Pending | T002 |
| **T005**: Verify Test Scenario 2 (permission callback injection) | QA | P1 | Pending | T002 |
| **T006**: Verify Test Scenario 3 (user access checks) | QA | P1 | Pending | T002 |
| **T007**: Run full PHPUnit test suite (Test Scenario 5) | QA | P1 | Pending | T002 |
| **T008**: Debug + fix any P1 failures | Dev | P1 | Pending | T005–T007 |

**Success Criteria**: All P1 tests pass; no API incompatibilities detected; Test Scenarios 1, 2, 3, 5 validated.

---

### Stage 3: Fail-Open Verification (P2)
**Duration**: ~30 min  
**Deliverable**: Admin notice verification checklist

| Task | Owner | Priority | Status | Blockers |
|------|-------|----------|--------|----------|
| **T009**: Simulate library absence (move vendor directory) | QA | P2 | Pending | Stage 2 passed |
| **T010**: Verify admin notice displays in wp-admin | QA | P2 | Pending | T009 |
| **T011**: Verify notice not visible to non-admin users | QA | P2 | Pending | T009 |
| **T012**: Restore library and verify notice disappears | QA | P2 | Pending | T010 |

**Success Criteria**: Admin notice displays when library absent; hidden from non-admins; disappears when library restored. DEC-FAIL-OPEN-NOTICE validated.

---

### Stage 4: Staging & Production Deployment
**Duration**: ~1 hour  
**Deliverable**: Deployment checklist, release notes

| Task | Owner | Priority | Status | Blockers |
|------|-------|----------|--------|----------|
| **T013**: Deploy Feature 007 to staging environment | DevOps | P1 | Pending | Stage 2 passed |
| **T014**: Verify plugin loads without errors on staging | QA | P1 | Pending | T013 |
| **T015**: Manual AC enforcement test on staging (admin-only ability) | QA | P1 | Pending | T013 |
| **T016**: Manual multisite validation (if applicable) | QA | P1 | Pending | T013 |
| **T017**: Update changelog with upgrade note | Dev | P1 | Pending | T014 |
| **T018**: Final approval for production deployment | PM | P1 | Pending | T014–T017 |
| **T019**: Deploy to production | DevOps | P1 | Pending | T018 |

**Success Criteria**: Staging tests pass; changelog updated; approved for production; production deployment successful.

---

## Task Decomposition

### Pre-Update Audit Tasks

#### T-AUDIT-01: Inspect wpb-access-control Changelog
**Owner**: Code Reviewer  
**Effort**: 15 min  
**Input**: GitHub repo URL: https://github.com/WPBoilerplate/wpb-access-control  
**Process**:
1. Navigate to GitHub releases page
2. Find tag corresponding to latest 1.x.x release (e.g., v1.0.0, v1.1.0)
3. Compare commit date and hash with pinned commit `2a9ddbdf...`
4. Review release notes and commit log between pinned commit and latest 1.x tag
5. Scan for:
   - API changes to `AccessControlManager` public methods
   - Changes to comparison operators (loose vs. strict)
   - Changes to multisite table scoping
   - Deprecations or breaking changes

**Deliverable**: Pre-update checklist document listing any breaking changes found.

#### T-AUDIT-02: Verify Public API Signatures
**Owner**: Code Reviewer  
**Effort**: 10 min  
**Input**: Changelog findings + GitHub source code  
**Process**:
1. Inspect latest 1.x tag's `AccessControlManager` class:
   - Confirm `get_manager(): ?AccessControlManager` signature (or compatible)
   - Confirm `user_has_access( $user_id, $category, $slug ): bool` return type
   - Confirm `get_query(): QueryBuilder` (or compatible) return type
2. Check for deprecated methods or parameter changes
3. Cross-reference with current usage in `AcrossAI_Sitewide_Access_Control.php`

**Deliverable**: API compatibility matrix (method, current signature, 1.x signature, compatible: yes/no).

#### T-AUDIT-03: Document Findings & Decision
**Owner**: Code Reviewer + PM  
**Effort**: 5 min  
**Input**: Changelog, API matrix  
**Process**:
1. Summarize findings: "No breaking changes found between commit X and latest 1.x.y tag"
2. Decision: Proceed to Stage 2 (T001) or escalate?
3. If blocking changes: create out-of-scope issue for Feature 007a (AC library compatibility layer)

**Deliverable**: Pre-update checklist signed off; green light to proceed or hold/escalate.

---

### Dependency Resolution & P1 Tests

#### T001: Update composer.json
**Owner**: Developer  
**Effort**: 2 min  
**Process**:
1. Open `composer.json` in editor
2. Locate `"wpboilerplate/wpb-access-control": "dev-main"`
3. Change to `"wpboilerplate/wpb-access-control": "^1.0"`
4. Save file

**Deliverable**: composer.json with constraint updated.

#### T002: Run Dependency Resolution
**Owner**: Developer  
**Effort**: 3 min  
**Command**: 
```bash
cd /path/to/plugin
composer update wpboilerplate/wpb-access-control
```

**Expected Output**:
```
Loading composer repositories with package information
Updating dependencies
Lock file operations: 1 file changed, 1 insertion(+), 1 deletion(-)
Writing lock file
Generating autoload files
```

**Deliverable**: Updated composer.lock with wpb-ac locked to 1.x.x tag.

#### T003: Verify Lock File
**Owner**: QA  
**Effort**: 2 min  
**Command**: 
```bash
grep -A 5 '"name": "wpboilerplate/wpb-access-control"' composer.lock
```

**Expected Output**: Lock entry shows version tag (e.g., `"version": "1.0.0"` or `"version": "1.1.5"`), NOT `dev-main`.

**Deliverable**: Screenshot or log showing lock constraint.

#### T004: Validate Composer
**Owner**: QA  
**Effort**: 1 min  
**Command**: 
```bash
composer validate
```

**Expected Output**:
```
./composer.json is valid
```

**Deliverable**: Validation report.

#### T005: Test Scenario 1 — Dependency Resolution
**Owner**: QA  
**Effort**: 5 min  
**Process**:
1. Clean install: `rm -rf vendor/; composer install --no-dev`
2. Verify wpb-access-control installs from lock file
3. Verify no errors or warnings
4. Check `vendor/wpboilerplate/wpb-access-control/composer.json` exists and version matches lock

**Deliverable**: Clean install verification log.

**Acceptance Criteria**:
- ✅ Lock file contains 1.x.x version tag
- ✅ `composer validate` passes
- ✅ Clean install succeeds with no errors

#### T006: Test Scenario 2 — Permission Callback Injection
**Owner**: QA  
**Effort**: 15 min  
**Process**:
1. Start WordPress with upgraded plugin
2. Navigate to AcrossAI Abilities Manager → Access Control tab
3. Create an AC rule for a test ability (e.g., "only admins")
4. Register a test ability via REST API or WP-CLI
5. Verify in PHP console or debugger:
   ```php
   $ac = AcrossAI_Sitewide_Access_Control::instance();
   $manager = $ac->get_manager();
   var_dump( $manager ); // Should be non-null AccessControlManager
   $args = [];
   $ac->inject_override_args( 'test-slug', $args );
   var_dump( isset( $args['permission_callback'] ) ); // Should be true
   var_dump( is_callable( $args['permission_callback'] ) ); // Should be true
   ```

**Deliverable**: Test log showing manager instance and permission_callback injection.

**Acceptance Criteria**:
- ✅ `get_manager()` returns non-null instance
- ✅ `inject_override_args()` sets permission_callback
- ✅ permission_callback is callable without errors
- ✅ DEC-PERM-CB pattern holds

#### T007: Test Scenario 3 — User Access Checks
**Owner**: QA  
**Effort**: 10 min  
**Process**:
1. Using the AC rule from T006, test access checks with different users:
   ```php
   $manager = $ac->get_manager();
   
   // Admin user
   $admin_result = $manager->user_has_access( $admin_id, 'acrossai-abilities', 'test-slug' );
   var_dump( $admin_result ); // Should be true (boolean)
   
   // Subscriber user
   $sub_result = $manager->user_has_access( $subscriber_id, 'acrossai-abilities', 'test-slug' );
   var_dump( $sub_result ); // Should be false (boolean)
   
   // Unknown rule
   $unknown = $manager->user_has_access( $admin_id, 'acrossai-abilities', 'nonexistent' );
   var_dump( $unknown ); // Should be false (boolean, deny by default)
   ```

**Deliverable**: Test log showing user_has_access results for multiple users and roles.

**Acceptance Criteria**:
- ✅ Return type is always boolean (not null, not mixed)
- ✅ Allowed user returns true
- ✅ Denied user returns false
- ✅ Unknown rule returns false (deny by default)

#### T008: Test Scenario 5 — Integration Tests
**Owner**: QA  
**Effort**: 20 min  
**Command** (if PHPUnit configured):
```bash
npm test
# or
composer test
# or
./vendor/bin/phpunit
```

**Expected Output**: All tests pass or known failures are documented.

**Deliverable**: Test report showing pass/fail status for each test.

**Acceptance Criteria**:
- ✅ Full plugin test suite passes
- ✅ Permission enforcement tests pass
- ✅ No regressions compared to previous run

#### T009–T012: Test Scenario 4 — Fail-Open Admin Notice
**Owner**: QA  
**Effort**: 20 min  
**Process**:
1. Simulate library absence:
   ```bash
   mv vendor/wpboilerplate/wpb-access-control vendor/wpboilerplate/wpb-access-control.bak
   ```
2. Access WordPress admin dashboard as admin user
3. Inspect page source or admin notice area
4. Verify notice displays: "wpb-access-control library is not available..."
5. Check notice is not visible to non-admin user (switch role or open in incognito)
6. Restore library:
   ```bash
   mv vendor/wpboilerplate/wpb-access-control.bak vendor/wpboilerplate/wpb-access-control
   ```
7. Refresh admin page; verify notice gone

**Deliverable**: Screenshots of admin notice (present when absent, gone when present), non-admin verification.

**Acceptance Criteria**:
- ✅ Notice displays when library absent (DEC-FAIL-OPEN-NOTICE)
- ✅ Notice is gated to admins only (`manage_options` capability)
- ✅ Notice contains clear explanation of what is inactive
- ✅ Notice disappears when library restored

---

### Risk Mitigation Tasks

#### T-RISK-01: Changelog Review for Breaking Changes
**Owner**: Code Reviewer  
**Effort**: 20 min  
**Process**: Combined with T-AUDIT-01, T-AUDIT-02. Review and document any behavioral changes in the library.

**Deliverable**: Risk assessment: "No breaking changes detected" or "Breaking changes identified: [list]".

#### T-RISK-02: Multisite Validation (If Applicable)
**Owner**: QA  
**Effort**: 15 min  
**Process**:
1. If plugin has multisite sandbox or staging:
   - Deploy Feature 007 to multisite environment
   - Create AC rules on primary site
   - Verify rules do not bleed to secondary site (SEC-03)
   - Test permission checks per site
2. If no multisite environment:
   - Document as "multisite validation not tested; recommend testing in staging"

**Deliverable**: Multisite test report or "not tested" note for release notes.

#### T-RISK-03: Security Review — Strict Type Comparison (SEC-04)
**Owner**: Security Reviewer  
**Effort**: 10 min  
**Process**:
1. Review wpb-access-control's user_has_access() method in latest 1.x tag
2. Scan for loose comparison operators (==, !=) vs. strict (===, !==)
3. If found: verify no bypasses (e.g., `if ( $user_id == 0 )` could be bypassed by non-numeric strings)
4. Document findings

**Deliverable**: Security review log: "Strict comparison verified in user_has_access()" or "Potential loose comparison issue: [details]".

**Acceptance Criteria**:
- ✅ No loose comparison vulnerabilities in AC library's access checks
- ✅ SEC-04 constraint maintained

---

### Deployment Tasks

#### T013: Deploy to Staging
**Owner**: DevOps  
**Effort**: 10 min  
**Process**:
1. Merge Feature 007 branch to staging branch
2. Deploy to staging environment
3. Verify plugin activates without PHP errors

**Deliverable**: Staging deployment confirmation.

#### T014: Verify Plugin Load
**Owner**: QA  
**Effort**: 5 min  
**Process**:
1. Access staging WordPress admin
2. Navigate to Plugins → check AcrossAI Abilities Manager is active
3. Check error logs: `wp-content/debug.log` should show no fatal errors from plugin
4. Navigate to AcrossAI Manager dashboard; verify no 500 errors

**Deliverable**: Plugin activation verification log.

#### T015: Manual AC Enforcement Test
**Owner**: QA  
**Effort**: 10 min  
**Process**:
1. Create an admin-only AC rule for a test ability
2. Access ability endpoint as subscriber (should be denied)
3. Access ability endpoint as admin (should be allowed)
4. Verify permission enforcement is working correctly post-upgrade

**Deliverable**: AC enforcement test log (deny subscriber, allow admin).

#### T016: Multisite Staging Test (If Applicable)
**Owner**: QA  
**Effort**: 15 min  
**Process**: Same as T-RISK-02 but on staging multisite environment.

**Deliverable**: Multisite staging test report.

#### T017: Update Changelog
**Owner**: Developer  
**Effort**: 5 min  
**File**: `CHANGELOG.md` or release notes  
**Entry**:
```
## [X.Y.Z] - 2026-05-20

### Changed
- Upgraded `wpboilerplate/wpb-access-control` from dev-main to ^1.0 for stable versioning and predictable dependency management.
- Verified API compatibility; no breaking changes detected.
- All ability access control enforcement tests pass with upgraded library.

### Notes
- Upgrade does not require code changes to the plugin.
- Permission callback injection pattern (DEC-PERM-CB) verified working.
- Fail-open admin notice (DEC-FAIL-OPEN-NOTICE) verified working.
```

**Deliverable**: Updated CHANGELOG.md

#### T018: Production Approval
**Owner**: PM / Release Manager  
**Effort**: 5 min  
**Process**:
1. Review all Stage 2–4 test results
2. Confirm no blockers or regressions
3. Approve Feature 007 for production deployment

**Deliverable**: Signed-off release approval.

#### T019: Production Deployment
**Owner**: DevOps  
**Effort**: 10 min  
**Process**:
1. Merge staging to main/production branch
2. Deploy to production
3. Verify plugin loads
4. Monitor error logs for 1 hour post-deployment

**Deliverable**: Production deployment confirmation + post-deployment health check.

---

## Integration Validation Checklist

### Pre-Deployment Validation (Staging)

- [ ] **T001–T004**: Composer dependency resolved to ^1.0 with lock file updated
- [ ] **T005**: Clean install succeeds (dependency resolution test passes)
- [ ] **T006**: Permission callback injection works (DEC-PERM-CB validated)
- [ ] **T007**: User access checks return boolean and enforce correctly (DEC-PERM-CB + logic validated)
- [ ] **T008**: Full integration test suite passes
- [ ] **T-RISK-01**: Changelog reviewed; no breaking changes found
- [ ] **T-RISK-03**: Security review confirms strict type comparison in AC library
- [ ] **T013–T015**: Staging deployment successful; plugin loads; AC enforcement works

### Conditional Validation

- [ ] **T-RISK-02**: Multisite validation passed (if applicable; or documented as "not tested")
- [ ] **T009–T012**: Admin notice displays when library absent (DEC-FAIL-OPEN-NOTICE validated)

### Release Readiness

- [ ] **T017**: Changelog updated
- [ ] **T018**: Release approved
- [ ] **T019**: Production deployment successful

---

## Risk Mitigation Summary

| Risk | Probability | Impact | Mitigation | Owner |
|------|-------------|--------|-----------|-------|
| API breaking change between commit & 1.x release | Medium | Critical | T-AUDIT-01, T-AUDIT-02 (changelog review), T006–T008 (test suite) | Reviewer + QA |
| Permission enforcement silently stops working | Low | Critical | T006–T008 (permission callback + access check tests), T015 (manual enforcement test) | QA |
| Loose comparison vulnerability in AC library | Low | Critical | T-RISK-03 (security review) | Security |
| Multisite rule scoping regression | Low | High | T-RISK-02 (multisite staging test) | QA |
| Admin not notified when AC library unavailable | Low | Medium | T009–T012 (fail-open notice test) | QA |

---

## Definition of Done

Feature 007 is complete when:

1. ✅ Composer constraint updated to ^1.0; composer.lock locked to 1.x.x tag
2. ✅ Pre-update audit completed; no blocking breaking changes identified
3. ✅ All P1 tests pass: dependency resolution (T004–T005), permission callbacks (T006), user access checks (T007), integration tests (T008)
4. ✅ Fail-open admin notice verified working (T009–T012)
5. ✅ Staging deployment successful; AC enforcement validated (T013–T015)
6. ✅ Changelog updated with upgrade summary (T017)
7. ✅ Release approved and deployed to production (T018–T019)
8. ✅ No permission enforcement regressions detected post-deployment
9. ✅ Memory review completed (if required by config.require_memory_review_before_verify)

---

## Success Criteria Verification Matrix

| Criteria | Test | Expected Result | Status |
|----------|------|-----------------|--------|
| Composer constraint is ^1.0 | T001 + check composer.json | String contains `"^1.0"` | Pending |
| Composer.lock locked to 1.x.x | T002 + check lock file | Lock entry shows `"version": "1.x.x"` | Pending |
| Clean install succeeds | T005 | `composer install` completes, no errors | Pending |
| Permission callbacks inject | T006 | `$args['permission_callback']` is callable | Pending |
| User access checks return boolean | T007 | `user_has_access()` always returns bool | Pending |
| Integration tests pass | T008 | PHPUnit/npm test exit code 0 | Pending |
| No breaking API changes | T-AUDIT-01 + T-AUDIT-02 | Changelog review shows "no breaking changes" | Pending |
| Admin notice displays (fail-open) | T009–T012 | Notice appears when library absent | Pending |
| AC enforcement works on staging | T015 | Permission deny/allow works correctly | Pending |
| Changelog updated | T017 | CHANGELOG.md contains Feature 007 entry | Pending |
| Production deployment successful | T019 | Plugin loads, no 500 errors in staging/prod | Pending |

---

## Timeline & Effort Estimate

| Phase | Duration | Effort | Owner |
|-------|----------|--------|-------|
| Pre-Update Audit (T-AUDIT-*) | ~1 hour | 30 min | Reviewer |
| Dependency Update (T001–T008) | ~2–3 hours | 2 hours | Dev + QA |
| Fail-Open Verification (T009–T012) | ~30 min | 20 min | QA |
| Staging Deployment (T013–T018) | ~1 hour | 45 min | DevOps + QA |
| **Total** | **~4–5 hours** | **~3.5 hours** | Multi-team |

**Critical Path**: T-AUDIT → T001–T008 (P1 tests) → T013–T018 (staging + approval) → T019 (prod deploy)

---

## Notes

- Feature 007 has no new PHP classes, no REST endpoints, no admin UI changes. It is a pure dependency chore.
- The upgrade is low-risk IF API compatibility is confirmed (T-AUDIT-01 through T-AUDIT-03). Run this audit early.
- All 5 test scenarios are independent and can run in parallel after dependency resolution (T002) completes.
- P1 tests (1, 2, 3, 5) must all pass before proceeding to P2 or staging deployment. If any P1 test fails, the feature is blocked until root cause is identified and fixed.
- Fail-open admin notice (DEC-FAIL-OPEN-NOTICE) is a hard requirement per memory synthesis. It MUST be verified working before release.
- Staging deployment should include multisite environment if available (T-RISK-02 is recommended, not optional).

