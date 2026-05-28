# Implementation Plan: User Access Section — Ability Edit / Add Form

**Branch**: `018-user-access-form` | **Date**: 2026-05-28 | **Spec**: [specs/018-user-access-form/spec.md](spec.md)
**Input**: Feature specification from `specs/018-user-access-form/spec.md`
**Memory Synthesis**: `specs/018-user-access-form/memory-synthesis.md`

---

## Summary

Add a "User Access" section (Section 5) to `AbilityForm.jsx` that renders the `AccessControl`
component from the already-vendored `wpboilerplate/wpb-access-control` library (v1.1.1),
positioned between existing Section 4 (Annotation Overrides) and the renumbered Section 6
(Callback). The implementation is a five-file surgical changeset: a composer version pin, a
webpack alias update, an SCSS import addition, one key appended to an existing PHP inline-script
payload, and the JSX section insertion + two section renumbers.

**Technical Approach**: No new PHP classes, no new hooks, no new JS entry points, no new
CSS asset handles. All pre-conditions are already wired (B-1 through B-6). The feature
only consumes an existing singleton and an existing React library already aliased in the
webpack config.

---

## Technical Context

**Language/Version**: PHP 7.4+ (production), Node 20 (build — required for `@wordpress/scripts`)
**Primary Dependencies**: `wpboilerplate/wpb-access-control ^1.1.0`, `@wordpress/element`, `@wordpress/i18n`, `@wordpress/data`
**Storage**: N/A (no DB schema changes)
**Testing**: PHPStan L8, PHPCS, ESLint (`npm run lint:js`), `npm run validate-packages`, browser manual verification checklist
**Target Platform**: WordPress 6.9+ admin, PHP 7.4+
**Project Type**: WordPress plugin (existing feature extension)
**Performance Goals**: No new REST calls at page load. `AccessControl` component defers REST to user interaction.
**Constraints**: Exactly 5 files changed. No new hooks. No new PHP classes. No new asset handles. Node 20 required for build.
**Scale/Scope**: Single form component, one PHP flag, one SCSS line.

---

## Constitution Check

*GATE: Checked against CONSTITUTION.md v1.4.2 and memory-synthesis.md*

| § | Rule | Status |
|---|------|--------|
| §I Modular Architecture | No new modules — all changes are additive extensions to existing modules | ✅ PASS |
| §II WordPress Standards | PHPCS strict, PHPStan L8, ESLint zero errors, `'acrossai-abilities-manager'` text domain, PHP 7.4+/WP 6.9+ | ✅ PASS (gates enforced) |
| §III User-Centric Design | AbilityForm uses custom `.sect` sections (DEC-DESIGN-OVERRIDES-DATAVIEWS). AccessControl component is an external vendor UI library, not a DataForm replacement. | ✅ ACCEPTED DEVIATION (pre-existing, Feature 010+013) |
| §IV Security First | All output is serialized by `wp_json_encode`. No user input flows through CHANGE-4. `access_control_available` is a server-side bool, not user-controlled. | ✅ PASS |
| §V Extensibility Without Core Modification | No core files modified. No new hooks added. Library used via its existing singleton. | ✅ PASS |
| §VI DRY | `abilitiesConfig` is read once at module level (same pattern as `client.js`). No new utility classes — existing `AcrossAI_Abilities_Access_Control::instance()` reused. | ✅ PASS |
| §VII Definition of Done | PHPStan L8, PHPCS, ESLint, security review, validate-packages, DataForm deviation recorded | ✅ ALL GATES SCHEDULED |

**Accepted Deviation**: `AbilityForm.jsx` Section 5 uses plain `.sect`/`.sect-hdr` HTML, not DataForm. Governed by `DEC-DESIGN-OVERRIDES-DATAVIEWS`. Pre-existing from Feature 010; deepened by Feature 013. No new deviation introduced.

---

## Project Structure

### Documentation (this feature)

```text
specs/018-user-access-form/
├── spec.md                # Feature spec (complete, clarified)
├── memory-synthesis.md    # Memory synthesis (complete)
├── plan.md                # This file
├── security-constraints.md   # /speckit.security-review.plan output
├── checklists/
│   └── requirements.md    # Existing requirements checklist
└── tasks.md               # /speckit.tasks output (next step)
```

