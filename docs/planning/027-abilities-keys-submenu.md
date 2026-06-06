# Planning: Abilities Keys Submenu — Filter-Based Hierarchical Registry (Feature 027)

Add an "Abilities" admin submenu under the existing `acrossai-abilities-manager` parent menu
that lets the site admin enable/disable abilities by a **main_key -> sub_key** hierarchy.
Each main_key card shows a master toggle, an `All` / `Specific` mode selector, and the list
of sub_key checkboxes (only consulted in `Specific` mode).

Ability add-ons register ability definitions through one filter:

```php
acrossai_abilities_api_init
```

Each definition uses the **same array shape passed to `wp_register_ability()`**, with only
four additional manager fields:

```php
'main_key'       => 'content',
'main_key_label' => __( 'Content', 'my-addon' ),
'sub_key'        => 'summarize',
'sub_key_label'  => __( 'Summarize', 'my-addon' ),
```

The manager plugin owns the admin UI, REST endpoints, option storage, definition validation,
and the enable/disable gate. Add-ons do **not** implement this plugin's PHP interfaces and do
**not** depend on this plugin namespace. This keeps the integration friendly for many add-ons
and many abilities.

The main goal is strict:

> If the key is disabled, the ability must not be registered. Disabled abilities never enter
> the WordPress Abilities registry.

The admin UI uses `@wordpress/dataviews` for the grid/list and DataForm-compatible controls
from WordPress packages for editable state. Saves are debounced through a dedicated REST API
namespace: `acrossai-abilities/v1`.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit-git-feature "027-abilities-keys-submenu"

# 2. Specify
/speckit-specify "Add an Abilities admin submenu under the acrossai-abilities-manager parent
that renders a DataViews grid of main_key cards. Each card has a master toggle (ON/OFF),
an All/Specific mode radio, and per-sub_key checkboxes (only used when mode='Specific').

Introduce a filter-based ability definition registry. Add-ons register definitions via
'acrossai_abilities_api_init'. The filtered array is keyed by ability name, and each value
matches wp_register_ability() args plus four manager fields: main_key, main_key_label,
sub_key, sub_key_label.

The manager validates definitions, builds the admin hierarchy from main_key/sub_key, stores
only small enable/disable config in option 'acrossai_ability_keys_config' with autoload=false,
and calls wp_register_ability() only when the saved config allows the main_key/sub_key.

Add-ons remain responsible for registering their own ability categories on
wp_abilities_api_categories_init. Abilities are registered by this manager on
wp_abilities_api_init after filtering disabled keys.

Admin UI uses @wordpress/dataviews ^14.2.0 (already installed), debounced auto-save via
apiFetch to a dedicated REST controller at acrossai-abilities/v1/abilities/config."
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | `@wordpress/dataviews ^14.2.0` is already declared in `package.json`; no `npm install` is required | `grep dataviews package.json` |
| B-2 | New JS/CSS entries are explicit in `webpack.config.js`; this repo does **not** auto-discover `src/js/keys/index.js` | read `webpack.config.js` |
| B-3 | DataViews reference pattern lives at `src/js/components/LogsTable.js` | read `src/js/components/LogsTable.js` |
| B-4 | Parent admin menu slug `acrossai-abilities-manager` is registered in `admin/Partials/Menu.php:63` | read `admin/Partials/Menu.php` |
| B-5 | Existing `AcrossAI_Abilities_Processor::register_abilities()` registers DB-backed abilities; it does **not** contain a legacy callback-row loop | read `includes/Modules/Abilities/AcrossAI_Abilities_Processor.php` |
| B-6 | Existing manager REST namespace is `acrossai-abilities-manager/v1`; Feature 027 intentionally adds a separate namespace `acrossai-abilities/v1` | read `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Rest_Controller.php` |
| B-7 | Singleton + Loader pattern for admin partials is established (`admin/Partials/LogsMenu.php`, `admin/Partials/SettingsMenu.php`) | read both files |
| B-8 | `ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE`, `ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH`, and `ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL` are available constants | read `includes/Main.php::define_constants()` |
| B-9 | Constitution v1.4.4 forbids abstract module base classes and `includes/Base/`; hooks must be wired in `includes/Main.php` via the Loader | read `.specify/memory/CONSTITUTION.md` |
| B-10 | Admin assets must be enqueued only from `admin/Main.php`, gated by hook suffix | read `.agents/skills/wp-plugin-development/references/admin.md` |
| B-11 | Options not needed on every request should use `autoload=false` | read `.agents/skills/wp-plugin-development/SKILL.md` settings section |
| B-12 | Categories must be registered on `wp_abilities_api_categories_init`; abilities on `wp_abilities_api_init` | read `wp-includes/abilities-api.php` |

