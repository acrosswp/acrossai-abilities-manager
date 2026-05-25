# Implementation Plan: Feature 014 — Unified Ability Editing, Slug-Based Routing, Publish Default

**Branch**: `014-unify-edit-override-slug-routing` | **Date**: 2026-05-25 | **Spec**: [spec.md](./spec.md)
**Memory Synthesis**: [memory-synthesis.md](./memory-synthesis.md)

---

## Summary

Fixes five confirmed bugs across AbilitiesList, AbilityForm, two REST controllers, the Sanitizer, and the Merger. Replaces integer-id routing with slug-based routing on both single-ability REST endpoints, unifies the Edit/Override UI into a single Edit flow, makes label/description/category overridable for non-custom abilities, and defaults new ability creation to `publish`. No new DB columns, no new PHP classes, no new hook wiring in Main.php.

---

## Technical Context

**Language/Version**: PHP 7.4+ / WordPress 6.9+ / React (via `@wordpress/scripts`) / Node 20  
**Primary Dependencies**: BerlinDB, `@wordpress/i18n`, `@wordpress/api-fetch`  
**Storage**: Existing `abilities` table — no migration needed  
**Testing**: PHPUnit (PHP), Jest (JS)  
**Target Platform**: WordPress admin panel (single-site only)  
**Constraints**: PHPStan level 8 zero errors; PHPCS zero errors; ESLint zero new errors; `npm run build` via Node 20

---

## Constitution Check

| Principle | Status |
|---|---|
| §I Modular Architecture | ✅ — Changes scoped to Abilities module only |
| §II WordPress Standards | ✅ — PHPCS, PHPStan, ESLint gates required |
| §III User-Centric Design | ✅ (deviation active) — Custom HTML form pattern continues (DEC-DESIGN-OVERRIDES-DATAVIEWS) |
| §IV Security First | ✅ — `sanitize_ability_slug()` + `rawurldecode()` on new slug arg; SEC-01/SEC-04/SEC-02 enforced |
| §V Extensibility | ✅ — No new hooks; existing REST hooks reused |
| §VI DRY | ✅ — All methods reuse existing Query/Formatter/Merger/Sanitizer; no duplication |
| §VII Definition of Done | Enforced at task level (PHPCS + PHPStan + ESLint + security review + Jest/PHPUnit) |

**Hard Conflict (must resolve before implementation)**:  
`AcrossAI_Abilities_Rest_Controller::register_routes()` currently registers Write then Read then Category. Once the `(?P<slug>[^/]+)` pattern is active, `/abilities/categories` will match the slug route. Category **must** be registered first. This is addressed in T-PHP-01.

---

## Project Structure

### Documentation (this feature)

```text
specs/014-unify-edit-slug-routing/
├── plan.md                  ← this file
├── memory-synthesis.md
├── spec.md
└── checklists/
    └── requirements.md
```

### Source Files Modified (no new files)

```text
PHP (no new classes):
  includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php   ← route registration order
  includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php  ← route + update/delete rewrites
  includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php   ← route + get_ability rewrite
  includes/Utilities/AcrossAI_Ability_Merger.php                           ← overridable_fields + merge() fixes
  includes/Utilities/AcrossAI_Abilities_Sanitizer.php                      ← strip_protected_fields change
  includes/Utilities/AcrossAI_Abilities_Formatter.php                      ← format_merged_ability() additions

JS (no new components):
  src/js/abilities/api/client.js
  src/js/abilities/store/index.js
  src/js/abilities/components/AbilitiesList.jsx
  src/js/abilities/components/AbilitiesManager.jsx
  src/js/abilities/components/AbilityForm.jsx
```

---

## Implementation Phases

### Phase A — PHP Utilities (Merger, Sanitizer, Formatter)

**Rationale**: These are pure data-transformation utilities with no UI dependency. Changes here are self-contained and can be validated with PHPUnit in isolation.

#### T-PHP-A1 — Merger: add label/description/category to overridable fields
- File: `includes/Utilities/AcrossAI_Ability_Merger.php`
- Change `$overridable_fields` to include `'label'`, `'description'`, `'category'` (in that order, prepend to existing array)
- Update `merge()` loop guard: `null !== $override->{$field}` → `null !== $override->{$field} && '' !== (string) $override->{$field}`
- Update `has_override` computation: replace `null !== $override` with `null !== $override && $this->has_any_non_null_field($override)` — add private static helper `has_any_non_null_field(object $override): bool` that iterates `self::$overridable_fields` and returns true if any is non-null and non-empty-string
- Add `created_at` and `created_by` propagation: `$result['created_at'] = $has_override ? $override->created_at : null;` and `$result['created_by'] = $has_override ? $override->created_by : null;`
- `is_all_default()` and `$override_raw` loop: no change needed (auto-benefit from expanded `$overridable_fields`)
- **Validation**: PHPStan level 8; PHPCS

