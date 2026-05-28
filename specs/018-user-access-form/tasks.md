# Tasks: User Access Section — Ability Edit / Add Form

**Input**: Design documents from `specs/018-user-access-form/`
**Prerequisites**: `plan.md` ✅ | `spec.md` ✅ | `memory-synthesis.md` ✅ | `security-constraints.md` ✅

**Accepted Deviation**: Section 5 uses plain `.sect`/`.sect-hdr` HTML (not DataForm) per `DEC-DESIGN-OVERRIDES-DATAVIEWS`. Pre-existing from Feature 010+013.

---

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no unresolved dependencies)
- **[Story]**: US1/US2/US3 label for user story phases
- Exact file paths included in all implementation tasks

---

## Phase 1: Setup (Pre-Flight Verification)

**Purpose**: Verify all background pre-conditions (B-1 through B-6) before any file is modified.
If any check fails, stop and flag — do not proceed.

- [x] T001 Verify B-1 through B-6 pre-conditions: `grep "wpb-access-control" composer.json` (^1.0), `grep -n "@wpb/access-control" webpack.config.js` (→ index.js), `ls includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php`, `grep -n "abilities_ac" includes/Main.php` (register_rest_api wired), `grep -n "createNonceMiddleware" src/js/abilities/index.js` (one registration), `grep -n "rest_url\|nonce" admin/Main.php` (both keys present in inline script)

---

## Phase 2: Foundational (CHANGE-1 — BLOCKING Gate)

**Purpose**: Upgrade the `wpb-access-control` library to v1.0.2 and verify security constraints post-upgrade.
**⚠️ CRITICAL**: Phases 3–6 must NOT begin until T003 passes all checks.

- [x] T002 Run `composer require wpboilerplate/wpb-access-control:^1.0.2 && composer update wpboilerplate/wpb-access-control && composer dump-autoload` to update `composer.json` and vendor directory (CHANGE-1)
- [x] T003 Verify CHANGE-1 post-upgrade (SEC-018-01 — BLOCKING): (a) `grep "AccessControl.scss" vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` → no output; (b) `grep "AccessControl.scss" vendor/wpboilerplate/wpb-access-control/js/index.js` → import present; (c) `grep "export function AccessControl\|export default" vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` → both exports; (d) `grep -n ": bool" vendor/wpboilerplate/wpb-access-control/src/*.php` → is_available() returns strict bool (BUG-AC-NULL-RETURN-SILENT-FAIL); (e) confirm SEC-04 strict comparison (`===`) present in user_has_access() in vendor — if any check fails, stop

**Checkpoint**: CHANGE-1 verified — library v1.0.2 in place, security constraints hold. Phases 3–6 may begin.

---

## Phase 3: User Story 1 — Admin Configures Access on Existing Ability (Priority: P1) 🎯 MVP

**Goal**: Edit/override mode renders `AccessControl` component with correct `namespace`, `resourceKey`, `nonce`, and `restApiRoot`. Admin can assign or revoke per-user access independently of the main form save flow.

**Independent Test**: Open the edit form for any saved ability → scroll to Section 5 "User Access" → confirm `AccessControl` component renders with `namespace="acrossai-abilities"` and `resourceKey={ability_slug}` → interact with it and save → main form `isDirty` state is unaffected. Section order: 1, 2 (non-db only), 3, 4, **5 User Access**, **6 Callback**, **7 Schema**.

