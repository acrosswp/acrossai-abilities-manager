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


---

### 2026-05-28 — public static method on singleton class bypasses instance() contract (BUG-STATIC-METHOD-SINGLETON-BYPASS)

**Status**: Active

**Symptoms**
A method declared `public static` on a class that also implements the singleton pattern allows callers to invoke it without going through `::instance()`. Instance state is inaccessible from a static context, so any properties stored on `$this` are silently bypassed.

**Root Cause**
`AcrossAI_Logger_Query::get_logs()` was declared `public static function get_logs()`. The method body did not use `$this`, making the `static` declaration appear harmless, but it violated the Module Contract (singleton = all public interface via `::instance()`) and would prevent future refactors that rely on instance state.

**Future mistake prevented**
Any class that declares `protected static $_instance` and `public static function instance()` MUST NOT have any other `public static function` methods. All other methods must be instance methods (no `static` keyword). Architecture review checklist: `grep 'public static function' <file>` on singleton classes; any match other than `instance()` is a violation.

**Evidence**
Feature 017 FIX-3 (commit `29894b6`): removed `static` keyword from `get_logs()` in `includes/Modules/Logger/AcrossAI_Logger_Query.php`. Call site in `AcrossAI_Logger_Logs_Controller` updated to `AcrossAI_Logger_Query::instance()->get_logs( $args )`.

**Prevention / Detection**
Architecture review: `grep -n 'public static function' includes/Modules/Logger/AcrossAI_Logger_Query.php` — should return only `instance()`.

**Where to look next**
`includes/Modules/Logger/AcrossAI_Logger_Query.php` (instance() only; all other methods non-static),
`includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` (call site: ::instance()->get_logs()),
`specs/017-logger-constitution-fix/spec.md` (FIX-3).

---

### 2026-05-28 — Stale @static PHPDoc annotation not removed when static keyword removed (BUG-PHPDOC-STATIC-STALE)

**Status**: Active

**Symptoms**
After removing `static` from a method declaration, the `@static` annotation in the PHPDoc block above the method remains. PHPStan does not flag stale `@static` annotations, so it silently persists until a manual code or architecture review catches it.

**Root Cause**
FIX-3 correctly removed the `static` keyword from `AcrossAI_Logger_Query::get_logs()` (commit `29894b6`) but left the `@static` PHPDoc annotation. The stale annotation was caught as architecture violation V1 during the post-FIX-3 architecture review and fixed in a separate commit (`cc6b6b5`).

**Future mistake prevented**
Removing `static` from a method is a two-step operation: (1) remove the `static` keyword from the method signature, (2) immediately grep the same file for `@static` in PHPDoc blocks and remove the matching annotation. Do not treat the method signature change as complete until the PHPDoc is also clean.

**Evidence**
Feature 017 V1 (commit `cc6b6b5`): removed stale `@static` annotation from `get_logs()` docblock in `includes/Modules/Logger/AcrossAI_Logger_Query.php`. Found during architecture review, not during FIX-3 development.

**Prevention / Detection**
After de-staticifying any method: `grep -n '@static' <file>` — confirm no stale annotations remain.

**Where to look next**
`includes/Modules/Logger/AcrossAI_Logger_Query.php` (get_logs — PHPDoc clean after V1 fix),
`specs/017-logger-constitution-fix/spec.md` (V1 architecture violation).


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
### 2026-05-27 — PHPUnit bootstrap must define ABSPATH before Composer autoloader (BUG-PHPUNIT-ABSPATH-SILENT-EXIT)

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
PHPUnit runs with 0 tests executed, no errors, no failures — as if there are no test files. Plugin PHP files are silently exited before the class is loaded.

**Root Cause**
Every plugin PHP file starts with `defined('ABSPATH') || exit`. If `tests/bootstrap.php` loads the Composer autoloader before calling `define('ABSPATH', ...)`, the first autoloaded class hits the guard and exits. PHPUnit sees 0 classes loaded, runs 0 tests, and reports success. There is no error message.

**Future mistake prevented**
In `tests/bootstrap.php`, `define('ABSPATH', dirname(__DIR__) . '/')` MUST appear before `require_once __DIR__ . '/../vendor/autoload.php'`. Swapping the order silently zeroes the test run.

**Evidence**
Feature 016 (2026-05-27): Bootstrap produced 0 tests until ABSPATH define was moved above the autoloader. Fixed in `tests/bootstrap.php`.

**Prevention / Detection**
After adding PHPUnit test files for any plugin class: run `./vendor/bin/phpunit --list-tests`. If the list is empty but files exist, check ABSPATH define order in bootstrap.php.

**Where to look next**
`tests/bootstrap.php` (ABSPATH define must precede autoloader require).

---

### 2026-05-27 — phpunit.xml.dist must be scoped to avoid BerlinDB fatal errors (BUG-PHPUNIT-BERLINDDB-SCOPE)

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
PHPUnit aborts with a fatal error such as `Call to undefined function add_action()` or `Call to undefined function get_option()` when loading certain plugin classes, even though those classes are not directly under test.

**Root Cause**
BerlinDB Table subclasses call `add_action()` and `get_option()` in their constructors. If `phpunit.xml.dist` includes a suite directory that triggers autoloading of these classes (e.g., via a controller or query test that imports a BerlinDB Table), and the bootstrap provides only minimal WP stubs without these functions, PHP fatals immediately.

**Future mistake prevented**
Scope `phpunit.xml.dist` to only the test files/directories that do not transitively load BerlinDB Table classes. Tests for `AcrossAI_Sanitizer`, pure utilities, and standalone logic are safe. Tests for BerlinDB Query, Row, Schema, or any REST controller that calls `AcrossAI_Abilities_Query` require a full WP environment.

**Evidence**
Feature 016 (2026-05-27): `phpunit.xml.dist` scoped to `tests/phpunit/abilities/AbilitiesValidationTest.php` only. Any broader glob caused BerlinDB fatal errors under stub bootstrap.

