# Tasks: Abilities Four-Field Required Validation

**Input**: Design documents from `/specs/013-abilities-four-field-required-validation/`
**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md) | **Memory**: [memory-synthesis.md](./memory-synthesis.md)
**Branch**: `013-abilities-four-field-required-validation`

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files or different method locations, no dependency on incomplete tasks)
- **[US1]**: User Story 1 — Admin blocked from saving incomplete ability (P1)
- **[US2]**: User Story 2 — Inline blur-time validation (P2)
- **[US3]**: User Story 3 — PHP backend rejects empty required fields (P1)

## Key Constraints (from memory-synthesis.md)

- **SEC-04**: Use `'' === trim()` strict comparisons throughout — never `empty()` on string fields
- **SEC-02**: Sanitize → presence guard → `validate_ability()` order in write controller
- **DEC-UTILITY-STATIC-ONLY**: All validator methods must be `public static`
- **BUG-PHPCS-DOCBLOCK-CAPITAL**: PHP docblock long descriptions must start with "The "
- **BUG-PHPCBF-TABS**: PHP file edits must use tabs (not spaces)
- **SCSS no-op confirmed**: `.field-error` exists at `admin.scss:1258` — T018 is a no-op
- **DEC-DESIGN-OVERRIDES-DATAVIEWS (accepted deviation)**: Phase 4 tasks (T009–T016) use the custom `formErrors` / `validateRequiredFields` / `.field-error` validation pattern — NOT `@wordpress/dataforms`. This is the pre-existing approved pattern per `DEC-DESIGN-OVERRIDES-DATAVIEWS` (DECISIONS.md) and GitHub issue #16. Do NOT replace with DataForm.

---

## Phase 1: Setup

**Purpose**: Confirm pre-conditions and build environment before any file changes

- [x] T001 Verify build environment: run `nvm use 20 && node --version` and confirm `git branch` shows `013-abilities-four-field-required-validation`; run `./vendor/bin/phpcs --version` and `./vendor/bin/phpstan --version` to confirm tooling is available

**Checkpoint**: Node 20 active, on correct branch, PHPCS and PHPStan both accessible.

---

## Phase 2: Foundational — PHP Validator Tightening

**Purpose**: Add `DESCRIPTION_MAX_LENGTH` constant, tighten empty-string guards on existing methods, and introduce `validate_description()`. These are required by both US3 (REST create guards) and US1 (server-strict enforcement). All changes in `includes/Utilities/AcrossAI_Abilities_Validator.php`.

**⚠️ CRITICAL**: Phase 3 (US3) and Phase 4 (US1) both depend on this phase. Complete and verify before beginning those phases.

- [x] T002 Add `DESCRIPTION_MAX_LENGTH = 1000` constant after `CATEGORY_MAX_LENGTH` constant (~line 52) in `includes/Utilities/AcrossAI_Abilities_Validator.php` — use `/** The maximum... @var int */` docblock format (BUG-PHPCS-DOCBLOCK-CAPITAL)
- [x] T003 [P] Tighten `validate_label()` in `includes/Utilities/AcrossAI_Abilities_Validator.php`: add `if ( '' === trim( $label ) ) { return new \WP_Error(...) }` after the `! is_string($label)` block; preserve `null === $label` early return (null stays valid for update flows)
- [x] T004 [P] Tighten `validate_category()` in `includes/Utilities/AcrossAI_Abilities_Validator.php`: add `if ( '' === trim( $category ) ) { return new \WP_Error(...) }` after the `! is_string($category)` block; preserve `null === $category` early return
- [x] T005 Add `validate_description()` static method in `includes/Utilities/AcrossAI_Abilities_Validator.php` after `validate_category()`: null → true; non-string → WP_Error `invalid_description`; empty/whitespace → WP_Error `invalid_description`; `mb_strlen > self::DESCRIPTION_MAX_LENGTH` → WP_Error `invalid_description`; valid → true. Uses `DESCRIPTION_MAX_LENGTH` constant from T002.
- [x] T006 Wire `validate_description()` into `validate_ability()` `$simple_validators` array in `includes/Utilities/AcrossAI_Abilities_Validator.php`: add `'description' => array( self::class, 'validate_description' )` after the `'label'` entry. The `array_key_exists` loop guard ensures it only runs when description is present in `$fields` — null-safe for PATCH flows.

