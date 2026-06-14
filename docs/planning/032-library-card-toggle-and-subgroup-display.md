# Planning: Library Card Toggle + Optional Sub-Group Display (Feature 032)

Two display-only changes to the **Ability Library** admin page (`includes/Modules/Library/*`,
`src/js/ability-library/**`).

1. **CHANGE-A — Toggle/Radio visibility rules.** Lock down the existing All/Specific control
   on each `LibraryCard` so the slug-checkbox panel is rendered **only** when `mode === 'specific'`,
   and is fully hidden (no DOM, no whitespace) when `mode === 'all'`.
2. **CHANGE-B — Optional sub-group display.** Extend the `Ability_Definition` abstract base
   with an OPTIONAL sub-group field that subclasses can declare in `ability()`. The Library page
   uses it to render an extra heading row inside the `Specific` checkbox panel
   (e.g. "Core", "Plugins", "Themes", "Debug Log", "Config" inside the
   `Acrossai Core Abilities File Manager` card). **No database/storage change.** `sub_keys` is still
   keyed by ability slug — sub-groups are display-only.

This feature is UI-only. It does NOT alter saved option values, REST response shapes,
the on-disk `acrossai_library_config` option, or any ability execution path.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "032-library-card-toggle-and-subgroup-display"

# 2. Specify
/speckit.specify "Two display-only changes to the Ability Library page.

(A) On each LibraryCard, the slug-checkbox panel must be rendered only when mode === 'specific'.
    When mode === 'all', the slug-checkbox panel must NOT be in the DOM. The current code already
    implements this; this feature codifies it as a behavior contract and adds regression coverage.

(B) Add an OPTIONAL sub-group display layer to Ability_Definition. Subclasses MAY return a
    'sub_group' key inside the ability() spec's 'args' array. When present, the Library page
    renders an h-level heading above the slug checkbox(es) belonging to that sub-group inside the
    Specific panel. Sub-group is display-only — it does NOT change the saved config shape, REST
    response keys ('logs', 'total', 'pages'), the sub_keys map (keyed by slug), or any execution
    path. Library_Registry must accept a sub_group field, sanitize it like a key, and pass it
    through to the JS app via window.acrossaiAbilityLibraryData. The JS app must group slugs by
    sub_group while preserving the existing checkbox onChange wiring (still sub_keys[slug] = bool).
    Slugs with no sub_group fall under an implicit (ungrouped) bucket rendered first, without a
    heading. Order is preserved from the definitions array.

Files changed:
(1) includes/Modules/Library/Ability_Definition.php — pass through optional 'sub_group' from
    args into the pushed definition entry.
(2) includes/Modules/Library/AcrossAI_Ability_Library_Registry.php — add 'sub_group' to the
    optional fields whitelist, sanitize via sanitize_key_field(), include in the validated
    definition array.
(3) src/js/ability-library/components/LibraryPage.js — pass sub_group through the
    groupDefinitions() map onto each slug entry.
(4) src/js/ability-library/components/LibraryCard.js — group slugs by subGroup inside the
    Specific panel; render a small heading above each non-empty group; render ungrouped slugs
    first without a heading. Keep the existing 'mode === \"specific\"' guard intact so the
    panel is fully hidden under mode === 'all'.
(5) src/scss/ability-library/admin.scss (or wherever the library card styles live) — add a
    .acrossai-library-card__subgroup-heading rule.
