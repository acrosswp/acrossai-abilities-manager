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
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (get_all_overrides),
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
`includes/Modules/Abilities/Rest/` (apply pattern to Abilities REST write paths — Sitewide deleted in Feature 012),
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (any partial-save path).

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
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php` (__construct, mcp_servers decode),
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (inject_override_args — is_array guard),
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

---

### 2026-05-24 — include of .asset.php without file_exists guard causes PHP fatal (BUG-UNCONDITIONAL-ASSET-INCLUDE)

**Status**: Active

**Symptoms**
PHP fatal error on every page load: `include(): Failed opening '.../build/js/sitewide.asset.php'`. Occurs when a webpack bundle is decommissioned or has not been built yet.

**Root Cause**
`Admin\Main::__construct()` contained an unconditional `include` of `build/js/sitewide.asset.php`. When that file was deleted during decommissioning, the missing file caused a PHP fatal with no recovery path. The `logger.asset.php` and `abilities.asset.php` loads immediately below used `file_exists()` guards — confirming the correct pattern existed but was never applied to the older sitewide bundle.

**Future mistake prevented**
Every `include` of a `build/*.asset.php` file in any constructor MUST be wrapped in a `file_exists()` guard. Optional or feature-specific bundles must use:
```php
$asset_path = \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/bundle.asset.php';
if ( file_exists( $asset_path ) ) {
    $this->bundle_asset_file = include $asset_path;
}
```

**Evidence**
Feature 011 (2026-05-24): `$this->sitewide_asset_file = include ... 'build/js/sitewide.asset.php'` unconditional include identified as PLAN-SEC-003 in `specs/011-merge-abilities-ui/security-constraints.md`. Fixed by removing the include entirely (T008). The `logger_asset_file` and `abilities_asset_file` loads remain as the canonical examples of the correct pattern.

**Prevention / Detection**
Before adding any `include .asset.php` to a constructor, grep `admin/Main.php` for existing `file_exists()` guard examples and apply the same guard. When decommissioning a bundle, remove the PHP include BEFORE deleting the asset file.

**Where to look next**
`admin/Main.php` (constructor — logger and abilities asset guards as correct examples),
`specs/011-merge-abilities-ui/security-constraints.md` (PLAN-SEC-003),
`specs/011-merge-abilities-ui/tasks.md` (T008, RISK-001).


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

---

### 2026-05-25 — PHPDoc long description must start with a capital letter (BUG-PHPCS-DOCBLOCK-CAPITAL)

**Status**: Active

**Symptoms**
PHPCS reports `Doc comment long description must start with a capital letter` even after phpcbf auto-fixes other docblock issues. The test file appears clean from phpcbf but still has 1 error.

**Root Cause**
phpcbf handles short description capitalization automatically but does NOT rewrite long descriptions. A long description starting with a function name (e.g. `sanitize_ability_slug() enforces…`) is non-capitalized in phpcbf's view and must be manually prefixed.

**Future mistake prevented**
After any phpcbf pass, check long descriptions starting with function names or lowercase words. Rewrite as `The functionName() function …` or any sentence-case phrasing. Grep: `grep -n '^ \* [a-z]' FILE.php` to spot remaining violations.

**Evidence**
Feature 012 T030 — `AcrossAI_Abilities_Query_Override_Test.php` line 121: `sanitize_ability_slug() enforces a 255-char maximum…` → fixed to `The sanitize_ability_slug() function enforces…` (2026-05-25).

**Prevention / Detection**
After phpcbf: run `./vendor/bin/phpcs FILE.php` and look for `long description must start with a capital letter`. Python str_replace to prepend `The `.

**Where to look next**
Any test file with BerlinDB method docblocks after phpcbf.

---

### 2026-05-25 — phpcbf converts space indentation to tabs; Python str_replace must use \t (BUG-PHPCBF-TABS)

**Status**: Active

**Symptoms**
Python `str_replace` on a PHP file returns "not found" after phpcbf has already run on that file, even though the target string looks correct in the editor.

**Root Cause**
phpcbf enforces tab indentation in PHP files. If a file was drafted with space indentation (e.g., 9 leading spaces), phpcbf converts them to tabs. Any Python string literal using spaces to match indentation will fail to find the string post-phpcbf.

**Future mistake prevented**
When using Python str_replace on PHP files that have been or will be processed by phpcbf, always use `\t` (tab) for indentation in both `old` and `new` strings.

**Evidence**
Feature 012 T030 — multiple Python str_replace calls failed with "not found" after phpcbf; re-attempted using `\t` and succeeded (2026-05-25).

**Prevention / Detection**
Before Python str_replace on any PHP file: read the raw bytes of one line (e.g. `sed -n 'Np' FILE.php | hexdump -C | head -2`) to confirm tab vs space. Use `\t` in Python string literals accordingly.

**Where to look next**
Any PHP test file that phpcbf has processed.

---

### 2026-05-25 — Python str_replace scripts: write per-step, not once at end (BUG-PYTHON-STRREPLACE-PARTIAL-WRITE)

**Status**: Active

**Symptoms**
A Python str_replace script completes earlier steps successfully but all edits are lost when an assertion later in the script raises before the final `f.write(c)` call.

**Root Cause**
Scripts that read the file into memory, perform all transformations, then write once at the end discard every in-memory edit if any assert raises before reaching the write. The file on disk is unchanged despite apparently successful earlier steps.

**Future mistake prevented**
Write to disk after each successful transformation step, or verify all assertions on the immutable original content before making any edits. Never defer the single write to the end of a multi-step script.

**Evidence**
Feature 013 — multiple T015/T017 scripts lost edits due to late write; mid-script assertion failures left the target files unmodified.

**Prevention / Detection**
Pattern to avoid: `with open(path, 'w') as f: f.write(c)` at end of a script that mutates `c` across multiple steps with assertions between them. Prefer: write after each step, or restructure so all assertions run on the original file before modifications begin.

**Where to look next**
Any multi-step Python str_replace automation script targeting JSX or PHP files.

---

### 2026-05-25 — AbilityForm.jsx has inconsistent tab depths by element type (BUG-ABILITYFORM-JSX-MIXED-DEPTHS)

**Status**: Active

**Symptoms**
Python str_replace on `AbilityForm.jsx` fails with "not found" even when the target string looks correct, because the actual indentation depth differs from what was assumed.

**Root Cause**
`AbilityForm.jsx` has inconsistent tab depths by element type:
- Label/description inputs: 11-tab attrs + 10-tab `/>`
- Category select: 11-tab attrs + 10-tab `>`
- Slug input: 12-tab attrs + 11-tab `/>`

There is no single uniform depth. Each element must be verified individually before str_replace.

**Future mistake prevented**
Always read the actual raw tab depth of the target element in `AbilityForm.jsx` before constructing a str_replace string. Do not assume uniform depth.

**Evidence**
Feature 013 T009–T016 — multiple str_replace mismatches caused by incorrect assumed tab depths.

**Prevention / Detection**
Before any str_replace on `AbilityForm.jsx`: read the relevant section with `read_file` and count tabs (or hexdump a target line) to confirm actual indentation.

**Where to look next**
`src/js/abilities/components/AbilityForm.jsx` (any form field addition or modification).

---

### 2026-05-25 — Adding a SEC-04 guard: audit same method for pre-existing empty() calls (BUG-SEC04-EMPTY-AUDIT-MISS)

**Status**: Active

**Symptoms**
Security review flags pre-existing `empty()` calls in the same method that just received a new `'' === trim()` guard — meaning the SEC-04 violation was present before the feature and was missed during implementation.

**Root Cause**
When adding a new strict-comparison guard (`'' === trim()`) to a method for SEC-04 compliance, the implementer focused on the new field and did not audit the same method for pre-existing `empty()` calls on other fields that also violate SEC-04.

**Future mistake prevented**
On any SEC-04 guard addition, grep the same method for `empty(` and replace every `empty($row->field)` call with `'' === trim((string) $row->field)` in the same commit.

**Evidence**
Feature 013 T007 added a description guard to `is_row_registrable()` but pre-existing `empty($row->label)` and `empty($row->category)` were not updated until the security review caught them (finding SEC-04-P1).

**Prevention / Detection**
After adding any `'' === trim()` guard: `grep -n 'empty(' FILE.php` — if any `empty(` remains in the same method, fix it before submitting.

**Where to look next**
`includes/Utilities/AcrossAI_Abilities_Validator.php` (is_row_registrable, all field guards),
Feature 013 security review finding SEC-04-P1.

---

### 2026-05-25 — PHPStan exits 0 with no output when clean; silence is a pass (BUG-PHPSTAN-SILENT-PASS)

**Status**: Active

**Symptoms**
PHPStan runs and produces no stdout output. Developer interprets silence as an error or a failed execution.

**Root Cause**
PHPStan's default output on a clean run is no output (or only a brief "no errors" line depending on version/formatter). An empty stdout with exit code 0 means the analysis passed with zero errors — it is not a failure.

**Future mistake prevented**
Do not interpret empty PHPStan stdout as a failure. Check the exit code: exit 0 = clean pass, exit 1 = errors found.

**Evidence**
Feature 013 — confusion arose when PHPStan returned exit 0 with empty stdout; the run was clean but was initially misread as broken.

**Prevention / Detection**
Always capture and check the exit code: `./vendor/bin/phpstan analyse ... ; echo "Exit: $?"`. Exit 0 with no output is a clean pass.

**Where to look next**
Any PHPStan run in CI scripts or manual verification steps.

---

### 2026-05-26 — `rawurldecode` + allowlist regex needs consecutive-slash normalization (BUG-RAWURLDECODE-CONSECUTIVE-SLASHES)

**Status**: Active

**Symptoms**
A slug like `acrossai-abilities%2F%2Fmy-ability` passes `sanitize_ability_slug()` with double slashes (`//`) intact, potentially reaching the DB or matching the wrong row.

**Root Cause**
`sanitize_ability_slug()` calls `rawurldecode()` then applies an allowlist regex `[^a-zA-Z0-9\-_\/]`. The forward-slash `/` is intentionally allowed for namespaced slugs. After decoding, `%2F%2F` becomes `//` — two consecutive slashes — which are not stripped by the allowlist regex. Without normalization, double-slash slugs pass through to the DB.

**Future mistake prevented**
Any sanitizer function that (a) calls `rawurldecode()` before an allowlist regex and (b) allows `/` in the allowlist MUST also add `preg_replace('/\/+/', '/', $slug)` immediately after the allowlist pass to collapse consecutive slashes. The max-length guard alone is insufficient to prevent this.

**Evidence**
SEC-001 guard added to `AcrossAI_Sanitizer::sanitize_ability_slug()` in Feature 014 (2026-05-26). The `preg_replace('/\/+/', '/', $slug)` line is placed between the allowlist `preg_replace` and the `substr` max-length guard.

**Prevention / Detection**
When adding a new `rawurldecode()` call anywhere in the sanitization pipeline, grep for the allowlist pattern and confirm a consecutive-slash normalization step follows it.

**Where to look next**
`includes/Utilities/AcrossAI_Sanitizer.php` (sanitize_ability_slug — SEC-001 comment),
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (sanitize_callback usage — SEC-003 ordering comment).

---

### 2026-05-26 — WP REST API: literal-segment routes must register before wildcard `[^/]+` routes (BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD)

**Status**: Active

**Symptoms**
`GET /abilities/categories` returns a 404 or single-ability "not found" response instead of the categories list. The route resolves to the wrong controller.

**Root Cause**
WP REST API (`WP_REST_Server::match_request_to_handler`) iterates routes in insertion order and uses the first pattern that matches both path and method. A wildcard pattern like `/abilities/(?P<slug>[^/]+)` matches the literal path segment `categories` just as easily as a real slug. If the wildcard route is registered before the literal `/abilities/categories` route, any `GET /abilities/categories` request is silently hijacked by the slug handler.

**Future mistake prevented**
In the REST orchestrator `register_routes()`, always call `register_routes()` on the controller that owns **literal sub-paths** (e.g., `/abilities/categories`) **before** calling `register_routes()` on controllers that own wildcard `[^/]+` patterns under the same parent. Correct order in this plugin: Category → Write → Read → Exposure.

**Evidence**
Constitution §REST-ROUTE-ORDER constraint. Verified in `AcrossAI_Abilities_Rest_Controller::register_routes()` (Feature 014): Category is registered first (line 74), Write second (75), Read third (76), Exposure last (77).

**Prevention / Detection**
Architecture review checklist: for every REST orchestrator, confirm all literal-path sub-controllers are listed before wildcard-path sub-controllers in `register_routes()`.

**Where to look next**
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php` (register_routes — correct insertion order),
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Category_Controller.php` (/abilities/categories — must register first).

---

### 2026-05-27 — BerlinDB stale slug cache after INSERT causes first-save to return no row

**Status**: Active

**Symptoms**
First save of a non-db ability override returns `has_override: false` in the REST response. On page reload the override appears correctly.

**Root Cause**
BerlinDB's Query base class maintains an internal per-request slug cache. Before the INSERT, `save_override()` calls `get_override_by_slug()` to check for an existing row — BerlinDB caches a null result for that slug (no row exists yet). After `add_item()` inserts the new row, any further `get_override_by_slug()` call in the same request hits the stale cache and returns null. The Write Controller's post-save re-query thus returned null → `has_override: false`.

**Future mistake prevented**
After any `add_item()` INSERT in `save_override()`, re-read the row via an ID-based `query(['id' => $new_id, 'number' => 1])` call — ID-based queries bypass the slug cache entirely. Encapsulate this in `get_ability_by_id(int $id)`. All external callers remain slug-oriented and are unaware of the cache bypass. Never rely on `get_override_by_slug()` immediately after a first-ever INSERT in the same request.

**Evidence**
`AcrossAI_Abilities_Query::save_override()` — Bug 4 fix (Feature 015): `return $this->get_ability_by_id( (int) $result ) ?? false;` after `add_item()`. Same pattern applied to UPDATE path.

**Where to look next**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (`save_override` — ID re-read after INSERT/UPDATE),
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (uses returned row directly).

---

### 2026-05-27 — `meta.mcp.public` maps to `show_in_mcp`; never to a separate `mcp_public` key

**Status**: Active

**Symptoms**
`show_in_mcp` field is `null` in the REST response for abilities registered with the mcp-adapter nested convention (`meta['mcp']['public'] = true`), even though the plugin declares `public: true`.

**Root Cause**
`normalize_registry()` mapped `meta.mcp.public` to a stray `mcp_public` key. `AcrossAI_Ability_Merger::merge()` reads `show_in_mcp` only — it never reads `mcp_public`. The stray key was silently ignored, leaving `show_in_mcp: null`.

**Future mistake prevented**
The canonical bidirectional contract is `meta.mcp.public` ↔ `show_in_mcp`. Always verify new MCP field mappings in `normalize_registry()` against `AcrossAI_Ability_Override_Processor`, which writes `show_in_mcp` → `meta.mcp.public` as the authoritative reference direction. Never introduce a `mcp_public` key.

**Evidence**
`AcrossAI_Ability_Merger.php` — Bug 1 hotfix (Feature 015): `'show_in_mcp' => (null !== $mcp_meta && array_key_exists('public', $mcp_meta)) ? $mcp_meta['public'] : $ann_or_meta('show_in_mcp')`. Resolves real-world `ai/get-post-terms` ability returning `show_in_mcp: null`.

**Where to look next**
`includes/Utilities/AcrossAI_Ability_Merger.php` (`normalize_registry` — mcp field mapping),
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (canonical write direction: `show_in_mcp` → `meta.mcp.public`).

---

### 2026-05-27 — Redux `SET_SAVED` must seed `draftAbility` from `_override`, not merged top-level

**Status**: Active

**Symptoms**
TriChip controls for non-db abilities show the effective ("yes"/"no") value instead of "default/inherit" when the user has not explicitly set an override. After first save, TriChips revert to merged values rather than reflecting the saved override state.

**Root Cause**
The `SET_SAVED` reducer seeded `draftAbility = { ...saved }`, spreading all merged top-level fields. For non-db abilities, merged values inherit from the registry (e.g., `readonly: true` from the plugin declaration), which pre-populated TriChips incorrectly. `_override` values (`null` = inherit/not set) were never used.

**Future mistake prevented**
Always seed overridable fields in `draftAbility` from `saved._override[field]` (null = "inherit/default"). Use the `OVERRIDABLE_FIELDS` constant (13 fields, mirrors PHP `$overridable_fields`) to enumerate them. If `saved._override` is absent, default to `null` for all overridable fields. Never spread merged top-level values for these fields.

**Evidence**
`src/js/abilities/store/index.js` — Bug 3 fix (Feature 015): `OVERRIDABLE_FIELDS.forEach((f) => { draft[f] = saved._override[f] !== undefined ? saved._override[f] : null; })` inside `SET_SAVED`.

**Where to look next**
`src/js/abilities/store/index.js` (`SET_SAVED` case, `OVERRIDABLE_FIELDS` constant),
`includes/Utilities/AcrossAI_Ability_Merger.php` (`$overridable_fields` — PHP mirror).