#### T-PHP-A2 — Sanitizer: allow label/description/category for non-db updates
- File: `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`
- Method: `strip_protected_fields_for_non_db(array $fields): array`
- Remove `'label'`, `'description'`, `'category'` from `$protected` array
- Keep: `callback_type`, `callback_config`, `input_schema`, `output_schema`, `status`, `ability_slug`, `slug_suffix`, `source`
- **Validation**: PHPStan level 8; PHPCS; verify no existing PHPUnit test for this method breaks

#### T-PHP-A3 — Formatter: fix format_merged_ability() response shape
- File: `includes/Utilities/AcrossAI_Abilities_Formatter.php`
- Add `'_registry' => $merged['_registry'] ?? null` to the return array
- Change `'created_at' => null` → `'created_at' => self::to_iso8601( $merged['created_at'] ?? null )`
- Change `'created_by' => null` → `'created_by' => $merged['created_by'] ?? null`
- **Note**: `editable` is currently `false`; leave as-is (frontend uses `source !== 'db'` not `editable` for `isNonDb` detection)
- **Validation**: PHPStan level 8; PHPCS

---

### Phase B — PHP REST Controllers

**Rationale**: Route changes affect live API contracts. Category must be registered first (hard constraint). All PHP security gates (sanitize_callback, validate_callback, permission_callback) must be present.

#### T-PHP-B1 — Orchestrator: fix registration order
- File: `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php`
- `register_routes()` — reorder calls:
  ```php
  AcrossAI_Abilities_Category_Controller::instance()->register_routes();  // FIRST
  AcrossAI_Abilities_Write_Controller::instance()->register_routes();
  AcrossAI_Abilities_Read_Controller::instance()->register_routes();
  AcrossAI_Abilities_Exposure_Controller::instance()->register_routes();
  ```
- **Validation**: PHPCS; manual test: `GET /wp-json/acrossai-abilities-manager/v1/abilities/categories` still returns categories (not 404)

#### T-PHP-B2 — Write Controller: slug route + update_ability rewrite
- File: `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`
- Route change: `/abilities/(?P<id>\d+)` → `/abilities/(?P<slug>[^/]+)` with slug arg definition:
  ```php
  'slug' => array(
    'type'              => 'string',
    'required'          => true,
    'sanitize_callback' => function ( $slug ) {
      return AcrossAI_Abilities_Sanitizer::sanitize_ability_slug( rawurldecode( (string) $slug ) );
    },
    'validate_callback' => function ( $slug ) {
      return is_string( $slug ) && '' !== trim( $slug );
    },
  ),
  ```
- `update_ability()` rewrite (see spec FR-012 for full logic):
  1. `$slug = $request->get_param('slug');` (pre-sanitized)
  2. Exclusion check before DB lookup (DEC-EARLY-404-REST-CHECK)
  3. `$existing = $this->db_query->get_ability_by_slug($slug);`
  4. If found → sanitize → strip if non-db → validate → update by `$existing->id` → re-read → format
  5. If not found → `wp_get_ability($slug)` → if null: 404 → if found: upsert path via `save_override()` → detect source via `AcrossAI_Ability_Source_Detector::detect($registry)` → re-read → merge → `format_merged_ability()`
  6. Fire `acrossai_abilities_after_update` hook; call `AcrossAI_Ability_Override_Processor::bust_cache()`
- `delete_ability()` rewrite (spec FR-013):
  1. `$slug = $request->get_param('slug');`
  2. `$existing = $this->db_query->get_ability_by_slug($slug);`
  3. If null: 404. If `source !== 'db'`: 403.
  4. `do_action('acrossai_abilities_before_delete', $existing);`
  5. `$this->db_query->delete_ability($existing->id);`
  6. Fire after-delete hook; bust cache; return 200.
- **Validation**: PHPStan level 8; PHPCS

#### T-PHP-B3 — Read Controller: slug route + get_ability rewrite
- File: `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php`
- Same route change as Write: `/abilities/(?P<id>\d+)` → `/abilities/(?P<slug>[^/]+)` with same slug arg schema
- `get_ability()` rewrite (spec FR-014):
  1. `$slug = $request->get_param('slug');`
  2. `$row = $this->db_query->get_ability_by_slug($slug);`
  3. If found and `source === 'db'`: `format_for_response($row)`
  4. If found and `source !== 'db'`: fetch registry → normalize → merge → `format_merged_ability()`
  5. If not in DB: `wp_get_ability($slug)` → if found: normalize → merge(registry, null) → `format_merged_ability()` → if null: 404
