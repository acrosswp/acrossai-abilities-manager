# Bug Patterns


---

## Active Bug Patterns

### 2026-05-16 — BerlinDB unlimited query: `number => -1` silently becomes 1

**Status**: Active

**Symptoms**
`get_all_overrides()` returned exactly one row instead of all rows. No error.

**Root Cause**
BerlinDB's query builder passes `number` through `absint()`. `absint(-1) = 1`. The intended "unlimited" value of `-1` becomes a LIMIT of 1.

**Future mistake prevented**
Never use `number => -1` for unlimited BerlinDB queries. Always use `number => 0` (no LIMIT clause).

**Evidence**
Fixed in `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php::get_all_overrides()` (2026-05-16). Changed from `9999` → `0` after discovering the `absint` behavior.

**Prevention / Detection**
Code review: grep for `'number' => -1` in BerlinDB query() calls.

**Where to look next**
`includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` (get_all_overrides),
`specs/004-ability-override-processor/spec.md` (SC-004).

---

### 2026-05-16 — `inject_override_args` flat-path mistake: writing top-level `$args` keys

**Status**: Active

**Symptoms**
Override values for `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers` were written to flat top-level keys (`$args['readonly']` etc.) that WP core / merger never reads.

**Root Cause**
WP Abilities API uses a nested `$args` structure. Merger reads `$args['meta']['annotations']['readonly']`, `$args['meta']['show_in_rest']`, `$args['meta']['mcp']['public']`, etc. Writing to flat top-level keys has no effect — values are silently ignored.

**Future mistake prevented**
Always confirm `$args` write paths against `AcrossAI_Ability_Merger.php` read paths before implementing any new field injection. See FR-009 field-path table in `specs/004-ability-override-processor/spec.md`.

**Evidence**
Corrected in `AcrossAI_Ability_Override_Processor::inject_override_args()` (2026-05-16). Confirmed from `AcrossAI_Ability_Merger.php` grep of `annotations`, `show_in_rest`, `get_meta_item`.

**Prevention / Detection**
FR-009 field-path table in `specs/004-ability-override-processor/spec.md` is the canonical reference.

**Where to look next**
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (inject_override_args),
`includes/Modules/Sitewide/AcrossAI_Ability_Merger.php` (flatten / get_meta_item),
`specs/004-ability-override-processor/spec.md` (FR-009).


### 2026-05-17 — Partial-save paths fire `acrossai_abilities_sitewide_after_save` with incomplete `$fields`

**Status**: Active

**Symptoms**
Hook consumers registered on `acrossai_abilities_sitewide_after_save` received only `['site_allowed', 'source']` (2 keys) after bulk allow/disallow or toggle operations, while `save_override()` calls pass all 9 fields. Consumers checking any of the remaining 7 fields got undefined-index notices or silently wrong values.

**Root Cause**
`bulk_action()` (allow/disallow path) and `toggle_ability()` build a partial `$fields` array containing only the two fields they write, then fire `after_save` with that array. `do_action` passes arrays by value — hook consumers cannot reach back to re-fetch the row. Consumers relying on the canonical 9-field shape receive an incomplete payload.

**Future mistake prevented**
Any endpoint that performs a partial save MUST fetch the complete saved row after `save_override()` succeeds and pass the full row properties to `after_save`, not the local `$fields` variable. Use `get_override_by_slug($slug)` post-save; fall back to `$fields` only if the fetch fails.

**Evidence**
Fixed in commit `5c6fce6` — `AcrossAI_Sitewide_Bulk_Controller::bulk_action()` and `AcrossAI_Sitewide_Override_Controller::toggle_ability()`. Both now call `get_override_by_slug()` after save and build a 9-key `$hook_fields` array.

**Prevention / Detection**
When adding any new partial-save REST endpoint: grep for `do_action( 'acrossai_abilities_sitewide_after_save'` and verify the second arg is a full 9-field array, not a subset.

**Where to look next**
`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php` (bulk_action allow/disallow path),
`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php` (toggle_ability).

---

### 2026-05-17 — Extensibility filter declared in plan but never fired in implementation

**Status**: Active

