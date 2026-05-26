# Security Review: Abilities Business Logic and REST API (009)

**Review Date**: 2026-05-22  
**Reviewed Artifacts**: plan.md, spec.md, memory-synthesis.md, research.md, data-model.md, contracts/rest-api.md, contracts/runtime-registration.md, contracts/exposure-collections.md, quickstart.md, .specify/memory/CONSTITUTION.md, docs/memory/INDEX.md  
**Status**: Review complete — 2 high-risk findings RESOLVED (PD-001/PD-002 in plan.md), 5 advisory constraints carried forward, 0 implementation blockers

---

## Executive Summary

The Spec 009 plan is directionally sound and aligns with the repository's architecture constraints: it keeps the unified `acrossai_abilities` storage boundary, uses a thin REST orchestrator plus sub-controllers, keeps browse logic in the query layer, and explicitly requires sparse-write re-reads plus nested runtime registration metadata. Those are strong secure-by-design choices.

The main security risk during review was not missing sanitization language; it was **boundary ambiguity** between administrator management, authenticated runtime execution, and machine-consumable discovery. The planning correction pass resolved the two high-risk items by fixing the exposure endpoint to the admin boundary and by documenting explicit hardening rules for `php_code` and `wp_remote_post`.

Remaining carry-forward work is narrower: preserve the shared-table immutability boundary, keep payload guardrails explicit in implementation tasks, and fail closed when MCP server context is missing.

Recommendation: proceed to tasks with PD-001, PD-002, and the immutable-field constraints treated as mandatory implementation requirements rather than optional review notes.

---

## Plan Artifacts Reviewed

- `specs/009-abilities-business-logic-rest/plan.md`
- `specs/009-abilities-business-logic-rest/spec.md`
- `specs/009-abilities-business-logic-rest/memory-synthesis.md`
- `specs/009-abilities-business-logic-rest/research.md`
- `specs/009-abilities-business-logic-rest/data-model.md`
- `specs/009-abilities-business-logic-rest/contracts/rest-api.md`
- `specs/009-abilities-business-logic-rest/contracts/runtime-registration.md`
- `specs/009-abilities-business-logic-rest/contracts/exposure-collections.md`
- `specs/009-abilities-business-logic-rest/quickstart.md`
- `.specify/memory/CONSTITUTION.md`
- `docs/memory/INDEX.md`

---

## Trust Boundaries and Security Assumptions

### Intended Trust Model

- Management CRUD, browse, and categories endpoints are administrator-only.
- Runtime execution of published database-managed abilities is authenticated-user only.
- Unified-table storage is shared across DB-managed rows and non-DB inherited/sitewide rows; ownership is determined by `source` semantics rather than by separate tables.
- Runtime publication is a second trust boundary separate from management: only valid `source = db` and `status = publish` rows should cross from persistence into the executable registry.
- Exposure collections are administrator-only discovery surfaces for machine-consumable metadata in Spec 009 and must continue to fail closed on unknown server context.

### Assumptions That Need To Stay Explicit

- Runtime permission must not inherit management permission. Administrator-only management does **not** make runtime callbacks safe for arbitrary logged-in users.
- Query-layer helpers remain authorization-free, so every public or internal REST/controller entry point must fail closed before data leaves the module.
- `source` is a security boundary, not only a storage label. Requests must never be able to promote, rewrite, or delete inherited rows as if they were `db` rows.

---

## Vulnerability Findings

### Finding 1: Exposure endpoint authorization was ambiguous at the trust boundary

**Category**: Authorization / information disclosure  
**Severity**: High  
**Status**: ✅ RESOLVED — PD-001 in plan.md: `GET /abilities/exposures/{type}` is **admin-only (`manage_options`)**, same gate as all other Spec 009 endpoints.

**What the corrected artifacts now say**:
- Management endpoints are administrator-only.
- Runtime execution is authenticated-user only.
- `GET /abilities/exposures/{type}` is administrator-only and uses the same `manage_options` gate as the rest of Spec 009.
- Missing or unknown MCP server context fails closed for server-scoped results.

