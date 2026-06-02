# Planning: Abilities List UX Improvements (Feature 025)

Six targeted enhancements to the Abilities Manager list view and Settings page:

1. **Pagination** — add working prev/next pagination to the abilities table so all 100+ abilities are reachable.
2. **Per-page setting** — add an admin-controlled "Abilities per page" option on the Settings page (default 20).
3. **Hide All / Published / Draft tabs** — hide the `<ul class="subsubsub">` quick-links via CSS (`display: none`); keep the markup and state intact.
4. **"Clear All Overrides" row action** — add a button in the Actions column on each non-db (inherited) row that calls `dispatch.clearOverrides(slug)`, matching the same action available inside the ability edit form.
5. **Description + Show in REST columns** — add two new columns to the abilities table reading from `item.description` and `item.show_in_rest`, which already exist in the REST response schema.
6. **Column visibility toggle** — add a "Screen Options"-style panel that lets the user show or hide individual columns; preferences are persisted in `localStorage`.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "025-abilities-list-ux-improvements"

# 2. Specify
/speckit.specify "Six improvements to the Abilities Manager admin list view and Settings page.

(1) PAGINATION — AbilitiesList.jsx currently hard-codes `const [page] = useState(1)` and never
    exposes prev/next controls. The REST endpoint already supports page and per_page parameters.
    Replace the frozen useState with a stateful page variable and add tablenav pagination controls
    (first, prev, page indicator N of M, next, last) above and below the table.
    Total pages = Math.ceil(total / perPage).
    On page change: re-run dispatch.fetchAbilities with the new page number.
    Pagination controls must be disabled (not hidden) when at the first or last page.

(2) PER-PAGE SETTING — Add a new WordPress Settings API option
    `acrossai_abilities_per_page` (integer, min 1, max 200, default 20) under a new
    'Display Settings' section in admin/Partials/SettingsMenu.php.
    In AbilitiesList.jsx, replace the hard-coded `PER_PAGE = 20` constant with a value read from
    `window.acrossaiAbilities.perPage` (inject via wp_localize_script / wp_add_inline_script
    in the existing admin asset enqueue). When the setting changes, the list re-fetches.

(3) HIDE ALL/PUBLISHED/DRAFT TABS — Hide the `<ul className='subsubsub'>` block from view using
    CSS `display: none` on the `.subsubsub` selector. Do NOT remove the JSX, the statusFilter
    state, the publishedCount/draftCount derived values, or any related logic — they must be
    preserved so the feature can be re-enabled in future without a code change.
    The 'All Statuses' dropdown in the tablenav already covers this filtering need visually.
    The CSS rule goes in the existing admin stylesheet (src/scss/abilities/admin.scss or equivalent).

(4) CLEAR ALL OVERRIDES ROW ACTION — In the row action cell for non-db abilities (the `else` branch
    of `isCustom ? ... : ...` in AbilitiesList.jsx), add a 'Clear All Overrides' button after the
    existing 'Edit' button. The button must:
    - show only when the ability has at least one active override (check item.has_override or fall
      back to a REST field if not already present — see note below)
    - call dispatch.clearOverrides(slug) on click (same store action used in AbilityForm.jsx line 592)
    - show a window.confirm guard: 'Clear all overrides for this ability? This cannot be undone.'
    - re-fetch the list after success to reflect the cleared state
    Note on has_override field: check if the REST response already returns a has_override boolean.
    If not, add it to the REST schema so AbilitiesList can use it to conditionally show the button.
    The button should not appear for abilities that have no overrides.

(5) DESCRIPTION AND SHOW IN REST COLUMNS — Add two new columns to the wptable:
    - Description: render item.description truncated to ~80 chars with a title attribute for full text.
      Show '—' when empty.
    - Show in REST: render a Yes/No badge similar to the existing McpCell pattern.
      item.show_in_rest is already in the DB schema (AcrossAI_Abilities_Schema.php line 156).
    Add matching <col> entries and <th> headers. Insert columns between Type and MCP.
    Update colSpan in the loading and empty-state rows from 9 to 11.

