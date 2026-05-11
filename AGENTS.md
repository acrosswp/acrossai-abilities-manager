---
name: "AcrossAI Abilities Manager"
description: "Agency standards for professional AcrossAI Abilities Manager"
version: "1.0.0"
---

# Agency Standards

## Environment

```yaml
php_min_version: "7.4"
wordpress_min_version: "6.9"
node_version: "18.0"
npm_version: "9.0"
composer_version: "2.0"
```

## Plugin Configuration

```yaml
naming_prefix: "acrossai_"
coding_standard: "wpcs-strict"
multisite_support: true
```

## Security Requirements

```yaml
enforce_nonces: true
enforce_capabilities: true
sanitize_input: true
escape_output: true
sql_prepared_statements: true
file_upload_validation: true
```

## Code Quality

```yaml
phpcs_enabled: true
phpstan_level: 8
eslint_enabled: true
```

## Package Strategy

```yaml
prefer_wordpress_packages: true
validation_script: "npm run validate-packages"
package_hierarchy:
  tier_1: "@wordpress/* packages (official, always first)"
  tier_2: "npm packages (lodash, date-fns, etc.)"
  tier_3: "external frameworks (avoid duplicating React, Vue, etc.)"
```

## Before Commit Checklist

- [ ] PHPCS pass
- [ ] PHPStan pass
- [ ] All functions prefixed with "acrossai_"
- [ ] Nonces on all forms/AJAX
- [ ] Capabilities checked
- [ ] Input sanitized, output escaped
- [ ] No deprecated functions
- [ ] Package validation pass (npm run validate-packages)

---

# AI Engineering Rules

## Core Rules

- Follow WordPress Coding Standards
- Never perform broad refactors
- Never modify unrelated systems
- Execute one task at a time
- Read specs before implementation
- Update reports after implementation
- Update memory when new learnings are discovered
- Always run validation before completion

---

# Workflow

1. Read architecture.md
2. Read current feature spec
3. Read related memory
4. Read current task
5. Plan implementation
6. Execute scoped changes only
7. Run PHPCS
8. Run security validation
9. Run unit tests
10. Generate feature report
11. Update memory
12. Mark task complete

---

# WordPress Rules

- Escape output
- Sanitize input
- Use nonce validation
- Use capability checks
- Use wp_remote_get()
- Use wp_remote_post()
- Prefer Action Scheduler
- Avoid direct SQL unless required

---

# WooCommerce Rules

- HPOS compatible
- Use CRUD objects
- Use wc_get_orders()

---

# Testing Rules

Feature is NOT complete without:
- PHPCS validation
- Security review
- Unit tests

---

# Submodule Rules

Never modify files inside:

`.agents/tools/`

unless explicitly requested.

These repositories are external dependencies and must remain isolated from plugin implementation.

---

# Code Organization & Module Structure

When implementing the AcrossAI Abilities Manager plugin, follow this reusable component structure:

## Shared Utilities Directory
- Create a `includes/utilities/` directory for all reusable components
- All modules must reference shared utilities rather than duplicate code
- Never implement the same functionality twice across modules

## Base Classes and Inheritance
- Create base classes in `includes/base/` that all features extend from
- Extract common logic into abstract classes
- Use inheritance to prevent code duplication

## Reusable Components to Create
- Common form builders for standard input types (checkboxes, toggles, dropdowns, etc.)
- Common view generators for standard display types (lists, matrices, tables, etc.)
- Shared validation and sanitization functions
- Common data transformation utilities
- Shared API response formatters
- Shared permission checking utilities

## DataForms & DataViews Implementation
- Use WordPress DataForms for all form handling and data input
- Use WordPress DataViews for all data display and listing
- Create reusable DataForm components that other modules can use
- Create reusable DataView components that other modules can use
- DataForms must handle: form validation, error display, submission
- DataViews must provide: searchable lists, sorting, pagination, filtering

## When Implementing Features
1. Check if similar functionality exists in shared utilities first
2. Reuse existing base classes and utilities
3. Extract new common patterns into shared utilities
4. Never duplicate code from other modules
5. DRY principle: Don't Repeat Yourself - if code exists elsewhere, reuse it

## Code Review Checklist for Reusability
- [ ] No duplicate code between modules
- [ ] All common logic extracted to shared utilities
- [ ] Each module extends appropriate base classes
- [ ] Form patterns use shared form builders
- [ ] View patterns use shared view generators
- [ ] Validation uses shared validation functions
- [ ] Sanitization uses shared sanitization utilities
- [ ] DataForms used for all form implementations
- [ ] DataViews used for all data display implementations