**Risk**:
Leaving the exposure endpoint policy open at implementation time makes it easy to ship a discovery surface broader than intended. That can leak published DB abilities, schemas, descriptions, annotations, provider/source metadata, and server-scoping signals to callers who are allowed to invoke runtime abilities but should not enumerate the full catalog.

**Why it matters here**:
Spec 009 already separates admin management from runtime execution. Exposure collections sit between those boundaries. If their authorization is not fixed now, the implementation may accidentally treat them as either an admin browse endpoint or a public/internal runtime feed without adjusting response shape or filtering rules accordingly.

**Carry-forward constraint**:
- Keep exposure collections on the administrator boundary and do not introduce a broader runtime/internal discovery tier during implementation.
- Preserve fail-closed MCP server filtering so missing context never broadens exposure results.

---

### Finding 2: `php_code` and `wp_remote_post` execution modes widen runtime risk without enough hardening rules

**Category**: Runtime execution / code execution / SSRF  
**Severity**: High  
**Status**: ✅ RESOLVED — PD-002 in plan.md: both types kept in scope with explicit hardening rules (blocked-function scan, static closure wrapping, per-invocation error isolation for `php_code`; HTTPS-only, no redirects, timeout cap, no header propagation for `wp_remote_post`).

**What the plan says**:
- Supported runtime modes include `noop`, `filter_hook`, `wp_remote_post`, and `php_code`.
- Published DB abilities are executable by any authenticated user.
- Runtime registration validates execution config and skips invalid rows.

**Risk**:
Validation of shape alone is insufficient here. `php_code` is effectively stored code execution. `wp_remote_post` is an outbound network surface with SSRF, credential forwarding, and internal-service reachability implications. Because these abilities are executable by any logged-in user once published, the plan needs explicit rules preventing runtime privilege expansion and uncontrolled outbound requests.

**Why it matters here**:
The security boundary is not "admins created the row." It is "any authenticated runtime caller can trigger what the row does." Without execution-time constraints, a trusted authoring surface becomes a broad trigger surface.

**Required constraints**:
- `php_code` must be treated as a trusted-admin-authored mode with an explicit statement of non-sandboxed execution risk, or it must be removed from scope. If retained, its callable construction, allowed inputs, error isolation, and audit logging need documented rules before implementation.
- `wp_remote_post` must define an allowlist or explicit host-validation policy, a timeout ceiling, redirect rules, body/header restrictions, and no secret/header pass-through from the caller by default.
- Runtime permission must remain logged-in-only for all modes and must not branch to weaker behavior for any exposure type.
- Execution failures must be isolated per invocation and must not corrupt registry bootstrap or subsequent invocations.

---

### Finding 3: Shared-table source semantics are a security boundary but field immutability is not fully specified

**Category**: Storage boundary / integrity  
**Severity**: Medium  
**Status**: Advisory

**What the plan gets right**:
- Unified storage is intentional.
- Deletes are limited to `source = db` rows.
- Non-DB rows are only partially editable.

**Risk**:
The plan does not yet define a canonical immutable-field set or merge order for `source != db` updates. In a shared table, that is not a convenience detail; it is what prevents an admin write path from mutating identity, execution, or provenance fields on inherited rows.

**Constraint to carry forward**:
- `source` must be server-controlled and immutable in all update paths.
- For non-DB rows, protected fields must be blocked both before and after merge so sparse updates cannot smuggle changes into protected identity, descriptive, execution, or exposure fields.
- Query/read formatting should clearly expose editability state, but editability metadata must not become the only enforcement mechanism; write controllers must enforce it on the persisted row.

---

### Finding 4: Structured payload validation needs size, depth, and key-shape guardrails

**Category**: Validation / denial of service / unsafe payload normalization  
**Severity**: Medium  
**Status**: Advisory

**What the plan gets right**:
- Callback config, schema payloads, and exposure metadata are validated and sanitized.
- Memory already captures the 64 KB JSON guard and strict input sanitation expectations.

