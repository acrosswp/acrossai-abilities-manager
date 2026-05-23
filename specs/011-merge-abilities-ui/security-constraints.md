# Security Review — Plan Review
**Feature**: 011-merge-abilities-ui — Merge Abilities UI & Decommission Sitewide App
**Reviewer**: Security Review (automated)
**Date**: 2026-05-24
**Status**: ✅ Reviewed — Not Blocked

---

## Executive Summary

The plan describes six surgical changes to collapse two admin pages into one and
remove dead code. No new REST endpoints, no new database access, no new AJAX
handlers, and no new authentication surface are introduced. The overall security
posture is a net improvement: the overly-broad `strpos` asset guard is replaced
with exact-match comparisons, a nonce is no longer emitted on pages that don't
need it, and the merged page gains the defense-in-depth capability check that
existed on the deleted class. No P0 blockers found.

---

## Plan Artifacts Reviewed

| Artifact | Path |
|---|---|
| Specification | `specs/011-merge-abilities-ui/spec.md` |
| Implementation Plan | `specs/011-merge-abilities-ui/plan.md` |
| Memory Synthesis | `specs/011-merge-abilities-ui/memory-synthesis.md` |
| Current source | `admin/Main.php`, `admin/Partials/Menu.php`, `admin/Partials/AcrossAI_Abilities_Menu.php`, `includes/Main.php`, `src/js/abilities/index.js`, `src/js/abilities/api/client.js`, `src/js/sitewide/index.js` |

---

## Vulnerability Findings

### P1 — Must address in implementation (both already captured by plan)

#### PLAN-SEC-001 — Defense-in-Depth Capability Check Must Transfer
**Context**: `AcrossAI_Abilities_Menu::render()` contained a secondary
`current_user_can('manage_options')` check (SEC-010-01). The current
`Menu.php::contents()` has **no** secondary check — only the primary WordPress gate
via `add_menu_page(..., 'manage_options', ...)`.

**Risk**: If WordPress's page-registration gate is bypassed (e.g., via a plugin
that replaces the capability filter), the merged page would render without an
independent check.

**Required constraint**: The `current_user_can('manage_options')` + `wp_die()`
guard in `Menu.php::contents()` specified by the plan MUST be implemented. This is
not optional decoration — it replaces the defense layer that existed in the deleted
class.

**Plan coverage**: ✅ Plan Change 4 specifies this addition explicitly.

---

#### PLAN-SEC-002 — `wp_json_encode()` Is the Only Permitted Output Path
**Context**: `window.acrossaiAbilitiesManager` exposes a REST nonce, the REST base
URL, the namespace string, and the current user ID. The existing code already uses
`wp_json_encode()`. The plan retains this.

**Risk**: Any refactor that substitutes `json_encode()`, `printf()`, or raw string
interpolation would break JSON escaping and could allow XSS via a crafted REST URL
or user ID.

**Required constraint**: The inline script MUST be emitted exclusively through
`wp_add_inline_script(..., wp_json_encode($array), 'before')`. No other
serialization method is permitted.

**Data-type verification** (all values are safe):
- `wp_create_nonce('wp_rest')` → returns a hex string, no untrusted input
- `untrailingslashit(rest_url())` → WordPress-generated URL, `wp_json_encode` encodes it
- `'acrossai-abilities-manager/v1'` → hardcoded literal
- `get_current_user_id()` → integer cast by PHP, JSON-encoded as number

**Plan coverage**: ✅ Plan Change 3e specifies `wp_json_encode()` explicitly.

---

### P2 — Advisory (non-blocking, track separately)

#### PLAN-SEC-003 — Implementation Order Is a Safety Dependency
**Context**: The constructor of `Admin\Main` currently includes
`build/js/sitewide.asset.php` unconditionally (no `file_exists()` guard, unlike
the logger and abilities assets). If `sitewide.asset.php` is deleted from the build
output before the PHP `$sitewide_asset_file` property is removed from
`admin/Main.php`, the `include` will return `false`, and any later access to
`$this->sitewide_asset_file['version']` will throw a PHP TypeError.

**Risk**: Partial deployment or out-of-order changes produce a fatal PHP error on
every admin page load.

**Required constraint**: PHP changes to `admin/Main.php` (removing the `include`
and the property) MUST be applied before or simultaneously with running the clean
build. The plan's implementation order (steps 1–6 PHP/source first, step 7 build)
is correct and must be preserved.

---

#### PLAN-SEC-004 — `LogsMenu::render()` Missing Defense-in-Depth Check (Pre-existing)
**Context**: `AcrossAI_Abilities_Menu::render()` and, after this feature,
`Menu.php::contents()` both have `current_user_can('manage_options')` secondary
checks. `LogsMenu::render()` does not. This is a pre-existing inconsistency.

**Risk**: Low — `add_submenu_page(..., 'manage_options', ...)` provides the primary
gate. The secondary check is defense-in-depth only.

