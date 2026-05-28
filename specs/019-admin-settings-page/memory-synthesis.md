# Memory Synthesis

## Current Scope
Feature 019 adds a **Settings submenu page** under the Abilities Manager admin menu (PHP-only). Five files change: `admin/Partials/SettingsMenu.php` (new singleton), `includes/Main.php` (hook wiring), `admin/Main.php` (enqueue guard), `includes/Modules/Logger/AcrossAI_Ability_Logger.php` (option read), `uninstall.php` (conditional deletion). No JS, no build step, no REST changes.

## Relevant Decisions
- **AC-HOOKS-MAIN** (Reason Included: CHANGE-2 wires SettingsMenu hooks; variable-first rule applies, Status: Active, Source: CONSTITUTION.md §I)
- **AC-ENQUEUE-ADMIN** (Reason Included: CHANGE-3 touches enqueue guards in Admin\Main; no enqueue must occur in SettingsMenu itself, Status: Active, Source: CONSTITUTION.md §I)
- **DEC-MENU-HOOK-SUFFIX** (Reason Included: CHANGE-3 proposes `is_settings_page()` — this decision dictates HOW to implement it; SOFT CONFLICT with plan, Status: Active, Source: DECISIONS.md)
- **DEC-NAMESPACE-CONVENTION** (Reason Included: New class `SettingsMenu` must use `AcrossAI_Abilities_Manager\Admin\Partials` namespace, Status: Active, Source: DECISIONS.md)
- **DEC-UTILITY-STATIC-ONLY** (Reason Included: SettingsMenu renders a page and wires hooks → it is a page orchestrator; singleton pattern is correct; static-only pattern does NOT apply, Status: Active, Source: DECISIONS.md)

## Active Architecture Constraints
- **AC-HOOKS-MAIN**: Only `includes/Main.php` may call `loader->add_action/add_filter`; singleton resolved to named variable first (Reason Included: CHANGE-2, Source: CONSTITUTION.md §I)
- **AC-ENQUEUE-ADMIN**: `wp_enqueue_script/style` ONLY inside `Admin\Main::enqueue_scripts/styles`; SettingsMenu MUST NOT enqueue directly (Reason Included: CHANGE-1 + CHANGE-3, Source: CONSTITUTION.md §I)
- **AC-FILE-HEADER-PATTERN**: New PHP file needs `@package AcrossAI_Abilities_Manager`, `@subpackage Admin/Partials`, `@since 0.1.0` (Reason Included: CHANGE-1 is a new file, Source: ARCHITECTURE.md)
- **PATTERN-ENQUEUE-PAGE-GUARD**: `is_*_page()` helpers use Yoda `===` form; no `strpos`; no dynamic class coupling (Reason Included: CHANGE-3, Source: ARCHITECTURE.md)
- **Admin Partials Rule** (CONSTITUTION §I): Classes calling `add_submenu_page()` live in `admin/Partials/` with namespace `AcrossAI_Abilities_Manager\Admin\Partials`. SettingsMenu is correctly placed. (Reason Included: CHANGE-1, Source: CONSTITUTION.md)

## Accepted Deviations
- None applicable to this feature scope.

## Relevant Security Constraints
- **Settings API nonce**: `settings_fields('acrossai_abilities_settings')` generates the WP nonce; no custom nonce needed. `current_user_can('manage_options')` in `render()` is defense-in-depth. (Source: spec FR-004, planning doc)
- **Sanitize strictness**: `absint` for int field, `intval`+clamp for bool checkbox. No raw `$_POST` access; Settings API handles request parsing. (Source: FR-005, FR-006)
- **`uninstall.php` capability context**: `uninstall.php` runs outside WP request context (no `current_user_can`); the `$delete_data` gate uses option value, not a request parameter. (Source: FR-009)

## Related Historical Lessons
- **BUG-UNCONDITIONAL-ASSET-INCLUDE**: The Settings page has no custom asset bundle — no `.asset.php` file will be `include`d. No guard needed, but confirm CHANGE-3 does NOT add any asset include for the settings page. (Reason Included: CHANGE-3, Source: BUGS.md)
- **BUG-PHPCS-DOCBLOCK-CAPITAL**: All new PHP docblocks in SettingsMenu.php must have long descriptions starting with uppercase; phpcbf won't fix capitalization. (Reason Included: CHANGE-1 new file, Source: BUGS.md)
- **BUG-PHPCBF-TABS**: When applying patches to PHP files, use `\t` not spaces. phpcbf enforces tabs. (Reason Included: Implementation, Source: BUGS.md)

## Conflict Warnings
- **SOFT CONFLICT — `is_settings_page()` uses `get_hook_suffix()` coupling**: The planning doc (CHANGE-3) proposes `SettingsMenu::instance()->get_hook_suffix()` inside `is_settings_page()`. `DEC-MENU-HOOK-SUFFIX` (Active, 2026-05-24) explicitly forbids this pattern: *"Do not call `AcrossAI_SomeMenuClass::instance()->get_hook_suffix()` inside `Admin\Main` — this couples enqueue to a menu class."* The correct pattern is a hardcoded string literal:
  ```php
  private function is_settings_page( string $hook_suffix ): bool {
      return 'acrossai-abilities-manager_page_acrossai-abilities-settings' === $hook_suffix;
  }
  ```
  The formula `{parent-slug}_page_{submenu-slug}` is deterministic and stable. This pattern also means: (a) no `use SettingsMenu;` statement needed in `admin/Main.php`, (b) SettingsMenu does not need `$hook_suffix` property or `get_hook_suffix()` method. **The plan must be corrected.** Note: `is_logs_page()` already violates this decision (existing tech debt) — not in scope here.
- **SOFT CONFLICT — `get_hook_suffix()` in SettingsMenu**: If `admin/Main.php` uses the hardcoded pattern, SettingsMenu can be simplified to omit `$hook_suffix`/`get_hook_suffix()`. This reduces SettingsMenu's surface and eliminates a coupling point. **Minor simplification; spec proceeds with corrected pattern.**

## Retrieval Notes
- Index entries considered: 24 active decisions, 8 architecture constraints, 7 implementation patterns, 11 bug patterns, 4 security constraints, 4 accepted deviations, 4 worklog items
- Selected: 5 decisions, 5 architecture constraints, 0 accepted deviations, 3 security constraints (spec-derived), 3 bug patterns, 0 worklog items
- Source sections read: DECISIONS.md (DEC-MENU-HOOK-SUFFIX full entry), admin/Main.php (is_logs_page live code), admin/Partials/LogsMenu.php (hook_suffix pattern), CONSTITUTION.md (Admin Partials + singleton)
- Budget status: ~520 words — within 900-word limit
