# Security Review — Plan Artifact

**Feature**: 017 Logger Module Constitution Compliance
**Date**: 2026-05-28
**Artifacts Reviewed**: `spec.md`, `plan.md`, `memory-synthesis.md`, source files

---

## Executive Summary

This feature makes compliance-only changes: no new endpoints, no new data inputs, no new capabilities
granted, and no schema changes. The primary security change is FIX-4, which adds missing
`sanitize_callback` at the REST entry point — directly remediating a pre-existing OWASP A03 risk.
All other fixes are architectural compliance; security posture is preserved or improved.

**Overall Risk**: Low. One pre-existing medium-risk gap is addressed by FIX-4. Two minor out-of-scope
gaps are noted for follow-up.

---

## Plan Artifacts Reviewed

- `specs/017-logger-constitution-fix/spec.md`
- `specs/017-logger-constitution-fix/plan.md`
- `specs/017-logger-constitution-fix/memory-synthesis.md`
- `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` (register_routes scan)
- `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` (permission callback)
- `includes/Modules/Logger/AcrossAI_Ability_Logger.php` (MCP context lifecycle)

---

## Vulnerability Findings

### MEDIUM — Addressed by FIX-4: Missing `sanitize_callback` on `source` and `status`

**Status**: Addressed in this feature.
**Risk**: OWASP A03 — Injection. `source` and `status` REST args reach the query builder without
sanitization. Although the enum allowlist in `get_logs()` provides a second validation layer, the
entry point lacks sanitize_callback, meaning unsanitized values transit the request lifecycle.
**Fix**: FIX-4 adds `'sanitize_callback' => 'sanitize_text_field'` to both args. The enum allowlist
inside `get_logs()` is preserved (FR-007) — both layers are required and complementary.
**Verification**: `grep -n 'sanitize_callback' includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` must return ≥ 2 results.

---

### LOW (OUT OF SCOPE) — `sort_by` and `order` args lack `sanitize_callback`

**Status**: Pre-existing gap. Not introduced by this feature. Not in scope for Feature 017.
**Risk**: Low. Both args have `enum` validation — WP REST API rejects non-enum values before reaching
the handler. Values are passed to a query builder using `$wpdb->prepare()`. Risk of injection is minimal.
**Recommendation**: Add `sanitize_callback` to `sort_by` and `order` in a follow-up cleanup task
(defensive depth, not urgent).

---

### LOW (OUT OF SCOPE) — `user_id` arg lacks `sanitize_callback`

**Status**: Pre-existing gap. Not introduced by this feature. Not in scope for Feature 017.
**Risk**: Low. `type => integer` in the WP REST schema will coerce/reject non-integer values.
**Recommendation**: Add `'sanitize_callback' => 'absint'` in a follow-up cleanup task.

---

## Security-Architecture Boundary Checks

### FIX-1 — Hook Migration

- **Trust boundary**: Hook callbacks (`capture_mcp_server_id`, `start_pending_entry`, etc.) are
  preserved unchanged. Only the registration mechanism changes from direct `add_filter`/`add_action`
  to the Loader.
- **Risk**: None. Identical hooks, identical priorities, identical `$accepted_args` counts.
- **Status**: Secure ✅

### FIX-5 — Source Detector Singleton (MCP Context Lifecycle)

- **Critical**: `capture_mcp_server_id()` (line 140) calls `AcrossAI_Logger_Source_Detector::set_mcp_context($server_id)`. `finish_pending_entry()` (line 262) calls `AcrossAI_Logger_Source_Detector::clear_mcp_context()`. Both are among the 6 call sites updated in T005.
- **Risk if missed**: If `clear_mcp_context()` is not updated to `::instance()->clear_mcp_context()`, the static call will fail (non-static method on static call in PHP 8+) or create a separate static-path invocation. **The plan explicitly includes this in T005 step 10** — all 6 call sites updated.
- **Singleton state persistence**: The singleton instance holds `$is_mcp_context` and `$mcp_server_id`. After FIX-5, these are instance properties. The singleton persists per request. Semantics are identical to the pre-fix static state. `clear_mcp_context()` clears both properties; this is preserved.
- **Status**: Secure ✅ — plan correctly accounts for all call sites.

### REST Permission Callback

- `AcrossAI_Logger_Controller::check_permission()` gates all logs endpoints with `current_user_can('manage_options')`.
- Unchanged by this feature. **Status**: Secure ✅

### FIX-3 — Singleton Access to get_logs()

- Removes `static` keyword from `get_logs()`. Call site updated to `::instance()->get_logs()`.
- No change to method body or query logic. Enum allowlist and `$wpdb->prepare()` usage unchanged.
- **Status**: Secure ✅

---

## Confirmed Secure Patterns

| Pattern | Evidence |
|---|---|
| `manage_options` capability check on logs endpoint | `AcrossAI_Logger_Controller::check_permission()` line 91-93 — unchanged |
| `sanitize_text_field` at REST entry point for string params | FIX-4 adds for `source` + `status`; `search` already had it |
| `absint` for integer params | `page`, `per_page` already have it |
| `sanitize_key` for slug params | `ability_slug` already has it |
| Enum allowlist secondary validation | `get_logs()` enum check preserved (FR-007) |
| `$wpdb->prepare()` in query builder | Pre-existing; not modified |
| MCP context cleared after each ability execution | `clear_mcp_context()` call in `finish_pending_entry()` preserved via T005 call site update |

---

## Implementation Guard — Security Watchpoints

Before each FIX-4 commit:
- [ ] `source` arg has `'sanitize_callback' => 'sanitize_text_field'`
- [ ] `status` arg has `'sanitize_callback' => 'sanitize_text_field'`
- [ ] `get_logs()` enum allowlist lines are byte-for-byte unchanged

Before each FIX-5 commit:
- [ ] `AcrossAI_Logger_Source_Detector::clear_mcp_context()` → `::instance()->clear_mcp_context()` updated (line 262)
- [ ] `AcrossAI_Logger_Source_Detector::set_mcp_context($server_id)` → `::instance()->set_mcp_context($server_id)` updated (line 140)
- [ ] `AcrossAI_Logger_Source_Detector::detect_mcp_server_id()` → `::instance()->detect_mcp_server_id()` updated (line 170)
- [ ] No static calls to `AcrossAI_Logger_Source_Detector::` remain in `AcrossAI_Ability_Logger.php`
