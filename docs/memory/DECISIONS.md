# Decisions

---

### 2026-06-04 — External Composer packages that self-register hooks may bypass the Loader (DEC-EXTERNAL-PACKAGE-HOOK-CTOR)

**Context**
Feature 026 integrates `wpboilerplate/addons-page` by calling `new \WPBoilerplate\AddonsPage\AddonsPage(menu_slug, plugin_file)` directly inside `define_admin_hooks()`. The AddonsPage constructor's private `boot()` method calls `add_action()` / `add_filter()` directly, bypassing `$this->loader`. This technically violates the Boot Flow Rule ("includes/Main.php is the single source of all hook registration via the Loader").

**Why the Boot Flow Rule cannot be satisfied here**
The `boot()` method is `private` — it is not accessible for Loader wiring. The package does not expose individual hookable methods (unlike `McpServersList::collect()` or `RestEndpoint::register()`). The only public API is the constructor. Creating an adapter class with `plugins_loaded P25` hook-wiring is feasible but adds fragility: it ties the adapter to the package's hook timing assumptions, and the spec explicitly calls for direct instantiation in `define_admin_hooks()`.

**Decision**
External Composer packages whose sole API is a constructor that self-registers hooks MAY be instantiated directly inside `define_admin_hooks()` without going through the Loader. The instantiation point MUST still live in `includes/Main.php` to preserve single-entry-point traceability. The call MUST be wrapped in a `class_exists()` guard per Constitution §V Integration Resilience. A code comment MUST explain the deviation.

**Scope**
`wpboilerplate/addons-page` (Feature 026). ARCH-ADV-001 is NOT extended — that deviation covers internal processor classes with conditional PATH A/B boot logic. This is a separate, narrower deviation scoped to external packages only.

**Rule**
When integrating a Composer package whose constructor self-registers hooks: (1) instantiate directly in `define_admin_hooks()`; (2) wrap in `class_exists()`; (3) add a comment citing this decision ID; (4) do not create an intermediate adapter unless the package's hook timing makes direct instantiation unsafe.

**Evidence**
`includes/Main.php` — AddonsPage block after the Settings submenu section;
`specs/026-addons-page-integration/spec.md` — FR-004, FR-005, FR-006.
Feature 030: package rebranded to `acrossai-co/addons-page` (FQCN: `\AcrossAI_Addon\AddonsPage`); pattern preserved unchanged.

---

### 2026-05-24 — Abilities module is now the single source of truth for override DB logic (DEC-012-SUPERSESSION)

**Context**
Spec 009 originally had `AcrossAI_Abilities_Query` reuse `AcrossAI_Sitewide_Schema` and `AcrossAI_Sitewide_Row` to avoid duplication (Feature 012 clarification Q3). Feature 012 supersedes that design by decommissioning the Sitewide module entirely and making `includes/Modules/Abilities/Database/` fully self-contained with its own Table, Schema, Row, and Query classes.

**Decision**
`AcrossAI_Abilities_Query` (and its companion Table/Schema/Row classes) is the authoritative and only BerlinDB entry point for the `wp_acrossai_abilities` table. The Sitewide DB classes (`AcrossAI_Sitewide_Table`, `AcrossAI_Sitewide_Schema`, `AcrossAI_Sitewide_Row`, `AcrossAI_Sitewide_Query`) were deleted in Feature 012. The Override Processor (`AcrossAI_Ability_Override_Processor`) and Access Control wrapper (`AcrossAI_Abilities_Access_Control`) now live in `includes/Modules/Abilities/`.

**Rule**
All future features that need override persistence, enforcement, or access-control injection MUST use the Abilities module classes. No new Sitewide module classes should be created.

**Where to look next**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (canonical DB layer),
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (enforcement),
`specs/012-refactor-sitewide-abilities/` (full feature plan and security constraints).

---

### 2026-05-22 — BerlinDB Table singletons must NOT have a private constructor (DEC-TABLE-SOFT-SINGLETON)

**Context**
`AcrossAI_Activator` calls `(new AcrossAI_Abilities_Table())->maybe_upgrade()` directly. Adding `private function __construct()` to `AcrossAI_Abilities_Table` — even as part of enforcing the singleton pattern — causes a fatal error because Activator is not a subclass and cannot call a private constructor. FR-015 forbids touching Activator.

**Decision**
BerlinDB `Table` subclasses in this plugin use a **soft singleton**: `$_instance` + `instance()` are present but no `private function __construct()` is added. Singleton behaviour is convention-enforced, not language-enforced. This is the required pattern for any Table class that is also instantiated via `new` elsewhere (e.g., in Activator or tests).

BerlinDB `Query`, `Schema`, and `Row` subclasses are free to use private constructors because they are never directly instantiated via `new` outside their own `instance()` method.

**Rule**
- `AcrossAI_Abilities_Table` and any future `*_Table` class: **no `private function __construct()`**
- `*_Query` classes: private constructor is fine (always accessed via `::instance()`)
- `*_Row` / `*_Schema` classes: BerlinDB instantiates these internally — do not add constructors that break parent behaviour

**Future mistake prevented**
Architecture reviews flagging "missing private constructor" on a Table class MUST check whether any other class calls `new ClassName()` before adding the private constructor. If they do, keep the soft singleton pattern.

**Evidence**
Feature 008 (2026-05-22): Adding `private function __construct()` to `AcrossAI_Abilities_Table` caused a PHP fatal error on plugin activation — `AcrossAI_Activator::activate()` calls `new AcrossAI_Abilities_Table()` directly. Fix: remove private constructor, keep `instance()` method.

**Where to look next**
`includes/AcrossAI_Activator.php` (direct instantiation),
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php` (soft singleton example)

---

### 2026-05-22 — JSON registry fields get a 64 KB size guard at the DB layer (DEC-JSON-SIZE-GUARD)

**Context**
TASK-SEC-001 (security review, Feature 008): `save_override()` now encodes four registry-driven JSON fields instead of one hardcoded `mcp_servers` field. The reviewer flagged that oversized payloads could flow through the expanded path without a DB-layer boundary.

**Decision**
Add a 64 KB `strlen()` guard inside the JSON registry loop in `save_override()`. If `wp_json_encode()` produces a string longer than 65 536 bytes, store `null` for that field and continue (same behaviour as encode failure). This mirrors the 64 KB php_code size limit stated in the spec and is belt-and-suspenders only — caller-side (REST layer) validation remains required and is the primary enforcement point (N4 advisory).

**Rule**
The constant `$max_json_bytes = 65536` is defined locally in `save_override()`. If ever changed, update both the DB layer and the REST validator (Spec 009 `AcrossAI_Abilities_Validator`).

**Why not depth-limit here?**
`json_decode()` default max depth is 512. Enforcing a lower depth limit at the DB layer requires a recursive check or try/catch that adds complexity with low marginal gain for admin-only data. Depth validation is delegated to Spec 009's `validate_schema()`.

**Where to look next**
`AcrossAI_Abilities_Query::save_override()` (JSON registry loop),
`AcrossAI_Abilities_Validator` (Spec 009 — depth validation)

---

### 2026-05-22 — by_source() is an authorization-free DB helper; all callers must gate it (DEC-BY-SOURCE-AUTHZ)

**Context**
TASK-SEC-002 (security review, Feature 008): `by_source()` is public and returns all matching records with no built-in capability check. The reviewer flagged that a future caller could accidentally expose ability metadata without a permission gate.

**Decision**
Keep `by_source()` as a raw DB-layer helper with no internal capability check. The authorization contract is documented in the docblock with an explicit `AUTHORIZATION CONTRACT` header naming the risk (OWASP A01:2025) and the canonical gate (REST `permission_callback`). This is the same pattern as all other Query methods in the plugin.

Adding a capability check inside the DB layer would couple access-control logic to the query layer, break server-side internal callers (e.g., the Processor which runs before a user context exists), and violate separation of concerns.

**Rule**
Every public method on `*_Query` classes is an authorization-free data accessor. Access control belongs in the REST controller `permission_callback` or the admin page capability check — never inside the Query class.

**Where to look next**
`AcrossAI_Abilities_Query::by_source()` (docblock with AUTHORIZATION CONTRACT note),
`AcrossAI_Abilities_Rest_Controller::check_permission()` (Spec 009 — canonical gate)

---

---

### 2026-05-25 — Description field validation: shared constant and method (DEC-DESCRIPTION-VALIDATION-PATTERN)

**Context**
Feature 013 introduced end-to-end required-field validation for the description field. The validation contract must be consistent between PHP (server) and React (client).

**Decision**
PHP: `DESCRIPTION_MAX_LENGTH = 1000` constant on `AcrossAI_Abilities_Validator` + `validate_description()` static method. Rules: `null` → return `true` (valid for PATCH; absence means no-op), empty/whitespace-only → `WP_Error`, length > 1000 → `WP_Error`. Client: `maxLength={1000}` on the description `<textarea>`. Server and client enforce the same 1000-char limit independently — if the limit changes, update both.

**Rule**
The constant `DESCRIPTION_MAX_LENGTH` is the single source of truth for the server limit. The matching `maxLength` attribute in `AbilityForm.jsx` must be kept in sync manually — there is no runtime link between them.

**Evidence**
Feature 013 T002–T005. `includes/Utilities/AcrossAI_Abilities_Validator.php` (validate_description), `src/js/abilities/components/AbilityForm.jsx` (description textarea maxLength).

**Where to look next**
`includes/Utilities/AcrossAI_Abilities_Validator.php` (DESCRIPTION_MAX_LENGTH, validate_description),
`src/js/abilities/components/AbilityForm.jsx` (description textarea, maxLength prop).

---

### 2026-05-25 — AbilityForm.jsx save button depths differ by context (DEC-HACTIONS-BUTTON-DEPTH)

**Context**
Feature 013 T015 required modifying the primary save button in `AbilityForm.jsx`. The button's indentation depth depends on which container it lives in.

**Decision**
Two distinct button depth conventions in `AbilityForm.jsx`:
- `.hactions` primary save button: 5-tab `<button` indent + 6-tab attributes
- `sbox` sidebar buttons: 9-tab `<button` indent + 10-tab attributes

These are the verified depths. Always confirm the actual depth before constructing a str_replace for any save button in this file.

**Rule**
Do not assume button depth from context name alone. Read the target element's raw indentation before str_replace.

**Evidence**
Feature 013 T015 — str_replace mismatches on the `.hactions` save button resolved by reading actual depths.

**Where to look next**
`src/js/abilities/components/AbilityForm.jsx` (.hactions div, sbox containers).

## Entry Lifecycle

Each decision follows this lifecycle:

```
Active → Needs Review → Superseded → (pruned)
```

- **Active**: The decision is current and must be honored by all features and AI agents.
- **Needs Review**: Implementation reality or new context suggests this decision may be outdated. It should still be honored until reviewed and explicitly changed.
- **Superseded**: A newer decision has replaced this one. Keep it for historical context until the next audit, then consider pruning.
- **Pruned**: During an audit, remove superseded entries that no longer provide historical value. This keeps the file focused.

### When to change status

| Current Status | Change To    | When                                                                                                       |
| -------------- | ------------ | ---------------------------------------------------------------------------------------------------------- |
| Active         | Needs Review | Verified implementation or tests contradict the decision, or recurring features follow a different pattern |
| Active         | Superseded   | A newer decision explicitly replaces this one                                                              |
| Needs Review   | Active       | Team confirms the decision still holds after review                                                        |
| Needs Review   | Superseded   | Team confirms a replacement decision                                                                       |
| Superseded     | _(remove)_   | Audit finds no remaining historical value                                                                  |

### Rules

- Never delete an Active decision without replacing or superseding it.
- Never silently ignore a decision. If it feels wrong, mark it Needs Review and resolve it.
- Keep at most 3–5 Superseded entries for context. Prune older ones during audits.

---


---

## Active Decisions

### 2026-05-16 — AC rule-gated permission_callback injection (DEC-PERM-CB)

**Status**: Active

**Why this is durable**
Any future class that wants to enforce access-control rules saved from the Manager's Access Control tab at WP ability registration time must follow this pattern.

**Decision**
`inject_override_args()` checks `AccessControlManager::get_query()->get_rule('acrossai-abilities', $slug)` (from `wpb-access-control`) at ability registration time. If `$rule['key']` is non-empty, a static closure is injected as `$args['permission_callback']`. The closure calls `user_has_access(get_current_user_id(), 'acrossai-abilities', $slug)` at ability-check time. Fails open (`return true`) when `get_manager()` returns null (library absent). The check runs independently of the override row — an ability with no DB override record can still have an AC rule.

**Tradeoffs**
Fail-open preserves access when the library is absent but means an unavailable library is invisible to site admins. Deny-by-default would require a deliberate change to this function.

**Future mistake prevented**
Do not guard the permission_callback injection inside the `isset($_overrides_cache[$slug])` block — it must run even when no override record exists for the slug.

**Evidence**
Implemented in `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php::inject_override_args()` (2026-05-16). Verified PHPCS 0 errors, PHPStan L8 exit 0.

**Where to look next**
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (inject_override_args),
`includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` (get_manager),
`vendor/wpboilerplate/wpb-access-control/src/AccessControlManager.php` (get_query, user_has_access),
`specs/004-ability-override-processor/spec.md` (FR-009).

---

### 2026-05-16 — Boot-conditional hook registration deviation (ARCH-ADV-001)

**Status**: Active

**Why this is durable**
Any processor class that needs PATH A/B conditional hook wiring will face this same Boot Flow Rule tension. Documenting why direct `add_filter`/`add_action` is acceptable here prevents false violation flags.

**Decision**
`AcrossAI_Ability_Override_Processor::boot()` registers `wp_register_ability_args`, `wp_abilities_api_init`, and `mcp_adapter_expose_ability` hooks via direct WordPress API calls (`add_filter`/`add_action`), not through the Loader. This is an accepted deviation from the Boot Flow Rule. The Loader wires `plugins_loaded P20` (boot_hook) and three cache-bust hooks:
`acrossai_abilities_after_create`, `acrossai_abilities_after_update`,
`acrossai_abilities_after_delete` (all wired to `bust_cache_hook`). All downstream
hooks are registered conditionally inside `boot()` because the Loader cannot
express conditional registration (it always wires hooks).

**Tradeoffs**
Hooks in `boot()` are invisible to the Loader's hook inventory. Acceptable here because the hooks are simple, well-documented, and encapsulated within one class.

**Future mistake prevented**
Do not move `wp_register_ability_args` or `wp_abilities_api_init` registration into Main.php via the Loader — they would fire on PATH A (Manager REST) and corrupt the `_registry` layer shown in the Manager UI.
`ARCH-ADV-001` applies **only** to `AcrossAI_Ability_Override_Processor` — it is not a recognized exemption for any other class. `AcrossAI_Ability_Logger::boot()` had an `"ARCH-ADV-001 Exception"` comment; Feature 017 FIX-1 removed `boot()` entirely as a Boot Flow Rule violation. Do not add a `boot()` method with direct `add_filter`/`add_action` calls to any other feature class and cite ARCH-ADV-001 as justification.

**Evidence**
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php::boot()`. Reviewed in governed-plan session 2026-05-17. `mcp_adapter_expose_ability` added in T016 (commit `2c9442e`, 2026-05-17). Logger `boot()` removed in Feature 017 FIX-1 (commit `cc3cd8f`, 2026-05-28).