**Prevention / Detection**
When adding a new PHPUnit suite directory: check whether any class in that directory's dependency chain instantiates a BerlinDB Table subclass. If yes, it cannot run under stub bootstrap.

**Where to look next**
`phpunit.xml.dist` (narrow file-level scope, not directory glob),
`tests/phpunit/abilities/` (files requiring full WP: AbilitiesQueryTest, AbilitiesWriteControllerTest, AbilitiesReadControllerTest).

---

### 2026-05-27 — Pre-existing test/code mismatch: strip_protected_fields test expects fields not stripped by implementation (BUG-ABILITIES-STRIP-PROTECTED-PREEXISTING)

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
`AbilitiesValidationTest::test_strip_protected_fields_removes_identity_fields` fails. The test asserts that `label`, `description`, `category`, and other fields are removed by `strip_protected_fields_for_non_db()`, but the implementation only strips a narrower set.

**Root Cause**
The test was written with a broader expectation than the current implementation supports. This is a pre-existing mismatch unrelated to Feature 016. The test was present before Feature 016 and the implementation has never matched this assertion.

**Future mistake prevented**
This failure is not caused by Feature 016 sanitizer changes. Do not attempt to "fix" this by expanding `strip_protected_fields_for_non_db()` scope unless a spec explicitly requires it. The failing test line is `AbilitiesValidationTest.php:470`.

**Evidence**
Feature 016 security-hardening tasks (T019, T020) confirmed this failure was pre-existing. PHPUnit run of Feature 016's own 6 tests (`sanitize_mcp_servers_array` tests) pass 6/6 clean.

**Prevention / Detection**
When running the full `AbilitiesValidationTest.php` suite, expect this one pre-existing failure. Track it separately from Feature 016 quality gates.

**Where to look next**
`tests/phpunit/abilities/AbilitiesValidationTest.php` (line 470 — strip_protected_fields test),
`includes/Utilities/AcrossAI_Abilities_Validator.php` (strip_protected_fields_for_non_db — current scope).

---

### 2026-05-27 — Script-based JSX line edits can misplace .panel closing tag (BUG-ABILITYFORM-PANEL-PREMATURE-CLOSE)
After any Python/script-based line deletion or insertion in `AbilityForm.jsx`, a `</div>` at 5-tab indent can be silently left in the wrong position, prematurely closing `.panel` before Callback and Schema sections. The bug produces no JSX syntax error and is only discoverable via browser HTML inspection.

**Root Cause**
When a Python script deletes or moves line ranges in `AbilityForm.jsx`, the closing `</div>` that terminates the `.panel` (5-tab indent) can end up after the last `.sect` that was moved, instead of after all sections. The remaining sections become siblings of `.panel` in the rendered HTML.

**Future mistake prevented**
After any script-based section reorder in `AbilityForm.jsx`, verify: run `grep -n "panel\|end .panel"` and confirm the 5-tab `</div>` for `.panel` appears AFTER the last `.sect` div. Use `python3 -c "tab count check"` on the lines around the panel close comment.

**Evidence**
Feature 016 (2026-05-27): `git diff 161d1d4..e341d1a` — line 1500 was `\t\t\t\t\t</div>` (5 tabs) between Section 4 close and Section 5 open. Removed the errant div, added correct panel close after Schema (commit `e341d1a`).

**Where to look next**
`src/js/abilities/components/AbilityForm.jsx` (lines around `{/* end .panel */}` comment — 5-tab `</div>` must immediately precede it).

---

### 2026-05-27 — Rebase onto main scrambles AbilityForm.jsx section DOM order (BUG-ABILITYFORM-REBASE-SECTION-SCRAMBLE)
When a feature branch that edits `AbilityForm.jsx` is rebased onto a branch containing structural section changes, the `.sect` div order can be silently scrambled. There is no merge conflict and no build error — the wrong order only becomes apparent by reading the file or inspecting the rendered form.

**Root Cause**
Git rebase applies patches line-by-line. If both the base branch and the feature branch moved sections in `AbilityForm.jsx`, the result can place sections in an inconsistent order that satisfies neither branch's intent.

**Future mistake prevented**
After every rebase that touches `AbilityForm.jsx`, immediately verify: `grep -n "VARIANT\|Section [0-9]"` shows the canonical order **Identity(1) → Site Permission(2) → MCP Exposure(3) → Annotation Overrides(4) → Callback(5) → Schema(6)**. If the order is wrong, correct it before any further commits.

**Evidence**
Feature 016 (2026-05-27): Rebase onto `origin/main` (commit `2cfb80a`) moved Callback and Schema before MCP Exposure. Fixed in commit `5de0307`.

**Where to look next**
`src/js/abilities/components/AbilityForm.jsx` — section comment markers (`{/* ── VARIANT A: Section N ── */}`) show order at a glance.

---

### BUG-WP-ELEMENT-ACT-MISSING — `@wordpress/element` v6+ does not export `act`

`@wordpress/element` v6.46.0+ does not re-export `act` from React.
Attempting `import { act } from '@wordpress/element'` yields `undefined`, causing
all `act()` calls to throw `TypeError: act is not a function`.

**Fix**: Inside the `@wordpress/element` Jest manual mock, inject:
```js
act: jest.requireActual('react').act
```

**Future mistake prevented**: Never assume `act` is exported from `@wordpress/element`.
Always inject it via the mock factory.

**Evidence**: Feature 018 T022 (2026-05-29).

---

### BUG-MODULE-LEVEL-WINDOW-READ — Module-level `window.*` reads happen at `require()` time

When a JSX module reads `window.acrossaiAbilitiesManager` (or any global) at module
evaluation time (outside the component function), `import` order does not help —
the value is captured at `require()`. Setting `global.window.*` after the import
statement silently provides `undefined`.

