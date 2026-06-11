# Security Constraints: Library Category/Slug Rebrand (Feature 031)

**Review date**: 2026-06-11
**Overall risk**: INFORMATIONAL
**Findings**: 0 Critical / 0 High / 0 Medium / 0 Low / 2 Informational

---

## Active Constraints

### SC-031-01 — No `dangerouslySetInnerHTML` for label fields

`LibraryCard.js` MUST render `categoryLabel`, `slugLabel`, and `name` exclusively as React JSX
string or element props. `dangerouslySetInnerHTML` MUST NOT be used for any of these values.

**Why**: `category_label` and `slug_label` are sanitized by `wp_kses_post()` which permits a
subset of HTML. If rendered via `dangerouslySetInnerHTML`, stored HTML could execute as XSS.
React JSX auto-escaping (the default) makes this safe; deviating from it does not.

**Verification**: Phase 4 implementation checklist item — grep `LibraryCard.js` for
`dangerouslySetInnerHTML` before marking T017 complete.

---

## Deferred / Informational Findings

### SEC-031-001 — `wp_kses_post()` over-permissive for label fields

`category_label` and `slug_label` are sanitized by `wp_kses_post()` (permits some HTML) rather
than the more appropriate `sanitize_text_field()`. Pre-existing since Feature 027. Not exploitable
in current React rendering context but creates latent risk if rendering approach ever changes.

**Resolution**: Replace `wp_kses_post()` with `sanitize_text_field()` for these two fields in
a future cleanup feature. Track as tech-debt. Out of scope for Feature 031.

---

## Preserved Security Controls (must not be changed)

- `AcrossAI_Ability_Library_Rest_Controller::check_permission()` — `manage_options` + nonce,
  returns `true|\WP_Error` only (never `WP_REST_Response` — BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE)
- `AcrossAI_Ability_Library_Config::sanitize_key_field()` — `sanitize_key()` + max-length guard
  applied to all incoming category and slug key strings
- `name` field regex: `preg_replace('/[^a-z0-9_\-\/]/', '', strtolower(...))` in Registry
- On-disk option shape via `update_site_option('acrossai_library_config', ...)` — no raw DB writes