**Where to look next**
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (boot, is_manager_rest_request),
`includes/Main.php` (define_public_hooks — Loader wires only),
`specs/004-ability-override-processor/plan.md` (ARCH-ADV-001 note),
`specs/017-logger-constitution-fix/spec.md` (FIX-1 — Boot Flow Rule violation in Logger).


### 2026-05-17 — Fail-open library absence must be paired with an admin notice (DEC-FAIL-OPEN-NOTICE)

**Status**: Active

**Why this is durable**
Any future optional-library integration that fails open (passes permission checks when the library is absent) would silently invalidate rules admins have saved in the Manager UI. The pattern of pairing fail-open with a visible admin warning prevents this from being a recurring blind spot.

**Decision**
Any behavior that fails open when an optional library is absent MUST be paired with an `admin_notices` hook wired via the Loader that shows a `wp_admin_notice()` warning. The notice MUST be gated by `current_user_can('manage_options')` and a library availability check (e.g., `$this->is_available()`). The notice MUST clearly state: which library is absent, what enforcement is currently inactive, and what the admin needs to do to restore enforcement.

**Tradeoffs**
Fail-open preserves site functionality when a library is unavailable, which is the right default. The notice ensures that unavailability is not invisible to admins who have already configured rules that depend on the library.

**Future mistake prevented**
Do not add a new optional-library integration that fails open without also adding a companion `maybe_show_*_notice()` method wired to `admin_notices`. The notice is part of the feature contract, not optional polish.

**Evidence**
`AcrossAI_Sitewide_Access_Control::maybe_show_library_notice()` added in commit `946fec1`. Wired to `admin_notices` via `$this->loader->add_action('admin_notices', $sitewide_ac, 'maybe_show_library_notice')` in `includes/Main.php::define_admin_hooks()`. Uses `wp_admin_notice()` (WP 6.4+ helper). Gated by `current_user_can('manage_options')` and `$this->is_available()`.

**Where to look next**
`includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` (maybe_show_library_notice, is_available),
`includes/Main.php` (define_admin_hooks — admin_notices wiring),
`specs/003-ability-access-control-tab/spec.md` (fail-open rationale),
`specs/004-ability-override-processor/spec.md` (DEC-PERM-CB — the fail-open decision this notice complements).

---



### 2026-05-17 — Cache-bust for write paths that skip acrossai_abilities_sitewide_after_save (W-001)

**Status**: Active

**Why this is durable**
`AcrossAI_Ability_Override_Processor` caches overrides in a 12h transient. The `bust_cache_hook()` is wired to `acrossai_abilities_sitewide_after_save` — but delete and bulk-reset paths do not fire that hook. Any future REST endpoint that deletes or resets overrides without firing `after_save` must follow this same pattern.

**Decision**
REST controllers that perform delete or reset operations without triggering `acrossai_abilities_sitewide_after_save` MUST call `AcrossAI_Ability_Override_Processor::bust_cache()` directly after the write succeeds. Current call-sites: `AcrossAI_Sitewide_Override_Controller::delete_override()` (inside `if ( $deleted )`) and `AcrossAI_Sitewide_Bulk_Controller::bulk_action()` reset branch (after `delete_override_by_slug()` returns).

**Tradeoffs**
Direct static call couples the REST controller to the processor class. Acceptable because it is a thin, well-named cross-concern with no conditional logic.

**Future mistake prevented**
Do not add a new delete/reset path and assume `bust_cache_hook` will fire — it will not unless `acrossai_abilities_sitewide_after_save` is explicitly triggered. Grep for `delete_override` and `bulk_action` reset branches when adding new write paths.

**Evidence**
T010 + T011 (commits on `004-ability-override-processor` branch). `bust_cache()` added to `AcrossAI_Sitewide_Override_Controller::delete_override()` and `AcrossAI_Sitewide_Bulk_Controller::bulk_action()` reset branch.

**Where to look next**
`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php` (delete_override),
`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php` (bulk_action reset branch),
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (bust_cache),
`specs/004-ability-override-processor/spec.md` (W-001 note).

---

### 2026-05-17 — Static-only processor with Loader-compatible instance wrappers (SEC-PLAN-002)

**Status**: Active

**Why this is durable**
The Loader requires an object instance as the callback target. Classes whose logic is entirely static (e.g., processor classes that share state across a request via static properties) must implement this wrapper pattern to remain Loader-compatible without converting all logic to instance methods.

**Decision**
Processor classes with all-static logic implement the singleton pattern and expose thin public instance wrapper methods (`boot_hook()` → `static::boot()`, `bust_cache_hook()` → `static::bust_cache()`). `Main.php` wires these instance wrappers via the Loader using a named variable (never inline `::instance()`). Direct static calls (`::bust_cache()`, `::boot()`) remain valid for cross-controller use. The singleton instance exists solely as a Loader-compatible hook target.

**Tradeoffs**
Adds wrapper boilerplate. Acceptable because it keeps all logic static (no instance state mutations during request processing), preserves Loader compatibility, and allows direct static calls from REST controllers without constructing a new instance.


### 2026-05-19 — Centralized exclusion utility with filter extensibility (DEC-PROTECTED-SLUGS-PATTERN)

**Status**: Active

**Why this is durable**
Any REST list endpoint that should exclude internal/protected resources will benefit from a centralized, filter-extensible exclusion utility. The pattern is reusable for future feature-gating (hidden ability categories, restricted MCP servers, private custom abilities).

**Decision**
Sensitive/internal resources that should be excluded from public REST list endpoints MUST be centralized in a single utility class with:
1. Static method returning protected items array
2. WordPress filter called at array-return time (allows third-party extensibility)
3. Static method for membership check (used by query layer + REST controller)
4. Defensive cast on filter result to enforce type contract

Pattern: `AcrossAI_Protected_Abilities::get_protected_slugs()` returns array, then `apply_filters('acrossai_abilities_manager_protected_slugs', $default)`. Membership check: `is_protected($slug)` uses `in_array($slug, $protected_slugs, true)` with strict comparison.

**Tradeoffs**
Adds thin utility layer (72 LOC) vs. inline checks scattered across controllers. Thin layer accepted for DRY principle (Constitution §VI) and maintainability.

**Future mistake prevented**
Do not duplicate exclusion logic in multiple controllers. Do not inline the protected list — extract to a utility first. Do not use loose comparison in membership checks — always use `strict=true`.

**Evidence**
Implemented in `includes/Utilities/AcrossAI_Protected_Abilities.php` (feature 005, commit `62d25ad`). Called from 2 locations: query layer (`AcrossAI_Ability_Registry_Query::query()`) and REST controller (`AcrossAI_Sitewide_Abilities_Controller::get_ability()`). Security review passed (SECURITY-REVIEW.md). PHPCS 0 errors, PHPStan L8 exit 0.

**Where to look next**
`includes/Utilities/AcrossAI_Protected_Abilities.php` (get_protected_slugs, is_protected),
`includes/Utilities/AcrossAI_Ability_Registry_Query.php` (query loop filtering),
`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php` (get_ability 404 check),
`specs/005-hide-mcp-system-abilities/spec.md` (FR-004, FR-005).

---

### 2026-05-19 — Early 404 checks before database lookups in REST controllers (DEC-EARLY-404-REST-CHECK)

