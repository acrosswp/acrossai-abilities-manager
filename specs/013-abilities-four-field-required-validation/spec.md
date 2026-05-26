# Feature Specification: Abilities Four-Field Required Validation

**Feature Branch**: `013-abilities-four-field-required-validation`
**Created**: 2026-05-25
**Status**: Draft
**Input**: User description: "Enforce all four required fields (ability_slug, label, description, category) across the Abilities create/edit form and the PHP backend."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Admin blocked from saving incomplete ability (Priority: P1)

An admin filling the Add New or Edit ability form cannot submit the form until all four required fields (ability slug, label, description, category) have values. Inline error messages appear immediately beneath each empty field when the user tries to save or tabs away from an empty field.

**Why this priority**: Prevents invalid/useless ability registrations reaching the WordPress Abilities API and AI agents. This is the primary UX guard.

**Independent Test**: Open Add New form, leave all fields empty, click "Add Ability" — four inline "This field is required." messages appear and no network request is made.

**Acceptance Scenarios**:

1. **Given** the Add New form with all fields empty, **When** the admin clicks "Add Ability", **Then** four inline field-level error messages appear, no POST request is made, and the admin remains on the form.
2. **Given** the Add New form with only the slug filled, **When** the admin clicks "Add Ability", **Then** three errors remain (label, description, category) and the slug error is absent.
3. **Given** the Add New form with all four fields filled, **When** the admin clicks "Add Ability", **Then** the form submits successfully with no inline errors.
4. **Given** the Edit form for an existing ability, **When** the admin clears the label field and clicks "Save Changes", **Then** a label error appears and no PATCH/PUT request is made.
5. **Given** the Override Inherited form (mode=override), **When** the admin views the form, **Then** no required-field errors appear and the save button is fully enabled regardless of empty read-only fields.

---

### User Story 2 - Inline blur-time validation for required fields (Priority: P2)

Errors appear immediately when the admin tabs away from an empty required field — they do not need to click Save to discover a missing field.

**Why this priority**: Improves time-to-correct by surfacing errors at the earliest opportunity rather than only on submit.

**Independent Test**: Open Add New form, click into the Label field, leave it empty, tab away — "This field is required." appears beneath the label input without any other interaction.

**Acceptance Scenarios**:

1. **Given** an empty label input that has focus, **When** the admin tabs away (blur), **Then** "This field is required." appears immediately below the label field.
2. **Given** an error-displaying label field, **When** the admin types a character into the label field, **Then** the error clears immediately on change.
3. **Given** an empty description textarea, **When** the admin tabs away, **Then** an inline error appears below the description textarea.
4. **Given** an empty category select, **When** the admin changes focus away, **Then** an inline error appears below the category select.

---

### User Story 3 - PHP backend rejects empty required fields on create (Priority: P1)

The PHP REST API returns HTTP 400 errors when a POST /abilities request omits or submits empty values for label, description, or category. The `is_row_registrable()` processor guard rejects rows with empty descriptions from being registered with the WordPress Abilities API.

**Why this priority**: Defence in depth — even if the client-side validation is bypassed (e.g., direct API calls, future integrations), the server enforces the same rules.

**Independent Test**: Send `POST /wp-json/acrossai-abilities-manager/v1/abilities` without a `description` field — receive HTTP 400 with `code: 'missing_description'`.

**Acceptance Scenarios**:

1. **Given** a POST /abilities request body with no `description` field, **When** the request is processed, **Then** the API returns HTTP 400 with error code `missing_description`.
2. **Given** a POST /abilities request body with no `label` field, **When** the request is processed, **Then** the API returns HTTP 400 with error code `missing_label`.
3. **Given** a POST /abilities request body with no `category` field, **When** the request is processed, **Then** the API returns HTTP 400 with error code `missing_category`.
4. **Given** a database row with an empty `description` field and status `publish`, **When** the processor runs `is_row_registrable()`, **Then** it returns `false` and the row is not registered with the Abilities API.
5. **Given** a POST /abilities request with all four required fields non-empty, **When** the request is processed, **Then** the API returns HTTP 201 and the ability is persisted.

