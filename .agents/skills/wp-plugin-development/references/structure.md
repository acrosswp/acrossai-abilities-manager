# Structure: folder layout & namespace map

## Directory tree

```
plugin-root/
├── wordpress-plugin-boilerplate.php   # bootstrap (Plugin Name header + PLUGIN_FILE constant)
├── includes/
│   ├── Main.php         # core class: constants, autoloader, loader, hooks
│   ├── Loader.php       # singleton: collects add_action/add_filter calls, fires on run()
│   ├── Autoloader.php   # custom spl_autoload_register (namespace → directory)
│   ├── Activator.php    # static activate() stub
│   ├── Deactivator.php  # static deactivate() stub
│   ├── I18n.php         # do_load_textdomain() hooked on init
│   ├── Utilities/       # shared, context-neutral helpers (sanitizers, mergers, etc.)
│   └── Modules/
│       └── MyFeature/
│           ├── index.php                    # directory sentinel (silence is golden)
│           ├── MyFeature_Rest_Controller.php # singleton: REST_NAMESPACE + register_routes() + check_permission()
│           ├── Rest/                        # created when controller exceeds ~400 lines
│           │   ├── index.php
│           │   ├── MyFeature_Read_Controller.php   # GET handlers; singleton; delegates permission to orchestrator
│           │   ├── MyFeature_Write_Controller.php  # POST/DELETE handlers; singleton; delegates permission
│           │   └── MyFeature_Bulk_Controller.php   # bulk handlers; singleton; delegates permission
│           └── Database/                    # BerlinDB schema/table/query/row classes (context-neutral)
│               ├── MyFeature_Schema.php
│               ├── MyFeature_Table.php      # singleton; BerlinDB hooks maybe_upgrade() on admin_init
│               ├── MyFeature_Row.php
│               └── MyFeature_Query.php      # singleton; CRUD methods
├── admin/
│   ├── Main.php         # Admin\Main: enqueue backend CSS/JS (only place wp_enqueue_* is called)
│   └── Partials/
│       ├── Menu.php           # Admin\Partials\Menu: top-level add_menu_page + plugin_action_links
│       └── MyFeaturePage.php  # Admin\Partials\MyFeaturePage: feature-specific menu + render (if needed)
├── public/
│   ├── Main.php         # Public\Main: enqueue frontend CSS/JS
│   └── partials/        # frontend template partials (placeholder)
├── src/
│   ├── js/
│   │   ├── backend.js
│   │   └── frontend.js
│   ├── scss/
│   │   ├── backend.scss
│   │   ├── frontend.scss
│   │   └── blocks/core/   # per-core-block stylesheets (globbed by webpack)
│   ├── blocks/            # custom Gutenberg blocks (auto-discovered via block.json)
│   ├── media/             # static media → build/media/ (CopyPlugin)
│   └── fonts/             # static fonts → build/fonts/ (CopyPlugin)
├── build/                 # compiled output — never edit directly
├── languages/             # .pot / .po / .mo files
├── composer.json
├── package.json
└── uninstall.php
```

> **⚠️ No `includes/Base/` directory, no abstract module base class, no `register_hooks()` delegation.**
> See SKILL.md §2 and the plugin CONSTITUTION for the canonical rules.


### Where does feature module code live?

| Code type | Correct location |
|---|---|
| REST controller (orchestrator + sub-controllers) | `includes/Modules/MyFeature/` + `includes/Modules/MyFeature/Rest/` |
| DB schema / query / row classes | `includes/Modules/MyFeature/Database/` |
| Admin menu, page renderer | `admin/Partials/MyFeaturePage.php` |
| Admin asset enqueue | `admin/Main.php::enqueue_styles()/enqueue_scripts()` — nowhere else |
| Frontend template output | `public/partials/` |
| Shared utilities (sanitizers, mergers, helpers) | `includes/Utilities/` |

The key rule: **`includes/` is for shared, context-neutral code.** Anything that calls
`add_menu_page()`, `wp_enqueue_style()` for admin, or renders admin HTML belongs in `admin/`.

There is **no module orchestrator class** (`MyFeature_Module.php`), **no `boot()`**, and **no
`register_hooks()` delegation**. All hooks are wired directly in `includes/Main.php::define_admin_hooks()`
/ `define_public_hooks()`. Every feature class uses the singleton `instance()` pattern with a
`private` constructor — see SKILL.md §2 for the canonical form.


## PSR-4 namespace map

| Namespace prefix | Directory |
|---|---|
| `WordPress_Plugin_Boilerplate\Includes\` | `includes/` |
| `WordPress_Plugin_Boilerplate\Admin\` | `admin/` |
| `WordPress_Plugin_Boilerplate\Public\` | `public/` |

The root prefix changes to your plugin's namespace after running `init-plugin.sh`.
`Public` is used as a reserved-word-safe alias — it is not the PHP keyword.

## Autoloader strategy

One autoloader is used: the **Jetpack autoloader** (`automattic/jetpack-autoloader` Composer package).

`load_composer_dependencies()` conditionally loads `vendor/autoload_packages.php` if the file
exists. That file, generated by `composer install`, registers the PSR-4 map from `composer.json`
and handles all class loading.

**`Autoloader.php` exists but is no longer called from the constructor.** It is kept for
reference; do not rely on it for class resolution.

Running `composer install` is required before the plugin can load its own classes. Always run
it after cloning or after changing dependencies:

```
composer install
composer dump-autoload
```

- Upstream reference: `https://github.com/WPBoilerplate/wordpress-plugin-boilerplate/blob/main/includes/Autoloader.php`