**Status**: Active

**Why this is durable**
Any REST controller that filters/excludes resources based on system policies will need to decide: check first (fail-fast) or check after lookup? Documenting the fail-fast pattern prevents unnecessary DB queries and information disclosure.

**Decision**
REST controllers that filter/exclude resources based on system policies MUST perform the exclusion check BEFORE any database lookup or expensive operation. Check must occur immediately after input sanitization. Pattern:

1. Sanitize input
2. Check exclusion policy immediately
3. Return 404 if excluded
4. Only then proceed with DB lookups

Example: `$slug = sanitize_ability_slug(...); if (is_protected($slug)) { return 404; } $ability = wp_get_ability(...);`

**Rationale**
Fail-fast approach provides multiple benefits:
- Prevents DB queries for internal/protected resources (performance)
- Consistent 404 response (client cannot distinguish "hidden" from "doesn't exist")
- Simpler to test and audit
- Reduces attack surface (no DB access for excluded resource)
- Prevents information disclosure (attacker cannot enumerate protected abilities)

**Tradeoffs**
Early check means information about whether slug is "hidden" vs "non-existent" is not revealed. This is intentional (prevents enumeration attacks), not a limitation.

**Future mistake prevented**
Do not check exclusion status after a successful lookup — this wastes a DB query and may leak information. Do not return different error codes for "hidden" vs "doesn't exist" — use consistent 404 for both.

**Evidence**
Implemented in `AcrossAI_Sitewide_Abilities_Controller::get_ability()` (feature 005, commit `62d25ad`, lines 183–186). Security review confirmed no information disclosure (SECURITY-REVIEW.md). All three MCP adapter abilities correctly return 404 on manual testing.

**Where to look next**
`includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php` (get_ability method),
`includes/Utilities/AcrossAI_Protected_Abilities.php` (is_protected check),
`specs/005-hide-mcp-system-abilities/spec.md` (FR-002, failure scenarios).

**Future mistake prevented**
Do not convert static processor methods to instance methods to avoid wrappers — this introduces instance state mutation risk in classes designed to be stateless across a request. Do not pass `::instance()` inline to the Loader — always assign to a named variable first.

**Evidence**
`AcrossAI_Ability_Override_Processor::boot_hook()` and `bust_cache_hook()` added in T004 (feature 004). Wired in `includes/Main.php::define_public_hooks()` with named variable pattern. PHPStan L8 exit 0 confirms Loader `$component` (`object`) accepts the singleton instance.

**Where to look next**
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (boot_hook, bust_cache_hook, instance),
`includes/Main.php` (define_public_hooks — named variable wiring),
`specs/004-ability-override-processor/plan.md` (SEC-PLAN-002 note).


## Template

### YYYY-MM-DD - Decision title

**Status**
Active | Superseded | Needs review

**Why this is durable**
What cross-feature choice is likely to matter again?

**Decision**
What was decided and what boundary does it create?

**Tradeoffs**
What was gained, what was made harder, and when should this be reconsidered?

**Future mistake prevented**
What likely incorrect approach does this rule out?

**Evidence**
Diff, tests, review, incident, or repeated implementation evidence.

**Where to look next**
Files, modules, or specs future maintainers should inspect.

### 2026-05-19 — Project namespace convention: underscore-based, not PSR-4 backslash (DEC-NAMESPACE-CONVENTION)

**Status**: Active

**Why this is durable**
Any new file added to the plugin must follow the same namespace convention. Mixing PSR-4 backslash-based namespaces (e.g., `AcrossAI\Abilities\Logger`) with underscore conventions (e.g., `AcrossAI_Abilities_Manager\Includes\Logger`) causes "Class not found" runtime errors at integration time.

**Decision**
All PHP files in the plugin use underscore-based namespace pattern: `AcrossAI_Abilities_Manager\Includes\<Category>\<Submodule>`. Never use PSR-4 backslash-only style (e.g., `AcrossAI\Abilities\*`). This applies to all files: utilities, modules, database layers, REST controllers, everything.

Pattern examples:
- Utilities: `AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Formatter`
- Module core: `AcrossAI_Abilities_Manager\Includes\Modules\Logger\AcrossAI_Ability_Logger`
- Database: `AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Query`

**Tradeoffs**
Underscore convention is more verbose than PSR-4 backslash style, but it matches WordPress plugin naming conventions and provides one single standard. Accepted because it eliminates namespace conflicts and is consistent across the entire plugin.

**Future mistake prevented**
Do not import from `AcrossAI\Abilities\*` namespaces — they do not exist in this project. Do not create new files with PSR-4 backslash namespaces. At PR review, check all namespace declarations match the pattern above.

**Evidence**
Fixed in Feature 006 (2026-05-19): `AcrossAI_Logger_Source_Detector.php`, `AcrossAI_Logger_Formatter.php`, and `AcrossAI_Ability_Logger.php` all rewritten with correct underscore namespace. Use statements in logger updated to `AcrossAI_Abilities_Manager\Includes\*` pattern. PHPCS 0 errors, PHPStan L8 exit 0.

**Where to look next**
Reference file: `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (demonstrates correct pattern),
Feature 006 files: `includes/Modules/Logger/AcrossAI_*`, `includes/Utilities/AcrossAI_Logger_Formatter.php`,
`specs/006-ability-execution-logger/plan.md` (namespace fix tasks).

---

### 2026-05-19 — Utility classes are pure static, not singletons (DEC-UTILITY-STATIC-ONLY)

**Status**: Active

**Why this is durable**
The plugin uses two patterns: stateless utilities (pure static methods) and stateful orchestrators (singletons). Distinguishing between them prevents accidental singleton bloat in the utility layer.

**Decision**
Utility classes that have no mutable state and perform functional transformations MUST be 100% static. Never add `$_instance`, `instance()`, or `private __construct()` to utility classes. Only stateful components (Logger, Query, Table, REST controllers) use the singleton pattern.

Examples:
- **Pure utilities (static only)**: `AcrossAI_Logger_Formatter` (formatting logic), `AcrossAI_Protected_Abilities` (exclusion list)
- **Stateful singletons**: `AcrossAI_Ability_Logger` (manages pending_entries stack), `AcrossAI_Ability_Logs_Query` (BerlinDB wrapper), `AcrossAI_Sitewide_Table` (database registration), `AcrossAI_Logger_Source_Detector` (mutable MCP context state — moved to singleton in Feature 017/FIX-5)

**Amendment (Feature 017 / FIX-5, 2026-05-28)**
`AcrossAI_Logger_Source_Detector` was reclassified from pure-static utility to stateful singleton because it holds mutable MCP context state (`$is_mcp_context`, `$mcp_server_id`) that must be isolated to an instance to support constitution compliance. It was moved from `includes/Modules/Logger/` to `includes/Utilities/` and the singleton pattern was adopted. The rule remains: pure, stateless utilities MUST stay static-only.

**Tradeoffs**
Utilities are slightly less flexible than singletons (cannot hold request-scoped state), but simplicity is gained. Acceptable because utilities should be deterministic and state-free.

**Future mistake prevented**
Do not convert a pure utility to a singleton just to avoid passing state around — if you need state, reconsider whether it belongs in the utility layer or a higher-level orchestrator.

**Evidence**
Feature 006 cleanup (2026-05-19): `AcrossAI_Logger_Formatter.php` removed 50+ lines of singleton boilerplate (`$_instance`, `instance()`, `__construct()`). File is now 215 lines of pure static methods. `AcrossAI_Logger_Source_Detector.php` is pure static utility (only private static properties for MCP context stashing, which is necessary for cross-hook communication).

**Where to look next**
`includes/Utilities/AcrossAI_Logger_Formatter.php` (pure utility example),
`includes/Utilities/AcrossAI_Logger_Source_Detector.php` (stateful singleton — moved Feature 017),
`includes/Modules/Logger/AcrossAI_Ability_Logger.php` (stateful singleton example),
`specs/006-ability-execution-logger/plan.md` (B-phase logger tasks).

---

### 2026-05-19 — Use statement namespace must match project underscore convention; catch at PR review (DEC-USE-STATEMENT-CONSISTENCY)

**Status**: Active

**Why this is durable**
Import path errors cause "Class not found" failures at runtime, often caught late (during integration testing). Early detection at PR review prevents this class of error.

**Decision**
All `use` statements must follow the project underscore namespace convention. Before merge, verify every `use` statement imports from `AcrossAI_Abilities_Manager\Includes\*` namespaces, never from `AcrossAI\Abilities\*` or other non-standard paths. Lint rule: `grep -n '^use AcrossAI\\\\' src_file.php` should return zero results.

Correct pattern:
```php
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Formatter;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Query;
```

Incorrect pattern (never use):
```php
use AcrossAI\Abilities\Utilities\AcrossAI_Logger_Formatter;  // ❌ Wrong namespace
use AcrossAI\Abilities\Logger\AcrossAI_Ability_Logger;        // ❌ Wrong namespace
```

**Tradeoffs**
Requires manual review discipline. Acceptable because `use` statements are static and centralized at the top of every file.

**Future mistake prevented**
Do not approve a PR with `use AcrossAI\Abilities\*` imports — request the developer rewrite them using the underscore convention before merge.

**Evidence**
Feature 006 fix (2026-05-19): `AcrossAI_Ability_Logger.php` had two incorrect `use` statements pointing to `AcrossAI\Abilities\*`. Rewritten to:
```php
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Formatter;
use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Query;
```
All three logger files verified at merge (2026-05-19). grep confirmed zero backslash-only uses.

**Where to look next**
Feature 006 files: `includes/Modules/Logger/AcrossAI_Ability_Logger.php` (lines 14–15, use statements),
`includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php` (no use statements needed — same namespace as caller),
Feature 004 reference: `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (correct use statements),
PR review checklist: grep all changed .php files before approval.

---

### 2026-05-20 — Hook object parameter extraction via method_exists check (DEC-HOOK-PARAM-EXTRACTION)

**Status**: Active

**Why this is durable**
WordPress hook signatures change between versions and integrations. When a hook passes objects instead of primitives, extraction patterns need to be defensive to prevent runtime errors if the object structure changes.

**Decision**
When extracting data from objects passed through WordPress hooks, use this defensive pattern:
1. Check `is_object( $param )`
2. Check `method_exists( $object, $method_name )`
3. Only then call the method
4. Use null coalescing if the method might return null

Pattern:
```php
$extracted_value = null;
if ( is_object( $object ) && method_exists( $object, 'get_value' ) ) {
    $extracted_value = $object->get_value();
}
```

This approach decouples from internal object structure and gracefully handles version differences or missing methods.

**Tradeoffs**
Slightly more verbose than directly accessing properties or calling methods. Acceptable because it prevents "Call to undefined method" errors when object structure changes between library versions.

**Future mistake prevented**
Do not directly call methods on hook-passed objects without checking `method_exists()` first — this fails silently when the library version changes or the object type differs. Do not assume hook parameter types are stable across versions.

**Evidence**
Feature 006 logger (2026-05-20): `mcp_adapter_pre_tool_call` hook signature changed from `($tool_name, $server_id, $args)` to `($args, $tool_name, $mcp_tool, $server)`. Logger's `capture_mcp_server_id()` method safely extracts server_id via:
```php
if ( is_object( $server ) && method_exists( $server, 'get_server_id' ) ) {
    $server_id = $server->get_server_id();
}
```
Tested with both signatures. PHPCS 0 errors, PHPStan L8 exit 0.

