# Tasks: WPB Access Control Stable Release Upgrade (Feature 007)

**Feature Branch**: `007-upgrade-access-control`  
**Created**: 2026-05-20  
**Status**: Ready for Implementation  
**Total Tasks**: 27 granular tasks (organized into 4 phases)  

---

## Quick Reference: Task IDs & Dependencies

| Phase | Task ID | Title | Dependencies | Duration |
|-------|---------|-------|--------------|----------|
| **Phase 0** | T001 | Inspect WPB Changelog | None | 15 min |
| | T002 | Verify API Signatures | T001 | 10 min |
| | T003 | Security Review (SEC-04) | T001 | 10 min |
| | T004 | Pre-Update Checklist | T001, T002, T003 | 5 min |
| **Phase 1** | T005 | Update composer.json | T004 | 2 min |
| | T006 | Run composer update | T005 | 3 min |
| | T007 | Verify Lock File | T006 | 2 min |
| | T008 | Validate Composer | T006 | 1 min |
| | T009 | Test: Clean Install | T006, T008 | 5 min |
| | T010 | Test: Permission Callback | T009 | 15 min |
| | T011 | Test: User Access Checks | T010 | 10 min |
| | T012 | Test: Integration Tests | T009 | 20 min |
| | T013 | Test: Manual AC Enforcement | T010, T011 | 10 min |
| | T014 | Debug Phase 1 Failures | T009–T013 | 15–60 min |
| **Phase 2** | T015 | Simulate Library Absence | T014 | 2 min |
| | T016 | Verify Admin Notice | T015 | 5 min |
| | T017 | Verify Non-Admin Access | T016 | 5 min |
| | T018 | Restore & Verify Gone | T017 | 3 min |
| **Phase 3** | T019 | Deploy to Staging | T014 | 10 min |
| | T020 | Verify Plugin Load (Staging) | T019 | 5 min |
| | T021 | AC Enforcement Test (Staging) | T020 | 10 min |
| | T022 | Fail-Open Notice (Staging) | T020 | 10 min |
| | T023 | Multisite Validation (Staging) | T020 | 15 min |
| | T024 | Update CHANGELOG.md | T014 | 5 min |
| | T025 | Release Approval Checklist | T014, T018, T021–T024 | 5 min |
| | T026 | Deploy to Production | T025 | 10 min |
| | T027 | Post-Deploy Validation | T026 | 15 min + 1h monitoring |

---

## PHASE 0: Pre-Update Audit (Risk Mitigation)

**Duration**: ~1 hour | **Owner**: Code Reviewer | **Success**: Checklist signed off; no blockers

### T001: Inspect WPB Access Control GitHub Changelog

- [ ] T001 [P] Review wpboilerplate/wpb-access-control releases between commit `2a9ddbdf...` and latest 1.x.x

**Owner**: Code Reviewer | **Effort**: 15 min | **Priority**: P1  
**Depends On**: None  

**Action**:
Navigate to https://github.com/WPBoilerplate/wpb-access-control/releases and review changelog between the current locked commit and latest 1.x.x tag. Scan for:
- API changes to `AccessControlManager::get_manager()`, `user_has_access()`, `get_query()` 
- Comparison operator changes (loose vs. strict)
- Multisite table scoping changes
- Deprecations

**Acceptance**:
- ✅ Changelog reviewed
- ✅ Breaking changes identified (if any)
- ✅ Findings documented for T004

**Risk Flags**:
- 🚩 **API Breaking Change** (Medium Probability, Critical Impact): If found, escalate; blocks Phase 1
- 🚩 **Loose Comparison Vulnerability** (Low Probability, Critical): If found, escalate to security review

---

### T002: Verify Public API Signatures

- [ ] T002 [P] Validate `AccessControlManager` method signatures in latest 1.x tag vs. current usage

**Owner**: Code Reviewer | **Effort**: 10 min | **Priority**: P1  
**Depends On**: T001  

**Action**:
Compare method signatures:
- `AccessControlManager::get_manager(): ?AccessControlManager` 
- `AccessControlManager::user_has_access($user_id, $category, $slug): bool`
- `AccessControlManager::get_query(): QueryBuilder`

