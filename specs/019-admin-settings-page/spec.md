# Feature Specification: Admin Settings Page

**Feature Branch**: `019-add-settings-page`  
**Created**: 2026-05-29  
**Status**: Draft  
**Input**: User description: "Add Settings submenu page to Abilities Manager admin menu. Two sections: (A) Log Settings — number input for retention days (0 = never, default 0) stored in option 'acrossai_abilities_log_retention_days', (B) Uninstall Settings — checkbox 'delete all data on uninstall' (default unchecked) stored in option 'acrossai_abilities_uninstall_delete_data', with red warning text. Five files change: (1) NEW admin/Partials/SettingsMenu.php (singleton submenu + Settings API), (2) includes/Main.php (register submenu in define_admin_hooks), (3) admin/Main.php (is_settings_page guard + enqueue for settings page), (4) includes/Modules/Logger/AcrossAI_Ability_Logger.php (read option in cleanup_old_logs and skip scheduling when retention=0), (5) uninstall.php (conditional deletion based on option value)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Configure Log Retention (Priority: P1)

A site administrator navigates to **Abilities Manager → Settings** and sets the log retention period to control how long execution logs are kept before automatic deletion.

**Why this priority**: Log storage can grow unbounded on active sites. Giving admins control over retention period is the primary operational value of this feature.

**Independent Test**: Navigate to `admin.php?page=acrossai-abilities-settings`, enter `7` in the "Delete logs after (days)" field, click "Save Changes", then verify `wp option get acrossai_abilities_log_retention_days` returns `7`. Test separately that entering `0` disables automatic cleanup entirely.

**Acceptance Scenarios**:

1. **Given** the plugin is installed, **When** an admin opens the Settings page, **Then** a "Log Settings" section is visible with a number input defaulting to `0`.
2. **Given** the Settings page is open, **When** the admin enters `30` and saves, **Then** `acrossai_abilities_log_retention_days` is stored as integer `30` in `wp_options`.
3. **Given** retention is set to `0`, **When** the log cleanup job runs, **Then** no logs are deleted and no Action Scheduler job is created for cleanup.
4. **Given** retention is set to `7`, **When** the log cleanup job runs, **Then** logs older than 7 days are deleted.
5. **Given** a non-admin user, **When** they attempt to access the Settings page directly, **Then** WordPress blocks access or the page renders "Insufficient permissions."

---

### User Story 2 — Control Data Deletion on Uninstall (Priority: P2)

A site administrator uses the "Uninstall Settings" section to decide whether custom database tables and plugin options are permanently dropped when the plugin is uninstalled.

**Why this priority**: This is a destructive, irreversible operation. The default must be safe (unchecked = preserve data), and the UI must clearly warn the user before they enable it.

**Independent Test**: Verify the checkbox is unchecked by default. Check the box, save, then uninstall — confirm tables `wp_acrossai_abilities` and `wp_wpb_access_control` are dropped. Re-install with the checkbox unchecked and uninstall again — confirm tables are preserved.

**Acceptance Scenarios**:

1. **Given** the Settings page is open, **When** the admin views the "Uninstall Settings" section, **Then** the checkbox "Delete all data on uninstall" is unchecked and a red warning message is visible below it.
2. **Given** the checkbox is unchecked (default), **When** the plugin is uninstalled, **Then** the `wp_acrossai_abilities` and `wp_wpb_access_control` tables are NOT dropped and `acrossai_abilities_db_version` / `wpb_access_control_db_version` options remain.
3. **Given** the checkbox is checked and saved, **When** the plugin is uninstalled, **Then** both tables are dropped and all four related option keys are deleted.
4. **Given** either checkbox state, **When** the plugin is uninstalled, **Then** `acrossai_abilities_log_retention_days` and `acrossai_abilities_uninstall_delete_data` options are always removed.

---

### Edge Cases

