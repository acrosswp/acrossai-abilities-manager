# Memory Synthesis

## Current Scope

Feature 018 adds a "User Access" section (Section 5) to `AbilityForm.jsx` and upgrades the `wpb-access-control` library to v1.0.2. Five files change: `composer.json` (version pin), `webpack.config.js` (alias), `src/scss/abilities/admin.scss` (SCSS import), `admin/Main.php` (inline-script flag), `src/js/abilities/components/AbilityForm.jsx` (new section + renumber). Affected modules: Admin/Enqueue (`admin/Main.php`), React UI (`AbilityForm.jsx`), Access Control singleton (`AcrossAI_Abilities_Access_Control`), build toolchain.

## Relevant Decisions

- **DEC-FAIL-OPEN-NOTICE** (Reason Included: Feature 018 has a library-absent warning path; must confirm admin_notices hook already wired, Status: Active, Source: DECISIONS.md)
  → Planning note: `maybe_show_library_notice()` is already hooked in Main.php (B-4 + existing code). The inline `.notice` in AbilityForm.jsx is additive. Both are required; do NOT remove the admin_notices wiring.
- **DEC-STABLE-UPGRADE-WINDOW** (Reason Included: CHANGE-1 upgrades composer from v1.0.x to v1.0.2, Status: Active, Source: DECISIONS.md)
  → v1.0.2 is a patch release of the v1.0 stable line. Pre-update audit + post-upgrade security revalidation required before marking CHANGE-1 complete.
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE** (Reason Included: CHANGE-1 upgrades a security-critical library, Status: Active, Source: DECISIONS.md)
  → After `composer update`: (1) `is_available()` returns strict bool, (2) SEC-04 strict comparison holds in `user_has_access()`, (3) admin notice displays when library absent.
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason Included: Section 5 is a plain `.sect` block, not DataForm, Status: Active, Source: DECISIONS.md)
  → Section 5 follows existing plain `.sect`/`.sect-hdr` HTML pattern. Deviation already logged from Feature 010.
- **DEC-MENU-HOOK-SUFFIX** (Reason Included: admin/Main.php is touched (CHANGE-4); enqueue guard must not change, Status: Active, Source: DECISIONS.md)
  → CHANGE-4 adds one key to `wp_add_inline_script` payload in `enqueue_scripts()`. Do NOT change the is_manager_page() guard or hook suffix logic.

## Active Architecture Constraints

- **AC-ENQUEUE-ADMIN** (Reason Included: CHANGE-4 is in admin/Main.php::enqueue_scripts(), Source: CONSTITUTION.md §I)
  → `wp_add_inline_script` in `enqueue_scripts()` — correct location. CHANGE-3 adds to SCSS only, no new PHP enqueue.
- **AC-HOOKS-MAIN** (Reason Included: confirms no new hooks needed; REST hook B-4 already wired, Source: CONSTITUTION.md §I)
  → Feature 018 adds zero new hooks. All pre-conditions (B-4 REST hook, B-5 nonce middleware) are already wired.
- **ARCH-ZERO-CODE-DEPENDENCY-UPGRADE** (Reason Included: CHANGE-1 uses singleton service locator; plugin code need not change for the library upgrade itself, Source: ARCHITECTURE.md)
  → CHANGE-1 is a composer.json version bump only. admin/Main.php is touched only for CHANGE-4 (flag key), not because of the library upgrade.
- **PATTERN-ENQUEUE-PAGE-GUARD** (Reason Included: admin/Main.php enqueue_scripts() guard must not regress, Source: ARCHITECTURE.md)
  → Do NOT change `is_manager_page()` or introduce `strpos` variables. Only the inline script payload changes (CHANGE-4).
- **PATTERN-FEATURE-ASSET-SEPARATION** (Reason Included: CHANGE-3 SCSS import must land in existing build/css/abilities.css, not a new file, Source: ARCHITECTURE.md)
  → `@import` in `src/scss/abilities/admin.scss` is the correct pattern. Do NOT enqueue a new CSS file in PHP. Confirmed in spec FR-008.

