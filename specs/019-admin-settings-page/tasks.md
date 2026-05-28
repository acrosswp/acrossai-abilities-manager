# Tasks: Admin Settings Page (Feature 019)

**Input**: `specs/019-admin-settings-page/spec.md`, `docs/planning/019-admin-settings-page.md`, `specs/019-admin-settings-page/memory-synthesis.md`  
**Branch**: `019-add-settings-page`  
**Constraint**: Exactly 5 files change. No JS. No build step. PHPStan level 8 + PHPCS must pass.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel with other [P] tasks (different files, no dependencies)
- **[US1]**: User Story 1 — Configure Log Retention (P1)
- **[US2]**: User Story 2 — Control Data Deletion on Uninstall (P2)

---

## Phase 1: Foundation — Settings Page Singleton

**Purpose**: Create `SettingsMenu` — required before hook wiring (T002) but independent of T003–T006.

- [x] T001 [US1+US2] Create `admin/Partials/SettingsMenu.php`

  **File**: `admin/Partials/SettingsMenu.php` (new)

  PHP file header:
  ```
  @package    AcrossAI_Abilities_Manager
  @subpackage Admin/Partials
  @since      0.1.0
  ```

  Class: `AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu`

  **Singleton contract** (no `$hook_suffix` / no `get_hook_suffix()` — DEC-MENU-HOOK-SUFFIX):
  - `protected static $_instance = null`
  - `public static function instance(): self`
  - `private function __construct() {}`

  **`register_submenu()` method** (public, void):
  ```php
  add_submenu_page(
      'acrossai-abilities-manager',
      __( 'Settings', 'acrossai-abilities-manager' ),
      __( 'Settings', 'acrossai-abilities-manager' ),
      'manage_options',
      'acrossai-abilities-settings',
      array( $this, 'render' )
  );
  ```

  **`register_settings()` method** (public, void) — Settings API:
  - Option group: `acrossai_abilities_settings`
  - `register_setting( 'acrossai_abilities_settings', 'acrossai_abilities_log_retention_days', [ 'sanitize_callback' => 'absint', 'default' => 0 ] )`
  - `register_setting( 'acrossai_abilities_settings', 'acrossai_abilities_uninstall_delete_data', [ 'sanitize_callback' => function( $value ) { return empty( $value ) ? 0 : 1; }, 'default' => 0 ] )`
  - Section 1 ID: `acrossai_log_settings_section`, title: `__( 'Log Settings', 'acrossai-abilities-manager' )`
  - Field 1 ID: `acrossai_abilities_log_retention_days`, label: `__( 'Delete logs after (days)', 'acrossai-abilities-manager' )`, callback: `render_retention_field()`
  - Section 2 ID: `acrossai_uninstall_settings_section`, title: `__( 'Uninstall Settings', 'acrossai-abilities-manager' )`
  - Field 2 ID: `acrossai_abilities_uninstall_delete_data`, label: `__( 'Delete all data on uninstall', 'acrossai-abilities-manager' )`, callback: `render_uninstall_field()`

  **`render_retention_field()` method** (private, void):
  ```php
  $value = (int) get_option( 'acrossai_abilities_log_retention_days', 0 );
  printf(
      '<input type="number" id="acrossai_abilities_log_retention_days"
       name="acrossai_abilities_log_retention_days"
       value="%s" min="0" step="1" />
       <p class="description">%s</p>',
      esc_attr( (string) $value ),
      esc_html__( 'Set to 0 to keep logs forever. If a number is entered, logs older than that many days will be automatically deleted.', 'acrossai-abilities-manager' )
  );
  ```

  **`render_uninstall_field()` method** (private, void):
  ```php
  $checked = (bool) get_option( 'acrossai_abilities_uninstall_delete_data', 0 );
  printf(
      '<label><input type="checkbox" id="acrossai_abilities_uninstall_delete_data"
       name="acrossai_abilities_uninstall_delete_data" value="1" %s /> %s</label>
       <p class="description"><span style="color: #d63638;">%s</span></p>',
      checked( $checked, true, false ),
      esc_html__( 'Delete all data on uninstall', 'acrossai-abilities-manager' ),
      esc_html__( '⚠ Warning: When checked, uninstalling this plugin will permanently delete all custom database tables and plugin options. This cannot be undone.', 'acrossai-abilities-manager' )
  );
  ```

  **`render()` method** (public, void):
  ```php
  if ( ! current_user_can( 'manage_options' ) ) {
      wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
  }
  // <div class="wrap"><h1>...</h1><form method="post" action="options.php">
  // settings_fields( 'acrossai_abilities_settings' );
  // do_settings_sections( 'acrossai-abilities-settings' );
  // submit_button();
  ```

  **DoD**: File created, class instantiable, no PHP parse errors.