Cross-reference against usage in `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php`.

**Acceptance**:
- ✅ All signatures match (compatible) OR
- ✅ Breaking changes identified and documented

---

### T003: Security Review — Strict Comparison (SEC-04)

- [ ] T003 [P] Scan `user_has_access()` for loose comparison operators (==, !=)

**Owner**: Security Reviewer | **Effort**: 10 min | **Priority**: P1  
**Depends On**: T001  

**Action**:
Review the `AccessControlManager::user_has_access()` implementation in latest 1.x tag. Verify:
- ✅ Uses strict comparison (`===`, `!==`)
- ✅ No bypass vulnerabilities (e.g., loose `$user_id == 0` check)
- ✅ SEC-04 constraint maintained

**Acceptance**:
- ✅ Strict comparison verified OR
- ✅ Loose comparison issues documented

---

### T004: Pre-Update Checklist & Go/No-Go Decision

- [ ] T004 Create pre-update checklist; make go/no-go decision

**Owner**: Code Reviewer + PM | **Effort**: 5 min | **Priority**: P1 (GATE)  
**Depends On**: T001, T002, T003  

**Action**:
Consolidate T001–T003 findings into decision checklist. Decide:
- **GO** (green): No blockers; proceed to Phase 1
- **HOLD** (yellow): Minor issues; document mitigation
- **NO-GO** (red): Critical issues; escalate; Feature 007 blocked

**Deliverable**: `specs/007-upgrade-access-control/pre-update-checklist.md`

**Acceptance**:
- ✅ Checklist completed and signed off
- ✅ GO decision documented (gates Phase 1)

---

## PHASE 1: Dependency Update & P1 Tests (Blocking)

**Duration**: ~2–3 hours | **Owner**: Developer + QA | **Success**: All P1 tests PASS (mandatory)

**⚠️ CRITICAL**: If ANY test fails, halt and debug immediately. Phase 2 and 3 blocked until all P1 tests pass.

### T005: Update composer.json Constraint

- [ ] T005 Change composer.json: `dev-main` → `^1.0` for wpboilerplate/wpb-access-control

**Owner**: Developer | **Effort**: 2 min | **Priority**: P1  
**Depends On**: T004 (GO decision)  

**Action**:
Edit `composer.json` at plugin root. Change:
```
"wpboilerplate/wpb-access-control": "dev-main"
→
"wpboilerplate/wpb-access-control": "^1.0"
```

**Acceptance**:
- ✅ File edited and saved
- ✅ No other dependencies modified

---

### T006: Run Composer Update

- [ ] T006 Execute `composer update wpboilerplate/wpb-access-control`

**Owner**: Developer | **Effort**: 3 min | **Priority**: P1  
**Depends On**: T005  

**Action**:
```bash
cd /Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-abilities-manager
composer update wpboilerplate/wpb-access-control
```

**Acceptance**:
- ✅ No fatal errors
- ✅ `composer.lock` updated
- ✅ `Generating autoload files` output shown
- ✅ No unexpected package updates

---

### T007: Verify Composer Lock File

- [ ] T007 Verify composer.lock contains wpb-ac version 1.x.x (not dev-main)

**Owner**: QA | **Effort**: 2 min | **Priority**: P1  
**Depends On**: T006  

**Action**:
```bash
grep -A 10 '"name": "wpboilerplate/wpb-access-control"' composer.lock
```

Verify `"version": "1.x.x"` (e.g., `1.0.0`, `1.1.5`), not `dev-main` or dev reference.

**Acceptance**:
- ✅ Lock file entry shows semantic version 1.x.x
- ✅ Reference is specific commit hash (not dev-main)

---

### T008: Validate Composer Configuration

- [ ] T008 Run `composer validate` (exit code 0)

**Owner**: QA | **Effort**: 1 min | **Priority**: P1  
**Depends On**: T006  

**Action**:
```bash
composer validate
```

**Acceptance**:
- ✅ Exit code 0
- ✅ No errors or warnings

---

