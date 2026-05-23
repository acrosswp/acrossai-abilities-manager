# Security Review — Feature 008: Unified Abilities Table

**Security Review Status**: ⚠️ **CONDITIONAL APPROVAL — 3 corrections required before implementation**

**Prior Review Date**: 2026-05-22 (First Review)
**This Review Date**: 2026-05-22 (Re-Review after corrections applied)
**Reviewed By**: Security Review Workflow (speckit.security-review.plan)

---

## Executive Summary

This is the second security review for feature `008-unified-abilities-table`.

The first review issued **Conditional Approval** with 3 plan-vs-spec violations (V1 method name, V2 status varchar width, V3 label nullability) and 4 advisory findings (F1 filter injection risk, F2 enum guards absent, F3 missing docblock note, F4 JSON size deferred). The user confirmed all 3 violations have been corrected in plan.md and the plan has been annotated "Plan Corrections Applied (2026-05-22)".

This re-review confirms the V1/V2/V3 corrections are present. However, the plan's implementation of the F1 guard introduces a **new design conflict**: the allowlist-based guard in `get_json_fields()` makes the `acrossai_abilities_json_fields` filter completely non-functional for extension, which directly contradicts spec requirements SC-005 and FR-009. This is a blocking issue.

Two additional low-severity corrections are required (status value inconsistency in docblock; `by_source()` capability-check note missing). Two advisory items carry forward.

**Net result**: 1 blocker (N1 — design conflict), 2 low-severity (N2, N3), 2 advisory (N4, N5).

---

## Plan Artifacts Reviewed

| Artifact | Status |
|---|---|
| `specs/008-unified-abilities-table/plan.md` (full) | ✅ Read |
| `specs/008-unified-abilities-table/spec.md` (full) | ✅ Read |
| `specs/008-unified-abilities-table/tasks.md` (full: T001–T006) | ✅ Read |
| `specs/008-unified-abilities-table/memory-synthesis.md` | ✅ Read |
| `specs/008-unified-abilities-table/security-constraints.md` (prior review) | ✅ Read |
| `.specify/memory/CONSTITUTION.md` | ✅ Read (§I–§VI) |
| `docs/memory/ARCHITECTURE.md` | ✅ Read |
| `docs/memory/DECISIONS.md` | ✅ Read |

---

## Delta: Prior Violations — All Confirmed Corrected

### ✅ V1 (Resolved): Method Name `by_source()`

Plan File 4 and tasks.md T004 now use `by_source()` throughout. FR-019 is satisfied. The method signature in the plan is `public function by_source( ?string $source ): array` with watchpoint noting "Method name MUST be by_source() — NOT get_all_by_source()".

### ✅ V2 (Resolved): `status` Column `varchar(20)`

`set_schema()` SQL contains `` `status` varchar(20) NOT NULL DEFAULT 'draft' ``. Schema column definition uses `'length' => '20'`. FR-020 is satisfied. T001 and T002 acceptance criteria both carry the `varchar(20)` watchpoint.

### ✅ V3 (Resolved): `label` Column Nullable

`set_schema()` SQL contains `` `label` varchar(255) DEFAULT NULL ``. Schema column definition uses `'allow_null' => true`, `'default' => null`. Row property is `public $label = null;` with `@var string|null`. FR-021 is satisfied.

---

## Vulnerability Findings

### FINDING N1: F1 Allowlist Guard Breaks SC-005 and FR-009 Extension Point

**Category**: Design Conflict / Spec Violation
**Severity**: Medium — Blocker
**Prior Finding Reference**: F1 (first review recommended blocklist; plan implemented allowlist instead)

**Assessment**:

The plan implements the F1 guard using an **allowlist** approach:

```php
public static function get_json_fields(): array {
    $allowed_json_columns = array(
        'mcp_servers', 'callback_config', 'input_schema', 'output_schema',
    );

    $fields = apply_filters(
        'acrossai_abilities_json_fields',
        $allowed_json_columns
    );

    // F1: Strip any field name that is not a known longtext/JSON column.
    return array_values(
        array_filter(
            (array) $fields,
            static function ( $field ) use ( $allowed_json_columns ) {
                return in_array( $field, $allowed_json_columns, true );
            }
        )
    );
}
```