**Fix**: Set `globalThis.<property>` BEFORE the `require()` call in every test
that exercises such a module.

**Future mistake prevented**: Any feature that adds a module-level `window.*` read
will have the same constraint. Document it in the test file's setup block comment.

**Evidence**: Feature 018 T022 — `const abilitiesConfig = window.acrossaiAbilitiesManager || {}`
is read at module-eval time in `AbilityForm.jsx:37`.

---

### BUG-JEST-ASYNC-USEEFFECT-FLUSH — React 18 `useEffect` with resolved promises needs `await act(async()=>{})`

Plain `act(() => root.render(...))` does not flush microtasks queued by resolved
`Promise.resolve(...)` mocks inside `useEffect`. The promise settlement triggers
state updates outside the `act` scope, causing `@wordpress/jest-console` to fail
with `"An update to X inside a test was not wrapped in act(...)"`.

**Fix**: Always use `await act(async () => { root.render(...) })` when the component
has `useEffect` hooks that call `jest.fn(() => Promise.resolve(...))`.

**Future mistake prevented**: Affects any test that renders a component with async
`useEffect` side effects. Prefer `await act(async () => {...})` by default in React 18.

**Evidence**: Feature 018 T022 (2026-05-29) — `useEffect` for MCP server fetch
in `AbilityForm.jsx:202`.

---

### BUG-WP-API-FETCH-VIRTUAL — `@wordpress/api-fetch` requires `{ virtual: true }` in Jest

`@wordpress/api-fetch` is a WP external (not in `node_modules`). Jest cannot
resolve it without `{ virtual: true }` in the mock call.

**Fix**: `jest.mock('@wordpress/api-fetch', () => jest.fn(...), { virtual: true })`

**Future mistake prevented**: All WP external packages (`@wordpress/api-fetch`,
`@wordpress/blocks`, etc.) that are not installed in `node_modules` need `{ virtual: true }`.

**Evidence**: Feature 018 T022 (2026-05-29).

---

## BUG-PHPCS-ELSE-IF (2026-05-30, Feature 020)

**Symptom**: PHPCS error: "Expected 1 space after `if` keyword" or "Inline control structures are not allowed" — actually a `Squiz.ControlStructures.ControlSignature` violation on an `else { if () }` nesting.

**Cause**: `else { if ( $condition ) { $body; } }` is flagged by PHPCS because having a single `if` as the only statement inside an `else` block is a style violation. `phpcbf` will NOT auto-fix this — it requires manual restructuring.

**Fix**: Rewrite as `elseif ( $condition ) { $body; }`. The two forms are semantically equivalent when the outer conditional is a simple `if`.

**Evidence**: Feature 020 — `admin/Main.php` at line 120-125: `else { // phpcs:ignore\n  if ( defined('WP_DEBUG_LOG') ... ) { error_log(...); } }` restructured to `elseif ( defined('WP_DEBUG_LOG') ... ) { // phpcs:ignore\n  error_log(...); }`.

**Where to look**: `admin/Main.php` lines 118-126.

---

## BUG-PLUGIN-CHECK-ACTION-NODE24 (2026-05-30, Feature 020)

**Symptom**: The `WordPress/plugin-check-action@v1` CI job completes with exit code 0 and produces no output — Plugin Check never actually runs. No error is shown.

**Cause**: The action always injects `plugin-check.zip` as a URL-based plugin entry into `wp-env.json`. On `ubuntu-latest` runners running Node 24.16 (shipped from ≥ 2026-05-25), `@wordpress/env` silently exits 0 without starting any Docker containers when it encounters any URL-based plugin entry. Tracked upstream at https://github.com/WordPress/plugin-check-action/issues/579.

**Three intermediate fixes tried before root cause found**:
1. `8f92c02` — Delete repo's `.wp-env.json` before the action runs → still fails (action creates its own URL-plugin config)
2. `9ba14d2` — Provide custom `wp-env.json` with `testsEnvironment:false` → still fails (action overwrites it)
3. `d58f487` — Bypass the action entirely; run Plugin Check via `@wordpress/env` + WP-CLI directly → **works**

**Fix**: Do not use `WordPress/plugin-check-action@v1`. Use the direct approach documented in `PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT`.

**Where to look**: `.github/workflows/plugin-check.yml`, commits `8f92c02`–`d58f487` on branch `020-plugin-check-ci`.

---

## BUG-EVAL-NOT-SUPPRESSIBLE (2026-05-31, Feature 021)

**Symptom**: Attempting to suppress `eval()` via `--ignore-codes: Generic.PHP.ForbiddenFunctions.Found` in the Plugin Check workflow removes the gate for ALL future code — not just the one call site. Any future production file using `eval()` would silently pass CI.

**Cause**: `--ignore-codes` is a workflow-level suppression. It applies to every file scanned, not just the intended line. There is no per-line `--ignore-codes` equivalent in the `wp plugin check` CLI.

**Fix**: Remove and replace `eval()` with a safe WordPress pattern. For DB-stored callable dispatch, use the registered-callback model: DB row stores a `sanitize_key()` callback key; `apply_filters('acrossai_abilities_registered_callbacks', array())` returns an allow-list of callables from version-controlled code; `isset($callbacks[$key]) && is_callable($callbacks[$key])` guard; `WP_Error` fail-closed for unknown keys.

**Future mistake prevented**: Never use workflow-level `ignore-codes` for forbidden-function findings. Remove or replace the function instead. See `PATTERN-REGISTERED-CALLBACK-TRUST` for the canonical eval() replacement.

**Where to look**: `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` (`registered_callback` case), `docs/memory/DECISIONS.md` (DEC-PLUGIN-CHECK-PRODUCTION-SURFACE), `specs/021-plugin-check-remaining-cleanup/plan.md` (CHANGE-4).

---

### 2026-05-31 — `namespace AcrossAI_Abilities_Manager\Public` is invalid PHP — `public` is a reserved keyword (BUG-PUBLIC-NAMESPACE-RESERVED)

