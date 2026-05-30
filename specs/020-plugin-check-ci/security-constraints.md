# Security Review — Plugin Check CI + Compliance Fixes

**Feature**: `020-plugin-check-ci`
**Plan reviewed**: `specs/020-plugin-check-ci/plan.md`
**Spec reviewed**: `specs/020-plugin-check-ci/spec.md`
**Memory context**: `specs/020-plugin-check-ci/memory-synthesis.md`
**Review date**: 2026-05-30

---

## Executive Summary

Feature 020 introduces no new runtime attack surface. All changes are either CI pipeline
configuration (YAML — executed by GitHub Actions, not WordPress), code-quality guards
(wrapping `error_log()` calls), or documentation updates. The dominant security outcome is
**positive**: `error_log()` calls in production are silenced, reducing information disclosure risk.

One pre-existing residual risk is noted (the intentional `eval()` in
`AcrossAI_Abilities_Processor.php`) — it is correctly NOT modified by this plan, and the
CI suppression approach is appropriate. One optional hardening opportunity is identified for
the GitHub Actions action version pinning.

**Finding count**: 0 blocking · 1 advisory · 1 informational

---

## Plan Artifacts Reviewed

| Artifact | Status |
|----------|--------|
| `specs/020-plugin-check-ci/plan.md` | ✅ Reviewed |
| `specs/020-plugin-check-ci/spec.md` | ✅ Reviewed |
| `specs/020-plugin-check-ci/memory-synthesis.md` | ✅ Reviewed |
| `.github/copilot-instructions.md` (AGENTS.md) | ✅ Reviewed |
| `.specify/memory/CONSTITUTION.md` | ✅ Reviewed |

---

## Vulnerability Findings

### ADVISORY: Pre-existing `eval()` in `AcrossAI_Abilities_Processor.php`

**Severity**: Advisory (pre-existing, out-of-scope for this feature)
**OWASP**: A03 Injection
**Location**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` (line ~251)
**Status**: Correctly excluded from CHANGE-3 and CHANGE-4 scope

The plan correctly identifies the `eval()` as an *intentional* implementation choice for the
`php_code` ability type and explicitly lists it under "What Does NOT Change". The CI suppression
via `ignore-codes: 'WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval'` is
scoped to that one PHPCS error code — it does not suppress other checks, does not disable
code scanning, and does not mask any injection vulnerability.

**Constraint for implementation**:
- The `ignore-codes` value MUST be the exact PHPCS code token above. No wildcards.
- If a future plan proposes modifying the `eval()` itself, a dedicated security review is required at that time.
- This advisory should be captured in `docs/memory/BUGS.md` or `DECISIONS.md` as a known risk accept.

---

### INFORMATIONAL: GitHub Actions — action version pinning

**Severity**: Informational (optional hardening)
**Location**: `.github/workflows/plugin-check.yml`
**Status**: Current plan uses major-version tag pinning (`@v1`, `@v4`, `@v2`)

The plan pins third-party actions to major-version floating tags, not commit SHAs.
This is the standard practice for most WordPress-ecosystem projects and is acceptable here.

**Recommendation** (optional, not blocking): For supply-chain hardening, pin to specific
commit SHAs with a version comment:

```yaml
- uses: actions/checkout@11bd71901bbe5b1630ceea73d27597364c9af683  # v4.2.2
- uses: shivammathur/setup-php@v2  # acceptable — maintained by known author
- uses: WordPress/plugin-check-action@v1  # acceptable — first-party WordPress action
```

`WordPress/plugin-check-action@v1` and `actions/checkout@v4` are maintained by WordPress.org
and GitHub respectively — major-version pinning is sufficient for this plugin type.

---

## Confirmed Secure Patterns

| Pattern | Location | Assessment |
|---------|----------|------------|
| `WP_DEBUG_LOG` guard wrapping `error_log()` | 5 PHP files | ✅ Correct pattern. Prevents production log disclosure. |
| `phpcs:ignore` inline comment moved inside guard | All 12 call sites | ✅ Correct: suppression is scoped to the protected block only. |
| `pull_request` trigger (not `pull_request_target`) | `plugin-check.yml` | ✅ Safe for forks: `pull_request` runs in the fork's context with read-only token. |
| `composer install --no-dev` | `plugin-check.yml` | ✅ Dev dependencies excluded from the validated artifact. |
| `include-experimental: true` + `ignore-warnings: false` | `plugin-check.yml` | ✅ Maximum strictness; no checks silently bypassed. |
| No `GITHUB_TOKEN` write permissions | `plugin-check.yml` | ✅ Plugin Check is read-only; no explicit `permissions: write` needed. |
| No new REST endpoints | Feature scope | ✅ No new authorization surface. |
| No DB schema changes | Feature scope | ✅ No new SQL injection surface. |
| No user input introduced | Feature scope | ✅ No new XSS or sanitization considerations. |
| `ignore-codes` scoped to one PHPCS error token | `plugin-check.yml` | ✅ Narrow suppression; does not blanket-disable any security check. |

---

## Trust Boundaries

This feature's only trust boundary change is:

- **CI pipeline**: Third-party actions (`shivammathur/setup-php@v2`, `WordPress/plugin-check-action@v1`,
  `actions/checkout@v4`) run in the GitHub Actions runner with a scoped read-only token.
  No secrets are available to these actions beyond the default `GITHUB_TOKEN` (read permissions).
  No production data, no database, no credentials are accessible.

No WordPress runtime trust boundaries are changed by this feature.

---

## Security-Architecture Conflicts

None identified. The feature's CI workflow does not interact with the plugin's PHP runtime,
BerlinDB, REST endpoints, or admin UI. There is no architectural drift from the
security-relevant P0 rules in the Architecture Constitution.

---

## Constraints for Implementation

1. **MUST**: `ignore-codes` value must be exactly `WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval` — no looser tokens.
2. **MUST**: All 12 `error_log()` call sites must be wrapped. Partial wrapping leaves information disclosure risk in production.
3. **MUST NOT**: The `eval()` at `AcrossAI_Abilities_Processor.php` line ~251 must not be modified.
4. **SHOULD**: Add `permissions: contents: read` to the workflow job as explicit hardening (not blocking).
5. **ADVISORY**: Record the intentional `eval()` risk acceptance in `docs/memory/DECISIONS.md` after implementation.
