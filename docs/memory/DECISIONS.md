# Decisions

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
Implemented in `includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php::inject_override_args()` (2026-05-16). Verified PHPCS 0 errors, PHPStan L8 exit 0.

**Where to look next**
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (inject_override_args),
`includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` (get_manager),
`vendor/wpboilerplate/wpb-access-control/src/AccessControlManager.php` (get_query, user_has_access),
`specs/004-ability-override-processor/spec.md` (FR-009).

---

### 2026-05-16 — Boot-conditional hook registration deviation (ARCH-ADV-001)

**Status**: Active

**Why this is durable**
Any processor class that needs PATH A/B conditional hook wiring will face this same Boot Flow Rule tension. Documenting why direct `add_filter`/`add_action` is acceptable here prevents false violation flags.

**Decision**
`AcrossAI_Ability_Override_Processor::boot()` registers `wp_register_ability_args`, `wp_abilities_api_init`, and `mcp_adapter_expose_ability` hooks via direct WordPress API calls (`add_filter`/`add_action`), not through the Loader. This is an accepted deviation from the Boot Flow Rule. The Loader only wires `plugins_loaded P20` (boot_hook) and `acrossai_abilities_sitewide_after_save` (bust_cache_hook). All downstream hooks are registered conditionally inside `boot()` because the Loader cannot express conditional registration (it always wires hooks).

**Tradeoffs**
Hooks in `boot()` are invisible to the Loader's hook inventory. Acceptable here because the hooks are simple, well-documented, and encapsulated within one class.

**Future mistake prevented**
Do not move `wp_register_ability_args` or `wp_abilities_api_init` registration into Main.php via the Loader — they would fire on PATH A (Manager REST) and corrupt the `_registry` layer shown in the Manager UI.

**Evidence**
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php::boot()`. Reviewed in governed-plan session 2026-05-17. `mcp_adapter_expose_ability` added in T016 (commit `2c9442e`, 2026-05-17).

**Where to look next**
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php` (boot, is_manager_rest_request),
`includes/Main.php` (define_public_hooks — Loader wires only),
`specs/004-ability-override-processor/plan.md` (ARCH-ADV-001 note).


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
