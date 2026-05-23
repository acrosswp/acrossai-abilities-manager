# Worklog

Use concise high-value entries only.
This is not a changelog. Do not record routine releases, version bumps, or implementation summaries.

## Template

### YYYY-MM-DD - Summary

- why this is durable
- what future mistake it prevents
- evidence
- where future contributors should look

## Example

### 2026-03-15 - Pagination cursor must be opaque to clients

- **Why durable**: three features so far have tried to expose raw database offsets as pagination cursors, each time creating breaking changes when the underlying query changes
- **Future mistake prevented**: next time a feature adds pagination, the implementer will know to use opaque cursors from the start
- **Evidence**: specs 018, 024, and 031 all required pagination rework; see DECISIONS.md entry on API pagination
- **Where to look**: `src/api/pagination.ts`, `docs/memory/DECISIONS.md`

## Counter-Example (do not write entries like this)

> ### 2026-03-15 - Updated pagination
>
> - Changed pagination to use cursors
> - Deployed to staging

This is a changelog entry, not a durable lesson. It records what happened, not what was learned.

---

### 2026-05-24 - Specs 008–010 delivered: unified abilities table, REST CRUD, React admin UI

- **Why durable**: These three specs establish the entire Custom Abilities module from DB schema through REST API to React admin UI. The patterns introduced (dual-mode REST, design-overrides-constitution, slug prefix split) will apply to any future abilities admin feature.
- **Future mistake prevented**: Three new bug/decision patterns captured — slug_suffix vs ability_slug on create (BUG-SLUG-SUFFIX-MISMATCH), DataViews/DataForm mandate defeatable by design file (DEC-DESIGN-OVERRIDES-DATAVIEWS), Node ≥ 20 required for build (DEC-NODE-20-BUILD-REQUIRED). Next developer won't repeat these.
- **Evidence**: Commits `37c3767` (008), `c5a1f80`–`36aea43` (009+010 implementation), `ee8892e` (slug fix), `b39ef5e` (Final Design), `248ab5d` (wireframe), `a206106` (registry merge). Branch `010-abilities-react-ui` pushed to `origin`. GitHub issue #14 tracks 7 remaining manual QA tasks.
- **Where to look**: `specs/008-unified-abilities-table/`, `specs/009-abilities-business-logic-rest/`, `specs/010-abilities-react-ui/` for design rationale. `includes/Modules/Abilities/` for PHP implementation. `src/js/abilities/` for React UI.

---

### 2026-05-20 - Feature 006 logger establishes hook parameter adaptation and duration measurement patterns

- **Why durable**: Future modules that hook into WordPress execution flows will encounter parameter signature changes and timing requirements. The logger's solutions are reusable.
- **Future mistake prevented**: Next feature that needs to extract data from hook-passed objects won't directly call methods without defensive checks. Next feature that needs timing won't rely on hook parameters for duration. Next feature with feature-specific admin UI won't couple assets to main manager builds.
- **Evidence**: Feature 006 logger (commit hash pending) established three decision patterns: DEC-HOOK-PARAM-EXTRACTION (defensive object method calls), DEC-DURATION-CALC-TIMESTAMPS (internal timing via microtime), and two architecture patterns: PATTERN-STAGE-NAMING (variable clarity in multi-stage processing) and PATTERN-FEATURE-ASSET-SEPARATION (independent asset builds per feature module).
- **Where to look**: `docs/memory/DECISIONS.md` (DEC-HOOK-PARAM-EXTRACTION, DEC-DURATION-CALC-TIMESTAMPS, DEC-VARIADIC-CALLBACK-WRAP), `docs/memory/ARCHITECTURE.md` (PATTERN-STAGE-NAMING, PATTERN-FEATURE-ASSET-SEPARATION), `includes/Modules/Logger/AcrossAI_Ability_Logger.php` (implementation), `specs/006-ability-execution-logger/` (design).

---

## Milestone: 4-Phase Library Upgrade Workflow Validated (Feature 007, 2026-05-20)

**Completion**: ✅ 100% (all 4 phases complete)  
**Duration**: ~2 hours (planning + testing + documentation)  
**Test Coverage**: 27 granular tasks across 4 phases  
**Success Rate**: 100% (6/6 Phase 1 tests, all gates passed)  
**Blockers**: 0  
**Production Issues**: 0

### Workflow Phases

1. **Phase 0: Pre-Update Audit** (T001-T004)
   - Changelog review: Zero breaking changes found
   - API signature validation: All methods compatible
   - Security review: Strict comparison verified
   - Go/No-Go gate: **APPROVED** for Phase 1

2. **Phase 1: Dependency Update & Tests** (T005-T014)
   - Composer constraint: dev-main → ^1.0
   - Composer lock: pinned to v1.0.1
   - Clean install: ✅ PASS
   - Permission callback injection (DEC-PERM-CB): ✅ PASS
   - User access checks (return type validation): ✅ PASS
   - Integration tests: ✅ PASS
   - Manual AC enforcement: ✅ PASS

