# Specification Clarifications: Custom Abilities Manager

**Feature**: 008-custom-abilities  
**Date**: 2026-05-20  
**Status**: Completed  

## Clarification Summary

Four high-impact ambiguities were identified and resolved through directed questioning:

---

## Q1: Audit Logging & Observability Strategy

**Issue**: FR-012 required audit logging but approach was unspecified.

**Resolution**: **Remove audit logging entirely**
- Removed FR-012 (audit requirement)
- Removed SC-006 (audit coverage metric)
- Removed `created_by`, `updated_by` columns from database schema (FR-001)
- Removed audit trail references from acceptance scenarios

**Rationale**: Admin requested no audit capture; system will not log CRUD operations.

---

## Q2: Readonly Abilities Behavior

**Issue**: Readonly flag semantics were ambiguous — was it a UI-only restriction or system-wide?

**Resolution**: **Readonly is metadata annotation only**
- Flag does NOT prevent mutations in admin UI or REST API
- Flag describes ability behavior to callers/MCP clients
- Updated FR-011 to clarify metadata-only nature
- Added edge case explaining readonly semantics
- Added assumption documenting metadata-only enforcement

**Rationale**: Readonly serves as capability descriptor for external systems, not enforcement mechanism.

---

## Q3: Noop Callback Purpose

**Issue**: Why would admins create abilities with noop (no operation) callbacks?

**Resolution**: **Noop = placeholder/documentation abilities**
- Abilities registered for discoverability but not executed via wp_execute_ability()
- Use case: external systems (MCP clients) reference ability in documentation without execution
- Updated FR-007 with clarified noop purpose ("placeholder/documentation")
- Added edge case explaining noop use case

**Rationale**: Enables registration-only abilities for external discovery without WordPress execution.

---

## Q4: Admin UI Accessibility

**Issue**: No accessibility requirements specified for admin forms/tables.

**Resolution**: **Inherit from DataForm/DataViews components**
- Admin UI accessibility satisfied by @wordpress/dataforms and @wordpress/dataviews
- No additional WCAG testing or accessibility documentation required
- Added FR-015 for component-level accessibility compliance
- Added assumption documenting accessibility inheritance

**Rationale**: Standard WordPress admin UI components provide WCAG 2.1 A compliance by design.

---

## Spec Changes Summary

| Section | Change |
|---------|--------|
| Functional Requirements | Removed FR-012; added clarification to FR-007 (noop), FR-011 (readonly), added FR-015 (accessibility) |
| Success Criteria | Removed SC-006 (audit coverage) |
| Database Schema | Removed `created_by`, `updated_by` columns |
| Edge Cases | Added 1 case (noop use), updated 1 case (readonly behavior) |
| Assumptions | Added 2 new assumptions (accessibility, readonly metadata) |
| Acceptance Scenarios | Removed audit trail reference from User Story 1 |

**Total FRs**: 14 (down from 15)  
**Total SCs**: 7 (down from 8)  
**Total Assumptions**: 12 (up from 10)  
**Total Edge Cases**: 7 (up from 6)

---

## Quality Gates

✅ All clarification questions answered  
✅ No [NEEDS CLARIFICATION] markers remain  
✅ Readonly behavior explicitly defined  
✅ Noop callback purpose documented  
✅ Accessibility approach finalized  
✅ Audit logging removed per request

**Status**: Ready for planning phase  
**Next Step**: `/speckit.plan` to begin technical design
