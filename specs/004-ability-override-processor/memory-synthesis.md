# Memory Synthesis: Ability Override Processor (004)

**Generated**: 2026-05-16
**Scope**: Feature 004 — architecture decisions and watchpoints relevant to planning

---

## Key Architecture Constraints

1. **Singleton + Instance Wrapper Pattern** (SEC-PLAN-002 correction): `AcrossAI_Ability_Override_Processor` MUST implement `instance()` + private constructor. All logic remains static. Instance wrapper methods (`boot_hook()`, `bust_cache_hook()`) delegate to static implementations and satisfy the Loader's `object $component` type contract. `Main.php` wires via named variable: `$override_processor = AcrossAI_Ability_Override_Processor::instance(); $this->loader->add_action('plugins_loaded', $override_processor, 'boot_hook', 20)`. The earlier "static-only / string-class-name" wiring is superseded — it would fail PHPStan L8.

2. **Boot Flow Rule**: `includes/Main.php` is the ONLY file that registers hooks. `define_public_hooks()` wires the two Loader hooks for this class. The class itself does NOT call the Loader.

3. **No instance properties**: `$_overrides_cache` and `$_checked` must be `protected static` (or `private static`). The singleton instance carries NO state — it exists solely as a Loader-compatible hook target.

4. **DB access via `AcrossAI_Sitewide_Query` only**: Class never uses `$wpdb` directly.

5. **Hook timing**: PATH B wires `wp_register_ability_args` filter at P10 and `wp_abilities_api_init` at P100001. These fire inside the WP Abilities API boot sequence, not at `plugins_loaded` time.

---

## Active Architecture Decisions (from this session)

- **DEC-PATH-A/B**: Manager REST requests (detected via `$_SERVER` at `plugins_loaded`) skip all override hooks. Detection is a performance hint only — NOT access control.
- **DEC-CACHE**: Transient key `acrossai_ability_overrides_cache`, TTL 12h. In-memory static array populated from transient. Bust on any write or delete.
- **DEC-UNREGISTER**: `site_allowed = false` → complete unregistration via `wp_unregister_ability()` at P100001, after all plugin registrations. Also inject into args at filter time for consumers reading individual ability args.
- **DEC-WIRING-BUST**: `bust_cache()` wired to `acrossai_abilities_sitewide_after_save` action via Loader. Also called directly in `delete_override()` and bulk `reset` at the call site (these paths do not fire `after_save`).

---

## Bugs / Watchpoints

- **W-001 (BLOCKER)**: `delete_override()` and bulk `reset` in existing controllers do NOT fire `acrossai_abilities_sitewide_after_save`. Cache bust must be called directly at the call site. See plan.md §W-001.
- **W-002**: `get_all_overrides()` method missing from `AcrossAI_Sitewide_Query`. Must be added.
- **PHP bool→int cast (from feature 001)**: BerlinDB tinyint columns require PHP bool to be cast to `(int)` before INSERT/UPDATE. Already implemented in `save_override()`. No new risk in this feature (processor only reads).
- **`has_param()` partial-field pattern (from feature 001)**: Processor only reads DB rows — not applicable here.

---

## Conflicts

None. This feature adds a new class and minimal targeted changes to existing controllers + query layer. Constitution §I is fully satisfied — singleton+instance wrapper pattern applied per SEC-PLAN-002 amendment.

---

## Assumptions

- WP Abilities API (`wp_register_ability_args`, `wp_abilities_api_init`, `wp_unregister_ability`) available in WP 7.0+. If absent, hooks register but never fire — no error.
- `AcrossAI_Sitewide_Query` is initialized by `plugins_loaded` P10 (before P20 boot).
- Manager REST namespace path segment is `acrossai-abilities/` — stable across v1/v2 changes.
