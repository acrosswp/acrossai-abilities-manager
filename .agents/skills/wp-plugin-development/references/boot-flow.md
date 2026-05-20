# Boot flow & constants

## Full boot sequence

```
// File is loaded by WordPress
wordpress-plugin-boilerplate.php
  └─ define WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_FILE = __FILE__
  └─ require includes/Main.php
  └─ register_activation_hook  → Includes\Activator::activate()
  └─ register_deactivation_hook → Includes\Deactivator::deactivate()
  └─ wordpress_plugin_boilerplate_run()
       └─ Main::instance()           // singleton; triggers __construct()
            ├─ define_constants()    // all constants except PLUGIN_FILE (version hardcoded)
            ├─ $this->plugin_dir     // set directly in constructor from PLUGIN_PATH constant
            ├─ load_composer_dependencies() // conditionally loads vendor/autoload_packages.php
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
- `WORDPRESS_PLUGIN_BOILERPLATE_VERSION` — hardcoded string in `define_constants()`; update it manually when bumping the version

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

## `includes/Main.php` is the single source of all hook registration

`define_admin_hooks()` and `define_public_hooks()` in `includes/Main.php` are the **only**
methods that feed hooks into the Loader. Every hook the plugin registers must trace back to
one of those two methods.

**Correct pattern for feature classes (singleton + direct wiring):**

All feature classes in this plugin use the `instance()` singleton pattern. There is no
`Module_Base` abstract class and no `register_hooks()` delegation. `includes/Main.php` is
the single location that wires every hook:

```php
// includes/Main.php::define_admin_hooks()
private function define_admin_hooks(): void {
    // Admin asset enqueue
    $plugin_admin = new \AcrossAI_Abilities_Manager\Admin\Main( $this->get_plugin_name(), $this->get_version() );
    $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
    $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

    // Admin menu
    $menu = new \AcrossAI_Abilities_Manager\Admin\Partials\Menu( $this->get_plugin_name(), $this->get_version() );
    $this->loader->add_action( 'admin_menu', $menu, 'main_menu' );
    $this->loader->add_action( 'plugin_action_links', $menu, 'plugin_action_links', 1000, 2 );

    // Feature REST routes — singleton instance, no constructor args needed
    $this->loader->add_action(
        'rest_api_init',
        \AcrossAI_Abilities_Manager\Includes\Modules\Sitewide\AcrossAI_Sitewide_Rest_Controller::instance(),
        'register_routes'
    );
}
```

Feature classes expose the singleton via:

```php
// Any feature class (e.g. AcrossAI_Sitewide_Rest_Controller, AcrossAI_Sitewide_Query, etc.)
protected static $_instance = null;

public static function instance(): self {
    if ( null === self::$_instance ) {
        self::$_instance = new self();
    }
    return self::$_instance;
}

private function __construct() {
    // dependencies obtained via other ::instance() calls, never via constructor injection
    $this->db_query = \AcrossAI_Sitewide_Query::instance();
}
```

**What to avoid:**

```php
// ❌ Module orchestrator with register_hooks() — this pattern is NOT used
class AcrossAI_Sitewide_Module {
    public function register_hooks( Loader $loader ): void { ... }
}

// ❌ Abstract Module_Base — deleted; do not recreate
abstract class AcrossAI_Module_Base {
    abstract public function register_hooks( Loader $loader ): void;
}

// ❌ Module self-registering
public function boot(): void {
    $loader = Loader::instance();   // wrong — module must not self-register
    $this->register_hooks( $loader );
}

// ❌ Calling register_hooks() from load_dependencies()
private function load_dependencies(): void {
    $this->loader = Loader::instance();
    $module = new SomeModule();
    $module->register_hooks( $this->loader ); // wrong — runs before define_admin_hooks()
}
```

- Upstream reference: `https://github.com/WPBoilerplate/wordpress-plugin-boilerplate/blob/main/includes/Main.php`