---

## Architecture Choice — Filter-Based Definitions (Option C)

Use **Option C: filter-based definitions** as the approved approach.

Why:

- Best multi-add-on friendliness: add-ons only depend on a hook name and array schema.
- No hard dependency on this plugin's PHP namespace.
- No shared Composer package is required for v1.
- No abstract base class, no trait, no interface, no `includes/Base/`.
- Ability definitions can be copied from normal `wp_register_ability()` args with only four
  extra manager fields.
- It scales better than instantiating many per-ability classes when an install has hundreds or
  thousands of abilities.

Rejected alternatives:

| Option | Reason not selected |
|---|---|
| Interface + Trait | Good developer experience, but add-ons hard-depend on this plugin namespace unless the contract ships in a shared Composer package. |
| Abstract class | Same coupling as Interface + Trait and conflicts with the repository's no-base-class direction. |

Future compatibility:

- A shared Composer SDK may later provide helper builders or typed wrappers.
- The manager should still consume the filter as the stable integration contract.

---

## Module and Namespace

Create a new module:

```text
includes/Modules/AbilityKeys/
```

Use this namespace:

```php
AcrossAI_Abilities_Manager\Includes\Modules\AbilityKeys
```

Recommended class names:

| Concern | Class |
|---|---|
| Definition collection/normalization | `AcrossAI_Ability_Keys_Definitions` |
| Saved config and enable predicate | `AcrossAI_Ability_Keys_Config` |
| Runtime ability registration | `AcrossAI_Ability_Keys_Registrar` |
| REST namespace orchestrator | `Rest\AcrossAI_Ability_Keys_Rest_Controller` |
| REST config route | `Rest\AcrossAI_Ability_Keys_Config_Controller` |

Admin/UI stays outside the module:

```text
admin/Partials/AbilitiesKeysMenu.php
src/js/keys/
src/scss/keys/
```

---

## Public Add-on Contract

### Filter name

```php
acrossai_abilities_api_init
```

### Definition shape

The filtered array MUST be keyed by ability name. Each value MUST be normal
`wp_register_ability()` args plus four manager fields:

```php
add_filter(
	'acrossai_abilities_api_init',
	function ( array $abilities ): array {
		$abilities['my-addon/summarize'] = array(
			'main_key'       => 'content',
			'main_key_label' => __( 'Content', 'my-addon' ),
			'sub_key'        => 'summarize',
			'sub_key_label'  => __( 'Summarize', 'my-addon' ),

			'label'               => __( 'Summarize Content', 'my-addon' ),
			'description'         => __( 'Summarizes provided content.', 'my-addon' ),
			'category'            => 'my-addon',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'content' => array(
						'type'        => 'string',
						'description' => __( 'Content to summarize.', 'my-addon' ),
					),
				),
				'required'             => array( 'content' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'properties'           => array(
					'summary' => array(
						'type'        => 'string',
						'description' => __( 'Generated summary.', 'my-addon' ),
					),
				),
				'required'             => array( 'summary' ),
				'additionalProperties' => false,
			),
			'execute_callback'    => 'my_addon_summarize',
			'permission_callback' => 'my_addon_can_summarize',
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		);

		return $abilities;
	}
);
```

