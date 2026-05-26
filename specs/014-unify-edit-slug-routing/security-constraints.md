# Security Constraints — Feature 014: Unify Edit + Slug Routing

**Date**: 2026-05-25  
**Reviewer**: speckit.architecture-guard.governed-plan  
**Plan artifact**: specs/014-unify-edit-slug-routing/plan.md  
**Spec artifact**: specs/014-unify-edit-slug-routing/spec.md

---

## Executive Summary

The plan is secure-by-design. All three hard security constraints (SEC-01, SEC-02, SEC-04) from `memory-synthesis.md` are explicitly addressed. Two advisory clarifications and one implementer guardrail are noted below. No P0 blockers found. Safe to proceed to `/speckit.tasks`.

---

## Plan Artifacts Reviewed

- `specs/014-unify-edit-slug-routing/plan.md`
- `specs/014-unify-edit-slug-routing/spec.md`
- `specs/014-unify-edit-slug-routing/memory-synthesis.md`
- `.specify/memory/CONSTITUTION.md`

---

## Confirmed Secure Patterns

| Pattern | Task | Detail |
|---|---|---|
| SEC-01: Slug sanitization | T-PHP-B2, T-PHP-B3 | `sanitize_callback: sanitize_ability_slug(rawurldecode($slug))` declared on slug arg for both Write and Read routes |
| SEC-01: Validate callback | T-PHP-B2, T-PHP-B3 | `validate_callback: is_string($slug) && '' !== trim($slug)` — rejects empty strings |
| SEC-02: Before-save hook | T-PHP-B2 | `acrossai_abilities_before_delete` fires on sanitized fields only, before DB mutation |
| SEC-04: Strict type guards | T-PHP-A1 | `null !== $override->{$field} && '' !== (string) $override->{$field}` — no `empty()` |
| Permission callback | T-PHP-B1–B3 | All routes delegate to orchestrator's `check_permission()` — no new permission logic needed |
| No new hook wiring | T-PHP-B1 | Orchestrator already wired in `Main.php` via existing `rest_api_init` binding — no Main.php change |
| Strip protected fields | T-PHP-A2 | `strip_protected_fields_for_non_db()` removes protected fields from upsert payload |
| 403 on non-db delete | T-PHP-B2 | Delete of a non-`source=db` ability returns 403 — correctly blocks destructive operation on registry-only abilities |
| 404 on missing slug | T-PHP-B2, T-PHP-B3 | Both read and write paths return 404 when slug not found in DB or registry |

---

## Vulnerability Findings

### SEC-ADVISORY-01 — `source` check on delete must use DB row, not registry (MEDIUM)

**Location**: T-PHP-B2 `delete_ability()` step 3  
**Description**: The plan states `if source !== 'db': 403` but does not explicitly declare that the `source` field must be read from `$existing` (the DB row returned by `get_ability_by_slug()`), not from a separate registry lookup. If an implementer resolves `source` from both paths without precedence logic, the guard could be bypassed for an ability registered in the registry that also has a DB row with a different `source`.  
**Constraint**: **The `source` field for delete authorization MUST be `$existing->source` (the DB row value) — not a registry-derived source. If `$existing` is null (not found in DB), return 404, not 403.**  
**Risk Level**: MEDIUM — advisory; correct implementation is unambiguous but must be explicit in tasks.

---

### SEC-ADVISORY-02 — Body param sanitization sequence for first-time upsert must be explicit (LOW)

**Location**: T-PHP-B2 `update_ability()` "not found → upsert" branch  
**Description**: The plan describes the upsert path as: `wp_get_ability($slug)` → upsert via `save_override()`. The sanitization sequence `sanitize → strip if non-db → validate` is stated in the Phase B rationale but the upsert branch does not spell out the exact call order for body params. An implementer could skip `strip_protected_fields_for_non_db()` for first-time non-db overrides.  
**Constraint**: **All body params in the upsert (first-time non-db) path MUST pass through `sanitize → strip_protected_fields_for_non_db() → save_override()` in that order, identical to the update path.**  
**Risk Level**: LOW — implementation detail, but must be enforced in T-PHP-B2 task wording.

---

### SEC-GUARDRAIL-01 — `AcrossAI_Ability_Override_Processor` is out of scope; call only, do not modify (LOW)

**Location**: T-PHP-B2 `update_ability()` and `delete_ability()`  
**Description**: The plan calls `AcrossAI_Ability_Override_Processor::bust_cache()` after mutations. Per spec Q2 clarification, `AcrossAI_Ability_Override_Processor` is ENTIRELY out of scope for Feature 014 and MUST NOT be modified.  
**Constraint**: **Only `::bust_cache()` call is permitted. No other method in `AcrossAI_Ability_Override_Processor` may be modified or new methods added.**  
**Risk Level**: LOW — guardrail only; prevents scope creep.

---

## Trust Boundary Map

```
Browser → X-WP-Nonce header → WP REST API → permission_callback (manage_options)
                                                      ↓
                                          validate_callback (slug is non-empty string)
                                                      ↓
                                          sanitize_callback (rawurldecode + sanitize_ability_slug)
                                                      ↓
                                          Business logic: exclusion check → DB lookup
                                                      ↓
                                          strip_protected_fields_for_non_db (for non-db path)
                                                      ↓
                                          before_save hook (on sanitized data only)
                                                      ↓
                                          BerlinDB write (save_override / update / delete)
```

---

## Async Security Context

No async operations introduced. All REST operations are synchronous request-response. `bust_cache()` is a synchronous call. No Action Scheduler tasks added. No background processing concerns.

---

## Data Isolation

- Single-site only (per AGENTS.md `multisite_support: false`) — no cross-site data leak risk.
- Slug-based routing does not expose integer DB IDs — reduces enumeration attack surface.
- `rawurldecode()` applied before sanitization — percent-encoded traversal attempts (`..%2F..`) are sanitized by `sanitize_ability_slug()` after decoding.

---

## Follow-Up Actions Required

| ID | Severity | Action | Where |
|---|---|---|---|
| SEC-ADVISORY-01 | MEDIUM | Add explicit constraint: `source` for delete guard = `$existing->source` (DB row value) | T-PHP-B2 task in tasks.md |
| SEC-ADVISORY-02 | LOW | Add explicit sequence: sanitize → strip_protected → save_override for upsert path | T-PHP-B2 task in tasks.md |
| SEC-GUARDRAIL-01 | LOW | Note: `AcrossAI_Ability_Override_Processor` — call only, do not modify | T-PHP-B2 task in tasks.md |
