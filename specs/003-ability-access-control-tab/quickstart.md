# Quickstart: Ability Access Control Tab

**Phase**: 1 | **Date**: 2026-05-16 | **Plan**: [plan.md](plan.md)

This guide covers verifying the Access Control tab feature locally after the three-file change is
applied (`includes/Main.php`, `webpack.config.js`, `src/js/sitewide/components/AbilityEditPanel.jsx`).

---

## Prerequisites

| Tool | Minimum Version |
|---|---|
| PHP | 7.4 |
| Composer | 2.0 |
| Node.js | 18.0 |
| npm | 9.0 |
| WordPress | 6.9 |

---

## 1. Confirm Vendor Library Is Present

```bash
ls vendor/wpboilerplate/wpb-access-control/js/
# Expected: AccessControl.js  AccessControl.scss  components/  index.js
```

If missing, run:

```bash
composer install
```

---

## 2. Build the JS Bundle

```bash
# Development build with watch
npm start

# One-off production build
npm run build
```

The webpack alias `@wpb/access-control` is resolved at build time. If the alias is missing,
the build will fail with `Cannot find module '@wpb/access-control'` — this is the expected
early-failure signal confirming the alias must be added to `webpack.config.js`.

Built assets:
- `build/js/sitewide.js` — includes the `AccessControl` component
- `build/css/sitewide.css` — includes `AccessControl.scss` (auto-bundled via component import)

---

## 3. Verify REST Routes Are Registered

After activating the plugin, confirm the `wpb-ac/v1` routes appear in the WP REST API index:

```bash
curl -s https://<site>/wp-json/ | python3 -m json.tool | grep wpb-ac
# or with WP-CLI:
wp eval 'echo json_encode( array_keys( rest_get_server()->get_routes() ) );' | tr ',' '\n' | grep wpb-ac
```

Expected: routes under `/wpb-ac/v1/providers` and `/wpb-ac/v1/rules` are listed.

If no `wpb-ac` routes appear, the hook registration in `includes/Main.php` is missing or the
`wpboilerplate/wpb-access-control` PHP library is not installed.

---

## 4. Manual Browser Test

1. Navigate to **WP Admin → AcrossAI Abilities Manager**.
2. Click any ability row to open the slide-in edit panel.
3. Confirm three tabs appear: **General**, **MCP**, **Access Control**.
4. Click **Access Control** — the tab must render the provider dropdown without a JS console error.
5. Select a rule (e.g., "Specific Roles" → "Editor") and save.
6. Close and reopen the panel for the same ability → **Access Control** tab must show the saved rule.

---

## 5. Validation Commands

```bash
# PHPCS (PHP coding standards)
composer run phpcs

# PHPStan level 8
composer run phpstan

# ESLint
npm run lint:js

# Package hierarchy validation
npm run validate-packages
```

All commands must exit with code 0 before the feature is considered complete.

---

## 6. Common Issues

| Symptom | Likely Cause | Fix |
|---|---|---|
| `Cannot find module '@wpb/access-control'` during build | Webpack alias not added | Add `resolve.alias` block to `webpack.config.js` |
| Access Control tab renders blank / JS error | `window.acrossaiAbilitiesSitewide` undefined | Check that the sitewide script is enqueued on the admin page |
| REST PUT returns 404 | `wpb-ac/v1` routes not registered | Verify hook in `includes/Main.php` and that vendor library is installed |
| REST PUT returns 403 | Nonce invalid or expired | Hard-refresh the admin page to regenerate nonce |
| `rest_url` is `undefined` in `restApiRoot` | Key mismatch — use `rest_url` not `restApiRoot` | Confirm `sitewideConfig.rest_url` is used (see plan.md Change Spec §3) |