### Category registration

Add-ons remain responsible for registering their own ability categories:

```php
add_action(
	'wp_abilities_api_categories_init',
	function (): void {
		wp_register_ability_category(
			'my-addon',
			array(
				'label'       => __( 'My Addon', 'my-addon' ),
				'description' => __( 'Abilities provided by My Addon.', 'my-addon' ),
			)
		);
	}
);
```

Rationale:

- The user requested only four extra fields in the ability definition.
- `wp_register_ability()` requires categories to exist before ability registration.
- Category registration is naturally owned by the add-on that owns the category labels.

### Optional advanced core ability class

Core supports an optional `ability_class` arg. Add-ons may include it in the normal
`wp_register_ability()` args if they need custom runtime behavior:

```php
'ability_class' => My_Custom_WP_Ability::class,
```

The class must extend `WP_Ability`. The manager does not extend core registries and does not
require add-ons to extend `WP_Ability`.

---

## Data Model

### Definition registry

`AcrossAI_Ability_Keys_Definitions` owns:

- applying `acrossai_abilities_api_init` once per request,
- validating each definition,
- normalizing keys/labels,
- de-duplicating by ability name,
- returning UI hierarchy data,
- returning registration-ready ability args.

Class shape:

```php
namespace AcrossAI_Abilities_Manager\Includes\Modules\AbilityKeys;

class AcrossAI_Ability_Keys_Definitions {
	const FILTER_NAME = 'acrossai_abilities_api_init';

	protected static $instance = null;

	public static function instance(): self;
	private function __construct() {}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function get_definitions(): array;

	/**
	 * @return array<string,array{label:string,subs:array<string,array{label:string,count:int}>}>
	 */
	public function get_keys(): array;

	/**
	 * @return array<string,mixed>
	 */
	public function get_registration_args( string $ability_name ): array;
}
```

Validation rules:

- Ability name array key must match WordPress naming rules: `namespace/ability-name`.
- `main_key`, `sub_key` must be sanitized with `sanitize_key()`.
- `main_key_label`, `sub_key_label`, `label`, and `description` must be strings.
- `category` must exist in the definition and must be a string.
- `execute_callback` and `permission_callback` must be callable.
- `input_schema`, `output_schema`, `meta`, and `ability_class` follow core `wp_register_ability()` rules.
- Unknown manager fields are ignored; unknown normal ability args are left for core validation.
- Invalid definitions are skipped fail-closed and do not block other add-ons.

Performance rules:

- Apply the filter once per request and cache the normalized result in a private property.
- Add-on filter callbacks must return cheap/static arrays only.
- Add-ons must lazy-load expensive services in `execute_callback`, not while building definitions.
- 300-1000 definitions are acceptable when definitions are cheap arrays and the UI is paginated/lazy.

### Saved config option

Option key:

```php
acrossai_ability_keys_config
```

Option shape:

```php
array(
	'content' => array(
		'enabled' => true,
		'mode'    => 'all',                    // 'all' | 'specific'
		'subs'    => array( 'summarize' => true ),
	),
	'media' => array(
		'enabled' => false,
	),
)
```

Storage rules:

- Store only enable/disable config, never full ability definitions.
- Save with `autoload=false`.
- Missing main_key defaults to `enabled=true`, `mode='all'`.
- Missing sub_key in `specific` mode defaults to disabled unless explicitly true.
- Keep stale config entries when an add-on is deactivated so reactivation restores prior state.

`AcrossAI_Ability_Keys_Config` owns:

```php
namespace AcrossAI_Abilities_Manager\Includes\Modules\AbilityKeys;

class AcrossAI_Ability_Keys_Config {
	const OPTION_KEY = 'acrossai_ability_keys_config';

	protected static $instance = null;

	public static function instance(): self;
	private function __construct() {}

	public function get_config(): array;
	public function update_config( array $config ): bool;
	public function is_enabled( string $main_key, string $sub_key ): bool;
}
```

Registration rule:

