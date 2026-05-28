# Security Review — Plan Review

**Feature**: 018-user-access-form
**Date**: 2026-05-28
**Artifacts Reviewed**: `plan.md`, `spec.md`, `memory-synthesis.md`, `docs/memory/security-constraints.md`

---

## Executive Summary

Feature 018 is a low attack-surface changeset. No new REST endpoints, no new DB queries, no
new user input processing, and no new capability checks are introduced. The feature is entirely
additive: it exposes one server-computed boolean flag to the client and renders an existing vendor
component that manages its own REST lifecycle. Two constraints require explicit task-level gates:
(1) the post-upgrade security revalidation for CHANGE-1, and (2) confirmation that the `AccessControl`
component's REST calls cannot be triggered with a manipulated `resourceKey`.

**Overall Risk**: LOW. No blocking vulnerabilities found. Two advisory items below.

---

## Plan Artifacts Reviewed

| Artifact | Status |
|----------|--------|
| `specs/018-user-access-form/plan.md` | ✅ Reviewed |
| `specs/018-user-access-form/spec.md` | ✅ Reviewed |
| `specs/018-user-access-form/memory-synthesis.md` | ✅ Reviewed |
| `docs/memory/security-constraints.md` | ✅ Reviewed (SEC-04 only) |

---

## Vulnerability Findings

### PLAN-SEC-001 — Client-side Feature Flag Is Not a Server-Side Guard (Severity: LOW / Advisory)

**Finding**: `access_control_available` is serialized into `window.acrossaiAbilitiesManager` on
the client. A user with browser devtools access can flip this value to `true`, which would cause
the `AccessControl` component to mount even when the PHP library is unavailable.

**Impact**: The `AccessControl` component would mount with a valid `resourceKey` and attempt REST
calls to `wpb-ac/v1/rules/acrossai-abilities/{slug}`. Those REST endpoints validate permissions
server-side independently. If the library is truly absent (not just its PHP check bypassed), the
REST endpoint would return 404 or 403 — no unauthorized access granted.

**Mitigation (already in plan)**: The plan correctly scopes `access_control_available` as a
client rendering gate, not an authorization gate. Server-side access control is enforced by the
`wpb-ac/v1` REST endpoints. No additional code change required.

**Task Gate**: Document this client-only scope in a code comment on the `access_control_available`
key in `admin/Main.php`. Add to tasks.

---

### PLAN-SEC-002 — Post-Upgrade Security Revalidation Must Be an Explicit Blocking Task (Severity: MEDIUM / Process)

**Finding**: DEC-REVALIDATE-SECURITY-POST-UPGRADE requires explicit post-CHANGE-1 verification of:
- `is_available()` returns strict `: bool` (not nullable) — BUG-AC-NULL-RETURN-SILENT-FAIL
- `user_has_access()` uses `===` not `==` — SEC-04
- `admin_notices` hook still displays when library is absent — DEC-FAIL-OPEN-NOTICE

**Impact**: If the v1.0.2 upgrade silently changes any of these, a security regression could go
undetected until production. The plan mentions these checks but they must be enforced as a BLOCKING
task step, not an optional verification.

**Mitigation**: The plan includes Phase 1 post-upgrade verification table. Tasks must implement
this as an explicit T-level task with a block gate — CHANGE-2 through CHANGE-5 must not proceed
until Phase 1 verification passes.

**Task Gate**: Task for Phase 1 must be explicitly marked BLOCKING for the remaining phases.

---

## Confirmed Secure Patterns

| Pattern | Assessment |
|---------|------------|
| `wp_json_encode()` for inline script | ✅ Correct escaping for JSON in `<script>` context. No XSS risk from bool flag. |
| `namespace="acrossai-abilities"` hardcoded | ✅ Literal string, no injection surface. |
| `resourceKey={savedAbility.ability_slug}` | ✅ Sourced from server REST response stored in Redux. Not user-typed input. Server validates slug on REST routes. |
| `nonce={abilitiesConfig.nonce \|\| ''}` | ✅ Server-generated nonce, same pattern as existing nonce middleware. AccessControl uses it for its own `apiFetch` calls. |
| `restApiRoot={abilitiesConfig.rest_url \|\| '/wp-json'}` | ✅ Server-controlled URL, not user-supplied. |
| No new capability checks needed | ✅ `enqueue_scripts()` already gated to `is_manager_page()`. Admin page requires `manage_options`. |
| `::instance()->is_available()` | ✅ Correct singleton access. No direct instantiation. |
| No new SQL queries | ✅ Feature introduces no DB reads or writes. |
| No new file I/O | ✅ Composer update handled by Composer's own verified download. |
| FQCN in admin/Main.php | ✅ Correct; no ambiguous namespace resolution. PHPStan L8 will catch any mismatches. |

---

## Security-Architecture Constraints for Tasks

| ID | Constraint | Source |
|----|------------|--------|
| SEC-018-01 | CHANGE-1 post-upgrade verification (is_available bool, SEC-04, admin notice) is a BLOCKING task gate | DEC-REVALIDATE-SECURITY-POST-UPGRADE |
| SEC-018-02 | `access_control_available` in admin/Main.php must include inline comment: "client rendering gate only — server authorization in wpb-ac/v1 REST endpoints" | PLAN-SEC-001 |
| SEC-018-03 | No user input may be passed to AccessControl props — all props must be server-controlled or hardcoded literals | PLAN-SEC-001 + spec FR-003 |
| SEC-018-04 | `is_available()` return value must NOT be used as an authorization check anywhere in PHP — it is a library-presence flag only | PLAN-SEC-001 |

---

## Follow-Up Items (Non-Blocking)

None. All advisory items are captured as task-level gates above.