- **Validation**: PHPStan level 8; PHPCS

---

### Phase C — JavaScript: API, Store

**Rationale**: Client and store changes are pure API contract migrations. No UI changes. These must land before UI changes so the UI can import updated dispatch methods.

#### T-JS-C1 — api/client.js: slug-based function signatures
- `getAbility(id)` → `getAbility(slug)` — path: `` `${BASE}/${encodeURIComponent(slug)}` ``
- `updateAbility(id, data)` → `updateAbility(slug, data)` — same slug path, method POST
- `deleteAbility(id)` → `deleteAbility(slug)` — same slug path, method DELETE
- `createAbility`, `getAbilities`, `getCategories`: unchanged

#### T-JS-C2 — store/index.js: slug-keyed reducers and thunks
- `PATCH_ABILITY` reducer: `a.id === action.id` → `a.ability_slug === action.slug`
- `REMOVE_ABILITY` reducer: `a.id !== action.id` → `a.ability_slug !== action.slug`
- `fetchAbility(id)` → `fetchAbility(slug)`: calls `api.getAbility(slug)`
- `updateAbility(id, data)` → `updateAbility(slug, data)`: calls `api.updateAbility(slug, data)`; on success `dispatch({ type: PATCH_ABILITY, slug, patch: ability })`
- `deleteAbility(id)` → `deleteAbility(slug)`: calls `api.deleteAbility(slug)`; on success `dispatch({ type: REMOVE_ABILITY, slug })`
- `clearOverrides(id)` → `clearOverrides(slug)`: add `label: null, description: null, category: null` to `nullOverrides`; calls `api.updateAbility(slug, nullOverrides)`
- `bulkDeleteAbilities(ids)` → `bulkDeleteAbilities(slugs)`: `slugs.map(slug => api.deleteAbility(slug))`
- `bulkUpdateStatus(ids, status)` → `bulkUpdateStatus(slugs, status)`: `slugs.map(slug => api.updateAbility(slug, { status }))`

---

### Phase D — JavaScript: List and Manager

#### T-JS-D1 — AbilitiesList.jsx: single Edit button + slug selection
- Remove Override button; keep Edit button for all sources
- Edit button `onClick`: `dispatch.setView({ mode: 'edit', slug: item.ability_slug, ability: item })`
- `selected` Set: keyed by `ability_slug` (string), not stringified id
- `allDbSlugs`: `new Set(dbAbilities.map(a => a.ability_slug))`
- `toggleOne(slug)`: add/remove by slug
- `handleBulkApply`: detect mixed selection (any slug in `selected` maps to a non-db ability) → if mixed: show warning notice "Your selection includes abilities that cannot be deleted. Remove non-custom abilities from your selection and try again." and abort. Otherwise proceed with `dispatch.bulkDeleteAbilities(slugs)` or `dispatch.bulkUpdateStatus(slugs, status)`.
- Inline status dropdown `handleStatusDropdown`: `dispatch.updateAbility(item.ability_slug, { status })`
- Delete button: `dispatch.deleteAbility(item.ability_slug)`

#### T-JS-D2 — AbilitiesManager.jsx: remove override branch
- Remove `view.mode === 'override'` branch
- Edit branch: `<AbilityForm mode="edit" slug={view.slug} abilityData={view.ability} />`

---

### Phase E — JavaScript: AbilityForm (largest change)

#### T-JS-E1 — Props, useEffect, derived state
- Remove `id` prop; add `slug: string`, `abilityData: object|undefined` props
- `useEffect`: on `mode === 'edit'` and `slug` defined — `dispatch.setSaved(abilityData)` (if abilityData) then `dispatch.fetchAbility(slug)`. On fetch 404 error: `dispatch.setView({ mode: 'list' })` + display error notice.
- Derived state: `isCreate`, `isEdit`, `isNonDb = Boolean(savedAbility?.source && 'db' !== savedAbility.source)`; remove `isOverride`

#### T-JS-E2 — handleSave, handleDelete, handleClearOverrides
- Create mode: set `data.status = 'publish'` when `!forceDraft && !data.status`; after success `dispatch.setView({ mode: 'edit', slug: ability.ability_slug, ability })`
- Edit mode: build `payload` — if `isNonDb`, include only label/desc/cat/site_allowed/show_in_rest/show_in_mcp/mcp_type/mcp_servers/readonly/destructive/idempotent; else send full `data`; call `dispatch.updateAbility(slug, payload)`
- Remove override mode block
- `handleDelete`: `dispatch.deleteAbility(slug)`
- `handleClearOverrides`: `dispatch.clearOverrides(slug)`