1. `config[main_key].enabled === false` -> skip.
2. `config[main_key].mode === 'all'` or missing -> register.
3. `config[main_key].mode === 'specific'` and `config[main_key].subs[sub_key] === true` -> register.
4. Otherwise -> skip.

---

## Implementation Changes

### CHANGE-1 — NEW `includes/Modules/AbilityKeys/AcrossAI_Ability_Keys_Definitions.php`

Create the singleton definition service described above.

Key rules:

- No WordPress hook registration inside the class.
- Calls `apply_filters( 'acrossai_abilities_api_init', array() )` exactly once per request.
- Caches normalized definitions in a property.
- Provides `get_keys()` for the admin UI.
- Provides `get_registration_args()` that strips only:
  - `main_key`
  - `main_key_label`
  - `sub_key`
  - `sub_key_label`
- Does not store definitions in the database.

### CHANGE-2 — NEW `includes/Modules/AbilityKeys/AcrossAI_Ability_Keys_Config.php`

Create the singleton config service described above.

Key rules:

- Uses `get_option( self::OPTION_KEY, array() )`.
- Uses `update_option( self::OPTION_KEY, $config, false )` so the option is not autoloaded.
- Sanitizes and validates config against `AcrossAI_Ability_Keys_Definitions::get_keys()`.
- Drops unknown submitted keys while preserving already-stored stale keys from deactivated add-ons.
- No hook calls inside this class.

### CHANGE-3 — NEW `includes/Modules/AbilityKeys/AcrossAI_Ability_Keys_Registrar.php`

Registers enabled filter-provided abilities at runtime.

Class shape:

```php
namespace AcrossAI_Abilities_Manager\Includes\Modules\AbilityKeys;

class AcrossAI_Ability_Keys_Registrar {
	protected static $instance = null;

	public static function instance(): self;
	private function __construct() {}

	public function register_enabled_abilities(): void;
}
```

Pattern:

```php
public function register_enabled_abilities(): void {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$definitions = AcrossAI_Ability_Keys_Definitions::instance();
	$config      = AcrossAI_Ability_Keys_Config::instance();

	foreach ( $definitions->get_definitions() as $ability_name => $definition ) {
		$main_key = (string) $definition['main_key'];
		$sub_key  = (string) $definition['sub_key'];

		if ( ! $config->is_enabled( $main_key, $sub_key ) ) {
			continue;
		}

		wp_register_ability( $ability_name, $definitions->get_registration_args( $ability_name ) );
	}
}
```

Key rules:

- Hooked to `wp_abilities_api_init` from `includes/Main.php`.
- Does not register disabled abilities.
- Does not modify `AcrossAI_Abilities_Processor`; DB-backed abilities remain owned by the existing Abilities module.
- Does not touch `acrossai_abilities_registered_callbacks`.

### CHANGE-4 — NEW `includes/Modules/AbilityKeys/Rest/AcrossAI_Ability_Keys_Rest_Controller.php`

Dedicated REST orchestrator for the separate namespace:

```php
const REST_NAMESPACE = 'acrossai-abilities/v1';
```

Responsibilities:

- Own `REST_NAMESPACE`.
- Delegate route registration to `AcrossAI_Ability_Keys_Config_Controller`.
- Own shared `check_permission()` requiring `manage_options` and a valid REST nonce.

### CHANGE-5 — NEW `includes/Modules/AbilityKeys/Rest/AcrossAI_Ability_Keys_Config_Controller.php`

REST controller for config reads/writes.

Routes:

| Method | Route | Returns / Accepts | Permission |
|--------|-------|-------------------|------------|
| `GET` | `/abilities/config` | `{ keys: <get_keys()>, config: <get_config()> }` | `manage_options` + REST nonce |
| `POST` | `/abilities/config` | Accepts `{ config: { main_key: { enabled, mode, subs } } }`; returns updated config | `manage_options` + REST nonce |

Key rules:

- Use `AcrossAI_Ability_Keys_Rest_Controller::REST_NAMESPACE`.
- Register request args/schema with sanitize/validate callbacks.
- Delegate save validation to `AcrossAI_Ability_Keys_Config::update_config()`.
- Return `WP_Error` with appropriate HTTP status on malformed payload.
- Do not expose secrets; this feature stores ability toggle config only.

### CHANGE-6 — NEW `admin/Partials/AbilitiesKeysMenu.php`

Admin partial that registers the submenu and renders the React root container.

Class shape:

```php
namespace AcrossAI_Abilities_Manager\Admin\Partials;

class AbilitiesKeysMenu {
	const MENU_SLUG = 'acrossai-abilities-keys';

	protected static $instance = null;
	private $hook_suffix = '';

	public static function instance(): self;
	private function __construct() {}

	public function register_submenu(): void {
		$this->hook_suffix = add_submenu_page(
			'acrossai-abilities-manager',
			__( 'Abilities', 'acrossai-abilities-manager' ),
			__( 'Abilities', 'acrossai-abilities-manager' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'acrossai-abilities-manager' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Abilities', 'acrossai-abilities-manager' ) . '</h1><div id="acrossai-abilities-keys-root"></div></div>';
	}

	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}
}
```

Key rules:

- No `add_action()` / `add_filter()` calls inside the class.
- Stores the return value of `add_submenu_page()` for asset gating.
- Does not enqueue assets.

### CHANGE-7 — MODIFY `includes/Main.php`

Inside `define_admin_hooks()`, after existing submenu registrations:

```php
$keys_menu = \AcrossAI_Abilities_Manager\Admin\Partials\AbilitiesKeysMenu::instance();
$this->loader->add_action( 'admin_menu', $keys_menu, 'register_submenu' );
```

Inside REST registration area:

```php
$ability_keys_rest = \AcrossAI_Abilities_Manager\Includes\Modules\AbilityKeys\Rest\AcrossAI_Ability_Keys_Rest_Controller::instance();
$this->loader->add_action( 'rest_api_init', $ability_keys_rest, 'register_routes' );
```

Inside public hooks with other Abilities runtime registration:

```php
$ability_keys_registrar = \AcrossAI_Abilities_Manager\Includes\Modules\AbilityKeys\AcrossAI_Ability_Keys_Registrar::instance();
$this->loader->add_action( 'wp_abilities_api_init', $ability_keys_registrar, 'register_enabled_abilities', 10 );
```

Key rules:

- All hooks go through `$this->loader->add_action()`.
- Resolve each singleton into a named variable before Loader calls.
- Do not call `add_action()` directly in module classes.

### CHANGE-8 — MODIFY `admin/Main.php`

Add `keys` asset manifests and page gates.

Key rules:

- Load asset manifests in constructor with `file_exists()` guards, matching the existing optional feature asset pattern.
- Use `ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH`, not `ACROSSAI_ABILITIES_MANAGER_PLUGIN_DIR`.
- Gate with `$hook_suffix === AbilitiesKeysMenu::instance()->get_hook_suffix()`.
- Enqueue `build/js/keys.js` and `build/css/keys.css` only on the keys page.
- Enqueue `wp-components` styles for WordPress controls.
- Pass REST root/namespace/nonce to JS with `wp_add_inline_script()` or existing apiFetch nonce setup.

### CHANGE-9 — MODIFY `webpack.config.js`

Add explicit entries:

```js
'js/keys': path.resolve( process.cwd(), 'src/js/keys', 'index.js' ),
'css/keys': path.resolve( process.cwd(), 'src/scss/keys', 'admin.scss' ),
```

Key rules:

- This repo does not auto-discover `src/js/keys/index.js`.
- `npm run build` must emit:
  - `build/js/keys.js`
  - `build/js/keys.asset.php`
  - `build/css/keys.css`
  - `build/css/keys.asset.php`

### CHANGE-10 — NEW `src/js/keys/index.js`

React entry point that mounts the app on the partial root container.

