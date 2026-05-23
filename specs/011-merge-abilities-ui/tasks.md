---
description: "Task list for feature implementation"
---

# Tasks: Merge Abilities UI & Decommission Sitewide App

**Input**: Design documents from `/specs/011-merge-abilities-ui/`
**Prerequisites**: spec.md ✓ · plan.md ✓ · memory-synthesis.md ✓ · security-constraints.md ✓

**Tests**: No test tasks — this feature has no new logic requiring test coverage.
`is_manager_page()` is a private method with zero branches and a hardcoded string constant; exemption documented per Constitution §VII (zero cyclomatic complexity, no test file justified).
DataViews gate: N/A per DEC-DESIGN-OVERRIDES-DATAVIEWS (abilities React UI, accepted deviation from spec-010).

**Node version**: `nvm use 20` required before any `npm run build` invocation — DEC-NODE-20-BUILD-REQUIRED.

**Security constraints active**: SC-011-01 through SC-011-04 (see security-constraints.md). Note: these `SC-011-xx` IDs refer to security constraints; `SC-001`–`SC-005` in spec.md refer to measurable success criteria — two separate numbering schemes.

**CRITICAL ordering rule**: PHP changes in `admin/Main.php` (T008–T011) MUST be committed before running the clean build step (T015). `admin/Main.php::__construct()` includes `sitewide.asset.php` without a `file_exists()` guard — if the build runs first and removes the file, the site throws a PHP fatal on every admin page (PLAN-SEC-003).

---

## Phase 1: Setup

**Purpose**: Verify environment and internalize all implementation constraints before touching files.

- [ ] T001 Verify current branch is `011-merge-abilities-ui` (`git branch --show-current`), review `specs/011-merge-abilities-ui/plan.md`, `memory-synthesis.md`, and `security-constraints.md` in full before making any changes

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Remove all dead-code source files and the obsolete PHP class. These deletions are independent of each other and can run in parallel.

⚠️ **T004 must complete before Phase 5 (T008) begins** — `admin/Main.php` has a `use` statement for the deleted class.

- [ ] T002 [P] Delete source directory `src/js/sitewide/` in full (FR-009)
- [ ] T003 [P] Delete source directory `src/scss/sitewide/` in full (FR-009)
- [ ] T004 [P] Delete `admin/Partials/AcrossAI_Abilities_Menu.php` in full (FR-010)

**Checkpoint**: Source directories gone; PHP class file deleted. No PHP references yet removed — site still functional at this point (old build artifacts still in `build/`).

---

## Phase 3: User Story 1 — Consolidated Abilities Manager Page (Priority: P1) 🎯 MVP

**Goal**: The "Custom Abilities" submenu (`?page=acrossai-abilities-custom`) no longer exists. Only the top-level Abilities Manager page (`?page=acrossai-abilities-manager`) is registered by this plugin. The abilities React app renders on that page.

**Independent Test**: Log in to WordPress admin. Confirm the "Custom Abilities" sidebar item is gone. Confirm `?page=acrossai-abilities-custom` returns a 404/not-found response. Confirm the Abilities Manager page loads (even if assets are not yet fully switched — that is US3).

- [ ] T005 [US1] Remove `AcrossAI_Abilities_Menu` hook wiring from `includes/Main.php` — delete the two lines at ~278–279: `$abilities_menu = \AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu::instance();` and `$this->loader->add_action( 'admin_menu', $abilities_menu, 'register_submenu' );` (no `use` statement exists in this file; FQN was used inline — nothing else to remove here)
- [ ] T006 [P] [US1] Update `admin/Partials/Menu.php::contents()` — two changes: (1) change `<div id="acrossai-abilities-manager-root"></div>` to `<div id="acrossai-abilities-root"></div>` (FR-003, AC-MENU-IN-PLACE); (2) add `current_user_can('manage_options')` + `wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) )` defense-in-depth check as the first statement in the method body, before any output — matching SEC-010-01 pattern from deleted class (SC-011-01)

**Checkpoint** (US1 complete): WordPress no longer registers `?page=acrossai-abilities-custom`. Sidebar has one plugin entry. `Menu.php` now mounts to `#acrossai-abilities-root` with capability guard.

---

## Phase 4: User Story 2 — Clean Build Output (Priority: P2)

**Goal**: Running `npm run build` produces no sitewide compiled assets.

**Independent Test**: `nvm use 20 && npm run build` (with clean output dir) → `build/js/sitewide.js` and `build/css/sitewide.css` do not exist in the output.

- [ ] T007 [P] [US2] Remove `'js/sitewide'` and `'css/sitewide'` entry blocks from `webpack.config.js` entry object — delete these two blocks entirely, keep all other entries (`js/frontend`, `js/backend`, `css/frontend`, `css/backend`, `js/logger`, `css/logger`, `js/abilities`, `css/abilities`, `...blockStylesheets()`, `...blockEntries`, `...getWebpackEntryPoints()`) unchanged (FR-005)

