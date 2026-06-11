---
description: "Task list for Feature 031 — Library Category/Slug Rebrand"
---

# Tasks: Library Category/Slug Rebrand (Feature 031)

**Input**: Design documents from `specs/031-library-category-slug-rebrand/`
**Prerequisites**: plan.md ✅, spec.md ✅, security-constraints.md ✅

**Tests**: No new PHPUnit or Jest tests — feature is a pure rename with zero new logic.
Existing test suites remain valid after field name updates. PHPUnit fixture scan in T010.

**Organization**: PHP rename (foundational) → JS rename + display (US1) → config compat
verification (US2) → contract breakage verification (US3) → quality gate.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel with other tasks in the same phase (different files, no shared deps)
- **[US1/US2/US3]**: Which user story this task delivers

---

## Phase 1: Foundational — PHP Refactor

**Purpose**: Refactor PHP layer so definitions emit `category`/`slug`/`name` keys. Blocks
Phases 2–4; JS reads field names from the PHP payload.

**⚠️ CRITICAL**: No US1/US2 JS work can begin until this phase is complete.

- [x] T001 [P] In `includes/Modules/Library/Ability_Definition.php`: remove the four abstract grouping methods (`category()`, `category_label()`, `slug()`, `slug_label()`); update `push_definition()` to derive all Library fields from `ability()` — `category` and `category_label` from `$args['category']`, `slug` and `name` from `$spec['name']`, `slug_label` from `$args['label']`. Subclasses only need to implement `ability()`.
- [x] T002 [P] In `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`: update `REQUIRED_FIELDS` constant (replace `main_key`, `main_key_label`, `sub_key`, `sub_key_label` with `category`, `category_label`, `slug`, `slug_label`); rename local vars `$main_key`/`$sub_key` to `$category`/`$slug` in `validate_and_normalize()`; rename all four output array keys; note in docblock that `top-level category` and `args['category']` are now the same value (both sourced from the ability spec)
- [x] T003 [P] In `includes/Modules/Library/AcrossAI_Ability_Library_Processor.php`: rename `$main_key` → `$category` and `$sub_key` → `$slug` in `is_permitted()`; update all `$config[$main_key]` reads to `$config[$category]`; update `$entry['sub_keys'][$sub_key]` to `$entry['sub_keys'][$slug]` (note: `sub_keys` map key intentionally preserved); update FR-013–FR-017 docblock references from "main_key absent" to "category absent", "sub_key absent" to "slug absent"
- [x] T004 [P] In `includes/Modules/Library/AcrossAI_Ability_Library_Config.php`: rename `sanitize_entry()` docblock from "Sanitizes a single main_key entry." to "Sanitizes a single category entry."; optionally add `const MAX_SLUGS = self::MAX_SUB_KEYS;` for forward-compat naming (keep `MAX_SUB_KEYS` unchanged)

**Checkpoint**: PHP payload emitted by `AcrossAI_Ability_Library_Registry::get_definitions()` now contains `category`/`category_label`/`slug`/`slug_label`/`name` keys. The Processor reads renamed keys correctly. On-disk `sub_keys` wire format preserved.

---

## Phase 2: User Story 1 — JS Rename + Ability Name Display

**Goal**: Library admin page groups cards by `category`/`categoryLabel` and displays each ability's `name` as the per-row checkbox label (with `slugLabel` fallback).

**Independent Test**: Navigate to `wp-admin → Abilities Manager → Library`. Switch any card to Specific mode. Each checkbox must show the ability's registered `name` (e.g. `acrossai-sre/transient-flush`) rather than a raw key string. Verify no browser console errors.

