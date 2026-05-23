# Planning: Merge Abilities UI & Decommission Sitewide App

This document outlines the full Spec-Kit workflow for merging the Custom Abilities UI into the main Manager page and removing the obsolete sitewide application.

## Phase 1: Setup & Specification

Run these commands to initiate the feature and define requirements:

```markdown
# 1. Create a numbered feature branch
/speckit.git.feature "merge-abilities-ui"

# 2. Specify the feature requirements
# Use the detailed prompt below for the description
/speckit.specify "Collapse abilities UI into main manager page, remove sitewide application sources, and update webpack configuration."
```

### Description for `/speckit.specify`:
> **CONTEXT:** We are collapsing two admin pages into one and removing an obsolete 'sitewide' application.
> - **Target Page:** ?page=acrossai-abilities-manager (Main Manager Page)
> - **Source UI:** React app in `src/js/abilities/` (mounts to `#acrossai-abilities-root`)
> - **Obsolete UI:** React app in `src/js/sitewide/` (to be deleted)
>
> **CHANGES REQUIRED:**
> 1. Delete `src/js/sitewide/` and `src/scss/sitewide/`.
> 2. Remove 'js/sitewide' and 'css/sitewide' from `webpack.config.js`.
> 3. Update `admin/Main.php` to enqueue `abilities` assets on the manager page and remove `sitewide` logic.
> 4. Update `admin/Partials/Menu.php` to mount to `#acrossai-abilities-root`.
> 5. Disable submenu registration in `admin/Partials/AcrossAI_Abilities_Menu.php`.
> 6. Remove submenu hook wiring in `includes/Main.php`.

## Phase 2: Planning & Validation

Generate the technical plan and validate it against project architecture and security standards:

```markdown
# 3. Generate technical plan with memory context
/speckit.memory-md.plan-with-memory

# 4. Validate plan against Constitution
/speckit.architecture-guard.governed-plan

# 5. Review plan for security gaps
/speckit.security-review.plan
```

## Phase 3: Task Generation

Break the plan into executable tasks and verify sequencing:

```markdown
# 6. Generate implementation tasks
/speckit.tasks

# 7. Validate tasks for architecture drift
/speckit.architecture-guard.governed-tasks

# 8. Review task sequencing for security
/speckit.security-review.tasks
```

## Phase 4: Implementation & Verification

Execute the code changes and perform local quality checks:

```markdown
# 9. Execute tasks with governance and security review
/speckit.architecture-guard.governed-implement

# 10. Local Build & Lint (Manual Shell Commands)
# npm run build && npm run lint
```

## Phase 5: Review, Memory & Commit

Perform final audits and persist learnings before committing:

```markdown
# 11. Consistency check (Spec ↔ Plan ↔ Code)
/speckit.analyze

# 12. Post-implementation architecture review
/speckit.architecture-guard.architecture-review

# 13. Final security audit of changed code
/speckit.security-review.staged

# 14. Extract durable knowledge to project memory
/speckit.memory-md.capture-from-diff

# 15. Commit with structured message
/speckit.git.commit
```

### Manual Verification Checklist
- [ ] `abilities.js` loads on `?page=acrossai-abilities-manager`.
- [ ] `sitewide.js` is NOT loaded on any page.
- [ ] React mounts to `#acrossai-abilities-root`.
- [ ] `window.acrossaiAbilitiesManager` is present.
- [ ] "Custom Abilities" menu item is removed.
- [ ] Logger page remains functional.
