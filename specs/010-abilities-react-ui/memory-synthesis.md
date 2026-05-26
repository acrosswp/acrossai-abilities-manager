# Memory Synthesis

## Current Scope

Spec 010 adds a React admin UI for managing Custom Abilities (`source=db`) and overriding inherited abilities (`source≠db`). Affected modules: `admin/Partials/` (new `AcrossAI_Abilities_Menu.php`), `admin/Main.php` (new asset enqueue branch), `includes/Main.php` (new `admin_menu` hook), `webpack.config.js` (two new entries), and new `src/js/abilities/` + `src/scss/abilities/` source trees. Depends on Spec 009 REST endpoints.

---

## Relevant Decisions

- **DEC-NAMESPACE-CONVENTION** — All PHP files must use underscore namespace convention: `AcrossAI_Abilities_Manager\Admin\Partials\AcrossAI_Abilities_Menu`. Never use backslash-only PSR-4 style. (Reason: new PHP class added; Source: DECISIONS.md)

- **DEC-UTILITY-STATIC-ONLY** — `AcrossAI_Abilities_Menu` is a stateful orchestrator → singleton pattern is correct. Any helper extracted to a utility class must be 100% static with no singleton. (Reason: establishes class type boundary; Source: DECISIONS.md)

- **PATTERN-FEATURE-ASSET-SEPARATION** — Abilities UI assets (`js/abilities.js`, `css/abilities.css`) must use a feature-specific handle (`acrossai-abilities-manager-abilities`) and be enqueued separately from the main manager assets. The `Admin\Main::enqueue_scripts/styles` method gates by `$hook_suffix`. (Reason: directly governs new webpack entries + enqueue; Source: ARCHITECTURE.md)

- **DEC-PROTECTED-SLUGS-PATTERN** — The REST list read controller already uses `AcrossAI_Protected_Abilities` for exclusions. The React client does not need to replicate this logic — it receives only the allowed rows from the REST response. (Reason: confirms client-side filtering is not needed; Source: DECISIONS.md)

- **DEV1** — `McpVisibilityControl` uses a compound-control pattern instead of `@wordpress/dataforms`. This deviation is active and applies to Spec 010: reuse `McpVisibilityControl.jsx` as-is; do not attempt to wrap it in a DataForm field type. (Reason: directly applies to AbilityForm MCP section reuse; Status: Accepted-Deviation; Source: memory-synthesis.md)

---

## Active Architecture Constraints

- **AC-HOOKS-MAIN** — Only `includes/Main.php` calls `$this->loader->add_action()`. The `admin_menu` hook for `AcrossAI_Abilities_Menu::register_submenu` MUST be wired in `define_admin_hooks()`, not inside the menu class itself. (Reason: adds new hook; Source: CONSTITUTION.md §I)

- **AC-ENQUEUE-ADMIN** — `wp_enqueue_script()` and `wp_enqueue_style()` MUST only appear in `Admin\Main::enqueue_scripts()` / `enqueue_styles()`. The menu class's `render()` method MUST NOT enqueue assets — that belongs in `Admin\Main`. (Reason: new assets added; Source: CONSTITUTION.md §I)

- **AC-MENU-IN-PLACE** — The constraint "no new menu class for admin/Partials/Menu.php" applies to the top-level plugin menu only. For submenu pages, the `LogsMenu.php` precedent establishes that separate singleton partial classes are the correct pattern. `AcrossAI_Abilities_Menu.php` follows this established precedent. (Reason: clarifies scope boundary; Source: FR-020 / LogsMenu.php precedent)

- **ARCH-UNIFIED-ABILITIES-STORAGE** — The React UI reads from the same `wp_acrossai_abilities` table via the Spec 009 REST layer. No direct DB access from JS; all data flows through REST. (Reason: data boundary; Source: ARCHITECTURE.md)

---

## Accepted Deviations

- **DEV1** — `McpVisibilityControl` uses compound-control pattern instead of DataForm integration. Applies directly to the MCP Exposure section in `AbilityForm.jsx`. Reuse as-is. (Status: Accepted-Deviation)

---

## Relevant Security Constraints

- **SEC-04** — Strict type comparison for access checks. On the PHP side: `is_abilities_custom_page()` hook-suffix comparison must use `===` or `strpos() !== false`, not `==`. (Reason: new access guard added; Source: security-constraints.md)

- **Inline script nonce pattern** — Existing pattern in `Admin\Main.php` (lines 164–174) uses `wp_add_inline_script( $handle, 'window.X = ' . wp_json_encode([...]) . ';', 'before')`. The abilities config object MUST follow this exact pattern with `wp_create_nonce('wp_rest')` — not `wp_localize_script()`. (Reason: established plugin convention; Source: admin/Main.php)

---

## Related Historical Lessons

- **PATTERN-FEATURE-ASSET-SEPARATION** (Feature 006) — When the logger feature was added, assets were placed in separate `logger.js`/`logger.css` entries with a dedicated handle and guarded by `is_logs_page()`. Apply the same pattern: `abilities.js`/`abilities.css` + `is_abilities_custom_page()`. (Reason: directly reusable pattern)

- **AC-ENQUEUE-ADMIN violation risk** — Previous reviews flagged attempts to enqueue inside page-render callbacks. Ensure `AcrossAI_Abilities_Menu::render()` never calls `wp_enqueue_*`; all assets flow through `Admin\Main`.

---

## Conflict Warnings

- **AC-MENU-IN-PLACE vs. new class**: Index entry reads "no new menu class" but scoped to `Menu.php` (top-level menu). Creating `AcrossAI_Abilities_Menu.php` as a submenu partial follows the `LogsMenu.php` precedent and is not a violation. **Soft conflict resolved** — proceed.

- None blocking. No hard conflicts detected between the spec and active memory.

---

## Retrieval Notes

- Index entries considered: 14 active decisions, 8 architecture constraints, 2 bug patterns, 1 worklog entry
- Entries selected (filter=architecture, decision_limit=5): AC-HOOKS-MAIN, AC-ENQUEUE-ADMIN, AC-MENU-IN-PLACE, PATTERN-FEATURE-ASSET-SEPARATION, DEC-NAMESPACE-CONVENTION, DEC-UTILITY-STATIC-ONLY, DEV1, SEC-04, inline-script nonce pattern
- Source sections read: DECISIONS.md (DEC-NAMESPACE-CONVENTION, DEC-UTILITY-STATIC-ONLY, DEC-PROTECTED-SLUGS-PATTERN), ARCHITECTURE.md (PATTERN-FEATURE-ASSET-SEPARATION), security-constraints.md (SEC-04), admin/Main.php (lines 160–195)
- Budget: ~620 words / 900 max ✅
- Full memory read: false