**Status**: Active

**Symptoms**
PHPCompatibility CI flagged `public/Main.php` with a reserved-keyword error. The workaround was `--ignore=public/Main.php` in `phpcompat.yml`, silently excluding the file from compatibility scanning.

**Root Cause**
`public` is a reserved keyword in PHP. Using it as a namespace component (`namespace AcrossAI_Abilities_Manager\Public`) causes a parse error in strict PHP Compatibility tooling. The boilerplate generated this namespace by convention from the `public/` directory name without checking for reserved words.

**Future mistake prevented**
Never use a PHP reserved keyword (`public`, `private`, `protected`, `class`, `abstract`, `interface`, `static`, `final`, `new`, `return`, etc.) as a namespace component. When converting a directory path to a PSR-4 namespace, always verify each segment is a valid PHP identifier. This plugin uses `Front` as the canonical replacement for `Public` in the `public/` directory namespace.

**Evidence**
Feature 023: `public/Main.php` renamed from `namespace AcrossAI_Abilities_Manager\Public` to `namespace AcrossAI_Abilities_Manager\Front`; `includes/Main.php` and `composer.json` PSR-4 map updated to match; `--ignore=public/Main.php` removed from `phpcompat.yml`. Branch `023-fix-public-namespace-reserved-keyword`, PR #29.

**Prevention / Detection**
When adding a new directory under the plugin root, verify its namespace component is not a PHP reserved keyword before generating files. CI gate: `composer run phpcs` and `phpcompat.yml` must both pass with no `--ignore` flags.

**Where to look next**
`public/Main.php` (canonical `Front` namespace), `includes/Main.php` (`define_public_hooks` — `\AcrossAI_Abilities_Manager\Front\Main`), `composer.json` (PSR-4 autoload map — `AcrossAI_Abilities_Manager\\Front\\`).

---

### 2026-05-31 — `uninstall.php` removed options unconditionally, violating the data-preservation gate (BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE)

**Status**: Active

**Symptoms**
When a user uninstalled the plugin without enabling "delete all data", the `acrossai_abilities_log_retention_days` and `acrossai_abilities_uninstall_delete_data` options were deleted anyway. Admin settings were silently wiped on every uninstall.

**Root Cause**
The two `delete_option()` calls lived outside the `if ( $acrossai_delete_data )` block. Only the `DROP TABLE` was gated; the option deletion was unconditional.

**Future mistake prevented**
In `uninstall.php`, every `delete_option()`, `delete_post_meta_by_key()`, and destructive data removal call MUST be inside the `$acrossai_delete_data` gate. The gate is the explicit user consent boundary. Do not move option cleanup outside the gate to "always clean up config" — config options are data, and users may reinstall later expecting their settings to survive.

**Evidence**
Feature 023: `delete_option('acrossai_abilities_log_retention_days')` and `delete_option('acrossai_abilities_uninstall_delete_data')` moved inside the `if ( $acrossai_delete_data )` block in `uninstall.php`. PR #29.

**Prevention / Detection**
After editing `uninstall.php`, grep for `delete_option\|delete_post_meta\|drop table` outside the gate block. Any match outside `if ( $acrossai_delete_data )` is a violation.

**Where to look next**
`uninstall.php` (data gate — canonical correct form), `docs/memory/ARCHITECTURE.md` (`PATTERN-UNINSTALL-DATA-GATE`).

---

### 2026-06-02 — BUG-MERGER-BOOL-STRING-CAST: `(string) false === ''` silently drops boolean-false tri-state overrides

**Status**: Active

**Symptoms**
Force Block (`site_allowed = false`) set in the admin form did not persist after save. The override was written to DB correctly but was silently discarded when `AcrossAI_Ability_Merger::merge()` read the row back.

**Root Cause**
Both `merge()` and `has_any_non_null_field()` guarded with `'' !== (string) $override->{$field}` in addition to `null !== $override->{$field}`. PHP casts `false` to `''`, so `'' !== (string) false` evaluates to `false`, causing every boolean-false tri-state override to be treated as "not set" and silently dropped. Affected fields: all 6 tri-state columns (`site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`).

**Future mistake prevented**
Never use a string-cast guard (`'' !== (string) $value`) on typed tri-state boolean fields. `null !== $value` is sufficient — BerlinDB row properties are `?bool`; a non-null value is always `true` or `false`, never `''`.

**Evidence**
Fixed in `includes/Utilities/AcrossAI_Ability_Merger.php` lines ~60 and ~101 (Feature 024). Two PHPUnit regression tests added: `test_merger_site_allowed_false_override_is_applied`, `test_merger_boolean_false_overrides_survive_for_all_tri_state_fields` — 12/12 pass.

**Prevention / Detection**
Before adding any guard to a field retrieved from a BerlinDB row, check its PHP type. If it is `?bool` or `?int`, the null check alone is correct. Grep for `(string) $override` as a warning sign.

**Where to look next**
`includes/Utilities/AcrossAI_Ability_Merger.php` (`merge()`, `has_any_non_null_field()`), `tests/phpunit/abilities/AbilityOverrideInjectVariantATest.php` (regression tests).

---

### 2026-06-02 — BUG-INJECT-MISSING-TOP-LEVEL-FIELDS: `inject_override_args()` missed `label`, `description`, `category` top-level args

**Status**: Active

**Symptoms**
Saving a label, description, or category override for a non-db ability via the admin form had no effect on the live `WP_Ability` object (outside REST responses). The merger applied the values to REST output, but the WP registry object never received them.

**Root Cause**
`inject_override_args()` injected `site_allowed` and nested `meta` fields but omitted the three top-level WP Abilities API fields `label`, `description`, and `category`. These share the same arg level as `site_allowed` and must be written to `$args['label']` etc., not nested anywhere.

