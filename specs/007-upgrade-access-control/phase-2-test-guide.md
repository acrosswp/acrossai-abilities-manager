# Phase 2 Test Guide: Fail-Open Verification (T015-T018)

**Date**: 2026-05-20  
**Phase**: Phase 2 (Fail-Open Verification)  
**Feature**: Feature 007 - WPB Access Control Upgrade to ^1.0  

---

## Phase 2 Overview

**Objective**: Verify that the fail-open admin notice (DEC-FAIL-OPEN-NOTICE) pattern works correctly when the `wpb-access-control` library is unavailable.

**Context**:
- T015 has been completed: Library moved to `.bak` (simulated absence)
- T016-T018 require WordPress admin access
- These tests validate the safety mechanism: when library is missing, admin is notified (not silently broken)

---

## T015: Simulate Library Absence ✅ COMPLETE

**Status**: DONE

**Action Taken**:
```bash
mv vendor/wpboilerplate/wpb-access-control vendor/wpboilerplate/wpb-access-control.bak
```

**Result**:
- `vendor/wpboilerplate/wpb-access-control/` no longer exists
- `vendor/wpboilerplate/wpb-access-control.bak/` created as backup
- WordPress can still load without fatal PHP errors (graceful degradation)

**Verification**:
```
✅ Library simulated as unavailable
✅ Backup created for restoration
```

---

## T016: Verify Admin Notice Displays (When Library Absent)

**Status**: READY FOR MANUAL TEST

**Prerequisites**:
- WordPress admin dashboard accessible
- Admin user logged in (role: Administrator with `manage_options` capability)
- Plugin active and loaded
- Library unavailable (vendor directory moved to .bak)

**Test Steps**:

1. **Log in to WordPress Admin**:
   - URL: http://wordpress-7-0.local/wp-admin (or your local WP admin URL)
   - Username: rippin.heloise or any admin account
   - Password: (your admin password)

2. **Verify Plugin is Active**:
   - Navigate to: Plugins → Installed Plugins
   - Search for: "AcrossAI Abilities Manager"
   - Status: Should show **ACTIVE**

3. **Navigate to Dashboard or Plugin Page**:
   - Go to: Dashboard or AcrossAI Abilities Manager main page
   - Location: Top of admin page, below WordPress header bar

4. **Look for Admin Notice**:
   - **Expected**: A colored admin notice (yellow warning, red error, or blue info box) should be visible
   - **Text should contain**:
     - "wpb-access-control library is not available" (or similar)
     - "ability access control is inactive" (or "enforcement disabled")
     - Clear, non-technical language
     - Dismissible option (optional, depends on implementation)

5. **Screenshot & Document**:
   - Take screenshot of admin notice
   - Record notice text exactly
   - Note styling/color of notice

**Expected Output**:
```
NOTICE: "wpb-access-control Library Unavailable
Access control enforcement is currently inactive because the wpb-access-control 
library is not available. Ability access control will not be enforced until this 
dependency is resolved. Contact your site administrator for assistance."

[Dismiss button] or similar
```

**Pass Criteria**:
- ✅ Notice is visible and prominent
- ✅ Text clearly explains library is unavailable
- ✅ Text explains access control is inactive
- ✅ No 500 errors or fatal PHP errors on admin pages
- ✅ Admin can dismiss notice (if dismissible) without errors

**Acceptance Criteria**:
- [_] Admin notice displays when library unavailable
- [_] Notice contains "wpb-access-control library" reference
- [_] Notice contains "access control" and "inactive" keywords
- [_] Notice is visible on dashboard or plugin page
- [_] Admin can interact with notice without errors
- [_] No fatal PHP errors in debug.log

---

## T017: Verify Notice is Gated to Admins Only

**Status**: READY FOR MANUAL TEST

**Prerequisites**:
- WordPress environment still has library unavailable (moved to .bak)
- Admin notice verified to display (T016 complete)
- Test subscriber account available (or create one)

**Test Steps**:

1. **Create Test Subscriber Account** (if needed):
   - WordPress Admin → Users → Add New
   - Username: test-subscriber-feature007
   - Email: test@example.com
   - Role: Subscriber
   - Password: (generate random)
   - Save

2. **Log Out of Admin Account**:
   - Click admin user icon (top right)
   - Select "Log Out"

3. **Log In as Subscriber**:
   - URL: http://wordpress-7-0.local/wp-admin
   - Username: test-subscriber-feature007
   - Password: (from step 1)