Because `$allowed_json_columns` serves as both the filter default **and** the allowlist for validation, any field name added by a third-party filter consumer is immediately stripped. The filter becomes a no-op: it can only confirm the existing 4 fields or reduce them — it cannot add new ones.

**This contradicts SC-005 and FR-009 directly**:

- **SC-005** (spec.md): "Adding a new JSON field via the filter (FR-009) causes it to be automatically decoded on read and encoded on save without any further code changes."
- **FR-009** (spec.md): "The list of JSON-encoded fields MUST be governed by a single filterable registry."
- **SC-005 test would fail**: A test adding `'custom_json'` via the filter would result in the field being stripped — not auto-decoded/encoded.

The plan's own security note acknowledges this: *"If a third party needs to add a genuinely new longtext/JSON column, they must add it to the `$allowed_json_columns` array in this method."* This is a closed-system design — secure but incompatible with the spec's extensibility contract.

The prior review's recommended **blocklist** approach prevents the actual risk (scalar field injection) while preserving extensibility:

```php
public static function get_json_fields(): array {
    $base = array( 'mcp_servers', 'callback_config', 'input_schema', 'output_schema' );
    $filtered = apply_filters( 'acrossai_abilities_json_fields', $base );

    // F1: Protected scalar columns that MUST NOT appear in the JSON field list.
    // Injecting these into the JSON decode loop would silently corrupt Row properties.
    $protected = array(
        'id', 'ability_slug', 'label', 'status', 'enabled', 'source',
        'provider', 'category', 'callback_type', 'mcp_type',
        'created_at', 'updated_at', 'created_by', 'updated_by',
    );

    return array_values( array_filter(
        (array) $filtered,
        static function ( $field ) use ( $protected ) {
            return is_string( $field ) && ! in_array( $field, $protected, true );
        }
    ) );
}
```

This allows adding new longtext JSON columns (e.g., `custom_metadata`) while blocking the actual injection vector (scalar columns being decoded as JSON in `__construct()`).

**Required correction**: Replace the allowlist guard with the blocklist approach above. Update the docblock to reference the protected-column list and explain why each group is protected.

**Alternative acceptable resolution**: If the intent is to close the extension point for v1, update spec.md FR-009 and SC-005 to remove the extensibility contract, and the allowlist approach is acceptable. Do NOT silently contradict the spec.

**Implementation Verification Checklist**:
- [ ] `get_json_fields()` blocklist includes all 14 scalar/computed columns
- [ ] Adding `'custom_json'` via filter returns `['mcp_servers', 'callback_config', 'input_schema', 'output_schema', 'custom_json']`
- [ ] Adding `'ability_slug'` via filter returns unchanged base list (stripped)
- [ ] Docblock explains the protected-column set and the injection risk it prevents
- [ ] SC-005 acceptance test: add a new longtext column + its filter entry → auto-decoded on read, auto-encoded on save

---

### FINDING N2: `$status` Docblock Lists Invalid Values

**Category**: Data Integrity / Documentation Inconsistency
**Severity**: Low

**Assessment**:

File 3 (Row) in the plan declares `$status` with this docblock:
```php
/** Lifecycle status (draft|published|archived). @var string */
public $status = 'draft';
```

The F2 guard in File 4 (Query, `save_override()`) enforces:
```php
$allowed_statuses = array( 'draft', 'publish' );
```

Three inconsistencies:
1. `published` (docblock) ≠ `publish` (F2 guard and WordPress convention)
2. `archived` (docblock) is not in `$allowed_statuses` — it would be silently stripped by the F2 guard
3. `published` is not a valid WordPress status token — `publish` is the standard

