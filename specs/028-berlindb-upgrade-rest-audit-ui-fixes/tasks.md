# Tasks: BerlinDB Upgrade, PHP 8.1 Minimum, REST Audit, Abilities UI Fixes

**Input**: Design documents from `/specs/028-berlindb-upgrade-rest-audit-ui-fixes/`
**Prerequisites**: plan.md ✅, spec.md ✅, memory-synthesis.md ✅

**Organization**: Tasks are grouped by change (CHANGE-1 through CHANGE-5). All five changes are independent and can be parallelised. CHANGE-1 is recommended first to confirm vendor/ compatibility before CI changes go live.

**Active Constraints**:
- PATTERN-CI-WORKFLOW-HARDENING — SHA-pin all `uses:` in phpunit.yml, `permissions: {}`, `timeout-minutes: 15`
- ARCH-PHPUNIT-BOOTSTRAP — ABSPATH must precede autoloader in tests/bootstrap.php; phpunit.xml.dist must exclude BerlinDB Table-loading test files
- DEC-TABLE-SOFT-SINGLETON — `AcrossAI_Abilities_Table` must retain no `private __construct` after BerlinDB v3 upgrade
- DEC-REVALIDATE-SECURITY-POST-UPGRADE — SEC-03, SEC-04, DEC-PERM-CB checks required after composer update

## Format: `[ID] [P?] [CHANGE-N] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[CHANGE-N]**: Which change this task belongs to (CHANGE-1 through CHANGE-5)
- Include exact file paths in descriptions

---

## Phase 0: Pre-flight Research

**Purpose**: Verify live codebase state matches the plan's assumptions before making any edits.

- [x] T001 Verify pre-flight facts: run `grep berlindb composer.json` (expect `^2.0`), `grep "Requires PHP" acrossai-abilities-manager.php README.txt` (expect `7.4`), `grep php_min_version AGENTS.md` (expect `"7.4"`), `grep testVersion .github/workflows/phpcompat.yml` (expect `7.4-`), `ls .github/workflows/phpunit.yml` (expect NOT FOUND), `grep -n "PHP 7" .specify/memory/CONSTITUTION.md` (expect line 117), `grep -n "tn-pages" src/js/abilities/components/AbilitiesList.jsx` (expect line ~578), `grep -n "tablenav-pages-below" src/scss/abilities/admin.scss` (expect line ~308)

**Checkpoint**: All pre-flight facts confirmed — proceed to implementation phases.

---

## Phase 1: CHANGE-1 — BerlinDB ^3.0.0 Upgrade (Priority: P1)

**Goal**: `composer install` resolves `berlindb/core 3.0.0` and `wpboilerplate/wpb-access-control v1.2.0` with no conflicts. PHPStan and PHPCS pass after the upgrade.

**Independent Test**: `composer install --no-dev` exits 0 and `berlindb/core 3.0.0` appears in the lock file.

### Implementation for CHANGE-1

- [x] T002 [CHANGE-1] Edit `composer.json` — in `repositories[]` add a second VCS entry after the existing `wpb-addons-page` entry: `{ "type": "vcs", "url": "https://github.com/WPBoilerplate/wpb-access-control" }`; in `require` bump `"wpboilerplate/wpb-access-control": "^1.2.0"` and `"berlindb/core": "^3.0.0"`; keep `"minimum-stability": "dev"` and all other constraints unchanged
- [x] T003 [CHANGE-1] Run `composer update wpboilerplate/wpb-access-control berlindb/core --with-all-dependencies` — confirm `berlindb/core 3.0.0` and `wpboilerplate/wpb-access-control v1.2.0` appear in the updated lock file
- [x] T004 [CHANGE-1] Post-upgrade security revalidation (DEC-REVALIDATE-SECURITY-POST-UPGRADE): run `grep -n "global" vendor/wpboilerplate/wpb-access-control/src/Database/Rule/RuleTable.php` (SEC-03: expect `$global = false` or absent), `grep -rn "===\|!==" vendor/wpboilerplate/wpb-access-control/src/` (SEC-04: strict operators present), `grep -n "wrap_permission_callback\|build_permission_callback" includes/Modules/` (DEC-PERM-CB: both methods intact), `grep -n "private.*__construct" includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php` (DEC-TABLE-SOFT-SINGLETON: no match expected), `grep -rn "fail.open\|admin_notices\|access_control_available" includes/Modules/Abilities/` (DEC-FAIL-OPEN-NOTICE: fail-open path and admin notice hook both present — SEC-028-01) — document all five results
- [x] T005 [CHANGE-1] Run quality gates: `vendor/bin/phpstan analyse --level=8 --error-format=github`, `vendor/bin/phpcs --standard=phpcs.xml.dist`, `vendor/bin/phpunit` — all three must exit 0 before proceeding

