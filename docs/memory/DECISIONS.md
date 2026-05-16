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
`AcrossAI_Ability_Override_Processor::boot()` registers `wp_register_ability_args` and `wp_abilities_api_init` hooks via direct WordPress API calls (`add_filter`/`add_action`), not through the Loader. This is an accepted deviation from the Boot Flow Rule. The Loader only wires `plugins_loaded P20` (boot_hook) and `acrossai_abilities_sitewide_after_save` (bust_cache_hook). All downstream hooks are registered conditionally inside `boot()` because the Loader cannot express conditional registration (it always wires hooks).

**Tradeoffs**
Hooks in `boot()` are invisible to the Loader's hook inventory. Acceptable here because the hooks are simple, well-documented, and encapsulated within one class.

**Future mistake prevented**
Do not move `wp_register_ability_args` or `wp_abilities_api_init` registration into Main.php via the Loader — they would fire on PATH A (Manager REST) and corrupt the `_registry` layer shown in the Manager UI.

**Evidence**
`includes/Modules/Sitewide/AcrossAI_Ability_Override_Processor.php::boot()`. Reviewed in governed-plan session 2026-05-17.

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