### Source Code (5 files, exact paths)

```text
composer.json                                          # CHANGE-1: ^1.0 → ^1.1.1
webpack.config.js                                      # CHANGE-2: alias index.js → AccessControl.js
src/scss/abilities/admin.scss                          # CHANGE-3: @import AccessControl.scss
admin/Main.php                                         # CHANGE-4: access_control_available key
src/js/abilities/components/AbilityForm.jsx            # CHANGE-5: import + module const + Section 5 + renumber
```

---

## Implementation Phases

### Phase 0 — Pre-flight Verification

Verify all B-1 through B-7 background conditions before touching any file.

| Check | Command | Expected |
|-------|---------|----------|
| B-1: wpb-access-control in composer.json | `grep "wpb-access-control" composer.json` | `"wpboilerplate/wpb-access-control": "^1.0"` |
| B-2: webpack alias | `grep -n "@wpb/access-control" webpack.config.js` | Points to `js/index.js` (will change in CHANGE-2) |
| B-3: Singleton exists | `ls includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` | File present |
| B-4: REST hook in Main.php | `grep -n "abilities_ac" includes/Main.php` | `register_rest_api` wired |
| B-5: Nonce middleware | `grep -n "createNonceMiddleware" src/js/abilities/index.js` | One registration |
| B-6: window config keys | `grep -n "acrossaiAbilitiesManager" admin/Main.php` | `nonce`, `rest_url`, `rest_namespace`, `current_user_id` present |

**Block condition**: If any B-1 through B-6 check fails, stop and flag. Do not proceed.

---

### Phase 1 — CHANGE-1: Upgrade `wpb-access-control` to v1.1.1

**File**: `composer.json`
**Approach**: Run `composer require wpboilerplate/wpb-access-control:^1.1.1 && composer update wpboilerplate/wpb-access-control && composer dump-autoload`

**Post-upgrade verification (B-7 + DEC-REVALIDATE-SECURITY-POST-UPGRADE)**:

| Check | Command | Expected |
|-------|---------|----------|
| AccessControl.js has no SCSS import | `grep "AccessControl.scss" vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` | No output |
| index.js has SCSS import | `grep "AccessControl.scss" vendor/wpboilerplate/wpb-access-control/js/index.js` | Import present |
| Named + default export | `grep "export function AccessControl\|export default AccessControl" vendor/.../AccessControl.js` | Both present |
| is_available() returns strict bool (BUG-AC-NULL-RETURN-SILENT-FAIL) | `grep -n ": bool" vendor/.../AcrossAI_Wpb_Access_Control_Manager.php` | `: bool` return type |
| SEC-04: strict comparison in user_has_access | `grep -n "===" vendor/.../AcrossAI_Wpb_Access_Control_Manager.php` | Strict operators used |

**Block condition**: If AccessControl.js still imports its own SCSS, or if `is_available()` is nullable, stop.

---

### Phase 2 — CHANGE-2: Update webpack alias

**File**: `webpack.config.js` (line ~61–63)

**Current state**:
```js
'@wpb/access-control': path.resolve(
    __dirname,
    'vendor/wpboilerplate/wpb-access-control/js/index.js'
)
```

**Target state**:
```js
'@wpb/access-control': path.resolve(
    __dirname,
    'vendor/wpboilerplate/wpb-access-control/js/AccessControl.js'
)
```

**Rationale**: `index.js` imports `AccessControl.scss`; if the alias points to `index.js`, webpack
will extract the SCSS into `build/js/abilities.css` (wrong CSS file). Pointing directly at
`AccessControl.js` bypasses the SCSS import in index.js. CHANGE-3 then handles styles explicitly
via the SCSS entry point.

**Constraint**: Only the `index.js` string changes. No other part of `webpack.config.js` changes.

---

### Phase 3 — CHANGE-3: Import `AccessControl.scss` via abilities SCSS entry

**File**: `src/scss/abilities/admin.scss`

**Action**: Append the following line at the end of the file:
```scss
@import '../../../vendor/wpboilerplate/wpb-access-control/js/AccessControl';
```

**Rationale**: Because the webpack alias now bypasses `index.js`, the SCSS must be imported
explicitly so styles land in `build/css/abilities.css` (already enqueued by `admin/Main.php`).
No PHP change required. No new asset handle. Follows PATTERN-FEATURE-ASSET-SEPARATION.