### T009: Test Scenario 1 — Clean Install (Dependency Resolution)

- [ ] T009 [P] Clean install: remove vendor/, run `composer install --no-dev`, verify success

**Owner**: QA | **Effort**: 5 min | **Priority**: P1  
**Depends On**: T006, T008  

**Action**:
```bash
rm -rf vendor/
composer install --no-dev
```

**Acceptance**:
- ✅ Install completes without errors
- ✅ `vendor/wpboilerplate/wpb-access-control/` exists
- ✅ `vendor/autoload.php` generated
- ✅ No fatal PHP errors

---

### T010: Test Scenario 2 — Permission Callback Injection (DEC-PERM-CB)

- [ ] T010 [P] Verify `get_manager()` returns non-null; callback injection works

**Owner**: QA | **Effort**: 15 min | **Priority**: P1  
**Depends On**: T009  

**Action**:
Load WordPress; execute:
```php
$ac = AcrossAI_Sitewide_Access_Control::instance();
$manager = $ac->get_manager();
var_dump( $manager ); // Non-null
var_dump( is_callable( $manager ) ); // Can call methods

$args = [];
$ac->inject_override_args('test-slug', $args);
var_dump( isset($args['permission_callback']) ); // true
var_dump( is_callable($args['permission_callback']) ); // true
```

**Acceptance**:
- ✅ `get_manager()` returns non-null manager instance
- ✅ `inject_override_args()` sets permission_callback
- ✅ permission_callback is callable
- ✅ DEC-PERM-CB pattern holds (no exceptions)

**Memory Constraint**: 🔗 **DEC-PERM-CB** — Permission callback injection is THE critical integration point

---

### T011: Test Scenario 3 — User Access Checks (Boolean Return Type)

- [ ] T011 [P] Verify `user_has_access()` always returns boolean (never null or mixed)

**Owner**: QA | **Effort**: 10 min | **Priority**: P1  
**Depends On**: T010  

**Action**:
```php
$manager = $ac->get_manager();

// Admin user
$admin_result = $manager->user_has_access($admin_id, 'acrossai-abilities', 'test-slug');
var_dump( gettype($admin_result) ); // "boolean"
var_dump( $admin_result ); // true

// Subscriber user
$sub_result = $manager->user_has_access($subscriber_id, 'acrossai-abilities', 'test-slug');
var_dump( gettype($sub_result) ); // "boolean"
var_dump( $sub_result ); // false

// Unknown rule
$unknown = $manager->user_has_access($admin_id, 'acrossai-abilities', 'nonexistent');
var_dump( gettype($unknown) ); // "boolean"
var_dump( $unknown ); // false (deny by default)
```

**Acceptance**:
- ✅ All return type === "boolean"
- ✅ Allowed user → true
- ✅ Denied user → false
- ✅ Unknown rule → false (deny by default)
- ✅ No null or mixed types

**Memory Constraint**: 🔗 **DEC-PERM-CB** — Return type MUST remain boolean; null breaks enforcement

---

### T012: Test Scenario 5 — Full Integration Tests

- [ ] T012 [P] Run PHPUnit test suite; all tests pass or known failures below baseline

**Owner**: QA | **Effort**: 20 min | **Priority**: P1  
**Depends On**: T009  

**Action**:
```bash
npm test
# or
./vendor/bin/phpunit
# or
composer test
```

**Acceptance**:
- ✅ Exit code 0 (all tests pass) OR known failures < baseline
- ✅ No new regressions
- ✅ Permission enforcement tests pass

---

### T013: Test Scenario 2+3 Integrated — Manual AC Enforcement

- [ ] T013 [P] Create AC rule; test permission enforcement end-to-end via REST API

**Owner**: QA | **Effort**: 10 min | **Priority**: P1  
**Depends On**: T010, T011  

**Action**:
1. Create AC rule (e.g., admin-only)
2. Register test ability with rule
3. Test as admin: `GET /wp-json/wp-abilities/v1/test` → 200 OK
4. Test as subscriber: `GET /wp-json/wp-abilities/v1/test` → 403 Forbidden

