# Security Review — Plan Artifacts

**Feature**: 021 Plugin Check Remaining Cleanup
**Date**: 2026-05-31
**Artifacts Reviewed**: plan.md, spec.md, memory-synthesis.md

## Executive Summary

This feature is primarily a code-quality and compliance cleanup. The primary security event is the **removal of `eval()`** from production plugin code, which is an unambiguous security improvement. No new authentication surfaces, trust boundaries, or data flows are introduced. The registered-callback model replaces the `eval()` approach with a safer allow-listed dispatch; the key trust boundary is that registered callbacks must come from version-controlled plugin/theme code, not from database rows.

No blocking security issues found. Two advisory items require attention during implementation.

---

## Vulnerability Findings

### ADV-001 (Advisory) — Registered-callback model: DB-stored callback keys must fail closed

**Risk**: If `callback_config['callback']` is absent, empty, or an unregistered key, the processor must return `WP_Error`, not fall through to a `null` callable or silently no-op.

**Required behaviour** (already in plan):
- `sanitize_key()` applied before array lookup
- `isset( $callbacks[$callback] ) && is_callable( $callbacks[$callback] )` guard required
- Missing/unregistered key → `WP_Error('unsupported_callback_type')`

**Status**: Plan addresses this correctly. Implementer must verify `WP_Error` path is hit — not a silent return.

---

### ADV-002 (Advisory) — `apply_filters('acrossai_abilities_registered_callbacks')` trust boundary

**Risk**: Any plugin or theme hooked to `acrossai_abilities_registered_callbacks` can register arbitrary callables. This is intentional (it is the replacement for `eval()`), but the security boundary must be documented: only trusted plugin/theme code that is version-controlled should register callbacks.

**Required documentation**: The plan already describes this trust model. Add a PHPDoc comment to the filter usage site noting the trust expectation (trusted code registers, DB stores only the key).

**Status**: Advisory — no implementation blocker, but an inline comment at the `apply_filters` call is recommended.

---

### ADV-003 (Advisory) — `REQUEST_URI` used in boolean path detection after sanitization

**Risk**: `sanitize_text_field()` on `REQUEST_URI` may strip some URL characters (e.g. `%2F`) that would be relevant if the URI were used for routing or comparison. However, the plan explicitly states this is boolean `strpos()` detection only, never echoed or used in SQL. After `wp_unslash()` + `sanitize_text_field()`, the only remaining usage is `strpos()` matching — acceptable.

**Status**: No action needed. Plan is correct.

---

## Confirmed Secure Patterns

| Pattern | How Confirmed |
|---------|--------------|
| `eval()` removed from production code (OWASP A03 remediation) | CHANGE-4 replaces with allow-listed `call_user_func()` |
| `$input` at execution time is no longer passed to `eval()` | Registered-callback model: $input passed to a trusted callable, not to `eval()` |
| `sanitize_key()` applied before callback lookup | Stated explicitly in plan Phase 3 |
| `wp_unslash()` + `sanitize_text_field()` on `$_SERVER['REQUEST_URI']` | CHANGE-5 |
| `$wpdb->prepare()` with `%i` for all table identifiers | CHANGE-2, CHANGE-3, CHANGE-7 |
| Suppression codes are inline and exact — not workflow-wide | CHANGE-1 uses `--exclude-directories`, not `ignore-codes` |
| Existing `php_code` rows fail closed via `WP_Error` | Default case in processor switch |
| Uninstall data-gate unchanged | CHANGE-7 preserves the `acrossai_abilities_uninstall_delete_data` gate |

## Trust Boundaries

| Boundary | Notes |
|----------|-------|
| `manage_options` capability for ability create/edit | Unchanged from pre-Feature-021 — enforced in REST `permission_callback` |
| Registered-callback registration | Only version-controlled plugin/theme code (via `add_filter`) — no DB-stored callables |
| `$input` to `call_user_func` | Caller-controlled (same as before), but now dispatched to trusted callable, not arbitrary stored PHP |
| `wp_unslash()` + `sanitize_text_field()` on `REQUEST_URI` | Boolean detection only; never reflected or used in queries |

## Follow-Up Items (Non-Blocking)

- [ ] Add inline PHPDoc comment at `apply_filters('acrossai_abilities_registered_callbacks', ...)` noting trust boundary (ADV-002)
- [ ] Verify `WP_Error` is returned (not `null` or `false`) for all unregistered callback paths (ADV-001 implementation check)
