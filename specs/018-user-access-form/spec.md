# Feature Specification: User Access Section — Ability Edit / Add Form

**Feature Branch**: `018-user-access-form`
**Created**: 2026-05-28
**Status**: Draft
**Input**: User description: "Add a 'User Access' section (new Section 5) to AbilityForm.jsx immediately after the 'Annotation Overrides' section (Section 4). The section renders the AccessControl component from @wpb/access-control (v1.1.0) with hideSaveButton={true} and onChange wired to parent acState. Access-control state is saved inside the existing handleSave() via PUT/DELETE to /wpb-ac/v1/rules/acrossai-abilities/{slug} — one single Save Changes button, no separate save button. In create mode show a save-first placeholder. In edit/override mode render the component only when savedAbility has a slug and the library is available, or show a disabled notice when it is not. Renumber existing Section 5 (Callback) → 6, Section 6 (Schema) → 7. Five files change: composer.json (version pin), webpack.config.js (alias update), src/scss/abilities/admin.scss (SCSS import), admin/Main.php (flag), and src/js/abilities/components/AbilityForm.jsx (new section + handleSave update)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin Configures User Access on an Existing Ability (Priority: P1)

A WordPress administrator opens the ability edit form for an existing ability that has already been saved. They see a new "User Access" section (Section 5) positioned between "Annotation Overrides" (Section 4) and "Callback" (now Section 6). The AccessControl component is rendered inside the section without its own Save button. The admin selects access rules (e.g., role-based access), then clicks the main "Save Changes" button. The form saves the ability and the access-control rules in a single operation — one button, one intent.

**Why this priority**: This is the core value of the feature — letting admins control who can use each ability. Unified save reduces friction and keeps the UX consistent with the rest of the form.

**Independent Test**: Open the edit form for any saved ability, scroll to Section 5 "User Access", verify the AccessControl component renders without a Save Access Control button, change a role selection, click "Save Changes", and confirm the access-control state is persisted (re-opening the form shows the same selection). Verify the ability data is also saved in the same request.

**Acceptance Scenarios**:

1. **Given** the admin is on the ability edit form for a saved ability with a slug, **When** the page loads with the access-control library available, **Then** Section 5 "User Access" renders the `AccessControl` component with `namespace="acrossai-abilities"`, `resourceKey={ability_slug}`, and no visible "Save Access Control" button.
2. **Given** the admin changes an access-control selection (e.g., enables a role), **When** they click the main "Save Changes" button, **Then** the ability is saved first and then the access-control state is saved via PUT to `{rest_root}/wpb-ac/v1/rules/acrossai-abilities/{slug}` — both in a single user action.
3. **Given** the AC save fails after the ability save succeeds, **When** the form reports success, **Then** the form success notice is shown as normal; the AC failure is logged to the browser console only and does not block the success notice.
4. **Given** the admin is on the ability edit form, **When** they scroll down, **Then** the section order is: 1 Basic Info, 2 Meta, 3 Advanced, 4 Annotation Overrides, 5 User Access, 6 Callback, 7 Schema.

---

### User Story 2 — Admin Creates a New Ability and Sees Save-First Placeholder (Priority: P2)

A WordPress administrator opens the ability create form to add a new ability. When they reach Section 5 "User Access", they see a plain-text placeholder message: "Save this ability first to configure user access." No AccessControl component is rendered. The section heading, number, and description are still visible to make the feature discoverable.

**Why this priority**: Create mode is the second most common path. Showing a clear placeholder prevents confusion and guides the admin to save first.

**Independent Test**: Open the create form for a new ability; verify Section 5 shows only the save-first message with no broken component or console errors.

**Acceptance Scenarios**:

1. **Given** the admin is on the ability create form, **When** the page loads, **Then** Section 5 "User Access" displays the placeholder message "Save this ability first to configure user access." and no AccessControl component is rendered.
2. **Given** the admin fills in the ability form and saves (transitioning to edit mode), **When** the page refreshes or the form re-renders in edit mode, **Then** the AccessControl component (with `hideSaveButton={true}`) replaces the placeholder.

---

### User Story 3 — Admin Sees Disabled Notice When Library Is Unavailable (Priority: P3)

When the `wpb-access-control` PHP library is not installed or not active (i.e. `is_available()` returns `false`), the "User Access" section on the edit/override form shows a warning notice instead of the AccessControl component: "User Access is inactive — the wpb-access-control library is not loaded." No JS errors occur.

**Why this priority**: A graceful degradation path is required for production reliability, but occurs only in abnormal configurations.

**Independent Test**: With `is_available()` forced to return `false` (or the library removed), open any ability edit form and verify the warning notice appears with no JS errors.

**Acceptance Scenarios**:

1. **Given** the access-control library is not available (`window.acrossaiAbilitiesManager.access_control_available === false`), **When** the admin opens the edit form, **Then** a warning notice is shown and the AccessControl component is not rendered.
2. **Given** the library becomes available (flag changes to `true`), **When** the page reloads, **Then** the component renders normally.