**Future mistake prevented**
For every new overridable field: (1) check the FR-009 field-path table in `specs/004-ability-override-processor/spec.md` to confirm the correct `$args` write path, (2) update the `inject_override_args()` docblock field map, (3) verify the live WP registry object reflects the value (not only the REST response). The merger and DB pipeline are independent of `inject_override_args()`.

**Evidence**
Added three injection blocks in `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php::inject_override_args()` after the `site_allowed` block (Feature 024 CHANGE-5).

**Prevention / Detection**
When `$overridable_fields` gains a new top-level field, immediately add a matching block in `inject_override_args()` and update the docblock field map. Grep `$overridable_fields` vs `inject_override_args()` injection blocks to detect gaps.

**Where to look next**
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (`inject_override_args()` — canonical injection list + docblock), `specs/004-ability-override-processor/spec.md` (FR-009 field-path table).

---

### 2026-06-03 — BUG-ESLINT-DISABLE-LINE-EXACT: `eslint-disable-next-line` covers exactly one line

**Status**: Active

**Symptoms**
`window.confirm()` inside a `no-alert` ESLint rule was not suppressed despite a `// eslint-disable-next-line no-alert` comment appearing a few lines above it. The lint run still reported the violation.

**Root Cause**
`// eslint-disable-next-line <rule>` suppresses the rule on the single line immediately following the comment. Placing the comment before a wrapping `if (` statement or assignment means the directive applies to that `if` line, not to the `window.confirm(` line further down.

**Future mistake prevented**
The directive must be on the line directly above the offending call — no intervening lines. If `window.confirm(` is inside an `if (window.confirm(...)) {` block, the comment goes on the line immediately before `if (window.confirm(`.

**Evidence**
Fixed in `src/js/abilities/components/AbilitiesList.jsx` — the `// eslint-disable-next-line no-alert` comment sits directly above the `if ( window.confirm(` line in the Clear All Overrides handler. Feature 025.

**Prevention / Detection**
After adding any `eslint-disable-next-line` comment, verify with `npm run lint:js` that the rule violation count decreases. A comment in the wrong position leaves the violation count unchanged.

**Where to look next**
`src/js/abilities/components/AbilitiesList.jsx` (Clear All Overrides handler in the `else` branch of `isCustom ? ... : ...`).

---

### 2026-06-03 — BUG-PHP-ABSINT-NEGATIVE-RANGE: `absint()` converts negatives to their absolute value, not zero

**Status**: Active

**Symptoms**
A PHPUnit test for `sanitize_per_page(-5)` expected `20` (the out-of-range default) but the function returned `5`, causing a test failure.

**Root Cause**
PHP's `absint()` returns the absolute integer value: `absint(-5) = 5`. The value `5` is inside the valid range [1, 200], so `sanitize_per_page(-5)` correctly returns `5`, not `20`. The test was written with the incorrect assumption that all negative inputs are out-of-range.

**Future mistake prevented**
When writing range-check tests for sanitizers that use `absint()`, test the boundary correctly: small negative inputs (whose absolute value falls within [min, max]) return their absolute value; only large negative inputs (e.g., `-300` → `absint = 300 > 200`) fall back to the default.

**Evidence**
`tests/phpunit/abilities/SettingsMenuTest.php`:
- `test_sanitize_per_page_negative_converts_via_absint()` — input `-5`, expects `5` (pass).
- `test_sanitize_per_page_large_negative_returns_default()` — input `-300`, expects `20` (pass, because `absint(-300) = 300 > 200`).
Feature 025.

**Prevention / Detection**
Whenever a sanitizer uses `absint()` before a range check, add explicit tests for both a small negative (in-range after abs) and a large negative (out-of-range after abs).

**Where to look next**
`admin/Partials/SettingsMenu.php` (`sanitize_per_page()` method), `tests/phpunit/abilities/SettingsMenuTest.php`.

---

### 2026-06-03 — BUG-PHPUNIT-TYPED-PROPERTY-SETUP: Typed class properties not initialized via `set_up()` in WP_UnitTestCase

**Status**: Active

**Symptoms**
A `WP_UnitTestCase` subclass with a typed property (`private SettingsMenu $menu`) initialized in a `set_up()` (or `setUp()`) override produced tests that never had `$this->menu` populated — calling methods on it caused fatal errors or undefined-variable notices.

**Root Cause**
The property-initialization code in `set_up()` did not run as expected under the `WP_UnitTestCase` lifecycle when using typed properties. Whether this is a lifecycle ordering issue or a PHP typed-property strict-mode interaction, the property remained uninitialized.

**Future mistake prevented**
Avoid storing singleton references in typed class properties initialized via `set_up()` in `WP_UnitTestCase`. Call the singleton directly in each test method (`SettingsMenu::instance()->sanitize_per_page($value)`) — this is simpler, always works, and eliminates the setup/teardown surface.

**Evidence**
`tests/phpunit/abilities/SettingsMenuTest.php` — final version has no `setUp()` method and no class-level typed property. All 12 tests call `SettingsMenu::instance()` inline. Feature 025.

**Prevention / Detection**
When writing new PHPUnit tests that target singleton classes, default to inline calls rather than a `setUp()`-initialized property. If shared state is needed, use `setUpBeforeClass()` with a `static` property.

**Where to look next**
`tests/phpunit/abilities/SettingsMenuTest.php` (example of inline singleton calls), `admin/Partials/SettingsMenu.php` (`instance()` method).

---

### 2026-06-02 — BUG-NORMALIZE-REGISTRY-SOURCE-DEFAULT: `normalize_registry()` string-cast prevented Source Detector from firing

**Status**: Active

**Symptoms**
All WordPress core abilities (`core/*` slug prefix) showed Source badge "Plugin" instead of "Core" in the Abilities list admin view.

