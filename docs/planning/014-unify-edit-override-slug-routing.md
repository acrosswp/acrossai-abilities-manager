# Planning Prompt: Feature 014 — Unify Edit/Override UI, Slug-Based Routing, Publish Default

This document is the full Spec-Kit workflow prompt for Feature 014.
Run the commands in order. Paste the "Detailed Description" block verbatim into `/speckit.specify`.

---

## Problems Being Solved (verified from code)

### Bug 1 — Edit broken for plugin/core/theme abilities (`id = null`)

`AbilitiesList.jsx:479` — Edit button for non-custom abilities:
```js
onClick={ () => dispatch.setView( { mode: 'edit', id: item.id } ) }
```
`AbilitiesList.jsx:487` — Override button for non-custom abilities:
```js
onClick={ () => dispatch.setView( { mode: 'override', id: item.id } ) }
```
`AcrossAI_Ability_Merger.php:63`:
```php
$result['id'] = $has_override ? $override->id : null;
```
When a plugin/core/theme ability has **no DB override record yet**, `item.id` is `null`.
`AbilityForm.jsx:343-345` useEffect:
```js
} else if (id) {
    dispatch.fetchAbility(id);  // never called when id is null
}
```
Neither `clearDraft()` nor `fetchAbility()` is called → form shows stale/empty state.
Breadcrumb (`AbilityForm.jsx:584-586`): `savedAbility?.ability_slug || 'Add New'` → shows "Add New".

### Bug 2 — Two action buttons (Edit + Override) should be one

`AbilitiesList.jsx:436-493` renders two separate branches: custom abilities get Edit+Delete, non-custom get Edit+Override. User wants one "Edit" button for all.

### Bug 3 — "Add Ability" creates as draft

`AbilityForm.jsx:507-510`:
```js
const data = { ...draftAbility };
if (forceDraft) { data.status = 'draft'; }
```
`draftAbility.status` is undefined by default (auto-register toggle starts off).
`AcrossAI_Abilities_Write_Controller.php:179-181`:
```php
if ( empty( $fields['status'] ) ) {
    $fields['status'] = 'draft';
}
```
Result: every new ability lands as draft unless the user manually toggles auto-register.

### Bug 4 — Label/description/category not editable for plugin abilities (PHP blocks them)

`AcrossAI_Abilities_Sanitizer.php:353-371` — `strip_protected_fields_for_non_db()` strips:
`label`, `description`, `category`, `callback_type`, `callback_config`, `input_schema`, `output_schema`, `status`, `ability_slug`, `slug_suffix`, `source`.

`AcrossAI_Ability_Merger.php:27-36` — `$overridable_fields` only includes:
`site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`.
Label/description/category are absent → even if saved to DB they are never surfaced in merged results.

### Bug 5 — Slug is modifiable in edit mode (should be permanently locked)

`AbilityForm.jsx:760` — slug input has `readOnly={isEdit}` but also shows a warning:
```jsx
<div className="desc-warn">⚠ Changing the slug will break existing integrations.</div>
```
The visual warning implies it could be changed. Slug must be permanently immutable post-creation — no warning text needed, just a clear creation note.

---

## Key Existing Code to Reuse

### PHP

- **`AcrossAI_Abilities_Query::get_ability_by_slug(string $slug)`** (`Query.php:173`) — lookup by slug, returns `AcrossAI_Abilities_Row|null`.
- **`AcrossAI_Abilities_Query::save_override(string $slug, array $fields)`** (`Query.php:285`) — upsert by slug: INSERT if no existing row, UPDATE if row exists. Sets audit fields correctly. This eliminates the need for a new DB method.
- **`AcrossAI_Abilities_Query::delete_ability(int $id)`** (`Query.php:218`) — still takes int; get id from slug lookup first.
- **`AcrossAI_Abilities_Query::delete_override_by_slug(string $slug)`** (`Query.php:328`) — delete by slug directly.
- **`AcrossAI_Abilities_Formatter::format_for_response(AcrossAI_Abilities_Row $row)`** (`Formatter.php`) — for db-source update responses.
- **`AcrossAI_Abilities_Formatter::format_merged_ability(array $merged)`** (`Formatter.php`) — for non-db update responses (returns the registry+override merged shape the frontend expects).
- **`AcrossAI_Abilities_Sanitizer::sanitize_update_request(WP_REST_Request $request)`** — reuse for update field sanitization.
- **`AcrossAI_Abilities_Sanitizer::strip_protected_fields_for_non_db(array $fields)`** — keep for execution fields; modify which fields it protects.
- **`AcrossAI_Abilities_Validator::validate_ability($fields, bool $is_create)`** — reuse for validation after strip.
- **`AcrossAI_Ability_Merger::merge(array $registry, ?object $override)`** — reuse to produce merged response after upsert.
- **`AcrossAI_Ability_Merger::normalize_registry($ability)`** — normalize WP_Ability object to flat array.

### JavaScript

- **`api/client.js`** — all functions already use `apiFetch`; only path strings change.
- **`store/index.js` reducer**: `SET_SAVED`, `UPDATE_DRAFT`, `CLEAR_DRAFT`, `PATCH_ABILITY`, `REMOVE_ABILITY` — reuse; only `PATCH_ABILITY` and `REMOVE_ABILITY` switch from id-key to slug-key.
- **`AbilityForm.jsx`** `validateRequiredFields()` (exported, line 93) — reuse; adjust which fields are required for non-db edit.
- **`AbilityForm.jsx`** `TriStateSelect`, `SitePermissionTGC`, `CallbackTypeChips` — all reuse unchanged.
- **`AbilityForm.jsx`** `handleSave` structure — reuse; add non-db branch and publish default.

