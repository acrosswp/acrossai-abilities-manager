# Planning: Admin Settings Page (Feature 019)

Add a **Settings** submenu page under the Abilities Manager top-level admin menu.
The page exposes two setting groups:

1. **Log Settings** — number input controlling how many days before execution logs are
   auto-deleted (0 = never delete, which is the new default; the old hard-coded default
   was 30 days).
2. **Uninstall Settings** — checkbox that controls whether custom database tables and
   plugin options are dropped when the plugin is uninstalled. Unchecked by default, with
   a prominent red warning note.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "019-admin-settings-page"

# 2. Specify
/speckit.specify "Add Settings submenu page to Abilities Manager admin menu.
Two sections: (A) Log Settings — number input for retention days (0 = never, default 0)
stored in option 'acrossai_abilities_log_retention_days', (B) Uninstall Settings —
checkbox 'delete all data on uninstall' (default unchecked) stored in option
'acrossai_abilities_uninstall_delete_data', with red warning text.
Five files change: (1) NEW admin/Partials/SettingsMenu.php (singleton submenu + Settings API),
(2) includes/Main.php (register submenu in define_admin_hooks),
(3) admin/Main.php (is_settings_page guard + enqueue for settings page),
(4) includes/Modules/Logger/AcrossAI_Ability_Logger.php (read option in cleanup_old_logs
and skip scheduling when retention=0),
(5) uninstall.php (conditional deletion based on option value)."
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | `LogsMenu` singleton at `admin/Partials/LogsMenu.php` is the exact pattern to follow for the new `SettingsMenu` singleton | read the file |
| B-2 | `includes/Main.php::define_admin_hooks()` registers `LogsMenu::instance()` on `admin_menu`; the same pattern applies for `SettingsMenu` | `grep -n "logs_menu" includes/Main.php` |
| B-3 | `admin/Main.php` already has `is_logs_page()` and `is_manager_page()` guards; a new `is_settings_page()` guard follows the same pattern | read `admin/Main.php` |
| B-4 | `AcrossAI_Ability_Logger::cleanup_old_logs()` already reads retention days via `apply_filters('acrossai_ability_log_retention_days', 30)` — CHANGE-4 replaces the filter default with the stored option | `grep -n "retention_days" includes/Modules/Logger/AcrossAI_Ability_Logger.php` |
| B-5 | `schedule_cleanup()` currently schedules unconditionally when Action Scheduler is present; CHANGE-4 must skip scheduling when retention = 0 | `grep -n "schedule_cleanup" includes/Modules/Logger/AcrossAI_Ability_Logger.php` |
| B-6 | `uninstall.php` currently always drops tables and deletes options; CHANGE-5 wraps this in a conditional | read `uninstall.php` |
| B-7 | Two option keys already exist in `uninstall.php`: `acrossai_abilities_db_version` and `wpb_access_control_db_version` | read `uninstall.php` |

---

## Option Keys (authoritative list)

| Option key | Type | Default | Purpose |
|------------|------|---------|---------|
| `acrossai_abilities_log_retention_days` | `int` | `0` | Log auto-delete retention; 0 = never |
| `acrossai_abilities_uninstall_delete_data` | `bool` (stored as `1`/`0`) | `0` (false) | If `1`, drop tables and delete all options on uninstall |

Both keys are registered with `register_setting()` in `SettingsMenu` so WordPress handles
sanitization, nonce validation, and the redirect after save.

---

## CHANGE-1 — NEW `admin/Partials/SettingsMenu.php`

Create a new singleton class following the `LogsMenu` pattern exactly:

```
namespace AcrossAI_Abilities_Manager\Admin\Partials;
class SettingsMenu { ... }
```

**Singleton contract** (identical to `LogsMenu`):
- `protected static $_instance = null`
- `public static function instance(): self`
- `private function __construct()`
- `private $hook_suffix = ''`
- `public function get_hook_suffix(): string`

**`register_submenu()` method** — hooked to `admin_menu`:

```php
$this->hook_suffix = add_submenu_page(
    'acrossai-abilities-manager',
    __( 'Settings', 'acrossai-abilities-manager' ),
    __( 'Settings', 'acrossai-abilities-manager' ),
    'manage_options',
    'acrossai-abilities-settings',
    array( $this, 'render' )
);
```

**`register_settings()` method** — hooked to `admin_init`:

Uses the WordPress Settings API. Registers two settings sections and fields under the
option group `acrossai_abilities_settings`.


Section 1: `acrossai_log_settings_section`
- Title: "Log Settings"
- Field ID: `acrossai_abilities_log_retention_days`
- Label: "Delete logs after (days)"
- Type: `number`, min `0`, step `1`
- Sanitize: `absint` — returns integer ≥ 0; 0 means "never delete"
- Description below field: "Set to 0 to keep logs forever. If a number is entered,
  logs older than that many days will be automatically deleted."