---

### Phase 4 — CHANGE-4: Add `access_control_available` to inline script payload

**File**: `admin/Main.php` (lines ~206–219)

**Insertion point**: Inside the `array()` passed to `wp_json_encode()` in `wp_add_inline_script()`,
after `'current_user_id' => get_current_user_id(),`. Add:

```php
'access_control_available' => \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control::instance()->is_available(),
```

**Result** (full array after change):
```php
array(
    'nonce'                     => wp_create_nonce( 'wp_rest' ),
    'rest_url'                  => untrailingslashit( rest_url() ),
    'rest_namespace'            => 'acrossai-abilities-manager/v1',
    'current_user_id'           => get_current_user_id(),
    'access_control_available'  => \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control::instance()->is_available(),
)
```

**Memory guard (BUG-PHPCBF-TABS)**: admin/Main.php uses tabs. Use `\t` in any str_replace
operations on this file. Confirm actual indentation by reading the raw file before modifying.

**Quality gates** (run before CHANGE-5):
- `composer run phpcs` — zero errors
- `composer run phpstan` — zero errors (PHPStan L8, FQCN or `use` statement must resolve)

---

### Phase 5 — CHANGE-5: Insert User Access section in `AbilityForm.jsx`

**File**: `src/js/abilities/components/AbilityForm.jsx`

**Sub-steps (in order; write to disk after each)**:

#### 5-A: Add import
After the last existing `import` line (line ~30), add:
```js
import { AccessControl } from '@wpb/access-control';
```

#### 5-B: Add module-level config constant
After the last module-level `const` (before the component function), add:
```js
const abilitiesConfig = window.acrossaiAbilitiesManager || {};
```

**Memory guard (BUG-ABILITYFORM-JSX-MIXED-DEPTHS)**: Before writing any str_replace string
targeting `AbilityForm.jsx`, read the raw lines around the insertion point with `read_file`
and hexdump one line to confirm tab depth. The file has inconsistent tab depths by element type —
do not assume uniformity.

#### 5-C: Insert Section 5 "User Access" JSX
**Insertion point**: Between Section 4 closing `</div>` and the comment `{/* ── VARIANT A: Section 5 — Callback ── */}` (currently around line 1504).

**Read** lines ~1490–1510 first to confirm exact tab depth of the Section 4 boundary, then insert:

```jsx
{/* ── Section 5 — User Access ── */}
<div className="sect">
    <div className="sect-hdr">
        <div className="sect-title">
            <span className="sect-num">5</span>
            {__('User Access', 'acrossai-abilities-manager')}
        </div>
        <div className="sect-desc">
            {__('Who can use this ability.', 'acrossai-abilities-manager')}
        </div>
    </div>

    {isCreate && (
        <p className="desc">
            {__(
                'Save this ability first to configure user access.',
                'acrossai-abilities-manager'
            )}
        </p>
    )}

    {!isCreate && !abilitiesConfig.access_control_available && (
        <p className="notice notice-warning inline-notice">
            {__(
                'User Access is inactive — the wpb-access-control library is not loaded.',
                'acrossai-abilities-manager'
            )}
        </p>
    )}

    {!isCreate && savedAbility?.ability_slug && abilitiesConfig.access_control_available && (
        <AccessControl
            namespace="acrossai-abilities"
            resourceKey={savedAbility.ability_slug}
            restApiRoot={abilitiesConfig.rest_url || '/wp-json'}
            nonce={abilitiesConfig.nonce || ''}
            title={__('User Access', 'acrossai-abilities-manager')}
            description={__(
                'Who can use this ability.',
                'acrossai-abilities-manager'
            )}
        />
    )}
</div>
```

**Tab depth note**: The actual insertion must match the indentation depth of the adjacent `.sect`
divs. Verify by reading the surrounding lines before str_replace.

#### 5-D: Renumber existing sections

| Line range (approx) | Current `sect-num` | New value | Section |
|---------------------|--------------------|-----------|---------|
| ~1504 (now +offset) | `5` | `6` | Callback (`VARIANT A: Section 5`) |
| ~1629 (now +offset) | `6` | `7` | Schema |

