# Tasks: Feature 014 — Unified Ability Editing, Slug-Based Routing, Publish Default

**Input**: Design documents from `specs/014-unify-edit-slug-routing/`
**Plan**: [plan.md](./plan.md) | **Spec**: [spec.md](./spec.md) | **Memory**: [memory-synthesis.md](./memory-synthesis.md) | **Security**: [security-constraints.md](./security-constraints.md)
**Branch**: `014-unify-edit-override-slug-routing`

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependency on incomplete tasks)
- **[US1]**: Single Edit button + pre-populated form for all sources
- **[US2]**: Override label/description/category for non-custom abilities
- **[US3]**: Add Ability publishes by default
- **[US4]**: Slug permanently locked after creation
- **[US5]**: Bulk operations use slug as identifier

## Key Constraints (from memory-synthesis.md + security-constraints.md)

- **HARD — Category route first**: `AcrossAI_Abilities_Rest_Controller::register_routes()` must call `Category_Controller::register_routes()` first. Failure to do so makes `/abilities/categories` unmatchable after slug pattern migration.
- **SEC-01**: New slug route arg must declare both `sanitize_callback: sanitize_ability_slug(rawurldecode($slug))` and `validate_callback: is_string($slug) && '' !== trim($slug)`.
- **SEC-ADVISORY-01 (MEDIUM)**: Delete authorization MUST use `$existing->source` (DB row value). Never derive source from registry for the delete guard.
- **SEC-ADVISORY-02 (LOW)**: Upsert body params MUST pass sanitize → `strip_protected_fields_for_non_db()` → `save_override()` in that order — same as the update path.
- **SEC-GUARDRAIL-01 (LOW)**: `AcrossAI_Ability_Override_Processor` is OUT OF SCOPE — only `::bust_cache()` may be called. No methods added or modified.
- **SEC-04**: Strict `=== / !==` comparisons throughout. `empty()` on strings is forbidden.
- **DEC-UTILITY-STATIC-ONLY**: Merger, Sanitizer, Formatter are static-only. New Merger helper must be `private static`.
- **DEC-DESIGN-OVERRIDES-DATAVIEWS (accepted deviation)**: AbilityForm.jsx uses `.panel`/`.sect` custom HTML — NOT DataForm. Do not replace with DataForm.
- **BUG-ABILITYFORM-JSX-MIXED-DEPTHS**: AbilityForm.jsx has inconsistent tab depths by section. Verify exact whitespace before any string replacement.
- **BUG-PHPCBF-TABS**: PHP file edits must use `\t` not spaces.
- **DEC-NODE-20-BUILD-REQUIRED**: All JS build commands must use `nvm use 20 && npm run build`.

---

## Phase 1: Setup

**Purpose**: Verify environment and confirm all prerequisites before any file changes.

- [ ] T001 Verify build environment: run `nvm use 20 && node --version` (expect v20.x), `git branch` (expect `014-unify-edit-override-slug-routing`), `./vendor/bin/phpcs --version`, `./vendor/bin/phpstan --version` — all must succeed with no errors

**Checkpoint**: Node 20 active, on correct branch, PHPCS and PHPStan accessible.

---

## Phase 2: PHP Utilities — Merger, Sanitizer, Formatter

**Purpose**: Fix the three utility-level data bugs that block all downstream features (US2 label/desc/cat overrides, US1 Activity sidebar, `has_override` semantics). These are pure data-transformation classes with no UI dependency. Complete before any REST controller changes.

**⚠️ CRITICAL**: Phases 3 (REST controllers) depends on `$overridable_fields` including label/desc/cat. Complete and verify Phase 2 before Phase 3.

- [ ] T002 [US2] Add `'label'`, `'description'`, `'category'` to `$overridable_fields` in `includes/Utilities/AcrossAI_Ability_Merger.php` — prepend these three to the existing array (`site_allowed`, `readonly`, etc.) so they appear first. Use tabs (`\t`) not spaces (BUG-PHPCBF-TABS).