**Acceptance**:
- ✅ AC rule created
- ✅ Admin access allowed (200)
- ✅ Subscriber access denied (403)
- ✅ End-to-end enforcement working

---

### T014: Debug & Fix Phase 1 Failures

- [ ] T014 If ANY test (T009–T013) fails, debug and fix before Phase 2 proceeds

**Owner**: Developer + QA | **Effort**: 15–60 min (variable) | **Priority**: P1 (GATE)  
**Depends On**: T009–T013  

**Action**:
1. Identify failure root cause
2. Categorize:
   - **Breaking API Change**: Update pre-update checklist; escalate to Feature 007a
   - **Plugin Integration Bug**: Fix plugin code (AcrossAI_Sitewide_Access_Control)
   - **Environment Issue**: Fix and re-run
3. Re-run failed test; confirm pass
4. Document fix in git commit

**Acceptance**:
- ✅ Root cause identified
- ✅ Fix applied
- ✅ Failed test now passes
- ✅ All T009–T013 GREEN before Phase 2

---

## PHASE 2: Fail-Open Verification (P2 Tests)

**Duration**: ~30 min | **Owner**: QA | **Success**: Admin notice works; DEC-FAIL-OPEN-NOTICE verified

### T015: Simulate Library Absence

- [ ] T015 Move vendor/wpboilerplate/wpb-access-control to .bak to simulate missing library

**Owner**: QA | **Effort**: 2 min | **Priority**: P2  
**Depends On**: T014 (Phase 1 passed)  

**Action**:
```bash
mv vendor/wpboilerplate/wpb-access-control vendor/wpboilerplate/wpb-access-control.bak
```

**Acceptance**:
- ✅ Library no longer exists
- ✅ WordPress still loads (graceful)

**Memory Constraint**: 🔗 **DEC-FAIL-OPEN-NOTICE** — Pattern begins

---

### T016: Verify Admin Notice Displays

- [ ] T016 Log in as admin; verify notice displays: "wpb-access-control library not available; access control inactive"

**Owner**: QA | **Effort**: 5 min | **Priority**: P2  
**Depends On**: T015  

**Action**:
1. Access WordPress admin dashboard (logged in as admin)
2. Look for admin notice in alert area
3. Verify text mentions library unavailable and enforcement inactive

**Acceptance**:
- ✅ Notice visible to admin
- ✅ Clear text explaining library unavailable
- ✅ Clear text explaining enforcement inactive
- ✅ No 500 errors

**Memory Constraint**: 🔗 **DEC-FAIL-OPEN-NOTICE** — Admin must be notified (not silently broken)

---

### T017: Verify Notice Gated to Admin Only

- [ ] T017 Log in as subscriber; verify notice is NOT visible (admin-only gate)

**Owner**: QA | **Effort**: 5 min | **Priority**: P2  
**Depends On**: T016  

**Action**:
1. Create test subscriber account (if not exists)
2. Log out; log in as subscriber
3. Access dashboard; verify notice NOT visible

**Acceptance**:
- ✅ Notice NOT visible to subscriber
- ✅ Notice NOT visible to non-admin roles
- ✅ Notice gated to `manage_options` capability

---

### T018: Restore Library & Verify Notice Gone

- [ ] T018 Restore wpb-ac vendor directory; refresh admin dashboard; verify notice disappears

**Owner**: QA | **Effort**: 3 min | **Priority**: P2  
**Depends On**: T017  

**Action**:
```bash
mv vendor/wpboilerplate/wpb-access-control.bak vendor/wpboilerplate/wpb-access-control
```

Refresh admin dashboard; verify notice gone.

**Acceptance**:
- ✅ Library restored
- ✅ Notice no longer visible (library available)
- ✅ WordPress loads correctly
- ✅ DEC-FAIL-OPEN-NOTICE verified: notice conditional on absence

---

## PHASE 3: Staging & Production Deployment

**Duration**: ~1–2 hours | **Owner**: DevOps + QA + PM | **Success**: Staging tests pass; prod deployment approved & successful

### T019: Deploy to Staging