- [x] T005 [P] [US1] In `src/js/ability-library/components/LibraryPage.js`: update `groupDefinitions()` — replace destructure `{main_key: mainKey, main_key_label: mainKeyLabel, sub_key: subKey, sub_key_label: subKeyLabel}` with `{category, category_label: categoryLabel, slug, slug_label: slugLabel, name}`; update `map.set()` group shape from `{id: mainKey, mainKey, mainKeyLabel, subKeys: []}` to `{id: category, category, categoryLabel, slugs: []}`; update sub-entry from `{subKey, subKeyLabel}` to `{slug, slugLabel, name}`; rename `handleChange(mainKey, ...)` to `handleChange(category, ...)`; update `key={item.mainKey}` to `key={item.category}`; update `groupDefinitions()` docblock from "by main_key" to "by category"
- [x] T006 [P] [US1] In `src/js/ability-library/components/LibraryCard.js`: rename destructure `{mainKey, mainKeyLabel, subKeys}` to `{category, categoryLabel, slugs}`; update `config[mainKey]` to `config[category]`; rename `subKeysConfig` to `slugsConfig`; update `onChange(mainKey, ...)` to `onChange(category, ...)`; update ToggleControl label from `<strong>{mainKeyLabel}</strong>` to `<strong>{categoryLabel}</strong>`; update Specific-mode guard from `subKeys.length > 0` to `slugs.length > 0`; update `.map(({ subKey, subKeyLabel })` to `.map(({ slug, slugLabel, name })`; change `label={subKeyLabel}` to `label={slugLabel || name}` (slugLabel is primary — human-readable; name is machine fallback); change `key={subKey}` to `key={slug}`; change `subKeysConfig[subKey]` to `slugsConfig[slug]`; **rename `<div className="acrossai-library-card__sub-keys">` to `<div className="acrossai-library-card__slugs">`** (unconditional — regardless of SCSS); keep `update({ sub_keys: { ...slugsConfig, [slug]: value } })` unchanged (on-disk wire key preserved); update `@param` docblock from "mainKey, mainKeyLabel, subKeys" to "category, categoryLabel, slugs"
- [x] T007 [P] [US1] In `src/js/ability-library/api.js`: update `@return` docblock from "keyed by main_key" to "keyed by category"; update `@param config` docblock from "keyed by main_key" to "keyed by category" (docblock-only, no behaviour change)

**Checkpoint**: Library page renders `categoryLabel` card titles. Specific mode shows `name` (or `slugLabel` fallback) per ability row. No browser console errors.

---

## Phase 3: User Story 2 — Config Backward Compat Verification

**Goal**: Saved `acrossai_library_config` option written by the pre-rename plugin loads and applies correctly post-rename — zero configuration loss.

**Independent Test**: Using `wp option get acrossai_library_config --format=json` (or via admin UI), confirm the saved option is identical before and after the feature deploys. POST a pre-rename config body to the Library config REST endpoint and confirm HTTP 200.

- [x] T008 [US2] In `admin/Partials/LibraryMenu.php`: grep for `main_key` and `sub_key` strings; update any comment references found; confirm no code changes are needed (data flows through Registry automatically)
- [x] T009 [US2] In `src/scss/`: grep for CSS selector `.acrossai-library-card__sub-keys`; if found, rename to `.acrossai-library-card__slugs` in the SCSS file only (the JS className was already renamed in T006; if not found, no SCSS action needed)
- [x] T010 [US2] In `tests/phpunit/`: grep for `main_key` and `sub_key` as array keys in Library-related test fixtures; update any found definition fixture arrays to use the new field names; no new test logic needed

**Checkpoint**: `wp option get acrossai_library_config --format=json` shows identical shape before and after. REST endpoint accepts pre-rename config bodies and returns HTTP 200.

---

## Phase 4: User Story 3 — Contract Breakage Verification

**Goal**: An external add-on subclassing `Ability_Definition` with the old method names produces a clear, actionable PHP fatal (abstract method not implemented) — no silent failure.

**Independent Test**: Confirm `Ability_Definition.php` declares only `ability()` as `abstract protected` — no `category()`, `category_label()`, `slug()`, `slug_label()` abstract methods exist. External subclasses (e.g. `acrossai-core-abilities`) with old `main_key()`/`sub_key()` methods load without errors.

- [x] T011 [US3] Verify `includes/Modules/Library/Ability_Definition.php` has only `ability()` as an abstract method (no `category()`, `category_label()`, `slug()`, `slug_label()` abstract methods). Confirm external subclasses (e.g. `acrossai-core-abilities`) that still implement old `main_key()`/`sub_key()` methods load without PHP fatal errors — PHP only errors on missing abstract implementations, not on extra methods.

**Checkpoint**: US3 is satisfied by T001. This task is a post-rename review, not an implementation task.

---

## Phase 5: Quality Gate

**Purpose**: All quality gates must pass before the feature is considered complete (§VII Definition of Done).