(6) COLUMN VISIBILITY TOGGLE — Add a 'Columns' toggle button in the tablenav area that opens a
    small panel listing every hideable column with a checkbox next to each name. The user can
    check or uncheck columns to show or hide them instantly. Preferences must persist across page
    reloads via localStorage key 'acrossai_abilities_columns'.
    Hideable columns (all visible by default): Label, Category, Source, Status, Type, Description,
    Show in REST, MCP.
    Always-visible columns (not listed in the panel): checkbox, Slug, Actions.
    Implementation:
    - Maintain a `visibleColumns` state object: { label: true, category: true, source: true,
      status: true, type: true, description: true, show_in_rest: true, mcp: true }.
    - On mount, read the saved value from localStorage and merge with defaults (so new columns
      added in future features default to visible without breaking existing saved preferences).
    - On change, write the updated object back to localStorage immediately.
    - In the table, conditionally render each <th>, <col>, and <td> based on the corresponding
      visibleColumns entry. The column's <col> element should be removed (not hidden) when the
      column is off, so column widths stay correct.
    - When a column is hidden its colSpan contributions are subtracted from the loading and
      empty-state rows dynamically (derive colSpan from Object.values(visibleColumns).filter(Boolean)
      count + 2 for the always-visible checkbox and Slug, + 1 for Actions).
    - The toggle panel button label: 'Columns ▾' when closed, 'Columns ▴' when open.
      Close the panel when the user clicks outside it (blur / document click handler).

Memory and governance targets:
- AGENTS.md: note that AbilitiesList pagination state is driven by the REST per_page / page params
  and that the per-page value is injected via wp_localize_script from the DB option.
- docs/memory/DECISIONS.md: record DEC-ABILITIES-LIST-UX-025 with the above five decisions."

# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpstan

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Scope Rules

### In scope

- `src/js/abilities/components/AbilitiesList.jsx` — pagination controls, hide tabs, Clear All Overrides button, Description + Show in REST columns, column visibility toggle.
- `admin/Partials/SettingsMenu.php` — new Display Settings section with per-page option.
- `admin/Main.php` (or wherever `wp_localize_script` is called) — inject `perPage` into `window.acrossaiAbilities`.
- REST controller or schema if `has_override` field is absent from the response.

### Out of scope

- No changes to the edit form (AbilityForm.jsx) beyond what is needed for the store action.
- No changes to DB schema tables.
- No changes to the REST endpoint paths or response shapes other than adding `has_override` if missing.
- No changes to tests that do not already cover these components.

---

## Background — Current State

| Component | Current behaviour | Target behaviour |
|-----------|------------------|-----------------|
| `AbilitiesList.jsx` line 139 | `const [page] = useState(1)` — page frozen at 1 | Stateful `page`, driven by pagination controls |
| `AbilitiesList.jsx` line 140 | `const PER_PAGE = 20` — hard-coded | Read from `window.acrossaiAbilities.perPage` |
| `AbilitiesList.jsx` lines 292–334 | `<ul className="subsubsub">` with All/Published/Draft tabs | Hidden via CSS `display: none`; JSX and state preserved |
| `AbilitiesList.jsx` lines 634–652 | `else` branch for inherited rows only has `Edit` button | Add `Clear All Overrides` button after `Edit` |
| `AbilitiesList.jsx` table | 9 columns: checkbox, Slug, Label, Category, Source, Status, Type, MCP, Actions | 11 columns: add Description and Show in REST between Type and MCP |
| `AbilitiesList.jsx` tablenav | No column control | "Columns ▾" button opens a panel with checkboxes for 8 hideable columns; state persisted in localStorage |
| `SettingsMenu.php` | Log Settings + Uninstall Settings sections | Add Display Settings section with per-page field |

---

## CHANGE-1 — Stateful Pagination in AbilitiesList.jsx

**File**: `src/js/abilities/components/AbilitiesList.jsx`

Replace:
```jsx
const [page] = useState(1);
const PER_PAGE = 20;
```

With:
```jsx
const [page, setPage] = useState(1);
const perPage = window.acrossaiAbilities?.perPage || 20;
```

Add derived value:
```jsx
const totalPages = Math.ceil(total / perPage) || 1;
```

Update the `useEffect` fetch call:
```jsx
dispatch.fetchAbilities({
    page,
    per_page: perPage,
    ...
});
```

Reset page to 1 when any filter or search changes:
```jsx
useEffect(() => {
    setPage(1);
}, [search, sourceFilter, statusFilter, sortDir]);
```

Add pagination nav markup (render above and below the table, inside the tablenav area):
```jsx
<div className="tablenav-pages">
    <span className="displaying-num">{total} items</span>
    <span className="pagination-links">
        <button disabled={1 === page} onClick={() => setPage(1)}>«</button>
        <button disabled={1 === page} onClick={() => setPage(p => p - 1)}>‹</button>
        <span className="paging-input">
            {page} of {totalPages}
        </span>
        <button disabled={page >= totalPages} onClick={() => setPage(p => p + 1)}>›</button>
        <button disabled={page >= totalPages} onClick={() => setPage(totalPages)}>»</button>
    </span>
</div>
```

