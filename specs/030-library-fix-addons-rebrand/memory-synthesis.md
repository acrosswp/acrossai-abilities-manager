# Memory Synthesis

## Current Scope

Feature 030 fixes two independent issues:
(A) The Ability Library admin page injects data via `wp_localize_script()` inside the page render callback — too late for reliable delivery; fix replaces it with `wp_add_inline_script()` inside `Admin\Main::enqueue_scripts()` matching the existing pattern.
(B) The Composer dependency `wpboilerplate/addons-page` is rebranded to `acrossai-co/addons-page`; all namespace references in `includes/Main.php` must be updated to the new package's declared namespace.

Affected modules: `admin/Main.php` (enqueue_scripts + AddonsPage wiring), `admin/Partials/LibraryMenu.php` (render method cleanup), `composer.json` (require + repositories), `vendor/` (after composer update).

---

## Relevant Decisions

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — External Composer packages whose constructor self-registers hooks MUST be instantiated directly in `define_admin_hooks()`, wrapped in `class_exists()`, with a comment citing this decision ID. Applies directly to Fix B: the pattern stays intact; only the class FQCN changes. (Source: DECISIONS.md)
- **DEC-FREEMIUS-PER-PLUGIN-INIT** — `AddonsPage()` constructor must receive `fs_product_id`, `fs_public_key`, `fs_slug` in the `$args` array. Constructor throws `InvalidArgumentException` if absent. Credentials live in `includes/Main.php`, never in the package. (Source: DECISIONS.md)
- **DEC-STABLE-UPGRADE-WINDOW** — When upgrading from dev-main to `^X.Y`: target first stable release; run pre-update changelog + API + security audit before applying; do NOT skip the audit for "early" releases. (Source: DECISIONS.md)
- **DEC-MENU-HOOK-SUFFIX** — `is_library_page()` uses the hardcoded suffix `acrossai-abilities-manager_page_acrossai-abilities-library`; no change needed for Fix A. (Source: DECISIONS.md)
- **DEC-ABILITIES-LIST-UX-025** — Data injected into admin pages via `window.*` global using `wp_add_inline_script()` with `'before'` position — Fix A adopts this exact pattern. (Source: DECISIONS.md)

---

## Active Architecture Constraints

- **AC-ENQUEUE-ADMIN** — `wp_enqueue_script/style` and all data injection for admin pages MUST happen only inside `Admin\Main::enqueue_scripts/styles`. Fix A corrects the Library page to comply with this constraint. (Source: CONSTITUTION.md §I)
- **AC-HOOKS-MAIN** — `includes/Main.php` is the single wiring point for all hooks. The AddonsPage instantiation lives there and stays there after the namespace update. (Source: CONSTITUTION.md §I)

---

## Accepted Deviations

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — The AddonsPage constructor self-registers hooks, bypassing the Loader. This is the accepted deviation; Fix B preserves the deviation — only the FQCN changes. (Source: DECISIONS.md, Feature 026)

---

## Relevant Security Constraints

- **BUG-EXTERNAL-PACKAGE-CTOR-SILENT** — A bare `catch {}` on the AddonsPage constructor swallows all errors silently. The catch block MUST wire `admin_notices` to surface the error to `manage_options` users. Existing guard in Main.php must be preserved after the namespace update. (Source: BUGS.md)

---

## Related Historical Lessons

- **BUG-EXTERNAL-PACKAGE-CTOR-SILENT** — First AddonsPage integration (Feature 026) failed silently: missing credentials → `InvalidArgumentException` → empty `catch` → no hooks registered → Add-ons submenu never appeared. Fix B must preserve the admin notice in the catch block. (Source: BUGS.md)
- **DEC-STABLE-UPGRADE-WINDOW** — Upgrade audit pattern: read changelog, compare constructor signature, verify Freemius init still accepts the same credentials format before running `composer update`. (Source: DECISIONS.md)

---

## Conflict Warnings

None. Both fixes are purely additive/corrective. Fix A moves existing code to the correct location (no behavioral change). Fix B is a namespace rename with no functional change.

---

## Retrieval Notes

- Index entries considered: 37 active decisions — selected 5 by (Impact × Uncertainty): DEC-EXTERNAL-PACKAGE-HOOK-CTOR, DEC-FREEMIUS-PER-PLUGIN-INIT, DEC-STABLE-UPGRADE-WINDOW, DEC-MENU-HOOK-SUFFIX, DEC-ABILITIES-LIST-UX-025.
- Architecture constraints selected: AC-ENQUEUE-ADMIN (primary Fix A constraint), AC-HOOKS-MAIN.
- Bug patterns selected: BUG-EXTERNAL-PACKAGE-CTOR-SILENT (Fix B guard).
- Full durable memory read: false (index-first, targeted).
- Budget: within limits.
- Optimizer: disabled (markdown-only flow).
