# Memory Synthesis

## Current Scope

Feature 023 bundles a full rebrand from WPBoilerplate → AcrossWP (10 PHP files + composer.json + README.txt), an uninstall-gate behavior fix, a Logger query variable-naming cleanup (spread operator), deletion of the `plugin-check.yml` workflow, and a permanent namespace rename: `AcrossAI_Abilities_Manager\Public` → `AcrossAI_Abilities_Manager\Front`.

## Why This Exists

The user made several manual changes directly on `main` (rebrand, uninstall fix, logger refactor, plugin-check.yml deletion) that were never captured in a spec. This feature also fixes the root cause of the `--ignore=public/Main.php` workaround added in 022: `public` is a PHP reserved keyword since PHP 5.0 and cannot be used as a namespace segment.

## Completed (committed on branch 023-fix-public-namespace-reserved-keyword, PR #29)

### Rebrand: WPBoilerplate → AcrossWP
| File | Change |
|---|---|
| `acrossai-abilities-manager.php` | `@link`, Plugin URI, Description, Author, Author URI |
| `admin/Main.php` | `@link`, `@author` |
| `includes/Main.php` | `@link`, `@author`, `define()` param names (`$acrossai_name`, `$acrossai_value`) |
| `includes/AcrossAI_Activator.php` | `@author` |
| `includes/AcrossAI_Deactivator.php` | `@link`, `@author` |
| `includes/AcrossAI_Loader.php` | `@link`, `@author` |
| `public/Main.php` | `@link`, `@author`, namespace `\Public` → `\Front` |
| `public/Partials/display.php` | `@link` |
| `README.txt` | Donate link |
| `composer.json` | `support.issues` URL, autoload PSR-4 key `\Public` → `\Front` |

### Uninstall gate fix
`uninstall.php`: `delete_option` calls moved inside the `$acrossai_delete_data` gate — options are now preserved by default on uninstall, deleted only when user opts in.

### Logger query cleanup
`includes/Modules/Logger/AcrossAI_Logger_Query.php`:
- `$count_values` → `$count_params`
- `$final_values` → `$select_params`
- `$wpdb->prepare($sql, $array)` → `$wpdb->prepare($sql, ...$params)` (spread operator)

### Namespace fix
- `public/Main.php:12` — `namespace AcrossAI_Abilities_Manager\Public;` → `namespace AcrossAI_Abilities_Manager\Front;`
- `includes/Main.php:297` — `new \AcrossAI_Abilities_Manager\Public\Main(` → `new \AcrossAI_Abilities_Manager\Front\Main(`
- `composer.json` autoload PSR-4 key renamed
- `.github/workflows/phpcompat.yml` — `--ignore=public/Main.php` removed
- `composer dump-autoload` regenerated

### Workflow deletion
`.github/workflows/plugin-check.yml` — deleted entirely.

## What Does NOT Change

- No REST endpoints, DB schema, admin menus, or hooks
- No other namespace changes
- `public/Partials/display.php` is not namespaced — no change needed