- [x] T004 [P] [US1] Update webpack alias to point `@wpb/access-control` at `vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` (not `index.js`) in `webpack.config.js` line ~62–63 (CHANGE-2)
- [x] T005 [P] [US1] Append `@import '../../../vendor/wpboilerplate/wpb-access-control/js/AccessControl';` at end of `src/scss/abilities/admin.scss` (CHANGE-3 — lands styles in `build/css/abilities.css` via existing PHP enqueue; no new PHP asset handle)
- [x] T006 [US1] Add `'access_control_available' => \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control::instance()->is_available(),` as fifth key in `wp_add_inline_script` array in `admin/Main.php` `enqueue_scripts()` method; add inline comment `// Client rendering gate only — server authorization enforced by wpb-ac/v1 REST endpoints (SEC-018-02)`; use FQCN (no new `use` statement); confirm PATTERN-ENQUEUE-PAGE-GUARD (`is_manager_page()` guard) is unchanged (CHANGE-4)
- [x] T007 [US1] Run `composer run phpcs` after T006 — zero errors required; fix any PHPCS violations before continuing
- [x] T008 [US1] Run `composer run phpstan` after T006 — zero errors at level 8 required; fix any PHPStan violations before continuing
- [x] T009 [US1] Read `src/js/abilities/components/AbilityForm.jsx` lines ~1490–1510 and hexdump one line near the Section 4 / Callback boundary to confirm actual tab depth (BUG-ABILITYFORM-JSX-MIXED-DEPTHS guard — do not assume uniform indentation)
- [x] T010 [US1] Add `import { AccessControl } from '@wpb/access-control';` after the last existing import line (~line 30) in `src/js/abilities/components/AbilityForm.jsx`; use named import (not default); write to disk immediately (BUG-PYTHON-STRREPLACE-PARTIAL-WRITE) (CHANGE-5 Step A)
- [x] T011 [US1] Add `const abilitiesConfig = window.acrossaiAbilitiesManager || {};` at module level (after last `const`, outside component function) in `src/js/abilities/components/AbilityForm.jsx`; write to disk immediately (CHANGE-5 Step B)
- [x] T012 [US1] Insert full Section 5 "User Access" JSX block (three conditional branches: `isCreate` placeholder, `!isCreate && !access_control_available` warning, `!isCreate && savedAbility?.ability_slug && access_control_available` AccessControl component) between Section 4 closing `</div>` and the `{/* ── VARIANT A: Section 5 — Callback ── */}` comment in `src/js/abilities/components/AbilityForm.jsx`; use actual tab depth confirmed in T009; `namespace` must be hardcoded string `"acrossai-abilities"`; `resourceKey={savedAbility.ability_slug}`; `restApiRoot={abilitiesConfig.rest_url || '/wp-json'}`; `nonce={abilitiesConfig.nonce || ''}`; do NOT pass `onSave` prop; do NOT touch `handleSave`, `isDirty`, or `isSaving`; write to disk immediately (CHANGE-5 Step C)
- [x] T013 [US1] Renumber sect-num `5`→`6` in the Callback section and sect-num `6`→`7` in the Schema section in `src/js/abilities/components/AbilityForm.jsx`; re-read file after T012 insertion to locate new line positions; change only these two values; sections 1–4 unchanged (CHANGE-5 Step D)

**Checkpoint**: User Story 1 complete — edit/override mode shows AccessControl component, section order correct.

---

## Phase 4: User Story 2 — Create Mode Placeholder (Priority: P2)

**Goal**: Create form shows "Save this ability first to configure user access." placeholder; no `AccessControl` component is mounted; no REST calls to `wpb-ac/v1` routes.

**Independent Test**: Open Add New ability form → verify Section 5 shows only the placeholder message → confirm no JS errors in console → confirm no `wpb-ac/v1` REST calls in Network tab.

- [x] T014 [P] [US2] Verify `isCreate` gate in `src/js/abilities/components/AbilityForm.jsx` Section 5: confirm `{isCreate && (<p className="desc">...</p>)}` branch is present and `AccessControl` is inside the `{!isCreate && savedAbility?.ability_slug && ...}` gate; no `AccessControl` mount path for create mode

**Checkpoint**: User Story 2 complete — create mode shows safe placeholder.

---

## Phase 5: User Story 3 — Library Unavailable Warning Notice (Priority: P3)

**Goal**: When `window.acrossaiAbilitiesManager.access_control_available` is `false`, edit form shows warning notice "User Access is inactive — the wpb-access-control library is not loaded." instead of the component; no JS errors.