- [ ] T003 [US2] Update `merge()` loop guard in `includes/Utilities/AcrossAI_Ability_Merger.php`: change the condition that applies an override value from `null !== $override->{$field}` to `null !== $override->{$field} && '' !== (string) $override->{$field}`. This prevents empty-string overrides from clobbering registry values (SEC-04: strict comparison, no `empty()`).

- [ ] T004 [US2] Add `private static function has_any_non_null_field( object $override ): bool` helper in `includes/Utilities/AcrossAI_Ability_Merger.php` after `merge()`: iterates `self::$overridable_fields`, returns `true` if any field on `$override` is non-null and non-empty-string (`null !== $override->{$field} && '' !== (string) $override->{$field}`). Then update `has_override` computation in `merge()` from `null !== $override` to `null !== $override && self::has_any_non_null_field( $override )`. Use tabs (BUG-PHPCBF-TABS). PHPStan: parameter must be typed as `object` and any dynamic property access needs a `@phpstan-ignore` or cast.

- [ ] T005 [US1] Add `created_at` and `created_by` propagation to `merge()` in `includes/Utilities/AcrossAI_Ability_Merger.php`: after computing `$has_override`, add `$result['created_at'] = $has_override ? $override->created_at : null;` and `$result['created_by'] = $has_override ? $override->created_by : null;`. This feeds the Activity sidebar guard (FR-006 — hide sidebar when `savedAbility?.created_at` is null).

- [ ] T006 [P] [US2] Remove `'label'`, `'description'`, `'category'` from the `$protected` array in `strip_protected_fields_for_non_db()` in `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`. Keep: `callback_type`, `callback_config`, `input_schema`, `output_schema`, `status`, `ability_slug`, `slug_suffix`, `source`. Verify no existing PHPUnit test for this method fails.

- [ ] T007 [US1][US2] Fix `format_merged_ability()` in `includes/Utilities/AcrossAI_Abilities_Formatter.php` — three changes: (a) add `'_registry' => $merged['_registry'] ?? null` to the return array; (b) change `'created_at' => null` to `'created_at' => self::to_iso8601( $merged['created_at'] ?? null )`; (c) change `'created_by' => null` to `'created_by' => $merged['created_by'] ?? null`. Do NOT change `'editable' => false` — frontend uses `source !== 'db'` for `isNonDb` detection, not this field.

**Checkpoint**: Run `./vendor/bin/phpcs includes/Utilities/AcrossAI_Ability_Merger.php includes/Utilities/AcrossAI_Abilities_Sanitizer.php includes/Utilities/AcrossAI_Abilities_Formatter.php` and `./vendor/bin/phpstan analyse` — both must pass zero errors before continuing.

---

## Phase 3: PHP REST Controllers — Orchestrator, Write, Read

**Purpose**: Migrate single-ability routes from `(?P<id>\d+)` to `(?P<slug>[^/]+)` and implement slug-based lookup logic. The orchestrator reorder (T008) must be the very first change in this phase — it is the hard architectural requirement.

**⚠️ CRITICAL**: T008 (orchestrator reorder) MUST be done before T009–T010. Without it, after the slug pattern lands, `GET /abilities/categories` will match the slug route and return a 404.

- [ ] T008 [US1][US5] Fix registration order in `AcrossAI_Abilities_Rest_Controller::register_routes()` (`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php`): reorder calls so `AcrossAI_Abilities_Category_Controller::instance()->register_routes()` is called FIRST, then Write, then Read, then Exposure. Manual verify: `GET /wp-json/acrossai-abilities-manager/v1/abilities/categories` still returns categories (not 404) after this change.

