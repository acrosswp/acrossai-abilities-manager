# Memory Synthesis: WPB Access Control Stable Release Upgrade (Feature 007)

**Generated**: 2026-05-20  
**Purpose**: Targeted knowledge for planning phase (Phase: Plan)

## Current Scope

Feature 007 upgrades the `wpboilerplate/wpb-access-control` dependency from unstable `dev-main` to stable `^1.0`. This is a dependency management chore with no new PHP classes or features. The scope is:
- Validate API compatibility between current lock (commit `2a9ddbdf...`) and latest ^1.0 tag
- Update composer.json and composer.lock
- Verify integration tests pass (permission enforcement, admin notices, multisite behavior)
- No code changes to plugin classes (API should be compatible)

**Affected modules**: AcrossAI_Sitewide_Access_Control (consumer of wpb-ac library), integration tests

## Relevant Decisions

### DEC-PERM-CB (2026-05-16)
AC rule-gated permission_callback injection pattern. The AC library manager is retrieved at ability registration time via `AcrossAI_Sitewide_Access_Control::get_manager()`, which calls into `wpb-access-control`.

**Application to Feature 007**: MUST verify that the upgraded library's `get_manager()` public API has NOT changed. If the method signature or return type changes, the injection pattern breaks. Test scenario 2 (permission callback injection) validates this.

---

### DEC-FAIL-OPEN-NOTICE (2026-05-17)
When an optional library is absent, a notice must display in wp-admin to inform admins that enforcement is inactive.

**Application to Feature 007**: MUST verify that `AcrossAI_Sitewide_Access_Control::maybe_show_library_notice()` still works with upgraded library. Test scenario 4 (admin notice) validates this. The notice should trigger when library is unavailable, just as it does now.

---

## Active Architecture Constraints

### AC-HOOKS-MAIN (Constitution §I)
Only Main.php calls loader->add_action/add_filter via Loader. AcrossAI_Sitewide_Access_Control class does not wire hooks.

**Application to Feature 007**: No changes needed. The library is instantiated and used in AcrossAI_Sitewide_Access_Control, not wired as a hook directly. The integration point (`get_manager()`) remains internal to the class.

---

## Relevant Security Constraints

### SEC-04 (Feature 005)
Strict type comparison for access control checks: `in_array($slug, $protected_slugs, true)` with strict=true.

**Application to Feature 007**: MUST verify that the upgraded library uses strict comparison in its internal access checks. If the library starts using loose comparison for permission checks, it introduces a bypass vulnerability. Security review should scan the library's changelog for any changes to comparison operators.

---

### SEC-03 (Feature 004)
Per-site table isolation for multisite: `AcrossAI_*_Table::$global = false`. BerlinDB automatically handles per-site prefixing.

**Application to Feature 007**: MUST test multisite behavior post-upgrade. The library should not interact with the database directly (it manages rules, not tables), but verify no multisite regressions occur. Test scenario 5 (integration tests) should include multisite coverage.

---

## Related Historical Lessons

### DEC-PERM-CB Implementation (Feature 004)
The permission callback injection has been in production for ~2-3 sprints (Features 004–006). The pattern is stable and well-tested. The upgrade must not break this critical pattern.

**Application to Feature 007**: Test scenario 2 is the make-or-break test. If permission callbacks don't inject correctly post-upgrade, access control enforcement silently fails. This is the highest-priority test.

---

## Known Implementation Risks

### API Compatibility Risk
The current lock is commit `2a9ddbdf...` (dev-main branch). The ^1.0 release may have breaking changes between that commit and the tag. Mitigated by:
1. Running test suite (test scenarios 1, 2, 3 catch API breaks)
2. Changelog review (check wpb-ac github for breaking changes between commit and latest 1.x tag)
3. Manual verification of key public methods: `get_manager()`, `user_has_access()`

### Silent Permission Regression
If the library's `user_has_access()` method changes behavior (e.g., returns null instead of false), permission checks could fail unpredictably. Mitigated by:
1. Test scenario 3 (user access check) validates boolean return and logic consistency
2. Staging deployment with permission rule sanity checks before production

### Multisite Regression
Library might not handle per-site rule scoping correctly. Mitigated by:
1. Test scenario 5 includes multisite environment verification
2. Integration tests run in both single-site and multisite contexts

---

## Conflict Warnings

**None identified**. Feature 007 is orthogonal to other features. DEC-PERM-CB and DEC-FAIL-OPEN-NOTICE are existing patterns; Feature 007 only needs to verify they still work post-upgrade.

---

## Retrieval Notes

**Index entries considered**: 6 Active Decisions, 7 Architecture Constraints, 4 Security Constraints (17 rows scanned)  
**Source sections read**:  
- INDEX.md (routing): identified DEC-PERM-CB, DEC-FAIL-OPEN-NOTICE, SEC-04, SEC-03 as relevant (4 selected)
- DECISIONS.md: DEC-PERM-CB (2026-05-16), DEC-FAIL-OPEN-NOTICE (2026-05-17) — ~280 lines total
- ARCHITECTURE.md: AC-HOOKS-MAIN constraint — ~50 lines

**Budget status**: 2 decisions, 1 constraint, 2 security constraints selected (within limits: max 5/5/3)

---

## Planning Phase Watchpoints

1. **API Compatibility Audit**: Before updating composer.json, inspect wpb-access-control changelog on GitHub for breaking changes between pinned commit and latest ^1.0.x tag.
2. **Test Execution Order**: Run test scenarios in priority order: P1 first (1, 2, 3, 5), then P2 (4). P1 tests are blocking; P2 is informational.
3. **Multisite Validation**: If the project's multisite sandbox is available, run tests there. If not, document as "not tested in multisite" in release notes.
4. **Fail-Open Verification**: Manually remove the library file and verify the admin notice displays. This confirms DEC-FAIL-OPEN-NOTICE is still working.

