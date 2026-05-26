# Security Review: Abilities React UI + Admin Shell (Spec 010)

**Review Date**: 2026-05-23
**Reviewed Artifacts**: plan.md, spec.md, memory-synthesis.md, docs/memory/security-constraints.md,
docs/memory/BUGS.md, docs/memory/DECISIONS.md, specs/009-abilities-business-logic-rest/security-constraints.md,
.specify/memory/CONSTITUTION.md
**Status**: Review complete — all 4 findings RESOLVED in plan.md ✅

---

## Executive Summary

Spec 010 is a presentation layer that sits entirely above the Spec 009 REST boundary. Its trust model
is clean: `manage_options` guards the submenu page, the REST nonce gates all API calls, and all
data-mutation validation (slug prefix injection, identity-field lock, `php_code` blocked-function scan,
schema depth check) is delegated to Spec 009. That is the right division of responsibility.

One **medium-severity gap** was found: the plan specifies a confirmation dialog for single-row delete
but has **no equivalent guard for bulk delete**. Bulk deleting multiple published abilities without
confirmation is irreversible and not recoverable from the UI. This must be addressed before
implementation of the bulk-action toolbar.

Three **low-severity** items require explicit wording in implementation tasks: (1) a secondary
`current_user_can()` check inside `render()`, (2) an in-form warning label in the `php_code`
config block, and (3) strict confirmation count for bulk destructive actions.

No blocking security issues. Proceed to `/speckit-tasks` after accepting the four findings below.

---

## Plan Artifacts Reviewed

| Artifact | Path |
|---|---|
| Plan | `specs/010-abilities-react-ui/plan.md` |
| Spec | `specs/010-abilities-react-ui/spec.md` |
| Memory synthesis | `specs/010-abilities-react-ui/memory-synthesis.md` |
| Global security constraints | `docs/memory/security-constraints.md` |
| Known bug patterns | `docs/memory/BUGS.md` |
| Upstream security review | `specs/009-abilities-business-logic-rest/security-constraints.md` |
| Constitution | `.specify/memory/CONSTITUTION.md` |

---

## Trust Boundaries and Security Assumptions

### Intended Trust Model (confirmed correct in plan)

| Layer | Gate | Confirmed in plan? |
|---|---|---|
| WordPress admin submenu | `manage_options` at `add_submenu_page` | ✅ Phase 1A |
| `render()` page callback | Secondary `current_user_can()` (SEC-010-01) | ⚠️ Required — see Finding 2 |
| REST API calls | `wp_create_nonce('wp_rest')` via `apiFetch` nonce middleware | ✅ Phase 2A |
| REST endpoint permissions | `manage_options` — all Spec 009 endpoints | ✅ Spec 009 responsibility |
| Slug prefix injection | Server prepends `acrossai-abilities/`; client sends suffix only | ✅ Spec 009 Write Controller |
| Override identity lock | Write Controller strips identity fields from override-row updates | ✅ SC-007 / Spec 009 |
| `php_code` validation | Blocked-function scan + syntax check in `AcrossAI_Abilities_Validator` | ✅ Spec 009 |
| localStorage | View layout preferences only — no ability data or tokens | ✅ Plan §2E |

---

## Vulnerability Findings

### FINDING-010-01 — Bulk Delete has no confirmation dialog (MEDIUM)

**OWASP Category**: A04:2021 — Insecure Design
**Severity**: Medium
**Status**: ✅ RESOLVED — `window.confirm()` guard added to bulk-apply handler in plan.md §2E

**Location**: `plan.md §2E — AbilitiesList bulk actions` / `spec.md FR-009`

**Description**:
The plan specifies a confirmation dialog for single-row delete:
> `delete` — confirm dialog → `deleteAbility(id)` (db rows only)

However, for **bulk delete** (`Bulk Actions → Delete → Apply`), neither the plan nor the spec
defines an equivalent confirmation step. Bulk deletion of multiple published abilities is:
- Irreversible via the UI (no undo/restore action exists in scope)
- Potentially site-breaking (deletes published abilities that may be in active use by MCP clients)
- Triggered by a single "Apply" click after selecting rows and choosing Delete from a dropdown

**Risk**:
An administrator accidentally selecting all rows and applying Delete destroys the entire custom
abilities catalog without a single friction point to prevent it.

**Required fix**:
Before implementing the bulk-action toolbar in `AbilitiesList.jsx`, add a confirmation step:

```jsx
// In bulk-apply handler — before dispatching bulk delete
if ( action === 'delete' ) {
    const ok = window.confirm(
        `Delete ${ selectedIds.length } abilities? This cannot be undone.`
    );
    if ( ! ok ) return;
}
```

Spec alignment: FR-024 specifies a confirmation before DELETE on single-row; FR-009 (bulk) should
carry the same requirement by implication. Add this as an implementation task.

---

### FINDING-010-02 — `render()` has no secondary capability check (LOW)

**OWASP Category**: A01:2021 — Broken Access Control
**Severity**: Low
**Status**: ✅ RESOLVED — `current_user_can('manage_options') + wp_die()` guard added to `render()` in plan.md §1A

**Location**: `plan.md §1A — AcrossAI_Abilities_Menu::render()`

**Description**:
WordPress's `add_submenu_page()` provides the first capability gate. However, `render()` is
a public method and the returned callback can theoretically be invoked directly (e.g., via a
misconfigured plugin or test harness). Defense-in-depth requires a guard at render time.