- [ ] T009 [US1][US2][US5] Update `AcrossAI_Abilities_Write_Controller::register_routes()` in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`: change single-ability route from `/abilities/(?P<id>\d+)` to `/abilities/(?P<slug>[^/]+)`. Add slug arg definition with: `'type' => 'string'`, `'required' => true`, `'sanitize_callback' => function($slug) { return AcrossAI_Abilities_Sanitizer::sanitize_ability_slug( rawurldecode( (string) $slug ) ); }`, `'validate_callback' => function($slug) { return is_string($slug) && '' !== trim($slug); }` (SEC-01).

- [ ] T010 [US1][US2][US5] Rewrite `update_ability()` in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`:
  1. `$slug = $request->get_param( 'slug' );` — slug already sanitized by `sanitize_callback`
  2. Exclusion check before any DB lookup (DEC-EARLY-404-REST-CHECK) — if slug is excluded, return 403
  3. `$existing = $this->db_query->get_ability_by_slug( $slug );`
  4. **If found (DB row exists)**: sanitize body params → `strip_protected_fields_for_non_db()` if `source !== 'db'` → run `validate_ability()` → update by `$existing->id` → re-read → format via `format_for_response()` or `format_merged_ability()`
  5. **If not found (first-time non-db upsert)**: call `wp_get_ability( $slug )` — if null, return 404; if found, sanitize body params → `strip_protected_fields_for_non_db()` → `save_override( $slug, $fields )` → re-read → `merge()` → `format_merged_ability()`. **SEC-ADVISORY-02**: sanitize → strip_protected → save_override sequence is mandatory in this branch too.
  6. Fire `do_action( 'acrossai_abilities_after_update', $ability_data );` on sanitized data only (SEC-02)
  7. Call `AcrossAI_Ability_Override_Processor::bust_cache()` — **SEC-GUARDRAIL-01: only this call; do NOT modify any other method in Override Processor**