**Root Cause**
`normalize_registry()` returned `source` as `(string) $ability->get_meta_item('source', 'plugin')`. The cast plus `'plugin'` default meant `source` was always non-empty (`'plugin'` for any ability without an explicit source meta item). The `empty($merged['source'])` guard in `AcrossAI_Ability_Registry_Query::get_ability()` was therefore never true, so `AcrossAI_Ability_Source_Detector::detect()` was never called for core abilities.

**Future mistake prevented**
The default for `get_meta_item('source', ...)` must be `null` so `empty()` can fire the detector for abilities that don't declare a source. Never use a non-null default for optional meta fields that are expected to be auto-detected.

**Evidence**
Fixed in `includes/Utilities/AcrossAI_Ability_Merger.php` line 183: changed `(string) $ability->get_meta_item('source', 'plugin')` to `$ability->get_meta_item('source', null)` (Feature 024 CHANGE-1).

**Prevention / Detection**
When adding auto-detection logic gated by `empty()`, trace every upstream writer of that field and confirm none set a non-null/non-empty default that would short-circuit the guard.

**Where to look next**
`includes/Utilities/AcrossAI_Ability_Merger.php` (`normalize_registry()` line ~183), `includes/Utilities/AcrossAI_Ability_Registry_Query.php` (line ~79, `empty()` guard), `includes/Utilities/AcrossAI_Ability_Source_Detector.php` (`detect()` — correct, no change needed).

---

### 2026-06-06 — BUG-ADMIN-POST-NONCE-PARAM: `check_admin_referer()` uses `_wpnonce` by default; must pass `'nonce'` explicitly when URL uses that key

**Status**: Active

**Symptoms**
WordPress "The link you followed has expired" page when clicking an admin-post handler built with `check_admin_referer('wpb_addons_connect')`. The nonce in the URL was `nonce=abc123` but WordPress was looking for `_wpnonce`.

**Root Cause**
`check_admin_referer( $action )` looks for `$_REQUEST['_wpnonce']` by default. When the URL builder uses `add_query_arg([ 'nonce' => wp_create_nonce(...) ])`, the param is `nonce`, not `_wpnonce`, and the check fails.

**Future mistake prevented**
Whenever the outgoing URL uses a custom nonce key (e.g. `nonce=`), pass that key as the second argument: `check_admin_referer('wpb_addons_connect', 'nonce')`. Never assume `_wpnonce` is the only valid key — always match the key to what `add_query_arg` / the form actually generates.

**Evidence**
`wpb-addons-page/src/AddonsPage.php` — `handle_connect_again()` now uses `check_admin_referer('wpb_addons_connect', 'nonce')`. Feature 026 UX iteration.

**Prevention / Detection**
When writing a new `admin-post` handler, grep for the nonce key used in the corresponding URL builder (`add_query_arg` or `<input type="hidden">`). Confirm the key matches the second arg of `check_admin_referer()`.

**Where to look next**
`wpb-addons-page/src/AddonsPage.php` (`handle_connect_again()`) — correct pattern.

---

### 2026-06-06 — BUG-EXTERNAL-PACKAGE-CTOR-SILENT: External package constructor wrapped in bare try/catch registers nothing and shows no error

**Status**: Active

**Symptoms**
Add-ons page submenu never appeared; AJAX actions for install/activate were unregistered. No PHP error was thrown. The plugin appeared to load cleanly.

**Root Cause**
`AddonsPage::__construct()` throws `InvalidArgumentException` when `fs_product_id` / `fs_public_key` are absent. The initial integration didn't pass credentials, so the constructor threw and the exception was swallowed by an empty `catch {}` — all `boot()` hook registrations were skipped silently.

**Future mistake prevented**
A bare `try/catch` on an external package constructor means zero hooks get registered with no visible signal. The catch block MUST always wire `admin_notices` to surface the error to `manage_options` users. Never use `catch ( \Throwable $e ) {}` with an empty body for external package constructors.

**Evidence**
`includes/Main.php` — the AddonsPage block now has a full catch that calls `add_action('admin_notices', ...)` to display the exception message to admins. Feature 026 fix.

**Prevention / Detection**
Whenever wrapping an external constructor in try/catch, add an `admin_notices` fallback in the catch block. If the package is required (not optional), rethrow instead.

**Where to look next**
`includes/Main.php` `define_admin_hooks()` — reference implementation of `class_exists` guard + `try/catch` + `admin_notices` fallback.

---

### 2026-06-06 — BUG-FREEMIUS-CONNECT-AGAIN-LOOP: Freemius `connect_again()` redirects internally; wrapping it in another admin-post redirect causes ERR_TOO_MANY_REDIRECTS

**Status**: Active

**Symptoms**
Browser showed `ERR_TOO_MANY_REDIRECTS` when clicking the "Login / Connect" button on the Add-ons page.

**Root Cause**
The handler generated a new `admin-post.php?action=wpb_addons_connect_again` URL and redirected to it. That handler then called `$fs->connect_again()`, which itself calls `fs_redirect(get_activation_url())`. The cycle repeated indefinitely.

**Future mistake prevented**
Never redirect to a handler that calls `$fs->connect_again()` — that method already handles its own redirect internally (Freemius hooks `_prepare_admin_menu` at priority 999999999 and calls `fs_redirect()`). Call `$fs->connect_again()` directly in the `admin_post` handler. Use `wp_safe_redirect()` only as a fallback when `connect_again()` is unavailable.

**Evidence**
`wpb-addons-page/src/FreemiusBridge.php` — `trigger_connect_again()` calls `$this->fs->connect_again()` directly with no further redirect.
`wpb-addons-page/src/AddonsPage.php` — `handle_connect_again()` calls `$this->fs_bridge->trigger_connect_again()` then a fallback `wp_safe_redirect()`.

**Prevention / Detection**
For any Freemius activation/connect flow: call the SDK method directly and let Freemius handle its own redirect. Never build a URL pointing back at a handler that calls another Freemius redirect method.