**Risk**:
The plan names validation targets but does not define payload size, nesting depth, or unsupported-key behavior for `callback_config`, `input_schema`, `output_schema`, and `mcp_servers`. That leaves room for oversized JSON, deeply nested payloads, schema abuse, and loose acceptance of mode-incompatible config.

**Constraint to carry forward**:
- Apply explicit size guards to all JSON-backed fields, consistent with durable memory (`64 KB` ceiling unless there is a documented exception).
- Enforce depth limits for structured payloads.
- Reject unknown keys for mode-specific callback configs and exposure metadata instead of silently storing them.
- Validate schemas as data contracts, not arbitrary JSON blobs.

---

### Finding 5: Sparse update and after-save hook safety is acknowledged but needs a hard requirement on sanitized/full-row hook payloads

**Category**: Hook safety / data integrity  
**Severity**: Medium  
**Status**: Advisory

**What the plan gets right**:
- Sparse updates must merge with stored rows.
- The saved row must be re-read before response formatting and after-save hooks.
- Memory explicitly calls out the partial-hook bug pattern.

**Risk**:
This is the kind of rule that tends to degrade during implementation if it is not written as a hard planning requirement. If `before_save` or `after_save` receive unsanitized request payloads or local sparse arrays, downstream hook consumers can observe incomplete or unsafe data and make authorization, logging, or propagation decisions on the wrong shape.

**Constraint to carry forward**:
- `before_save` hooks must receive sanitized submitted fields only.
- BerlinDB casting must be re-applied after sanitization and before write.
- `after_save` hooks must receive the full re-read persisted row only.
- The plan should explicitly forbid formatting or hook emission from local merged arrays.

---

### Finding 6: Nested registry/meta rules are correct, but the authoritative arg contract is still under-specified

**Category**: Runtime registration integrity  
**Severity**: Medium  
**Status**: Advisory

**What the plan gets right**:
- The plan explicitly forbids flat top-level registration args and requires nested meta paths.

**Risk**:
The contract does not yet pin down which fields live at top level versus inside nested meta. That can create implementation drift, especially around exposure metadata, annotations, execution config, and source/audit markers. In this codebase, incorrect arg placement is already a known bug class.

**Constraint to carry forward**:
- Define the canonical registry arg map before implementation.
- Treat unexpected top-level writes as invalid during internal formatter construction.
- Do not allow source/audit or execution config to land in alternate shapes based on callback type.

---

### Finding 7: MCP exposure filtering needs explicit fail-closed behavior for server scoping

**Category**: Exposure filtering / authorization consistency  
**Severity**: Medium  
**Status**: Advisory

**What the plan gets right**:
- Exposure collections include only published valid DB rows.
- `mcp_type` and `mcp_servers` are part of validation.
- Server allowlist filtering is recognized as relevant.

**Risk**:
The plan does not define how the current server context is resolved or what happens when it is unavailable, malformed, or unmatched. In a multi-server environment, defaulting to "no context means allow all" would violate the server-allowlist boundary.

**Constraint to carry forward**:
- Empty `mcp_servers` may mean unrestricted only when that is the stored value.
- Unknown current server context must return no server-restricted rows.
- Filtering must use strict membership checks.
- The current server source must be canonical and documented, not derived ad hoc in controllers.

---

## Confirmed Secure Patterns

- The plan preserves the unified `acrossai_abilities` table boundary and does not introduce a second storage surface for managed abilities.
- Management endpoints are consistently modeled as administrator-gated, separated from runtime execution.
- Runtime publication is limited to `source = db` and `status = publish`, with skip-and-continue behavior for invalid rows.
- Query-layer ownership of filtering, search, sort, and pagination is correctly preserved, reducing controller-level security drift.
- Sparse updates are explicitly designed to merge with stored rows and re-read the persisted row before response formatting.
- The plan explicitly carries forward two known hardening lessons from durable memory: nested registry meta paths and the after-save full-row re-read requirement.
- Runtime execution is explicitly denied to anonymous callers for all published DB abilities.
- The boot-flow and REST split patterns remain aligned with the constitution, limiting hook-registration sprawl.