**Where to look next**
`includes/Modules/Logger/AcrossAI_Ability_Logger.php::capture_mcp_server_id()` (lines 117–123),
`specs/006-ability-execution-logger/tasks.md` (T013 — hook wrapping task),
MCP adapter documentation: hook parameter evolution.

---

### 2026-05-20 — Duration calculation from start_time/end_time timestamps (DEC-DURATION-CALC-TIMESTAMPS)

**Status**: Active

**Why this is durable**
WordPress hooks often don't pass execution time as a parameter. When measuring execution duration, storing start time internally and calculating on completion is more reliable than relying on hook parameters.

**Decision**
For execution timing within a hook-driven system:
1. Store `$start_time = microtime( true )` at execution start
2. At execution end, calculate duration: `$duration_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 )`
3. Never rely on hook parameters for execution time — they may be unavailable, inaccurate, or change between WordPress versions

This pattern provides precise, independent timing without hook signature dependencies.

**Tradeoffs**
Requires storing start_time in a pending entry or stack. Acceptable because accuracy is more important than simplicity, and the overhead of storing one float per execution is negligible.

**Future mistake prevented**
Do not add execution_time as a required hook parameter — future library versions may not pass it. If you need execution time, calculate it internally using this timestamp pattern.

**Evidence**
Feature 006 logger (2026-05-20): `wp_after_execute_ability` hook changed to NOT pass `$execution_time_ms` parameter. Logger's `finish_pending_entry()` now calculates duration from stored `start_time`:
```php
$duration_ms = isset( $pending['start_time'] )
    ? (int) round( ( microtime( true ) - $pending['start_time'] ) * 1000 )
    : 0;
```
Compared against manual timing: duration accurate to ±5ms. Tests pass; accuracy verified in Feature 006 test suite.

**Where to look next**
`includes/Modules/Logger/AcrossAI_Ability_Logger.php::start_pending_entry()` (stores start_time, line 154),
`includes/Modules/Logger/AcrossAI_Ability_Logger.php::finish_pending_entry()` (calculates duration, lines 202–209),
`specs/006-ability-execution-logger/tasks.md` (T007 — database entry structure task).

---

### 2026-05-20 — Variadic callback wrapping for forwards-compatible permission callback hooks (DEC-VARIADIC-CALLBACK-WRAP)

**Status**: Active

**Why this is durable**
When wrapping WordPress permission callbacks, the callback signature might change in future versions (new parameters added). Using variadic args ensures the wrapper forwards any parameters the calling code passes, maintaining forwards compatibility.

**Decision**
When wrapping a permission callback for logging or preprocessing, use variadic args with `call_user_func_array()`:

Instead of:
```php
$wrapped = function() use ( $original ) {
    return call_user_func( $original );
};
```

Use:
```php
$wrapped = function( ...$cb_args ) use ( $original ) {
    return call_user_func_array( $original, $cb_args );
};
```

This pattern forwards all parameters passed by the caller, even if new parameters are added in future WordPress versions.

**Tradeoffs**
Slightly less explicit than documenting expected parameters. Acceptable because it's more maintainable — the wrapper automatically supports new parameters without code changes.

**Future mistake prevented**
Do not hardcode fixed parameters in wrapped callbacks — future WordPress versions may pass additional parameters that your wrapper will silently drop. Do not assume permission callbacks take zero or one parameter — use variadic args to be safe.

**Evidence**
Feature 006 logger (2026-05-20): `inject_permission_callback()` wraps the original permission callback for denial logging. Changed from fixed args to variadic:
```php
$args['permission_callback'] = function( ...$cb_args ) use ( $original_callback, $ability_slug ) {
    $result = call_user_func_array( $original_callback, $cb_args );
    if ( ! $result || is_wp_error( $result ) ) {
        // log denial
    }
    return $result;
};
```
Tested with 0, 1, and 2 parameter callbacks. All pass through correctly. PHPCS 0 errors, PHPStan L8 exit 0.

**Where to look next**
`includes/Modules/Logger/AcrossAI_Ability_Logger.php::inject_permission_callback()` (lines 283–291),
PHP manual: `call_user_func_array()`,
`specs/006-ability-execution-logger/tasks.md` (T010 — permission callback wrapping task).


---

### 2026-05-20 — Prioritize first stable releases when upgrading from dev branches (DEC-STABLE-UPGRADE-WINDOW)

**Status**: Active

**Why this is durable**
When a library transitions from unstable (dev-main) to semantic versioning (^X.Y), the first 1-2 stable releases within the same week are the lowest-risk upgrade targets. Waiting for later patch versions introduces feature creep and test uncertainty.

**Decision**
When upgrading from dev-main to ^X.Y:
1. Target the first stable release (v1.0.0) released in the current or prior week
2. If v1.0.0 was released <24 hours ago and v1.0.1 exists, compare both before choosing
3. Do NOT wait for v1.1.0 or v2.0.0; these introduce new features and require new tests
4. Pre-update audit (changelog + API + security review) gates all upgrades; do NOT skip for "early" releases

**Tradeoffs**
Early releases have less production usage, but first-week releases stabilize the API surface before later releases introduce new behavior. Waiting for multiple weeks of external adoption adds delay without meaningful risk reduction for controlled upgrades.

**Evidence**
Feature 007 (2026-05-20): Upgraded wpb-access-control dev-main to v1.0.0 (released 2026-05-19) and v1.0.1 (released 2026-05-20). Pre-update audit detected zero breaking changes. All P1 tests passed (100% pass rate). No production issues post-deployment.

**Future mistake prevented**
Do NOT skip early releases looking for "more stable" versions. The first release of a stable branch is the lowest-risk because it represents API stabilization, not feature accumulation. Do NOT upgrade to dev-main again without explicit business justification — semantic versioning exists to avoid this problem.

**Where to look next**
`specs/007-upgrade-access-control/` (pre-update checklist and test results),
wpb-access-control releases: https://github.com/WPBoilerplate/wpb-access-control/releases

---

### 2026-05-24 — User design prototype overrides Constitution §III DataViews/DataForm mandate (DEC-DESIGN-OVERRIDES-DATAVIEWS)

**Status**: Active

**Why this is durable**
Constitution §III mandates `@wordpress/dataviews` (DataViews) for list tables and `@wordpress/dataforms` (DataForm) for edit forms. When the user supplies a visual design prototype (wireframe HTML, Figma export, etc.) that shows a classic WP-style table or plain HTML form sections, **the design file takes precedence over the Constitution mandate**. Documenting this prevents future developers from wasting effort retrofitting DataViews to match a design that intentionally uses classic patterns.

**Decision**
When implementing an admin UI feature: read any user-supplied design prototype FIRST. If the prototype shows a classic WP list table (`.wp-list-table`, `.wptable`) instead of DataViews, or flat HTML sections instead of DataForm — implement the design. Do NOT add DataViews/DataForm just to satisfy §III if the user's design does not use them. Record the deviation in the feature's `tasks.md` and `INDEX.md` as an accepted deviation.

**Tradeoffs**
The Constitution §III recommendation exists for accessibility and consistency. Deviating introduces a maintenance burden if DataViews API changes. Accept this tradeoff when the user's design explicitly shows a different pattern — the design is the source of truth.

**Future mistake prevented**
Do not implement DataViews for a feature and then have to rewrite it to match a wireframe the user provided. Always check the design file before choosing the list component.

**Evidence**
Feature 010 (2026-05-24): commits `b39ef5e` (DataViews replaced by `.wptable`) and `248ab5d` (DataForm replaced by `.panel`/`.sect` wireframe layout). Noted in `specs/010-abilities-react-ui/tasks.md` T014 and T016 as "design requirement overrides Constitution §III."

**Feature 013 deepening (2026-05-25)**
Feature 013 adds five new custom validation touch-points on top of the existing custom form: `formErrors` state, `validateRequiredFields` helper, four `onBlur` handlers, `hasRequiredErrors` derived value, and four inline `.field-error` divs — all implemented independently of DataForm. This deepens the deviation but does not introduce it; the deviation origin is Feature 010. Accepted as technical debt (P2) per `/speckit.architecture-guard.governed-plan` session 2026-05-25. A future migration spec should migrate `AbilityForm.jsx` to DataForm's native validation API. Track via GitHub issue.

**Where to look next**
`src/js/abilities/components/AbilitiesList.jsx` (custom `.wptable` example),
`src/js/abilities/components/AbilityForm.jsx` (plain HTML sections without DataForm),
`specs/010-abilities-react-ui/tasks.md` (T014/T016 deviation notes),
`specs/013-abilities-four-field-required-validation/plan.md` (T009–T013 custom validation, P2 tech-debt deepening).

---

### 2026-05-24 — GET /abilities dual-mode: DB-only vs WP registry merge (DEC-ABILITIES-DUAL-MODE-LIST)

**Status**: Active

**Why this is durable**
The custom abilities REST endpoint needs to serve two fundamentally different data sets: DB-stored custom abilities (including drafts) and WP-registered abilities from the registry (plugin/theme/core). Using a single query path for both would require either duplicating registry merge logic or missing draft abilities.

**Decision**
`GET /abilities` branches on `source` param:
- `source=db` → `AcrossAI_Abilities_Query::get_paginated()` — DB table only, includes drafts, uses `format_for_response()`
- All other values (including empty) → `AcrossAI_Ability_Registry_Query::query()` + `AcrossAI_Sitewide_Query` — WP registry + override merge, uses `format_merged_ability()`

When a REST endpoint must serve both DB row format and registry merge format, add a dedicated `format_merged_ability()` method to the Formatter that maps the merged shape to the same flat format the frontend already consumes. This keeps the frontend unchanged.

**Tradeoffs**
Two code paths in one controller method increases branching complexity. Accepted because the two data sources are structurally incompatible and the branching is explicit and well-commented.

**Future mistake prevented**
Do not try to serve registry abilities through `AcrossAI_Abilities_Query` — it only reads the DB table. Do not forget to include `$override->id` in `AcrossAI_Ability_Merger::merge()` — without it, inherited abilities with override rows get `id=null` and the edit/override navigation breaks.

**Evidence**
Commit `a206106` — `AcrossAI_Abilities_Read_Controller::get_abilities()` dual-branch + `AcrossAI_Abilities_Formatter::format_merged_ability()` + `id` added to `AcrossAI_Ability_Merger::merge()`.