A developer reading the Row docblock would reasonably pass `status => 'published'` or `status => 'archived'` to `save_override()`. Both would be silently dropped by the F2 guard, falling back to the column's DEFAULT (`'draft'`). This silent failure creates confusing behavior without any error indication (the F2 guard uses `unset()` not `error_log()`, unlike the prior review's recommendation).

**Required correction**:
- Change docblock to `/** Lifecycle status. Valid: 'draft', 'publish'. @var string */`
- T003 acceptance criteria should verify the docblock matches the F2 guard's allowlist exactly

**Note on error logging**: The prior review (F2) recommended logging (`error_log()`) on invalid status/callback_type. The plan drops the error log. This is acceptable (avoid leaking internal field names to logs), but the silent `unset()` makes debugging harder. Advisory: add a `do_action('acrossai_abilities_debug', ...)` hook or at minimum keep the `error_log()` under `WP_DEBUG` check.

---

### FINDING N3: `by_source()` Docblock Missing Capability-Check Caller Contract

**Category**: Security Documentation
**Severity**: Low
**Prior Finding Reference**: F3 (first review — required)

**Assessment**:

The `by_source()` docblock in the plan reads:
```php
/**
 * Retrieve all ability records matching a given source (FR-019).
 *
 * Method name MUST be by_source() — spec 009 calls it by this exact name.
 * Returns [] immediately when $source is empty or null (FR-011).
 * Uses 'number' => 0 for unlimited results (BUG-BERLINDB-UNLIMITED pattern).
 *
 * @since  0.1.0
 * @param  string|null $source Source value to filter by (e.g. 'db', 'plugin').
 * @return AcrossAI_Sitewide_Row[]
 */
```

The F3 finding from the prior review explicitly required adding a capability-check caller contract. This is still absent. The tasks.md T004 acceptance criteria do not include it. If a future caller (REST controller, processor, or third-party integration) invokes `by_source()` without first checking `current_user_can('manage_options')`, ability records would be exposed without authorization.

This is consistent with the existing `get_all_overrides()` pattern, but that method is also undocumented on this point. Establishing the pattern now prevents the same mistake in all future callers.

**Required correction**: Add to the `by_source()` docblock:
```php
 * IMPORTANT: This method performs no capability check. All callers
 * MUST verify current_user_can( 'manage_options' ) before invoking.
```

**Add to T004 acceptance criteria**:
- `by_source()` docblock includes explicit caller capability-check requirement

---

### FINDING N4: F4 JSON Size/Depth — No T006 Quality Gate Step

**Category**: Denial of Service / Data Integrity
**Severity**: Advisory (carry-forward from prior F4)

**Assessment**:

F4 (first review) deferred JSON depth and size validation for `callback_config`, `input_schema`, `output_schema` to REST controllers, which is architecturally correct. The plan acknowledges this is caller responsibility. However:

- No T004 or T006 step verifies that existing REST controllers enforce size/depth limits on these 3 new fields
- No `save_override()` comment documents the caller contract ("JSON field size validation is the caller's responsibility")
- `input_schema` and `output_schema` can be arbitrarily complex JSON Schema Draft 7 documents; without depth/size limits, a crafted payload could exhaust memory during `json_decode()`

The prior review recommended: max 64KB per field, max 10 nesting levels.

**Advisory — add to T006 (or create a follow-up task)**:
- Verify `AcrossAI_Sitewide_Abilities_Controller` / `AcrossAI_Sitewide_Override_Controller` validate `callback_config`, `input_schema`, `output_schema` size (≤ 64KB) before calling `save_override()`
- Add comment to `save_override()`: `// JSON field size/depth validation is the caller's responsibility.`

---

### FINDING N5: `enabled` Bool-to-Int Cast in `save_override()` Undocumented

**Category**: Data Integrity
**Severity**: Advisory

**Assessment**:

`AcrossAI_Sitewide_Row::__construct()` explicitly casts `$this->enabled = (bool) $this->enabled` on read — correct. However, `save_override()` in the plan has no documented cast of the PHP `enabled` field to `int` before BerlinDB write.

