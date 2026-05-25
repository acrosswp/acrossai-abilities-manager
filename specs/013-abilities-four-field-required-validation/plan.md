# Implementation Plan: Abilities Four-Field Required Validation

**Branch**: `013-abilities-four-field-required-validation` | **Date**: 2026-05-25 | **Spec**: [spec.md](spec.md)
**Input**: [spec.md](spec.md) — enforce ability_slug, label, description, category as required across form UI and PHP backend.

---

## Summary

Add end-to-end required-field enforcement for the four core ability identity fields. The change is surgical: five files touched, no DB layer, no sanitizer, no webpack config, no new classes. The React form gains `formErrors` state, blur-time validation, and CSS-only save-button disabling in create/edit modes. The PHP validator gains a new `validate_description()` static method and tighter empty-string guards on `validate_label()` and `validate_category()`. The processor gains a description registrability guard. The REST create handler gains three presence guards that fire before the shared validator.

---

## Technical Context

**Language/Version**: PHP 7.4+, JavaScript/React (Node ≥ 20 for build)
**Primary Dependencies**: `@wordpress/element`, `@wordpress/i18n`, `@wordpress/data`, BerlinDB (DB read — no changes)
**Storage**: No changes
**Testing**: PHPCS, PHPStan level 8, ESLint, `nvm use 20 && npm run build`
**Target Platform**: WordPress 6.9+ admin
**Performance Goals**: No new network requests; validation is synchronous client-side
**Constraints**: No `disabled` HTML attribute for the new visual-only button state; no changes outside the five files listed; mode=override unaffected; "Save as Draft" always clickable

---

## Constitution Check

| Gate | Status | Notes |
|------|--------|-------|
| §II WordPress Standards — WPCS strict, PHPStan 8, ESLint | ✅ Required | All new PHP uses strict `=== ''`, `trim()`, `mb_strlen()`; i18n strings use `__()` |
| §III User-Centric Design — DataForm mandate | ✅ Accepted-Deviation | DEC-DESIGN-OVERRIDES-DATAVIEWS: custom form UI is the approved pattern; inline `.field-error` divs are consistent with existing `slugError`/schema-error pattern |
| §IV Security First — sanitize → validate order | ✅ Required | Presence guards fire on already-sanitized `$fields`; strict string comparison (SEC-04) |
| §VI DRY / no duplication | ✅ Required | `validate_description()` is the single validation entry point; wired in `validate_ability()` via existing `$simple_validators` array |
| §VII DoD — PHPCS, PHPStan, ESLint, build | ✅ Required | All gates listed in T017 |

---

## Project Structure

### Documentation (this feature)

```text
specs/013-abilities-four-field-required-validation/
├── spec.md
├── plan.md                  ← this file
├── memory-synthesis.md
└── checklists/
    └── requirements.md
```

### Source Files Changed (5 total)

```text
src/js/abilities/components/AbilityForm.jsx                          ← React (UI)
src/scss/abilities/admin.scss                                        ← SCSS (NO CHANGE — .field-error already exists)
includes/Utilities/AcrossAI_Abilities_Validator.php                  ← PHP utility
includes/Modules/Abilities/AcrossAI_Abilities_Processor.php          ← PHP processor
includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php  ← PHP REST
```

---

## Memory Synthesis

From [memory-synthesis.md](memory-synthesis.md):

- **DEC-UTILITY-STATIC-ONLY**: `validate_description()` must be a `public static` method — no singleton on utility classes.
- **DEC-DESIGN-OVERRIDES-DATAVIEWS**: Inline `<div className="field-error">` is the approved pattern (matches existing `slugError` and `inputSchemaError`).
- **SEC-02 + SEC-04**: Presence guards fire *after* `sanitize_create_request()` and *before* `validate_ability()`. Use `'' === trim()` strict comparisons.
- **BUG-PHPCS-DOCBLOCK-CAPITAL**: New PHP docblock long descriptions must start with "The ".
- **SCSS no-op confirmed**: `.field-error` at admin.scss:1258 already has `color: $red; font-size: 11px; margin-top: 4px;`.

---

## Pre-flight Observations (from source inspection)