**Checkpoint**: `berlindb/core 3.0.0` in lock file, security revalidation documented, all three quality gates pass.

---

## Phase 2: CHANGE-2 — PHP 8.1 Minimum + PHPUnit Matrix CI (Priority: P2)

**Goal**: Every declaration site says `8.1`; a new `phpunit.yml` CI workflow runs PHPUnit on PHP 8.1–8.5 with `fail-fast: false`.

**Independent Test**: `grep "Requires PHP" acrossai-abilities-manager.php README.txt` returns `8.1` in both; `.github/workflows/phpunit.yml` exists with matrix `['8.1', '8.2', '8.3', '8.4', '8.5']`.

### Implementation for CHANGE-2 (nine atomic edits)

- [x] T006 [P] [CHANGE-2] Edit `composer.json` — change `"php": ">=7.4"` to `"php": ">=8.1"` (keep all other content unchanged)
- [x] T007 [P] [CHANGE-2] Edit `acrossai-abilities-manager.php` line 27 — change `* Requires PHP:      7.4` to `* Requires PHP:      8.1`
- [x] T008 [P] [CHANGE-2] Edit `README.txt` line 7 — change `Requires PHP: 7.4` to `Requires PHP: 8.1`
- [x] T009 [P] [CHANGE-2] Edit `AGENTS.md` line 12 — change `php_min_version: "7.4"` to `php_min_version: "8.1"`
- [x] T010 [P] [CHANGE-2] Edit `.github/workflows/phpcompat.yml` — change job name from `PHP 7.4+ Compatibility` to `PHP 8.1+ Compatibility`; change `--runtime-set testVersion 7.4-` to `--runtime-set testVersion 8.1-`
- [x] T011 [P] [CHANGE-2] Edit `.specify/memory/CONSTITUTION.md` line 117 — change `compatible with WordPress 6.9+ and PHP 7.4+` to `compatible with WordPress 6.9+ and PHP 8.1+`; prepend a new SYNC IMPACT block inside the HTML comment at the top of the file: `Version change: 1.4.5 → 1.4.6 / Modified sections: §II WordPress Standards Compliance — PHP minimum floor raised from 7.4 to 8.1 / Rationale: Feature 028 raises the declared PHP minimum to 8.1. PHP 7.4 reached end-of-life November 2022. phpunit/phpunit ^13.2 already requires PHP 8.2+, making the 7.4 declaration misleading. PHPUnit CI matrix now covers PHP 8.1–8.5. / Templates reviewed: plan-template.md ✅ reviewed — no outdated references, spec-template.md ✅ reviewed — no outdated references, tasks-template.md ✅ reviewed — no outdated references, checklist-template.md ✅ reviewed — no outdated references / Deferred TODOs: None`
- [x] T012 [P] [CHANGE-2] Edit `.agents/skills/wp-packages-strategy/SKILL.md` line 4 — change `PHP 7.4+` to `PHP 8.1+`
- [x] T013 [CHANGE-2] Create `.github/workflows/phpunit.yml` (new file) — full YAML with: `name: PHPUnit`, `on: push: branches: [main]` + `pull_request: paths: ['**.php','composer.json','composer.lock','phpunit.xml.dist']`, `permissions: {}`, single job `phpunit` with `timeout-minutes: 15`, `permissions: contents: read`, `strategy: fail-fast: false`, `matrix: php: ['8.1', '8.2', '8.3', '8.4', '8.5']`; steps: `actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5 # v4`, `shivammathur/setup-php@7c071dfe9dc99bdf297fa79cb49ea005b9fcadbc # v2` with `php-version: ${{ matrix.php }}` and `coverage: none`, `composer install --prefer-dist --no-progress`, `vendor/bin/phpunit` (PATTERN-CI-WORKFLOW-HARDENING)
- [x] T014 [CHANGE-2] ARCH-PHPUNIT-BOOTSTRAP guard: confirm `tests/bootstrap.php` defines `ABSPATH` before `require_once vendor/autoload.php`; confirm `phpunit.xml.dist` excludes test files that load BerlinDB Table subclasses (`AbilitiesQueryTest`, `AbilitiesWriteControllerTest`, etc.) — do not modify if already correct; fix only if order or scope is wrong
- [x] T015 [CHANGE-2] Verify all nine edits locally: `grep "Requires PHP" acrossai-abilities-manager.php README.txt` (both `8.1`), `grep '"php"' composer.json` (`>=8.1`), `grep php_min_version AGENTS.md` (`"8.1"`), `grep testVersion .github/workflows/phpcompat.yml` (`8.1-`), `cat .github/workflows/phpunit.yml` (matrix present), `grep -n "PHP 8.1" .specify/memory/CONSTITUTION.md` (line 117), `grep PHP .agents/skills/wp-packages-strategy/SKILL.md` (`8.1+`)