**Where to look next**
`wpb-addons-page/src/FreemiusBridge.php` (`trigger_connect_again()`), `wpb-addons-page/src/AddonsPage.php` (`handle_connect_again()`).

---

### 2026-06-09 — BUG-ABSPATH-STATIC-CLASS: Static utility classes must include ABSPATH guard

**Status**: Active

**Symptoms**
`AcrossAI_Ability_Library_Config.php` (100% static, never instantiated) shipped without `defined( 'ABSPATH' ) || exit;`. The file compiled fine and the plugin worked, but the guard was missing — caught only during architecture review.

**Root Cause**
The misperception that pure-static utility classes don't need the ABSPATH guard because "nothing executes them directly." All sibling files in the same module had the guard; Config was the only one missing it.

**Future mistake prevented**
The ABSPATH guard is per-file, not per-instantiation. Static utility classes are still PHP files that can be loaded directly by a URL scanner or misconfigured web server. Every PHP file in the plugin must start (after namespace) with `defined( 'ABSPATH' ) || exit;`, regardless of whether it has a constructor or instantiation pattern.

**Prevention / Detection**
- After writing a `100% static` utility class, add the ABSPATH guard immediately after the namespace declaration — same position as sibling files in the same module.
- Architecture review: verify ABSPATH guard presence in all new PHP files, not just singleton/non-static classes.

**Where to look next**
`includes/Modules/Library/AcrossAI_Ability_Library_Config.php` (fixed); compare with `AcrossAI_Ability_Library_Registry.php` and `AcrossAI_Ability_Library_Processor.php` as reference patterns.

---

### 2026-06-10 — BUG-BERLINDB-V3-DOUBLE-PRIMARY: BerlinDB v3 double primary key declaration causes silent DDL generation failure

**Status**: Active

**Symptoms**
`CREATE TABLE` silently fails — table is never created, no PHP exception is thrown. WordPress debug log shows MySQL error: `Incorrect table definition; there can be only one auto column and it must be defined as a key`.

**Root Cause**
`Column::get_create_string()` in BerlinDB v3 does not emit `PRIMARY KEY` from `'primary' => true` on the column definition; the correct mechanism is an explicit `$indexes` entry with `'type' => 'primary'`. However, if **both** `'primary' => true` on the column AND a `'type' => 'primary'` index entry are present, `Schema::is_valid()` counts 2 primary keys → `get_create_table_string()` returns `''` → `create()` silently returns false.

**Future mistake prevented**
In BerlinDB v3 Schema subclasses, declare PRIMARY KEY only via `$indexes`. Remove `'primary' => true` from `$columns` entirely. Having both causes the silent DDL failure.

**Evidence**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`, `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Schema.php` — Feature 028.

**Prevention / Detection**
When upgrading from BerlinDB v2 to v3: grep for `'primary' => true` in all `$columns` arrays. If a matching `'type' => 'primary'` index also exists in `$indexes`, remove the column-level flag.

**Where to look next**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` (`$indexes` PRIMARY KEY entry, no column-level `'primary'` flag) — reference pattern.

---

### 2026-06-10 — BUG-BERLINDB-V3-TIMESTAMP-QUOTING: BerlinDB v3 quotes all column defaults, breaking CURRENT_TIMESTAMP

**Status**: Active

**Symptoms**
`CREATE TABLE` fails with MySQL error: `Incorrect table definition` or `Invalid default value for 'created_at'`. The generated DDL contains `datetime not null default 'CURRENT_TIMESTAMP'` — the string literal, not the MySQL function.

**Root Cause**
`Column::get_default_sql()` in BerlinDB v3 wraps every `'default'` value in single quotes before any special-value check. `'default' => 'CURRENT_TIMESTAMP'` generates `default 'CURRENT_TIMESTAMP'`, which MySQL rejects for `datetime` columns.

**Future mistake prevented**
Never set `'default' => 'CURRENT_TIMESTAMP'` on a datetime/timestamp column in BerlinDB v3. Use `'created' => true` or `'modified' => true` column flags for auto-timestamping. Remove `'default'` from datetime columns entirely.

**Evidence**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` — `created_at`/`updated_at` columns fixed. Feature 028.

**Prevention / Detection**
Grep for `'default' => 'CURRENT_TIMESTAMP'` in all BerlinDB Schema subclasses. Replace with `'created' => true` or `'modified' => true` as appropriate.

**Where to look next**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` (`created_at` column with `'created' => true`, no `'default'` key) — reference pattern.

---

### 2026-06-10 — BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE: Returning WP_REST_Response from permission_callback silently grants access to all callers

**Status**: Active

**Symptoms**
REST endpoint is accessible to all authenticated (or even unauthenticated) callers. No PHP error or warning. The 403 status embedded in the response object is never evaluated — access is granted regardless.

**Root Cause**
WordPress REST dispatcher evaluates `permission_callback` via `is_wp_error($result)` then `! $result`. A `WP_REST_Response` object: (1) is not a `WP_Error`, (2) is truthy. Both checks pass → access granted regardless of the HTTP status code in the response.

**Future mistake prevented**
`permission_callback` MUST return only `true`, `false`, or `WP_Error`. Never return `WP_REST_Response` from `permission_callback`, even with a 403 status. The CONSTITUTION.md MUST rule records the canonical `check_permission()` pattern.

**Evidence**
`includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` — was returning `new WP_REST_Response(..., 403)`; fixed to return `new \WP_Error('rest_forbidden', ..., ['status' => 403])`. Feature 028.

**Prevention / Detection**
Grep for `new WP_REST_Response` and `new \WP_REST_Response` in all files containing `permission_callback`. Verify return type declarations use `true|\WP_Error`, not `\WP_REST_Response`.

**Where to look next**
`includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` (`check_permission()`) — correct pattern. `.specify/memory/CONSTITUTION.md` — MUST rule with canonical code example.

---