- [x] T012 [P] Residual grep: run `grep -rn "main_key\|sub_key\|mainKey\|subKey\|MainKey\|SubKey" includes/Modules/Library/ src/js/ability-library/` and confirm zero matches (excluding the intentional `sub_keys` map key and any updated comments); explicitly verify `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` — update any docblock occurrences of "main_key"/"sub_key" found; fix any other stragglers
- [x] T013 Run `composer dump-autoload` from plugin root; verify exit code 0 with no warnings (no class renames → autoloader output unchanged)
- [x] T014 [P] Run `composer phpcs` — zero PHPCS/WPCS errors for all modified PHP files (`Ability_Definition.php`, `AcrossAI_Ability_Library_Registry.php`, `AcrossAI_Ability_Library_Config.php`, `AcrossAI_Ability_Library_Processor.php`)
- [x] T015 [P] Run `composer phpstan` (level 8) — zero errors for all modified PHP files; pay attention to `validate_and_normalize()` return array shape annotations
- [x] T016 Run `npm run build` — confirm clean build and `build/js/ability-library.js` artifact regenerates without errors
- [x] T017 [P] Run ESLint (`npm run lint:js` or equivalent) — zero errors for `LibraryPage.js`, `LibraryCard.js`, `api.js`
- [x] T019 [P] In `src/js/ability-library/components/LibraryPage.js`: remove debounce — replace 1000ms `setTimeout` wrapper around `saveConfig()` with a direct call so config saves fire instantly on every toggle/checkbox change. Remove `debounceTimer` ref.
- [x] T020 [P] In `src/js/ability-library/components/LibraryPage.js`: remove "Saving…" spinner — delete `isSaving` state, `Spinner` import, and the `{isSaving && ...}` JSX block to eliminate the layout-shift flash caused by the transient saving indicator.
- [x] T018 Manual smoke test: (a) load `wp-admin → Abilities Manager → Library`; (b) confirm cards show `categoryLabel` titles; (c) switch a card to Specific mode — confirm each row shows `name` (or `slugLabel` fallback); (d) toggle a row checkbox and reload — confirm saved state persists; (e) check no browser console errors from plugin scripts; (f) **SC-031-01**: confirm no `dangerouslySetInnerHTML` exists in `LibraryCard.js` for label fields (grep the built file or source)

---

## Dependencies & Execution Order

```
Phase 1 (T001–T004): Foundational PHP rename — parallel-safe; start immediately
                      ↓
Phase 2 (T005–T007): US1 JS rename — parallel-safe; requires Phase 1 complete
Phase 3 (T008–T010): US2 verification — requires Phase 1 complete; parallel with Phase 2
                      ↓
Phase 4 (T011):      US3 contract verification — requires T001 complete
                      ↓
Phase 5 (T012–T018): Quality gate — requires all phases complete
```

Phase 2 and Phase 3 can run simultaneously after Phase 1 completes.
T009 (SCSS rename) must be synchronized with T006 (LibraryCard className) if the CSS class exists.

### Parallel Opportunities

```bash
# Phase 1 — all 4 tasks touch different files:
T001  includes/Modules/Library/Ability_Definition.php
T002  includes/Modules/Library/AcrossAI_Ability_Library_Registry.php
T003  includes/Modules/Library/AcrossAI_Ability_Library_Processor.php
T004  includes/Modules/Library/AcrossAI_Ability_Library_Config.php

# Phase 2 — all 3 tasks touch different files:
T005  src/js/ability-library/components/LibraryPage.js
T006  src/js/ability-library/components/LibraryCard.js
T007  src/js/ability-library/api.js

# Phase 5 — quality gate:
T014  phpcs      (parallel with T015)
T015  phpstan    (parallel with T014 and T017)
T017  eslint     (parallel with T014 and T015)
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Complete Phase 1: Foundational PHP rename (T001–T004)
2. Complete Phase 2: US1 JS rename + name display (T005–T007)
3. **Validate**: Smoke test — Library page renders `categoryLabel` + ability `name` per row
4. Complete Phase 3 + 4 for US2/US3 verification
5. Complete Phase 5: Quality gate

### Notes

- No new PHP classes, REST routes, JS bundles, or DB tables — pure rename
- T004 (Config.php) is docblock-only — lowest risk; complete last within Phase 1
- T009 (SCSS) is conditional — grep first; only act if the class selector exists
- T010 (PHPUnit fixtures) expected to be no-op — Library PHPUnit grep returned zero matches
- T011 is a review task; no code to write beyond confirming T001 output is correct
- The `sub_keys` key in `update({ sub_keys: ... })` (LibraryCard T006) MUST NOT be renamed — it is the on-disk wire format key preserved for US2 backward compatibility