---

## CHANGE-2 — Per-page Setting in SettingsMenu.php

**File**: `admin/Partials/SettingsMenu.php`

Add to `register_settings()`:
```php
register_setting(
    'acrossai_abilities_settings',
    'acrossai_abilities_per_page',
    array(
        'sanitize_callback' => array( $this, 'sanitize_per_page' ),
        'default'           => 20,
    )
);

add_settings_section(
    'acrossai_display_settings_section',
    __( 'Display Settings', 'acrossai-abilities-manager' ),
    '__return_false',
    'acrossai-abilities-settings'
);

add_settings_field(
    'acrossai_abilities_per_page',
    __( 'Abilities per page', 'acrossai-abilities-manager' ),
    array( $this, 'render_per_page_field' ),
    'acrossai-abilities-settings',
    'acrossai_display_settings_section'
);
```

Add sanitizer and renderer:
```php
public function sanitize_per_page( $value ): int {
    $int = absint( $value );
    return ( $int < 1 || $int > 200 ) ? 20 : $int;
}

public function render_per_page_field(): void {
    $value = (int) get_option( 'acrossai_abilities_per_page', 20 );
    printf(
        '<input type="number" id="acrossai_abilities_per_page" name="acrossai_abilities_per_page" value="%s" min="1" max="200" step="1" /><p class="description">%s</p>',
        esc_attr( (string) $value ),
        esc_html__( 'Number of abilities shown per page in the abilities table. Default: 20. Min: 1. Max: 200.', 'acrossai-abilities-manager' )
    );
}
```

---

## CHANGE-3 — Inject perPage into JS via wp_localize_script

**File**: `admin/Main.php` (or the file that enqueues the admin abilities script)

Locate the `wp_localize_script` (or `wp_add_inline_script`) call for `acrossaiAbilities` data. Add:
```php
'perPage' => (int) get_option( 'acrossai_abilities_per_page', 20 ),
```

If the inline data object does not yet exist, add a `wp_add_inline_script` call after the abilities script enqueue:
```php
wp_add_inline_script(
    'acrossai-abilities-admin',
    'window.acrossaiAbilities = ' . wp_json_encode( array(
        'perPage' => (int) get_option( 'acrossai_abilities_per_page', 20 ),
    ) ) . ';',
    'before'
);
```

---

## CHANGE-4 — Hide subsubsub Tabs via CSS

**File**: `src/scss/abilities/admin.scss` (or the compiled admin stylesheet)

Add a single rule to hide the quick-link tabs without touching any JSX:

```scss
.subsubsub {
    display: none;
}
```

Do NOT touch `AbilitiesList.jsx` for this change. The `<ul className="subsubsub">` JSX block, the `statusFilter` state, the `publishedCount` and `draftCount` derived values, and the `setStatusFilter` calls inside the list items must all remain in place so the tabs can be re-shown in future by simply removing this CSS rule.

---

## CHANGE-5 — Clear All Overrides Row Action

**File**: `src/js/abilities/components/AbilitiesList.jsx`

In the `else` branch (non-db/inherited abilities), after the `Edit` button, add:
```jsx
{(item.has_override) && (
    <>
        <span className="ra-sep">|</span>
        <button
            type="button"
            className="ra"
            onClick={() => {
                if (
                    // eslint-disable-next-line no-alert
                    window.confirm(
                        __('Clear all overrides for this ability? This cannot be undone.', 'acrossai-abilities-manager')
                    )
                ) {
                    dispatch.clearOverrides(item.ability_slug);
                }
            }}
        >
            {__('Clear All Overrides', 'acrossai-abilities-manager')}
        </button>
    </>
)}
```

**has_override field**: verify whether `has_override` is already returned in the REST response by checking `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php` and `AcrossAI_Abilities_Schema.php`. If absent, add it:
- In the DB query, a `has_override` boolean should be `true` when a row exists in the overrides table for the given slug.
- Expose it in the REST response so the frontend can conditionally show the button.
- If computing it requires a JOIN or a separate query, prefer a lightweight per-row check or a batch preload approach that does not issue N+1 queries.

---

## CHANGE-6 — Description and Show in REST Columns

**File**: `src/js/abilities/components/AbilitiesList.jsx`

