# Memory Synthesis

## Current Scope
Feature 011 consolidates two admin pages into one: the React abilities UI (previously on the custom submenu page `?page=acrossai-abilities-custom`) moves to the top-level manager page `?page=acrossai-abilities-manager`. The sitewide React app (`src/js/sitewide/`) is fully removed. Affected layers: webpack config, `admin/Main.php` (enqueue), `admin/Partials/Menu.php` (mount point HTML), `admin/Partials/AcrossAI_Abilities_Menu.php` (deleted entirely), `includes/Main.php` (hook wiring removed).

## Relevant Decisions
- **DEC-NODE-20-BUILD-REQUIRED** (Reason: SC-001 requires `npm run build`; Node < 20 silently fails with `toSorted` error, Status: Active, Source: DECISIONS.md §2026-05-24) — Always run `nvm use 20 && npm run build`. Every feature's build task must document this.
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: Abilities React app does NOT use DataViews/DataForm despite Constitution §III; this deviation applies to the UI being merged, Status: Active, Source: DECISIONS.md §2026-05-24) — The abilities UI on the manager page is exempt from the DataForm/DataViews mandate.
- **DEC-ABILITIES-DUAL-MODE-LIST** (Reason: REST GET /abilities is the data source for the abilities UI now consolidated on the manager page; architecture remains unchanged, Status: Active, Source: DECISIONS.md §2026-05-24) — No REST changes in this feature; the merged UI already consumes `/abilities` REST endpoints.
- **DEC-UTILITY-STATIC-ONLY** (Reason: Class deletion of AcrossAI_Abilities_Menu must not affect utility class pattern, Status: Active, Source: DECISIONS.md) — Only orchestrators use singleton; utility classes are 100% static. AcrossAI_Abilities_Menu is an admin menu handler (singleton); its deletion is clean.
- **DEC-NAMESPACE-CONVENTION** (Reason: Deleting `AcrossAI_Abilities_Menu` removes a namespace `AcrossAI_Abilities_Manager\Admin\Partials`; all remaining files must retain their underscore-convention namespaces, Status: Active, Source: DECISIONS.md) — Verify no orphaned `use` statements reference the deleted class.

## Active Architecture Constraints
- **AC-HOOKS-MAIN** (Reason: Removing AcrossAI_Abilities_Menu hook wiring from `includes/Main.php` is the correct action; ALL hook changes go only here, Source: CONSTITUTION.md §Boot Flow Rule) — Remove only the `$abilities_menu` named variable and its `add_action` call. Touch nothing else.
- **AC-ENQUEUE-ADMIN** (Reason: All enqueue changes are in `Admin\Main::enqueue_scripts()` / `enqueue_styles()` only; no enqueue logic in Partials page classes, Source: CONSTITUTION.md §Admin Partials Rule) — `is_manager_page()` (renamed from `is_abilities_custom_page()`) gates the abilities asset enqueue. The method must check `'toplevel_page_acrossai-abilities-manager'` exactly.
- **AC-MENU-IN-PLACE** (Reason: `admin/Partials/Menu.php` is updated in-place with the new mount point `#acrossai-abilities-root`; no new menu class is created, Source: INDEX.md §AC-MENU-IN-PLACE) — One targeted string replacement in `Menu.php::contents()`.
- **AC-FILE-HEADER-PATTERN** (Reason: Any PHP file touched must retain `@package AcrossAI_Abilities_Manager`, `@subpackage` matching full path, `@since`, Source: ARCHITECTURE.md) — Applies to `admin/Main.php`, `admin/Partials/Menu.php`, `includes/Main.php`. Do not alter headers on unmodified files.
- **PATTERN-FEATURE-ASSET-SEPARATION** (Reason: The enqueue pattern in ARCHITECTURE.md uses hook suffix detection (`strpos`) — the renamed `is_manager_page()` must use `=== 'toplevel_page_acrossai-abilities-manager'` (exact match, not strpos), consistent with the existing hook suffix strategy, Source: ARCHITECTURE.md §PATTERN-FEATURE-ASSET-SEPARATION) — The abilities asset handle is `acrossai-abilities-manager-abilities`; the logger asset handle remains `acrossai-abilities-logger`. Keep the two-branch guard (`$on_abilities` / `$on_logs`) in `enqueue_styles()` and `enqueue_scripts()`.

## Accepted Deviations
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: The abilities UI already uses a custom React design prototype, not DataForm/DataViews, and this was approved for spec-010; the same exemption carries forward to spec-011 since we are moving the same UI, Status: Active-Deviation) — No DataViews/DataForm implementation required for the abilities UI.

## Relevant Security Constraints
- **Capability check on merged page**: The deleted `AcrossAI_Abilities_Menu::render()` had a `current_user_can('manage_options')` defense-in-depth check (SEC-010-01). Verify that `admin/Partials/Menu.php::contents()` (the main manager page renderer) has an equivalent capability check or that WordPress page registration already gates it. Do not leave the page unguarded.
- **Inline script data** (`window.acrossaiAbilitiesManager`): The `wp_add_inline_script` call must use `wp_json_encode()` with properly sanitized PHP data — no raw interpolation into the inline script block.
- **Multisite explicitly out of scope** (per Q1 clarification in spec): `AcrossAI_Sitewide_Table::$global = false` (SEC-03) applies to DB layer only and is unaffected by this UI change.

## Related Historical Lessons
- **Spec-010 abilities React UI completed 2026-05-24** (Reason: The React UI being merged was just built; its asset handles, mount point, and REST wiring are fresh and known, Source: WORKLOG.md) — `#acrossai-abilities-root` is the mount point used by `src/js/abilities/index.js`. Asset handle: `acrossai-abilities-manager-abilities`.
- **Spec-006 logger established PATTERN-FEATURE-ASSET-SEPARATION** (Reason: The same two-branch enqueue guard pattern must be preserved when the manager-page branch is updated; do not accidentally collapse the logs branch, Source: ARCHITECTURE.md) — The logs branch (`'acrossai-abilities-logs'` hook suffix) must remain identical and untouched.

## Conflict Warnings
- **Constitution §II multisite mandate vs. Q1 clarification**: Constitution says the plugin MUST be multisite-compatible unless explicitly scoped to single-site with documented justification. Q1 answer explicitly scopes this feature to single-site. Documented in spec `## Clarifications`. **Resolved — soft conflict, justification recorded.**
- **Constitution §III DataViews mandate vs. DEC-DESIGN-OVERRIDES-DATAVIEWS**: The abilities UI bypasses the DataForm/DataViews mandate. This deviation was approved for spec-010 and carries forward. **Resolved — accepted deviation, no action required.**

## Retrieval Notes
- Index entries considered: 17 active decisions, 8 architecture constraints, 6 bug patterns, 4 security constraints, 2 worklog entries.
- Entries selected: 5 decisions, 5 architecture constraints, 0 bug patterns, 3 security constraints, 2 worklog items, 2 accepted deviations.
- Source sections read: `ARCHITECTURE.md` lines 165–230 (PATTERN-FEATURE-ASSET-SEPARATION), `DECISIONS.md` targeted grep (DEC-NODE-20, DEC-DESIGN-OVERRIDES, DEC-ABILITIES-DUAL).
- Budget status: Within limits. `full_memory_read_allowed: false` respected.