**Constraint**: Out of scope for this feature (FR-007). Track as a separate
follow-up task: add `current_user_can()` guard to `LogsMenu::render()`.

---

#### PLAN-SEC-005 — `client.js` Module-Level Destructure (Pre-existing)
**Context**: `src/js/abilities/api/client.js` destructures
`window.acrossaiAbilitiesManager` at module load:
```js
const { rest_namespace: restNamespace } = window.acrossaiAbilitiesManager;
```
If `window.acrossaiAbilitiesManager` is undefined (asset loaded on wrong page),
this throws a TypeError at module evaluation. `index.js` defensively handles this
with `|| {}`, but `client.js` does not.

**Risk**: Low for this feature — `is_manager_page()` exact-match gate ensures the
script only loads on the correct page and `wp_add_inline_script(..., 'before')`
guarantees the data is injected before the script evaluates. However, it is a
fragile pattern.

**Constraint**: Pre-existing issue, not introduced by this feature. Track as a
separate follow-up: add null-guard to `client.js` destructure.

---

## Confirmed Secure Patterns

| Pattern | Verification |
|---|---|
| Primary capability gate on merged page | `add_menu_page(..., 'manage_options', ...)` in `Menu.php::main_menu()` — unchanged ✓ |
| Defense-in-depth capability gate | Plan adds `current_user_can('manage_options')` to `Menu.php::contents()` — mirrors deleted class ✓ |
| Inline script output path | `wp_json_encode()` only, typed PHP array, no raw interpolation ✓ |
| `is_manager_page()` comparison | `===` strict comparison against stable WordPress-generated string ✓ — MORE secure than replaced `strpos` |
| Asset scope reduction | New guard (exact match on 2 pages) is stricter than old `strpos` guard (3 pages broadly) — reduces nonce emission surface ✓ |
| Sitewide nonce removal | `window.acrossaiAbilitiesSitewide` (nonce + user ID) no longer emitted on any page — net security improvement ✓ |
| No new REST/AJAX surface | FR-008 explicitly bans REST controller changes; confirmed in plan ✓ |
| No DB or option changes | Plan states N/A storage; confirmed no DB classes touched ✓ |
| Hook removal confined to `includes/Main.php` | AC-HOOKS-MAIN respected ✓ |
| Asset enqueue changes confined to `admin/Main.php` | AC-ENQUEUE-ADMIN respected ✓ |
| Hardcoded hook suffix correctness | WordPress generates `toplevel_page_{slug}` deterministically for `add_menu_page`; hardcoding `'toplevel_page_acrossai-abilities-manager'` is correct and eliminates timing dependency on singleton ✓ |

---

## Security-Architecture Conflicts

**None found.**

The plan is fully consistent with the project security architecture. No conflict
exists between the trust boundary changes and the Constitution §IV Security First
requirement. The deletion of `AcrossAI_Abilities_Menu` removes no authorization
logic that is not being explicitly re-added.

---

## Constraints Summary for Implementation

| ID | Constraint | Enforcement |
|---|---|---|
| SC-011-01 | `Menu.php::contents()` MUST include `current_user_can('manage_options')` + `wp_die()` guard before any output | PHPCS (no-output-before-check), code review |
| SC-011-02 | `window.acrossaiAbilitiesManager` MUST be serialized exclusively via `wp_json_encode($typed_array)` — no `json_encode`, no interpolation | Code review |
| SC-011-03 | PHP changes to `admin/Main.php` MUST be deployed before the clean build step that removes `sitewide.asset.php` | Implementation order in plan |
| SC-011-04 | `is_manager_page()` MUST use `===` (strict equality) against the hardcoded string `'toplevel_page_acrossai-abilities-manager'` — no `strpos`, no loose comparison | PHPStan type checks |

---

## Return Values

**STATUS**: `Reviewed`

**KEY_FINDINGS**:
- `P1` — PLAN-SEC-001: Defense-in-depth `current_user_can()` check must transfer from deleted class to `Menu.php::contents()` — already in plan, must not be skipped
- `P1` — PLAN-SEC-002: `wp_json_encode()` is the only permitted output path for inline script data — already in plan, must be preserved
- `P2` — PLAN-SEC-003: Implementation order is a safety dependency (PHP changes before build artifact removal)
- `P2` — PLAN-SEC-004: `LogsMenu::render()` missing defense-in-depth check — pre-existing, track separately
- `P2` — PLAN-SEC-005: `client.js` module-level destructure is fragile — pre-existing, track separately

**CONSTRAINTS**:
- SC-011-01: Capability gate in `Menu.php::contents()`
- SC-011-02: `wp_json_encode()` only for inline script
- SC-011-03: PHP changes before build
- SC-011-04: Strict `===` in `is_manager_page()`

**Security-Architecture Conflicts**: None