**Independent Test**: In browser DevTools, set `window.acrossaiAbilitiesManager.access_control_available = false` before React hydrates (or simulate via PHP) → open any ability edit form → verify warning `<p>` renders in Section 5 → confirm no `AccessControl` component mounted → no JS errors.

- [x] T015 [P] [US3] Verify `!isCreate && !abilitiesConfig.access_control_available` warning branch is present in `src/js/abilities/components/AbilityForm.jsx` Section 5 and references `'acrossai-abilities-manager'` text domain; confirm it is mutually exclusive with the AccessControl render gate (SEC-018-03 — all props server-controlled or hardcoded; SEC-018-04 — `is_available()` result is a render gate only, not an authorization check)

**Checkpoint**: User Story 3 complete — graceful degradation path verified.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Build, quality gates, and full verification checklist.

- [x] T016 Run `nvm use 20 && npm run build` from repo root (DEC-NODE-20-BUILD-REQUIRED — Node 16 will fail with `toSorted` TypeError)
- [x] T017 [P] Verify build output: `grep -r "wpb-ac" build/css/abilities.css` → `.wpb-ac` rules present; confirm `build/js/abilities.css` does NOT exist (no CSS extracted from JS bundle — alias correctly points at AccessControl.js)
- [x] T018 [P] Run `npm run lint:js` after T016 — zero errors required; fix any ESLint violations (AccessControl is used in conditional JSX — not an "unused import")
- [x] T019 Run `npm run validate-packages` — passes (no duplicate React/ReactDOM; `@wpb/access-control` only references vendor)
- [x] T020 Run `git diff --name-only` and verify exactly 5 files modified: `composer.json`, `webpack.config.js`, `src/scss/abilities/admin.scss`, `admin/Main.php`, `src/js/abilities/components/AbilityForm.jsx` (FR-012 — stop if count ≠ 5)
- [x] T021 Complete Manual Verification Checklist from `docs/planning/018-user-access-section-ability-form.md` §Manual Verification Checklist: all 8 CHANGE-5 browser checks + 4 quality-gate checks
- [x] T022 [DoD-Gate] Write Jest unit tests for Section 5 branches in `src/js/abilities/components/AbilityForm.jsx`: (a) `isCreate=true` → placeholder `<p>` rendered, no `AccessControl`; (b) `isCreate=false && access_control_available=false` → warning `<p>` rendered, no `AccessControl`; (c) `isCreate=false && savedAbility.ability_slug && access_control_available=true` → `AccessControl` mounted with correct props (`namespace`, `resourceKey`, `restApiRoot`, `nonce`); confirm no `onSave` prop passed (CONSTITUTION §VII DoD — unit tests for all new logic)

---

## Phase 7: v1.1.0 Library Upgrade — Integrated Save (Spec Revision)

**Purpose**: Upgrade `wpb-access-control` from v1.0.2 → v1.1.0 and wire AC state into `handleSave()`. The library adds `hideSaveButton` and `onChange` props. No separate Save button — one "Save Changes" button saves both ability and AC state.

**Spec changes driving this phase**: FR-003 (hideSaveButton/onChange), FR-006 (^1.1.0), FR-013 (acState/handleAcChange), FR-014 (handleSave AC integration).

**Completed 2026-05-29**: Library v1.1.0 tagged at commit ae49d54 on the `fix/embedded-component-scss-and-resource-key-race` branch, published and available via `composer update`. T023–T030 all applied.