Add cell renderers:
```jsx
function DescriptionCell({ item }) {
    const desc = item.description || '';
    if (!desc) return <span>—</span>;
    const truncated = desc.length > 80 ? desc.slice(0, 80) + '…' : desc;
    return <span title={desc}>{truncated}</span>;
}

function ShowInRestCell({ item }) {
    return item.show_in_rest ? (
        <span className="mcp-y">{__('✓ Yes', 'acrossai-abilities-manager')}</span>
    ) : (
        <span className="mcp-n">{__('○ No', 'acrossai-abilities-manager')}</span>
    );
}
```

Add `<col>` entries (insert between col-typ and col-mcp):
```jsx
<col className="col-desc" />
<col className="col-rest" />
```

Add `<th>` headers (insert between Type and MCP):
```jsx
<th>{__('Description', 'acrossai-abilities-manager')}</th>
<th>{__('Show in REST', 'acrossai-abilities-manager')}</th>
```

Add `<td>` cells in each row (insert between TypeCell and McpCell):
```jsx
<td><DescriptionCell item={item} /></td>
<td><ShowInRestCell item={item} /></td>
```

Update colSpan in loading and empty-state rows from `9` to `11`.

---

## CHANGE-7 — Column Visibility Toggle

**File**: `src/js/abilities/components/AbilitiesList.jsx`

### State and persistence

Add a `COLUMN_DEFAULTS` constant and a `visibleColumns` state, initialized by merging localStorage with defaults so newly added columns are visible by default:

```jsx
const COLUMN_DEFAULTS = {
    label:        true,
    category:     true,
    source:       true,
    status:       true,
    type:         true,
    description:  true,
    show_in_rest: true,
    mcp:          true,
};

const LS_KEY = 'acrossai_abilities_columns';

function loadColumnPrefs() {
    try {
        const saved = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
        return { ...COLUMN_DEFAULTS, ...saved };
    } catch {
        return { ...COLUMN_DEFAULTS };
    }
}

const [visibleColumns, setVisibleColumns] = useState(loadColumnPrefs);
const [columnsOpen, setColumnsOpen] = useState(false);

function toggleColumn(key) {
    setVisibleColumns((prev) => {
        const next = { ...prev, [key]: !prev[key] };
        try { localStorage.setItem(LS_KEY, JSON.stringify(next)); } catch {}
        return next;
    });
}
```

### "Columns" button in tablenav

Add a button in the tablenav (alongside the existing dropdowns) and a dropdown panel beneath it:

```jsx
<div className="columns-toggle" style={{ position: 'relative' }}>
    <button
        type="button"
        className="button"
        onClick={() => setColumnsOpen((o) => !o)}
        aria-expanded={columnsOpen}
    >
        {__('Columns', 'acrossai-abilities-manager')} {columnsOpen ? '▴' : '▾'}
    </button>
    {columnsOpen && (
        <div className="columns-panel">
            {Object.entries(COLUMN_DEFAULTS).map(([key]) => (
                <label key={key} className="columns-panel-item">
                    <input
                        type="checkbox"
                        checked={!!visibleColumns[key]}
                        onChange={() => toggleColumn(key)}
                    />
                    {' '}
                    {COLUMN_LABELS[key]}
                </label>
            ))}
        </div>
    )}
</div>
```

Add a `COLUMN_LABELS` map for human-readable names:

```jsx
const COLUMN_LABELS = {
    label:        __('Label',         'acrossai-abilities-manager'),
    category:     __('Category',      'acrossai-abilities-manager'),
    source:       __('Source',        'acrossai-abilities-manager'),
    status:       __('Status',        'acrossai-abilities-manager'),
    type:         __('Type',          'acrossai-abilities-manager'),
    description:  __('Description',   'acrossai-abilities-manager'),
    show_in_rest: __('Show in REST',  'acrossai-abilities-manager'),
    mcp:          __('MCP',           'acrossai-abilities-manager'),
};
```

Add a `useEffect` to close the panel on outside click:

```jsx
useEffect(() => {
    if (!columnsOpen) return;
    function handleOutside(e) {
        if (!e.target.closest('.columns-toggle')) {
            setColumnsOpen(false);
        }
    }
    document.addEventListener('mousedown', handleOutside);
    return () => document.removeEventListener('mousedown', handleOutside);
}, [columnsOpen]);
```

### Conditional rendering

Wrap each hideable `<col>`, `<th>`, and `<td>` with a visibility guard:

```jsx
{visibleColumns.label && <col className="col-lbl" />}
// ... repeat for each hideable column
```