- [ ] T011 [US5] Rewrite `delete_ability()` in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php`:
  1. `$slug = $request->get_param( 'slug' );`
  2. `$existing = $this->db_query->get_ability_by_slug( $slug );`
  3. If `null === $existing`: return `new \WP_Error( 'ability_not_found', ..., array( 'status' => 404 ) )`
  4. **SEC-ADVISORY-01**: Authorization check MUST use `$existing->source` (the DB row value) — NOT a registry-derived source. If `'db' !== $existing->source`: return `new \WP_Error( 'cannot_delete_non_custom', ..., array( 'status' => 403 ) )`
  5. `do_action( 'acrossai_abilities_before_delete', $existing );`
  6. `$this->db_query->delete_ability( $existing->id );`
  7. `do_action( 'acrossai_abilities_after_delete', $slug );`
  8. Call `AcrossAI_Ability_Override_Processor::bust_cache()` — **SEC-GUARDRAIL-01: call only**
  9. Return `new \WP_REST_Response( array( 'deleted' => true, 'slug' => $slug ), 200 );`

- [ ] T012 [US1][US2] Update `AcrossAI_Abilities_Read_Controller::register_routes()` and rewrite `get_ability()` in `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php`:
  - Route: same slug arg schema as T009 (SEC-01)
  - `get_ability()`: `$slug = $request->get_param( 'slug' );` → `$row = $this->db_query->get_ability_by_slug( $slug );` → if found and `'db' === $row->source`: return `format_for_response( $row )`; if found and `source !== 'db'`: fetch `wp_get_ability( $slug )` → normalize → `merge( $normalized, $row )` → `format_merged_ability()`; if not in DB: `wp_get_ability( $slug )` → if found: normalize → `merge( $normalized, null )` → `format_merged_ability()`; if null: return `new \WP_Error( 'ability_not_found', ..., array( 'status' => 404 ) )`

**Checkpoint**: Run `./vendor/bin/phpcs includes/Modules/Abilities/Rest/` and `./vendor/bin/phpstan analyse` — zero errors. Manual: `GET /wp-json/acrossai-abilities-manager/v1/abilities/categories` must return categories (not 404).

---

## Phase 4: JavaScript — API Client + Store

**Purpose**: Migrate all JS API calls and store operations from integer `id` to slug. These are pure logic changes with no UI rendering dependency. T014 (store) depends on T013 (client) being slug-based first.

- [ ] T013 [US5] Update slug-based function signatures in `src/js/abilities/api/client.js`:
  - `getAbility(id)` → `getAbility(slug)`: path becomes `` `${BASE}/${encodeURIComponent(slug)}` ``
  - `updateAbility(id, data)` → `updateAbility(slug, data)`: same slug path, method POST/PUT (keep same HTTP method as current)
  - `deleteAbility(id)` → `deleteAbility(slug)`: same slug path, method DELETE
  - Leave `createAbility`, `getAbilities`, `getCategories` unchanged

- [ ] T014 [US5] Update slug-keyed reducers and thunks in `src/js/abilities/store/index.js`:
  - `PATCH_ABILITY` reducer: change `a.id === action.id` to `a.ability_slug === action.slug`
  - `REMOVE_ABILITY` reducer: change `a.id !== action.id` to `a.ability_slug !== action.slug`
  - `fetchAbility(id)` → `fetchAbility(slug)`: calls `api.getAbility(slug)`, dispatches with slug
  - `updateAbility(id, data)` → `updateAbility(slug, data)`: calls `api.updateAbility(slug, data)`; on success `dispatch({ type: PATCH_ABILITY, slug, patch: ability })`
  - `deleteAbility(id)` → `deleteAbility(slug)`: calls `api.deleteAbility(slug)`; on success `dispatch({ type: REMOVE_ABILITY, slug })`
  - `clearOverrides(id)` → `clearOverrides(slug)`: calls `api.deleteOverride(slug)` (DELETE `/abilities/{slug}/override`) — deletes the override row, returns fresh registry-merged data (FR-012). The null-update approach is replaced.
  - `bulkDeleteAbilities(ids)` → `bulkDeleteAbilities(slugs)`: map over slugs array
  - `bulkUpdateStatus(ids, status)` → `bulkUpdateStatus(slugs, status)`: map over slugs array

**Checkpoint**: `nvm use 20 && npm run lint:js` — zero errors on changed files.

---

## Phase 5: JavaScript — AbilitiesList + AbilitiesManager

**Purpose**: Remove the Override button, unify to a single Edit action, and migrate selection/bulk ops from integer id to slug. T016 (Manager) depends on T015 (List) dispatch shape.

- [ ] T015 [US1][US5] Update `src/js/abilities/components/AbilitiesList.jsx` — list-level changes:
  - Remove Override button; keep Edit button for all sources (FR-001)
  - Edit button `onClick`: `dispatch.setView({ mode: 'edit', slug: item.ability_slug, ability: item })`
  - `selected` Set: key by `ability_slug` (string), not stringified integer id
  - `allDbSlugs`: `new Set(dbAbilities.map(a => a.ability_slug))`
  - `toggleOne(slug)`: add/remove by slug string
  - Inline status dropdown `handleStatusDropdown`: `dispatch.updateAbility(item.ability_slug, { status })`
  - Delete button: `dispatch.deleteAbility(item.ability_slug)`
  - `handleBulkApply`: before proceeding with bulk delete, check if any slug in `selected` maps to a non-db ability — if any match: show error notice "Your selection includes abilities that cannot be deleted. Remove non-custom abilities from your selection and try again." and abort; otherwise proceed with `dispatch.bulkDeleteAbilities(slugs)` or `dispatch.bulkUpdateStatus(slugs, status)` as appropriate (FR-009)

- [ ] T016 [US1] Update `src/js/abilities/components/AbilitiesManager.jsx` — remove override branch:
  - Remove `view.mode === 'override'` branch entirely (FR-001)
  - Edit branch: `<AbilityForm mode="edit" slug={view.slug} abilityData={view.ability} />`

**Checkpoint**: `nvm use 20 && npm run lint:js` — zero errors on changed files.

---

## Phase 6: JavaScript — AbilityForm (Largest Change)

**Purpose**: Overhaul AbilityForm.jsx to support unified edit, slug-based API calls, non-custom visibility rules, and publish-default create. Split into four subtask groups matching plan phases E1–E4.

**⚠️ WARNING — BUG-ABILITYFORM-JSX-MIXED-DEPTHS**: AbilityForm.jsx has inconsistent indentation by section. Before every string replacement, verify the exact whitespace at that location. Use `grep -n "searchString" src/js/abilities/components/AbilityForm.jsx` to confirm the exact line and leading whitespace before modifying.

### Phase 6a — Props, useEffect, Derived State (T-JS-E1)

- [x] T017 [US1][US2] Update props in `src/js/abilities/components/AbilityForm.jsx`: remove `id` prop; add `slug: string` and `abilityData: object|undefined` props. Update prop destructuring and PropTypes.

- [x] T018 [US1][US2] Update `useEffect` in `src/js/abilities/components/AbilityForm.jsx`: on `mode === 'edit'` and `slug` defined — call `dispatch.setSaved(abilityData)` first (if abilityData present, seeds form immediately, preventing blank flash — FR-002 SC-002), then call `dispatch.fetchAbility(slug)`. Add error handling for 404 response: on fetch 404, call `dispatch.setView({ mode: 'list' })` and display dismissible error notice "Ability not found. It may have been removed or the plugin deactivated." (FR-002).

- [x] T019 [US1][US2] Update derived state in `src/js/abilities/components/AbilityForm.jsx`: add `isNonDb = Boolean(savedAbility?.source && 'db' !== savedAbility.source)`; remove `isOverride`. Keep `isCreate`, `isEdit` as-is.

### Phase 6b — Save, Delete, ClearOverrides Handlers (T-JS-E2)

- [x] T020 [US3] Update `handleSave` create path in `src/js/abilities/components/AbilityForm.jsx`: set `data.status = 'publish'` when `!forceDraft && !data.status` before calling createAbility. After success, navigate to edit mode: `dispatch.setView({ mode: 'edit', slug: ability.ability_slug, ability })` (FR-010).

- [x] T021 [US1][US2] Update `handleSave` edit path in `src/js/abilities/components/AbilityForm.jsx`: if `isNonDb`, build payload with only overridable fields: `label`, `description`, `category`, `site_allowed`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `readonly`, `destructive`, `idempotent`; else send full `data`. Call `dispatch.updateAbility(slug, payload)`. Remove the override-mode block entirely.

- [x] T022 [US1][US2][US5] Update `handleDelete` and `handleClearOverrides` in `src/js/abilities/components/AbilityForm.jsx`:
  - `handleDelete`: call `dispatch.deleteAbility(slug)` (was `dispatch.deleteAbility(id)`)
  - `handleClearOverrides`: call `dispatch.clearOverrides(slug)` (was `dispatch.clearOverrides(id)`)

### Phase 6c — Section Visibility + Validation (T-JS-E3)

- [x] T023 [US2] Replace all `isOverride` references with `isNonDb` in `src/js/abilities/components/AbilityForm.jsx` for section visibility:
  - Callback section: `{!isNonDb && ...}` (FR-005)
  - Schema section: `{!isNonDb && ...}` (FR-005)
  - Auto-register toggle: `{!isNonDb && ...}` (FR-005)
  - Site Permission section: `{isNonDb && ...}` (was `{isOverride && ...}`)
  - `show_in_rest` TriStateSelect: `{isNonDb && ...}`
  - Preview sidebar Callback row: `{!isNonDb && ...}`

- [x] T024 [US2] Update required-field validation guards in `src/js/abilities/components/AbilityForm.jsx`: skip `validateRequiredFields` when `isNonDb && isEdit` (non-custom abilities have no required field constraints in edit mode — FR-003 allows empty label to fall back to registry). `hasRequiredErrors` must be `false` when `isNonDb && isEdit`. Blur validators for slug, label, description, category must add `!isNonDb` guard to prevent false errors on non-custom ability edit.

### Phase 6d — UI Text, Slug, LockedCard, Provider Info, Sidebar (T-JS-E4)

- [x] T025 [US1][US4] Update static text in `src/js/abilities/components/AbilityForm.jsx`:
  - Page title: remove override branch; all edit shows "Edit Ability" (FR-001 unification)
  - Save button: "✓ Save Changes" for all edit modes — remove "✓ Save Overrides" variant
  - Slug field `readOnly` attribute: change to `{!isCreate}` (was `{isEdit}`) (FR-011)
  - Remove "⚠ Changing the slug will break existing integrations." warning text entirely (FR-011)
  - Add "Once saved, this slug cannot be changed." note shown only when `{isCreate}` (FR-011)

- [x] T026 [US2] Remove LockedCard component and its render block from `src/js/abilities/components/AbilityForm.jsx` entirely (FR-002 — non-custom abilities now open with full form, not locked). Verify no import of LockedCard remains.

- [x] T027 [US2] Add provider info row in `src/js/abilities/components/AbilityForm.jsx` inside Identity section: `{isNonDb && savedAbility && (<div className="fr provider-info-row">Registered by {savedAbility._registry?.registered_by || savedAbility.source} <span className="source-badge">{savedAbility.source}</span></div>)}` (FR-006).

- [x] T028 [US1][US2][US5] Update sidebar guards in `src/js/abilities/components/AbilityForm.jsx`:
  - Update box: show for `{!isCreate}` (not only isEdit)
  - Delete link: `{!isNonDb && !isCreate && ...}` — only custom abilities can be deleted (FR-013)
  - Clear All Overrides button: `{isNonDb && savedAbility?.has_override && ...}` — visible only for non-custom abilities with at least one active override (FR-012)
  - Activity sidebar (created_at, updated_at, created_by): `{!isCreate && savedAbility && savedAbility.created_at && ...}` — hidden until first override record is saved (FR-006)

**Checkpoint**: `nvm use 20 && npm run lint:js` — zero errors. `nvm use 20 && npm run build` — exits clean.

---

## Phase 7: Tests + Quality Gates

**Purpose**: Ensure full test coverage for new slug-based paths and validate all quality gates pass.

- [x] T029 [P] [US1][US5] Write PHPUnit tests for Write Controller slug routes in `tests/phpunit/`:
  - `PUT /abilities/core%2Fget-user-info` on first-time non-db ability creates override record (upsert path)
  - `PUT /abilities/core%2Fget-user-info` on existing override row updates the record
  - `DELETE /abilities/{slug}` on `source=db` ability returns 200 and is removed
  - `DELETE /abilities/{slug}` on `source=plugin` ability returns 403
  - `GET /abilities/categories` still returns categories array (not 404) — validates orchestrator reorder

- [x] T030 [P] [US1][US2][US5] Write Jest tests for store and AbilityForm in `tests/jest/`:
  - `PATCH_ABILITY` reducer matches by `ability_slug`, not `id`
  - `bulkDeleteAbilities` with mixed selection (custom + non-custom slugs) is blocked and emits warning
  - `clearOverrides` payload includes `label: null`, `description: null`, `category: null`
  - Existing `validateRequiredFields` tests still pass
  - `fetchAbility` 404 causes `setView({ mode: 'list' })` dispatch

- [x] T031 PHPCS zero errors: `./vendor/bin/phpcs --standard=WordPress includes/Modules/Abilities/Rest/ includes/Utilities/AcrossAI_Ability_Merger.php includes/Utilities/AcrossAI_Abilities_Sanitizer.php includes/Utilities/AcrossAI_Abilities_Formatter.php`

- [x] T032 PHPStan level 8 zero errors: `./vendor/bin/phpstan analyse`

- [x] T033 JS quality gates: `nvm use 20 && npm run lint:js && npm run build && npm run validate-packages` — all must exit 0

**Checkpoint — Definition of Done (§VII)**:
- [ ] All 33 tasks checked
- [x] T031: PHPCS zero errors
- [x] T032: PHPStan level 8 zero errors
- [x] T033: ESLint zero, build clean, validate-packages pass
- [x] T029: PHPUnit tests green
- [x] T030: Jest tests green
- [ ] Security review complete (security-constraints.md satisfied)
- [ ] No new hook wiring in Main.php
- [ ] `AcrossAI_Ability_Override_Processor` unmodified (SEC-GUARDRAIL-01)

---

## FR → Task Traceability

| FR | Task(s) |
|---|---|
| FR-001 (single Edit button) | T015, T016, T025, T026 |
| FR-002 (pre-populated form, 404 nav) | T017, T018, T026 |
| FR-003 (label/desc/cat editable) | T002, T006, T010, T021 |
| FR-004 (saved overrides reflected) | T002–T007, T010, T012 |
| FR-005 (hide Callback/Schema/Auto-register) | T023 |
| FR-006 (provider info row, Activity sidebar guard) | T005, T007, T027, T028 |
| FR-007 (upsert on first save) | T010 (upsert branch) |
| FR-008 (slug-based API) | T009, T010, T011, T012, T013 |
| FR-009 (bulk slug-keyed, mixed-selection block) | T014, T015 |
| FR-010 (publish default) | T020 |
| FR-011 (slug locked) | T025 |
| FR-012 (Clear All Overrides) | T004, T014, T022, T028 |
| FR-013 (Delete only for custom) | T011 (403), T028 |
| FR-014 (merged read endpoint) | T012 |

---

## Architecture Guard Remediation Tasks (Post-Review)

| Task | Violation | Status |
|------|-----------|--------|
| RT-1 | V1/V2/V3: sanitize_callback rawurldecode + validate_callback | ✅ Done |
| RT-2 | V4: allDbIds ReferenceError → allDbSlugs | ✅ Done |
| RT-3 | V5: status:'publish' not set on create | ✅ Done |
| RT-4 | V6/V7: bust_cache after DB update path + after delete | ✅ Done |
| RT-5 | V8: protected slug exclusion check at top of update_ability() | ✅ Done |
| RT-6 | V9: after_update hook on non-db override upsert path | ✅ Done |
| RT-7 | V10: after_delete hook signature → single slug arg only | ✅ Done |
| RT-8 | V11/V13: Remove stale slug warning; fix heading dual-render | ✅ Done |
| RT-9 | V12: Pre-seed savedAbility from list row to prevent blank flash | ✅ Done |
| RT-10 | V14: DELETE returns 200 + body instead of 204 | ✅ Done |
| RT-11 | V15: Rename fetchAbility(id) → fetchAbility(slug) param | ✅ Done |
| RT-12 | Callback section for non-DB: show all 4 chips + "Keep as default" chip; remove locked single chip | ✅ Done |
| RT-13 | Default "Keep as default" selected when `draftAbility.callback_type` is null/undefined for non-DB | ✅ Done |
| RT-14 | PHP: remove `callback_type` + `callback_config` from `strip_protected_fields_for_non_db()`; add both to `$overridable_fields` in Merger | ✅ Done |
| RT-15 | Convert 6 annotation/visibility fields from dropdowns/toggles to TriChips: Show in MCP (null/true/false), MCP Type (null/"tool"/"resource"/"prompt"), Readonly/Destructive/Idempotent/Show in REST (null/true/false each); remove dead `ts2s`, `s2ts`, `TriStateSelect` | ✅ Done |
| RT-16 | FR-012 Clear All Overrides: replace null-update with `DELETE /abilities/{slug}/override` that deletes the override row; add `delete_override()` PHP handler + `deleteOverride(slug)` client func; `clearOverrides` store thunk calls `api.deleteOverride` | ✅ Done |