- [x] T023 Upgrade: `composer update wpboilerplate/wpb-access-control` — `composer.lock` now pins `v1.1.0`; `composer.json` constraint `^1.0.2` already satisfies v1.1.0 (no change required)
- [x] T024 Verified v1.1.0 post-upgrade: (a) `hideSaveButton = false` destructured in vendor `AccessControl.js`; (b) `onChange` prop present; (c) footer `<div>` wrapped in `{ ! hideSaveButton && ... }`; (d) `AccessControl.js` still has no `import './AccessControl.scss'`
- [x] T025 Added `const [acState, setAcState] = useState(null)` after `mcpServersError` state; added `handleAcChange` useCallback after `patch` useCallback in `AbilityForm.jsx`
- [x] T026 Updated `handleSave()` in `AbilityForm.jsx`: AC save block inserted in `if ('edit' === mode)` block after both `dispatch.updateAbility` branches and before `return`; gate: `savedAbility?.ability_slug && acState !== null`; empty key → DELETE; non-empty key → PUT; catch → `console.error` only
- [x] T027 Updated Section 5 AccessControl render: added `hideSaveButton={true}` and `onChange={handleAcChange}`; no `onSave` or `saveLabel` passed
- [ ] T028 Run `nvm use 20 && npm run build` — confirm clean build after v1.1.0 props added
- [ ] T029 Run `npm run lint:js`; run `npm run validate-packages` — must pass
- [ ] T030 Run `git diff --name-only` — verify file count

---

## Dependency Graph (User Story Completion Order)

```
T001 (pre-flight)
  └─→ T002 (CHANGE-1 composer upgrade)
        └─→ T003 (post-upgrade verification) ← BLOCKING GATE
              ├─→ T004 [P] (CHANGE-2 webpack alias)
              ├─→ T005 [P] (CHANGE-3 SCSS import)
              └─→ T006 (CHANGE-4 PHP flag)
                    ├─→ T007 (phpcs)
                    └─→ T008 (phpstan)
                          └─→ T009 (tab-depth read)
                                └─→ T010 (import)
                                      └─→ T011 (module const)
                                            └─→ T012 (JSX section insert)
                                                  ├─→ T013 (renumber)
                                                  ├─→ T014 [P] (US2 verify)
                                                  └─→ T015 [P] (US3 verify)
                                                        └─→ T016 (build)
                                                              ├─→ T017 [P] (build output verify)
                                                              └─→ T018 [P] (lint)
                                                                    └─→ T019 (validate-packages)
                                                                          └─→ T020 (file count)
                                                                                └─→ T021 (manual verify)
                                                                                      └─→ T022 [DoD-Gate] (Jest unit tests — §VII DoD)
```

---

## Parallel Execution Examples

### Per-story parallel opportunities

**T004 + T005** (after T003): Different files — run together:
```bash
# Terminal 1
# Edit webpack.config.js: change index.js → AccessControl.js

# Terminal 2  
# Edit src/scss/abilities/admin.scss: append @import
```

**T014 + T015** (after T013): Both are code-review verification steps on the same file (read-only at this point):
```bash
# Both can be verified in a single file read of AbilityForm.jsx after T013
```

**T017 + T018** (after T016): Both are validation steps on build output:
```bash
# Terminal 1: grep build/css/abilities.css for .wpb-ac rules
# Terminal 2: npm run lint:js
```

---

## Implementation Strategy

**MVP Scope**: Phases 1 + 2 + 3 (T001–T013) deliver the complete P1 user story and constitute the full functional feature. P2 (T014) and P3 (T015) are verification of code paths already written in T012.

**Suggested Sequence**:
1. T001–T003 first (library upgrade + blocking verification)
2. T004+T005 in parallel (build config changes, no PHP dependency)
3. T006–T008 sequentially (PHP change + two quality gates)
4. T009–T013 sequentially (JSX, each step writes before next reads)
5. T014+T015 in parallel (read-only verification)
6. T016–T022 sequentially (build → verify → lint → packages → count → manual → Jest unit tests)

**Critical Path**: T001 → T002 → T003 → T006 → T007 → T008 → T009 → T010 → T011 → T012 → T013 → T016 → T018 → T019 → T020 → T021
**Advisory Path (§VII)**: T021 → T022 (Jest unit tests — can run after implementation, before commit)

**Risk Mitigation**:
- T003 is the highest-risk task (library upgrade verification). If it fails, the feature is blocked until the library is corrected.
- T009 (tab-depth read) prevents BUG-ABILITYFORM-JSX-MIXED-DEPTHS from causing str_replace failures in T010–T013.
- T007+T008 between CHANGE-4 and CHANGE-5 ensures PHP quality gates pass before JS work begins.