**Symptoms**
Third-party consumers registered with `add_filter('acrossai_abilities_sitewide_rest_response', ...)` received zero calls. The hook was documented in `plan.md §V` as an exposed extensibility point but `apply_filters()` was never called anywhere in the implementation.

**Root Cause**
The filter was planned as an extensibility hook but the corresponding `apply_filters()` call was omitted during implementation. There is no compiler or linter that catches a declared-but-never-fired filter.

**Future mistake prevented**
After implementing any feature that declares hooks in plan.md, grep the codebase for every hook name listed in plan.md §V (or equivalent extensibility section) and confirm a matching `do_action()`/`apply_filters()` call exists. Cast filter return values to the expected type — `(array)` for REST response filters — to guard against consumers returning the wrong type crashing `rest_ensure_response()`.

**Evidence**
Fixed in commit `5c6fce6` — `apply_filters('acrossai_abilities_sitewide_rest_response', $merged, $slug)` added to `AcrossAI_Sitewide_Abilities_Controller::get_ability()` and `AcrossAI_Sitewide_Override_Controller::save_override()`. Return value cast to `(array)`.

**Prevention / Detection**
Implementation checklist: for every hook listed in spec/plan extensibility sections, verify `apply_filters()`/`do_action()` call exists in the shipped code before marking the feature done.

**Where to look next**
`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php` (get_ability),
`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php` (save_override),
`specs/001-sitewide-ability-management/plan.md` (§V hooks inventory).

---

### 2026-05-17 — `mcp_servers` is already decoded: never call json_decode() in the processor

**Status**: Active

**Symptoms**
`mcp_servers` value received as a double-decoded empty array or `null` unexpectedly; server
allowlist enforcement silently fails for all abilities.

**Root Cause**
`AcrossAI_Sitewide_Row::__construct()` already calls `json_decode()` on the raw DB `mcp_servers`
column and stores the result as `array|null` on `$row->mcp_servers`. If a consumer calls
`json_decode()` again on this value, it receives `null` (decoding an array) and the `is_array()`
guard fails — the servers allowlist is treated as unset.

**Future mistake prevented**
In `AcrossAI_Ability_Override_Processor::inject_override_args()` and any future consumer of
`$row->mcp_servers`: guard with `is_array( $row->mcp_servers )` only. Never call `json_decode()`
on this value — the Row constructor already handles decoding. The same applies to any Row class
field that decodes JSON in its constructor.

**Evidence**
Caught during T006 implementation (2026-05-16). `inject_override_args()` uses `is_array()` guard,
confirmed in PHPCS/PHPStan pass on `AcrossAI_Ability_Override_Processor.php`. Noted in
`memory-synthesis.md`: "mcp_servers already decoded — guard with is_array() only".

**Prevention / Detection**
Before writing any new field read from a BerlinDB Row object, grep `__construct()` of the Row
class for `json_decode` to confirm whether the field is already decoded.

**Where to look next**
`includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Row.php` (__construct, mcp_servers decode),
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (inject_override_args — is_array guard),
`specs/004-ability-override-processor/spec.md` (FR-009 mcp_servers note).



---

### 2026-05-24 — REST create receives `ability_slug` but endpoint expects `slug_suffix` (BUG-SLUG-SUFFIX-MISMATCH)

**Status**: Active

**Symptoms**
`POST /abilities` returns 422 with *"Slug suffix is required when creating an ability."* even though the form has a slug value. No visible error in the form state.

**Root Cause**
The React form stores the full slug (e.g. `acrossai-abilities/my-ability`) in the `ability_slug` field for display purposes. The REST write controller expects only the user-typed suffix (`my-ability`) in a field called `slug_suffix` — it prepends `acrossai-abilities/` server-side. Sending `ability_slug` causes the validator to find `slug_suffix` empty and reject the request.

**Future mistake prevented**
Any form that has a read-only prefix display + editable suffix input MUST extract the suffix before submit, send it as `slug_suffix`, and omit `ability_slug` from the create payload.

**Evidence**
Fixed in `ee8892e` — `AbilityForm.jsx::handleSave()`:
```js
const fullSlug = data.ability_slug || '';
data.slug_suffix = fullSlug.startsWith(SLUG_PREFIX)
    ? fullSlug.slice(SLUG_PREFIX.length) : fullSlug;
delete data.ability_slug;
```