**Checkpoint**: All nine declaration sites updated, phpunit.yml created with correct SHA-pins and matrix, bootstrap guard confirmed.

---

## Phase 3: CHANGE-3 — REST Permission Callback Audit (Priority: P3)

**Goal**: Every `register_rest_route()` call in `includes/` has a non-trivial `permission_callback`; no `__return_true`. Result documented in PR description.

**Independent Test**: `grep -rn "__return_true" includes/` returns nothing.

### Implementation for CHANGE-3

- [x] T016 [CHANGE-3] Run audit: `grep -rn "register_rest_route\|permission_callback\|__return_true" includes/` — review every `register_rest_route()` call to confirm each has a `permission_callback` key; confirm no route uses `'__return_true'` or an unconditionally-true closure
- [x] T017 [CHANGE-3] If audit finds a gap: add the correct delegate reference `'permission_callback' => array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' )` to the affected route — skip if no gap found
- [x] T018 [CHANGE-3] Document audit result (all compliant, or gap + fix applied) for inclusion in PR description — note which modules and controllers were checked

**Checkpoint**: `grep -rn "__return_true" includes/` returns nothing; every `register_rest_route()` has a permission_callback; result documented.

---

## Phase 4: CHANGE-4 — Remove X of Y Items Label (Priority: P4)

**Goal**: The abilities list page no longer shows `X of Y items` text in the top tablenav. No console errors. Remaining pagination controls function correctly.

**Independent Test**: `grep -n "tn-pages" src/js/abilities/components/AbilitiesList.jsx` returns nothing.

### Implementation for CHANGE-4

- [x] T019 [CHANGE-4] Edit `src/js/abilities/components/AbilitiesList.jsx` — remove the five-line block: `<div className="tn-pages">`, `{isLoading ? __('Loading…', 'acrossai-abilities-manager') : \`${abilities.length} ${__('of', 'acrossai-abilities-manager')} ${total} ${__('items', 'acrossai-abilities-manager')}\`}`, `</div>` — do not replace it; do not touch the `.tablenav-pages` div on the next line

**Checkpoint (pre-build)**: `grep -n "tn-pages" src/js/abilities/components/AbilitiesList.jsx` returns nothing.

---

## Phase 5: CHANGE-5 — Right-align Bottom Pagination (Priority: P5)

**Goal**: Bottom pagination of the abilities table is right-aligned. Top tablenav alignment is unchanged.

**Independent Test**: `grep -n "text-align.*right" src/scss/abilities/admin.scss` returns a match inside `.tablenav-pages-below`.

### Implementation for CHANGE-5

- [x] T020 [CHANGE-5] Edit `src/scss/abilities/admin.scss` — add `justify-content: flex-end` to the `.tablenav-pages` rule (right-aligns flex children in both top and bottom contexts); add `text-align: right` to `.tablenav-pages-below`; add a nested `.tablenav .tablenav-pages` rule inside `.tablenav {}` to override WordPress `list-tables.css` specificity-20 rule (`margin: 0 0 9px`) that zeroed out `margin-left: auto` — nested rule generates specificity 20 and loads after WP's rule so it wins

**Checkpoint (pre-build)**: `grep -n "justify-content.*flex-end" src/scss/abilities/admin.scss` returns a match; `grep -n "tablenav .tablenav-pages" src/scss/abilities/admin.scss` returns the override rule.

---

## Phase 6: Asset Build

**Purpose**: Single `npm run build` covers both CHANGE-4 and CHANGE-5.