---

### Edge Cases

- What happens when the description field contains only whitespace (spaces/tabs)?  
  → Treated as empty — client trims before checking, PHP uses `trim()` before comparison. Both reject whitespace-only values.
- How does the system handle an update (PATCH) that omits description?  
  → Partial updates are valid; absence of `description` in the PATCH body is allowed. The presence check only applies to the create handler.
- What happens when "Save as Draft" is clicked with empty required fields?  
  → The button remains clickable (not visually dimmed). Clicking it with empty required fields triggers the same client-side validation gate as the primary save button — inline errors appear immediately and **no API request is made** (forceDraft bypass removed per Clarifications). If the client is bypassed directly, the server still returns HTTP 400 with `missing_label`, `missing_description`, or `missing_category`. The server applies the same four-field rule regardless of `status`.
- What if `validate_label` receives `null` for an update row that inherits its label from the plugin registration?  
  → `null` is still accepted (nullable for update/override rows); only empty string `''` is newly rejected.
- What if the admin opens Edit mode for an existing published ability that was saved before this feature (i.e., it has an empty description in the DB)?  
  → The edit form does NOT show errors immediately on page load. Errors appear only when the admin blurs an empty required field or attempts to save (consistent with FR-002). The admin must fill the field before "Save Changes" works. "Save as Draft" button remains visually enabled (FR-005), but the save will fail server-side if fields remain empty (FR-013).
- What happens when `mb_strlen` is unavailable?  
  → `mbstring` is a WordPress hard dependency; `mb_strlen` is always available in a WP context.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The Add New and Edit ability forms MUST prevent submission when any of the four fields (ability_slug, label, description, category) is empty, displaying an inline error below the offending field.
- **FR-002**: Each required field MUST display its inline error immediately on blur (focus-out) if the field is empty, without requiring a save attempt.
- **FR-003**: Each required field MUST clear its inline error immediately when the user types a non-empty value (onChange).
- **FR-004**: The primary save buttons ("Add Ability" / "Save Changes" and the sticky bar equivalent) MUST be visually disabled (opacity 0.5, pointer-events none) and MUST set `aria-disabled={hasRequiredErrors}` when any required field is empty, without using the HTML `disabled` attribute (tab order must be preserved).
- **FR-005**: The "Save as Draft" button MUST remain visually enabled (not dimmed, pointer-events intact) even when required fields are empty. Clicking it triggers the same required-field validation gate as the primary save button — if any required field is empty, inline errors appear and no API request is made. Both client and server enforce the four-field requirement; the draft pathway does not exempt fields.
- **FR-006**: The Override Inherited form (mode=override) MUST NOT show required-field errors or apply save-button disabling.
- **FR-007**: The description field label MUST display a required asterisk (*) instead of the current "optional" text.
- **FR-008**: The PHP `is_row_registrable()` method MUST return `false` for rows with an empty `description`, preventing them from being registered with the WordPress Abilities API.
- **FR-009**: The PHP `validate_label()` method MUST reject empty string `''` as well as whitespace-only strings, returning a 400 WP_Error, while continuing to accept `null`.
- **FR-010**: The PHP `validate_category()` method MUST reject empty string `''` and whitespace-only strings, returning a 400 WP_Error, while continuing to accept `null`.
- **FR-011**: A new `validate_description()` PHP method MUST validate that description is a non-empty, non-whitespace string not exceeding 1000 characters; `null` is accepted for partial-update flows.
- **FR-012**: The `validate_description()` method MUST be wired into `validate_ability()` so it runs whenever `description` is present in the submitted fields.
- **FR-013**: The REST create handler MUST reject requests that omit or submit empty `description`, `label`, or `category` fields with HTTP 400 and codes `missing_description`, `missing_label`, `missing_category` respectively.
- **FR-014**: The REST update handler MUST NOT require description, label, or category to be present (partial PATCH remains valid).
- **FR-015**: All new user-visible strings MUST be wrapped in `__( '...', 'acrossai-abilities-manager' )`.
- **FR-016**: The `formErrors` state MUST be reset to empty when the component loads a new or freshly-created ability.
- **FR-017**: The description `<textarea>` in `AbilityForm.jsx` MUST carry `maxLength={1000}` to enforce the server-side character limit at the browser level. No character counter is required.