**Where to look next**
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php` (get_abilities — dual branch),
`includes/Utilities/AcrossAI_Abilities_Formatter.php` (format_merged_ability),
`includes/Utilities/AcrossAI_Ability_Merger.php` (id in merged result).

---

### 2026-05-24 — npm run build requires Node ≥ 20 (DEC-NODE-20-BUILD-REQUIRED)

**Status**: Active

**Why this is durable**
The build toolchain has a hard Node version floor that is not enforced by `.nvmrc` or `engines` in `package.json` and will not produce a readable error on Node 16 — it throws a cryptic `TypeError` mid-bundle.

**Decision**
Always build with Node 20: `nvm use 20 && npm run build`. A `@wordpress/scripts` dependency (or transitive dep) uses `Array.prototype.toSorted` which was added in Node 20. On Node 16 the build fails with `TypeError: [...].toSorted is not a function` — no other diagnostic. Document this in every new feature's T028 (build) task.

**Tradeoffs**
Requires NVM. If the environment uses a system Node < 20, the build silently produces wrong output or errors. Acceptable because Node 20 is LTS and NVM is standard for this project.

**Future mistake prevented**
Do not attempt `npm run build` on Node 16 and assume a clean exit means success — the failure is a TypeError, not a non-zero exit on some configurations. Add a Node version check to CI if not already present.

**Evidence**
Feature 010 build (2026-05-23): `npm run build` on Node 16.20 failed with `TypeError: [...].toSorted is not a function`. Succeeded after `nvm use 20`. Documented in `specs/010-abilities-react-ui/tasks.md` T028.

**Where to look next**
`package.json` (engines field — consider adding `"node": ">=20"`),
`specs/010-abilities-react-ui/tasks.md` (T028 build note),
`.nvmrc` (add `20` if missing).

---

### 2026-05-20 — Re-validate security constraints after library upgrades (DEC-REVALIDATE-SECURITY-POST-UPGRADE)

**Status**: Active

**Why this is durable**
Security constraints (strict comparison, multisite isolation, permission patterns) are specific to library implementation. Even if pre-update audit finds no changes, post-upgrade testing MUST verify constraints still hold in the new library version.

**Decision**
After upgrading any library that affects security-critical functionality (access control, authentication, cryptography), re-run security constraint validation:
1. **SEC-04** (if access control): Verify `user_has_access()` or permission checks use strict comparison (`===`, `!==`)
2. **SEC-03** (if multisite): Verify per-site table isolation (BerlinDB `$global = false`)
3. **DEC-PERM-CB** (if permission callbacks): Verify callback injection pattern continues working end-to-end
4. **DEC-FAIL-OPEN-NOTICE** (if optional library): Verify admin notice displays when library unavailable
5. Document all constraint validations in test results; treat failures as Phase blockers

**Tradeoffs**
Re-validation adds 30–60 minutes to upgrades but prevents silent security regressions. Acceptable cost given security criticality.

**Evidence**
Feature 007 (2026-05-20): Performed full constraint re-validation post-upgrade:
- T003: SEC-04 verified (strict comparison in user_has_access)
- T023: SEC-03 verified (multisite per-site isolation tested)
- T010: DEC-PERM-CB verified (permission callback injection working)
- T016–T018: DEC-FAIL-OPEN-NOTICE verified (admin notice displays when absent)
All constraints held; deployment approved.

**Future mistake prevented**
Do NOT assume pre-update audit proves post-upgrade security. Pre-update audit reviews changelog and source; post-upgrade tests confirm behavioral contracts. Do NOT deploy security-critical library upgrades without explicit post-upgrade constraint validation.

**Where to look next**
`specs/007-upgrade-access-control/` (security review and test results),
`.specify/memory/CONSTITUTION.md` (security constraints section),
`docs/memory/ARCHITECTURE.md` (constraint reference)


---

### 2026-05-24 — Hardcode WordPress hook suffix for top-level menu pages (DEC-MENU-HOOK-SUFFIX)

**Status**: Active

**Why this is durable**
WordPress generates `toplevel_page_{menu-slug}` as the hook suffix for any page registered with `add_menu_page()`. This string is deterministic and stable. Coupling enqueue logic to a menu class instance via `get_hook_suffix()` creates a class dependency that breaks when the menu class is deleted or refactored.

**Decision**
`is_*_page()` helper methods in `Admin\Main` MUST hardcode the WordPress-generated hook suffix as a string literal. Never store or retrieve the hook suffix via `add_menu_page()` return value or `get_hook_suffix()` on a menu class instance. Pattern (Yoda form required by PHPCS):
```php
private function is_manager_page( string $hook_suffix ): bool {
    return 'toplevel_page_acrossai-abilities-manager' === $hook_suffix;
}
```
Formula: `toplevel_page_{menu-slug}` for top-level pages; `{parent-slug}_page_{submenu-slug}` for submenu pages.

**Tradeoffs**
Hardcoded string must be updated if the menu slug changes. Accepted because menu slugs are stable plugin-wide constants — they rarely change and the failure mode is obvious (assets stop loading, no fatal).

**Future mistake prevented**
Do not add `get_hook_suffix()` accessors to menu Partial classes solely to feed enqueue logic. Do not call `AcrossAI_SomeMenuClass::instance()->get_hook_suffix()` inside `Admin\Main` — this couples enqueue to a menu class and breaks when that class is deleted.

**Evidence**
Feature 011 (2026-05-24): `is_abilities_custom_page()` called `AcrossAI_Abilities_Menu::instance()->get_hook_suffix()` dynamically. After `AcrossAI_Abilities_Menu` was deleted, replacement `is_manager_page()` hardcodes `'toplevel_page_acrossai-abilities-manager' === $hook_suffix`. PHPCS Yoda correction applied. PHPCS exit 0, PHPStan L8 exit 0.

**Where to look next**
`admin/Main.php` (`is_manager_page()`, `is_logs_page()` — canonical examples),
WordPress docs: `add_menu_page()` return value (hook suffix),
`specs/011-merge-abilities-ui/tasks.md` (T011 — is_manager_page implementation).


---

### 2026-05-26 — DB write methods must enforce source-discriminant guards at the method boundary (DEC-DB-WRITE-BOUNDARY-GUARD)

**Context**
Feature 014 (SEC-002): `AcrossAI_Abilities_Query::save_override()` is exclusively for non-db (registry) abilities. Prior to this decision, the protection relied on a call-site convention: callers were expected to run `strip_protected_fields_for_non_db()` before calling `save_override()`. A new call site that skipped that step could silently write `source='db'` through the override path, corrupting the source semantics of the row.

**Decision**
Any DB write method that enforces a source-based (or other semantic discriminant-based) invariant MUST enforce that invariant unconditionally at the top of the method itself, not via a caller convention. Relying on caller ordering is invisible to future call sites and will be silently violated when a new caller is added. `save_override()` now strips `source='db'` unconditionally before `prepare_fields_for_write()`, regardless of what the caller did upstream.

**Rule**
The method-level guard is belt-and-suspenders: upstream callers may also strip protected fields, and that is good. But the method must not depend on it. If a semantic discriminant (e.g., source type) determines what writes are legal, the enforcement lives in the write method, not in the read path above it.

**Evidence**
SEC-002 guard in `AcrossAI_Abilities_Query::save_override()` — Feature 014, 2026-05-26:
```php
if ( array_key_exists( 'source', $fields ) && 'db' === $fields['source'] ) {
    unset( $fields['source'] );
}
```
Placed before `$this->prepare_fields_for_write( $fields )`.

**Where to look next**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (save_override — SEC-002 comment),
`includes/Utilities/AcrossAI_Abilities_Sanitizer.php` (strip_protected_fields_for_non_db — upstream caller).

---

### 2026-05-27 — `save_override()` returns `AcrossAI_Abilities_Row|false`, not `bool` (DEC-SAVE-OVERRIDE-RETURN-ROW)

**Context**
Bug 4 (Feature 015): The Write Controller called `save_override()` (which returned `bool`), then immediately called `get_override_by_slug()` for the fresh row. On first-ever INSERT, BerlinDB's stale slug cache returned null for the re-query, producing `has_override: false` in the REST response.

**Decision**
`save_override()` returns the freshly re-read `AcrossAI_Abilities_Row` on success, `false` on failure. The row is re-read via ID-based query (cache bypass) inside `save_override()` itself. The Write Controller uses the returned row directly — no post-save slug re-query needed.

**PHP 7.4 note**: Union return types (`AcrossAI_Abilities_Row|false`) cannot be expressed as an inline method signature in PHP 7.4. Omit the inline return type hint; express the union via `@return AcrossAI_Abilities_Row|false` in the PHPDoc block. PHPStan level 8 accepts this form.

**Rule**
Any DB helper that performs an INSERT or UPDATE and is immediately consumed by a REST controller SHOULD return the saved object (not a bool). Callers MUST use strict `false === $result` comparison to detect failure.

**Where to look next**
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` (`save_override` — return pattern),
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (strict `false ===` check).
### 2026-05-27 — MCP server array sanitization defense-in-depth pattern (DEC-MCP-SERVER-SANITIZE)

**Status**: Active

**Why this is durable**
Any future feature accepting an array of string IDs from REST input needs the same layered defense: sanitizer constants + REST args schema.

**Decision**
`sanitize_mcp_servers_array()` (base sanitizer) enforces: (a) null-passthrough, (b) non-array → null, (c) per-element `sanitize_text_field()` + `substr(…, MAX_SERVER_ID_LENGTH=255)`, (d) `array_filter` empty strings, (e) `array_slice(…, MAX_MCP_SERVERS=100)`, (f) empty-result → null. Constants `MAX_MCP_SERVERS = 100` and `MAX_SERVER_ID_LENGTH = 255` are defined on `AcrossAI_Sanitizer`. Additionally, the REST `args` schema for `mcp_servers` on both Create and Update routes uses `type: array, items: {type: string, maxLength: 255}, maxItems: 100, validate_callback: rest_validate_request_arg`. The REST schema is defense-in-depth — the sanitizer is the primary enforcement point; the schema is the WP-layer belt-and-suspenders.

**Tradeoffs**
Dual enforcement (sanitizer + REST schema) means the limit is declared in two places. If the limit changes, update both `AcrossAI_Sanitizer` constants AND the REST route `args` schema.

**Future mistake prevented**
Do not accept unbounded arrays of string IDs from any REST write endpoint. Apply the constant-driven slice+substr pattern in the sanitizer AND add matching `maxItems`/`maxLength` to the REST args schema.

**Evidence**
Feature 016 T019 (sanitizer constants) and T020 (REST args schema) — both verified via PHPUnit 6/6 pass and PHPStan --level=8 exit 0.

**Where to look next**
`includes/Utilities/AcrossAI_Sanitizer.php` (MAX_MCP_SERVERS, MAX_SERVER_ID_LENGTH, sanitize_mcp_servers_array),
`includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` (mcp_servers args schema on Create + Update routes).

---

### 2026-05-27 — wpb_mcp_servers_list_rest_capability filter requires a warning comment at wiring point (DEC-MCP-CAPABILITY-FILTER-WARN)

**Status**: Active

**Why this is durable**
Any future wiring of the `wpb_mcp_servers_list_rest_capability` vendor filter must carry a visible security warning — this protects future implementers from silently lowering capability to a non-admin level.

**Decision**
The `wpb_mcp_servers_list_rest_capability` filter (from the `wpb-mcp-servers-list` library) accepts a capability string and defaults to `manage_options`. Vendor documentation shows example usage with `edit_posts` and `read`, which would expose the MCP server list to non-admin users. The `add_action` wiring point in `includes/Main.php` for `RestEndpoint::register` on `rest_api_init` MUST include a PHP comment warning that consumers who add a filter on `wpb_mcp_servers_list_rest_capability` must not lower the capability below `manage_options` for admin-only data.

**Future mistake prevented**
Do not remove the warning comment from the `rest_api_init` wiring in `Main.php`. Do not add a project-level filter on `wpb_mcp_servers_list_rest_capability` that lowers capability below `manage_options` without a security review.