- [x] T021 Run `npm run build` — confirm build exits 0 with no errors; verify compiled assets contain no console-error-producing JS

---

## Phase 7: Final Quality Gate

**Purpose**: All gates must pass before opening the PR. Run sequentially.

- [x] T022 `vendor/bin/phpstan analyse --level=8 --error-format=github` — must exit 0 (§VII DoD)
- [x] T023 `vendor/bin/phpcs --standard=phpcs.xml.dist` — must exit 0 (§VII DoD)
- [x] T024 `vendor/bin/phpcs --standard=PHPCompatibility --extensions=php --runtime-set testVersion 8.1- acrossai-abilities-manager.php uninstall.php includes/ admin/ public/` — must exit 0 (FR-005)
- [x] T025 `vendor/bin/phpunit` — must exit 0 (§VII DoD)
- [x] T026 `composer validate` — must exit 0
- [x] T027 [P] Manual UI smoke test: load abilities list page → confirm no `X of Y items` text in top nav, no JS console errors → confirm bottom pagination is right-aligned → confirm top pagination alignment is unchanged (SC-005, SC-006)

**Checkpoint**: All gates pass → PR is ready to open.

---

---

## Phase 8: Post-Implementation Bug Fixes (Discovered During Testing)

**Purpose**: Bugs uncovered by manual testing after all planned phases passed quality gates. Fixed on the same branch.

### BerlinDB v3 Table Migration

- [x] T028 [CHANGE-1] Fix `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php` — BerlinDB v3 made `Table::set_schema()` private; child overrides are silently ignored, leaving `schema_object = null` and causing `Table::create()` to bail — replace the `protected function set_schema()` method with `protected $schema = AcrossAI_Abilities_Schema::class;` property; add `use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Schema;` import
- [x] T029 [CHANGE-1] Fix `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php` — same BerlinDB v3 breaking change — replace `protected function set_schema()` with `protected $schema = AcrossAI_Ability_Logs_Schema::class;` property; add `use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Schema;` import

**Checkpoint**: deactivate plugin → drop tables manually → reactivate → both `wp_acrossai_abilities` and `wp_acrossai_ability_logs` tables exist.

### Pagination CSS Specificity Fix

- [x] T030 [CHANGE-5] Additional SCSS fix — WordPress `list-tables.css` has `.tablenav .tablenav-pages { margin: 0 0 9px }` (specificity 20) which zeros out `margin-left` on our `.tablenav-pages { margin-left: auto }` (specificity 10); add a nested `.tablenav-pages` block inside the `.tablenav {}` rule in `src/scss/abilities/admin.scss` with `float: none; margin: 0; margin-left: auto;` — the SCSS nesting compiles to `.tablenav .tablenav-pages` (specificity 20, later cascade = wins); run `npm run build`

**Checkpoint**: compiled `build/css/abilities.css` contains `.tablenav .tablenav-pages{float:none;margin:0 0 0 auto}`.

---

---

## Phase 9: Additional BerlinDB v3 Compliance Fixes (Discovered After Wiki Review)

**Purpose**: Bugs and API gaps identified by reading the BerlinDB v3 wiki and comparing the v2→v3 diff against all 8 Database-layer files. Fixed on the same branch.

### Fix B — BerlinDB v3 Schema `'null'` key removal (PHP 8.2 dynamic property deprecation)

- [x] T031 [CHANGE-1] Fix `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` — remove `'null' => false` from `ability_slug`, `source`, `created_at`, `updated_at` column definitions; BerlinDB v3 `Base` trait sets `$column->null` dynamically but `Column` only declares `$allow_null`, causing PHP 8.2 deprecation warnings; `allow_null` defaults to `false` so NOT NULL constraint is preserved
- [x] T032 [CHANGE-1] Fix `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Schema.php` — same removal of `'null' => false` from `ability_slug`, `source`, `status`, `created_at` columns

**Checkpoint**: `grep -rn "'null'" includes/Modules/*/Database/*Schema.php` returns nothing.

### Fix C — BerlinDB v3 "phantom version" — table not recreated after manual drop

- [x] T033 [CHANGE-1] Override `maybe_upgrade()` in `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php` — add method that calls `$this->exists()` (BerlinDB v3 `SHOW TABLES LIKE` check); if table absent, call `delete_option($this->db_version_key)` to clear the phantom stored version so `parent::maybe_upgrade()` treats the next run as a fresh install
- [x] T034 [CHANGE-1] Same override in `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php`
- [x] T035 [CHANGE-1] Edit `includes/AcrossAI_Activator.php` — add `use AcrossAI_Ability_Logs_Table` import and call `( new AcrossAI_Ability_Logs_Table() )->maybe_upgrade()` in `activate()` so the logger table is created on plugin (re)activation alongside the abilities table