The existing tri-state cast block handles `site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp` — but `enabled` must NOT be in that list (it's a regular bool, not tri-state). Without an explicit cast, PHP `true` could reach BerlinDB, which may handle it internally (MySQL accepts non-zero as `1`) — but this is not guaranteed behavior.

**Advisory — add to T004 implementation notes**:
```php
// Normalise enabled to tinyint before save (not tri-state, just bool-to-int).
if ( array_key_exists( 'enabled', $fields ) ) {
    $fields['enabled'] = (int) (bool) $fields['enabled'];
}
```

---

## Confirmed Secure Patterns

### ✅ Multisite Isolation (SEC-03)

`AcrossAI_Sitewide_Table::$global = false` is preserved unchanged. T001, T006 both carry explicit SEC-03 watchpoints. Per-site table prefix (`{prefix}acrossai_abilities`) ensures per-site isolation in WP networks.

### ✅ BerlinDB Prepared Statements

All mutations (`add_item()`, `update_item()`, `delete_item()`) and reads (`query()`) route through BerlinDB, which uses `$wpdb->prepare()` internally. No raw SQL interpolation is introduced in the 5 modified files.

### ✅ `WP_UNINSTALL_PLUGIN` Guard

`uninstall.php` retains the `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }` guard. New DROP statements use `{$wpdb->prefix}` (set from wp-config.php, not user input). The `IF EXISTS` clause prevents fatals on partial installs.

### ✅ Audit Field Immutability

`save_override()` unsets `created_at` and `created_by` before `update_item()`. The plan preserves this pattern (it's part of the "3 preserved blocks" set indirectly). Confirmed in the acceptance criteria.

### ✅ JSON Encode Failure Path (FR-010)

Registry loop stores `null` (not `false`) on `wp_json_encode()` failure and continues:
```php
$fields[ $json_field ] = false !== $encoded ? $encoded : null;
```
Prevents PHP `false` from reaching a `longtext` column and allows partial saves to succeed.

### ✅ Strict `is_array()` Check (SEC-04)

JSON encoding loop uses `is_array( $fields[ $json_field ] )` — strict type check. Non-array values (strings, ints, booleans already in string/int form) pass through unmodified. Only PHP arrays are encoded.

### ✅ BUG-BERLINDB-UNLIMITED Pattern

`by_source()` uses `'number' => 0`. The plan, tasks.md T004 watchpoints, and T006 quality gate step all explicitly flag the `-1` anti-pattern (`absint(-1) = 1` → 1 row silently). Correctly resolved.

### ✅ F2 Enum Guards — Status and Callback Type

Plan adds F2 guards in `save_override()` for `status` (allowlist: `['draft', 'publish']`) and `callback_type` (allowlist: `['noop', 'filter_hook', 'wp_remote_post']`). Guards use strict `in_array(..., true)`. Guards placed after the tri-state cast block and before the JSON registry loop. Consistent with the existing `mcp_type` guard pattern.

### ✅ FR-018 Three Preserved Blocks

Plan explicitly retains all 3 existing `save_override()` blocks: (1) tri-state bool-to-int cast, (2) `mcp_type` value validation, (3) `mcp_servers` non-string guard. T004 acceptance criteria requires byte-for-byte diff equality on these 3 blocks. The new registry JSON loop replaces only the old hardcoded `mcp_servers` encode block.

### ✅ `enabled` Cast Placement in `__construct()`

`(bool) $this->enabled` runs before the JSON decode loop. `enabled` is not in the JSON field registry, preventing accidental `json_decode()` on the `1`/`0` tinyint value. A `NULL` DB value casts to `false` (disabled by default — safe).

### ✅ `$version = '1.0.0'` Freeze (FR-017)

Bumping this would trigger BerlinDB's `maybe_upgrade()` DDL on existing sites, which would fail because the old table doesn't have the new columns. Keeping it at `'1.0.0'` ensures the new schema is new-install-only — consistent with the spec's accepted assumption ("no data migration required").

### ✅ UNIQUE KEY on `ability_slug`

`set_schema()` SQL promotes `ability_slug` from a non-unique `KEY` to `UNIQUE KEY ability_slug (191)`. This enforces slug uniqueness at the DB layer. Since `$version` is frozen (FR-017), this constraint applies to new installs only — but it is present and correct for the feature scope.

### ✅ Backward-Compat Uninstall

`uninstall.php` adds new DROP/delete_option for `acrossai_abilities` / `acrossai_abilities_db_version` while preserving the existing `acrossai_abilities_overwrite` / `acrossai_abilities_overwrite_db_version` cleanup lines. New DROP precedes old DROP — correct ordering.

---

## Constitution Compliance Summary

| Principle | Status | Notes |
|---|---|---|
| §I Modular Architecture | ✅ | 5-file constraint (FR-015) maintained; no cross-module coupling introduced |
| §II WordPress Standards | ✅ | WPCS, PHPStan L8, BerlinDB prepared statements, ABSPATH guards required |
| §III User-Centric Design | N/A | No admin UI changes in this feature |
| §IV Security First | ⚠️ | F1 guard approach creates spec/security trade-off; N2, N3 corrections needed |
| §V Extensibility | ⚠️ | F1 allowlist contradicts SC-005 / FR-009 filter extensibility contract |
| §VI Reusability / DRY | ✅ | JSON registry is single source of truth across Row + Query |
| §VII Definition of Done | ⚠️ | PHPCS/PHPStan/tests pending implementation; quality gate T006 covers these |

---

## Summary of All Findings

| # | Type | Severity | Description | Correct Before Implementation? |
|---|------|----------|-------------|-------------------------------|
| N1 | Design Conflict | **Medium — Blocker** | F1 allowlist guard neutralizes `acrossai_abilities_json_fields` filter — SC-005 and FR-009 extensibility contract broken | **Yes** |
| N2 | Docblock Inconsistency | Low | `$status` docblock lists `draft\|published\|archived`; F2 guard only allows `['draft','publish']` — "published" ≠ "publish", "archived" not valid | **Yes** |
| N3 | Security Documentation | Low | `by_source()` docblock missing explicit `manage_options` caller-responsibility note (required by prior F3) | **Yes** |
| N4 | Advisory | Advisory | F4 JSON size/depth: no T006 quality gate step or save_override() caller contract comment | At implementation |
| N5 | Advisory | Advisory | `enabled` bool-to-int cast in `save_override()` undocumented; relies on implicit DB behavior | At implementation |

**Prior violation status**: V1 ✅ V2 ✅ V3 ✅ — all corrected
**Prior advisory status**: F1 → N1 (new form, still blocking), F2 ✅ (resolved), F3 → N3 (carry-forward), F4 → N4 (carry-forward)

---

## Required Plan Corrections (Before Tasks Can Proceed)

1. **N1 — Replace F1 allowlist with blocklist**: In `get_json_fields()`, replace the `in_array($field, $allowed_json_columns)` allowlist check with the denylist approach using the 14-element `$protected` array. Update the docblock to explain the protected-column set. Alternatively: update spec.md to remove SC-005 and FR-009 filter extensibility requirements and document the intentional closed-system design.

2. **N2 — Fix `$status` property docblock**: Change `draft|published|archived` to `draft|publish`. Verify alignment with F2 guard `['draft', 'publish']` allowlist. Optionally add `'archived'` to both docblock and F2 guard if "archived" is intended as a valid future lifecycle state.

3. **N3 — Add capability-check note to `by_source()` docblock**: Insert the sentence: `"IMPORTANT: This method performs no capability check. All callers MUST verify current_user_can( 'manage_options' ) before invoking."` Also add to T004 acceptance criteria.

---

## Approval

**Security Review Status**: ⚠️ **CONDITIONAL APPROVAL — 3 plan corrections required (N1, N2, N3)**

After N1 is resolved (F1 guard approach decided + spec aligned), N2 is corrected (docblock), and N3 is added (docblock), this feature is **approved for implementation**.

**Reviewed By**: Security Review Workflow (speckit.security-review.plan)
**Review Date**: 2026-05-22