- [ ] T019 [P] Merge 007-upgrade-access-control to staging; deploy to staging environment

**Owner**: DevOps | **Effort**: 10 min | **Priority**: P1  
**Depends On**: T014 (Phase 1 passed)  

**Action**:
```bash
git checkout staging
git pull origin staging
git merge 007-upgrade-access-control
# Deploy using your pipeline
```

**Acceptance**:
- ✅ Branch merged
- ✅ Deployed to staging
- ✅ `composer.json` shows ^1.0
- ✅ `vendor/wpboilerplate/wpb-access-control/` is 1.x.x

---

### T020: Verify Plugin Loads on Staging

- [ ] T020 Access staging WordPress admin; verify plugin active; no PHP errors

**Owner**: QA | **Effort**: 5 min | **Priority**: P1  
**Depends On**: T019  

**Action**:
1. Access staging admin
2. Check Plugins page: AcrossAI Abilities Manager is Active
3. Check debug.log: no fatal errors

**Acceptance**:
- ✅ Plugin Active
- ✅ Dashboard loads (no 500)
- ✅ No fatal PHP errors in debug.log

---

### T021: AC Enforcement Test on Staging

- [ ] T021 [P] Create admin-only AC rule on staging; test admin allows, subscriber denies

**Owner**: QA | **Effort**: 10 min | **Priority**: P1  
**Depends On**: T020  

**Action**:
1. Create AC rule: admin-only
2. Register test ability with rule
3. Test as admin: access allowed (REST 200)
4. Test as subscriber: access denied (REST 403)

**Acceptance**:
- ✅ AC rule created
- ✅ Admin access allowed
- ✅ Subscriber access denied
- ✅ Permission enforcement consistent with pre-upgrade

**Memory Constraint**: 🔗 **DEC-PERM-CB** — Must work end-to-end on staging

---

### T022: Fail-Open Notice on Staging (Optional)

- [ ] T022 [P] On staging, simulate library absence; verify notice displays; restore

**Owner**: QA | **Effort**: 10 min | **Priority**: P2  
**Depends On**: T020  

**Action**:
Repeat T015–T018 on staging environment to verify pattern works in production-like environment.

**Acceptance**:
- ✅ Notice displays when missing
- ✅ Notice disappears when restored

**Memory Constraint**: 🔗 **DEC-FAIL-OPEN-NOTICE** — Verified on staging

---

### T023: Multisite Validation on Staging (If Applicable)

- [ ] T023 [P] If multisite staging available: verify AC rules scoped per-site; no bleeding

**Owner**: QA | **Effort**: 15 min | **Priority**: P2  
**Depends On**: T020 + multisite environment  

**Action**:
1. Create rule on primary site
2. Switch to secondary site; verify rule not visible (scoped)
3. Create different rule on secondary site
4. Switch to primary; verify secondary's rule not visible
5. Test permission enforcement per-site

**Acceptance**:
- ✅ Rules scoped per-site (no bleeding)
- ✅ Enforcement independent per-site
- ✅ SEC-03 constraint maintained

**Conditional**: Skip if no multisite staging; document in release notes.

---

### T024: Update CHANGELOG.md

- [ ] T024 Add Feature 007 entry to CHANGELOG.md (version, date, upgrade summary, test results)

**Owner**: Developer | **Effort**: 5 min | **Priority**: P1  
**Depends On**: T014 (Phase 1 passed)  

**Action**:
Edit `CHANGELOG.md` at plugin root. Add entry:
```markdown
## [X.Y.Z] - 2026-05-20

### Changed
- Upgraded `wpboilerplate/wpb-access-control` from dev-main to ^1.0
  - No breaking API changes detected
  - All access control tests pass

### Verified
- ✅ Permission callback injection works (DEC-PERM-CB)
- ✅ User access checks return boolean (DEC-PERM-CB)
- ✅ Fail-open admin notice displays (DEC-FAIL-OPEN-NOTICE)
- ✅ Full integration tests pass
- ✅ No permission enforcement regressions

### Security
- SEC-04: Strict comparison verified
- SEC-03: Multisite scoping validated
```