**Checkpoint**: Run `./vendor/bin/phpcs includes/Utilities/AcrossAI_Abilities_Validator.php` and `./vendor/bin/phpstan analyse includes/Utilities/AcrossAI_Abilities_Validator.php --level=8` — both must pass zero errors before continuing.

---

## Phase 3: User Story 3 — PHP Backend Rejects Empty Required Fields (Priority: P1)

**Goal**: REST `POST /abilities` returns HTTP 400 for missing/empty `label`, `description`, or `category`; `is_row_registrable()` returns false for rows with empty description.

**Independent Test**: `curl -X POST /wp-json/acrossai-abilities-manager/v1/abilities` with authenticated headers but no `description` field → HTTP 400, `{"code":"missing_description",...}`.

- [x] T007 [P] [US3] Add description guard to `is_row_registrable()` in `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`: insert `if ( '' === trim( (string) $row->description ) ) { return false; }` after the `empty($row->category)` check; update `is_row_registrable()` docblock to list all four checked fields (slug, label, category, description). Cast `(string)` before trim for SEC-04 compliance.
- [x] T008 [US3] Add three presence guards in `create_ability()` in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`: insert after `$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request($request)` and before `$valid = AcrossAI_Abilities_Validator::validate_ability($fields, true)`. Guards: `missing_label` / `missing_description` / `missing_category` each returning `new \WP_Error(...)` with `status => 400`. Use `'' === trim( (string) ( $fields['key'] ?? '' ) )` (SEC-04, SEC-02). Guards are unconditional — no `$is_draft` branch (CLARIFY-Q1/B).

**Checkpoint**: Run `./vendor/bin/phpcs` and `./vendor/bin/phpstan analyse` on both changed PHP files — zero errors. Manual: `POST /abilities` without description → 400 `missing_description`.

---

## Phase 4: User Story 1 — Admin Blocked from Saving Incomplete Ability (Priority: P1)

**Goal**: Clicking "Add Ability" or "Save Changes" with any empty required field shows inline errors beneath each empty field and makes no API request. Primary save buttons visually dimmed when any required field is empty.

**Independent Test**: Open Add New form, leave all fields empty, click "Add Ability" — four inline "This field is required." messages appear, no POST request in Network tab.

All tasks in this phase edit `src/js/abilities/components/AbilityForm.jsx`.

- [x] T009 [US1] Add `formErrors` state: `const [formErrors, setFormErrors] = useState({ slug_suffix: '', label: '', description: '', category: '' })` after the `[slugError, setSlugError]` state declaration (~line 297) in `src/js/abilities/components/AbilityForm.jsx`
- [x] T010 [P] [US1] Add `validateRequiredFields()` pure helper function at module level (outside component, near `formatDate`) in `src/js/abilities/components/AbilityForm.jsx`: returns `{ slug_suffix, label, description, category }` error-string object; uses `__('This field is required.', 'acrossai-abilities-manager')` for each empty-after-trim field; empty string means no error
- [x] T011 [US1] Modify `handleSave()` in `src/js/abilities/components/AbilityForm.jsx`: add required-field gate at top of function body — `if ('create' === mode || 'edit' === mode) { const errors = validateRequiredFields(draftAbility, slugSuffix); setFormErrors(errors); if (Object.values(errors).some(Boolean)) { return; } }` — applies to ALL save paths including `forceDraft=true` (CLARIFY-Q5/A bypass removed)
- [x] T012 [P] [US1] Add `hasRequiredErrors` derived value in derived-state section (~line 497) in `src/js/abilities/components/AbilityForm.jsx`: `const hasRequiredErrors = ('create' === mode || 'edit' === mode) ? (!slugSuffix.trim() || !(draftAbility.label||'').trim() || !(draftAbility.description||'').trim() || !(draftAbility.category||'').trim()) : false;`
- [x] T013 [US1] Render inline `<div className="field-error" role="alert" aria-live="polite">` beneath each of the four required fields inside `{!isOverride && (...)}` block in `src/js/abilities/components/AbilityForm.jsx`: slug (after existing `{slugError && ...}` block), label, description, and category; each guarded by `('create' === mode || 'edit' === mode) && formErrors.fieldName &&`
- [x] T014 [P] [US1] Update description field in `src/js/abilities/components/AbilityForm.jsx`: change `<span className="lopt">{__('optional'...)}</span>` to `<span className="req"> *</span>` on the description label; add `maxLength={1000}` to the description `<textarea>` (FR-007, FR-017). `.req` class already exists at admin.scss:433.
- [x] T015 [US1] Apply CSS-only disable to all three primary save button instances in `src/js/abilities/components/AbilityForm.jsx`: add `style={hasRequiredErrors ? { opacity: 0.5, pointerEvents: 'none' } : undefined}` and `aria-disabled={hasRequiredErrors}` to (A) `.hactions` header button, (B) create sidebar `sbox` button, (C) edit sidebar `sbox` button. "Save as Draft" buttons are NOT modified. No HTML `disabled` attribute added (tab order preserved per FR-004).
- [x] T016 [US1] Add `setFormErrors({ slug_suffix: '', label: '', description: '', category: '' })` at the top of the existing `savedAbility` `useEffect` callback (~line 310) in `src/js/abilities/components/AbilityForm.jsx` — resets stale errors when ability loads or after a successful save (FR-016, CLARIFY-Q2/B: no errors on page load)

**Checkpoint**: Run `npm run lint:js` — zero ESLint errors. Manual: Open Add New, leave fields empty, click "Add Ability" — four inline errors, zero network requests.

---

## Phase 5: User Story 2 — Inline Blur-Time Validation (Priority: P2)

**Goal**: Required-field errors appear immediately when the admin tabs away from an empty required field; errors clear immediately when the admin types a non-empty value.

**Independent Test**: Open Add New form, click into Label field, leave it empty, tab away — "This field is required." appears beneath label without clicking Save.

- [x] T017 [US2] Add `onBlur` handlers and `onChange` error-clearing for all four required fields in `src/js/abilities/components/AbilityForm.jsx`: add `handleSlugBlur()`, `handleLabelBlur()`, `handleDescriptionBlur()`, `handleCategoryBlur()` functions in the handler section (near `handleSlugChange()`/`handleInputSchemaBlur()`); each checks `trim()` and calls `setFormErrors(prev => ({...prev, field: __('This field is required.', 'acrossai-abilities-manager')}))` if empty. Also modify each field's `onChange` to clear its error when a non-empty value is entered: `setFormErrors(prev => ({...prev, field: ''}))`. Wire `onBlur` prop on slug `<input>`, label `<input>`, description `<textarea>`, category `<select>`.

**Checkpoint**: Run `npm run lint:js` — zero errors. Manual: Tab away from empty Label field → error appears; type a character → error clears.

---

## Phase 6: Polish & Quality Gates

**Purpose**: Confirm SCSS no-op and run all required quality gates before marking the feature complete.

- [x] T018 [P] Confirm SCSS no-op: verify `.field-error { color: $red; font-size: 11px; margin-top: 4px; }` exists at `src/scss/abilities/admin.scss:1258` and `.req` exists at line ~433 — no file edit required. Record confirmation as comment in PR description.
- [x] T019 Run full quality gate sequence in order: `nvm use 20 && npm run build` → `npm run lint:js` → `./vendor/bin/phpcs includes/Utilities/AcrossAI_Abilities_Validator.php includes/Modules/Abilities/AcrossAI_Abilities_Processor.php includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` → `./vendor/bin/phpstan analyse includes/Utilities/AcrossAI_Abilities_Validator.php includes/Modules/Abilities/AcrossAI_Abilities_Processor.php includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php --level=8` → `npm run validate-packages`. All must pass with zero errors.

**Final Checkpoint**: All 19 tasks complete, all quality gates green, all manual acceptance tests from plan.md passing.

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1 (Setup)
    └── Phase 2 (Foundational — Validator)
            ├── Phase 3 (US3 — PHP Backend) [T007 can also start during Phase 2]
            └── Phase 4 (US1 — React form gate)
                    └── Phase 5 (US2 — Blur-time UX)
                            └── Phase 6 (Polish & Quality)
```

