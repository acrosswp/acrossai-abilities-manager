# Security Constraints — Feature 033

> Inline security review (security-review extension was available; producing the artifact here per project policy "user runs spec-kit commands themselves" — `/speckit-security-review-plan` was NOT auto-invoked).

## Scope

Plan-level review of `specs/033-library-card-toggle-subgroup/plan.md`. Display-only, additive feature on the Ability Library admin page. No new REST endpoints, no DB schema change, no new form/AJAX boundary, no new user-input surface beyond a trusted-PHP-source `args['sub_group']` field on `Ability_Definition` subclasses.

## Trust Boundaries

| Boundary | Direction | Source | Sink | Validation |
|----------|-----------|--------|------|------------|
| Add-on PHP → Library Registry | inbound | Trusted plugin/theme PHP (same as `args['category']` / `args['label']`) | `AcrossAI_Ability_Library_Registry::validate_and_normalize()` | New: `sanitize_key_field()` (existing 100-char + `sanitize_key()` helper) for `sub_group`; `wp_kses_post()` for `sub_group_label`. |
| Registry → JS app | outbound | Server-side localized data | `window.acrossaiAbilityLibraryData.definitions[].sub_group{,_label}` | Already sanitized at Registry. React renders as text content (auto-escaped). |
| JS app → Save endpoint | outbound | Admin checkbox toggles | Existing `acrossai_library_config` site option (unchanged shape: `{ enabled, mode, sub_keys }`) | **No new save surface.** Sub-group never crosses this boundary. |
| Admin → REST | inbound | Admin user (`manage_options` capability) | Existing Library REST controller (untouched) | Existing nonce + capability checks remain in force. |

## Constraints to Enforce During Implementation

### SC-033-01 — Sub-group sanitization MUST reuse the existing key-field helper

Implementation MUST call `AcrossAI_Ability_Library_Config::sanitize_key_field()` for `sub_group`. Do NOT invent a new sanitizer. The helper enforces lowercase, hyphen-friendly, ≤ 100 chars — the same surface already accepted by Plugin Check, PHPCS, and the existing category/slug paths.

**Verifies**: FR-009, Constitution §IV (sanitize at boundary).

### SC-033-02 — `sub_group_label` MUST pass through `wp_kses_post()` when add-on supplies an explicit override

When an add-on supplies `args['sub_group_label']`, the Registry MUST call `wp_kses_post()` (same as the existing `category_label` / `slug_label` handling). The auto-derived label from `ucwords(str_replace('-', ' ', $clean))` requires no escaping (it is built from already-sanitized input), but the explicit override path MUST escape.

**Verifies**: Constitution §II (escaping), defense-in-depth.

### SC-033-03 — Empty-after-sanitization MUST be treated as "no sub_group declared"

If `sub_group` survives sanitization to `''`, the Registry MUST omit the `sub_group` / `sub_group_label` keys from the validated entry (FR-018). This prevents downstream JS code from rendering an `<h4>` with empty text content.

**Verifies**: FR-018; prevents accessibility regression (empty headings).

### SC-033-04 — `sub_keys` map MUST remain slug-keyed; sub_group MUST NOT participate in saved config

`AcrossAI_Ability_Library_Config::sanitize_entry()` and `save_config()` MUST NOT be modified. The saved option shape remains `{ enabled: bool, mode: 'all'|'specific', sub_keys: { [slug]: bool } }`. A regression test MUST assert that calling `save_config()` with a payload containing a stray `sub_group` field produces a saved entry whose shape does NOT include `sub_group` anywhere.

**Verifies**: FR-011, FR-012; protects the on-disk wire-key invariant.

### SC-033-05 — No new REST route, no new permission_callback, no new nonce surface

The feature adds zero new REST routes. The existing Library REST controller is untouched. The constitution's REST `permission_callback` Return Type rule (MUST) does not gain new touchpoints in this feature, but any future Library REST work MUST still comply.

**Verifies**: Constitution §IV REST `permission_callback` Return Type (MUST).

### SC-033-06 — Display path MUST NOT echo `sub_group` raw via PHP

If a future enhancement adds server-rendered Library output, `sub_group` and `sub_group_label` MUST be escaped via `esc_html()` at the render point. The current React-rendered path auto-escapes; PHP-rendered fallback (none planned) would need explicit escaping.

**Verifies**: Constitution §IV (escape at point of rendering).

### SC-033-07 — Debug logging MUST follow `PATTERN-WP-DEBUG-LOG-GUARD`

If the Registry adds a new `error_log()` for a rejected sub_group (recommended, mirrors `log_invalid()`), it MUST be inside `if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) { … }` with the `phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log` inside the guard.

**Verifies**: Constitution §II Plugin Check compliance.

## Authorization / Capability Review

| Surface | Capability | Status |
|---------|------------|--------|
| Library page render | `manage_options` (existing) | Unchanged |
| Library REST save | `manage_options` (existing) | Unchanged |
| `acrossai_abilities_api_init` filter (sub_group declaration) | None — trusted plugin code | Unchanged |

No new capability check is introduced or weakened. Feature 033 does not alter who can do what on the Library page.

## Data Isolation

- Saved configuration: `get_site_option()` / `update_site_option()` — multisite-shared (per existing AcrossAI_Ability_Library_Config behavior). Sub-group is not persisted, so no multisite-isolation question arises.
- No PII handled.
- No external network calls added.

## Async Security Context

None. Feature is synchronous, fires during admin page render and during the `acrossai_abilities_api_init` filter at init P99. No Action Scheduler usage, no background process.

## Risks / Warnings

| ID | Risk | Severity | Mitigation |
|----|------|----------|------------|
| R-SEC-1 | An add-on author supplies a malicious `sub_group_label` containing `<script>` tags. | Low (mitigated) | `wp_kses_post()` strips script tags. React rendering also escapes. Two-layer defense. |
| R-SEC-2 | A future test that exercises the save endpoint accidentally includes `sub_group` in the payload, then asserts that the saved shape includes it. | Low | SC-033-04 mandates the inverse assertion: stray `sub_group` MUST NOT appear in saved data. |
| R-SEC-3 | A future refactor unifies category and sub_group handling and accidentally exposes `sub_group` on the save side. | Medium (long-term) | This `security-constraints.md` is the audit trail; future security reviews MUST grep for `sub_group` in `AcrossAI_Ability_Library_Config` and reject any introduction. |

## Verdict

**No blocking security findings.** The feature is purely additive, display-only, and enters through a trusted trust boundary already used by `args['category']` and `args['label']`. The 7 constraints above must be enforced during implementation; PHPUnit coverage required for SC-033-03 (empty-after-sanitization), SC-033-04 (no sub_group in saved config), and SC-033-07 (WP_DEBUG_LOG guard).

Proceed to `/speckit-tasks`.