---

### Edge Cases

- What happens when `savedAbility.ability_slug` is empty/undefined after a save? The component gate (`!isCreate && savedAbility?.ability_slug && abilitiesConfig.access_control_available`) prevents a mount with an empty `resourceKey`.
- What is `savedAbility.ability_slug` in `isNonDb` (override) mode? It holds the slug of the underlying ability being overridden — the same value used in `isEdit` mode — ensuring a single access-control record is shared across both form modes for the same ability.
- What happens when `window.acrossaiAbilitiesManager` is undefined (e.g. script loaded out of order)? The module-level `const abilitiesConfig = window.acrossaiAbilitiesManager || {}` guard ensures all property accesses are safe.
- What happens when the composer update for v1.0.2 fails? The implementation must stop and flag; it must not continue with a partial upgrade.
- What happens if the SCSS import path is wrong after the alias change? The build will fail with a webpack/sass error — caught at build time before deployment.
- What happens when `!isCreate` is true but `savedAbility?.ability_slug` is falsy (async pre-seed not yet complete)? The section header renders but the body is intentionally empty — this transient state is brief and requires no additional loading UI.
- What happens if the access-control PUT/DELETE call fails inside `handleSave()`? The error is caught in a nested try/catch, logged to the browser console, and does not change the form's success/error notice — the ability itself was already saved successfully.
- What happens if `acState` is `null` when `handleSave()` runs? The AC save block is skipped entirely (`acState !== null` gate). No REST call is made if the user has not yet interacted with the AC component.
- What happens when `acState.key === ''`? A DELETE request is sent to the AC endpoint, removing any existing access-control record for the ability.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The ability edit/add form MUST display a "User Access" section labelled Section 5, positioned between Section 4 (Annotation Overrides) and Section 6 (Callback).
- **FR-002**: In create mode (`isCreate === true`), the User Access section MUST display only a placeholder message: "Save this ability first to configure user access." No AccessControl component is rendered.
- **FR-003**: In edit/override mode, when `savedAbility.ability_slug` is non-empty AND `window.acrossaiAbilitiesManager.access_control_available` is `true`, the `AccessControl` component MUST be rendered with `namespace="acrossai-abilities"`, `resourceKey={savedAbility.ability_slug}`, `restApiRoot={abilitiesConfig.rest_url || '/wp-json'}`, `nonce={abilitiesConfig.nonce || ''}`, `hideHeader={true}`, `hideSaveButton={true}`, and `onChange={handleAcChange}`. No `onSave` or `saveLabel` prop is passed. The `title` and `description` props are omitted — the section's own `sect-hdr` div already renders the equivalent heading and description; passing them alongside `hideHeader={true}` would be dead code (RT-AR-002). In `isNonDb` (override) mode, `savedAbility.ability_slug` is the slug of the underlying ability being overridden — the same value as in `isEdit` mode for the same ability — so both forms share the same access-control record.
- **FR-004**: In edit/override mode, when `access_control_available` is `false`, a warning notice MUST be displayed instead of the AccessControl component.
- **FR-005**: The PHP `admin/Main.php` MUST expose `access_control_available` (boolean) as a fifth key in the `window.acrossaiAbilitiesManager` inline script payload, derived from `AcrossAI_Abilities_Access_Control::instance()->is_available()`.
- **FR-006**: The `wpboilerplate/wpb-access-control` composer package MUST be updated to version `^1.1.1` (library was published as v1.1.1; v1.1.0 was never released).
- **FR-007**: The webpack alias for `@wpb/access-control` MUST be updated to resolve to `vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` (not `index.js`), so that `AccessControl.scss` is not pulled into the JS CSS bundle.
- **FR-008**: The abilities admin stylesheet (`src/scss/abilities/admin.scss`) MUST import `AccessControl.scss` explicitly so that styles land in `build/css/abilities.css`.
- **FR-009**: The existing Callback section MUST be renumbered from 5 to 6, and the Schema section MUST be renumbered from 6 to 7. Sections 1–4 remain unchanged.
- **FR-010**: The `AccessControl` component MUST NOT be tracked in `isDirty` state. Its selection changes are captured via `onChange` into a local `acState` variable that is saved inside `handleSave()` after the ability PUT succeeds. The component has no visible Save button (`hideSaveButton={true}`).
- **FR-013**: The `AbilityForm` component MUST maintain an `acState` piece of state (initially `null`) and a stable `handleAcChange(key, options)` callback that sets `acState` to `{ key, options }`. This callback is passed as `onChange` to the `AccessControl` component. The implementation also maintains `isAcDirty` (bool, initially `false`) and `acInitialRef` (useRef, initially `null`) to detect when AC state has changed from the initial load so the main Save Changes button is enabled on AC-only changes. `isAcDirty` does NOT modify the form's `isDirty` state (RT-AR-003).
- **FR-014**: The `handleSave()` function MUST, after a successful ability PUT, conditionally send a PUT (non-empty key) or DELETE (empty key) to `{abilitiesConfig.rest_url}/wpb-ac/v1/rules/acrossai-abilities/{savedAbility.ability_slug}` when `!isCreate && savedAbility?.ability_slug && acState !== null`. AC save errors MUST be caught in a nested try/catch, logged only, and MUST NOT change the form success/error notice or re-throw.
- **FR-011**: All `__()` translatable strings introduced in this feature MUST use the text domain `'acrossai-abilities-manager'`.
- **FR-012**: Exactly five files MUST change: `composer.json`, `webpack.config.js`, `src/scss/abilities/admin.scss`, `admin/Main.php`, and `src/js/abilities/components/AbilityForm.jsx`.