## Accepted Deviations

- **DEC-DESIGN-OVERRIDES-DATAVIEWS / DEV1** (Reason Included: AccessControl component is not a DataForm; Section 5 uses plain .sect HTML, Status: Accepted-Deviation)
  → Pre-existing deviation from Feature 010. Section 5 deepens it (Feature 013 set the precedent). Record as accepted in tasks.md.
- **CONSTITUTION §III DataForm mandate** (Reason Included: AbilityForm.jsx uses custom sections; Section 5 follows existing custom section pattern, Status: Accepted-Deviation)
  → No conflict with planning; deviation already accepted in Features 010+013.

## Relevant Security Constraints

- **SEC-04** (Reason Included: access_control_available is a PHP bool; must not introduce loose comparison at any gate, Source: security-constraints.md)
  → `is_available()` returns bool. `wp_json_encode` serializes as JSON true/false. JS gate uses `&&` truthy check — acceptable for a feature flag (not an authorization check). No SEC-04 violation on the JS side; PHP side must confirm `is_available()` returns strict bool.
- **DEC-FAIL-OPEN-NOTICE compliance** (Reason Included: inline warning notice is additive to existing admin_notices hook, Source: DECISIONS.md)
  → Both the inline form notice (CHANGE-5) and the admin_notices hook (`maybe_show_library_notice()`) must be present. CHANGE-5 does not replace the hook notice.
- **Post-upgrade revalidation** (Reason Included: DEC-REVALIDATE-SECURITY-POST-UPGRADE applies to CHANGE-1, Source: DECISIONS.md)
  → Tasks must include a verification step for `is_available()` bool return, SEC-04 in user_has_access(), and admin_notices display after composer update.

## Related Historical Lessons

- **BUG-ABILITYFORM-JSX-MIXED-DEPTHS** (Reason Included: AbilityForm.jsx has inconsistent tab depths — will cause str_replace failures if assumed wrong)
  → Before inserting Section 5 JSX: read the exact line range around Section 4's closing `</div>` and Section 5 (Callback) opening `<div>` to confirm actual tab depths. Do not assume uniform depth.
- **BUG-PHPCBF-TABS** (Reason Included: admin/Main.php is touched in CHANGE-4; PHP file tab handling)
  → Python str_replace on PHP files must use `\t` not spaces. Confirm indentation of the `wp_add_inline_script` array in admin/Main.php before writing.
- **BUG-PYTHON-STRREPLACE-PARTIAL-WRITE** (Reason Included: multi-step Python scripts must write per step)
  → Any Python str_replace script for CHANGE-4 or CHANGE-5 must write to disk after each successful step.
- **BUG-AC-NULL-RETURN-SILENT-FAIL** (Reason Included: v1.0.2 upgrade — verify is_available() returns strict bool post-upgrade)
  → Post-upgrade task: grep `is_available()` in vendor and confirm `: bool` return type, not nullable.

## Conflict Warnings

- **DEC-FAIL-OPEN-NOTICE vs. inline form notice**: Soft concern only. Inline notice in AbilityForm.jsx is ADDITIVE to the existing `admin_notices` hook wired in Main.php. Both must remain present. No conflict — planning doc explicitly states "Do not add admin_notices logic — that already exists."
- **CONSTITUTION §III DataForm vs. custom section**: Pre-existing accepted deviation (DEC-DESIGN-OVERRIDES-DATAVIEWS). No hard conflict for this feature.

## Retrieval Notes

- Index entries considered: 24 (all relevant to Admin/Enqueue, React/UI, Access Control, Dependencies, Build scopes)
- Source sections read: DECISIONS.md lines 226-250, 650-810; ARCHITECTURE.md lines 162-230, 268-370; BUGS.md lines 181-465; security-constraints.md full (21 lines); CONSTITUTION.md lines 1-150
- Budget status: within 900-word limit; full_memory_read_allowed=false respected