```js
import { createRoot } from '@wordpress/element';
import { AbilitiesKeysApp } from './AbilitiesKeysApp';

const root = document.getElementById( 'acrossai-abilities-keys-root' );

if ( root ) {
	createRoot( root ).render( <AbilitiesKeysApp /> );
}
```

### CHANGE-11 — NEW `src/js/keys/AbilitiesKeysApp.js`

Top-level React component.

Key rules:

- Loads `{ keys, config }` from `/acrossai-abilities/v1/abilities/config`.
- Uses DataViews for the grouped list/grid.
- Uses WordPress controls for editable state.
- Supports search/pagination/lazy rendering so 300-1000 definitions do not render as one expanded page.
- Suppresses auto-save after the initial GET load.
- Debounces saves after user changes.
- Preserves `subs` selections across mode switches.
- Uses only `@wordpress/*` packages already in the project.
- All strings use `__()` with text domain `acrossai-abilities-manager`.

Initial-load save guard pattern:

```js
const didLoad = useRef( false );

useEffect( () => {
	apiFetch( { path: '/acrossai-abilities/v1/abilities/config' } ).then( ( res ) => {
		setKeys( res.keys );
		setConfig( res.config );
		didLoad.current = true;
	} );
}, [] );

useEffect( () => {
	if ( ! didLoad.current || ! config ) {
		return;
	}

	const timer = setTimeout( () => {
		saveConfig( config );
	}, 500 );

	return () => clearTimeout( timer );
}, [ config ] );
```

### CHANGE-12 — NEW `src/js/keys/save-config.js`

Small helper wrapping the REST POST.

```js
import apiFetch from '@wordpress/api-fetch';

export function saveConfig( config ) {
	return apiFetch( {
		path: '/acrossai-abilities/v1/abilities/config',
		method: 'POST',
		data: { config },
	} );
}
```

### CHANGE-13 — NEW `src/scss/keys/admin.scss`

Feature-specific styles for the keys grid/cards.

Key rules:

- Keep styles scoped to the keys page root class.
- Do not duplicate WordPress component styling that `wp-components` already provides.

---

## What must NOT change

- Do **not** introduce an abstract base class or `includes/Base/` directory.
- Do **not** introduce a required plugin-owned interface/trait for add-ons.
- Do **not** require add-ons to depend on this plugin's PHP namespace.
- Do **not** store full ability definitions in the database.
- Do **not** store API secrets/credentials in this feature; this stores ability toggle config only.
- Do **not** call `wp_register_ability()` for disabled keys.
- Do **not** modify the existing DB-backed `AcrossAI_Abilities_Processor` registration flow unless a direct integration bug is found during implementation.
- Do **not** modify or rename `acrossai_abilities_registered_callbacks`.
- Do **not** change the existing manager REST namespace `acrossai-abilities-manager/v1`.
- Do **not** change the new dedicated namespace `acrossai-abilities/v1` without a documented decision.
- Do **not** enqueue assets from Partials or module classes.
- Do **not** add a hard dependency on a new Composer or npm package.

---

## Constraints

- New module folder: `includes/Modules/AbilityKeys/`.
- Admin page class stays in `admin/Partials/`.
- Asset enqueue stays in `admin/Main.php`.
- Hook wiring stays in `includes/Main.php`.
- Config option uses `autoload=false`.
- Definitions are request-cached and not persisted.
- `composer dump-autoload` must be run once after new PHP files exist.
- `npm run build` must produce keys JS/CSS bundles and manifests.
- PHPCS, PHPStan, and Plugin Check must remain clean for changed production files.

Expected file changes:

- NEW `includes/Modules/AbilityKeys/AcrossAI_Ability_Keys_Definitions.php`
- NEW `includes/Modules/AbilityKeys/AcrossAI_Ability_Keys_Config.php`
- NEW `includes/Modules/AbilityKeys/AcrossAI_Ability_Keys_Registrar.php`
- NEW `includes/Modules/AbilityKeys/Rest/AcrossAI_Ability_Keys_Rest_Controller.php`
- NEW `includes/Modules/AbilityKeys/Rest/AcrossAI_Ability_Keys_Config_Controller.php`
- NEW `admin/Partials/AbilitiesKeysMenu.php`
- NEW `src/js/keys/index.js`
- NEW `src/js/keys/AbilitiesKeysApp.js`
- NEW `src/js/keys/save-config.js`
- NEW `src/scss/keys/admin.scss`
- MOD `includes/Main.php`
- MOD `admin/Main.php`
- MOD `webpack.config.js`

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
composer dump-autoload
composer run phpcs
composer run phpstan
npm run build

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

### Filter contract

- [ ] Add-on can register an ability through `acrossai_abilities_api_init`.
- [ ] Definition value matches `wp_register_ability()` args plus only the four manager fields.
- [ ] Invalid definitions are skipped and do not break valid definitions from other add-ons.
- [ ] Duplicate ability names are handled deterministically and documented.

### Config and hierarchy

- [ ] `get_keys()` groups multiple abilities under the same `main_key`.
- [ ] `get_keys()` groups multiple `sub_key` values under one card.
- [ ] `is_enabled( 'unknown_main', 'unknown_sub' )` returns true.
- [ ] Disabled main_key skips all sub_keys.
- [ ] Specific mode registers only checked sub_keys.
- [ ] Config option is saved with `autoload=false`.
- [ ] Full ability definitions are never stored in the option.

### Runtime registration

- [ ] Add-on categories register on `wp_abilities_api_categories_init`.
- [ ] Enabled definitions register on `wp_abilities_api_init`.
- [ ] Disabled definitions are absent from `wp_get_abilities()`.
- [ ] DB-backed abilities from the existing Abilities module still register as before.
- [ ] Core `ability_class` arg still passes through when supplied and valid.

### REST controller

- [ ] `GET /wp-json/acrossai-abilities/v1/abilities/config` returns `{ keys, config }` for `manage_options`.
- [ ] Same GET returns `401` / `403` for unauthorized users.
- [ ] `POST` with valid payload updates the option and returns updated config.
- [ ] `POST` with malformed payload returns `WP_Error` with appropriate HTTP status.
- [ ] REST nonce is required for browser requests.

### Admin partial and assets

- [ ] `wp-admin -> Abilities Manager -> Abilities` renders without PHP error.
- [ ] Page source contains `<div id="acrossai-abilities-keys-root"></div>`.
- [ ] Submenu does not appear for users without `manage_options`.
- [ ] On the keys page, network tab shows `build/js/keys.js` and `build/css/keys.css`.
- [ ] On unrelated admin pages, keys assets are not loaded.

### React app

- [ ] Initial GET does not trigger an immediate POST.
- [ ] Flipping a control triggers one debounced POST after idle.
- [ ] Refresh restores saved state.
- [ ] Switching `Specific -> All -> Specific` preserves prior sub_key selections.
- [ ] UI remains usable with 300 definitions.
- [ ] UI remains usable with 1000 cheap/static definitions.
- [ ] All strings use `__()` with text domain `acrossai-abilities-manager`.

### Cross-plugin sanity

- [ ] Add one test add-on with `main_key=content`, `sub_key=summarize`; card appears.
- [ ] Add second test add-on with same `main_key=content`, different `sub_key=translate`; same card has two sub entries.
- [ ] Add third test add-on with `main_key=media`; second card appears.
- [ ] Disable `content`; both content abilities are absent from `wp_get_abilities()`.
- [ ] Reactivate a deactivated add-on; previous saved config is preserved.

### Quality gates

- [ ] `composer dump-autoload` succeeds.
- [ ] `composer run phpstan` succeeds.
- [ ] `composer run phpcs` succeeds for changed PHP files.
- [ ] `npm run build` emits keys bundles and succeeds.
- [ ] Plugin Check remains green.
- [ ] `/speckit.architecture-guard.architecture-review` reports no Constitution violations.