### User Story Dependencies

| Story | Depends on | Reason |
|---|---|---|
| **US3 (P1)** | Phase 2 complete | `validate_ability()` wiring (T006) must exist before create presence guards are meaningful end-to-end |
| **US1 (P1)** | Phase 2 complete | Server-strict enforcement underpins the client gate design (CLARIFY-Q1/B) |
| **US2 (P2)** | Phase 4 complete (T009, T013) | onBlur handlers need `formErrors` state (T009) and `.field-error` divs (T013) to be wired |

### Parallel Opportunities Within Phases

**Phase 2**:
- T003 + T004 can run in parallel (different method bodies in the same file)
- T007 (processor) can start immediately — it does not depend on any Phase 2 validator changes

**Phase 4**:
- T009 + T010 can run in parallel (component state vs module-level function)
- T012 can run in parallel with T011 (both depend only on T009)
- T014 can run in parallel with T013 + T015 (description label/maxLength is independent of formErrors/hasRequiredErrors)

---

## Parallel Execution Examples

### Example: Two developers, Phase 2 + Phase 3 (US3)

```bash
# Developer A — Phase 2: Validator
# T002: Add DESCRIPTION_MAX_LENGTH constant
# T003 + T004 [P]: Tighten validate_label() and validate_category() simultaneously
# T005: Add validate_description() (after T002)
# T006: Wire validate_description() into validate_ability() (after T003-T005)

# Developer B — Phase 3 T007 [P]: Processor guard (can start immediately)
# Then wait for Developer A to finish T006 before doing T008 (write controller)
```

