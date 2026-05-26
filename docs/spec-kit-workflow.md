# Spec Kit Workflow Documentation

---

## Overview

This document explains how to use the Spec Kit commands and their extensions. The workflow moves from project principles through specification, planning, implementation, and final review.

---

## Complete Command Reference

### Core Workflow Commands

| Command | What it produces |
|---|---|
| `/speckit.constitution` | Creates/updates `.specify/memory/CONSTITUTION.md` |
| `/speckit.specify` | Creates `specs/NNN-feature/spec.md` + `research.md` |
| `/speckit.clarify` | Refines `spec.md` by asking up to 5 targeted questions *(optional)* |
| `/speckit.plan` | Creates `specs/NNN-feature/plan.md` + `data-model.md` + `quickstart.md` + `contracts/` |
| `/speckit.tasks` | Creates `specs/NNN-feature/tasks.md` + `checklists/` |
| `/speckit.implement` | Executes tasks.md — writes actual code to plugin files |
| `/speckit.analyze` | Cross-artifact consistency check (spec ↔ plan ↔ tasks ↔ code) |

### Memory MD Extension — Knowledge Management

| Command | When to use |
|---|---|
| `/speckit.memory-md.bootstrap` | First time setup — initialises `.specify/memory/` structure |
| `/speckit.memory-md.audit` | Audits memory files for staleness or gaps |
| `/speckit.memory-md.capture` | Saves key decisions/findings to memory manually |
| `/speckit.memory-md.capture-from-diff` | After implementation — extracts learnings from git diff into memory |
| `/speckit.memory-md.log-finding` | Logs a single finding (bug, pattern, decision) to memory |
| `/speckit.memory-md.plan-with-memory` | Runs `/speckit.plan` with full memory context injected |

### Architecture Guard Extension — Architectural Integrity

| Command | When to use |
|---|---|
| `/speckit.architecture-guard.init` | Initialises Architecture Guard config for this project |
| `/speckit.architecture-guard.governed-plan` | Validates plan against constitution + memory before proceeding |
| `/speckit.architecture-guard.governed-tasks` | Validates tasks against architecture constraints + refactor awareness |
| `/speckit.architecture-guard.governed-implement` | Runs implement then reviews code against architecture + security |
| `/speckit.architecture-guard.architecture-review` | Post-implementation drift analysis vs spec/plan/tasks |
| `/speckit.architecture-guard.violation-detection` | Scans plans, tasks, or implementation summaries for violations |
| `/speckit.architecture-guard.refactor-generator` | Converts violations into non-blocking structured refactor tasks |
| `/speckit.architecture-guard.architecture-apply` | Applies approved architecture refactors to plan/task artifacts |
| `/speckit.architecture-guard.architecture-workflow` | Single workflow incorporating memory + security context |

### Security Review Extension — Security Auditing

| Command | When to use |
|---|---|
| `/speckit.security-review.plan` | Security review of plan artifacts before implementation starts |
| `/speckit.security-review.tasks` | Security review of task sequencing for security gaps |
| `/speckit.security-review.staged` | Security review of currently staged git changes |
| `/speckit.security-review.branch` | Security review of an entire feature branch |
| `/speckit.security-review.followup` | Creates remediation tasks from security findings |
| `/speckit.security-review.apply` | Applies approved security follow-up items into planning artifacts |

### Git Extension — Version Control

| Command | What it does |
|---|---|
| `/speckit.git.initialize` | Initialises git repo with first commit |
| `/speckit.git.feature` | Creates a numbered feature branch (`feature/NNN-slug`) |
| `/speckit.git.validate` | Validates current branch follows naming conventions |
| `/speckit.git.commit` | Auto-commits all staged changes with a structured message |
| `/speckit.git.remote` | Detects and configures git remote URL |

---

## Step-by-Step Workflow

### Phase 0 — One-Time Project Setup

#### Step 1: `/speckit.constitution`

Run once when the project starts (or when principles need updating).

**Creates:** `.specify/memory/CONSTITUTION.md`

#### Step 2: `/speckit.memory-md.bootstrap`

Run once after constitution to initialise the `.specify/memory/` directory structure.

**Creates:** Memory scaffolding that agents read during planning and implementation.

---

### Phase 1 — Per-Feature: Specification

#### Step 3: `/speckit.specify <natural language description>`

**Creates in `specs/NNN-feature/`:**
- `spec.md` — functional requirements (FR-001…), user stories (US1…), acceptance scenarios (SC-001…), constraints
- `research.md` — technical research findings used by the planner

**Naming convention:** Specs are numbered sequentially: `001-`, `002-`, `003-`, etc.

#### Step 4: `/speckit.clarify` *(optional)*

Run only if `spec.md` has ambiguous requirements. Asks up to 5 targeted questions and encodes answers back into the spec.

---

### Phase 2 — Per-Feature: Planning


#### Step 5: `/speckit.plan` or `/speckit.memory-md.plan-with-memory`

Prefer `plan-with-memory` on features 002+ — it injects prior memory context so the planner knows existing patterns (singleton, REST namespace, DB schema, etc.).

**Creates in `specs/NNN-feature/`:**
- `plan.md` — technical blueprint: architecture decisions, data model, API endpoints, component structure, phases
- `data-model.md` — database schema and shape definitions *(if applicable)*
- `quickstart.md` — fast-path verification steps for manual testing
- `contracts/` — API contract files (e.g., `wpb-ac-v1-rest-api.md`)