**Prevention / Detection**
On any REST create form with a prefix+suffix slug: grep the payload for `ability_slug` — if present on a create call, it is wrong.

**Where to look next**
`src/js/abilities/components/AbilityForm.jsx` (handleSave — create branch),
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (create_ability — slug_suffix validation),
`includes/Utilities/AcrossAI_Abilities_Validator.php` (validate_slug).

---

## Template
### YYYY-MM-DD - Bug / Failure Pattern
**Status**
Active | Monitored | Retired

**Symptoms**
What was observed?

**Root Cause**
What actually caused it?

**Future mistake prevented**
What change pattern should future work avoid?

**Evidence**
Failing test, production incident, review finding, or verified fix.

**Prevention / Detection**
How should future work avoid it and how can we catch it sooner?

**Where to look next**
Files, modules, logs, or checks maintainers should inspect.

## BUG-LOOSE-COMPARISON-BYPASS

Loose comparison in access control checks allows type coercion attacks.

**Pattern**: Mixed integer/string comparisons in access checks bypass security. Example:
- `if ( $user_role == 'admin' )` → `0 == 'admin'` → TRUE (type coercion) ❌
- `if ( $user_role === 'admin' )` → `0 === 'admin'` → FALSE (correct) ✅

**When This Bug Occurs**:
- Mixed integer/string comparisons in access checks
- `in_array()` without `strict=true` parameter
- Loose equality (`==`) for identity checks

**Prevention Rule**: Always use strict comparison (`===`, `!==`) in security-sensitive code. Always pass `strict=true` to `in_array()` for access checks.

**Example Fix**:
```php
// WRONG
if ( in_array( $user_role, $admin_roles ) ) { grant_access(); }

// CORRECT
if ( in_array( $user_role, $admin_roles, true ) ) { grant_access(); }
```

**Reference**: Feature 005 implementation, security review (SECURITY-REVIEW.md "Type Safety" section), OWASP A03:2021 (Injection).


---

## 2026-05-20 — Access control permission checks silently fail when library returns null (BUG-AC-NULL-RETURN-SILENT-FAIL)

**Pattern**: Permission callback returns null instead of false; access control enforcement silently fails

**Root Cause**: Code that checks `if ( $manager->user_has_access(...) )` treats null as falsy, but code paths expecting explicit boolean logic may behave unexpectedly. If a library returns null instead of false, silent permission failures can occur.

**Example Scenario**:
```php
// Vulnerable pattern
if ( $manager->user_has_access($user_id, $namespace, $key) ) {
    allow_access();
} else {
    deny_access(); // null is falsy, so this executes, but semantically wrong
}
```

If library returns `null` instead of `false`, the code "works" (denies access) but for the wrong reason. Future code refactoring could break this assumption.

**Prevention Rules**:
1. Use typed return values in AC libraries (`: bool`, never nullable)
2. Add validation tests that confirm return type consistency:
   ```php
   $result = $manager->user_has_access($user_id, $namespace, $key);
   assert(gettype($result) === 'boolean', 'AC library must return bool, not null');
   ```
3. Validate at integration points before production deployment (T011 pattern)
4. Document return type contract in code comments and tests

**Verification Checklist**:
- [ ] Method signature explicitly declares `bool` return type (not nullable)
- [ ] Code inspection finds only `return true;` and `return false;` statements
- [ ] Unit tests verify return type with `gettype()` checks
- [ ] Integration tests call the method with multiple user roles; confirm boolean returns
- [ ] No `return null;` or `return $mixed;` patterns in method

**Evidence**:
Feature 007 (2026-05-20): T011 validated that wpb-access-control `user_has_access()` always returns boolean:
- Method signature: `: bool` (not nullable)
- Return statements: only `true` and `false`
- Test results: 100% boolean returns (admin=true, subscriber=false, null_user=boolean)
- Validation confirmed: `gettype() === 'boolean'` for all cases

**Related Constraints**:
- DEC-PERM-CB: Permission callback pattern relies on boolean return type
- SEC-04: Strict comparison in access control assumes boolean returns, not null

**Future Prevention**:
When reviewing access control library upgrades or integrations, make T011-style type validation a mandatory Phase 1 test. Treat return type regressions as Phase blockers.