### Key Entities

- **Ability Form (React)**: The `AbilityForm.jsx` component in modes `create` and `edit`. Owns `formErrors` state and `hasRequiredErrors` derived value.
- **Ability Validator (PHP)**: `AcrossAI_Abilities_Validator` — static utility class for field-level validation. Gains `validate_description()` and tightened `validate_label()`/`validate_category()`.
- **Ability Processor (PHP)**: `AcrossAI_Abilities_Processor` — runtime processor. `is_row_registrable()` gains description check.
- **Ability Write Controller (PHP)**: `AcrossAI_Abilities_Write_Controller` — REST create handler gains presence guards for label, description, category.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Admins can see field-level validation errors for all four required fields without submitting the form (blur-time feedback).
- **SC-002**: Zero network requests are made when the admin clicks "Add Ability" while any required field is empty.
- **SC-003**: The REST API returns HTTP 400 for 100% of create requests missing label, description, or category.
- **SC-004**: Abilities with empty descriptions are never registered with the WordPress Abilities API during processor boot.
- **SC-005**: The "Save as Draft" button is not visually disabled; clicking it with empty required fields shows inline field-level errors and makes no API request — identical client behaviour to clicking the primary save button.
- **SC-006**: The Override Inherited form remains unaffected — no new error states, no save button changes.
- **SC-007**: The build succeeds with zero errors (`nvm use 20 && npm run build`).
- **SC-008**: PHPCS and PHPStan level 8 pass with zero errors on changed PHP files.

## Assumptions

- The `formErrors` state approach is consistent with how other form-validation patterns work in the codebase (no global form validation library is in use).
- `mode` prop reliably distinguishes `create`, `edit`, and `override` — no other modes will be affected.
- The `slugSuffix` state variable in `AbilityForm.jsx` holds only the user-typed suffix (not the full `acrossai-abilities/` prefixed slug); this is already the case per the existing `handleSlugChange()` implementation.
- Existing `validate_label()` and `validate_category()` null-tolerance is intentional for update/override flows and must be preserved.
- `mbstring` PHP extension is always available in the WordPress environment.
- The description 1000-character limit is sufficient for AI agent capability descriptions; no product requirement conflicts with this limit.
- The `.field-error` CSS class may already exist from Feature 011; if so, no SCSS change is needed.
## Clarifications

### Session 2026-05-25

- Q: Should FR-013/SC-003 server-side presence checks exempt `status: 'draft'` creates, or apply unconditionally to all creates? → A: Server-strict (Option B) — all creates, including draft-status, require non-empty label, description, and category. FR-005/SC-005 updated to reflect that "Save as Draft" remains clickable client-side only; the server still rejects empty required fields.
- Q: When an admin opens Edit mode for an existing ability with an empty description in the DB, should errors appear immediately on page load or only on blur/save? → A: Blur/save only — errors appear on blur or save attempt, never on initial page load. Edge case updated accordingly.
- Q: Should `aria-disabled={hasRequiredErrors}` be required in FR-004 or left as an implementation detail? → A: Required in FR-004 — screen readers must not announce a visually-disabled button as active. FR-004 updated.
- Q: Should AbilityForm.jsx enforce the 1000-character description limit client-side? → A: `maxLength={1000}` on the textarea only (no character counter). FR-017 added.
- Q: Should the `forceDraft` bypass in `handleSave()` be removed so "Save as Draft" also triggers client-side required-field validation? → A: Yes — remove bypass (Option A). Both buttons now run the same validation gate; FR-005/SC-005 updated. T010 plan updated.