Section 2: `acrossai_uninstall_settings_section`
- Title: "Uninstall Settings"
- Field ID: `acrossai_abilities_uninstall_delete_data`
- Label: "Delete all data on uninstall"
- Type: `checkbox`, value `1`
- Sanitize: cast to `int`, clamp to 0 or 1
- Below the checkbox, render a `<p>` with class `description` styled red:
  `<span style="color: #d63638;">⚠ Warning: When checked, uninstalling this plugin will
  permanently delete all custom database tables and plugin options. This cannot be undone.</span>`

**`render()` method** — outputs the settings page HTML:

```php
public function render(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Abilities Manager — Settings', 'acrossai-abilities-manager' ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'acrossai_abilities_settings' );
            do_settings_sections( 'acrossai-abilities-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
```

**Security constraints**:
- `current_user_can( 'manage_options' )` check at the top of `render()`.
- Settings API handles nonce (`settings_fields()`) and redirect.
- All output uses `esc_html_e()`, `esc_attr()`, `wp_kses_post()` as appropriate.
- Sanitize callbacks must be strict: `absint` for the integer, `intval` + clamp for the bool.
- PHPStan level 8 must pass. PHPCS must pass.

---

## CHANGE-2 — `includes/Main.php`

In `define_admin_hooks()`, add after the `$logs_menu` registration block:

```php
// Settings submenu page (Feature 019).
$settings_menu = \AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu::instance();
$this->loader->add_action( 'admin_menu', $settings_menu, 'register_submenu' );
$this->loader->add_action( 'admin_init', $settings_menu, 'register_settings' );
```

Follow the Boot Flow Rule: named variable before Loader calls.
Do not change any other line in `define_admin_hooks()`.

---

## CHANGE-3 — `admin/Main.php`

**Add `is_settings_page()` private method**:

```php
private function is_settings_page( string $hook_suffix ): bool {
    $settings_menu = SettingsMenu::instance();
    return $hook_suffix === $settings_menu->get_hook_suffix();
}
```

**Add `use` statement** at the top of the file, alongside the existing
`use AcrossAI_Abilities_Manager\Admin\Partials\LogsMenu;`:

```php
use AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu;
```

**Update `enqueue_styles()` and `enqueue_scripts()`** early-return guard:

```php
// Current:
if ( ! $this->is_manager_page( $hook_suffix ) && ! $this->is_logs_page( $hook_suffix ) ) {
    return;
}

// New:
if ( ! $this->is_manager_page( $hook_suffix )
    && ! $this->is_logs_page( $hook_suffix )
    && ! $this->is_settings_page( $hook_suffix ) ) {
    return;
}
```

The Settings page uses only WordPress core styles (WP admin CSS). No custom JS or CSS
bundle is enqueued for the settings page — the early-return change just prevents the
guard from accidentally blocking WP's own settings API CSS (which it would not, but
being explicit is cleaner). No new `wp_register_style` or `wp_register_script` calls are
added for the settings page.

---

## CHANGE-4 — `includes/Modules/Logger/AcrossAI_Ability_Logger.php`

### `cleanup_old_logs()` — read from option, not filter default

Replace:

```php
$retention_days = (int) apply_filters( 'acrossai_ability_log_retention_days', 30 );

if ( $retention_days < 1 ) {
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log( 'Logger: Invalid retention days, skipping cleanup' );
    return;
}
```

With:

```php
$option_days    = (int) get_option( 'acrossai_abilities_log_retention_days', 0 );
$retention_days = (int) apply_filters( 'acrossai_ability_log_retention_days', $option_days );

if ( $retention_days < 1 ) {
    return;
}
```

Key changes:
- Default shifts from `30` to `0` — meaning "never delete" out of the box.
- The `apply_filters` call is preserved so developers can still override via code;
  the option is now the default passed to the filter.
- The `error_log` is removed: `retention_days < 1` is now a valid user-configured
  "never delete" state, not an error condition.

### `schedule_cleanup()` — skip scheduling when retention = 0

After the `function_exists( 'as_schedule_recurring_action' )` guard, add an early return
when the user has opted out of auto-cleanup:

```php
// Do not schedule if retention is set to 0 (never delete).
$option_days = (int) get_option( 'acrossai_abilities_log_retention_days', 0 );
if ( 0 === $option_days ) {
    return;
}
```

This prevents an unnecessary recurring Action Scheduler job when the site owner does
not want automatic cleanup.

**Important**: if the option is later changed from 0 to a positive value, the next
`plugins_loaded` will call `schedule_cleanup()` again — the existing idempotency check
(`as_next_scheduled_action`) handles re-scheduling correctly.

---

## CHANGE-5 — `uninstall.php`

The current file drops tables and deletes options unconditionally. Wrap all destructive
operations in a check against the new option:

```php
// Respect the user's "delete data on uninstall" setting.
$delete_data = (bool) get_option( 'acrossai_abilities_uninstall_delete_data', false );

if ( $delete_data ) {
    // Drop the unified abilities table.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}acrossai_abilities`" );
    delete_option( 'acrossai_abilities_db_version' );

    // Drop the WPBoilerplate Access Control rules table.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}wpb_access_control`" );
    delete_option( 'wpb_access_control_db_version' );
}