### Key Entities

- **AbilityForm**: The React component at `src/js/abilities/components/AbilityForm.jsx` that renders the tabbed ability edit/add form with multiple named sections.
- **AccessControl**: The `@wpb/access-control` React component that manages per-user access permissions for a named resource, identified by namespace + resourceKey.
- **AcrossAI_Abilities_Access_Control**: PHP singleton at `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` that reports library availability (`is_available()`) and exposes REST endpoints.
- **window.acrossaiAbilitiesManager**: Client-side configuration object serialized as an inline script by `admin/Main.php::enqueue_scripts()`, consumed by the React app.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The "User Access" section is visible and interactive on any ability edit form within a single page load, with no user-facing errors.
- **SC-002**: In create mode, the placeholder message is displayed and no broken component renders or console errors occur.
- **SC-003**: When the access-control library is unavailable, the warning notice is shown with no JS errors and no broken layout.
- **SC-004**: PHPStan level 8 exits with zero errors after all PHP changes.
- **SC-005**: PHPCS exits with zero errors after all PHP changes.
- **SC-006**: ESLint (`npm run lint:js`) exits with zero errors after all JS/JSX changes.
- **SC-007**: `npm run validate-packages` passes after all JS changes.
- **SC-008**: 100% of introduced translatable strings use the `'acrossai-abilities-manager'` text domain.
- **SC-009**: The git diff shows changes to exactly five files; no other tracked file is modified.

## Clarifications

### Session 2026-05-29

- Q: Why does the implementation use `hideHeader={true}` instead of the `title` and `description` props required by FR-003? → A: The section's own `sect-hdr` div already renders "User Access" heading and "Who can use this ability." description. Passing `title`/`description` alongside `hideHeader={true}` would be dead code. FR-003 updated to reflect `hideHeader={true}` approach (RT-AR-002).
- Q: Why does the implementation add `isAcDirty` and `acInitialRef` beyond the minimum `acState`/`handleAcChange` in FR-013? → A: Without `isAcDirty`, the Save Changes button remains disabled when `isDirty=false` even if the user has changed AC settings, breaking the unified-save UX (US1). `isAcDirty` enables the save button on AC-only changes without touching the form's `isDirty` state. FR-013 updated to document this pattern (RT-AR-003).

### Session 2026-05-28

- Q: In `isNonDb` override mode, does `savedAbility.ability_slug` hold the same slug as the underlying DB ability (same access-control record in both edit and override mode)? → A: Yes — the override record's `ability_slug` field is the slug of the underlying ability; the same `resourceKey` is used in both `isEdit` and `isNonDb` modes.
- Q: When `!isCreate` is true but `savedAbility?.ability_slug` is still falsy (transient pre-seed state), the section body is empty — is this acceptable? → A: Yes — the empty body is intentional and transient; no additional UI is needed.


## Assumptions

- The `wpboilerplate/wpb-access-control` v1.1.1 package is available on the configured composer repository (published 2026-05-29 at tag v1.1.1 — v1.1.0 was never released). Resolvable without changing auth credentials.
- Library v1.1.0 adds `hideSaveButton` (bool, default `false`) and `onChange(acKey, acOptions)` props to the `AccessControl` component (B-8). `onChange` fires when `isLoading` transitions to `false` and on every selection change. The footer `<div>` is conditionally rendered via `{ !hideSaveButton && ... }`.
- The `wpb_access_control` database table is already provisioned by the existing `AcrossAI_Abilities_Access_Control` module (established in Feature 003/007).
- The REST endpoints for access control are already registered via `rest_api_init → $abilities_ac->register_rest_api()` in `includes/Main.php` (B-4).
- The nonce middleware (`apiFetch.createNonceMiddleware`) is already registered globally in `src/js/abilities/index.js` (B-5); no second registration is needed.
- `window.acrossaiAbilitiesManager.nonce` and `window.acrossaiAbilitiesManager.rest_url` are already present in the inline script (B-6); only `access_control_available` is being added.
- No new PHP files, JS entry points, or database schema changes are needed.
- The feature is scoped to the WordPress admin ability form only; no front-end or public-facing changes are included.
- v1.0.2 ships with the `AccessControl.scss` moved out of `AccessControl.js` and into `index.js` only, requiring explicit SCSS import on the consumer side (B-7).