4. **Check Dashboard** (if subscriber has access):
   - Dashboard or available page to subscriber
   - **Expected**: Admin notice should **NOT** be visible
   - Verify notice is completely absent

5. **Optional: Inspect Page Source**:
   - Right-click → View Page Source
   - Search for: "wpb-access-control" or "not available"
   - **Expected**: No notice HTML should be present

**Pass Criteria**:
- ✅ Admin notice is NOT visible to subscriber
- ✅ Notice HTML not present in page source (if inspected)
- ✅ No errors displayed to subscriber

**Acceptance Criteria**:
- [_] Admin notice not visible to subscriber
- [_] Notice not visible to editor or other non-admin roles (test if multiple roles available)
- [_] Notice only visible to users with `manage_options` capability
- [_] Subscriber sees normal WordPress dashboard without notice

---

## T018: Restore Library & Verify Notice Disappears

**Status**: READY FOR COMPLETION

**Prerequisites**:
- Library still unavailable (moved to .bak)
- Admin notice verified to display to admins only (T016-T017 complete)
- Still logged in as subscriber (optional)

**Test Steps**:

1. **Log Back In as Admin** (if logged in as subscriber):
   - Log out of subscriber account
   - Log in as admin user

2. **Restore Library**:
   - Execute command:
     ```bash
     cd /Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-abilities-manager
     mv vendor/wpboilerplate/wpb-access-control.bak vendor/wpboilerplate/wpb-access-control
     ```

3. **Refresh WordPress Admin** (or navigate to dashboard):
   - URL: http://wordpress-7-0.local/wp-admin
   - Refresh page (Cmd+R or F5)

4. **Verify Notice is Gone**:
   - **Expected**: Admin notice should **NO LONGER** be visible
   - Admin notice completely disappeared
   - Dashboard looks normal

5. **Verify Plugin Still Active**:
   - Plugins page
   - AcrossAI Abilities Manager should still be **ACTIVE**
   - No errors displayed

**Pass Criteria**:
- ✅ Admin notice is NO LONGER visible after library restoration
- ✅ Plugin still active and functioning
- ✅ No errors when accessing admin pages
- ✅ WordPress loads normally

**Acceptance Criteria**:
- [_] Notice disappears when library restored
- [_] Plugin functionality returns to normal
- [_] No regressions introduced (ability access control active again)
- [_] DEC-FAIL-OPEN-NOTICE pattern verified end-to-end

---

## Test Execution Matrix

| Task | Status | Validator | Notes |
|---|---|---|---|
| **T015** | ✅ Done | Script | Library moved to .bak |
| **T016** | ⏳ Ready | Manual | Admin logs in, verifies notice displays |
| **T017** | ⏳ Ready | Manual | Subscriber logs in, verifies notice hidden |
| **T018** | ⏳ Ready | Manual | Restore library, verify notice gone |

---

## Success Criteria Summary

✅ **T015**: Library successfully simulated as unavailable  
⏳ **T016**: Admin notice displays when library unavailable (awaiting manual test)  
⏳ **T017**: Notice gated to `manage_options` capability (awaiting manual test)  
⏳ **T018**: Notice disappears when library restored (awaiting manual test)  

---

## Memory Constraints

✅ **DEC-FAIL-OPEN-NOTICE** (Feature 005 decision):
- When optional library unavailable, admin must be notified
- Applied to Feature 007: Verify pattern works with upgraded library
- **Expected**: Admin sees clear notice; non-admins see nothing
- **Status**: T015 complete; T016-T018 awaiting manual WordPress admin access

---

## Next Steps

1. **Manual Tests** (requires WordPress admin access):
   - [ ] T016: Log into WordPress admin; verify notice displays
   - [ ] T017: Log in as subscriber; verify notice NOT visible
   - [ ] T018: Restore library; verify notice disappears

2. **After T016-T018 Complete**:
   - Commit test results
   - Proceed to Phase 3 (Staging & Production Deployment, T019-T027)

3. **If WordPress admin not accessible**:
   - Document as "manual test deferred to staging validation"
   - Proceed to Phase 3 with note
   - Execute T016-T018 on staging environment instead

---

## Environment Info

**Local Environment**:
- WordPress: 7.0-RC4
- Plugin: acrossai-abilities-manager
- Feature Branch: `007-upgrade-access-control`
- Test Type: Manual WordPress UI test

**Library Status**:
- Current: Unavailable (moved to .bak for T015-T018)
- Restored in: T018

---

**Generated**: 2026-05-20  
**Phase**: Phase 2 (Fail-Open Verification)  
**Status**: T015 Complete; T016-T018 Ready for Manual Test