---

## Phase 1: Setup & Specification

```markdown
/speckit.git.feature "014-unify-edit-override-slug-routing"

/speckit.specify "<paste the FULL Detailed Description block below>"
```

### Detailed Description for `/speckit.specify`

> **FEATURE 014: Unified Edit UI, Slug-Driven API, Publish Default**
>
> This feature unifies the Edit/Override split, fixes broken editing for plugin/core/theme abilities, switches all API operations to use `ability_slug` as the identifier, and corrects the draft-creation default.
>
> **Skill requirement:** All PHP and JS implementation MUST follow `.agents/skills/wp-plugin-development/SKILL.md`. Key rules:
> - Hooks only via `includes/Main.php::define_admin_hooks()` / `define_public_hooks()` through the Loader singleton — never `add_action()`/`add_filter()` directly in feature classes.
> - Every `register_rest_route()` must declare `permission_callback` and `sanitize_callback` on all args.
> - Security baseline: `wp_unslash()` + `sanitize_*()` early, escape late, `$wpdb->prepare()` for all SQL.
> - i18n: `esc_html__()` in PHP — never `echo __()`. `__()` from `@wordpress/i18n` in JS.
> - PHPStan level 8 + PHPCS zero errors + ESLint zero errors.
> - Pre-ship: run all four scripts in `.agents/skills/wp-plugin-development/scripts/`.
>
> ---
>
> ### FR-001: Single "Edit" Action Button in the List
>
> **File:** `src/js/abilities/components/AbilitiesList.jsx`
>
> Currently lines 436–493 branch on `isCustom = 'db' === (item.source || 'db')` to show either (Edit + status dropdown + Delete) or (Edit + Override). Replace this with:
> - All abilities get **one "Edit" button** only.
> - The status dropdown (inline Enabled/Disabled select) and Delete button remain for `source = 'db'` abilities only — they are independent row actions and stay.
> - Remove the Override button entirely.
> - The Edit button onClick: `dispatch.setView({ mode: 'edit', slug: item.ability_slug, ability: item })`.
>   - `slug`: the full ability slug string (e.g. `'core/get-user-info'`).
>   - `ability`: the full item object from the list (used for instant form seeding before API fetch returns).
>
> ---
>
> ### FR-002: Remove `mode='override'` Routing Branch
>
> **File:** `src/js/abilities/components/AbilitiesManager.jsx`
>
> Currently lines 82–84 route `view.mode === 'override'` to `<AbilityForm mode="override" id={view.id} />`. Remove this branch entirely.
>
> Edit branch changes:
> - Before: `<AbilityForm mode="edit" id={view.id} />`
> - After: `<AbilityForm mode="edit" slug={view.slug} abilityData={view.ability} />`
>
> `view.slug` is the full `ability_slug` string. `view.ability` is the raw list item object for immediate seeding.
>
> ---
>
> ### FR-003: Slug-Based API — Frontend
>
> **File:** `src/js/abilities/api/client.js`
>
> Change the following function signatures and URL paths. The slug must be URL-encoded using `encodeURIComponent()` because slugs contain forward slashes (e.g., `core/get-user-info` → `core%2Fget-user-info`).
>
> - `getAbility(id)` → `getAbility(slug: string)` — path: `${BASE}/${encodeURIComponent(slug)}`
> - `updateAbility(id, data)` → `updateAbility(slug: string, data)` — path: `${BASE}/${encodeURIComponent(slug)}`, method POST
> - `deleteAbility(id)` → `deleteAbility(slug: string)` — path: `${BASE}/${encodeURIComponent(slug)}`, method DELETE
>
> `createAbility(data)` and `getAbilities(params)` and `getCategories()` are unchanged.
>
> ---
>
> ### FR-004: Slug-Based API — Store
>
> **File:** `src/js/abilities/store/index.js`
>
> #### Action type changes
>
> Add action type `PATCH_ABILITY_BY_SLUG` to update list items by slug (or repurpose `PATCH_ABILITY` to use slug). Change `REMOVE_ABILITY` to use slug as identifier.
>
> #### Reducer changes
>
> `PATCH_ABILITY` (currently line 130): change `a.id === action.id` → `a.ability_slug === action.slug`:
> ```js
> case PATCH_ABILITY:
>   return { ...state, abilities: state.abilities.map((a) =>
>     a.ability_slug === action.slug ? { ...a, ...action.patch } : a
>   ), isSaving: false };
> ```
>
> `REMOVE_ABILITY` (currently line 122): change `a.id !== action.id` → `a.ability_slug !== action.slug`:
> ```js
> case REMOVE_ABILITY:
>   return { ...state, abilities: state.abilities.filter((a) => a.ability_slug !== action.slug),
>     total: Math.max(0, state.total - 1), isSaving: false };
> ```
>
> #### Thunk changes
>
> **`fetchAbility(id)` → `fetchAbility(slug)`** (line 169):
> - Calls `api.getAbility(slug)`.
> - Otherwise identical.
>
> **`updateAbility(id, data)` → `updateAbility(slug, data)`** (line 197):
> - Calls `api.updateAbility(slug, data)`.
> - On success: `dispatch({ type: SET_SAVED, ability })` + `dispatch({ type: PATCH_ABILITY, slug, patch: ability })`.
>
> **`deleteAbility(id)` → `deleteAbility(slug)`** (line 221):
> - Calls `api.deleteAbility(slug)`.
> - On success: `dispatch({ type: REMOVE_ABILITY, slug })` + `dispatch({ type: SET_VIEW, view: 'list' })`.
>
> **`clearOverrides(id)` → `clearOverrides(slug)`** (line 257):
> - Calls `api.updateAbility(slug, nullOverrides)`.
> - Add `label: null, description: null, category: null` to `nullOverrides` (since these are now overridable for non-db abilities).
>
> **`bulkDeleteAbilities(ids)` → `bulkDeleteAbilities(slugs)`** (line 234):
> - Accepts `slugs: string[]` instead of `ids: number[]`.
> - Calls `Promise.all(slugs.map((slug) => api.deleteAbility(slug)))`.
> - Dispatches `fetchAbilities()` on success (unchanged).
>
> **`bulkUpdateStatus(ids, status)` → `bulkUpdateStatus(slugs, status)`** (line 246):
> - Accepts `slugs: string[]`.
> - Calls `Promise.all(slugs.map((slug) => api.updateAbility(slug, { status })))`.
>
> ---
>
> ### FR-005: List Checkbox and Bulk Actions Switch to Slug Keys
>
> **File:** `src/js/abilities/components/AbilitiesList.jsx`
>
> Currently `selected` is a `Set<string>` of stringified integer ids. Change to a `Set<string>` of `ability_slug` strings.
>
> - `allDbIds` → `allDbSlugs`: `new Set(dbAbilities.map((a) => a.ability_slug))`
> - `allChecked`: check `allDbSlugs.size > 0 && [...allDbSlugs].every((s) => selected.has(s))`
> - `toggleAll`: add/remove all slugs
> - `toggleOne(id)` → `toggleOne(slug)`: `setSelected` keyed by `item.ability_slug`
> - `handleBulkApply`: `const slugs = [...selected]` (already strings, no `.map(Number)` needed)
> - `dispatch.bulkDeleteAbilities(slugs)` and `dispatch.bulkUpdateStatus(slugs, status)`
> - Inline checkbox `aria-label`: use `item.ability_slug` (unchanged in label text but checked via slug)
>
> **Inline status dropdown** (`handleStatusDropdown`, line 177):
> - Before: `dispatch.updateAbility(item.id, { status: newStatus })`
> - After: `dispatch.updateAbility(item.ability_slug, { status: newStatus })`
>
> **Delete button** (line 453):
> - Before: `dispatch.deleteAbility(item.id)`
> - After: `dispatch.deleteAbility(item.ability_slug)`
>
> ---
>
> ### FR-006: AbilityForm — New Props and Lifecycle
>
> **File:** `src/js/abilities/components/AbilityForm.jsx`
>
> #### Props
> - Remove `id: number` prop.
> - Add `slug: string` prop (full ability slug, e.g. `'core/get-user-info'`).
> - Add `abilityData: object|undefined` prop (raw list item for instant seeding).
>
> #### useEffect (lines 338–346) — fix immediate seeding
> ```js
> useEffect(() => {
>   dispatch.fetchCategories();
>   if ('create' === mode) {
>     dispatch.clearDraft();
>     dispatch.setSaved(null);
>   } else if (slug) {
>     // Seed immediately from list data so form isn't blank while fetching.
>     if (abilityData) {
>       dispatch.setSaved(abilityData);
>     }
>     // Fetch fresh server data. SET_SAVED in fetchAbility will overwrite the seed.
>     dispatch.fetchAbility(slug);
>   }
> }, [mode, slug]); // eslint-disable-line react-hooks/exhaustive-deps
> ```
>
> This fixes Bug 1: when `slug` is defined (even for abilities with no DB id), `fetchAbility` is always called. The `abilityData` seed prevents the blank-form flash.
>
> #### Derived state
> ```js
> const isCreate   = 'create' === mode;
> const isEdit     = 'edit'   === mode;
> const isNonDb    = Boolean(savedAbility?.source && 'db' !== savedAbility.source);
> // isOverride is removed — no longer a mode
> ```
>
> #### handleSave changes (lines 497–547)
>
> **Fix A — Publish by default on create:**
> ```js
> if ('create' === mode) {
>   // ... existing slug logic ...
>   if (!forceDraft && !data.status) {
>     data.status = 'publish'; // FR-009: default to publish, not draft
>   }
>   const ability = await dispatch.createAbility(data);
>   if (ability) {
>     dispatch.setSaved(ability);
>     dispatch.setView({ mode: 'edit', slug: ability.ability_slug, ability });
>   }
>   return;
> }
> ```
>
> **Fix B — Edit mode unified save:**
> ```js
> if ('edit' === mode) {
>   let payload;
>   if (isNonDb) {
>     // SC-007 extended: send only allowed override fields for non-db abilities.
>     // Slug, callback, schema are never sent. Status is not applicable.
>     payload = {
>       label:        data.label,
>       description:  data.description,
>       category:     data.category,
>       site_allowed: data.site_allowed,
>       show_in_rest: data.show_in_rest,
>       show_in_mcp:  data.show_in_mcp,
>       mcp_type:     data.mcp_type,
>       mcp_servers:  data.mcp_servers,
>       readonly:     data.readonly,
>       destructive:  data.destructive,
>       idempotent:   data.idempotent,
>     };
>   } else {
>     payload = data; // db-source: send everything
>   }
>   await dispatch.updateAbility(slug, payload);
>   return;
> }
> ```
>
> Remove the separate `if ('override' === mode)` block (lines 533–546) — it is replaced by the `isNonDb` branch above.
>
> #### handleDelete (lines 549–561)
> - Change `dispatch.deleteAbility(id)` → `dispatch.deleteAbility(slug)`.
>
> #### handleClearOverrides (lines 563–575)
> - Change `dispatch.clearOverrides(id)` → `dispatch.clearOverrides(slug)`.
>
> ---
>
> ### FR-007: AbilityForm — Section Visibility for Non-DB Abilities
>
> **File:** `src/js/abilities/components/AbilityForm.jsx`
>
> Everywhere `isOverride` controlled visibility, replace with `isNonDb`:
>
> | Section | Condition | Change |
> |---|---|---|
> | Section 1 — Identity (lines 723–952) | `{!isOverride && ...}` → `always show` | Show for create AND edit (any source). Slug readOnly when `!isCreate`. |
> | Auto-register toggle (lines 888–951) | Currently inside Section 1 | Hide when `isNonDb`: `{!isNonDb && (<div className="fr">...</div>)}`. Status has no effect for plugin/core/theme abilities; `site_allowed` is their enable/disable. |
> | Section 2 — Callback (lines 955–994) | `{!isOverride && ...}` → `{!isNonDb && ...}` | Hidden for non-db edit. |
> | Section 3 — Schema (lines 997–1062) | `{!isOverride && ...}` → `{!isNonDb && ...}` | Hidden for non-db edit. |
> | Section 4 — MCP Exposure (lines 1064–1218) | Always shown | No change. |
> | Site Permission section (lines 1220–1255) | `{isOverride && ...}` → `{isNonDb && ...}` | Show in non-db edit, hidden for db edit and create. |
> | Section 5 — Annotations (lines 1258–1328) | Always shown | No change. |
> | `show_in_rest` TriStateSelect (lines 1315–1327) | `{isOverride && ...}` → `{isNonDb && ...}` | Show for non-db edit only. |
> | Slug readOnly | `readOnly={isEdit}` (line 760) | Change to `readOnly={!isCreate}` — same outcome but clearer intent. |
> | Slug "⚠ Changing the slug" warning (lines 773–779) | Currently shown in edit mode | **Remove entirely.** Slug is permanently immutable — no warning needed. |
> | Slug creation note | Lines 780–787 — "Lowercase letters, numbers..." | **Replace** with: `"Once saved, this slug cannot be changed."` (shown only in create mode, i.e. `{isCreate && <div className="desc">Once saved, this slug cannot be changed.</div>}`). |
>
> ---
>
> ### FR-008: AbilityForm — Remove LockedCard, Add Provider Info Row
>
> **File:** `src/js/abilities/components/AbilityForm.jsx`
>
> Remove the `LockedCard` component entirely (lines 107–166). It is replaced by a small provider info row rendered at the top of the Identity section when `isNonDb && savedAbility`:
> ```jsx
> {isNonDb && savedAbility && (
>   <div className="fr provider-info-row">
>     <div className="fl">{__('Registered by', 'acrossai-abilities-manager')}</div>
>     <div className="ff">
>       <strong>{savedAbility.provider || __('external source', 'acrossai-abilities-manager')}</strong>
>       {' '}<SourceBadge source={savedAbility.source} />
>     </div>
>   </div>
> )}
> ```
>
> Remove the `{isOverride && savedAbility && (<LockedCard ability={savedAbility} />)}` block (line 715–717).
>
> ---
>
> ### FR-009: AbilityForm — UI Text Unification
>
> **File:** `src/js/abilities/components/AbilityForm.jsx`
>
> - Page title (lines 637–642): Remove `{isOverride && __('Override Ability'...)}`. All edit modes show `{isEdit && __('Edit Ability'...)}`.
> - Subtitle (lines 688–701): Remove the override subtitle block (lines 688–701). In edit mode, show a single subtitle only when `isNonDb`: `"Slug, Callback, and Schema are defined by the plugin and cannot be changed here."`.
> - Save button label (lines 604–613): Remove `'✓ Save Overrides'` label. Non-override: `'✓ Save Changes'` for all edit modes.
>
> #### Sidebar
> - Remove the separate "Override: Actions box" (lines 1417–1449) and its `{isOverride && ...}` guard.
> - The existing "Edit: Update box" (lines 1372–1415) remains and is shown for ALL edit modes (`{isEdit && ...}` → `{!isCreate && ...}`).
> - Inside the Update box, add the "Clear All Overrides" link for non-db abilities only:
>   ```jsx
>   {isNonDb && (
>     <div style={{ borderTop: '1px dashed #ddd', marginTop: '14px', paddingTop: '12px', textAlign: 'center' }}>
>       <button type="button" className="button-link" style={{ fontSize: '12px' }} onClick={handleClearOverrides}>
>         {__('↩ Clear All Overrides', 'acrossai-abilities-manager')}
>       </button>
>     </div>
>   )}
>   ```
> - The "Delete Ability" link inside the Update box remains, but guard with `{!isNonDb && ...}` (non-db abilities cannot be deleted — PHP already enforces this, but hide the button to avoid user confusion).
> - The `{isCreate && ...}` Publish box stays (lines 1338–1370) with the "✓ Add Ability" and "Save as Draft" buttons.
>
> ---
>
> ### FR-010: AbilityForm — Required Field Validation for Non-DB Edit
>
> **File:** `src/js/abilities/components/AbilityForm.jsx`
>
> Currently `hasRequiredErrors` (lines 593–601) and `handleSave` gate on required fields for `create` and `edit` modes. In the new unified edit mode, non-db abilities have editable label/description/category but these are **not required** — they fall back to WP registry values if left empty/null.
>
> Required field validation applies only to create mode and db-source edit mode:
> ```js
> if ('create' === mode || ('edit' === mode && !isNonDb)) {
>   const errors = validateRequiredFields(draftAbility, slugSuffix);
>   setFormErrors(errors);
>   if (Object.values(errors).some(Boolean)) return;
> }
> ```
>
> `hasRequiredErrors` derived state also gates on `!isNonDb`:
> ```js
> const hasRequiredErrors = ('create' === mode || ('edit' === mode && !isNonDb))
>   ? (!slugSuffix.trim() || !draftAbility.label?.trim() || !draftAbility.description?.trim() || !draftAbility.category?.trim())
>   : false;
> ```
>
> Blur validators (`handleLabelBlur`, etc.) already gate on `'create' === mode || 'edit' === mode` — extend guard: also check `!isNonDb`.
>
> ---
>
> ### FR-011: PHP — Slug-Based Route Registration
>
> **File:** `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`
>
> Change the route pattern for the single-item update+delete route (lines 99–130):
> - Before: `'/abilities/(?P<id>\d+)'`
> - After: `'/abilities/(?P<slug>[^/]+)'`
>
> The `[^/]+` regex matches a single URL segment. Slugs containing forward slashes (e.g., `core/get-user-info`) will be URL-encoded by the frontend (`encodeURIComponent`) so they arrive as `core%2Fget-user-info` — a single segment with no literal `/`. WordPress REST API automatically percent-decodes path params, so `$request->get_param('slug')` returns the decoded `core/get-user-info`.
>
> Route args — remove integer id validation, add slug validation:
> ```php
> 'args' => array(
>   'slug' => array(
>     'type'              => 'string',
>     'required'          => true,
>     'sanitize_callback' => function( $slug ) {
>       return AcrossAI_Abilities_Sanitizer::sanitize_ability_slug( rawurldecode( (string) $slug ) );
>     },
>     'validate_callback' => function( $slug ) {
>       return is_string( $slug ) && '' !== trim( $slug );
>     },
>   ),
> ),
> ```
>
> **File:** `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php`
>
> Same change for the GET single-item route (lines 149–167):
> - Before: `'/abilities/(?P<id>\d+)'` with integer arg
> - After: `'/abilities/(?P<slug>[^/]+)'` with string arg (same sanitize/validate as above)
>
> **IMPORTANT:** Both controllers must register their slug routes AFTER `/abilities/categories` (already the case for the Read Controller since categories is registered first). Verify registration order is maintained.
>
> ---
>
> ### FR-012: PHP — `update_ability` Method Rewrite (Slug + Upsert)
>
> **File:** `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`
>
> Rewrite `update_ability(WP_REST_Request $request)` (lines 224–283).
>
> New logic:
> ```
> 1. Read slug from request: $slug = $request->get_param('slug');
>    (Already sanitized via route arg sanitize_callback.)
>
> 2. Try DB lookup by slug:
>    $existing = $this->db_query->get_ability_by_slug($slug);
>
> 3a. DB row found (covers db-source AND non-db-with-existing-override):
>    - Sanitize fields: $fields = AcrossAI_Abilities_Sanitizer::sanitize_update_request($request);
>    - Strip protected fields if source ≠ 'db': same as before.
>    - Validate: AcrossAI_Abilities_Validator::validate_ability($validate_context, false).
>    - Update: $this->db_query->update_ability($existing->id, $fields);
>    - Re-read row: $saved_row = $this->db_query->get_ability_by_id($saved_id);
>    - Response:
>      - If $saved_row->source === 'db': return format_for_response($saved_row).
>      - Else (non-db): build merged response — get WP registry data via wp_get_ability($slug),
>        normalize via AcrossAI_Ability_Merger::normalize_registry(), merge via
>        AcrossAI_Ability_Merger::merge($registry, $saved_row), return format_merged_ability($merged).
>
> 3b. DB row NOT found — check if slug exists in WP registry:
>    - $wp_ability = wp_get_ability($slug);
>    - If null: return WP_Error('rest_not_found', 'Ability not found.', ['status' => 404]).
>    - If found: this is a non-db ability's first override. Use UPSERT path:
>      - Sanitize: $fields = AcrossAI_Abilities_Sanitizer::sanitize_update_request($request);
>      - Strip protected non-db fields: AcrossAI_Abilities_Sanitizer::strip_protected_fields_for_non_db($fields).
>      - Add source to fields: $fields['source'] = AcrossAI_Ability_Source_Detector::detect($slug)
>        OR derive from WP registry directly. Fallback: 'plugin'.
>      - Call $this->db_query->save_override($slug, $fields); — handles INSERT with created_at/created_by.
>      - Re-read: $saved_row = $this->db_query->get_ability_by_slug($slug).
>      - Build merged response and return format_merged_ability($merged).
>
> 4. Fire acrossai_abilities_after_update hook with $saved_row after all paths.
>    Call AcrossAI_Ability_Override_Processor::bust_cache() to invalidate transient.
> ```
>
> ---
>
> ### FR-013: PHP — `delete_ability` Method Update (Slug-Based)
>
> **File:** `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`
>
> Rewrite `delete_ability(WP_REST_Request $request)` (lines 294–340):
> ```
> 1. $slug = $request->get_param('slug');
> 2. $existing = $this->db_query->get_ability_by_slug($slug);
> 3. If null: WP_Error 404.
> 4. If $existing->source !== 'db': WP_Error 403 (unchanged guard).
> 5. do_action('acrossai_abilities_before_delete', $existing);
> 6. $this->db_query->delete_ability($existing->id); // still uses integer id internally
> 7. Fire after-delete hook, bust cache, return 200.
> ```
>
> ---
>
> ### FR-014: PHP — `get_ability` Method Update (Slug-Based Read)
>
> **File:** `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php`
>
> Rewrite `get_ability(WP_REST_Request $request)` (lines 227+):
> ```
> 1. $slug = $request->get_param('slug');
> 2. Try DB lookup: $row = $this->db_query->get_ability_by_slug($slug);
> 3a. Found AND source='db': return format_for_response($row).
> 3b. Found AND source≠'db': build merged (registry + DB row) → format_merged_ability($merged).
> 3c. Not in DB: try WP registry: $wp_ability = wp_get_ability($slug).
>     - If found: normalize → format_merged_ability with null override → return registry-only response.
>     - If not found: WP_Error 404.
> ```
>
> ---
>
> ### FR-015: PHP — Sanitizer: Allow Label/Description/Category for Non-DB Updates
>
> **File:** `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`
>
> Method `strip_protected_fields_for_non_db(array $fields)` (lines 353–371).
>
> Before:
> ```php
> $protected = array(
>   'label', 'description', 'category',        // ← REMOVE THESE THREE
>   'callback_type', 'callback_config',
>   'input_schema', 'output_schema',
>   'status',
>   'ability_slug', 'slug_suffix', 'source',
> );
> ```
>
> After:
> ```php
> $protected = array(
>   'callback_type', 'callback_config',         // execution fields — always protected
>   'input_schema', 'output_schema',
>   'status',                                   // no auto-register for non-db
>   'ability_slug', 'slug_suffix', 'source',    // immutable identity fields
> );
> ```
>
> This allows `label`, `description`, `category` to pass through for non-db update requests.
>
> ---
>
> ### FR-016: PHP — Merger: Add Label/Description/Category to Overridable Fields
>
> **File:** `includes/Utilities/AcrossAI_Ability_Merger.php`
>
> Add `'label'`, `'description'`, `'category'` to `$overridable_fields` array (line 27).
>
> Update the merge loop (lines 54–61) to guard against empty-string DB values overwriting non-empty registry values:
> ```php
> foreach ( self::$overridable_fields as $field ) {
>   if ( $has_override && null !== $override->{$field} && '' !== (string) $override->{$field} ) {
>     $result[ $field ] = $override->{$field};
>   } else {
>     $result[ $field ] = isset( $registry[ $field ] ) ? $registry[ $field ] : null;
>   }
> }
> ```
>
> Also update `is_all_default()` (lines 92–106) — no change needed since it already iterates `$overridable_fields`; the new fields are added automatically.
>
> Also update `$override_raw` loop (lines 72–76) — same, no change needed; it already iterates `$overridable_fields`.
>
> This means for non-db abilities, the `_override` field in the API response will now include `label`, `description`, `category` override values (null when not set).
>
> ---
>
> ### FR-017: PHP — Source Detection for Upsert
>
> When inserting a new DB override row for a non-db ability (FR-012, path 3b), the `source` field must be set correctly so the DB row is not treated as a `db`-managed ability.
>
> Use **`AcrossAI_Ability_Source_Detector::detect_source($slug)`** if it exists, OR derive directly from the WP_Ability object:
> ```php
> $wp_ability  = wp_get_ability( $slug );
> $registry    = AcrossAI_Ability_Merger::normalize_registry( $wp_ability );
> $source      = $registry['source'] ?? 'plugin'; // 'plugin', 'core', or 'theme'
> $fields['source'] = $source;
> ```
>
> Check `includes/Utilities/AcrossAI_Ability_Source_Detector.php` for the existing implementation pattern to reuse.
>
> ---
>
> ### CONSTRAINTS
>
> #### Feature-Specific
>
> - **Slug immutability in create form:** `AbilityForm.jsx` create mode slug input must show: `"Once saved, this slug cannot be changed."` below the input. Remove the existing "⚠ Changing the slug will break existing integrations." warning from edit mode.
> - **No double-registering routes:** Ensure `/abilities/categories` (registered by the Category sub-controller) is registered before `/abilities/(?P<slug>[^/]+)` so it takes priority.
> - **PATCH_ABILITY after list status dropdown:** The inline `handleStatusDropdown` update in `AbilitiesList.jsx` uses `updateAbility` — after the thunk succeeds, `PATCH_ABILITY` now matches by `ability_slug`, so the list item refresh is automatic.
> - **Bulk operations:** Bulk delete and bulk status update currently pass integer ids. After this feature they pass slug strings. The `selected` Set now holds slugs, not stringified ids.
> - **`clearOverrides` extended null set:** Clear overrides must null-out `label`, `description`, `category` in addition to the existing 8 fields — so registry values are restored in the merged result.
> - **Activity sidebar:** The Activity box (lines 1513–1547) currently guards `{isEdit && savedAbility && ...}`. Keep as `{!isCreate && savedAbility && ...}` so it shows in both db-source and non-db edit.
> - **Preview sidebar:** Lines 1452–1511 — `{!isOverride && ...}` guards on Callback preview row (line 1499). Change to `{!isNonDb && ...}`.
> - **`wp_get_ability()` availability:** This function requires WP 6.9+. The plugin already uses it (see `AcrossAI_Ability_Override_Processor.php`). No compatibility wrapper needed.
> - **Auth:** All write and read endpoints retain the `manage_options` capability check via `AcrossAI_Abilities_Rest_Controller::check_permission()`. No changes to auth layer.
> - **No new DB columns:** All columns needed (label, description, category, source, etc.) already exist in `AcrossAI_Abilities_Schema.php`. The `save_override()` method already handles all columns via `prepare_fields_for_write()`.
> - **`format_merged_ability()` response shape:** Must match the shape the frontend expects (same keys as `format_for_response()` plus `_registry`, `_override`, `has_override`). Confirm formatter produces the `ability_slug`, `label`, `id`, `source`, `status`, `has_override` keys that the frontend's `SET_SAVED` reducer and `PATCH_ABILITY` reducer expect.
>
> #### WP Plugin Dev Skill Constraints (`.agents/skills/wp-plugin-development/SKILL.md`)
>
> **Boot Flow**
> - `includes/Main.php` is the sole hook entry point. `define_admin_hooks()` and `define_public_hooks()` are the ONLY methods that call `$this->loader->add_action()` / `$this->loader->add_filter()`. No feature class may call `add_action()` or `add_filter()` directly.
> - The existing `AcrossAI_Abilities_Write_Controller` and `AcrossAI_Abilities_Read_Controller` are already wired via `rest_api_init` in `Main.php`. No new hook wiring is needed — only route patterns and callback logic inside the controllers change.
> - All feature classes use `protected static $_instance = null; public static function instance(): self`. No new base classes or `register_hooks()` delegation.
> - No `Module_Base` abstract class and no `register_hooks( Loader $loader )` convention. Do not create these.
>
> **REST API**
> - Routes registered on `rest_api_init`; namespace `acrossai-abilities-manager/v1`.
> - Every `register_rest_route()` must have `permission_callback` — already `AcrossAI_Abilities_Rest_Controller::check_permission()` (manage_options). Never `__return_true` on mutating routes.
> - All route args must declare `sanitize_callback` in the arg schema. For the new `slug` arg:
>   ```php
>   'sanitize_callback' => function( $slug ) {
>     return AcrossAI_Abilities_Sanitizer::sanitize_ability_slug( rawurldecode( (string) $slug ) );
>   },
>   'validate_callback' => function( $slug ) {
>     return is_string( $slug ) && '' !== trim( $slug );
>   },
>   ```
> - Return `WP_REST_Response|WP_Error` from all callbacks.
>
> **Security Baseline**
> - Sanitize input early: `wp_unslash()` + `sanitize_*()` — already handled by `AcrossAI_Abilities_Sanitizer`. The new slug param must go through `rawurldecode()` + `sanitize_ability_slug()` before any DB lookup.
> - Escape output late: `esc_html()`, `esc_attr()`, `esc_url()` — no raw echo of user-controlled data.
> - Capability check before every mutation: `current_user_can('manage_options')` — enforced by `check_permission()` on all routes.
> - SQL via `$wpdb->prepare()` — never string concatenation. BerlinDB handles this internally; any raw `$wpdb` calls must use `prepare()`.
>
> **PSR-4 / Namespace**
> - No new PHP classes are needed (only modifying existing files). If a helper class is introduced, it must match the PSR-4 map in `composer.json`, and `composer dump-autoload` must be run.
>
> **Internationalization**
> - All new PHP strings: `esc_html__( 'string', 'acrossai-abilities-manager' )` — never `echo __()`.
> - New JS strings: `__()` from `@wordpress/i18n`. No hardcoded untranslated UI text.
>
> **Code Quality**
> - PHPStan level 8: `?AcrossAI_Abilities_Row` return types, null-checks before every property access, typed parameters.
> - PHPCS zero errors: no direct SQL, WordPress coding standards, WP escaping functions throughout.
> - ESLint: zero new lint errors in JS changes.