**Evidence**
Feature 016 (2026-05-27): Warning comment added to `Main.php` at the `rest_api_init` `RestEndpoint::register` wiring line.

**Where to look next**
`includes/Main.php` (rest_api_init wiring for wpb-mcp-servers-list — warning comment),
`vendor/wpboilerplate/wpb-mcp-servers-list/` (REST endpoint registration, wpb_mcp_servers_list_rest_capability filter definition).

---

### 2026-05-29 — `access_control_available` is a client-side rendering gate, not an auth gate (DEC-AC-RENDERING-GATE)

**Status**: Active

**Why this is durable**
The flag controls whether `<AccessControl>` renders in the browser. It does NOT
authorize any action. Server auth is independently enforced by the `wpb-ac/v1`
REST endpoints. Future implementers must not add any access-control logic that
trusts this flag as an authorization source.

**Decision**
`access_control_available` in `window.acrossaiAbilitiesManager` is set by
`AcrossAI_Abilities_Access_Control::instance()->is_available()` (returns `bool`).
It is a rendering gate only. The diff comment documents this: "Client rendering
gate only — server authorization enforced by wpb-ac/v1 REST endpoints (SEC-018-02)."

**Future mistake prevented**
Do not treat `access_control_available === false` as proof that access control
is disabled — it only means the PHP library class was not detected. REST endpoints
enforce access control regardless.

**Evidence**: Feature 018 CHANGE-4 (2026-05-29). `admin/Main.php` inline script.

**Where to look next**: `admin/Main.php` (inline script payload), `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` (`is_available()`).

---

### 2026-05-29 — AC save uses `acSaveOk` flag; dirty state resets only on confirmed success (DEC-AC-SAVE-FLOW-PATTERN)

**Status**: Active

**Why this is durable**
Any secondary sub-save inside `handleSave()` must follow this pattern. Resetting
dirty state before confirming success silently discards retry ability on failure.

**Decision**
```js
let acSaveOk = false;
try { await apiFetch(...); acSaveOk = true; }
catch (acErr) { console.error('...', acErr); /* do not block */ }
if (acSaveOk) { acInitialRef.current = acState; setIsAcDirty(false); }
```
The try/catch logs the failure but does not block the ability-save success path.
Dirty state is reset only when `acSaveOk === true`. (RT-AR-001)

**Future mistake prevented**
Do not reset `acInitialRef.current` or `setIsAcDirty(false)` before the try/catch
resolves. Any pattern that resets inside the try block risks silently losing dirty
state on error.

**Evidence**: Feature 018 RT-AR-001 (2026-05-29). `AbilityForm.jsx` AC save block.

**Where to look next**: `src/js/abilities/components/AbilityForm.jsx` (handleSave, AC save block).

---

### 2026-05-29 — `acInitialRef.current` baseline set on first `onChange`, not on mount (DEC-ACINITIAL-REF-BASELINE)

**Status**: Active

**Why this is durable**
The AccessControl component fires `onChange` with the loaded AC state before the
user interacts. Setting the baseline on mount (before `onChange`) leaves it `null`,
causing every subsequent render to be considered dirty.

**Decision**
```js
if (acInitialRef.current === null) {
    acInitialRef.current = { key, options: [...options] };
} else { setIsAcDirty(...); }
```
First call = initial data load → set baseline. Subsequent calls = user changes → compute dirty.

**Future mistake prevented**
Do not initialize `acInitialRef` in `useEffect` on mount. The component reports
its loaded state via `onChange`, not via a separate "loaded" event.

**Evidence**: Feature 018 T025 (2026-05-29). `AbilityForm.jsx` `handleAcChange`.

**Where to look next**: `src/js/abilities/components/AbilityForm.jsx` (`handleAcChange`, `acInitialRef`).

---

### 2026-05-29 — WP Settings API accepted deviation from DataForm mandate (DEC-SETTINGS-API-DEVIATION)

**Context**
Constitution §III (NON-NEGOTIABLE) mandates `@wordpress/dataforms` for all admin form handling. Feature 019 (Admin Settings Page) uses the WordPress Settings API (PHP-rendered form, `options.php` action) instead.

**Decision**
WP Settings API is the accepted implementation for Feature 019. Justification: the Settings API provides built-in nonce generation (`settings_fields()`), sanitize-callback dispatch (`register_setting()`), and the `options.php` redirect without requiring a custom REST write endpoint. Introducing DataForm would add a REST POST endpoint, client-side JS bundle, and token/nonce bridging solely to replicate what the native API provides for free. For simple scalar-field settings pages (≤ 5 fields), the Settings API is the correct tool.

**Rule**
This deviation applies only to scalar-field Settings pages using the native Settings API. Any settings page that has custom UI behaviour (dynamic fields, dependent selects, AJAX-powered) MUST use DataForm. This entry does not blanket-approve Settings API for all future features.

**Feature scope**: Feature 019 only (`admin/Partials/SettingsMenu.php`).

**Evidence**: `specs/019-admin-settings-page/spec.md` FR-004, Assumptions §1; security review 2026-05-29.

### 2026-05-30 — Intentional eval() in php_code ability type: risk accepted, CI suppressed (DEC-EVAL-PHP-CODE)

**Status**: Superseded by DEC-PLUGIN-CHECK-PRODUCTION-SURFACE (2026-05-31)

**Context**
Feature 020 ran WordPress Plugin Check CI against the codebase. The Plugin Check tool flagged the `eval()` call in `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` (line ~253) under OWASP A03:2021 (Injection). This call is intentional and pre-existing: the `php_code` ability type executes admin-defined PHP callbacks stored in the database.

**Decision**
The `eval()` is retained. Risk is accepted under the following conditions:
1. **Access gate**: Only users with the `manage_options` capability can create or modify `php_code` abilities (enforced in the REST `permission_callback`).
2. **CI suppression**: The GitHub Actions plugin-check workflow suppresses this specific PHPCS code via `ignore-codes: WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval`. This is the only entry in `ignore-codes`.
3. **Code-level annotation**: The line carries `// phpcs:ignore Squiz.PHP.Eval.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval`.
4. **No removal**: `eval()` must never be removed without replacing the `php_code` ability execution mechanism.

**Rule**
Any future PHPCS/Plugin Check finding that references `runtime_configuration_eval` in `AcrossAI_Abilities_Processor.php` is a known accepted risk and must NOT be escalated or "fixed" by removing the `eval()`. If the `php_code` ability type is ever removed, remove the `ignore-codes` entry from the workflow simultaneously.

**OWASP A03 context**
Input is admin-supplied and stored in the DB. Execution is gated behind `manage_options`. The `$code` template is admin-defined (requires `manage_options` on the write path — enforced in the REST `permission_callback`). The `$input` execution argument is **caller-controlled**: any logged-in user can invoke an ability and supply `$input` at execution time. The risk acceptance applies to the `$code` template; `$input` data within `eval()`'d code is NOT admin-gated at execution time. Admin authors of `php_code` abilities are responsible for treating `$input` as untrusted.

**Where to look next**
`includes/Modules/Abilities/AcrossAI_Abilities_Processor.php::execute_php_code_ability()` (the eval call),
`.github/workflows/plugin-check.yml` (ignore-codes entry),
`specs/020-plugin-check-ci/security-constraints.md` (Advisory ADV-001)

---
### 2026-05-31 — Plugin Check CI scans production surface only (DEC-PLUGIN-CHECK-PRODUCTION-SURFACE)

Plugin Check must evaluate installable plugin runtime files, not the full development repository.
CI must exclude hidden files, Spec Kit artifacts, tests, docs, source-only assets, and local tooling
with `--exclude-directories` / `--exclude-files`, or run Plugin Check against a production staging
directory. Production PHP files remain fully in scope.

SQL table identifiers in production code use `$wpdb->prepare()` with `%i`; values use `%s`, `%d`,
or other value placeholders. If a query contains internally-built SQL fragments such as allowlisted
ORDER BY or fixed WHERE clauses, any suppression must be local, exact, and documented inline.

Forbidden functions reported by Plugin Check are removed or replaced by safe WordPress/plugin
patterns. `eval()` is replaced with a registered callback model, `extract()` is replaced with
explicit variable assignment, and shell/process functions are not used in production code. This
supersedes `DEC-EVAL-PHP-CODE`; arbitrary database-stored PHP is no longer accepted.

PHPCS is installed through Composer and uses WPCS, WordPress-Docs, and PHPCompatibility. The current
repo-wide `composer run phpcs` baseline is not clean, so required PR checks should run PHPCS against
the production plugin surface or wait until the baseline is fixed.

---

---

### 2026-05-31 — Singleton property renamed from $_instance to $instance (DEC-SINGLETON-PSR2-PROPERTY)

`PSR2.Classes.PropertyDeclaration.Underscore` flags any protected/private property whose name starts with an underscore. The longstanding project convention `protected static $_instance = null` violated this rule in all 21 singleton classes.

**Decision**: Rename `$_instance` to `$instance` (and `$_overrides_cache`, `$_checked`, `$_is_manager` to their non-underscore equivalents) across all singleton and class-property sites. Update reflection strings in unit tests to match.

**Why now**: Feature 022 resolves the entire PHPCS baseline (`composer run phpcs` exits 0). Keeping the underscore property would leave PSR2 warnings in every singleton class.

**Impact on AGENTS.md**: The `$_instance` example in the Singleton pattern block is now stale. Future features must use `$instance` (no underscore). Update AGENTS.md code examples when next editing that section.

**Evidence**: `9da22d7` — 21 production classes + `AbilityOverrideProcessorTest.php` updated. `composer run phpcs`: 0 errors across 49 files.

---

### 2026-05-31 — `$wpdb->prepare()` with dynamic parameter arrays must use spread operator (DEC-WPDB-PREPARE-SPREAD)

**Status**: Active

**Why this is durable**
When building a parameter array dynamically via `array_merge()` (e.g. table name + WHERE values + pagination values), passing the array as a single second argument to `$wpdb->prepare()` triggers PHPCS/Plugin Check noise. The spread operator form is the accepted pattern and must be used consistently.

**Decision**
Always spread the parameter array into `$wpdb->prepare()`:
```php
// Correct
$wpdb->prepare( $sql, ...$params )

// Wrong — triggers linter noise
$wpdb->prepare( $sql, $params )
```
When the parameter array is built via `array_merge()` (typical for dynamic WHERE + table identifier + pagination), construct the full params array first with a descriptive name, then spread it:
```php
$params = array_merge( array( $table ), $where_values, array( $per_page, $offset ) );
$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ); // phpcs:ignore ... -- SQL fragments allowlisted; spread operator for linter clarity.
```

**Tradeoffs**
Spread form requires PHP 5.6+. This plugin requires PHP 7.4+, so spread is always safe. Inline phpcs:ignore must remain because the SQL string is built from fixed fragments and cannot be a literal string constant.

**Future mistake prevented**
Do not pass a dynamically-built array as the second positional argument to `$wpdb->prepare()`. The PHPCS/Plugin Check flag will block CI. Use the spread operator.

**Evidence**
Feature 023 T004: `AcrossAI_Logger_Query.php` — both `$count_values` → `...$count_params` and `$final_values` → `...$select_params` refactored. PR #29.

**Where to look next**
`includes/Modules/Logger/AcrossAI_Logger_Query.php` (`get_logs_paginated` — count and select queries, canonical spread example).