#### T-JS-E3 — Section visibility and required validation
- `isOverride` references → `isNonDb` (all occurrences)
- Callback section and Schema section: `{!isNonDb && ...}`
- Auto-register toggle: `{!isNonDb && ...}`
- Site Permission section: `{isNonDb && ...}`
- `show_in_rest` TriStateSelect: `{isNonDb && ...}`
- Required validation: skip `validateRequiredFields` when `isNonDb` in edit mode
- `hasRequiredErrors`: false when `isNonDb && isEdit`
- Blur validators: add `!isNonDb` guard

#### T-JS-E4 — UI text, slug field, LockedCard, provider info row, sidebar
- Page title: remove override branch; all edit shows "Edit Ability"
- Subtitle: show non-db-only info text when `isNonDb && isEdit`
- Save button: "✓ Save Changes" for all edit modes (remove "✓ Save Overrides")
- Slug `readOnly`: `{!isCreate}` (was `{isEdit}`)
- Remove "⚠ Changing the slug" warning
- Add "Once saved, this slug cannot be changed." (shown `{isCreate}`)
- Remove LockedCard component and its render block
- Add provider info row: `{isNonDb && savedAbility && <div className="fr provider-info-row">...</div>}`
- Sidebar: Update box shows for `{!isCreate}` (not just isEdit). Delete link: `{!isNonDb && ...}`. Add Clear All Overrides: `{isNonDb && has_override && ...}` (guard on `savedAbility?.has_override`).
- Activity sidebar: `{!isCreate && savedAbility && savedAbility.created_at && ...}` (was `{isEdit && savedAbility && ...}`)
- Preview sidebar Callback row: `{!isNonDb && ...}` (was `{!isOverride && ...}`)

---

### Phase F — Tests and Quality Gates

#### T-TEST-F1 — PHPUnit: Write + Read Controller slug route tests
- Test: `PUT /abilities/core%2Fget-user-info` creates override for first-time non-db ability
- Test: `PUT /abilities/core%2Fget-user-info` updates existing override
- Test: `DELETE /abilities/{slug}` on db-source ability succeeds
- Test: `DELETE /abilities/{slug}` on non-db ability returns 403
- Test: `GET /abilities/categories` still returns categories (not matched by slug route)

#### T-TEST-F2 — Jest: store and AbilityForm slug migration
- Existing Jest tests for `validateRequiredFields` must still pass
- New test: `bulkDeleteAbilities` with mixed selection is blocked
- New test: `PATCH_ABILITY` matches by `ability_slug` not `id`

#### T-TEST-F3 — Quality gates
- `vendor/bin/phpcs --standard=WordPress includes/Modules/Abilities/Rest/ includes/Utilities/AcrossAI_Ability_Merger.php includes/Utilities/AcrossAI_Abilities_Sanitizer.php includes/Utilities/AcrossAI_Abilities_Formatter.php`
- `vendor/bin/phpstan analyse`
- `npm run lint:js`
- `nvm use 20 && npm run build`
- `npm run validate-packages`

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| No new PHP classes | All logic fits in existing controllers and utilities |
| Category registration order change | Hard requirement — `(?P<slug>[^/]+)` would swallow `/abilities/categories` |
| `save_override()` for upsert | Already implements INSERT-or-UPDATE semantics; no new DB method needed |
| `AcrossAI_Ability_Source_Detector::detect($registry)` | Canonical source detection for upsert path; already exists |
| `has_override` based on field values, not row existence | Q4 answer — Clear All Overrides must hide the button |
| `created_at` propagated through merge() | Q5 answer — Activity sidebar guard requires non-null `created_at` |
| `_registry` added to format_merged_ability() | Required for TriStateSelect "Inherit" rendering in JS |
| Publish default (not draft) | Q3 answer and US3 — primary Add button produces published ability |
| Bulk mixed selection blocks entire operation | Q1 answer — fail-fast with warning rather than partial delete |

---

## Risk Register

| Risk | Likelihood | Mitigation |
|---|---|---|
| Category route collision | HIGH (without T-PHP-B1) | T-PHP-B1 is the first PHP task; manual test validates |
| AbilityForm.jsx tab depth mismatch | MEDIUM | BUG-ABILITYFORM-JSX-MIXED-DEPTHS — verify whitespace before each str_replace |
| PHP `phpcbf` tabs vs spaces | MEDIUM | BUG-PHPCBF-TABS — use `\t` in Python edit scripts |
| Create route broken by slug migration | LOW | Slug route only replaces `(?P<id>\d+)`; create `POST /abilities` is untouched |
| PHPStan nullable property access | MEDIUM | All `$override->{$field}` accesses are guarded by `null !== $override` |