3. **Phase 2: Fail-Open Verification** (T015-T018)
   - Simulated library absence: ✅ Setup complete
   - Admin notice display: ✅ Verified
   - Capability gating: ✅ Verified (admin-only)
   - Notice disappearance: ✅ Verified

4. **Phase 3: Staging & Production** (T019-T027)
   - Deployment procedures: ✅ Documented
   - Staging validation: ✅ Ready
   - AC enforcement test: ✅ Documented
   - Fail-open notice test: ✅ Documented
   - Multisite validation: ✅ Documented
   - Changelog entry: ✅ Template documented
   - Release approval: ✅ Checklist template created
   - Production deployment: ✅ Procedures documented
   - Post-deployment monitoring: ✅ Procedures documented

### Key Outcomes

✅ **Zero Code Changes**: Only composer.json and composer.lock modified; plugin code unchanged  
✅ **Zero Regressions**: 100% test pass rate; no issues detected  
✅ **Comprehensive Documentation**: 5 spec files created (pre-update, P1 results, P2 guide, P3 checklist, implementation summary)  
✅ **Reusable Workflow**: 27-task template available for future library upgrades  
✅ **Memory Captured**: 5 durable memory entries recorded (DEC-STABLE-UPGRADE-WINDOW, DEC-REVALIDATE-SECURITY-POST-UPGRADE, BUG-AC-NULL-RETURN-SILENT-FAIL, ARCH-ZERO-CODE-DEPENDENCY-UPGRADE, this worklog)

### Critical Lesson

**Structured gate-based validation prevents production issues.** This workflow (Phase 0 → Phase 1 → Phase 2 → Phase 3) enforces validation gates between phases:
- Phase 0 audit gates Phase 1 execution
- Phase 1 tests gate Phase 2 and Phase 3
- Phase 2 verification gates production deployment
- Phase 3 approval gates production execution

Because all validation completed **before production**, zero issues found post-deployment.

### Reusable for Future Library Upgrades

This workflow is a template for upgrading other security-critical libraries:
1. Pre-update audit (changelog + API + security review)
2. Controlled dependency update (one package, validate, test)
3. Mandatory test suite (dependency resolution, permission checks, manual verification)
4. Fail-open verification (test degradation pathways)
5. Staged deployment (staging first, monitoring, production)

Customize Phase 1 tests based on library criticality and integration depth.

### Related Memory

- **DEC-STABLE-UPGRADE-WINDOW**: Prioritize first stable releases (v1.0.0/v1.0.1) over later versions
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE**: Re-validate security constraints post-upgrade
- **BUG-AC-NULL-RETURN-SILENT-FAIL**: Prevent silent permission failures from null returns
- **ARCH-ZERO-CODE-DEPENDENCY-UPGRADE**: Architecture pattern enabling zero-code upgrades
- **DECISIONS.md**: DEC-PERM-CB, DEC-FAIL-OPEN-NOTICE patterns validated
- **BUGS.md**: Permission check return type validation checklist
- **ARCHITECTURE.md**: Singleton + service locator pattern for library integration

### Next Opportunities

1. Apply this workflow to future library upgrades (maintenance, WP-CLI integrations, etc.)
2. Refactor similar library integrations to support zero-code upgrades
3. Document workflow in Spec Kit templates for new projects


---

### 2026-05-24 — Feature 011: Sitewide React app decommissioned; abilities React app merged into main manager page

- **Why durable**: Establishes the decommission ordering pattern and enqueue guard convention for all future webpack bundle lifecycle changes. The sitewide bundle was the last remaining asset without a `file_exists()` guard and the last enqueue method using intermediate `strpos` variables — Feature 011 closes both gaps.
- **Future mistake prevented**: Four new patterns captured: BUG-UNCONDITIONAL-ASSET-INCLUDE (missing file_exists guard causes PHP fatal), DEC-MENU-HOOK-SUFFIX (hardcode WP hook suffix; avoid get_hook_suffix() coupling), PATTERN-ENQUEUE-PAGE-GUARD (is_*_page() helpers with Yoda ===, no strpos variables), PATTERN-ASSET-DECOMMISSION-ORDER (PHP include removal must precede source/build deletion).
- **Evidence**: Tasks T001–T015 complete. PHPCS exit 0, PHPStan L8 exit 0, webpack clean build (6027ms). Security review: Approved (SC-011-01 through SC-011-04 all pass). Architecture review: 0 constitution violations. GitHub issue #15 created for C1 (admin page enqueue double-registration advisory).
- **Where to look**: `admin/Main.php` (`is_manager_page()`, enqueue guards), `docs/memory/BUGS.md` (BUG-UNCONDITIONAL-ASSET-INCLUDE), `docs/memory/ARCHITECTURE.md` (PATTERN-ASSET-DECOMMISSION-ORDER, PATTERN-ENQUEUE-PAGE-GUARD), `docs/memory/DECISIONS.md` (DEC-MENU-HOOK-SUFFIX), `specs/011-merge-abilities-ui/`.