**Checkpoint**: drop both tables manually → deactivate/reactivate plugin → both `wp_acrossai_abilities` and `wp_acrossai_ability_logs` tables exist.

### Fix D — Full BerlinDB 3.0 API adoption (prompted by BerlinDB v3 wiki review)

- [x] T036 [CHANGE-1] Update all 8 Database-layer files — change `use BerlinDB\Database\{Schema,Table,Query,Row}` to `use BerlinDB\Database\Kern\{Schema,Table,Query,Row}` (canonical v3 namespace; old paths work via `class_alias` shim but `Kern\*` is the authoritative surface)
- [x] T037 [CHANGE-1] Add `declare(strict_types=1)` to all 8 Database-layer files matching BerlinDB v3 source convention (PHP 8.1+ target; strict_types is file-scoped and safe for these DB-layer files which only call BerlinDB methods and our own well-typed utilities)
- [x] T038 [CHANGE-1] Add missing v3 Query properties to `AcrossAI_Abilities_Query` — `$item_name = 'ability'`, `$item_name_plural = 'abilities'`, `$cache_group = 'acrossai-abilities'`, `$table_alias = 'aa'`; without these, BerlinDB generates hook names like `pre_get_items` and uses an unscoped cache group
- [x] T039 [CHANGE-1] Same for `AcrossAI_Ability_Logs_Query` — `$item_name = 'ability_log'`, `$item_name_plural = 'ability_logs'`, `$cache_group = 'acrossai-ability-logs'`, `$table_alias = 'aal'`
- [x] T040 [CHANGE-1] Edit `AcrossAI_Ability_Logs_Schema.php` `$indexes` array — add explicit `'type' => 'key'` to all four composite index definitions (Index class defaults to `'key'` but explicit declaration removes ambiguity)
- [x] T041 [CHANGE-1] Clean v2-migration docblock comments in both Table classes — remove "BerlinDB v3: property, not overridden method" from `$schema` docblock (no longer a migration note; this is simply how Table works); update Logs Table constructor comment to remove old `BerlinDB\Database\Table` class path reference

**Checkpoint**: `grep -rn "BerlinDB.Database.Schema\|BerlinDB.Database.Table\|BerlinDB.Database.Query\|BerlinDB.Database.Row" includes/Modules/*/Database/` returns nothing (all replaced with Kern paths); `grep -rn "declare.*strict_types" includes/Modules/*/Database/` returns 8 matches.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 0** (Pre-flight): No dependencies — run immediately
- **Phase 1** (CHANGE-1): Start after Phase 0; foundational — confirms vendor/ compatibility
- **Phase 2** (CHANGE-2): Independent of Phase 1; can run in parallel with it
- **Phase 3** (CHANGE-3): Independent of Phases 1–2; audit only, no code dependency
- **Phase 4** (CHANGE-4): Independent of Phases 1–3
- **Phase 5** (CHANGE-5): Independent of Phases 1–4
- **Phase 6** (Build): Run after BOTH Phase 4 and Phase 5 are complete
- **Phase 7** (Quality Gate): Run after all Phases 1–6 are complete

### Parallel Opportunities

- T006–T012 (CHANGE-2 declaration edits) can all run in parallel — each touches a different file
- T016–T018 (CHANGE-3 audit) is purely read-only until T017 (fix if gap found)
- T019 (CHANGE-4) and T020 (CHANGE-5) can run in parallel — different files
- T022–T026 (quality gate) must run sequentially after all changes are committed

---

## Notes

- [P] tasks = different files, no cross-task dependencies within the same phase
- T013 SHA pins must match existing `phpstan.yml` / `phpcompat.yml` (verify with `grep "uses: actions/checkout\|uses: shivammathur" .github/workflows/phpstan.yml`)
- Do NOT change any file under `tests/`; do NOT change `phpunit.xml.dist` unless T014 finds it misconfigured
- Do NOT remove `"minimum-stability": "dev"` from `composer.json`
- Do NOT touch the `.tablenav-pages` (top) pagination div when editing `.tablenav-pages-below` (bottom)