Change only these two `<span className="sect-num">` values. Do not change 1, 2, 3, or 4.
After insertion the line numbers shift by the length of the inserted block — read again after
5-C to locate the exact renumber positions.

#### 5-E: Run ESLint
```bash
npm run lint:js
```
Fix any errors. Common expected issues: none (AccessControl is used in conditional JSX;
`abilitiesConfig` is referenced in three conditional expressions).

---

### Phase 6 — Build and Full Quality Gates

Run in this order:
```bash
nvm use 20                          # Node 20 required (DEC-NODE-20-BUILD-REQUIRED)
npm run build
npm run validate-packages
npm run lint:js
composer run phpcs
composer run phpstan
```

**Build verification**:
- `build/js/abilities.css` must NOT exist (no CSS extracted from JS bundle — alias points at AccessControl.js not index.js)
- `build/css/abilities.css` must contain `.wpb-ac` rules (CHANGE-3 landed styles correctly)

---

### Phase 7 — Manual Verification

Follow the checklist in `docs/planning/018-user-access-section-ability-form.md` §Manual Verification Checklist. Key checks:

1. Add New form → Section 5 shows "Save this ability first" placeholder, no JS errors
2. Edit form (db ability) → Section 5 renders AccessControl with correct `resourceKey`
3. Override form (non-db ability) → same component, same `resourceKey` = underlying slug
4. `access_control_available = false` (devtools) → warning notice rendered, no component
5. Section numbers: 1, (2 for non-db), 3, 4, **5 User Access**, **6 Callback**, **7 Schema**
6. AccessControl save does NOT trigger `isDirty` or sticky save bar

---

## Architecture Decisions for This Feature

| Decision | Rationale |
|----------|-----------|
| No new PHP class or module | Access control integration is already complete (B-1 through B-4). Feature 018 only exposes the library's availability flag to the client. |
| FQCN in admin/Main.php | `admin/Main.php` uses no `use` imports for the Abilities module. FQCN avoids adding a new `use` statement and keeps the PHPCS scope clean. |
| `abilitiesConfig` at module level | Stable for page lifetime. Matches `client.js` pattern. Avoids repeated property access inside the component render. |
| Named import `{ AccessControl }` not default | Library exports both named and default. Named import is explicit and matches the component's documented API. |
| Section body empty in transient pre-seed state | `!isCreate && !savedAbility?.ability_slug` edge case. Body intentionally empty — brief transient, no spinner needed (clarified in spec). |
| Alias points to AccessControl.js not index.js | v1.0.2+ restructured SCSS imports to index.js only. Pointing alias at AccessControl.js avoids CSS-in-JS bundle contamination. Consumer explicitly imports SCSS via CHANGE-3. |
| Single save button via `hideSaveButton` + `onChange` (v1.1.0) | Library v1.1.0 adds `hideSaveButton` to suppress the internal save button and `onChange(key, options)` to stream current selection to the parent. `handleSave()` saves AC state via `PUT /wpb-ac/v1/rules/acrossai-abilities/{slug}` after the ability save. AC save failure is caught and logged only — does not block the ability success flow. |
| AC state not wired into `isDirty` | AC is always saved on every "Save Changes" click when `acState !== null`. No dirty tracking needed; the occasional no-op PUT is acceptable. |

---

## Complexity Tracking

| Area | Why Deviation Accepted | Simpler Alternative Rejected Because |
|------|------------------------|--------------------------------------|
| CONSTITUTION §III DataForm | Design uses existing `.sect` HTML pattern; DEC-DESIGN-OVERRIDES-DATAVIEWS pre-existing | Retrofitting DataForm would require rewriting all 7 form sections (Feature 010 scope, not Feature 018) |

---

## What Must NOT Change

- `includes/Main.php` — REST hook already wired (B-4)
- `src/js/abilities/index.js` — nonce middleware already registered (B-5)
- `admin/Main.php` `is_manager_page()` / `is_logs_page()` guard logic (PATTERN-ENQUEUE-PAGE-GUARD)
- Form save flow: `isDirty`, `isSaving` — `handleSave` now also saves AC state but the ability save path is unchanged
- Section numbers 1–4
- No new admin_notices hook — `maybe_show_library_notice()` already hooked
- No new CSS handle enqueued in PHP