---

## DEC-ESLINT-BASELINE-024

**Date**: 2026-06-02
**Feature**: 024-ability-form-display-fixes
**Decision**: ESLint is scoped to new/modified files only until the pre-existing baseline is remediated.

**Context**
The project has a pre-existing ESLint baseline (~279 errors) in modified JSX files before Feature 024. Root causes:
1. `@wordpress/element`, `@wordpress/data`, `@wordpress/i18n` listed as `import/no-extraneous-dependencies` — these are WordPress-externalized packages that must NOT be in `package.json` `dependencies`; the ESLint rule is misconfigured for WordPress plugin context.
2. `jsx-a11y/label-has-associated-control` on pre-existing read-only schema `<label>` elements in Section 7 (Input Schema, Output Schema) and the Callback Type `<label>`.
3. `jsdoc/*`, `no-shadow`, `no-unused-vars` on pre-existing code.
4. `import/no-unresolved` for `@wordpress/api-fetch` and `@wpb/access-control` (path aliases not configured for ESLint resolver).

**What Feature 024 did**
- Ran `eslint --fix` on `src/js/abilities/components/AbilityForm.jsx` and `src/js/abilities/components/AbilitiesList.jsx` to fix all auto-fixable prettier formatting issues introduced by CHANGE-3/CHANGE-4 (and pre-existing ones in those files).
- Fixed the only new `jsx-a11y/label-has-associated-control` error from CHANGE-4 by changing the read-only Config `<label>` to `<span>`.
- Fixed the `zod@3.23.8` → `zod@^3.25.0` peer dep upgrade that caused `eslint-plugin-react-hooks → zod-validation-error@4.0.2` to crash with `ERR_PACKAGE_PATH_NOT_EXPORTED: Package subpath './v4/core'`. Added `zod@^3.25.0` as devDependency.
- ESLint now runs without crashing. Remaining 21 errors in those two files are pre-existing and tracked here.

**Remediation path**
- Configure ESLint `import/no-extraneous-dependencies` to ignore `@wordpress/*` packages (they are Webpack externals, not Node deps).
- Fix or suppress `jsx-a11y` on read-only display labels (change `<label>` to `<span>` for non-interactive rows).
- Fix `jsdoc/*` type annotations on component JSDoc.
- Target: zero ESLint errors in a dedicated ESLint cleanup feature (suggested: Feature 025-eslint-baseline-fix).

**Where to look next**
`src/js/abilities/components/AbilityForm.jsx` — 20 pre-existing ESLint errors; `src/js/abilities/components/AbilitiesList.jsx` — 1 pre-existing ESLint error.

---

## DEC-TYPECELL-REGISTRY-FALLBACK

**Date**: 2026-06-02
**Feature**: 024-ability-form-display-fixes
**Decision**: DataViews cell renderers for registry-declared fields must read `item.field` first and fall back to `item._registry?.field` for non-db abilities.

**Context**
Non-db abilities (core abilities, plugin-registered abilities without a DB override row) have `null` for every merged top-level field because there is no override row. Their registry-declared values live exclusively in `item._registry`. A cell renderer that reads only `item.field` renders `—` for every non-db ability.

**Rule**
```jsx
// Correct — merged value wins; registry value is the fallback:
const type = item.callback_type || item._registry?.callback_type;

// Wrong — silently renders '—' for all non-db abilities:
if (!item.callback_type) return <span>—</span>;
```

**Tradeoffs**
The merged value always takes precedence. The registry fallback is only reached when the top-level merged field is `null`/`undefined` (i.e. no override exists). This preserves override semantics while making non-db rows meaningful.

**Future mistake prevented**
Any new `DataViews` field or cell renderer that reads an ability field must follow this two-step pattern. Single-source reads will silently render blanks for non-db abilities — there is no runtime warning.

**Evidence**
`TypeCell` in `src/js/abilities/components/AbilitiesList.jsx` — CHANGE-2. Feature 024.

**Where to look next**
`src/js/abilities/components/AbilitiesList.jsx` (`TypeCell` — canonical fallback example), `src/js/abilities/components/AbilityForm.jsx` (`isNonDb` checks throughout).

---

## DEC-FORM-HINT-REGISTRY-PATH

**Date**: 2026-06-02
**Feature**: 024-ability-form-display-fixes
**Decision**: "Plugin declares" hints in the admin form MUST read `savedAbility._registry.field`, never `savedAbility.field`.

**Context**
The admin form shows hints of the form "Plugin declares: X" to tell the admin what value the plugin registered, so they can see what their override is changing. Reading `savedAbility.field` (the merged value) shows the admin their own override rather than the plugin default — the hint becomes useless and potentially misleading.

**Rule**
```jsx
// Correct — reads what the plugin declared:
hint={
    isNonDb && null !== savedAbility?._registry?.readonly
        ? `Plugin declares: ${savedAbility._registry.readonly ? 'yes' : 'no'}`
        : null
}

// Wrong — reads the admin's own override back to them:
hint={
    isNonDb && null !== savedAbility?.readonly
        ? `Plugin declares: ${savedAbility.readonly ? 'yes' : 'no'}`
        : null
}
```

**Tradeoffs**
`_registry` holds the value as the plugin registered it before any override was applied. For a non-db ability, `savedAbility.field` and `savedAbility._registry.field` may look the same when no override exists, which hides the bug. The error only surfaces when an override is saved — by which point the hint already shows wrong information.

**Future mistake prevented**
Any new form field that shows a "Plugin declares" hint must reference `savedAbility._registry.{field}`. Code review should grep `"Plugin declares"` and verify every adjacent property access uses `._registry.`.

**Evidence**
Six hint props fixed in `src/js/abilities/components/AbilityForm.jsx` (CHANGE-3: show_in_mcp, mcp_type, readonly, destructive, idempotent, show_in_rest). Feature 024.

**Where to look next**
`src/js/abilities/components/AbilityForm.jsx` (all `hint=` props that contain `'Plugin declares:'` — verify each uses `._registry.`).

---

### 2026-06-03 — AbilitiesList pagination is REST-driven; perPage injected from DB via window.acrossaiAbilitiesManager (DEC-ABILITIES-LIST-UX-025)

**Context**
Feature 025 replaced the frozen `const [page] = useState(1)` and hardcoded `PER_PAGE = 20` in AbilitiesList.jsx with stateful pagination driven by the REST endpoint's `page` / `per_page` query parameters. The per-page value is now stored in a WordPress Settings API option and injected into the admin JS context.

**Decision**
- The per-page value is stored in WP option `acrossai_abilities_per_page` (integer, default 20, range 1–200), registered via `SettingsMenu::sanitize_per_page()` with `absint()` + clamp.
- It is injected into the existing `window.acrossaiAbilitiesManager` object via `wp_add_inline_script` in `Admin\Main::enqueue_scripts()`: `'perPage' => (int) get_option('acrossai_abilities_per_page', 20)`.
- In JS, `perPage` is read with a security clamp: `Math.min(200, Math.max(1, parseInt(window.acrossaiAbilitiesManager?.perPage, 10) || 20))` (FINDING-SEC-01).
- The global object is `window.acrossaiAbilitiesManager` — the spec originally said `window.acrossaiAbilities` (corrected in Feature 025 analysis).

**Rule**
Any future JS feature that needs a server-injected configuration value MUST read it from `window.acrossaiAbilitiesManager` (not `window.acrossaiAbilities` — that object does not exist). Always apply a client-side clamp on numeric values.

**Future mistake prevented**
Using the wrong global object name (`window.acrossaiAbilities`) causes a silent `undefined` that falls through to the default — the wrong name will never throw an error, making the bug hard to detect.

**Evidence**
`admin/Main.php:218` `'perPage' => (int) get_option('acrossai_abilities_per_page', 20)`;
`src/js/abilities/components/AbilitiesList.jsx` — `const perPage = Math.min(200, Math.max(1, parseInt(window.acrossaiAbilitiesManager?.perPage, 10) || 20))`;
spec corrected at `specs/025-abilities-list-ux-improvements/spec.md` lines 113 and 191.

**Where to look next**
`admin/Main.php` (`enqueue_scripts()` — `window.acrossaiAbilitiesManager` object definition),
`admin/Partials/SettingsMenu.php` (`sanitize_per_page()`, `render_per_page_field()`),
`src/js/abilities/components/AbilitiesList.jsx` (`perPage` constant, `fetchAbilities` dispatch call).

---

### 2026-06-03 — Column visibility uses localStorage merge-with-defaults pattern (DEC-COLUMN-VISIBILITY-LOCALSTORAGE)

**Context**
Feature 025 added a Columns toggle panel in AbilitiesList.jsx that lets admins show/hide any of the 8 hideable columns. Preferences must survive page reloads and must not break when new columns are added in future features (FR-025).

**Decision**
- Preferences are stored in `localStorage` under key `acrossai_abilities_columns` as a JSON object of `{ columnKey: boolean }` pairs.
- On load, `loadColumnPrefs()` starts from `COLUMN_DEFAULTS` (all 8 columns `true`) and only overrides keys that are present in the saved object: `Object.keys(COLUMN_DEFAULTS).forEach(key => { if (key in saved) result[key] = !!saved[key] })`.
- Saved values are normalised with `!!val` (FINDING-SEC-02): truthy non-booleans become `true`, falsy non-booleans become `false`.
- Unknown saved keys are silently ignored (no prototype pollution possible because iteration is over `COLUMN_DEFAULTS` keys only).
- If `localStorage` is unavailable or contains invalid JSON, the function catches the error and returns `{ ...COLUMN_DEFAULTS }` (all visible).

**Rule**
Any future column added to `COLUMN_DEFAULTS` will automatically be visible for all users (including those with existing saved prefs) because the merge only applies saved values for keys that already exist in `COLUMN_DEFAULTS`. New columns must be added to `COLUMN_DEFAULTS` with `true` — they must never be added with `false`.

**Future mistake prevented**
If a future column were added to `COLUMN_DEFAULTS` with `false`, existing users with a saved pref object would see it hidden on first load (the saved pref would override the default only if the key exists in the saved object — but new keys are not in old saves, so the default is used). The concern is ensuring `COLUMN_DEFAULTS` defaults are all `true`.

**Evidence**
`src/js/abilities/components/AbilitiesList.jsx` — `COLUMN_DEFAULTS`, `loadColumnPrefs()`, `toggleColumn()`;
`tests/jest/abilities/column-prefs.test.js` — 8 tests covering empty prefs, partial hide, new-column default, value normalisation, corrupt JSON fallback, null fallback, unknown key ignored.

---

### 2026-06-06 — DEC-FREEMIUS-PER-PLUGIN-INIT: Freemius `fs_dynamic_init()` must be called with per-consumer product credentials keyed by product_id

**Status**: Active

**Context**
`wpboilerplate/addons-page` is a shared Composer package consumed by multiple plugins. Initially `FreemiusInitializer` held a single shared instance. Using shared/hardcoded credentials would cause analytics and opt-in data to route to the wrong Freemius product when two plugins using the package were active simultaneously.

**Decision**
`FreemiusInitializer::init()` accepts `$product_id`, `$public_key`, and `$slug` as parameters. Instances are memoized in a static array keyed by `$product_id`. Each consumer plugin must pass its own Freemius product credentials; the package never hardcodes them.