**Checkpoint** (US2 complete): Webpack config no longer references `src/js/sitewide/index.js` or `src/scss/sitewide/admin.scss`. Next `npm run build` will not emit sitewide output.

---

## Phase 5: User Story 3 — Abilities JS/CSS Loads on Manager Page (Priority: P2)

**Goal**: `abilities.js` and `abilities.css` load on `?page=acrossai-abilities-manager`. `sitewide.js` and `sitewide.css` load on no page. `window.acrossaiAbilitiesManager` is available at app init.

**Independent Test**: Open DevTools Network on `?page=acrossai-abilities-manager` — confirm `abilities.js` + `abilities.css` present, `sitewide.js` absent. Open console — confirm `window.acrossaiAbilitiesManager` is defined.

**⚠️ Prerequisite**: T004 (class file deleted) and T005 (wiring removed) must be complete before T008 begins.
**⚠️ PLAN-SEC-003**: T008 (removes `sitewide.asset.php` include) must be committed before the clean build step T015.
**⚠️ Intra-file edit order**: Within the `admin/Main.php` editing session, execute the changes in this order: T011 first (rename/rewrite `is_abilities_custom_page()` → `is_manager_page()`), then T009 (update `enqueue_styles()` caller), then T010 (update `enqueue_scripts()` caller). This avoids a briefly broken class state where callers reference an undefined method name (RISK-001).

- [ ] T008 [US3] In `admin/Main.php`, make three removals in order: (1) remove `use AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu;` statement (line 12); (2) remove the `$sitewide_asset_file` private property declaration + its full PHPDoc block (~lines 78–85); (3) remove `$this->sitewide_asset_file = include \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/sitewide.asset.php';` from the constructor (~line 118) — this is the PLAN-SEC-003 critical line; PHP will fatal if this survives past the clean build

- [ ] T009 [US3] In `admin/Main.php::enqueue_styles()`: (1) remove `$on_abilities`, `$on_logs`, `$on_custom` local variable declarations; (2) remove `if ( ! $on_abilities && ! $on_logs && ! $on_custom ) { return; }` early-return guard; (3) remove entire sitewide `wp_register_style` + `wp_enqueue_style` block (2 calls); (4) add new early-return guard: `if ( ! $this->is_manager_page( $hook_suffix ) && ! $this->is_logs_page( $hook_suffix ) ) { return; }` as first statement; (5) update abilities enqueue conditional from `$this->is_abilities_custom_page( $hook_suffix )` to `$this->is_manager_page( $hook_suffix )` — keep logger block completely unchanged

- [ ] T010 [US3] In `admin/Main.php::enqueue_scripts()`: mirror T009 changes for scripts — (1) remove `$on_abilities`, `$on_logs`, `$on_custom`; (2) remove old early-return guard; (3) remove entire sitewide `wp_register_script` + `wp_enqueue_script` + `wp_add_inline_script` block (`window.acrossaiAbilitiesSitewide`); (4) add new early-return guard `if ( ! $this->is_manager_page( $hook_suffix ) && ! $this->is_logs_page( $hook_suffix ) ) { return; }`; (5) update abilities enqueue conditional to `is_manager_page()` — keep logger block completely unchanged, keep abilities `wp_add_inline_script` for `window.acrossaiAbilitiesManager` exactly as-is (SC-011-02)

- [ ] T011 [US3] In `admin/Main.php`: replace the entire `is_abilities_custom_page()` private method with `is_manager_page()` — new implementation: `return $hook_suffix === 'toplevel_page_acrossai-abilities-manager';` (=== strict comparison, SC-011-04); update method name in PHPDoc, update `@since` tag to `0.3.0`, update description to "Check if currently viewing the main Abilities Manager page", retain "SEC-04" note

**Checkpoint** (US3 complete): `admin/Main.php` no longer references `AcrossAI_Abilities_Menu` or `sitewide_asset_file`. The abilities enqueue guard uses `is_manager_page()` which matches `toplevel_page_acrossai-abilities-manager`. The logger enqueue guard is untouched.

---

## Phase 6: User Story 4 — Logs Page Unaffected (Priority: P1)

**Goal**: Logger application and Logs admin page continue to work exactly as before.

**Independent Test**: Navigate to the Logs admin page — logs table renders, `logger.js` and `logger.css` load, all log entries display correctly. No JavaScript errors.

