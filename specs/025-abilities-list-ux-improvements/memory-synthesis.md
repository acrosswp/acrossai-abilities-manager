# Memory Synthesis

## Current Scope

Feature 025 adds six UX improvements to `AbilitiesList.jsx` and `SettingsMenu.php`: stateful pagination driven by the REST `page`/`per_page` params, an admin-controlled per-page setting, CSS-only hiding of the All/Published/Draft tabs, a "Clear All Overrides" row action for inherited abilities, two new table columns (Description, Show in REST), and a localStorage-backed column visibility toggle. One possible PHP change: adding a `has_override` boolean to the REST read response if absent. All admin JS enqueue changes go through `Admin\Main`.

---

## Relevant Decisions

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: Table is classic `.wptable`; DataViews/DataForm must NOT be introduced for this feature. Status: Active, Source: DECISIONS.md)
- **DEC-SETTINGS-API-DEVIATION** (Reason: New "Abilities per page" field uses WP Settings API — the existing scalar-settings-page deviation already covers this. The `SettingsMenu` currently has 2 fields; adding 1 stays well under the ≤5-field rule. Status: Active, Source: DECISIONS.md)
- **DEC-ABILITIES-DUAL-MODE-LIST** (Reason: Pagination fetch must pass `page`/`per_page` through the dual-mode branch — DB query path already supports these; registry merge path also needs to honour them. Status: Active, Source: DECISIONS.md)
- **DEC-TYPECELL-REGISTRY-FALLBACK** (Reason: `DescriptionCell` and `ShowInRestCell` must read `item.description` / `item.show_in_rest` first, then fall back to `item._registry?.description` / `item._registry?.show_in_rest` — non-db abilities carry values in `_registry` only. Status: Active, Source: DECISIONS.md, Feature 024)
- **DEC-WPDB-PREPARE-SPREAD** (Reason: If `has_override` requires a `$wpdb->prepare()` call, always spread params: `...$params`, never pass an array as the second argument. Status: Active, Source: DECISIONS.md)

---

## Active Architecture Constraints

- **AC-ENQUEUE-ADMIN** (Reason: `perPage` injection via `wp_add_inline_script` or `wp_localize_script` MUST be placed inside `Admin\Main::enqueue_scripts()` — no other file may call these functions. Source: CONSTITUTION.md §I)
- **PATTERN-CHECKBOX-SANITIZE** (Reason: The new `sanitize_per_page()` method must be a named public method — not a closure. Use `absint()` + range clamp. Source: ARCHITECTURE.md, Feature 019)
- **PATTERN-ENQUEUE-PAGE-GUARD** (Reason: Any new `wp_add_inline_script` call must be inside the existing `is_manager_page()` guard in `Admin\Main`. Source: ARCHITECTURE.md)
- **DEC-BY-SOURCE-AUTHZ** (Reason: Any new DB query method for `has_override` must be auth-free at the query layer; the REST controller — which already runs `permission_callback` — is the correct auth gate. Source: DECISIONS.md)
- **AC-REST-SPLIT** (Reason: If the read controller grows past 400 lines after adding `has_override`, a split is required. Check line count before editing. Source: CONSTITUTION.md §I)

---

## Accepted Deviations

- **DEC-SETTINGS-API-DEVIATION** (Reason: Extending the existing scalar-settings page with one more integer field; no new deviation required — the same rule covers it. Status: Accepted-Deviation)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: Classic `.wptable` and HTML pagination controls are the accepted UI pattern for this plugin. No DataViews retrofit needed. Status: Accepted-Deviation)

---

## Relevant Security Constraints

- **SEC-01** (Reason: If `has_override` is added to the REST endpoint, `sanitize_ability_slug()` must still be applied to the slug param — no change to the existing pattern. Source: security-constraints.md)
- **DEC-EARLY-404-REST-CHECK** (Reason: The read controller already does early 404 before DB lookups; any `has_override` lookup must sit after this guard, not before. Source: DECISIONS.md)
- **localStorage only** (Reason: Column preferences are stored client-side in localStorage — no user data reaches the server, so no new server-side auth or sanitization surface is added.)

---

## Related Historical Lessons

- **Feature 024 (2026-06-02)**: Established `DEC-TYPECELL-REGISTRY-FALLBACK`. Every new cell renderer for registry-declared fields must double-read `item.field ?? item._registry?.field`. Missing this produces blank cells for all plugin/core/theme abilities.
- **Feature 019 (2026-05-29)**: Established `PATTERN-CHECKBOX-SANITIZE` and `DEC-SETTINGS-API-DEVIATION`. New Settings API fields must follow the named-public-method sanitizer convention already used by `sanitize_uninstall_flag()`.
- **BUG-MODULE-LEVEL-WINDOW-READ**: `window.acrossaiAbilities.perPage` must be read inside the component function (or a `useState` initializer), never at module level — module-level reads run at `require()` time before the inline script fires.
- **BUG-ABILITYFORM-JSX-MIXED-DEPTHS**: `AbilitiesList.jsx` has inconsistent indentation in places. Verify actual tab depth of the target insertion point before any string replacement to avoid misaligned JSX.

---

## Conflict Warnings

None. All six changes are additive. The new per-page settings field is covered by the existing `DEC-SETTINGS-API-DEVIATION`. The classic table pattern is covered by `DEC-DESIGN-OVERRIDES-DATAVIEWS`. No active decision conflicts with localStorage-based column prefs or CSS-only tab hiding.

---

## Retrieval Notes

- Index entries considered: 20 (full budget used; 8 directly relevant selected)
- Source sections read: DECISIONS.md (DEC-DESIGN-OVERRIDES-DATAVIEWS, DEC-ABILITIES-DUAL-MODE-LIST, DEC-SETTINGS-API-DEVIATION, DEC-TYPECELL-REGISTRY-FALLBACK), ARCHITECTURE.md (AC-ENQUEUE-ADMIN, PATTERN-CHECKBOX-SANITIZE)
- Feature memory file: none yet (no `memory.md` for 025)
- Budget status: within 900-word limit; no summarization needed
- `full_memory_read_allowed: false` respected — no full-file dumps