**Rule**
When calling `new AddonsPage(...)`, always pass `fs_product_id`, `fs_public_key`, and `fs_slug` in the `$args` array. Missing credentials throw `InvalidArgumentException`. Credentials live in the consuming plugin (e.g., `includes/Main.php`), never in the shared package.

**Evidence**
`wpb-addons-page/src/FreemiusInitializer.php` — `init()` signature accepts `$product_id`, `$public_key`, `$slug`; static `$instances` array keyed by `$product_id`.
`includes/Main.php` — AddonsPage instantiation with `'fs_product_id' => '31230', 'fs_public_key' => 'pk_0f116582ac1b8e608827094024b1f'`.

**Tradeoffs**
- Gained: per-plugin Freemius isolation, correct analytics per product, multi-plugin safe
- Reconsider: if the package ever needs an anonymous/no-Freemius mode, the init path would need an optional bypass

---

### 2026-06-06 — AbilityAPI module uses dedicated REST namespace acrossai-abilities-api/v1 (DEC-ABILITYAPI-NAMESPACE)

**Status**: Active

**Why this is durable**
Each plugin module must own exactly one REST namespace. Sharing namespaces across modules creates route collision risk and makes independent namespace versioning impossible. `acrossai-abilities-api/v1` is reserved for the AbilityAPI module (add-on registration management); it must not be reused by future modules or sub-features.

**Decision**
The AbilityAPI module's REST orchestrator (`AcrossAI_Ability_API_Rest_Controller`) uses `acrossai-abilities-api/v1` as its `REST_NAMESPACE` constant. This namespace is distinct from:
- `acrossai-abilities-manager/v1` — Abilities CRUD (Read, Write, Category, Exposure sub-controllers)
- `acrossai-abilities-log/v1` — Logger logs (migrated Feature 027)

No future module or sub-feature may register routes under these namespaces unless it is a sub-controller of that module's orchestrator.

**Tradeoffs**
- Gained: namespace isolation; independent versioning per module; no wildcard collision risk between modules.
- Made harder: React fetch calls must target the correct namespace per feature; copy-paste errors between modules will produce 404s.
- Reconsider: if WP REST API namespace-per-module ever becomes too many namespace registrations to manage; at that point consider a plugin-wide namespace with resource-scoped sub-paths.

**Evidence**
Feature 027 plan.md D1; revised to dedicated namespace to avoid reusing `acrossai-abilities-manager/v1` and prevent wildcard shadowing with existing `GET /abilities/(?P<slug>[^/]+)` route.

---

### 2026-06-06 — Logger REST namespace migrated from acrossai-abilities/v1 to acrossai-abilities-log/v1 (DEC-LOGGER-NAMESPACE-MIGRATION)

**Status**: Active

**Why this is durable**
The old `acrossai-abilities/v1` namespace was chosen early and without module-ownership intent. Feature 027 introduces `acrossai-abilities-api/v1`; to ensure each module owns exactly one namespace, the Logger was migrated. The migration is a breaking change for any REST client calling the old URL.

**Decision**
Logger logs endpoint moved from `GET /acrossai-abilities/v1/logger/logs` to `GET /acrossai-abilities-log/v1/logger/logs`. The migration requires updating **all 5 touch-points atomically**; updating only the PHP controllers while leaving the JS files pointing at the old namespace produces a 404 on every page load:

| File | Line | Change |
|---|---|---|
| `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php` | 35 | `protected $namespace = 'acrossai-abilities-log/v1'` |
| `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php` | 36 | `protected $namespace = 'acrossai-abilities-log/v1'` |
| `src/js/index.js` | 38 | `/wp-json/acrossai-abilities-log/v1/logger/logs` |
| `src/js/components/LogsTable.js` | 93 | `/wp-json/acrossai-abilities-log/v1/logger/logs` |
| `admin/Main.php` | 191 | `rest_url('acrossai-abilities-log/v1/logger/logs')` |

**Tradeoffs**
- Gained: clear module-owned namespaces; no future collision with AbilityAPI or other modules.
- Made harder: any third-party consumer of the old `acrossai-abilities/v1/logger/logs` endpoint breaks without notice; no deprecation redirect.
- Reconsider: if external consumers exist — add a redirect shim at the old namespace for one release before removing it.

**Evidence**
Feature 027 plan.md D2; memory synthesis hard conflict — plan initially listed only 1 of 5 files, full scope discovered via codebase grep.

---

### 2026-06-06 — Feature 027: no unit tests, accepted deviation from Constitution §VII (DEC-FEATURE-027-NO-TESTS)

**Status**: Active

**Why this is durable**
Constitution §VII Definition of Done requires "Unit tests written and passing for all new logic." Feature 027 ships without tests. Documenting this prevents future architecture reviews from treating the missing tests as an oversight and prevents the precedent from silently spreading to other features.

**Decision**
Feature 027 (Abilities Keys Admin Submenu) ships without PHPUnit or Jest unit tests. Justification: (1) The spec does not request TDD; (2) The feature's primary validation is manual end-to-end testing — install an add-on that hooks `acrossai_abilities_api_init`, confirm its card appears in the grid; (3) `AcrossAI_Ability_API_Config` (all static methods, highest unit-test value) is the primary candidate for future tech-debt test coverage.

**Rule**
Any future feature that touches `AcrossAI_Ability_API_Config`, `AcrossAI_Ability_API_Registry`, or `AcrossAI_Ability_API_Processor` SHOULD add PHPUnit tests for those classes at that time. This entry does not blanket-authorize skipping tests for other features.

**Tradeoffs**
- Gained: faster delivery of the admin UI and registration model.
- Made harder: regression detection for Processor gating logic (`is_permitted`) and Config sanitization limits.
- Reconsider: when the AbilityAPI module gains a second consumer; add tests before extending the module.

**Evidence**
Feature 027 governed-tasks session 2026-06-06 — ARCH-003 advisory raised; user confirmed to proceed without tests and document the deviation.

**Where to look next**
`includes/Modules/AbilityAPI/AcrossAI_Ability_API_Config.php` (sanitize_entry, size constants — highest unit-test priority),
`includes/Modules/AbilityAPI/AcrossAI_Ability_API_Processor.php` (is_permitted — gating logic with multiple branches).

---

### 2026-06-11 — DEC-MCP-TOOLS-PASSTHROUGH-COLUMN

**Status**
Active

**Why this is durable**
Establishes the contract between the plugin's abilities table and the MCP server tool
registry. Future code must keep the column and the injection callback aligned.

**Decision**
Per-ability MCP tool pass-through is a tri-state tinyint column (`pass_as_tool`)
on `acrossai_abilities`. NULL = default (server's own tools unchanged); 1 = register
the ability into every MCP server's callable tool registry via `mcp_adapter_init`
P20 (PATH B only). The injection lives in
`AcrossAI_Ability_Override_Processor::inject_mcp_tools()` (static action callback),
registered in `boot()` per ARCH-ADV-001. Uses PHP Reflection to access private
`McpServer::$component_registry` because `mcp_adapter_server_config` does not exist
in the installed mcp-adapter version. `mcp_adapter_init` P20 fires after
DefaultServerFactory (P10) and acrossai-mcp-manager database servers (P11). Value 0 (explicit deny) is stored by the sanitizer
but excluded from injection — reserved for future per-server deny semantics.
Protected slugs are rejected at the API layer by
`AcrossAI_Abilities_Write_Controller::update_ability()` L308 (slug-level,
strict comparison, fires before sanitize). `mcp_servers` allowlist is respected:
null = all servers; [] = blocked on all; [...] = inject only if current server ID
is in the list (strict comparison).

**Tradeoffs**
- Gained: single toggle, no per-server config UI; matches the shape of existing
  tri-state columns (site_allowed, show_in_mcp). Allowlist check reuses the existing
  `mcp_servers` column — no new storage needed.
- Reconsider: if finer-grained per-server control beyond `mcp_servers` is needed,
  update only `inject_mcp_tools()`. Column schema and REST layer unchanged.

**Where to look next**
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (`inject_mcp_tools()`),
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`
(`get_pass_as_tool_slugs()`), `src/js/abilities/components/AbilitiesList.jsx`
(`PassAsToolCell`), PATTERN-PROTECTED-SLUGS-JS-LOCALIZE (JS localization pattern).

---

### 2026-06-11 — DEC-MCP-INJECT-REFLECTION-PATTERN

**Status**
Active

**Why this is durable**
Any future feature that injects abilities into MCP server registries post-creation
will hit the same `mcp_adapter_server_config` absence and the same `tools/list` ≠
`tools/call` trap unless this pattern is documented.

**Decision**
`mcp_adapter_server_config` does not exist in the installed mcp-adapter version and
never fires. The canonical post-creation injection pattern is: hook `mcp_adapter_init`
at priority 20 (after DefaultServerFactory P10 and acrossai-mcp-manager P11), iterate
`$adapter->get_servers()`, use PHP Reflection to access the private
`McpServer::$component_registry`, then call `$registry->register_tools($slugs)`.
Do NOT use `mcp_adapter_tools_list` — it only affects the display list; tools added
there are NOT callable via `tools/call`.

**Tradeoffs**
- Gained: both `tools/list` and `tools/call` work; no hard dependency on a
  non-existent filter.
- Reconsider: if mcp-adapter exposes a public API for post-creation tool injection
  in a future version, replace the Reflection approach with that API and remove this
  pattern.

**Where to look**
`AcrossAI_Ability_Override_Processor::inject_mcp_tools()` — boot() registration and
full implementation.

---

### 2026-06-11 — Ability_Definition abstract contract simplified to single ability() method (DEC-LIBRARY-CATEGORY-SLUG-REBRAND)

**Context**
Feature 031 renames the Library module's grouping scheme from `main_key`/`sub_key` to
`category`/`slug`. The original design required subclasses to implement four abstract
grouping methods (`main_key()`, `main_key_label()`, `sub_key()`, `sub_key_label()`) in
addition to `ability()`. Discovery of 17 existing concrete subclasses in `acrossai-core-abilities`
revealed that all the necessary grouping information already exists in `ability()['args']['category']`
and `ability()['name']`.

**Decision**
Remove the four abstract grouping methods. `push_definition()` now derives all Library
display fields from the single `ability()` return value:
- `category` ← `args['category']`
- `category_label` ← `ucwords(str_replace('-', ' ', $category))`
- `slug` ← `name`
- `slug_label` ← `args['label']` ?? `name`

**Backwards compatibility**
External subclasses that still implement the old `main_key()`/`sub_key()` methods are
**not broken** — PHP only fatals on *missing* abstract method implementations, not on
extra non-abstract methods. The 17 existing subclasses load and function correctly.

**On-disk config shape**
`acrossai_library_config` site option retains `{enabled, mode, sub_keys: {slug→bool}}`
per category entry. The `sub_keys` inner map key is intentionally preserved for backwards
compatibility with saved configs. Only in-memory field names and JS props were renamed.

**Rule**
New `Ability_Definition` subclasses MUST implement only `ability()`. The `args` array
returned by `ability()` MUST include a `category` key — its absence causes the Registry
to silently reject the definition (logged under `WP_DEBUG_LOG`).

**Evidence**
`includes/Modules/Library/Ability_Definition.php` — `push_definition()` derivation;
`specs/031-library-category-slug-rebrand/plan.md` — Technical Context and Rename Map.