---

## Recommended Constraints for Governed Planning

1. Fix the exposure endpoint policy now. Decide whether `GET /abilities/exposures/{type}` is admin-only or runtime/internal-authenticated, and lock its response shape to that choice.
2. Treat `source` as immutable and security-sensitive. Define the protected-field matrix for `source != db` rows before tasks are generated.
3. Add payload guardrails for JSON-backed fields: explicit size limit, depth limit, and reject-unknown-key rules for mode-specific configs and schemas.
4. Keep hook payload guarantees hard: sanitized fields only for `before_save`, full re-read persisted row only for `after_save`.
5. Define the canonical nested registry arg map before implementation so formatter and processor code cannot drift into flat or mixed structures.
6. For `wp_remote_post`, specify host validation, timeout ceiling, redirect policy, and no caller-secret/header propagation by default.
7. For `php_code`, either remove it from scope or explicitly document that it is trusted admin-authored stored code with no sandbox and requires execution-time audit/error isolation.
8. Make MCP filtering fail closed on unknown server context and require strict membership checks for `mcp_servers`.

---

## Task Review Refinements (2026-05-22)

The task review against `tasks.md` did not reopen any architecture or trust-boundary decisions, but it did refine what the implementation plan must make explicit before coding starts.

### Refinement 1: Every admin-only endpoint group needs explicit forbidden-path coverage

**Task review result**:
- Implementation tasks consistently describe the write, read, category, and exposure endpoints as administrator-only.
- The corresponding test tasks do not explicitly require forbidden-path assertions for every endpoint group.
- `T015` covers exposure filtering and fail-closed server handling, but it is scoped to `AbilitiesProcessorTest.php`; that is not sufficient by itself to prove controller-level authorization on the exposure endpoint.

**Carry-forward constraint**:
- Before implementation begins, the governed task summary must require explicit non-admin or forbidden-path coverage for create, sparse update, delete, list, single-item, categories, and exposures endpoints.
- Exposure endpoint authorization must be proven in controller or route coverage, not inferred from processor tests.

### Refinement 2: Runtime hardening subtasks must stay explicit at execution time, not only at save-time validation

**Task review result**:
- `T003` and `T007` explicitly cover save-time validation for `php_code` and `wp_remote_post`.
- `T015` and `T016` refer to hardening more generally, but do not spell out each execution-time rule that the plan and runtime contract already require.

**Carry-forward constraint**:
- The governed task summary must preserve explicit implementation and test ownership for these runtime rules:
- `php_code` static-closure wrapping, per-invocation `Throwable` isolation, and execution outcome audit logging.
- `wp_remote_post` HTTPS revalidation at callable construction, timeout clamping, `redirection => 0`, and no caller header, cookie, or secret propagation.
- General references to hardening are not sufficient once implementation begins; the execution-time controls above must remain enumerated requirements.

### Refinement 3: Structured payload guardrails must include reject-unknown-key coverage across callback and exposure payloads

**Task review result**:
- The foundational tasks cover schema guardrails and `wp_remote_post` unknown-key hardening.
- The task list does not make reject-unknown-key behavior equally explicit for all mode-specific callback payloads and exposure metadata paths.

**Carry-forward constraint**:
- Task execution must treat unknown-key rejection as a shared structured-payload rule, not a `wp_remote_post`-only rule.
- Coverage must explicitly verify reject-unknown-key behavior for:
- mode-specific `callback_config` payloads such as `filter_hook`, `wp_remote_post`, and `php_code`.
- exposure metadata payloads including `show_in_mcp`, `mcp_type`, and `mcp_servers`.
- Size and depth guardrails remain mandatory for JSON-backed payloads and must be verified together with unknown-key rejection.

## Review Outcome

**Result**: Approved with planning constraints  
**Blocker Status**: No architectural blockers, but the high-risk authorization and runtime-execution constraints above should be resolved in planning artifacts before implementation begins.