- What happens when the retention days field is submitted with a negative number? (`absint` sanitization clamps it to `0` — effectively "never delete".)
- What happens when the uninstall checkbox is unchecked and submitted? (WordPress sends no value for unchecked checkboxes; the sanitize callback must treat absent value as `0`.)
- What happens if Action Scheduler is not installed when retention changes from `0` to a positive value? (`schedule_cleanup()` guards on `function_exists('as_schedule_recurring_action')` — no scheduling attempt is made; no error.)
- What happens if a non-integer string is entered in the retention field? (`absint` converts it to `0`.)
- What happens when retention changes from a positive value to `0` while an Action Scheduler job is already scheduled? The existing job is NOT unscheduled — `schedule_cleanup()` only skips creating a new job. The recurring job will continue to fire, but `cleanup_old_logs()` will early-return (no logs deleted). No extra code is needed; this is intentional to keep the implementation minimal.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST expose a "Settings" submenu page under the Abilities Manager top-level admin menu, accessible only to users with `manage_options` capability.
- **FR-002**: The Settings page MUST contain a "Log Settings" section with a number input (`acrossai_abilities_log_retention_days`, default `0`, minimum `0`) that controls how many days before logs are auto-deleted; `0` means never delete.
- **FR-003**: The Settings page MUST contain an "Uninstall Settings" section with a checkbox (`acrossai_abilities_uninstall_delete_data`, default unchecked) and a prominent red warning message explaining the irreversibility of data deletion.
- **FR-004**: Both settings MUST be registered via the WordPress Settings API (option group `acrossai_abilities_settings`) so WordPress handles nonce validation, sanitization, and redirect.
- **FR-005**: The log retention option MUST be sanitized with `absint` (returns integer ≥ 0).
- **FR-006**: The uninstall delete option MUST be sanitized using an explicit absent-field-safe callback: `empty( $value ) ? 0 : 1` — returns `1` when the checkbox is checked, `0` when unchecked or absent (browser sends no field for unchecked checkboxes).
- **FR-007**: `AcrossAI_Ability_Logger::cleanup_old_logs()` MUST read `acrossai_abilities_log_retention_days` from `wp_options` (default `0`) and pass it as the default to the existing `acrossai_ability_log_retention_days` filter.
- **FR-008**: `AcrossAI_Ability_Logger::schedule_cleanup()` MUST skip scheduling when `acrossai_abilities_log_retention_days` is `0`.
- **FR-009**: `uninstall.php` MUST check `acrossai_abilities_uninstall_delete_data` before dropping tables or deleting BerlinDB version options; plugin settings options (`log_retention_days`, `uninstall_delete_data`) MUST always be deleted on uninstall regardless of the checkbox state.
- **FR-010**: The `SettingsMenu` class MUST follow the established singleton pattern (identical to `LogsMenu`) and be registered in `includes/Main.php` via the Loader.
- **FR-011**: `admin/Main.php` MUST add `is_settings_page()` and include it in the `enqueue_styles()`/`enqueue_scripts()` early-return guards.

### Key Entities

- **Log Retention Setting** (`acrossai_abilities_log_retention_days`): Integer ≥ 0. Stored in `wp_options`. `0` = never auto-delete. Controls both scheduling and cleanup execution.
- **Uninstall Delete Flag** (`acrossai_abilities_uninstall_delete_data`): `1` or `0`. Stored in `wp_options`. Gates destructive table drops and option deletes in `uninstall.php`.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can navigate to the Settings page, change both settings, save, and verify the stored values match the input — all within 60 seconds.
- **SC-002**: With retention = `0`, no `acrossai_ability_logger_cleanup` Action Scheduler job exists after plugin activation.
- **SC-003**: With retention = `7`, logs older than 7 days are removed when the cleanup job runs; no PHP error or warning is produced.
- **SC-004**: Uninstalling with `uninstall_delete_data = 0` (default) leaves all site data intact. Uninstalling with `uninstall_delete_data = 1` removes all plugin tables and options.
- **SC-005**: All five changed files pass PHPCS and PHPStan level 8 with zero errors.

## Clarifications

### Session 2026-05-29

- Q: When retention changes from a positive value back to `0`, should the existing scheduled Action Scheduler job be unscheduled, or left to run as a no-op? → A: Leave the existing job scheduled. `cleanup_old_logs()` early-returns when retention = 0, so the job is harmless. `schedule_cleanup()` only skips creating a new job — no unscheduling logic is added.
- Q: For sites upgrading from the previous version (where cleanup used a hard-coded 30-day default), should a migration seed `acrossai_abilities_log_retention_days = 30` on first activation so existing behavior is preserved? → A: No migration. The new default is explicitly `0` (never delete). Upgrading sites will no longer auto-delete logs until an admin sets a value in Settings. This is the intended behavior change.

## Assumptions

- The WordPress Settings API (`register_setting`, `add_settings_section`, `add_settings_field`, `settings_fields`, `do_settings_sections`) is available in all supported WP versions (6.9+).
- `LogsMenu.php` is the authoritative singleton pattern to follow; no divergence is introduced.
- The `apply_filters('acrossai_ability_log_retention_days', ...)` hook is preserved with the option value as its new default — third-party filters continue to work.
- No JavaScript is needed — the Settings page uses WordPress core admin UI only.
- Action Scheduler may not be installed; all scheduling code is guarded with `function_exists('as_schedule_recurring_action')`.
- The `acrossai_ability_logger_cleanup` Action Scheduler job slug is not renamed.
- No new CSS or JS bundles are enqueued for the Settings page.
- Upgrading sites will have no `acrossai_abilities_log_retention_days` option in the DB; `get_option` returns `0` (never delete). This is a deliberate behavior change from the previous hard-coded 30-day default — no data migration is provided.
