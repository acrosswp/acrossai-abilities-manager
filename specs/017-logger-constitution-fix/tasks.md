# Tasks: Logger Module — Constitution Compliance (Feature 017)

**Input**: `specs/017-logger-constitution-fix/spec.md`, `plan.md`, `security-constraints.md`
**Branch**: `017-logger-constitution-fix`
**Commit rule**: One commit per fix, in order. Each fix MUST pass PHPStan + PHPCS + PHPUnit before committing.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel with siblings in the same phase
- **[Story]**: US1–US7 map to FIX-1 through WARNING-2

---

## Phase 1 — US1: Boot Flow Restored (P1)

**Goal**: Delete `boot()` from `AcrossAI_Ability_Logger`; move all 6 Logger hooks to `Main::define_public_hooks()` via Loader.
**Independent test**: `grep -rn 'add_filter\|add_action' includes/Modules/Logger/AcrossAI_Ability_Logger.php` returns zero results.

### Tests — US1 (required, write first — verify FAIL before T003)

- [x] T001 [US1] Write failing test: assert `AcrossAI_Ability_Logger` class has no `boot` method — `tests/phpunit/Modules/Logger/AcrossAI_Ability_Logger_Test.php`
- [x] T002 [US1] Write failing test: assert the Loader hook registry contains all 6 Logger hook names (`mcp_adapter_pre_tool_call`, `wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `acrossai_ability_logger_cleanup`, second `plugins_loaded` for `schedule_cleanup`) — same file

### Implementation — US1

- [x] T003 [US1] Delete `boot()` method and its PHPDoc block (lines 86–122) from `includes/Modules/Logger/AcrossAI_Ability_Logger.php`
- [x] T004 [US1] In `includes/Main.php` `define_public_hooks()`: delete the line `$this->loader->add_action( 'plugins_loaded', $logger, 'boot', 20 );` and replace with the 6 Loader calls using `$logger` (named variable already declared on the preceding line):
  ```php
  $this->loader->add_filter( 'mcp_adapter_pre_tool_call',       $logger, 'capture_mcp_server_id',     5,      4 );
  $this->loader->add_action( 'wp_before_execute_ability',       $logger, 'start_pending_entry',      10,      2 );
  $this->loader->add_action( 'wp_after_execute_ability',        $logger, 'finish_pending_entry',     10,      3 );
  $this->loader->add_filter( 'wp_register_ability_args',        $logger, 'wrap_permission_callback', 100001,  2 );
  $this->loader->add_action( 'acrossai_ability_logger_cleanup', $logger, 'cleanup_old_logs',          10,      0 );
  $this->loader->add_action( 'plugins_loaded',                  $logger, 'schedule_cleanup',          20,      0 );
  ```
- [x] T005 [US1] Quality gate: `composer run phpcs` — zero errors; `composer run phpstan` — exit 0
- [x] T006 [US1] Run `vendor/bin/phpunit tests/phpunit/Modules/Logger/AcrossAI_Ability_Logger_Test.php` — tests pass (T001, T002 now GREEN)
- [x] T007 [US1] Commit: `fix(logger): FIX-1 migrate Logger hooks from boot() to Main Loader`

**Checkpoint**: `AcrossAI_Ability_Logger` has no `boot()`. All Logger hooks visible in Loader inventory.

---

## Phase 2 — US2: Correct Text Domain (P2)

**Goal**: Replace all `'acrossai-abilities'` text domains with `'acrossai-abilities-manager'` in both Logger REST controllers.
**Independent test**: `grep -rn "acrossai-abilities'" includes/Modules/Logger/Rest/` returns zero results.

### Tests — US2 (required, write first — verify FAIL before T010)

- [x] T008 [US2] Write failing test: assert REST route registration for Logger logs endpoint produces no PHPCS I18n errors (test by checking no `'acrossai-abilities'` string literals exist in loaded class) — `tests/phpunit/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller_Test.php`

### Implementation — US2

- [x] T009 [US2] In `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php`: replace every `'acrossai-abilities'` with `'acrossai-abilities-manager'` in all `__()` calls
- [x] T010 [US2] In `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php`: replace every `'acrossai-abilities'` with `'acrossai-abilities-manager'` in all `__()` calls
- [x] T011 [US2] Verify: `grep -rn "acrossai-abilities'" includes/Modules/Logger/Rest/` — zero results
- [x] T012 [US2] Quality gate: `composer run phpcs` — zero I18n errors; `composer run phpstan` — exit 0
- [x] T013 [US2] Run `vendor/bin/phpunit tests/phpunit/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller_Test.php` — tests pass
- [x] T014 [US2] Commit: `fix(logger): FIX-2 correct text domain to acrossai-abilities-manager in Logger REST controllers`

**Checkpoint**: PHPCS `WordPress.WP.I18n` reports zero violations on both REST controller files.

---

## Phase 3 — US3: Singleton-Only Query Access (P3)

**Goal**: Remove `static` from `get_logs()`; update call site to `::instance()->get_logs()`.
**Independent test**: `grep -n 'public static function get_logs' includes/Modules/Logger/AcrossAI_Logger_Query.php` returns zero results.

### Tests — US3 (required, write first — verify FAIL before T017)

- [x] T015 [US3] Write failing test: instantiate `AcrossAI_Logger_Query::instance()`, verify `get_logs()` is callable as an instance method (non-static dispatch) — `tests/phpunit/Modules/Logger/Database/AcrossAI_Logger_Query_Test.php`

### Implementation — US3

- [x] T016 [US3] In `includes/Modules/Logger/AcrossAI_Logger_Query.php` line 77: remove `static` keyword from `public static function get_logs(` → `public function get_logs(`
- [x] T017 [US3] In `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` line 189: replace `AcrossAI_Logger_Query::get_logs( $args )` with `AcrossAI_Logger_Query::instance()->get_logs( $args )`
- [x] T018 [US3] Quality gate: `composer run phpcs` — zero errors; `composer run phpstan` — exit 0 (PHPStan will fail if any remaining static call to non-static method exists)
- [x] T019 [US3] Run `vendor/bin/phpunit tests/phpunit/Modules/Logger/Database/AcrossAI_Logger_Query_Test.php` — tests pass
- [x] T020 [US3] Commit: `fix(logger): FIX-3 de-staticify get_logs() and update call site to ::instance()`

**Checkpoint**: No `public static function get_logs` in `AcrossAI_Logger_Query.php`; call site uses `::instance()`.

---

## Phase 4 — US4: Sanitize Callbacks at REST Entry Point (P2)

**Goal**: Add `'sanitize_callback' => 'sanitize_text_field'` to `source` and `status` REST args. Enum allowlist in `get_logs()` unchanged.
**Independent test**: `grep -c 'sanitize_callback' includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` returns ≥ 4.

### Tests — US4 (required, write first — verify FAIL before T023)

- [x] T021 [US4] Write failing test: assert `register_routes()` arg schema for `source` includes `sanitize_callback`; assert `status` includes `sanitize_callback` — `tests/phpunit/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller_Test.php`

### Implementation — US4

- [x] T022 [US4] In `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` `register_routes()`: add `'sanitize_callback' => 'sanitize_text_field'` to the `source` arg array (lines 113–117)
- [x] T023 [US4] In same method: add `'sanitize_callback' => 'sanitize_text_field'` to the `status` arg array (lines 118–121)
- [x] T024 [US4] Verify: `grep -c 'sanitize_callback' includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` — returns ≥ 4; confirm `get_logs()` enum allowlist is unchanged
- [x] T025 [US4] Quality gate: `composer run phpcs` — zero errors; `composer run phpstan` — exit 0
- [x] T026 [US4] Run `vendor/bin/phpunit tests/phpunit/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller_Test.php` — tests pass
- [x] T027 [US4] Commit: `fix(logger): FIX-4 add sanitize_callback to source and status REST args`

**Checkpoint**: Both `source` and `status` args have `sanitize_callback`. Enum allowlist untouched.

---

## Phase 5 — US5: Utility Class in Correct Directory + Singleton (P3)

**Goal**: `git mv` Source Detector to `includes/Utilities/`; adopt Module Contract singleton; update namespace, use import, and all 6 call sites; `composer dump-autoload`.
**Independent test**: `ls includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php` fails; `ls includes/Utilities/AcrossAI_Logger_Source_Detector.php` succeeds.

### Tests — US5 (required, write first — verify FAIL before T030)

- [x] T028 [US5] Write failing test: assert `AcrossAI_Logger_Source_Detector::instance()` returns `instanceof AcrossAI_Logger_Source_Detector` under new namespace `AcrossAI_Abilities_Manager\Includes\Utilities` — `tests/phpunit/Utilities/AcrossAI_Logger_Source_Detector_Test.php`
- [x] T029 [US5] Write failing test: verify `detect_source()`, `set_mcp_context()`, `clear_mcp_context()` all work as instance methods (non-static calls via `::instance()`) — same file

### Implementation — US5

- [x] T030 [US5] Run: `git mv includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php includes/Utilities/AcrossAI_Logger_Source_Detector.php`
- [x] T031 [US5] In `includes/Utilities/AcrossAI_Logger_Source_Detector.php`: change namespace declaration to `AcrossAI_Abilities_Manager\Includes\Utilities`
- [x] T032 [US5] In same file: update `@subpackage` file header to `Includes/Utilities`
- [x] T033 [US5] In same file: add singleton scaffolding immediately after the class declaration:
  ```php
  protected static $_instance = null;
  public static function instance(): self {
      if ( null === self::$_instance ) {
          self::$_instance = new self();
      }
      return self::$_instance;
  }
  private function __construct() {}
  ```
- [x] T034 [US5] In same file: convert `private static $is_mcp_context = false;` → `private $is_mcp_context = false;`; convert `private static $mcp_server_id = null;` → `private $mcp_server_id = null;`
- [x] T035 [US5] In same file: remove `static` keyword from all 10 public methods: `detect_source`, `is_mcp_context`, `is_rest_context`, `is_cli_context`, `is_cron_context`, `is_ajax_context`, `detect_mcp_server_id`, `set_mcp_context`, `clear_mcp_context`, `is_valid_source`
- [x] T036 [US5] In same file: update all internal references from `self::$is_mcp_context` → `$this->is_mcp_context` and `self::$mcp_server_id` → `$this->mcp_server_id`
- [x] T037 [US5] In `includes/Modules/Logger/AcrossAI_Ability_Logger.php`: update `use` import: `AcrossAI_Abilities_Manager\Includes\Modules\Logger\AcrossAI_Logger_Source_Detector` → `AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Source_Detector`
- [x] T038 [US5] In `includes/Modules/Logger/AcrossAI_Ability_Logger.php`: update all 6 static call sites to instance calls:
  - `AcrossAI_Logger_Source_Detector::set_mcp_context($server_id)` → `AcrossAI_Logger_Source_Detector::instance()->set_mcp_context($server_id)` (L106) ← **security-critical**
  - `AcrossAI_Logger_Source_Detector::detect_source()` → `::instance()->detect_source()` (L127)
  - `AcrossAI_Logger_Source_Detector::detect_mcp_server_id()` → `::instance()->detect_mcp_server_id()` (L136, inside `$pending` array)
  - `AcrossAI_Logger_Source_Detector::clear_mcp_context()` → `::instance()->clear_mcp_context()` (L228) ← **security-critical**
  - `AcrossAI_Logger_Source_Detector::detect_source()` → `::instance()->detect_source()` (L258, inside `wrap_permission_callback` closure)
  - `AcrossAI_Logger_Source_Detector::detect_mcp_server_id()` → `::instance()->detect_mcp_server_id()` (L265, inside `wrap_permission_callback` closure)
  - Note: `is_valid_source()` was listed in the original task but has zero call sites in `AcrossAI_Ability_Logger.php` — task description corrected here for accuracy.
  - Run `grep -n "AcrossAI_Logger_Source_Detector::" includes/Modules/Logger/AcrossAI_Ability_Logger.php` — identify any unlisted call site and update to `::instance()->method()` form
  - Verify zero remaining `AcrossAI_Logger_Source_Detector::` static calls in this file
- [x] T039 [US5] Run `composer dump-autoload` — must complete without errors
- [x] T040 [US5] Quality gate: `composer run phpcs` — zero errors; `composer run phpstan` — exit 0
- [x] T041 [US5] Run `vendor/bin/phpunit tests/phpunit/Utilities/AcrossAI_Logger_Source_Detector_Test.php` — tests pass (T028, T029 now GREEN)
- [x] T042 [US5] Commit: `fix(logger): FIX-5 move Source Detector to Utilities/ and adopt singleton pattern`

**Checkpoint**: `ls includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php` — no such file. `ls includes/Utilities/AcrossAI_Logger_Source_Detector.php` — exists. PHPStan and PHPCS zero errors.

---

## Phase 6 — US6: BerlinDB Constructor Exception Documented (P4)

**Goal**: Add PHPDoc block above `instance()` in `AcrossAI_Ability_Logs_Table.php` documenting the BerlinDB constructor exception.
**Independent test**: PHPDoc above `instance()` contains "BerlinDB" and "Justified exception".

### Tests — US6 (required, write first — verify FAIL before T045)

- [x] T043 [US6] Write failing test: assert `AcrossAI_Ability_Logs_Table::instance()` returns non-null (smoke test confirms constructor still runs after PHPDoc added); assert no fatal error on instantiation — `tests/phpunit/Modules/Logger/Database/AcrossAI_Ability_Logs_Table_Test.php`

### Implementation — US6

- [x] T044 [US6] In `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php`: insert the following PHPDoc block immediately above `public static function instance(): self {` (must start with capital "Note:" per `BUG-PHPCS-DOCBLOCK-CAPITAL`):
  ```php
  /**
   * Note: constructor is intentionally NOT private. BerlinDB\Database\Table
   * performs table-registration side-effects in parent::__construct().
   * A private constructor would prevent those from running and break table
   * registration. Justified exception to the Module Contract.
   *
   * @since 0.1.0
   * @return self
   */
  ```
- [x] T045 [US6] Quality gate: `composer run phpcs` — zero errors (verify `BUG-PHPCS-DOCBLOCK-CAPITAL` does not fire); `composer run phpstan` — exit 0
- [x] T046 [US6] Run `vendor/bin/phpunit tests/phpunit/Modules/Logger/Database/AcrossAI_Ability_Logs_Table_Test.php` — tests pass
- [x] T047 [US6] Commit: `docs(logger): WARNING-1 document BerlinDB constructor exception in Logs Table`

**Checkpoint**: PHPDoc present above `instance()`. PHPCS zero errors.

---

## Phase 7 — US7: Constitution Module List Patched (P4)

**Goal**: Amend `CONSTITUTION.md` to v1.4.2 — add `Logger/` to Directory Layout, correct §I module count, update version footer and sync impact block. Committed separately.

### Tests — US7

- [x] T048 [US7] Verify (shell assertions, not PHPUnit): `grep 'Logger/' .specify/memory/CONSTITUTION.md` returns result inside Directory Layout block; `grep 'five active' .specify/memory/CONSTITUTION.md` returns result; `grep '1.4.2' .specify/memory/CONSTITUTION.md` returns result; `grep '2026-05-28' .specify/memory/CONSTITUTION.md` returns result

### Implementation — US7

- [x] T049 [US7] In `.specify/memory/CONSTITUTION.md` Directory Layout code block: add `    └── Logger/` between `Abilities/` and `Webmcp/` lines
- [x] T050 [US7] In `.specify/memory/CONSTITUTION.md` §I: change `"four active feature areas"` → `"five active feature areas"`; add "Ability Execution Logging" to the list of feature areas alongside the four existing ones
- [x] T051 [US7] In `.specify/memory/CONSTITUTION.md` version footer: change `1.4.1` → `1.4.2`; update `Last Amended` to `2026-05-28`
- [x] T052 [US7] In `.specify/memory/CONSTITUTION.md` HTML sync impact comment at top of file: add new block:
  ```
  Version change: 1.4.1 → 1.4.2
  Modified sections: Directory Layout (Logger/ added), §I (module count corrected)
  Rationale: Logger module existed but was omitted from the module list;
  namespace examples already referenced it correctly.
  ```
- [x] T053 [US7] Update `docs/memory/DECISIONS.md` — entry `DEC-UTILITY-STATIC-ONLY`: remove `AcrossAI_Logger_Source_Detector` from the "pure utilities (static only)" examples; add note: "Note: `AcrossAI_Logger_Source_Detector` was moved to `includes/Utilities/` and adopted the singleton pattern in Feature 017 because it holds mutable request-scoped state (`$is_mcp_context`, `$mcp_server_id`)."
- [x] T054 [US7] Update `docs/memory/DECISIONS.md` — entry `ARCH-ADV-001`: add note that this deviation is scoped exclusively to `AcrossAI_Ability_Override_Processor`; Logger's incorrect claim was removed in Feature 017.
- [x] T055 [US7] Update `docs/memory/INDEX.md` — `Accepted Deviations` row for `ARCH-ADV-001`: add scope note "Override Processor only; does NOT cover Logger module (resolved Feature 017)"
- [x] T056 [US7] Run shell assertions from T048 — all return results
- [x] T057 [US7] Commit (standalone — no PHP files staged): `docs: amend constitution to v1.4.2 (Logger/ added to directory layout, §I module count corrected)`

**Checkpoint**: `CONSTITUTION.md` v1.4.2, Logger/ in Directory Layout, five active feature areas. Committed separately from all PHP fixes.

---

## Phase 8 — Post-Implementation Quality Gates

**Purpose**: Final cross-fix verification after all commits.

- [x] T058 [P] Run full quality gate: `composer run phpcs` — zero errors across all changed files
- [x] T059 [P] Run full quality gate: `composer run phpstan` — exit 0
- [x] T060 [P] Run full PHPUnit suite: `vendor/bin/phpunit` — zero failures
- [x] T061 Run `npm run validate-packages` — no package hierarchy violations
- [x] T062 [P] Verify SC-001: `grep -rn 'add_filter\|add_action' includes/Modules/Logger/AcrossAI_Ability_Logger.php` — zero results
- [x] T063 [P] Verify SC-002: `grep -rn "acrossai-abilities'" includes/Modules/Logger/Rest/` — zero results
- [x] T064 [P] Verify SC-003: `grep -n 'public static function get_logs' includes/Modules/Logger/AcrossAI_Logger_Query.php` — zero results
- [x] T065 [P] Verify SC-004: `grep -c 'sanitize_callback' includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` — returns ≥ 4
- [x] T066 [P] Verify SC-005: `ls includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php` — no such file; `ls includes/Utilities/AcrossAI_Logger_Source_Detector.php` — exists
- [x] T067 [P] Verify SC-008: `grep 'Logger/' .specify/memory/CONSTITUTION.md` — returns result in Directory Layout; `grep '1.4.2' .specify/memory/CONSTITUTION.md` — returns result
- [x] T068 [P] Verify SC-006 (WARNING-1): `grep -c "BerlinDB" includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php` — returns ≥ 1; PHPDoc above `instance()` present and contains "Justified exception"
- [x] T069 [P] Verify SC-007 (WARNING-2 memory): `grep "Override Processor only" docs/memory/DECISIONS.md` — returns result; `grep "017" docs/memory/INDEX.md` — returns result
- [x] T070 Verify SC-009 (manual): confirm ability execution still logs correctly (Logger behaviour unchanged)
