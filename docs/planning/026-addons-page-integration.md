# Planning: Add-ons Page Integration (Feature 026)

Integrate `wpboilerplate/addons-page` into the plugin so that an "Add-ons" submenu appears under the
plugin's top-level admin menu. The package handles free installs, Freemius paid checkout, and opt-in
flow automatically — no custom rendering is required.

The local clone of the package lives at:
`/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/wpb-addons-page/`

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "026-addons-page-integration"

# 2. Specify
/speckit.specify "Integrate wpboilerplate/addons-page into the acrossai-abilities-manager plugin.
Wire in the package using a local path repository entry in composer.json.
The package's namespace is WPBoilerplate\\AddonsPage\\AddonsPage.
The plugin menu slug is acrossai-abilities-manager.
Instantiate AddonsPage inside define_admin_hooks() in includes/Main.php.
Also append the readme-template.txt sections (Installation, External Services, Privacy Policy)
from the package to README.txt."
```

---

## Scope

### In scope

- `composer.json` — add path repository and require entry for the local clone.
- `includes/Main.php` — instantiate `AddonsPage` inside `define_admin_hooks()`.
- `README.txt` — append the three required WordPress.org sections from the package template.

### Out of scope

- No custom Add-ons page UI — the package renders everything.
- No Freemius SDK configuration beyond what the package provides.
- No changes to existing admin menus, hooks, or REST endpoints.

---

## Background — Pre-flight Checks

| Item | Current state | Action |
|------|--------------|--------|
| `automattic/jetpack-autoloader` version | `^5.0` in `composer.json` | No change — already correct |
| `allow-plugins.automattic/jetpack-autoloader` | `true` in `composer.json` | No change — already present |
| Autoloader bootstrap in `Main.php` | `vendor/autoload_packages.php` (line 207) | No change — already correct |
| Local package clone path | `/Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/wpb-addons-page/` | Use as path repository |
| Plugin menu slug | `acrossai-abilities-manager` (Menu.php line 63) | Use as first argument to `AddonsPage` |
| `README.txt` | Present in plugin root | Append template sections |

---

## CHANGE-1 — `composer.json` Path Repository and Require Entry

**File**: `composer.json`

Add a `repositories` entry pointing at the local clone, then add the package to `require`.

### Target shape

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../../../wpb-addons-page"
        }
    ],
    "require": {
        "php": ">=7.4",
        "automattic/jetpack-autoloader": "^5.0",
        "wpboilerplate/wpb-access-control": "^1.1.1",
        "berlindb/core": "^2.0",
        "wpboilerplate/wpb-mcp-servers-list": "^0.0.1",
        "wpboilerplate/addons-page": "@dev"
    }
}
```

The relative `url` path resolves from the plugin directory to the local clone. Verify the path is
correct before running `composer update`.

After editing, run:

```bash
composer update wpboilerplate/addons-page
```

---

## CHANGE-2 — Instantiate `AddonsPage` in `define_admin_hooks()`

**File**: `includes/Main.php`

Locate `define_admin_hooks()` (currently ends after the `$abilities_rest` registration on
approximately line 285). Add the `AddonsPage` instantiation immediately after the existing
`$settings_menu` block and before the BerlinDB/abilities lines, grouped with the other admin-menu
registrations.

### Target addition

```php
// Add-ons submenu page (Feature 026).
new \WPBoilerplate\AddonsPage\AddonsPage(
    'acrossai-abilities-manager',
    ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE
);
```

`ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE` is defined in the main plugin file as `__FILE__` — it is
the correct constant to pass as the second argument. Do not use `__FILE__` inside `Main.php`.

The constructor registers the submenu, enqueues assets, and wires AJAX/Freemius hooks automatically.
No additional loader hook calls are needed.

### Placement context

Insert after this block in `define_admin_hooks()`:

```php
// Settings submenu page (Feature 019).
$settings_menu = \AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu::instance();
$this->loader->add_action( 'admin_menu', $settings_menu, 'register_submenu' );
$this->loader->add_action( 'admin_init', $settings_menu, 'register_settings' );
```

---

## CHANGE-3 — `README.txt` WordPress.org Sections

**File**: `README.txt`

Append the following three sections verbatim from the package template at:

```
vendor/wpboilerplate/addons-page/docs/readme-template.txt
```

Sections to append:

- `== Installation ==`
- `== External Services ==`
- `== Privacy Policy ==`

These sections are required by WordPress.org plugin guidelines whenever the plugin connects to an
external service (Freemius checkout and opt-in).

Read the template file after running `composer update` and copy the sections exactly.

---

## Expected Files Changed

```text
composer.json
includes/Main.php
README.txt
```

---

## Validation Checklist

### Composer

- [ ] `composer.json` contains a `repositories` entry with `"type": "path"` pointing at the local clone.
- [ ] `wpboilerplate/addons-page` is in the `require` block with `"@dev"`.
- [ ] `composer update wpboilerplate/addons-page` completes without error.
- [ ] `vendor/wpboilerplate/addons-page/` directory exists after update.
- [ ] `vendor/autoload_packages.php` is regenerated and loads the new package.

### Admin UI

- [ ] An "Add-ons" submenu appears under the "Abilities Manager" top-level menu in wp-admin.
- [ ] No PHP fatal errors or warnings on admin pages after the change.
- [ ] Existing submenus (Logs, Settings) still appear and function correctly.

### README.txt

- [ ] `README.txt` contains `== Installation ==`, `== External Services ==`, and `== Privacy Policy ==` sections.

### Quality gates

- [ ] `composer run phpstan` passes.
- [ ] Run PHPCS on changed production PHP files; no new errors introduced.

---

## Spec-kit Commands

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer update wpboilerplate/addons-page
composer run phpstan
composer run phpcs

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```