| File | Finding |
|------|---------|
| `AbilityForm.jsx:L295` | `slugError` state already uses `.field-error` div — same pattern for all four fields |
| `AbilityForm.jsx:L730` | Description field label has `<span className="lopt">optional</span>` — must change to `<span className="req"> *</span>` |
| `AcrossAI_Abilities_Validator:L207` | `validate_label()` null-pass is present; no empty-string rejection — add `'' === trim($label)` after `is_string` check |
| `AcrossAI_Abilities_Validator:L225` | `validate_category()` same gap; `null` and format checked but not `trim()` empty |
| `AcrossAI_Abilities_Validator:L392` | `$simple_validators` array has `label`, `category` keys — add `description` key here |
| `AcrossAI_Abilities_Processor:L122` | `is_row_registrable()` checks slug + label + category — add `'' === trim( (string) $row->description )` guard after category check (SEC-04: never `empty()` on string fields) |
| `AcrossAI_Abilities_Write_Controller:L142` | `create_ability()` calls `sanitize_create_request()` then `validate_ability()` — insert three presence guards between these two calls |
| `admin.scss:L1258` | `.field-error` exists with correct values — **no SCSS change required** |
| `admin.scss:L433` | `.req` class exists — use it for description label |

---

## Implementation Tasks

### CHANGE 1 — `AcrossAI_Abilities_Validator.php`

#### T001 — Add `DESCRIPTION_MAX_LENGTH` constant

**File**: `includes/Utilities/AcrossAI_Abilities_Validator.php`
**Position**: After the existing `CATEGORY_MAX_LENGTH` constant (line ~52)

```php
/**
 * Maximum allowed length for a description (characters).
 *
 * @var int
 */
const DESCRIPTION_MAX_LENGTH = 1000;
```

---

#### T002 — Tighten `validate_label()`: reject empty-string and whitespace-only

**File**: `includes/Utilities/AcrossAI_Abilities_Validator.php`
**Position**: Inside `validate_label()`, after the `is_string` check and before `mb_strlen`

Add after the `! is_string($label)` block:

```php
if ( '' === trim( $label ) ) {
    return new \WP_Error( 'invalid_label', __( 'Ability label cannot be empty.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
}
```

**Null-tolerance preserved**: The `null === $label` early return at the top of the method is unchanged.

**PHPCS note**: The WP_Error string starts with "Ability" — satisfies the docblock-capital rule.

---

#### T003 — Tighten `validate_category()`: reject empty-string and whitespace-only

**File**: `includes/Utilities/AcrossAI_Abilities_Validator.php`
**Position**: Inside `validate_category()`, after the `! is_string($category)` block and before `mb_strlen`

Add:

```php
if ( '' === trim( $category ) ) {
    return new \WP_Error( 'invalid_category', __( 'Ability category cannot be empty.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
}
```

**Null-tolerance preserved**: The `null === $category` early return is unchanged.

---

#### T004 — Add `validate_description()` static method

**File**: `includes/Utilities/AcrossAI_Abilities_Validator.php`
**Position**: After `validate_category()`, before `validate_status()`

```php
/**
 * The validate_description method checks that description is a non-empty string within the allowed length.
 *
 * Null is accepted for partial-update flows (update rows that do not submit description).
 * Empty string and whitespace-only strings are rejected.
 *
 * @since  0.1.0
 * @param  mixed $description Value to validate.
 * @return true|\WP_Error
 */
public static function validate_description( $description ) {
    if ( null === $description ) {
        return true; // nullable — partial-update rows may omit description.
    }
    if ( ! is_string( $description ) ) {
        return new \WP_Error( 'invalid_description', __( 'Ability description must be a string.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
    }
    if ( '' === trim( $description ) ) {
        return new \WP_Error( 'invalid_description', __( 'Ability description cannot be empty.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
    }
    if ( mb_strlen( $description ) > self::DESCRIPTION_MAX_LENGTH ) {
        return new \WP_Error( 'invalid_description', __( 'Ability description must not exceed 1000 characters.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
    }
    return true;
}
```

---

#### T005 — Wire `validate_description()` into `validate_ability()`

**File**: `includes/Utilities/AcrossAI_Abilities_Validator.php`
**Position**: Inside `validate_ability()`, in the `$simple_validators` array

Add `'description'` entry after `'label'`:

```php
$simple_validators = array(
    'label'         => array( self::class, 'validate_label' ),
    'description'   => array( self::class, 'validate_description' ),
    'category'      => array( self::class, 'validate_category' ),
    // ... rest unchanged
);
```