```jsx
{visibleColumns.label && <th>{__('Label', 'acrossai-abilities-manager')}</th>}
// ... repeat for each hideable column
```

```jsx
{visibleColumns.label && <td><LabelCell item={item} /></td>}
// ... repeat for each hideable column
```

### Dynamic colSpan

Replace hard-coded `colSpan="11"` in loading and empty-state rows with a derived value:

```jsx
const visibleCount = Object.values(visibleColumns).filter(Boolean).length;
const totalColSpan = visibleCount + 3; // +1 checkbox, +1 Slug (always), +1 Actions (always)
```

Use `colSpan={totalColSpan}` in the loading and empty-state `<td>` cells.

---

## What Must NOT Change

- Do not change REST endpoint paths or response shapes beyond adding `has_override` if absent.
- Do not remove the `All Statuses` dropdown from the tablenav.
- Do not change the database schema for abilities.
- Do not alter the AbilityForm edit view.
- Do not modify existing test files for unrelated behaviour.

---

## Expected Files Changed

```text
src/js/abilities/components/AbilitiesList.jsx
src/scss/abilities/admin.scss             (add .subsubsub { display: none })
admin/Partials/SettingsMenu.php
admin/Main.php                            (or wherever wp_localize_script is called)
includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php   (if has_override is absent)
includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php          (if has_override needs a DB query)
AGENTS.md
docs/memory/DECISIONS.md
```

No new PHP files are needed for column visibility — it is a pure front-end feature using localStorage.

---

## Validation Checklist

### Pagination

- [ ] With 100 abilities loaded, the list shows only the first N items (N = per_page setting).
- [ ] Prev/Next and First/Last buttons navigate through pages and re-fetch from the REST endpoint.
- [ ] Prev/First buttons are disabled on page 1; Next/Last are disabled on the last page.
- [ ] Page counter shows correct "N of M" text.
- [ ] Changing a filter or search resets to page 1.

### Per-page setting

- [ ] Settings page at `/wp-admin/admin.php?page=acrossai-abilities-settings` shows "Display Settings" section with "Abilities per page" field defaulting to 20.
- [ ] Saving a different value (e.g. 10 or 50) changes the number of items shown in the list.
- [ ] Values < 1 or > 200 are sanitized back to 20.
- [ ] `window.acrossaiAbilities.perPage` reflects the saved option value on page load.

### Tabs removed

- [ ] `<ul class="subsubsub">` is present in the DOM but not visible (`display: none`).
- [ ] All / Published / Draft quick-link tabs are invisible in the admin UI.
- [ ] The underlying JSX, `statusFilter` state, and `publishedCount`/`draftCount` values are unchanged.
- [ ] The "All Statuses" dropdown still works for status filtering.

### Clear All Overrides row action

- [ ] On an inherited ability with overrides, the "Clear All Overrides" button appears in the Actions column.
- [ ] On an inherited ability without overrides, the button does not appear.
- [ ] Clicking shows a confirm dialog.
- [ ] Confirming clears the overrides and the row refreshes (override status returns to Default).
- [ ] Cancelling does nothing.

### Description + Show in REST columns

- [ ] Two new columns appear between Type and MCP.
- [ ] Description cell shows truncated text with full text in the `title` attribute.
- [ ] Abilities without a description show "—".
- [ ] Show in REST column shows "✓ Yes" or "○ No" correctly.
- [ ] Loading and empty-state rows span all 11 columns.

### Column visibility toggle

- [ ] A "Columns ▾" button appears in the tablenav area.
- [ ] Clicking the button opens a panel listing Label, Category, Source, Status, Type, Description, Show in REST, MCP with checkboxes.
- [ ] Unchecking a column immediately hides its `<col>`, `<th>`, and all `<td>` cells; rechecking restores them.
- [ ] Checkbox, Slug, and Actions columns are never listed in the panel and are always visible.
- [ ] Preferences survive a page reload (localStorage key `acrossai_abilities_columns`).
- [ ] When the user first visits with no saved prefs, all 8 columns are shown (defaults).
- [ ] When a new column is added in a future feature, it defaults to visible even for users who have existing saved prefs (merge-with-defaults logic).
- [ ] The colSpan on the loading and empty-state rows updates dynamically to match the number of visible columns.
- [ ] Clicking outside the panel closes it.

### Quality gates

- [ ] `composer run phpstan` passes.
- [ ] No JavaScript console errors on the abilities manager page.
- [ ] Existing bulk actions, search, source/status filters, and Edit/Delete row actions still work.