- [ ] T012 [P] [US4] Verify `admin/Main.php` logger enqueue branches are intact after T009/T010 — confirm `is_logs_page()` method body is unchanged, all three logger enqueue calls (`wp_register_script`, `wp_enqueue_script`, `wp_add_inline_script` for `window.acrossaiAbilitiesLogger`) are identical to pre-change state; confirm `LogsMenu::instance()` usage and `$this->logger_asset_file` guard are unchanged — no edit needed if verified; raise a blocker if any logger code was accidentally modified

**Checkpoint** (US4 complete): Logger functionality verified intact. All four user stories now have their implementation changes in place.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Static analysis, build verification, and manual acceptance testing.

- [ ] T013 Run static analysis across all modified PHP files: `vendor/bin/phpcs --standard=phpcs.xml.dist admin/Main.php admin/Partials/Menu.php includes/Main.php` and `vendor/bin/phpstan analyse admin/Main.php admin/Partials/Menu.php includes/Main.php --level=8` — zero errors and zero warnings required; fix any violations before T014

- [ ] T014 [P] Run `npm run lint` (ESLint) — zero errors; run `npm run validate-packages` — zero violations; both must pass before T015

- [ ] T015 Run clean build verification (SC-001 precondition): (1) remove stale sitewide build artifacts: `rm -f build/js/sitewide.js build/js/sitewide.asset.php build/css/sitewide.css`; (2) `nvm use 20 && npm run build`; (3) assert `build/js/sitewide.js` does NOT exist and `build/css/sitewide.css` does NOT exist; (4) assert `build/js/abilities.js`, `build/css/abilities.css`, `build/js/logger.js`, `build/css/logger.css` all exist — build exit code must be 0

- [ ] T016 Manual browser verification (all 8 items): (1) `?page=acrossai-abilities-manager` → DevTools Network: `abilities.js` + `abilities.css` loaded ✓; (2) `sitewide.js` not in any page's Network tab ✓; (3) `#acrossai-abilities-root` div present in DOM ✓; (4) React app mounts and abilities interface renders ✓; (5) console: `window.acrossaiAbilitiesManager` defined with nonce, rest_url, rest_namespace, current_user_id ✓; (6) sidebar: "Custom Abilities" menu item absent ✓; (7) `?page=acrossai-abilities-custom` → WordPress shows page-not-found ✓; (8) Logs admin page: logs table renders, `logger.js` + `logger.css` loaded, no JS errors ✓

---

## Dependencies

```
T001
  └─ T002 [P], T003 [P], T004 [P], T006 [P], T007 [P]  ← all parallel
       ├─ T004 ─── T005
       │              └─ T008 ─── T009 ─── T010 ─── T011
       │                                              └─ T012 [P]
       ├─ T006 ──────────────────────────────────────────┘
       └─ T007
              └─ (all above complete) ─── T013 ─── T014 ─── T015 ─── T016
```

### Parallel Execution Examples

**Group A — Phase 2 + early Phase 3/4** (run together once T001 is done):
```
T002 [P]  delete src/js/sitewide/
T003 [P]  delete src/scss/sitewide/
T004 [P]  delete admin/Partials/AcrossAI_Abilities_Menu.php
T006 [P]  update admin/Partials/Menu.php
T007 [P]  update webpack.config.js
```

**Sequential chain** (after T004 complete):
```
T005 → T008 → T009 → T010 → T011 → T012
```

**Final parallel** (after all changes):
```
T013, T014 [P]  (lint + validate-packages, independent)
```

---

## Implementation Strategy

**MVP scope** (User Story 1 only — verifiable after T001–T006):
- Source directories deleted, PHP class deleted, hook wiring removed, Menu.php updated
- WordPress admin shows no "Custom Abilities" submenu
- Manager page renders (existing assets, wrong mount ID until T006 — but submenu goal met)

**Full delivery** (all user stories — after T015–T016):
- Abilities UI renders on manager page from `#acrossai-abilities-root`
- Only `abilities.js` and `logger.js` in build output
- All static analysis clean
- Manual acceptance checklist passed

---

## Summary

| Metric | Value |
|---|---|
| Total tasks | 16 (T001–T016) |
| US1 tasks | T005, T006 |
| US2 tasks | T007 |
| US3 tasks | T008, T009, T010, T011 |
| US4 tasks | T012 (verification) |
| Parallel opportunities | T002, T003, T004, T006, T007 (Group A) · T013, T014 (final) |
| PHP files changed | 3 (`admin/Main.php`, `admin/Partials/Menu.php`, `includes/Main.php`) |
| Files deleted | 3 (`src/js/sitewide/`, `src/scss/sitewide/`, `admin/Partials/AcrossAI_Abilities_Menu.php`) |
| Config files changed | 1 (`webpack.config.js`) |
| Test tasks | 0 (exemption documented above) |
| MVP scope | T001–T006 (US1 complete) |