### Example: One developer, Phase 4 fast path

```bash
# T009 first (formErrors state — all others in Phase 4 build on it)
# T010 [P] + T012 [P] can be done together (pure function + derived value, different locations)
# T011 (handleSave gate — needs T009 + T010)
# T013 + T014 [P] + T015 together (all JSX render changes — T014 truly independent)
# T016 last in Phase 4 (useEffect reset — needs T009)
```

---

## Implementation Strategy

### MVP Scope (minimum to unblock review)

Phase 1 + Phase 2 + Phase 3 = **US3 complete**: the PHP backend rejects empty required fields. This is independently deployable and verifiable via REST calls.

### Full P1 Delivery

Phase 1–4 complete = **US1 + US3 complete**: admin cannot save incomplete abilities through the UI, PHP API enforces the same rule. Form shows inline errors and dimmed primary buttons.

### Full Feature

All phases complete = **US1 + US2 + US3 complete**: all three user stories implemented, blur-time UX included, all quality gates passing.

---

## Manual Acceptance Tests (from plan.md)

| # | Test | Expected | Maps to |
|---|---|---|---|
| 1 | Open Add New, leave all fields empty, click "Add Ability" | Four inline errors; no POST | SC-001, SC-002, FR-001 |
| 2 | Open Add New, tab away from empty Label field | Label error appears immediately | SC-001, FR-002 |
| 3 | Type a character into Label field after error | Error clears immediately | FR-003 |
| 4 | Open Add New, fill all four fields, click "Add Ability" | Submits, no errors | FR-001 |
| 5 | Open Edit, clear Label, click "Save Changes" | Label error; no PATCH sent | FR-001, FR-002 |
| 6 | Open Add New, click "Save as Draft" with all fields empty | All four inline errors; no API request (forceDraft bypass removed) | SC-005, FR-005, T011 |
| 7 | Open Override form | No errors, save button fully enabled | SC-006, FR-006 |
| 8 | `POST /abilities` without `description` | HTTP 400, `code: missing_description` | SC-003, FR-013 |
| 9 | `POST /abilities` without `label` | HTTP 400, `code: missing_label` | SC-003, FR-013 |
| 10 | `POST /abilities` without `category` | HTTP 400, `code: missing_category` | SC-003, FR-013 |
| 11 | `POST /abilities/{id}` (update) without `description` | HTTP 200/success | FR-014 |
| 12 | `is_row_registrable()` with empty-description row | Returns false; not registered | SC-004, FR-008 |
| 13 | Description field label in Add New form | Shows red `*` (not "optional") | FR-007 |
| 14 | Primary save button when any required field is empty | Dimmed (opacity 0.5); click has no effect | FR-004 |
| 15 | "Save as Draft" button when required fields empty | Button remains visually enabled; click shows inline errors, no API request | FR-005 |
| 16 | `PATCH /abilities/{id}` with explicit `label: ""` | HTTP 400, `invalid_label` — intended; prevents clearing required fields via partial update (plan.md Risk Register) | T003, T006 |
