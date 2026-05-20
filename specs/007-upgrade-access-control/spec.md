# Feature Specification: WPB Access Control Stable Release Upgrade

**Feature Branch**: `007-upgrade-access-control`  
**Created**: 2026-05-20  
**Status**: Draft  
**Feature Type**: Dependency Upgrade (Chore)  
**Input**: Update composer dependency wpboilerplate/wpb-access-control from dev-main to ^1.0

---

## Executive Summary

Move the `wpboilerplate/wpb-access-control` package from the unstable `dev-main` branch to a stable tagged release `^1.0`. This eliminates the risk of silent regressions from future dev-main drift and establishes a repeatable, auditable dependency baseline for access control enforcement.

**Current State**:
- Constraint: `"dev-main"` (unstable branch tracking)
- Locked commit: `2a9ddbdf29fff064078f04fbd121ec9ff7c69500`
- Repository: https://github.com/WPBoilerplate/wpb-access-control

**Target State**:
- Constraint: `"^1.0"` (stable semantic version)
- Pinned to latest compatible 1.x release
- Same security/permission enforcement behavior as current locked commit

---

## User Scenarios & Testing *(mandatory)*

### Test Scenario 1 — Dependency Resolution and Lock File Update (Priority: P1)

**Why this priority**: Must complete first; blocks all downstream testing. No ability enforcement works without the dependency update.

**Independent Test**: Update composer.lock, verify lock file contains wpboilerplate/wpb-access-control ^1.0 with a 1.x.x tag version (not dev-main).

**Acceptance Scenarios**:

1. **Given** composer.json has `"wpboilerplate/wpb-access-control": "dev-main"`, **When** `composer update wpboilerplate/wpb-access-control`, **Then** composer.json is updated to `"^1.0"` and composer.lock contains a 1.x.x version tag
2. **Given** the updated composer.lock, **When** `composer install --no-dev` on a clean vendor directory, **Then** wpb-access-control installs successfully with no errors
3. **Given** updated packages, **When** running `composer validate`, **Then** no validation errors reported

---

### Test Scenario 2 — Permission Callback Injection Still Works (Priority: P1)

**Why this priority**: Core feature of the access control integration. If permission callbacks don't inject correctly, ability access control fails silently.

**Independent Test**: Verify that AcrossAI_Sitewide_Access_Control::inject_override_args() can retrieve rules and inject permission_callback without errors.

**Acceptance Scenarios**:

1. **Given** access control is enabled and wpb-access-control ^1.0 is loaded, **When** an ability is registered with an AC rule, **Then** AcrossAI_Sitewide_Access_Control::get_manager() returns non-null manager instance
2. **Given** a valid manager instance, **When** AcrossAI_Sitewide_Access_Control::inject_override_args() is called, **Then** $args['permission_callback'] is set and is callable without errors
3. **Given** permission_callback is invoked, **When** user does not have the required rule, **Then** callback returns false and ability check fails

---

### Test Scenario 3 — User Has Access Check Still Works (Priority: P1)

**Why this priority**: Critical enforcement function. Broken user access checks defeat access control.

**Independent Test**: Verify that `$manager->user_has_access( $user_id, $category, $slug )` method exists and works as expected.

**Acceptance Scenarios**:

1. **Given** access control is configured with a test rule, **When** calling $manager->user_has_access( admin_id, 'acrossai-abilities', 'test-ability' ), **Then** return value is boolean and is logically consistent (allowed user returns true, denied user returns false)
2. **Given** different user roles (admin, editor, subscriber), **When** calling user_has_access() for each, **Then** results reflect role permissions per rule configuration
3. **Given** a user without any rules assigned, **When** calling user_has_access(), **Then** returns false (deny by default)

---

### Test Scenario 4 — Admin Notice Displays When Library Absent (Priority: P2)

**Why this priority**: User-facing clarity. If library goes missing, admin should be notified. Verifies fail-open behavior is working.

**Independent Test**: Verify admin notices display correctly when access control library becomes unavailable.

**Acceptance Scenarios**:

