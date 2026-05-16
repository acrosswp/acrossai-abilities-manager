# Tasks: Ability Access Control Tab

**Input**: Design documents from `/specs/003-ability-access-control-tab/`
**Prerequisites**: [plan.md](plan.md) ✅ | [spec.md](spec.md) ✅ | [research.md](research.md) ✅ | [data-model.md](data-model.md) ✅ | [contracts/wpb-ac-v1-rest-api.md](contracts/wpb-ac-v1-rest-api.md) ✅ | [quickstart.md](quickstart.md) ✅

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no incomplete dependencies)
- **[Story]**: User story label — US1 or US2
- No test tasks generated (not requested in spec)

---

## Phase 1: Setup

**Purpose**: Confirm build prerequisites before making code changes

- [X] T001 Confirm vendor library is present: `ls vendor/wpboilerplate/wpb-access-control/js/index.js` — run `composer install` if missing

**Checkpoint**: `vendor/wpboilerplate/wpb-access-control/js/index.js` exists on disk

---

## Phase 2: Foundational (Blocking Prerequisite)

**Purpose**: Register the `wpb-ac/v1` REST routes so the Access Control tab can load and persist data. This MUST be complete before User Story 1 can be verified end-to-end.

**⚠️ CRITICAL**: User Story 1 tab renders without this, but all REST calls return 404 until this hook is registered.

- [X] T002 [US2] In `define_admin_hooks()` in `includes/Main.php`, after the `$mcp_servers_list` add_action call, add two lines: resolve `AcrossAI_Sitewide_Access_Control::instance()` to `$sitewide_ac` then call `$this->loader->add_action( 'rest_api_init', $sitewide_ac, 'register_rest_api' )`

**Checkpoint**: `wp eval 'echo json_encode(array_keys(rest_get_server()->get_routes()));'` output includes `/wpb-ac/v1/` routes

---

## Phase 3: User Story 1 — Access Control Tab UI (Priority: P1) 🎯 MVP

**Goal**: A site administrator can open the ability edit panel, see the "Access Control" tab, interact with the provider dropdown, and persist access rules without a JS error.

**Independent Test**: Open any ability in the edit panel → three tabs visible → Access Control tab renders the provider dropdown → select a rule → close and reopen → rule persists (requires Phase 2 complete for persistence; tab render itself is independently verifiable at the JS level)

### Implementation

- [X] T003 [P] [US1] Add `resolve.alias` block to `webpack.config.js`: spread `defaultConfig.resolve`, spread `defaultConfig.resolve?.alias ?? {}`, and add `'@wpb/access-control': path.resolve(process.cwd(), 'vendor/wpboilerplate/wpb-access-control/js/index.js')`
- [X] T004 [P] [US1] In `src/js/sitewide/components/AbilityEditPanel.jsx`: add `import { AccessControl } from '@wpb/access-control'` after the existing imports; add `accessControlTab` const (wrapping `<AccessControl namespace="acrossai-abilities" resourceKey={slug} restApiRoot={sitewideConfig.rest_url || '/wp-json'} nonce={sitewideConfig.nonce || ''} />` where `sitewideConfig = window.acrossaiAbilitiesSitewide || {}`); add `{ name: 'access-control', title: __( 'Access Control', 'acrossai-abilities-manager' ) }` entry to the `tabs` array; replace the existing two-branch ternary render callback with a three-branch conditional returning `accessControlTab` as the default case

**Checkpoint**: `npm run build` exits 0; three tabs render in the panel; Access Control tab shows the provider dropdown; a rule selection is saved and survives a page reload

---

## Phase 4: Polish & Validation

**Purpose**: Confirm all definition-of-done gates pass before marking feature complete

- [X] T005 [P] Run `composer run phpcs` — zero errors and zero warnings in the 1 modified PHP file (`includes/Main.php`)
- [X] T006 [P] Run `composer run phpstan` — PHPStan level 8 passes with zero errors
- [X] T007 [P] Run `npm run lint:js` — ESLint zero errors in `webpack.config.js` and `AbilityEditPanel.jsx`
- [X] T008 [P] Run `npm run validate-packages` — package hierarchy passes (no Tier 3 conflicts introduced)
- [ ] T009 Manual browser test per [quickstart.md §4](quickstart.md): open panel → 3 tabs → Access Control tab renders → select "Specific Roles → Editor" → save → close → reopen → rule persists

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 — BLOCKS end-to-end testing of US1
- **Phase 3 (US1)**: Depends on Phase 1 only for build (`composer install`); T003 and T004 are [P] and can run simultaneously; end-to-end persistence test requires Phase 2
- **Phase 4 (Polish)**: Depends on all prior phases complete

### User Story Dependencies

- **US2 (P2)** — Phase 2: pure PHP; no JS dependency; can be done first or concurrently with Phase 3
- **US1 (P1)** — Phase 3: T003 and T004 can run in parallel (different files); T004 build verification needs T003

### Within Phase 3

- T003 (`webpack.config.js`) and T004 (`AbilityEditPanel.jsx`) touch different files → fully parallel
- Both must be complete before running `npm run build` to verify

---

## Parallel Execution Example: Phase 3

```
Agent/Dev A                          Agent/Dev B
──────────────────────               ──────────────────────
T003: webpack alias                  T004: JSX tab integration
  webpack.config.js                    AbilityEditPanel.jsx
        │                                      │
        └──────────┬───────────────────────────┘
                   ▼
            npm run build (verify both changes together)
                   ▼
            Phase 4 validation (all [P] — run concurrently)
```

---

## Implementation Strategy

**MVP scope** (minimum to deliver User Story 1 end-to-end):

1. T001 → T002 → T003 + T004 (parallel) → build + browser verify → T005–T009 (parallel)

**Estimated file touches**: 3 files, ~10 lines of code total across all changes.

**Rollback**: All changes are additive. Removing the two lines from `Main.php`, the `resolve` block from `webpack.config.js`, and the import + tab code from `AbilityEditPanel.jsx` fully reverts the feature with no data loss (vendor table exists independently).
