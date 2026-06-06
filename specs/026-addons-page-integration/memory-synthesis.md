# Memory Synthesis

## Current Scope
Integrate wpboilerplate/addons-page via Composer path repo; instantiate AddonsPage in `define_admin_hooks()`; append three README.txt sections. Affected modules: `composer.json`, `includes/Main.php`, `README.txt`.

## Relevant Decisions
- **DEC-FAIL-OPEN-NOTICE** — Fail-open library absence must pair with a `manage_options` admin notice. (Reason Included: AddonsPage class may be absent if vendor is missing; spec's edge case requires graceful failure, not silent white screen. Status: Active, Source: DECISIONS.md)
- **DEC-STABLE-UPGRADE-WINDOW** — Prioritize first stable releases when upgrading from dev branches. (Reason Included: package is required at `@dev`; planning should note a post-MVP stable-pin step. Status: Active, Source: DECISIONS.md)
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE** — Re-validate SEC-04, SEC-03, DEC-PERM-CB, DEC-FAIL-OPEN-NOTICE after library upgrades. (Reason Included: new Composer dependency introduced. Status: Active, Source: DECISIONS.md)
- **DEC-MENU-HOOK-SUFFIX** — Hardcode `toplevel_page_{slug}`; avoid `get_hook_suffix()` coupling. (Reason Included: AddonsPage constructor passes the plugin slug and may hook admin page assets — the package must follow this or we note it as an external exception. Status: Active, Source: DECISIONS.md)
- **ARCH-ADV-001** — `boot()` wires hooks directly when PATH-A/B conditional loading required (scope: Override Processor only). (Reason Included: AddonsPage constructor self-registers hooks — similar direct-registration pattern; deviation scope may need to be widened or a parallel accepted-deviation created. Status: Active-Deviation, Source: DECISIONS.md)

## Active Architecture Constraints
- **AC-HOOKS-MAIN** — Only `Main.php` calls `loader->add_action/add_filter`; variable-first pattern. (Reason Included: AddonsPage constructor wires its own WordPress hooks directly, bypassing the project loader — this is a HARD deviation from AC-HOOKS-MAIN that must be acknowledged as an accepted deviation. Source: CONSTITUTION.md §I)
- **AC-ENQUEUE-ADMIN** — `wp_enqueue_script/style` ONLY in `Admin\Main::enqueue_scripts/styles`. (Reason Included: AddonsPage may enqueue its own assets from its constructor or internal hooks — this is an external package exception and must be documented. Source: CONSTITUTION.md §I)
- **AC-MENU-IN-PLACE** — `admin/Partials/Menu.php` updated in-place; no new menu class. (Reason Included: Confirms no new local menu class should be created for Add-ons; the package handles it. Source: FR-020)

## Accepted Deviations
- **ARCH-ADV-001** — Direct hook wiring in constructor/boot(), not via loader, when conditional loading is required (Override Processor). (Reason Included: AddonsPage self-registers hooks in its constructor — the same pattern, extended to an external package. Status: Accepted-Deviation — a new parallel deviation entry for AddonsPage should be recorded.)

## Relevant Security Constraints
- **SEC-03** — Per-site table prefix; multisite isolation. (Reason Included: AddonsPage will register admin pages; verify it respects per-site context rather than network-wide. Source: security-constraints.md)

## Related Historical Lessons
- **BUG-UNCONDITIONAL-ASSET-INCLUDE** — `include .asset.php` without `file_exists` guard causes PHP fatal on missing bundle. (Reason Included: If AddonsPage or its assets are missing from vendor, an unconditional include in the package could cause a fatal. Must run `composer update` before testing and verify vendor presence.)
- **BUG-PHPSTAN-SILENT-PASS** — PHPStan exit 0 + no output = clean pass. (Reason Included: Validation checklist requires `composer run phpstan` to pass; remember that silence is success.)

## Conflict Warnings
- **HARD — AC-HOOKS-MAIN violation**: `new \WPBoilerplate\AddonsPage\AddonsPage(...)` in `define_admin_hooks()` causes the external class constructor to register WordPress hooks directly (not via `$this->loader`). This violates AC-HOOKS-MAIN. **Resolution**: Record a new accepted-deviation entry (parallel to ARCH-ADV-001) scoped to external/third-party packages whose constructors self-register hooks. The instantiation itself still lives in `Main.php` (satisfying the spirit of AC-HOOKS-MAIN), but the loader is bypassed. Plan must document this explicitly.
- **SOFT — AC-ENQUEUE-ADMIN**: AddonsPage package may enqueue assets from its own internal hooks rather than `Admin\Main`. This is acceptable for third-party packages but should be noted as an external-package exception to AC-ENQUEUE-ADMIN.
- **SOFT — DEC-FAIL-OPEN-NOTICE**: The spec edge case says "fail gracefully" when vendor is missing, but doesn't prescribe a `manage_options` admin notice. Per DEC-FAIL-OPEN-NOTICE, a `class_exists()` guard with an admin notice is the project standard. Planning should add this guard.

## Retrieval Notes
- Index entries considered: 35 entries scanned; 5 decisions selected, 3 constraints selected, 1 accepted deviation, 1 security constraint, 2 bug patterns, 2 worklog items.
- Source sections read: INDEX.md only (no individual durable memory files needed; all required entries were available in the index summary).
- Budget status: Within limits (5/5 decisions, 3/3 constraints, 1/3 deviations, 1/3 security, 2/3 bug patterns).