The `array_key_exists` guard in the loop means `validate_description()` only runs when `description` is present in `$fields` — null-safe for update flows that omit description.

---

### CHANGE 2 — `AcrossAI_Abilities_Processor.php`

#### T006 — Add description guard in `is_row_registrable()`

**File**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php`
**Position**: Inside `is_row_registrable()`, after the `empty($row->category)` check

```php
if ( '' === trim( (string) $row->description ) ) {
    return false;
}
```

**Docblock update**: Update the `is_row_registrable()` docblock summary to mention all four checked fields:

```php
/**
 * Determine whether a row has the minimum required data to register.
 *
 * The method skips rows with an empty slug, empty label, empty category,
 * or empty description (all four are required by the WP Abilities API and
 * the Abilities Manager data model).
 *
 * @since  0.1.0
 * @param  AcrossAI_Abilities_Row $row Row to check.
 * @return bool
 */
```

---

### CHANGE 3 — `AcrossAI_Abilities_Write_Controller.php`

#### T007 — Add presence guards in `create_ability()` for label, description, category

**File**: `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`
**Position**: Inside `create_ability()`, after `$fields = AcrossAI_Abilities_Sanitizer::sanitize_create_request($request)` and **before** `$valid = AcrossAI_Abilities_Validator::validate_ability($fields, true)`

```php
// Presence guards (create only): label, description, and category are required
// for ALL creates, including draft-status creates (server-strict per FR-013/SC-003).
// SEC-04: strict comparison '' === trim() throughout. SEC-02: after sanitize, before validate.
if ( '' === trim( (string) ( $fields['label'] ?? '' ) ) ) {
    return new \WP_Error( 'missing_label', __( 'Ability label is required.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
}
if ( '' === trim( (string) ( $fields['description'] ?? '' ) ) ) {
    return new \WP_Error( 'missing_description', __( 'Ability description is required.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
}
if ( '' === trim( (string) ( $fields['category'] ?? '' ) ) ) {
    return new \WP_Error( 'missing_category', __( 'Ability category is required.', 'acrossai-abilities-manager' ), array( 'status' => 400 ) );
}
```

**Why before `validate_ability()`**: `validate_label(null)`, `validate_category(null)`, and the new `validate_description(null)` all return `true` (null-tolerant). A create request that omits these fields would produce null values after sanitization and pass the validator. The presence guards close this gap explicitly, matching spec FR-013 and the `missing_*` error codes.

**Why unconditional**: FR-013/SC-003 requires HTTP 400 for all creates with empty required fields, including draft-status creates. The "Save as Draft" button remains visually enabled client-side (FR-005), but the server enforces the same rule regardless of status.

**`update_ability()` unchanged**: Partial PATCH does not require these fields. The update path already uses `validate_ability($fields, false)` which is null-tolerant for all three. No changes to `update_ability()`.

---

### CHANGE 4 — `AbilityForm.jsx`

All changes are within a single file: `src/js/abilities/components/AbilityForm.jsx`

#### T008 — Add `formErrors` state

**Position**: After the existing `[slugError, setSlugError]` state declaration (~line 297)

```jsx
// Required-field validation errors (create/edit only)
const [formErrors, setFormErrors] = useState({
    slug_suffix: '',
    label: '',
    description: '',
    category: '',
});
```

---

#### T009 — Add `validateRequiredFields()` pure helper function

**Position**: Module level, just before the `AbilityForm` component definition (outside the component, near `formatDate`)

```jsx
/**
 * Validate the four required fields in create/edit mode.
 * Returns an object with error strings; empty string means no error.
 *
 * @param {Object} draft      Current draftAbility object.
 * @param {string} slugSuffix User-typed slug suffix (without prefix).
 * @return {Object}
 */
function validateRequiredFields(draft, slugSuffix) {
    const REQUIRED = __('This field is required.', 'acrossai-abilities-manager');
    return {
        slug_suffix: (slugSuffix || '').trim() ? '' : REQUIRED,
        label:       (draft.label || '').trim() ? '' : REQUIRED,
        description: (draft.description || '').trim() ? '' : REQUIRED,
        category:    (draft.category || '').trim() ? '' : REQUIRED,
    };
}
```

---

#### T010 — Modify `handleSave()`: validate before dispatch in create/edit (all save paths)

**Position**: Inside `handleSave()`, at the top of the function body, before the `const data = { ...draftAbility }` line

```jsx
// Required-field gate: runs in create/edit mode for ALL save paths, including
// "Save as Draft" (forceDraft=true). Server enforces the same four-field rule
// unconditionally (FR-013/SC-003); blocking client-side first gives faster UX.
if ('create' === mode || 'edit' === mode) {
    const errors = validateRequiredFields(draftAbility, slugSuffix);
    setFormErrors(errors);
    if (Object.values(errors).some(Boolean)) {
        return;
    }
}
```

---

#### T011 — Add `onBlur` handlers for all four required fields

**Position**: Near `handleSlugChange()` and `handleInputSchemaBlur()`, in the handler section

```jsx
// ---------------------------------------------------------------------------
// Required-field blur handlers (show error on focus-out if empty)
// ---------------------------------------------------------------------------
function handleSlugBlur() {
    if (!slugSuffix.trim()) {
        setFormErrors((prev) => ({
            ...prev,
            slug_suffix: __('This field is required.', 'acrossai-abilities-manager'),
        }));
    }
}

function handleLabelBlur() {
    if (!(draftAbility.label || '').trim()) {
        setFormErrors((prev) => ({
            ...prev,
            label: __('This field is required.', 'acrossai-abilities-manager'),
        }));
    }
}

function handleDescriptionBlur() {
    if (!(draftAbility.description || '').trim()) {
        setFormErrors((prev) => ({
            ...prev,
            description: __('This field is required.', 'acrossai-abilities-manager'),
        }));
    }
}

function handleCategoryBlur() {
    if (!(draftAbility.category || '').trim()) {
        setFormErrors((prev) => ({
            ...prev,
            category: __('This field is required.', 'acrossai-abilities-manager'),
        }));
    }
}
```

**onChange error clearing** — modify each field's `onChange` prop (or `handleSlugChange`) to clear its corresponding error when a value is entered:

- **Slug** (`handleSlugChange`): add `if (raw.trim()) { setFormErrors((prev) => ({ ...prev, slug_suffix: '' })); }` before the `patch()` call.
- **Label** `onChange`: change to `(e) => { patch({ label: e.target.value }); if (e.target.value.trim()) { setFormErrors((prev) => ({ ...prev, label: '' })); } }`
- **Description** `onChange`: change to `(e) => { patch({ description: e.target.value }); if (e.target.value.trim()) { setFormErrors((prev) => ({ ...prev, description: '' })); } }`
- **Category** `onChange`: change to `(e) => { patch({ category: e.target.value }); if (e.target.value) { setFormErrors((prev) => ({ ...prev, category: '' })); } }`

---

#### T012 — Add `hasRequiredErrors` derived value

**Position**: In the "Derived state" section, after `isOverride` declaration (~line 497)

```jsx
// Compute whether any required field is currently empty (create/edit only).
// Drives CSS-only visual disable on primary save buttons (FR-004).
// mode=override is explicitly excluded (FR-006).
const hasRequiredErrors =
    ('create' === mode || 'edit' === mode)
        ? !slugSuffix.trim() ||
          !(draftAbility.label || '').trim() ||
          !(draftAbility.description || '').trim() ||
          !(draftAbility.category || '').trim()
        : false;
```

---

#### T013 — Render `<div className="field-error">` beneath each required field

**Context**: These renders are inside the `{!isOverride && (...)}` Section 1 block, so they only appear in create/edit modes. The `formErrors` state only receives values in those modes. In override mode `hasRequiredErrors` is false and `formErrors` are always empty — no error divs ever render.

**Slug field**: After the existing `{slugError && <div className="field-error">{slugError}</div>}` block, add:
```jsx
{('create' === mode || 'edit' === mode) && formErrors.slug_suffix && (
    <div className="field-error">{formErrors.slug_suffix}</div>
)}
```
Also add `onBlur={handleSlugBlur}` to the slug `<input>`.

**Label field**: The label `<input>` currently has no `onBlur`. Add the following inside the `<div className="ff">` after the `<input>`:
```jsx
{('create' === mode || 'edit' === mode) && formErrors.label && (
    <div className="field-error">{formErrors.label}</div>
)}
```
And add `onBlur={handleLabelBlur}` to the label `<input>`.

**Category field**: After the `<select>` closing tag, inside `<div className="ff">`:
```jsx
{('create' === mode || 'edit' === mode) && formErrors.category && (
    <div className="field-error">{formErrors.category}</div>
)}
```
And add `onBlur={handleCategoryBlur}` to the category `<select>`.

**Description field**: After the `<textarea>` closing tag, inside `<div className="ff">`:
```jsx
{('create' === mode || 'edit' === mode) && formErrors.description && (
    <div className="field-error">{formErrors.description}</div>
)}
```
And add `onBlur={handleDescriptionBlur}` to the description `<textarea>`.

---

#### T014 — Change description field label: `.lopt "optional"` → `.req *`

**Position**: Description field label, inside the `{!isOverride && (...)}` Section 1 block (~line 730)

**Before**:
```jsx
<label htmlFor="ability-description" className="fl">
    {__('Description', 'acrossai-abilities-manager')}
    <span className="lopt">
        {__('optional', 'acrossai-abilities-manager')}
    </span>
</label>
```

**After**:
```jsx
<label htmlFor="ability-description" className="fl">
    {__('Description', 'acrossai-abilities-manager')}
    <span className="req"> *</span>
</label>
```

Also add `maxLength={AcrossAI_Abilities_Validator::DESCRIPTION_MAX_LENGTH}` — since this is JS, use the numeric constant directly:

```jsx
<textarea
    id="ability-description"
    maxLength={1000}
    {/* …existing props unchanged… */}
/>
```

The `.req` class already exists in `admin.scss:433` with `color: $red; font-weight: 400; margin-left: 2px;`. No SCSS change needed.

---

#### T015 — Apply CSS-only disable to primary save buttons when `hasRequiredErrors`

The primary save buttons are located in two places: the `.hactions` header area and the sidebar boxes. The "Save as Draft" buttons (`onClick={() => handleSave(true)}`) are **never** affected.

**Pattern**: Add a `style` prop with `opacity: 0.5` and `pointerEvents: 'none'` when `hasRequiredErrors`. Existing `disabled` props remain unchanged (they handle isSaving/isDirty state).

**A) `.hactions` header primary button** (~line 573):
```jsx
<button
    type="button"
    className="button button-primary"
    style={hasRequiredErrors ? { opacity: 0.5, pointerEvents: 'none' } : undefined}
    aria-disabled={hasRequiredErrors}
    disabled={isSaving || (!isCreate && !isDirty)}
    onClick={() => handleSave(false)}
>
    {saveBtnLabel}
</button>
```

**B) Create sidebar `sbox` primary button** (~line 1228):
```jsx
<button
    type="button"
    className="button button-primary button-large"
    style={{
        width: '100%',
        justifyContent: 'center',
        ...(hasRequiredErrors ? { opacity: 0.5, pointerEvents: 'none' } : {}),
    }}
    aria-disabled={hasRequiredErrors}
    disabled={isSaving}
    onClick={() => handleSave(false)}
>
```

**C) Edit sidebar `sbox` primary button** (~line 1253):
```jsx
<button
    type="button"
    className="button button-primary button-large"
    style={{
        width: '100%',
        justifyContent: 'center',
        ...(hasRequiredErrors ? { opacity: 0.5, pointerEvents: 'none' } : {}),
    }}
    aria-disabled={hasRequiredErrors}
    disabled={isSaving || !isDirty}
    onClick={() => handleSave(false)}
>
```

The `isOverride` sidebar primary button is **unchanged** — `hasRequiredErrors` is `false` for override mode, so even if we added the style it would be a no-op. Leave it untouched to minimize diff.

---

#### T016 — Reset `formErrors` in `savedAbility` useEffect

**Position**: Inside the existing `useEffect` that syncs `slugSuffix` from `savedAbility` (~line 310)

Add at the top of the effect callback:
```jsx
setFormErrors({ slug_suffix: '', label: '', description: '', category: '' });
```

This ensures that when an ability loads (or a new ability is saved and the view switches to edit), any stale errors are cleared (FR-016).

---

### CHANGE 5 — `admin.scss`

#### T017 — No SCSS change required

`.field-error` already exists at `admin.scss:1258`:
```scss
.field-error {
    color:      $red;      // #d63638
    font-size:  11px;
    margin-top: 4px;
}
```

`.req` already exists at `admin.scss:433`:
```scss
.req, .required { color: $red; font-weight: 400; margin-left: 2px; }
```

Both match the spec requirements exactly. No file edit needed.

---

## Validation & Build

#### T018 — Build and quality gates

Run in order:

```bash
# 1. JavaScript build (Node 20 required — DEC-NODE-20-BUILD-REQUIRED)
nvm use 20 && npm run build

# 2. ESLint
npm run lint:js

# 3. PHPCS on changed PHP files
./vendor/bin/phpcs includes/Utilities/AcrossAI_Abilities_Validator.php \
    includes/Modules/Abilities/AcrossAI_Abilities_Processor.php \
    includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php

# 4. PHPStan level 8 on changed PHP files
./vendor/bin/phpstan analyse \
    includes/Utilities/AcrossAI_Abilities_Validator.php \
    includes/Modules/Abilities/AcrossAI_Abilities_Processor.php \
    includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php \
    --level=8

# 5. Package validation
npm run validate-packages
```

All must pass with zero errors before the feature is complete.

---

## Manual Acceptance Test Sequence

| Test | Expected Result | Maps to |
|------|----------------|---------|
| Open Add New, leave all fields empty, click "Add Ability" | Four inline "This field is required." messages appear; no POST sent | SC-001, SC-002, FR-001 |
| Open Add New, tab away from empty Label field | Label error appears immediately | SC-001, FR-002 |
| Type a character into the Label field after error shows | Label error clears immediately | FR-003 |
| Open Add New, fill all four fields, click "Add Ability" | Form submits, no errors | FR-001 |
| Open Edit, clear Label, click "Save Changes" | Label error; no PATCH sent | FR-001, FR-002 |
| Open Add New, click "Save as Draft" with all fields empty | Inline field-level errors appear for all empty required fields; no API request made (same as clicking primary save button — forceDraft bypass removed per CLARIFY-Q5/A) | SC-005, FR-005, T010 |
| Open Override form | No errors visible, save button fully enabled | SC-006, FR-006 |
| `POST /abilities` without `description` | HTTP 400, `code: missing_description` | SC-003, FR-013 |
| `POST /abilities` without `label` | HTTP 400, `code: missing_label` | SC-003, FR-013 |
| `POST /abilities` without `category` | HTTP 400, `code: missing_category` | SC-003, FR-013 |
| `POST /abilities/{id}` (update) without `description` | HTTP 200/success (partial update allowed) | FR-014 |
| `is_row_registrable()` with empty description row | Returns false; ability not registered | SC-004, FR-008 |
| Description field label in Add New form | Shows red `*` (not "optional") | FR-007 |
| Primary save button when any required field is empty | Visually dimmed (opacity 0.5); click has no effect | FR-004 |
| "Save as Draft" button when required fields are empty | Button remains visually enabled (not dimmed) — FR-005. Clicking it with empty fields shows inline errors and makes no API request (same gate as primary save). | FR-005, T010 |

---

## Extensibility / Hook Inventory

No new WordPress hooks are introduced by this feature. The existing hooks (`acrossai_abilities_before_create`, `acrossai_abilities_after_create`) fire in the same positions as before — the new presence guards fire before `acrossai_abilities_before_create` and return early if validation fails, so the hook is never called for invalid payloads. This is the intended and correct behavior.

---

## Risk Register

| Risk | Mitigation |
|------|-----------|
| Existing abilities saved before this feature have empty descriptions — edit form loads without errors (CLARIFY-Q2/B) | No errors shown on page load; errors appear only on blur or save attempt. Admin must fill description before "Save Changes" works. "Save as Draft" button visually enabled but gate applies. |
| `validate_description(null)` must remain true for partial-update PATCH flows | The null early-return is explicit in T004; UPDATE path in write controller is untouched |
| `trim()` on `$row->description` in Processor may trip if `description` is not a string in the Row object | Cast to `(string)` before trim: `'' === trim( (string) $row->description )` — SEC-04 compliant; handles null and non-string values safely |
| CSS-only disable on primary button: keyboard users can still tab to button (pointer-events only blocks mouse) | Acceptable for this iteration; the `handleSave()` gate provides the actual enforcement |
| PHPStan: `$fields['label'] ?? ''` with nullable string type | Nullish coalescing to `''` + explicit cast `(string)` satisfies PHPStan strict typing |
