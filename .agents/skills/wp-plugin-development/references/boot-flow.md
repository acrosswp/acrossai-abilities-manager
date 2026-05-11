# Boot flow & constants

## Full boot sequence

```
// File is loaded by WordPress
wordpress-plugin-boilerplate.php
  └─ define WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_FILE = __FILE__
  └─ register_activation_hook  → Includes\Activator::activate()
  └─ register_deactivation_hook → Includes\Deactivator::deactivate()
  └─ wordpress_plugin_boilerplate_run()
       └─ Main::instance()           // singleton; triggers __construct()
            ├─ define_constants()    // all constants except PLUGIN_FILE
            ├─ register_autoloader() // spl_autoload_register via Autoloader.php
            ├─ load_composer_dependencies() // vendor/autoload.php + Mozart blocks
            ├─ load_dependencies()   // $this->loader = Loader::instance()
            ├─ set_locale()          // loader->add_action('init', $i18n, 'do_load_textdomain')
            └─ load_hooks()
                 └─ apply_filters('wordpress-plugin-boilerplate-load', true)
                      ├─ define_admin_hooks()   // Loader collects admin hooks
                      └─ define_public_hooks()  // Loader collects public hooks
       └─ add_action('plugins_loaded', [$plugin, 'run'], 0)

// WordPress fires plugins_loaded at priority 0
Main::run()
  └─ Loader::run()
       ├─ foreach $filters → add_filter(...)
       └─ foreach $actions → add_action(...)
```

## Where to put constants

| Constant | Location |
|---|---|
| `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_FILE` | Bootstrap only (already there) |
| All other constants | `includes/Main.php::define_constants()` |

Use the private `define($name, $value)` guard (already in the class) when adding constants:

```php
$this->define( 'MY_PLUGIN_FOO', 'bar' );
```

Never add constants in the bootstrap file or in any other class.

## Defined constants (complete list)

- `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_FILE` — set in bootstrap
- `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_BASENAME`
- `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_PATH`
- `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_URL`
- `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_NAME_SLUG`
- `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_NAME`
- `WORDPRESS_PLUGIN_BOILERPLATE_VERSION` — read live from `get_plugin_data()`

⚠️ **Known source bug**: `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_URL` is `define()`-d twice
in `define_constants()`. The private guard prevents a PHP fatal; the constant keeps its
first (correct URL) value. Do not attempt to fix it — leave the block as-is and append
new constants below it.

## Kill switch

Third-party code can prevent hook registration entirely:

```php
add_filter( 'wordpress-plugin-boilerplate-load', '__return_false' );
```

This filter runs inside `load_hooks()` before any admin or public hooks are registered.

## Loader API

`$this->loader` is the `Includes\Loader` singleton. Both actions and filters use the same signature:

```php
$this->loader->add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 );
$this->loader->add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 );
```

All four of these calls are equivalent and correct — use whichever fits the WordPress hook type:

```php
// Action (no return value)
$this->loader->add_action( 'admin_menu', $menu_obj, 'register_menus' );

// Filter (must return a value in the callback)
$this->loader->add_filter( 'the_content', $content_obj, 'modify_content' );

// Custom priority and arg count
$this->loader->add_action( 'plugin_action_links', $menu_obj, 'plugin_action_links', 1000, 2 );
```

Never call `add_action()` or `add_filter()` directly in any class — always go through the Loader.

- Upstream reference: `https://github.com/WPBoilerplate/wordpress-plugin-boilerplate/blob/main/includes/Main.php`