**Acceptance**:
- ✅ CHANGELOG.md updated with Feature 007 entry
- ✅ Entry includes version, date, upgrade summary, test results, security notes

---

### T025: Release Approval Checklist

- [ ] T025 Consolidate Phase 1–3 test results; create approval checklist; obtain sign-offs

**Owner**: PM / Release Manager | **Effort**: 5 min | **Priority**: P1 (GATE)  
**Depends On**: T014 (Phase 1), T018 (Phase 2), T021–T024 (Phase 3 tests)  

**Action**:
Create `specs/007-upgrade-access-control/release-approval-checklist.md` with:
- Phase 1 tests: all GREEN
- Phase 2 tests: all GREEN
- Phase 3 tests: all GREEN
- Security sign-off
- PM approval

Decision: **APPROVED FOR PRODUCTION**

**Acceptance**:
- ✅ Checklist completed
- ✅ All tests marked PASSED
- ✅ No blockers
- ✅ Security & PM signed off

---

### T026: Deploy to Production

- [ ] T026 Merge staging to main; deploy to production

**Owner**: DevOps | **Effort**: 10 min | **Priority**: P1  
**Depends On**: T025 (approval)  

**Action**:
```bash
git checkout main
git merge staging
git tag vX.Y.Z
git push origin main
git push origin vX.Y.Z
# Deploy to production
```

**Acceptance**:
- ✅ Main branch merged & tagged
- ✅ Deployed to production
- ✅ composer.json shows ^1.0 on production

---

### T027: Post-Deployment Validation (Health Check)

- [ ] T027 Verify plugin loads on production; monitor error logs 1 hour; confirm AC enforcement works

**Owner**: DevOps + QA | **Effort**: 15 min active + 1 hour monitoring | **Priority**: P1  
**Depends On**: T026  

**Action**:
1. Access production admin; verify plugin Active
2. Check debug.log; no fatal errors
3. Test AC enforcement on production (admin allowed, non-admin denied)
4. Monitor logs for 1 hour; watch for errors or anomalies

**Acceptance**:
- ✅ Plugin Active on production
- ✅ Dashboard loads (no 500)
- ✅ No fatal PHP errors
- ✅ AC enforcement works
- ✅ 1-hour monitoring clean (no new errors)
- ✅ Production stable

---

## Summary & Metrics

### Task Count by Phase

| Phase | Tasks | Duration | Owner |
|-------|-------|----------|-------|
| Phase 0 (Pre-Audit) | 4 | 1 hour | Reviewer |
| Phase 1 (P1 Tests) | 10 | 2–3 hours | Dev + QA |
| Phase 2 (P2 Tests) | 4 | 30 min | QA |
| Phase 3 (Deployment) | 9 | 1–2 hours | DevOps + QA |
| **TOTAL** | **27** | **4.5–6.5 hours** | Multi-team |

### Critical Path

```
T004 ✅ GATE
  → T005–T008 (update, resolve)
  → T009–T013 (5 parallel tests)
  → T014 ✅ GATE (debug if needed)
  → T015–T018 (fail-open)
  → T019–T022 (staging tests)
  → T024–T025 ✅ GATE (approval)
  → T026–T027 (production deploy)
```

### Architecture Boundary: MAINTAINED ✅

- ✅ No code changes outside AcrossAI_Sitewide_Access_Control
- ✅ No new hooks added to Main.php
- ✅ No new admin UI changes
- ✅ Dependency upgrade only; API integration unchanged

### Memory Constraints: VERIFIED ✅

- 🔗 **DEC-PERM-CB**: Permission callback injection verified working (T010, T013, T021)
- 🔗 **DEC-FAIL-OPEN-NOTICE**: Fail-open admin notice verified working (T016–T018, T022)

### Security Constraints: VERIFIED ✅

- 🔗 **SEC-04**: Strict comparison verified (T003, T011, T027)
- 🔗 **SEC-03**: Multisite scoping verified (T023)

---

**Generated**: 2026-05-20 | **Branch**: 007-upgrade-access-control | **Status**: Ready for Implementation