**Required fix** (plan already specifies this — must be in the implementation task):
```php
public function render(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
    }
    ?>
    <div class="wrap">
        <div id="acrossai-abilities-root"></div>
    </div>
    <?php
}
```

**Reference**: SEC-04 (strict type comparison for access checks); Constitution §IV Security First.

---

### FINDING-010-03 — `php_code` config block has no in-form execution warning (LOW)

**OWASP Category**: A05:2021 — Security Misconfiguration (UX/awareness gap)
**Severity**: Low
**Status**: ✅ RESOLVED — execution warning label with blocked-function list added to `CallbackConfigField.jsx` `php_code` branch in plan.md §2G

**Location**: `plan.md §2G — CallbackConfigField.jsx (php_code branch)`

**Description**:
The `php_code` callback type executes arbitrary PHP with `$input` in scope on the server.
The plan specifies a dark monospace textarea but no visible warning label informing the admin
of the execution context and trust implications. An admin unfamiliar with the risk might
paste user-supplied code without understanding it runs server-side with plugin-level access.

This is not a code-execution risk per se (only `manage_options` users can set it, same trust
level as editing `functions.php`). But consistent with the BUGS.md principle of making security
implications explicit at the authoring surface, a warning label should be shown.

**Required fix** — add above the textarea in `CallbackConfigField.jsx`:
```jsx
{ 'php_code' === callbackType && (
    <p className="description" style={{ color: '#996600' }}>
        ⚠ PHP code runs server-side with plugin-level access.
        Variable <code>$input</code> contains the ability input.
        Blocked functions: eval, exec, system, shell_exec, file_put_contents, unlink.
    </p>
) }
```

---

### FINDING-010-04 — Partial-save hook pattern: Spec 009 must pass full row to `after_save` (LOW / UPSTREAM)

**OWASP Category**: A08:2021 — Software and Data Integrity Failures
**Severity**: Low (Spec 009 responsibility — noted here for cross-spec coordination)
**Status**: ✅ RESOLVED — SEC-010-04 constraint added to plan.md §Security Constraints; must appear as explicit subtask in Spec 009 tasks.md

**Location**: `docs/memory/BUGS.md — partial-save paths fire after_save with incomplete $fields`

**Description**:
Spec 010 sends sparse update payloads via `updateAbility(id, changedFields)`. When the Spec 009
Write Controller processes these, if it fires an `acrossai_abilities_after_save` hook it MUST
fetch the complete saved row after the DB write and pass the full row to the hook — not just
the `$fields` subset it received. This is the exact bug pattern documented in BUGS.md (2026-05-17).

**Required check in Spec 009 implementation**:
- After every `updateAbility()` call completes a DB write, call `get_ability_by_id($id)` to
  fetch the full row before firing any post-save action hook.
- Pass the full `AcrossAI_Sitewide_Row` / formatted array to the hook, not the sparse input.

**Reference**: `docs/memory/BUGS.md — 2026-05-17 — Partial-save paths fire after_save with incomplete $fields`.

---

## Confirmed Secure Patterns

| Pattern | Evidence |
|---|---|
| `manage_options` at submenu registration | `add_submenu_page(..., 'manage_options', ...)` — plan §1A |
| `===` strict comparison in page guard | `is_abilities_custom_page()` uses `===` — plan §1B + SEC-04 |
| REST nonce via `wp_add_inline_script(..., 'before')` | Established inline-script pattern; not `wp_localize_script` |
| `apiFetch.createNonceMiddleware(nonce)` | Nonce injected once at app bootstrap — plan §2A |
| No client-side slug-prefix injection | Suffix-only form field; server prepends `acrossai-abilities/` |
| Override identity-field lock (SC-007) | Spec 009 Write Controller strips identity fields; client never sends them |
| `php_code` blocked-function scan | `AcrossAI_Abilities_Validator` (Spec 009) — client shows server error inline |
| `localStorage` scope limited to view prefs | No ability data, nonces, or user tokens stored client-side |
| React JSX auto-escaping | All string values in JSX are escaped by React; no `dangerouslySetInnerHTML` in plan |
| `rest_namespace` exposure acceptable | REST namespaces are publicly discoverable via `/wp-json` — not a secret |
| `beforeunload` uses native browser dialog | No ability data included in dialog message string |
| `current_user_id` in inline script | Not sensitive; same pattern as existing `acrossaiAbilitiesSitewide` config object |
| XSS via ability label/slug in list | DataViews renders field values as React children — framework-level escaping applies |
| Nonce on all REST endpoints | Inherited from Spec 009 `check_permission()` via `manage_options` check |

---

## Implementation Requirements (all resolved in plan.md)

These MUST appear as explicit subtasks in `tasks.md`:

| ID | Requirement | Severity | Phase | Status |
|---|---|---|---|---|
| SEC-010-01 | `render()` secondary `current_user_can('manage_options')` check + `wp_die()` | Low | 1A | ✅ In plan.md §1A |
| SEC-010-02 | Bulk delete: `window.confirm()` before dispatching bulk delete action | Medium | 2E | ✅ In plan.md §2E |
| SEC-010-03 | `php_code` block: add in-form execution warning label | Low | 2G | ✅ In plan.md §2G |
| SEC-010-04 | Spec 009 Write Controller: fetch full row before firing any post-save hook | Low | Spec 009 | ✅ In plan.md §Security Constraints |