(6) tests/* (PHPUnit) — assert Registry accepts and exposes sub_group; assert Ability_Definition
    pushes sub_group through; assert sub_keys is still slug-keyed and sub_group does NOT appear
    in saved config.
(7) AGENTS.md — short note that sub_group on Ability_Definition is display-only and never
    changes saved config or sub_keys shape.

Out of scope:
- No DB migration.
- No REST schema change.
- No change to AcrossAI_Ability_Library_Config (the saved-config shape is untouched).
- No change to the All radio: it still hides the checkbox panel."
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | `LibraryCard.js` already gates the slug checkbox panel on `enabled && mode === 'specific' && slugs.length > 0` (line 72). The "All hides checkboxes" behavior is shipped — CHANGE-A is a contract lockdown, not a new implementation. | Read `src/js/ability-library/components/LibraryCard.js:72` |
| B-2 | The radio control uses `RadioControl` from `@wordpress/components` with `selected={mode}` and two options (`all`, `specific`). Do NOT replace the radio with a second `ToggleControl` — that would be a UX regression and the existing saved configs use the `mode` string field with values `'all' \| 'specific'`. | Read `src/js/ability-library/components/LibraryCard.js:46-68` |
| B-3 | Saved config shape is `{ enabled: bool, mode: 'all'\|'specific', sub_keys: { [slug]: bool } }`. `sub_keys` is the on-disk wire key — must NOT be renamed. | Read `includes/Modules/Library/AcrossAI_Ability_Library_Config.php:79-106` |
| B-4 | `Ability_Definition::push_definition()` currently emits a flat row: `category`, `category_label`, `slug`, `slug_label`, `name`, `args`. There is no sub-group field yet — CHANGE-B adds one. | Read `includes/Modules/Library/Ability_Definition.php:58-75` |
| B-5 | `AcrossAI_Ability_Library_Registry::REQUIRED_FIELDS` lists exactly 6 fields. There is no concept of optional fields today — the validator hard-rejects definitions missing any required field. CHANGE-B introduces an "optional" tier without breaking that contract. | Read `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:44-52` |
| B-6 | The Registry strips unknown keys from `args` via `array_intersect_key()` against `ALLOWED_ARGS_FIELDS`. If `sub_group` is added to `args` (CHANGE-B option), it MUST be added to `ALLOWED_ARGS_FIELDS` or the registry will silently drop it. | Read `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php:58-67, 164-168` |
| B-7 | The JS app receives definitions via `window.acrossaiAbilityLibraryData.definitions` (a flat array). `LibraryPage.js::groupDefinitions()` re-groups them by `category` into card items. Adding sub-group grouping happens at the slug level inside that function, NOT at the category level. | Read `src/js/ability-library/components/LibraryPage.js:13-37` |
| B-8 | Slug entries in the card receive `{ slug, slugLabel, name }`. Adding `subGroup` here keeps the existing checkbox `onChange` wiring intact — the checkbox still writes `sub_keys[slug] = bool`. | Read `src/js/ability-library/components/LibraryCard.js:74-89` |
| B-9 | `AcrossAI_Ability_Library_Processor::is_slug_enabled()` reads `$entry['sub_keys'][$slug]`. Sub-group does NOT participate in this lookup; it is display-only. | Read `includes/Modules/Library/AcrossAI_Ability_Library_Processor.php:118-121` |
| B-10 | `acrossai-core-abilities/file-manager` is the concrete example for the screenshot — its current 15 abilities are all in one flat list. After CHANGE-B, the addon plugin's subclasses can declare `sub_group => 'core'`, `'plugins'`, `'themes'`, `'debug-log'`, `'config'` and the Library page will render those headings. | Read `acrossai-core-abilities/includes/Abilities/FileManager/*.php` |

---

## Where the `sub_group` field lives — args vs top-level

**Decision: store it inside `args`.** Two reasons:

1. `Ability_Definition::ability()` returns `{ name, args }`. Putting `sub_group` inside `args`
   keeps subclasses to a single nested shape and lets `push_definition()` hoist it the same way
   it hoists `label` and `category`.
2. The Library Registry already has an args allowlist (`ALLOWED_ARGS_FIELDS`) — adding `sub_group`
   there gives us a single concrete place to declare the new optional field. The top-level row
   emitted by `push_definition()` will surface `sub_group` as a sibling of `slug`, not inside `args`.

Rejected alternative: a separate top-level `sub_group` arg on `Ability_Definition::push_definition()`.
This would require subclasses to override `push_definition()` to add it, which defeats the
"single abstract method" contract documented in the class docblock (lines 18–21).

---

## CHANGE-A — Lock down All/Specific checkbox visibility

**Files**: `src/js/ability-library/components/LibraryCard.js`, tests.

### Required behavior contract

| `mode` value | DOM state of slug checkbox panel | Notes |
|--------------|----------------------------------|-------|
| `'all'` | NOT rendered — element does not exist in the DOM | No `display:none`, no `hidden` attribute, no empty wrapper |
| `'specific'` (with `slugs.length > 0`) | Rendered with one `CheckboxControl` per slug | Plus sub-group headings from CHANGE-B |
| `'specific'` (with `slugs.length === 0`) | NOT rendered | Empty category — show no panel |
| `enabled === false` | NOT rendered regardless of `mode` | Whole card body collapsed |

The existing guard at `LibraryCard.js:72` already encodes this:

```jsx
{enabled && mode === 'specific' && slugs.length > 0 && (
    <div className="acrossai-library-card__slugs">…</div>
)}
```

**Do NOT change the JSX guard itself.** The work in CHANGE-A is:

1. Add a JSX comment immediately above the guard documenting the contract — one line, no
   multi-paragraph block:
   ```jsx
   {/* Slug checkbox panel — rendered only under mode === 'specific'. Feature 032 contract. */}
   ```
2. Add a unit test (or React Testing Library test if the harness supports it) that asserts:
   - With `mode='all'`, `document.querySelector('.acrossai-library-card__slugs')` is `null`.
   - Switching the radio from `'all'` to `'specific'` mounts the panel; switching back unmounts it.
3. Confirm that toggling `mode` from `'specific'` → `'all'` ALSO clears `sub_keys` (current code does
   this at `LibraryCard.js:65`: `sub_keys: value === 'all' ? {} : slugsConfig`). Keep that wiring.

### What must NOT change in CHANGE-A

- Do NOT replace the `RadioControl` with a second `ToggleControl`. The saved-config `mode` field
  remains a 2-value string enum.
- Do NOT add a `display:none` CSS rule for the slug panel — the panel must be removed from the
  DOM, not hidden, so React unmounts the checkboxes and the form's controlled-component state
  stays consistent.
- Do NOT change the order of the toggle, label, and radio in the card header.
- Do NOT remove the `sub_keys: value === 'all' ? {} : slugsConfig` reset on radio change — this
  is what guarantees the saved config matches the visible UI state.

---

## CHANGE-B — Optional sub-group display

### B-1: `includes/Modules/Library/Ability_Definition.php`

**Add `sub_group` as an OPTIONAL pass-through.**

Current `push_definition()` body (lines 58–75) already pulls `category`, `label`, `name`,
and pushes a row. Extend it to also extract `sub_group` from `args` and add it to the
pushed row only when non-empty:

```php
public function push_definition( array $definitions ): array {
    $spec = $this->ability();
    $name = $spec['name'] ?? '';
    $args = $spec['args'] ?? array();

    $category  = $args['category'] ?? '';
    $sub_group = isset( $args['sub_group'] ) ? (string) $args['sub_group'] : '';

    $row = array(
        'category'       => $category,
        'category_label' => ucwords( str_replace( '-', ' ', $category ) ),
        'slug'           => $name,
        'slug_label'     => $args['label'] ?? $name,
        'name'           => $name,
        'args'           => $args,
    );

    if ( '' !== $sub_group ) {
        $row['sub_group']       = $sub_group;
        $row['sub_group_label'] = ucwords( str_replace( '-', ' ', $sub_group ) );
    }

    $definitions[] = $row;

    return $definitions;
}
```

**Rules**:
- The class docblock (lines 17–21) must be updated to mention `sub_group` as an optional
  args key, with the line: "Optional: `args['sub_group']` adds a display-only sub-heading inside
  the Library Specific panel. Does NOT affect saved config or execution."
- The auto-derived `sub_group_label` uses the same `ucwords(str_replace('-', ' ', …))` transform
  as `category_label` for consistency.
- Subclasses MAY also supply an explicit `args['sub_group_label']` to override the auto-derived
  label. If present and non-empty, prefer it over the auto-derived version. (Add this branch
  only after the basic flow above is working — keep it as a small follow-up if it adds noise.)

### B-2: `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`

**Three changes**:

1. Add `sub_group` to `ALLOWED_ARGS_FIELDS` (lines 58–67) so the Registry does not strip it out of `args`:
   ```php
   private const ALLOWED_ARGS_FIELDS = array(
       'label',
       'description',
       'category',
       'sub_group',          // Feature 032 — optional display-only sub-heading
       'execute_callback',
       'permission_callback',
       'input_schema',
       'output_schema',
       'meta',
   );
   ```

2. Introduce a parallel `OPTIONAL_FIELDS` constant for top-level optional row fields:
   ```php
   private const OPTIONAL_FIELDS = array(
       'sub_group',
       'sub_group_label',
   );
   ```

3. In `validate_and_normalize()`, after the required-field loop, sanitize each optional field
   present on the row and copy it onto the validated entry:
   ```php
   $entry = array(
       'category'       => $category,
       'category_label' => wp_kses_post( (string) $item['category_label'] ),
       'slug'           => $slug,
       'slug_label'     => wp_kses_post( (string) $item['slug_label'] ),
       'name'           => $name,
       'args'           => $item['args'],
   );

   if ( isset( $item['sub_group'] ) && '' !== $item['sub_group'] ) {
       $clean_sub = self::sanitize_sub_group( (string) $item['sub_group'] );
       if ( '' !== $clean_sub ) {
           $entry['sub_group']       = $clean_sub;
           $entry['sub_group_label'] = isset( $item['sub_group_label'] ) && '' !== $item['sub_group_label']
               ? wp_kses_post( (string) $item['sub_group_label'] )
               : ucwords( str_replace( '-', ' ', $clean_sub ) );
       }
   }

   $valid[] = $entry;
   ```

   Add a private helper:
   ```php
   private static function sanitize_sub_group( string $raw ): string {
       return AcrossAI_Ability_Library_Config::sanitize_key_field( $raw );
   }
   ```

   This reuses the existing 100-char + `sanitize_key()` rule already proven safe for category/slug.

4. Add `sub_group` (and `sub_group_label`) to the inline filter docblock at lines 103–116 so
   add-on authors discover them: "Optional: `sub_group` (display-only sub-heading inside the
   Specific panel) and `sub_group_label` (override the auto-derived label)."

### B-3: `src/js/ability-library/components/LibraryPage.js`

Pass `sub_group` / `sub_group_label` through `groupDefinitions()`. The current function
(lines 13–37) destructures the PHP row and builds card items keyed by `category`. Extend the
destructure and the slug push:

```js
function groupDefinitions(definitions) {
    const map = new Map();
    for (const def of definitions) {
        const {
            category,
            category_label: categoryLabel,
            slug,
            slug_label: slugLabel,
            name,
            sub_group: subGroup,
            sub_group_label: subGroupLabel,
        } = def;
        if (!map.has(category)) {
            map.set(category, {
                id: category,
                category,
                categoryLabel,
                slugs: [],
            });
        }
        const group = map.get(category);
        if (!group.slugs.some((s) => s.slug === slug)) {
            group.slugs.push({
                slug,
                slugLabel,
                name,
                subGroup: subGroup || '',
                subGroupLabel: subGroupLabel || '',
            });
        }
    }
    return Array.from(map.values());
}
```

**Rules**:
- Slugs WITHOUT a `subGroup` keep `subGroup === ''`. They render first, without a heading.
- Slug order is preserved from the input `definitions` array — do NOT alphabetize, do NOT
  re-sort by `subGroup`. The Library page reflects the registration order from the addon
  plugins.

### B-4: `src/js/ability-library/components/LibraryCard.js`

Inside the existing `enabled && mode === 'specific' && slugs.length > 0` block, group the
slugs by `subGroup` while preserving order, and render a heading row above each non-empty
group whose key is non-empty. Replace the current `{slugs.map(…)}` block with:

```jsx
{enabled && mode === 'specific' && slugs.length > 0 && (
    <div className="acrossai-library-card__slugs">
        {groupBySubGroupPreservingOrder(slugs).map(
            ({ subGroup, subGroupLabel, items }) => (
                <Fragment key={subGroup || '__ungrouped'}>
                    {subGroup !== '' && (
                        <h4 className="acrossai-library-card__subgroup-heading">
                            {subGroupLabel}
                        </h4>
                    )}
                    {items.map(({ slug, slugLabel, name }) => (
                        <CheckboxControl
                            __nextHasNoMarginBottom
                            key={slug}
                            label={slugLabel || name}
                            checked={slugsConfig[slug] ?? false}
                            onChange={(value) =>
                                update({
                                    sub_keys: {
                                        ...slugsConfig,
                                        [slug]: value,
                                    },
                                })
                            }
                        />
                    ))}
                </Fragment>
            )
        )}
    </div>
)}
```

The helper `groupBySubGroupPreservingOrder()` lives in the same file (don't extract a util
unless reuse appears elsewhere):

```js
function groupBySubGroupPreservingOrder(slugs) {
    const order = [];
    const groups = new Map();
    for (const slug of slugs) {
        const key = slug.subGroup || '';
        if (!groups.has(key)) {
            groups.set(key, {
                subGroup: key,
                subGroupLabel: slug.subGroupLabel || '',
                items: [],
            });
            order.push(key);
        }
        groups.get(key).items.push(slug);
    }
    return order.map((key) => groups.get(key));
}
```

**Rules**:
- Use `Fragment` from `@wordpress/element` (already in scope via `createRoot` import path —
  add it explicitly to the import statement).
- The CheckboxControl `onChange` body is unchanged — `sub_keys[slug] = value`. The
  sub-group is never written to `sub_keys`.
- The heading element is `<h4>` because the page already uses `<h2>` for the page title and
  the toggle label renders as a `<strong>` inside the toggle control. `<h4>` keeps the
  document outline tidy.
- The empty-string sub_group bucket (ungrouped) renders FIRST, with no heading. This matches
  the screenshot-2 expectation where File/Create/Edit/Delete File (the "Core" group) appear
  before the sub-heading rows.

### B-5: CSS

Add a single rule wherever the Library card styles live (search for
`acrossai-library-card__slugs`):

```scss
.acrossai-library-card__subgroup-heading {
    margin: 12px 0 4px;
    font-size: 13px;
    font-weight: 600;
    color: var(--wp-admin-theme-color, #1e1e1e);
    text-transform: none;
}
```

If a CSS file does not exist for this component, do NOT create a new SCSS file just for this
rule — inline the style via the existing global admin stylesheet for the library page. Confirm
where existing `.acrossai-library-card__*` selectors live before adding a new file.

### B-6: Tests

Add PHPUnit cases in the existing Library test directory (run `find tests -name '*Library*'`
to locate). Cover:

| Test | Assertion |
|------|-----------|
| `test_push_definition_omits_sub_group_when_absent()` | Definition with no `args['sub_group']` produces a row WITHOUT `sub_group` / `sub_group_label` keys. |
| `test_push_definition_includes_sub_group_when_present()` | Definition with `args['sub_group'] = 'core'` produces a row with `sub_group === 'core'` and auto-derived `sub_group_label === 'Core'`. |
| `test_registry_strips_invalid_sub_group()` | A definition with `sub_group = '!!! bad-input ###'` is sanitized via `sanitize_key()` to `'bad-input'` (or whatever `sanitize_key()` produces) — assert the validated row reflects that. |
| `test_registry_allows_sub_group_in_args_allowlist()` | A definition passed through with `args['sub_group']` retains the key on the validated `args` array. |
| `test_save_config_ignores_sub_group()` | Calling `AcrossAI_Ability_Library_Config::save_config()` with a payload that contains a stray `sub_group` field results in a saved entry whose shape is still `{ enabled, mode, sub_keys }`. Sub-group MUST NOT appear in saved config. |

If React Testing Library is already wired (check `package.json`), add a JS test that
asserts the slug-checkbox panel does NOT render with `mode='all'` and that switching to
`'specific'` mounts it.

---

## What must NOT change

- `AcrossAI_Ability_Library_Config::OPTION_KEY` (`acrossai_library_config`).
- Saved config entry shape: `{ enabled, mode, sub_keys }`. **No sub_group inside `sub_keys`.**
- `AcrossAI_Ability_Library_Config::VALID_MODES` — still `array( 'all', 'specific' )`.
- `AcrossAI_Ability_Library_Processor::is_slug_enabled()` — sub-group plays no role here.
- The REST API responses backing the Library page — sub-group is delivered via the localized
  `window.acrossaiAbilityLibraryData`, not by a new endpoint.
- Existing `Ability_Definition` subclasses — they continue to work unchanged because
  `sub_group` is OPTIONAL.
- `AcrossAI_Ability_Library_Registry::REQUIRED_FIELDS` — still 6 entries. Sub-group is an
  optional field tier, not a required one.

---

## CONSTRAINTS

- Exactly 5–7 files change (depending on whether tests/CSS land in existing files):
  `includes/Modules/Library/Ability_Definition.php`,
  `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`,
  `src/js/ability-library/components/LibraryPage.js`,
  `src/js/ability-library/components/LibraryCard.js`,
  one CSS/SCSS file,
  one or more existing test files,
  `AGENTS.md` (one short line).
- PHPStan level 8 must pass after the PHP changes.
- PHPCS on the changed PHP files must report no new errors.
- Plugin Check on the production surface (post-Feature 021 exclusions) must remain clean.
- `npm run build` produces a working `build/js/ability-library.js`.
- The on-disk `acrossai_library_config` option, after saving via the UI with sub-groups
  visible, contains NO `sub_group` keys anywhere — only `enabled`, `mode`, `sub_keys`.
- All existing Library-related PHPUnit tests still pass without modification.

---

## Spec-kit Commands

```markdown
# 3. Plan + guard
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpstan
composer run phpcs
npm run build

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

### CHANGE-A — Toggle/Specific visibility

- [ ] Open the Ability Library admin page.
- [ ] Pick any card. With the toggle ON, click **All**. The slug-checkbox panel disappears.
      Inspect the DOM (`document.querySelector('.acrossai-library-card__slugs')`) — confirm it
      is `null`, not just `display:none`.
- [ ] Click **Specific**. The slug-checkbox panel mounts. The DOM node now exists.
- [ ] Tick one checkbox under **Specific**, then click **All**. Inspect saved config via
      `wp option get acrossai_library_config --format=json` (or via the network panel) — the
      affected category entry should either be absent (sparse storage default) or have an
      empty `sub_keys` map. Sub-keys are reset to `{}` whenever mode flips to `'all'`.
- [ ] Toggle the card OFF entirely. The Specific radio and the checkbox panel both disappear.

### CHANGE-B — Sub-group display

- [ ] In a development addon (or a temporary throwaway plugin) declare a subclass of
      `Ability_Definition` that returns `args['sub_group'] => 'core'` for File_Read /
      File_Create / File_Edit / File_Delete; `'plugins'` for Read Plugin Structure / Read
      Plugin Code / Manage Plugin Files; `'themes'` for the theme rows; `'debug-log'` for
      Read Debug Log / Clear Debug Log; `'config'` for Read wp-config.php / Edit wp-config.php.
- [ ] Confirm the Library page renders five `<h4>` headings — Core, Plugins, Themes,
      Debug Log, Config — in registration order, each followed by its checkboxes.
- [ ] Confirm slugs WITHOUT a `sub_group` (in other categories) still render without a heading
      and in their original order.
- [ ] Tick one or more sub-grouped checkboxes and reload. The selection persists.
- [ ] Run `wp option get acrossai_library_config --format=json` — assert the JSON contains
      `sub_keys` keyed by ability slug, with NO `sub_group` keys present anywhere in the
      structure.
- [ ] Switch the radio to **All** on a card that has sub-groups. The headings AND the
      checkboxes all disappear from the DOM (no orphan heading).
- [ ] Remove the `args['sub_group']` from the temporary plugin. The Library page falls back
      to the current flat slug list with no headings, no errors in console.
- [ ] Run `composer run phpstan` — zero errors.
- [ ] Run Plugin Check via the existing workflow against the production surface — zero new
      errors/warnings.

### Regression checks

- [ ] All existing categories without sub_group continue to render as a flat checkbox list.
- [ ] The page does not flicker between mounted/unmounted panel when toggling the radio
      rapidly (React reconciliation is stable because the guard is a single boolean expression).
- [ ] PHPUnit Library tests pass: `composer test` (or the project's equivalent).