// Always remove plugin settings options on uninstall (not data — configuration).
delete_option( 'acrossai_abilities_log_retention_days' );
delete_option( 'acrossai_abilities_uninstall_delete_data' );
```

**Rationale for the split**:
- Tables and BerlinDB version options are *data* — only deleted when the user explicitly
  opted in via the settings checkbox.
- The two new settings options (`log_retention_days`, `uninstall_delete_data`) are
  *configuration*, not data. They are always cleaned up on uninstall regardless of the
  checkbox, because there is no reason to persist settings for a plugin that no longer
  exists.

---

## What must NOT change

- Do not modify `src/js/abilities/` — this feature is pure PHP + WordPress Settings API.
- Do not add a new JS entry point or build artifact.
- Do not change the `apply_filters( 'acrossai_ability_log_retention_days', ... )` hook
  signature — only the default value passed to it changes. Third-party code using this
  filter continues to work.
- Do not change `AcrossAI_Ability_Logger::unschedule_cleanup()` — deactivation cleanup
  is unaffected by this feature.
- Do not change any REST endpoint, DB schema, or REST response shape.
- Do not change `admin/Partials/Menu.php` — the top-level menu is unchanged.
- Do not rename the Action Scheduler job slug `acrossai_ability_logger_cleanup`.

---

## CONSTRAINTS

- Exactly five files change: `admin/Partials/SettingsMenu.php` (new), `includes/Main.php`,
  `admin/Main.php`, `includes/Modules/Logger/AcrossAI_Ability_Logger.php`, `uninstall.php`.
- Every `__()` call must use `'acrossai-abilities-manager'` as the text domain.
- PHPStan level 8 must pass with zero errors.
- PHPCS must pass with zero errors.
- No JS build step is needed — `npm run build` is not required for this feature.

---

## Spec-kit Commands

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer run phpcs
composer run phpstan

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

### CHANGE-1 — SettingsMenu page

- [ ] `admin.php?page=acrossai-abilities-settings` loads without errors.
- [ ] "Log Settings" section renders a number input; default value shown is `0`.
- [ ] Changing the value to `7` and clicking "Save Changes" stores `7` in
      `wp_options` as `acrossai_abilities_log_retention_days`. Confirm with:
      `wp option get acrossai_abilities_log_retention_days`
- [ ] Setting value back to `0` stores `0` (not an empty string or absent key).
- [ ] "Uninstall Settings" section renders a checkbox that is **unchecked** by default.
- [ ] Red warning text is visible below the checkbox.
- [ ] Checking the box and saving stores `1` in `wp_options` as
      `acrossai_abilities_uninstall_delete_data`.
- [ ] Unchecking and saving stores `0`.
- [ ] Accessing the page as a non-admin user produces "Insufficient permissions." (or
      is blocked by WordPress capability check before render).

### CHANGE-2 — `includes/Main.php` hook registration

- [ ] `grep -n "SettingsMenu" includes/Main.php` shows `register_submenu` wired to
      `admin_menu` and `register_settings` wired to `admin_init`.

### CHANGE-3 — `admin/Main.php` guard

- [ ] `is_settings_page()` method exists and uses `SettingsMenu::instance()`.
- [ ] The early-return guard in both `enqueue_styles()` and `enqueue_scripts()` includes
      `! $this->is_settings_page( $hook_suffix )`.

### CHANGE-4 — Logger respects new option

- [ ] With `acrossai_abilities_log_retention_days = 0`, the scheduled Action Scheduler
      job `acrossai_ability_logger_cleanup` is **not** created on a fresh install.
      Confirm with: `wp action-scheduler list --hook=acrossai_ability_logger_cleanup`
- [ ] With `acrossai_abilities_log_retention_days = 7`, the job is scheduled (or already
      scheduled from a previous run).
- [ ] Running `wp action-scheduler run --hook=acrossai_ability_logger_cleanup` with
      retention = 0 exits silently with no rows deleted and no error logged.
- [ ] Running with retention = 7 deletes logs older than 7 days and logs the count.

### CHANGE-5 — `uninstall.php` is conditional

- [ ] With `acrossai_abilities_uninstall_delete_data = 0` (default): after uninstall,
      `wp_acrossai_abilities` and `wp_wpb_access_control` tables still exist.
      Options `acrossai_abilities_db_version` and `wpb_access_control_db_version`
      still exist. Settings options are removed.
- [ ] With `acrossai_abilities_uninstall_delete_data = 1`: after uninstall, all tables
      and all four option keys are gone.
- [ ] `acrossai_abilities_log_retention_days` and `acrossai_abilities_uninstall_delete_data`
      are always removed on uninstall regardless of the checkbox state.

### Quality gates

- [ ] `composer run phpstan` — zero errors.
- [ ] `composer run phpcs` — zero errors.
- [ ] Exactly five files modified (`git diff --name-only`): `admin/Partials/SettingsMenu.php`
      (new), `includes/Main.php`, `admin/Main.php`,
      `includes/Modules/Logger/AcrossAI_Ability_Logger.php`, `uninstall.php`.