**Checkpoint**: SettingsMenu class exists — T002 can now proceed.

---

## Phase 2: US1 — Configure Log Retention (P1)

**Goal**: Admin can visit Settings page, configure retention days, and Logger respects the stored value.

**Independent Test**: `admin.php?page=acrossai-abilities-settings` loads, "Log Settings" section visible; `wp option get acrossai_abilities_log_retention_days` returns configured value; with retention=0 no AS job exists.

- [x] T002 [US1] Wire `SettingsMenu` hooks in `includes/Main.php`

  **File**: `includes/Main.php`, inside `define_admin_hooks()`, after the `$logs_menu` registration block (line ~276).

  Insert:
  ```php
  // Settings submenu page (Feature 019).
  $settings_menu = \AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu::instance();
  $this->loader->add_action( 'admin_menu', $settings_menu, 'register_submenu' );
  $this->loader->add_action( 'admin_init', $settings_menu, 'register_settings' );
  ```

  **Rules**: Named variable before Loader calls (AC-HOOKS-MAIN). Do not change any other line.

  **DoD**: `grep -n "SettingsMenu" includes/Main.php` shows both hooks wired.

- [x] T003 [P] [US1] Add `is_settings_page()` guard to `admin/Main.php`

  **File**: `admin/Main.php`

  Add private method — hardcoded string (DEC-MENU-HOOK-SUFFIX; no `use SettingsMenu;` needed):
  ```php
  private function is_settings_page( string $hook_suffix ): bool {
      return 'acrossai-abilities-manager_page_acrossai-abilities-settings' === $hook_suffix;
  }
  ```

  Update early-return in both `enqueue_styles()` and `enqueue_scripts()`:
  ```php
  // Replace:
  if ( ! $this->is_manager_page( $hook_suffix ) && ! $this->is_logs_page( $hook_suffix ) ) {
  // With:
  if ( ! $this->is_manager_page( $hook_suffix )
      && ! $this->is_logs_page( $hook_suffix )
      && ! $this->is_settings_page( $hook_suffix ) ) {
  ```

  **Do NOT** add `use AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu;` — not needed.

  **DoD**: `grep -n "is_settings_page" admin/Main.php` shows method + 2 guard usages.

- [x] T004 [P] [US1] Update `cleanup_old_logs()` in `includes/Modules/Logger/AcrossAI_Ability_Logger.php`

  **File**: `includes/Modules/Logger/AcrossAI_Ability_Logger.php`

  Replace block at `cleanup_old_logs()` (currently line ~342–347):
  ```php
  // Remove:
  $retention_days = (int) apply_filters( 'acrossai_ability_log_retention_days', 30 );
  if ( $retention_days < 1 ) {
      // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
      error_log( 'Logger: Invalid retention days, skipping cleanup' );
      return;
  }

  // Insert:
  $option_days    = (int) get_option( 'acrossai_abilities_log_retention_days', 0 );
  $retention_days = (int) apply_filters( 'acrossai_ability_log_retention_days', $option_days );
  if ( $retention_days < 1 ) {
      return;
  }
  ```

  **DoD**: `grep -n "apply_filters.*retention" includes/Modules/Logger/AcrossAI_Ability_Logger.php` shows new 2-line pattern; `error_log.*Invalid retention` is gone.

