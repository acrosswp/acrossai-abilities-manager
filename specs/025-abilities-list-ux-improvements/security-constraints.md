# Security Review — Plan: Abilities List UX Improvements (Feature 025)

**Reviewed**: 2026-06-02
**Reviewer**: speckit.security-review.plan
**Status**: PASS — 0 blocking findings, 2 low-severity hardening advisories

---

## Executive Summary

Feature 025 is a low security-risk change. The six improvements are either pure CSS, pure client-side React state, or thin additions to already-hardened PHP/JS code paths. No new REST routes, no new DB tables, no new user-facing HTML input beyond one integer settings field. The only PHP changes are adding `perPage` to the existing inline script in `Admin\Main::enqueue_scripts()` and registering a new integer option via the WordPress Settings API in `SettingsMenu`. All existing auth gates (`manage_options` permission callback, nonce, `current_user_can`) are preserved and unchanged.

Two hardening advisories are raised at **Low** severity. Neither blocks implementation.

---

## Plan Artifacts Reviewed

| Artifact | Status |
|----------|--------|
| `specs/025-abilities-list-ux-improvements/plan.md` | ✓ Read |
| `specs/025-abilities-list-ux-improvements/spec.md` | ✓ Read |
| `specs/025-abilities-list-ux-improvements/memory-synthesis.md` | ✓ Read |
| `docs/memory/security-constraints.md` (project-level) | ✓ Read |
| `docs/memory/BUGS.md` (security-relevant patterns) | ✓ Read (BUG-LOOSE-COMPARISON-BYPASS, BUG-SEC04-EMPTY-AUDIT-MISS, BUG-AC-NULL-RETURN-SILENT-FAIL) |
| `docs/memory/INDEX.md` (SEC-01 through SEC-04, DEC-PERM-CB, DEC-EARLY-404-REST-CHECK) | ✓ Read |
| `admin/Main.php` (enqueue_scripts — inline script injection point) | ✓ Read |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (delete_override endpoint) | ✓ Read |

---

## Vulnerability Findings

### FINDING-SEC-01 — Hardening Advisory: Client-side `perPage` clamp (LOW)

**Area**: Input validation — client-side
**Location**: `src/js/abilities/components/AbilitiesList.jsx` — `per_page: perPage` passed to `dispatch.fetchAbilities`

**Description**:
`window.acrossaiAbilitiesManager.perPage` is PHP-cast to `(int)` before `wp_json_encode()`, so it arrives as an integer on a normal page load. However, the value is accessible and writable from the browser console. If a user manipulates it to a very large number (e.g., 99999), the REST request sends `per_page=99999`. The REST endpoint is the authoritative gate (it enforces its own server-side limits), but sending an extreme value wastes a round-trip.

**Recommended Fix** (implement in T1/T2):
```js
const perPage = Math.min(200, Math.max(1, parseInt(window.acrossaiAbilitiesManager?.perPage, 10) || 20));
```

**Why 200**: Matches the Settings API sanitizer upper bound (`sanitize_per_page()` clamps to 200), making the client and server limits identical.

**Severity**: Low — server is authoritative; this is client-side defence-in-depth only.

---

### FINDING-SEC-02 — Hardening Advisory: Double-bang normalisation at all column visibility render sites (LOW)

**Area**: Input normalisation — localStorage deserialization
**Location**: `src/js/abilities/components/AbilitiesList.jsx` — `loadColumnPrefs()` + every `{visibleColumns.key && ...}` render site

**Description**:
`localStorage.getItem()` returns a JSON string that a user or browser extension could modify to store non-boolean values (e.g., `"yes"`, `1`, `null`, `"true"`). The `loadColumnPrefs()` function merges saved values with `COLUMN_DEFAULTS` using spread, which preserves the raw saved type. If a render site uses `visibleColumns.key` (truthy check) it would still work, but if it uses `visibleColumns.key === true` (strict equality) non-boolean truthy values would be treated as hidden.

**Recommended Fix** (implement in T6):
1. Normalise on load: `const val = saved[key]; result[key] = val === undefined ? COLUMN_DEFAULTS[key] : !!val;`
2. Use `!!visibleColumns[key]` (double-bang) — not `=== true` — at all conditional render sites.

**Severity**: Low — already mitigated by plan's `!!visibleColumns[key]` pattern; confirm it is applied consistently during implementation.

---

## Confirmed Secure Patterns

### Authentication & Authorization

| Check | Evidence | Verdict |
|-------|----------|---------|
| Clear All Overrides dispatches to existing `DELETE /wp-abilities/v1/abilities/{slug}/override` endpoint | `AcrossAI_Abilities_Write_Controller.php` line 128: `'permission_callback' => $permission` (shared `manage_options` orchestrator gate) | ✓ **Secure** — no new auth surface |
| All four REST operations in write controller gated on `manage_options` | Lines 89, 99, 128, 156, 188 share `$permission = array( AcrossAI_Abilities_Rest_Controller::instance(), 'check_permission' )` | ✓ **Secure** |
| `render_per_page_field()` only reachable by `manage_options` users | `SettingsMenu::render()` begins with `current_user_can('manage_options')` guard | ✓ **Secure** |
| `sanitize_per_page()` registered via `register_setting()` — runs on `options.php` POST | WP Settings API enforces nonce + capability check before invoking sanitize callbacks | ✓ **Secure** |