#### Step 6: `/speckit.architecture-guard.governed-plan` *(recommended)*

Validates `plan.md` against the constitution and prior architecture decisions before proceeding.

#### Step 7: `/speckit.security-review.plan`

Reviews `plan.md` for security gaps:
- Missing nonce verification declarations
- Unvalidated REST args
- Capability check coverage

---

### Phase 3 — Per-Feature: Task Generation

#### Step 8: `/speckit.tasks`

**Creates in `specs/NNN-feature/`:**
- `tasks.md` — dependency-ordered, phase-grouped implementation tasks with parallel execution hints
- `checklists/` — quality gate checklists (PHPCS, PHPStan, ESLint, browser test)
- `memory.md` — feature-specific memory artifacts
- `memory-synthesis.md` — cross-feature learning synthesis

#### Step 9: `/speckit.architecture-guard.governed-tasks` *(recommended)*

Validates task ordering and checks for architecture violations or missing refactor tasks. Safe to run in parallel with Step 7 if plan is already reviewed.

#### Step 10: `/speckit.security-review.tasks`

Checks task sequencing for security: Are capability checks added before REST routes? Is sanitization wired before the REST callback runs?

---

### Phase 4 — Implementation

#### Step 11: `/speckit.implement` or `/speckit.architecture-guard.governed-implement`

Prefer `governed-implement` — it runs implementation then immediately reviews the produced code against security and architecture constraints.

**After implement always run:**
```bash
npm run build          # verify webpack builds clean
composer run phpcs     # zero PHPCS errors
composer run phpstan   # PHPStan level 8 zero errors
npm run lint:js        # ESLint zero errors
```

---

### Phase 5 — Review & Commit

#### Step 12: `/speckit.analyze`

Cross-artifact consistency check. Verifies:
- All spec requirements (FR-NNN) have a corresponding task
- Plan architecture decisions are reflected in implementation
- No scope creep (tasks not in spec)

#### Step 13: `/speckit.architecture-guard.architecture-review`

Post-implementation drift analysis. Detects if code diverged from planned architecture.

#### Step 14: `/speckit.security-review.staged` or `/speckit.security-review.branch`

Final security audit of the actual changed code before commit.

#### Step 15: `/speckit.memory-md.capture-from-diff`

Extracts durable knowledge from the implementation diff and saves to `.specify/memory/`. This feeds future planning sessions.

#### Step 16: `/speckit.git.commit`

Commits all changes with a structured commit message. Always appended with:
```
Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>
```

---

## Recommended Workflow Sequence

```
─── ONE-TIME SETUP ───────────────────────────────────────────────────────────
/speckit.constitution
/speckit.memory-md.bootstrap
──────────────────────────────────────────────────────────────────────────────

─── PER FEATURE ──────────────────────────────────────────────────────────────

  SPEC
  /speckit.specify "<natural language description>"
  /speckit.clarify  ← OPTIONAL (only if spec is ambiguous)
        ↓
  PLAN
  /speckit.memory-md.plan-with-memory  (or /speckit.plan on feature 001)
  /speckit.architecture-guard.governed-plan
  /speckit.security-review.plan
        ↓
  TASKS
  /speckit.tasks
  /speckit.architecture-guard.governed-tasks
  /speckit.security-review.tasks
        ↓
  IMPLEMENT
  /speckit.architecture-guard.governed-implement  (or /speckit.implement)
  npm run build && composer run phpcs && composer run phpstan && npm run lint:js
        ↓
  REVIEW
  /speckit.analyze
  /speckit.architecture-guard.architecture-review
  /speckit.security-review.staged
        ↓
  CLOSE
  /speckit.memory-md.capture-from-diff
  /speckit.git.commit
  ✅ Feature complete

──────────────────────────────────────────────────────────────────────────────
```

---

## When to Skip Steps

| Step | Skip if | Run if |
|---|---|---|
| `/speckit.clarify` | Spec is clear, requirements agreed | Spec has gaps, stakeholders disagree |
| `governed-plan` vs `plan` | Feature 001 (no prior memory) | Feature 002+ (prior patterns exist) |
| `governed-implement` vs `implement` | Trivial config-only changes | Any new PHP class or React component |
| `security-review.staged` | Pure documentation PR | Any REST endpoint or capability check change |

---

## Actual Spec Artifacts (what each feature folder contains)

```
specs/NNN-feature-name/
├── spec.md              ← functional requirements (FR-NNN, US-N, SC-NNN)
├── plan.md              ← technical architecture and implementation blueprint
├── tasks.md             ← dependency-ordered, phase-grouped task list
├── research.md          ← technical research used by planner
├── data-model.md        ← DB schema / data shape definitions (if applicable)
├── quickstart.md        ← fast manual verification steps
├── memory.md            ← feature-specific memory artifacts
├── memory-synthesis.md  ← cross-feature learning synthesis
├── contracts/           ← API contract files
│   └── *.md
└── checklists/          ← quality gate checklists
    └── *.md
```

---

## Quick Start (new feature)

```
1. /speckit.git.feature
2. /speckit.specify "describe the feature in natural language"
3. /speckit.memory-md.plan-with-memory
4. /speckit.architecture-guard.governed-tasks
5. /speckit.architecture-guard.governed-implement
6. npm run build && composer run phpcs && composer run phpstan && npm run lint:js
7. /speckit.memory-md.capture-from-diff
8. /speckit.git.commit
```
