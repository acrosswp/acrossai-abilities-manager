# Admin area: adding pages and assets

## Namespace & directory

All admin code belongs under `admin/` with namespace `WordPress_Plugin_Boilerplate\Admin`.
Screen classes go under `admin/Partials/` with namespace `WordPress_Plugin_Boilerplate\Admin\Partials`.

## Existing classes

### `Admin\Main` (`admin/Main.php`)

- Receives `$plugin_name` and `$version` in its constructor.
- Reads `*.asset.php` manifests in the constructor (not in the enqueue methods):
  ```php
  $this->js_asset_file  = include WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_PATH . 'build/js/backend.asset.php';
  $this->css_asset_file = include WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_PATH . 'build/css/backend.asset.php';
  ```
- `enqueue_styles()` — hooked on `admin_enqueue_scripts`, enqueues `build/css/backend.css`.
- `enqueue_scripts()` — hooked on `admin_enqueue_scripts`, enqueues `build/js/backend.js`.

### `Admin\Partials\Menu` (`admin/Partials/Menu.php`)

- `main_menu()` — hooked on `admin_menu`; calls `add_menu_page()`.
- `about()` — callback for the About admin page.
- `plugin_action_links($links, $file)` — hooked on `plugin_action_links` at priority **1000**;
  checks `WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_BASENAME` before modifying links.

## How to add a new admin page

1. Create `admin/Partials/MyPage.php`:
   ```php
   namespace WordPress_Plugin_Boilerplate\Admin\Partials;
   class MyPage {
       public function add_menu() { add_submenu_page( ... ); }
       public function render()   { /* output */ }
   }
   ```
2. In `includes/Main.php::define_admin_hooks()`, instantiate and register:
   ```php
   $my_page = new \WordPress_Plugin_Boilerplate\Admin\Partials\MyPage( $this->get_plugin_name(), $this->get_version() );
   $this->loader->add_action( 'admin_menu', $my_page, 'add_menu' );
   ```
3. Run `composer dump-autoload`.

**Never** call `add_action()` directly inside `MyPage::__construct()` or any other constructor.
All hooks must be registered through the Loader.

## How to add new admin assets

Add an entry to `webpack.config.js`, then enqueue it in `Admin\Main` (or a new class) using
the sibling `*.asset.php` manifest. Never hardcode version strings.

See `build-system.md` for the full 5-step workflow with diffs.

⚠️ **PHP fatal if build not run:** `Admin\Main::__construct()` `include`s the manifest files
directly. Missing `build/` artifacts cause a PHP fatal on every admin page load, not a 404.

## Conditional per-screen enqueuing

To load assets only on a specific admin screen, guard the enqueue call with `get_current_screen()`:

```php
public function enqueue_scripts() {
    $screen = get_current_screen();
    if ( ! $screen || 'toplevel_page_my-plugin' !== $screen->id ) {
        return;
    }
    wp_enqueue_script( ... );
}
```

Call `get_current_screen()` inside the hooked method — never in the constructor.

## Passing PHP data to admin JS (wp_localize_script)

After enqueuing a script, use `wp_localize_script` to attach PHP data:

```php
public function enqueue_scripts() {
    wp_enqueue_script(
        $this->plugin_name,
        \WORDPRESS_PLUGIN_BOILERPLATE_PLUGIN_URL . 'build/js/backend.js',
        $this->js_asset_file['dependencies'],
        $this->js_asset_file['version'],
        true
    );
    wp_localize_script(
        $this->plugin_name,
        'myPluginAdmin',          // JS global object name
        [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'my_plugin_nonce' ),
        ]
    );
}
```

In JS:

```js
fetch( myPluginAdmin.ajaxUrl, {
    method: 'POST',
    body: new URLSearchParams({ action: 'my_action', nonce: myPluginAdmin.nonce }),
} );
```

- Upstream reference: `https://github.com/WPBoilerplate/wordpress-plugin-boilerplate/blob/main/admin/Main.php`
