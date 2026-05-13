# Quickstart: Sitewide Ability Management

**Phase**: 1 | **Date**: 2026-05-11 | **Plan**: [plan.md](plan.md)

This guide covers setting up the development environment to build, test, and run the
Sitewide Ability Management feature in the AcrossAI Abilities Manager plugin.

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

## 1. PHP Dependencies

Add `berlindb/core` (not yet in composer.json) and run Mozart to prefix it:

```bash
# 1. Add berlindb to composer.json require
composer require berlindb/core:^2.0

# 2. Run Mozart to prefix BerlinDB into AcrossAI_Abilities_Manager\Vendor\
composer exec mozart compose

# 3. Run autoload dump (Jetpack autoloader)
composer dump-autoload
```

After this, BerlinDB classes are available at:
`AcrossAI_Abilities_Manager\Vendor\BerlinDB\Database\{Table,Query,Row,Schema}`

---

## 2. JS Dependencies

```bash
npm install
```

New `@wordpress/*` packages are already listed as peer/dev dependencies by `@wordpress/scripts`.
No additional package installation is needed — DataViews and DataForms ship with WordPress 6.9+.

---

## 3. Build

```bash
# Development build with watch
npm start

# Production build
npm run build
```

Built assets output to:
- `build/js/sitewide.js`
- `build/js/sitewide.asset.php` ← loaded by `Admin\Main::__construct()`; consumed by `enqueue_styles()` / `enqueue_scripts()`; never hardcode deps or version
- `build/css/sitewide.css`

The webpack entry point is `src/js/sitewide/index.js`.
See `webpack.config.js` for entry point registration.

---

## 4. webpack.config.js Entry

Add the sitewide entry to `webpack.config.js`:

```js
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  entry: {
    ...defaultConfig.entry,
    'js/sitewide': './src/js/sitewide/index.js',
    'css/sitewide': './src/scss/sitewide/admin.scss',
  },
};
```

---

## 5. Database Setup

The BerlinDB Table class handles schema creation and migration automatically on plugin activation:

```php
// Activation hook (Activator.php — add this)
AcrossAI_Sitewide_Table::instance()->maybe_upgrade();
```

To verify the table was created:

```bash
wp db query "SHOW TABLES LIKE '%acrossai_abilities_overwrite%';"
```

---

## 6. Admin Page

After build and activation, navigate to:
`/wp-admin/admin.php?page=acrossai-abilities-manager`

The React app mounts on `<div id="acrossai-abilities-manager-root"></div>`.

---

## 7. Running Tests

### PHP

```bash
# PHPCS
vendor/bin/phpcs --standard=WordPress includes/Modules/Sitewide/ includes/Utilities/ includes/Base/

# PHPStan (level 8)
vendor/bin/phpstan analyse --level=8 includes/Modules/Sitewide/ includes/Utilities/ includes/Base/

# PHPUnit
vendor/bin/phpunit --testdox --filter SitewideTest
```

### JS

```bash
# Jest
npm run test -- --testPathPattern=sitewide
```

---

## 8. Environment Variables

```bash
WP_TESTS_DIR=/path/to/wordpress-tests-lib
WP_TESTS_DB_NAME=wp_test
WP_TESTS_DB_USER=root
WP_TESTS_DB_PASSWORD=
WP_TESTS_DB_HOST=localhost
```

---

## 9. WP-CLI Helpers

```bash
# Flush rewrite rules after registering REST routes
wp rewrite flush

# Inspect registered REST routes
wp rest route list --format=table | grep acrossai

# Check current ability overrides table
wp db query "SELECT ability_slug, site_allowed, show_in_mcp FROM {prefix}acrossai_abilities_overwrite LIMIT 10;"
```

---

## 10. Key Files Reference

| File | Purpose |
|---|---|
| `includes/Base/AcrossAI_Module_Base.php` | Abstract base all modules extend |
| `includes/Modules/Sitewide/AcrossAI_Sitewide_Module.php` | Context-neutral: REST + DB hooks only |
| `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` | 7 REST endpoints |
| `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` | BerlinDB Query (CRUD) |
| `includes/Utilities/AcrossAI_Ability_Merger.php` | Registry + override merge logic |
| `admin/Main.php` | Asset enqueue (scoped via `$hook_suffix` guard; manifest loaded in constructor) |
| `admin/Partials/Menu.php` | Menu registration (`add_menu_page`) + page render (`contents()` outputs React root) |
| `src/js/sitewide/index.js` | React entry — `createRoot`, apiFetch setup |
| `src/js/sitewide/store/index.js` | Redux store (`acrossai-abilities/sitewide`) |
| `src/js/sitewide/components/AbilityTable.jsx` | DataViews table (13 fields, deduped row actions) |
| `src/js/sitewide/components/AbilityEditPanel.jsx` | Slide-in drawer — per-tab save, `useEffect([slug])` dep |
| `src/js/sitewide/components/cells/TriStateBadgeCell.jsx` | Tri-state badge renderer (Yes/No/Inherit + Default suffix) |
| `src/js/sitewide/components/cells/McpServersCell.jsx` | MCP servers list renderer (All / truncated list / —) |
| `specs/001-sitewide-ability-management/plan.md` | This feature's implementation plan |
| `specs/001-sitewide-ability-management/data-model.md` | DB schema, entities, state shape |
| `specs/001-sitewide-ability-management/contracts/rest-api.md` | REST API contracts |