---

## Phase 2: Planning & Validation

```markdown
# 3. Generate technical plan with full memory context
/speckit.memory-md.plan-with-memory

# 4. Validate plan against Constitution + prior architectural decisions
/speckit.architecture-guard.governed-plan

# 5. Security review of plan
/speckit.security-review.plan
```

---

## Phase 3: Task Generation

```markdown
# 6. Generate dependency-ordered tasks
/speckit.tasks

# 7. Validate tasks for architecture drift and refactor awareness
/speckit.architecture-guard.governed-tasks

# 8. Review task sequencing for security gaps
/speckit.security-review.tasks
```

---

## Phase 4: Implementation

```markdown
# 9. Execute with governance + immediate code review
/speckit.architecture-guard.governed-implement

# 10. Verification (run after implement)
npm run build
composer run phpcs
composer run phpstan
npm run lint:js

# 11. WP Plugin Dev skill validators (run from plugin root)
node .agents/skills/wp-plugin-development/scripts/validate-structure.mjs --dir=.
node .agents/skills/wp-plugin-development/scripts/validate-security.mjs --dir=.
node .agents/skills/wp-plugin-development/scripts/detect-deprecations.mjs --dir=.
node .agents/skills/wp-plugin-development/scripts/detect-rest-endpoints.mjs --dir=.
```

