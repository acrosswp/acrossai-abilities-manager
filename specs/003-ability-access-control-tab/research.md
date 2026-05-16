# Research: Ability Access Control Tab

**Phase**: 0 | **Date**: 2026-05-16 | **Plan**: [plan.md](plan.md)

All decisions below were resolved from the user arguments, spec, and direct inspection of the
codebase. No NEEDS CLARIFICATION items remain.

---

## Decision 1: REST API Root URL Mapping

**Decision**: Pass `window.acrossaiAbilitiesSitewide.rest_url` as the `restApiRoot` prop to `AccessControl`.

**Rationale**:
- `admin/Main.php` (line ~134) localises the sitewide script with `rest_url` (PHP key), not `restApiRoot`.
- The `AccessControl` component (vendor) expects a prop named `restApiRoot`.
- Mapping: `restApiRoot={ sitewideConfig.rest_url || '/wp-json' }` where `sitewideConfig = window.acrossaiAbilitiesSitewide || {}`.
- The `/wp-json` fallback prevents a prop-type warning when the panel renders before the script fires.

**Alternatives considered**:
- Adding a `restApiRoot` key to the PHP localize array — rejected: modifies `admin/Main.php` unnecessarily; the spec explicitly states only 3 files change, and `admin/Main.php` is not one of them.

---

## Decision 2: PHP Method Name

**Decision**: Hook `AcrossAI_Sitewide_Access_Control` on `rest_api_init` calling `register_rest_api()`.

**Rationale**:
- Direct inspection of `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` confirms the public method is `register_rest_api()`, not `register_routes()`.
- The spec explicitly confirms this: "The method to call is register_rest_api() not register_routes()."
- `register_rest_api()` delegates to `AccessControlManager::register_rest_api()` which registers the `wpb-ac/v1` routes.

**Alternatives considered**: N/A — method name is dictated by the existing class.

---

## Decision 3: Webpack Alias Target Path

**Decision**: Point `@wpb/access-control` to `vendor/wpboilerplate/wpb-access-control/js/index.js` (vendor copy).

**Rationale**:
- `ls vendor/wpboilerplate/wpb-access-control/js/` confirms `index.js`, `AccessControl.js`, `AccessControl.scss`, and `components/` are present.
- Repo memory (`access-control-integration-2026-05-16.md`) records a sibling checkout path — but the spec explicitly states `vendor/wpboilerplate/wpb-access-control/js/index.js` is the target, and the vendor directory is confirmed to be populated.
- Using the vendor copy keeps all Composer-managed dependencies in one place and is consistent with how the plugin resolves PHP dependencies.
- `AccessControl.scss` is imported inside `AccessControl.js` (`import './AccessControl.scss'`), so webpack bundles the styles automatically via the sitewide CSS entry — no separate enqueue needed.

**Alternatives considered**:
- Sibling checkout path (`../../wpb-access-control/js/index.js`) — rejected: the spec directs the vendor copy; sibling paths are development-only and not present in CI/production.

---

## Decision 4: apiFetch Nonce Middleware

**Decision**: Do not register nonce middleware inside `AbilityEditPanel.jsx` or the Access Control tab render.

**Rationale**:
- `src/js/sitewide/index.js` (lines 11–14) already calls `apiFetch.use(apiFetch.createNonceMiddleware(config.nonce))` once on boot.
- `AccessControl.js` (vendor) calls `apiFetch` without setting up its own middleware — it relies on the global middleware already registered by the consuming plugin.
- Registering the middleware a second time would chain duplicate nonce middleware and could cause double-processing of requests.

**Alternatives considered**: N/A — constraint is explicit in spec and confirmed by code inspection.

---

## Decision 5: hasUnsaved Close Guard

**Decision**: Do not add Access Control draft state to the `hasUnsaved` expression.

**Rationale**:
- The `AccessControl` component auto-saves on interaction (it issues a REST PUT/PATCH immediately after the user selects a rule, as confirmed in `AccessControl.js` — `onSave` is called after a successful `apiFetch`).
- There is no "pending unsaved draft" concept in the vendor component from the plugin's perspective.
- Adding a phantom draft state would trigger false "unsaved changes" warnings when the tab hasn't even been visited.

**Alternatives considered**: N/A — component behaviour is dictated by the vendor library.