1. **Given** access control library is unavailable (simulated), **When** admin views the plugin admin page, **Then** notice displays: "wpb-access-control library is not available; ability access control is inactive"
2. **Given** notice is displayed, **When** admin is not logged in or not an admin, **Then** notice is not visible (non-admins don't see it)

---

### Test Scenario 5 — No Regressions in Integration Tests (Priority: P1)

**Why this priority**: Full test suite must pass. Any regressions block deployment.

**Independent Test**: Run full plugin test suite with upgraded dependency.

**Acceptance Scenarios**:

1. **Given** wpb-access-control ^1.0 is installed, **When** running `npm test` (if applicable) or PHPUnit test suite, **Then** all tests pass or regressions are identified
2. **Given** API changes in the upgraded library, **When** examining breaking changes between current lock and ^1.0 latest, **Then** no breaking changes detected or migration path documented

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Composer dependency constraint MUST change from `"dev-main"` to `"^1.0"`
- **FR-002**: Updated composer.json and composer.lock MUST be committed to Git
- **FR-003**: The AcrossAI_Sitewide_Access_Control::get_manager() method MUST continue to work identically with the new library version
- **FR-004**: The permission_callback injection pattern (ARCH-ADV-001, DEC-PERM-CB) MUST continue to function
- **FR-005**: User access checks via $manager->user_has_access() MUST work without modification
- **FR-006**: Admin notice for missing library MUST display correctly (fail-open behavior pattern)
- **FR-007**: No new security vulnerabilities MUST be introduced by the upgrade

### Non-Functional Requirements

- **NFR-001**: Upgrade MUST complete without requiring any changes to AcrossAI_Ability_Override_Processor or AcrossAI_Sitewide_Access_Control classes (API compatibility)
- **NFR-002**: Deployment to staging and production MUST verify no permission enforcement regressions
- **NFR-003**: All existing access control rules and overrides MUST continue to function post-upgrade

---

## Key Entities

**Composer Dependency**:
- Package: `wpboilerplate/wpb-access-control`
- Current constraint: `dev-main`
- Current lock: commit `2a9ddbdf29fff064078f04fbd121ec9ff7c69500`
- Target constraint: `^1.0`
- Repository: https://github.com/WPBoilerplate/wpb-access-control

**Affected Classes**:
- `AcrossAI_Sitewide_Access_Control::get_manager()` — library manager retrieval
- `AcrossAI_Sitewide_Access_Control::inject_override_args()` — permission callback injection
- `AcrossAI_Ability_Override_Processor::inject_override_args()` — calls the above

---

## Success Criteria *(mandatory)*

1. **Composer constraint updated**: composer.json contains `"^1.0"` (not `"dev-main"`), and composer.lock is locked to a 1.x.x tagged version
2. **No breaking API changes detected**: Upgrade to latest ^1.0 version without code changes to plugin
3. **Integration tests pass**: Full plugin test suite passes with no regressions
4. **Permission enforcement works**: Manual verification that ability permission checks still enforce correctly
5. **Deployment successful**: Package installs cleanly in both staging and production environments
6. **Risk mitigated**: No silent regressions from future dev-main drift; baseline is now explicit and version-controlled

---

## Assumptions

- The latest 1.x.x tag of wpb-access-control is API-compatible with the pinned dev-main commit
- No breaking changes have been introduced between the current lock and ^1.0 release
- The `^1.0` constraint will not automatically pull a 2.0.0 release (semver stability is maintained)
- The package maintainers will publish 1.x.z patch versions for any security fixes needed

---

## Constraints & Dependencies

**Architecture Constraints**:
- ARCH-ADV-001: Boot Flow Rule allows direct add_filter in boot() for library initialization. AC library manager retrieval MUST not require Loader modifications.
- AC-HOOKS-MAIN: Only Main.php wires hooks. AC library integration stays within AcrossAI_Sitewide_Access_Control.

**Security Constraints**:
- SEC-03: Per-site table isolation. AC library must maintain per-site scoping for multisite installations (verify no multisite regressions).
- SEC-04: Strict type comparison. AC library's access control checks MUST use strict comparison for user capabilities.

**Known Risks**:
- API change in wpb-access-control between current lock and latest ^1.0: Mitigated by running full test suite and manual verification
- Multisite regression: AC library might not handle per-site scoping correctly. Mitigated by testing on multisite sandbox.
- Silent permission regression: Admins might not notice if access control stops working. Mitigated by admin notice (DEC-FAIL-OPEN-NOTICE) and test alerts.

---

## Scope & Boundaries

**In Scope**:
- Updating composer.json and composer.lock
- Verifying no code changes needed in plugin
- Running integration tests
- Deploying to staging/production
- Documenting any necessary migration steps

**Out of Scope**:
- Adding new access control features
- Modifying the AC library source code
- Changing the permission callback injection pattern (DEC-PERM-CB stays unchanged)
- Upgrading to AC 2.0.0 (only ^1.0 within the 1.x series)

---

## Acceptance Criteria

- [ ] Composer constraint is ^1.0 (verified in composer.json)
- [ ] Composer.lock locked to a 1.x.x tag (not dev-main)
- [ ] `composer install` succeeds with no errors or warnings
- [ ] Full plugin test suite passes with upgraded dependency
- [ ] Permission enforcement verified manually on staging
- [ ] Admin notice displays correctly when library is missing (fail-open test)
- [ ] No regressions in multisite installations (if applicable)
- [ ] All ability access control rules continue to enforce after upgrade