---

## Phase 5: Review, Memory & Commit

```markdown
# 11. Cross-artifact consistency check
/speckit.analyze

# 12. Architecture drift analysis
/speckit.architecture-guard.architecture-review

# 13. Security audit of staged changes
/speckit.security-review.staged

# 14. Extract learnings to memory
/speckit.memory-md.capture-from-diff

# 15. Structured commit
/speckit.git.commit
```

---

## Manual Verification Checklist

### List View
- [ ] All abilities show only one "Edit" button in the Actions column. No "Override" button anywhere.
- [ ] Custom (db) abilities still show the inline status dropdown and Delete button.
- [ ] Bulk actions (delete, publish, unpublish) work using slug-keyed selection.

### Edit — Custom (db-source) Ability
- [ ] All sections visible: Identity (with slug read-only, no warning text), Callback, Schema, MCP Exposure, Annotations.
- [ ] Auto-register toggle visible and functional.
- [ ] No "Site Permission" section (that's non-db only).
- [ ] Slug field: `readOnly`, no "⚠ Changing the slug..." warning. No edit affordance.
- [ ] Save via `POST /abilities/{urlEncodedSlug}`.

### Edit — Plugin/Core/Theme Ability (no existing DB override)
- [ ] Clicking Edit → form seeds immediately from list item data (no blank flash).
- [ ] API fetch `GET /abilities/{urlEncodedSlug}` completes and refreshes form.
- [ ] Callback section hidden. Schema section hidden. Auto-register toggle hidden.
- [ ] Site Permission section visible (Force Block / Inherit / Force Allow).
- [ ] `show_in_rest` TriStateSelect visible in Annotations section.
- [ ] Provider info row shows at top of form: "Registered by [provider] [SourceBadge]".
- [ ] Label, Description, Category are editable.
- [ ] Save triggers `POST /abilities/{urlEncodedSlug}` → PHP upserts new DB row.
- [ ] After save: ability now has `has_override: true` in API response.
- [ ] No "Delete Ability" button (non-db).
- [ ] "↩ Clear All Overrides" link visible.

### Edit — Plugin/Core/Theme Ability (existing DB override)
- [ ] `GET /abilities/{slug}` returns merged result with `has_override: true`.
- [ ] All overridable fields pre-populated from DB overrides.
- [ ] Save via `POST /abilities/{urlEncodedSlug}` updates existing DB row.
- [ ] Clear All Overrides nulls out label, description, category, and all 8 override fields.

### Create Ability
- [ ] Slug input shows "Once saved, this slug cannot be changed." below it (not a warning — informational).
- [ ] Click "✓ Add Ability" → ability created with `status: 'publish'`. Appears in "Published" tab.
- [ ] Click "Save as Draft" → ability created with `status: 'draft'`. Appears in "Draft" tab.
- [ ] After create, navigates to edit form with the new ability's slug.

### REST API
- [ ] `GET /acrossai-abilities-manager/v1/abilities/{slug}` — 200 for db ability.
- [ ] `GET /acrossai-abilities-manager/v1/abilities/core%2Fget-user-info` — 200 for core ability (merged).
- [ ] `POST /acrossai-abilities-manager/v1/abilities/core%2Fget-user-info` — 200, upserts override.
- [ ] `DELETE /acrossai-abilities-manager/v1/abilities/{db-slug}` — 204.
- [ ] `DELETE /acrossai-abilities-manager/v1/abilities/core%2Fget-user-info` — 403 (non-db delete blocked).
- [ ] `GET /acrossai-abilities-manager/v1/abilities/categories` — still 200 (route not broken).

### Quality
- [ ] `npm run build` exits 0.
- [ ] `composer run phpcs` zero errors.
- [ ] `composer run phpstan` zero errors at level 8.
- [ ] `npm run lint:js` zero errors.
- [ ] `node .agents/skills/wp-plugin-development/scripts/validate-structure.mjs --dir=.` passes.
- [ ] `node .agents/skills/wp-plugin-development/scripts/validate-security.mjs --dir=.` passes.
- [ ] `node .agents/skills/wp-plugin-development/scripts/detect-deprecations.mjs --dir=.` no new deprecations.
- [ ] `node .agents/skills/wp-plugin-development/scripts/detect-rest-endpoints.mjs --dir=.` new slug routes listed correctly.