### Input Handling & Output Escaping

| Check | Pattern | Verdict |
|-------|---------|---------|
| `sanitize_per_page()` return type | `absint()` + range clamp → always returns `int` in [1, 200] or 20 | ✓ **Secure** |
| `render_per_page_field()` value output | `esc_attr( (string) $value )` | ✓ **Secure** |
| `render_per_page_field()` description output | `esc_html__( '...', 'acrossai-abilities-manager' )` | ✓ **Secure** |
| `perPage` PHP→JS injection | `(int) get_option(...)` before `wp_json_encode()` — integer in JSON output | ✓ **Secure** — no XSS surface |
| Description column JSX rendering | React renders `item.description` as a text node — automatic HTML escaping | ✓ **Secure** |
| Description `title` attribute | JSX attribute → React escapes attribute value | ✓ **Secure** |
| Show in REST column | Renders boolean flag as static string literal — no user content involved | ✓ **Secure** |

### Data Flow & Privacy

| Check | Verdict |
|-------|---------|
| Column preferences stored in `localStorage` only — never sent to server | ✓ No server-side exposure; no PII involved |
| `localStorage` key `acrossai_abilities_columns` contains only booleans — no capability/identity data | ✓ No sensitive data in localStorage |
| `perPage` is a non-sensitive display preference — safe to expose in inline script | ✓ No capability, nonce, or identity leakage |
| `window.acrossaiAbilitiesManager` already contains `nonce`, `rest_url`, `current_user_id` — `perPage` addition does not introduce new fields beyond display prefs | ✓ Existing object already considered public to authenticated admin users |

### Existing Security Constraints Verified (from `docs/memory/security-constraints.md`)

| Constraint | Applies to Feature 025 | Status |
|-----------|----------------------|--------|
| SEC-01: `sanitize_ability_slug()` at REST endpoints receiving a slug | `clearOverrides` uses `item.ability_slug` from REST response (already sanitized server-side) | ✓ Not regressed |
| SEC-02: `before_save` hook fires on sanitized `$fields` only | No new save paths in this feature | ✓ Not applicable |
| SEC-03: `AcrossAI_Abilities_Table::$global = false` — per-site prefix | No new DB tables or queries | ✓ Not applicable |
| SEC-04: Strict type comparison for access control checks | No new PHP access control code | ✓ Not applicable |
| DEC-PERM-CB: Permission callback injection pattern | Unchanged — `delete_override` reuses shared orchestrator `check_permission()` | ✓ Not regressed |
| DEC-EARLY-404-REST-CHECK: Early 404 before DB lookups | Unchanged — `delete_override` controller already follows this pattern | ✓ Not regressed |

### Destructive Action Guards

| Action | Guard | Verdict |
|--------|-------|---------|
| Clear All Overrides row button | `window.confirm('Clear all overrides for this ability? This cannot be undone.')` before dispatch | ✓ Consistent with existing Delete button pattern in same file |

---

## Trust Boundary Map

```
Browser (localStorage — column prefs, booleans only)
         ↕ no server contact
AbilitiesList.jsx
         ↕ authenticated REST (nonce + manage_options)
REST /wp-abilities/v1/abilities          ← pagination (GET, existing)
REST /wp-abilities/v1/abilities/{slug}/override  ← clear overrides (DELETE, existing)
         ↕
WordPress DB (abilities + overrides tables)

Settings form → options.php (WP nonce + manage_options)
         ↕
wp_options: acrossai_abilities_per_page  ← NEW (integer, non-sensitive)
         ↕ get_option() cached read
Admin\Main::enqueue_scripts()
         ↕ wp_json_encode((int))
window.acrossaiAbilitiesManager.perPage  ← NEW key on existing object
```

**No new trust boundaries introduced.** All new data flows are either:
- Client-only (localStorage, React state)
- Extensions of existing authenticated paths (Settings API, existing inline script)

---

## Pre-Implementation Security Checklist

- [ ] **T1/T2** — `perPage` clamped client-side: `Math.min(200, Math.max(1, parseInt(...) || 20))` (FINDING-SEC-01)
- [ ] **T6** — `loadColumnPrefs()` normalises values with `!!val` not raw spread (FINDING-SEC-02)
- [ ] **T6** — All column render sites use `!!visibleColumns[key]` not `=== true` (FINDING-SEC-02)
- [ ] **T2** — `sanitize_per_page()` is a named public method, not a closure
- [ ] **T2** — `render_per_page_field()` uses `esc_attr()` on value and `esc_html__()` on description
- [ ] **T2** — `perPage` added to **existing** `wp_add_inline_script` array, not a new call
- [ ] **All PHP** — No `empty()` calls introduced on security-sensitive fields (BUG-SEC04-EMPTY-AUDIT-MISS)
- [ ] **All PHP** — PHPStan level 8 passes (silence = pass, exit 1 = failure — BUG-PHPSTAN-SILENT-PASS)