### 2026-06-10 — BUG-GITATTRIBUTES-EXPORT-IGNORE: Removing a directory from composer.json archive.exclude does not fix Composer VCS installs from GitHub tags

**Status**: Active

**Symptoms**
After removing a directory from `composer.json archive.exclude`, running `composer update` still installs the package without that directory. The directory is present in the local repo checkout but absent from the installed vendor copy.

**Root Cause**
GitHub builds tag ZIPs using `git archive`, which respects `.gitattributes export-ignore` directives — not `composer.json archive.exclude`. `archive.exclude` only affects `composer archive` (the CLI packaging command). When Composer resolves a VCS (`github`) source, it downloads the `git archive`-generated ZIP from GitHub, so `.gitattributes` is the effective exclusion gate.

**Future mistake prevented**
To include a directory in Composer VCS installs from GitHub tags: remove its `export-ignore` line from `.gitattributes`, not just from `composer.json archive.exclude`. Both files serve different distribution mechanisms.

**Evidence**
`wpb-access-control` repo: v1.2.2 removed `/js` from `composer.json archive.exclude` only — ineffective. v1.2.3 removed `/js export-ignore` from `.gitattributes` — fixed vendor install of `js/AccessControl.js`. Feature 028.

**Prevention / Detection**
If a vendor directory is missing after `composer update` from a GitHub tag: inspect `.gitattributes` in the upstream repo before touching `archive.exclude`. Download the GitHub release ZIP directly and verify directory presence.

**Where to look next**
`wpb-access-control` `.gitattributes` (v1.2.3) — reference of corrected state with `/js export-ignore` line removed.

---

### 2026-06-11 — BUG-BERLINDDB-QUERY-PRIVATE-CTOR: `new AcrossAI_Abilities_Query()` causes a fatal PHP error

**Status**: Active

**Pattern**
`AcrossAI_Abilities_Query` has a private constructor (line 130). Calling
`new AcrossAI_Abilities_Query()` outside the class causes a fatal PHP error.
Always access via `AcrossAI_Abilities_Query::instance()`.

**Why this is durable**
DEC-TABLE-SOFT-SINGLETON documents that Table classes use a soft-singleton (no
private constructor) because Activator calls `new` on them directly. It also
states that Query classes "are free to use private constructors" — without
confirming that this specific Query class does. That ambiguity caused a plan-level
bug in Feature 029 (plan.md CHANGE-6 originally contained
`( new AcrossAI_Abilities_Query() )->get_pass_as_tool_slugs()`), which would have
produced a fatal PHP error on every MCP server init. Caught only by the
architecture review (ARCH-REFACTOR-001).

**Evidence**
Feature 029: `AcrossAI_Mcp_Tools_Passthrough::inject_tools()` — corrected to
`AcrossAI_Abilities_Query::instance()->get_pass_as_tool_slugs()` before
implementation. Every existing caller (e.g. `AcrossAI_Abilities_Write_Controller`
constructor, L79) already uses `::instance()`.

**Prevention / Detection**
Architecture review: grep for `new AcrossAI_Abilities_Query()` in any new plan or
implementation diff before shipping. The ONLY valid access is `::instance()`.
Distinct from DEC-TABLE-SOFT-SINGLETON — Table classes permit `new` (used by
Activator); Query classes do not.

**Where to look next**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (private
constructor at L130), DEC-TABLE-SOFT-SINGLETON (Table vs Query distinction),
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` L79
(canonical `::instance()` call site).

---

### 2026-06-11 — BUG-MCP-TYPE-PASSTOOL-CONFLICT

**Pattern**
`pass_as_tool = 1` + `mcp_type = 'resource'` (or `'prompt'`) on the same ability
row causes two simultaneous failures on every `mcp_adapter_init` call:
1. `DefaultServerFactory::discover_abilities_by_type('resource')` auto-discovers the
   ability and calls `McpResource::fromAbility()` — which requires `mcp.uri` — failing
   with an ERROR log entry once per server (N servers = N errors).
2. `inject_mcp_tools()` also tries to register it as a tool, creating a type conflict.

**Prevention**
Guard `inject_mcp_tools()` before building `$pass_rows`:
```php
$non_tool_types = array( 'resource', 'prompt' );
if ( true === $row->pass_as_tool
     && ! in_array( $row->mcp_type, $non_tool_types, true ) ) { ... }
```
Resource/prompt-typed abilities are handled by DefaultServerFactory's own discovery
paths; they must not also be injected as tools.

**Root cause (Feature 029)**
`core/get-site-info` had `mcp_type = 'resource'` + `pass_as_tool = 1`
simultaneously. Fix: clear `mcp_type` override in the Abilities Manager UI (set
to Inherit/null) or provide a valid `mcp.uri`.

---

### 2026-06-11 — BUG-PHPUNIT-VOID-ACTION-INTERFACE

**Pattern**
PHPUnit tests written for a filter-based interface `($config, $server_id): array`
when the real WordPress hook callback is a void action `($adapter): void` do not
fail loudly. PHP does not enforce the return type at call sites; `$result` becomes
`null`; assertions on `$result['tools']` emit PHP notices, not PHPUnit failures.
The test suite appears to "pass" while testing nothing about the actual
implementation.

**Prevention**
Before writing tests for a static action callback, check the hook type:
- `add_action` → callback returns void; test side-effects via Reflection on
  internal state or mock objects that record calls (e.g. `register_tools()`).
- `add_filter` → callback returns a value; assert the return value directly.
Never assign `$result = SomeClass::voidCallback(...)` and then assert on `$result`.

**Evidence (Feature 029)**
`AcrossAI_Mcp_Tools_Passthrough_Test.php` originally called
`inject_mcp_tools($config, 'test-server')` and asserted `$result['tools']`.
The real signature is `inject_mcp_tools($adapter): void`. Rewrite used anonymous
class mocks with `component_registry` Reflection to verify `register_tools()` calls.