- [x] T005 [US1] Update `schedule_cleanup()` in `includes/Modules/Logger/AcrossAI_Ability_Logger.php`

  **File**: Same file as T004 — complete T004 first.

  After the `if ( ! function_exists( 'as_schedule_recurring_action' ) ) { return; }` guard, insert:
  ```php
  // Do not schedule if retention is set to 0 (never delete).
  $option_days = (int) get_option( 'acrossai_abilities_log_retention_days', 0 );
  if ( 0 === $option_days ) {
      return;
  }
  ```

  **DoD**: `grep -n "option_days" includes/Modules/Logger/AcrossAI_Ability_Logger.php` shows 2 occurrences (T004 + T005).

**Checkpoint**: User Story 1 fully functional — Settings page renders Log Settings section; Logger reads option; AS job not created when retention=0.

---

## Phase 3: US2 — Uninstall Delete Flag (P2)

**Goal**: Admin can enable "delete data on uninstall" from Settings; uninstall.php respects the flag.

**Independent Test**: With flag=0 (default), uninstall preserves tables. With flag=1, tables dropped. Settings options always removed on uninstall.

- [x] T006 [P] [US2] Update `uninstall.php` with conditional data deletion

  **File**: `uninstall.php`

  Replace the two unconditional table-drop+delete_option blocks with:
  ```php
  // Respect the user's "delete data on uninstall" setting.
  $delete_data = (bool) get_option( 'acrossai_abilities_uninstall_delete_data', false );

  if ( $delete_data ) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
      $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}acrossai_abilities`" );
      \delete_option( 'acrossai_abilities_db_version' );

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
      $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wpb_access_control`" );
      \delete_option( 'wpb_access_control_db_version' );
  }

  // Always remove plugin settings options on uninstall.
  \delete_option( 'acrossai_abilities_log_retention_days' );
  \delete_option( 'acrossai_abilities_uninstall_delete_data' );
  ```

  **DoD**: `grep -n "delete_data\|get_option" uninstall.php` shows the conditional gate; both settings options are always deleted.

**Checkpoint**: User Story 2 fully functional — uninstall behaviour controlled by stored flag.

---

## Phase 4: Quality Gates

- [x] T007 Run `composer run phpcs` — zero errors across all 5 changed files.

  If errors found: fix before T008. Common issues to pre-empt:
  - Docblock long descriptions must start uppercase (BUG-PHPCS-DOCBLOCK-CAPITAL)
  - Tabs not spaces (BUG-PHPCBF-TABS)
  - File header `@package`/`@subpackage`/`@since` present in SettingsMenu.php

- [x] T008 Run `composer run phpstan` — zero errors at level 8.

  If errors found: fix before marking complete. Common issues to pre-empt:
  - `register_setting()` callback type: `callable` or inline `Closure`
  - PHPStan may need type annotation for `$_instance`

---

## Dependencies & Execution Order

```
T001 → T002
T001 (independent of T003, T004, T005, T006)
T003 [P] — independent of T001 (hardcoded string, no SettingsMenu import)
T004 [P] — independent of T001
T005 → T004 (same file; complete T004 first)
T006 [P] — fully independent
T007, T008 → all of T001–T006
```

### Parallel execution opportunities

T003, T004, and T006 can all be written simultaneously (different files). T001 only blocks T002.

---

## Definition of Done

- [x] All 6 implementation tasks (T001–T006) marked complete
- [x] `composer run phpcs` — zero errors (T007)
- [x] `composer run phpstan` — zero errors (T008)
- [x] `git diff --name-only` shows exactly 5 files: `admin/Partials/SettingsMenu.php`, `includes/Main.php`, `admin/Main.php`, `includes/Modules/Logger/AcrossAI_Ability_Logger.php`, `uninstall.php`
- [x] Manual verification checklist in `docs/planning/019-admin-settings-page.md` completed
