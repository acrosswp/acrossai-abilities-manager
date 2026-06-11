# Implementation Plan: Library Category/Slug Rebrand (Feature 031)

**Branch**: `031-library-category-slug-rebrand` | **Date**: 2026-06-11 | **Spec**: TBD

---

## Summary

Rename the Library module's two-level grouping scheme from `main_key` / `sub_key` to `category` / `slug` across PHP (abstract base class, Registry, Config, Processor, REST payloads) and JavaScript (LibraryPage, LibraryCard, api docblocks). Concurrently, update the Library admin page to display each ability's existing `name` field (the human-readable ability name already on every definition) as the visible identifier instead of the raw key strings. The persisted site option (`acrossai_library_config`) continues to use the same shape (top-level keys + `sub_keys` map), so saved configs remain compatible while the in-memory field names are clearer and align with the broader plugin vocabulary (the Abilities module already uses `category` and `slug`). Before: cards expose `main_key_label` and `sub_key_label` strings; after: cards expose category/slug grouping with each row labeled by the registered ability `name`.

---

## Technical Context

- **Language/Version**: PHP 7.4+ (plugin minimum), JavaScript ES2020 via `@wordpress/scripts`.
- **Primary Dependencies**: `@wordpress/components`, `@wordpress/element`, `@wordpress/i18n`, WordPress Abilities API (`wp_register_ability`), WordPress REST API.
- **Storage**: No DB schema changes. `get_site_option('acrossai_library_config')` continues to store the same sparse associative array structure (top-level keys map to `{enabled, mode, sub_keys}`). The persisted top-level keys were previously called `main_key` and the inner map was `sub_keys` — both names stay the same on disk; only the runtime variable / field naming in the definition payloads change.
- **Testing**: PHPStan static analysis, existing PHPUnit suites if present under `tests/`, manual browser smoke test of the Library admin page.
- **Target Platform**: WordPress 6.6+ multisite-compatible admin (uses `get_site_option` / `update_site_option`).
- **Scale/Scope**: 6 PHP files (~729 LOC) under `includes/Modules/Library/`, 3 JS files under `src/js/ability-library/`. Zero known concrete subclasses of `Ability_Definition` inside this plugin (verified by codebase search), so internal blast radius is contained.
- **Performance Goals**: No performance impact — purely a rename + display source swap. Registry collection, processor gating, and REST endpoints retain their existing wiring (init P99 collection, `wp_abilities_api_init` P5 registration, debounced auto-save).

---

## Architecture Decisions & Constraints

- **Namespace/FQCN unchanged**: Every PHP class keeps its namespace `AcrossAI_Abilities_Manager\Includes\Modules\Library` and current class name (`Ability_Definition`, `AcrossAI_Ability_Library_Registry`, `AcrossAI_Ability_Library_Config`, `AcrossAI_Ability_Library_Processor`, REST controllers). This avoids autoloader churn and preserves the Boot Flow Rule wiring in `includes/Main.php`.
- **Registry schema fields renamed**: `REQUIRED_FIELDS` in `AcrossAI_Ability_Library_Registry` changes from `['main_key','main_key_label','sub_key','sub_key_label','name','args']` to `['category','category_label','slug','slug_label','name','args']`. Note: `category` already appears inside the `ALLOWED_ARGS_FIELDS` allowlist as a `wp_register_ability()` arg — those two `category` values are at different array depths (top-level definition vs. `args` sub-array) and do not collide, but reviewers must verify the distinction is documented.
- **Config sanitizer key renamed in surface API only**: `AcrossAI_Ability_Library_Config::sanitize_entry()` keeps writing the same `sub_keys` array shape on disk (DEC-D6 sparse-storage rule preserved). The constant `MAX_SUB_KEYS` is retained as-is to avoid a public-API break; an alias constant `MAX_SLUGS` MAY be added for forward symmetry.
- **Processor logic key renamed**: `AcrossAI_Ability_Library_Processor::is_permitted()` reads `$definition['category']` and `$definition['slug']` instead of `$definition['main_key']` and `$definition['sub_key']`. The saved-config lookups still use `$config[$category]['sub_keys'][$slug]` — the on-disk shape is unchanged. (FR-013 through FR-017 semantics preserved.)
- **REST payload key renamed**: `/acrossai-abilities-library/v1/abilities/config` POST/GET bodies continue to use the same `{enabled, mode, sub_keys}` shape for each top-level entry. The route, namespace, and capability gate (`manage_options` + `wp_rest` nonce) are unchanged. Reference: `AcrossAI_Ability_Library_Rest_Controller::REST_NAMESPACE`.
- **JS component field references renamed**: `LibraryPage.js` `groupDefinitions()` destructures `category`, `category_label`, `slug`, `slug_label` (was `main_key`, `main_key_label`, `sub_key`, `sub_key_label`). `LibraryCard.js` consumes the renamed props and additionally renders each row's `name` (the per-ability `name` from the definition) as the human-visible row label, with `slug_label` retained as secondary/title-attr text where useful.
- **Abstract method names in `Ability_Definition` renamed**: `main_key()` → `category()`, `main_key_label()` → `category_label()`, `sub_key()` → `slug()`, `sub_key_label()` → `slug_label()`. Backwards-compatibility shims (default implementations on the base class that proxy from the old names if a subclass still defines them) are deferred — the codebase has **zero concrete subclasses** today (verified), so no compat layer is required inside this repo. External add-ons are addressed under Post-Implementation Notes.
- **AC referenced**: DEC-EXTERNAL-PACKAGE-HOOK-CTOR (no change — Library does not use that pattern), DEC-D6 (sparse storage rule — preserved verbatim), DEC-D9 (abstract `Ability_Definition` contract — being renamed in this feature).
- **BUG patterns referenced**: BUG-LIBRARY-HOOK-SUFFIX and BUG-WP-LOCALIZE-SCRIPT-RENDER remain mitigated — no changes to `admin/Partials/LibraryMenu.php` enqueue timing are required.

---

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| §I Modular Architecture | PASS | Changes confined to `includes/Modules/Library/` and `src/js/ability-library/`. No cross-module reach. |
| §II WordPress Coding Standards | PASS | Field renames follow `snake_case` convention (`category`, `slug`, `category_label`, `slug_label`). PHP sanitizer (`sanitize_key()` + max length) still applied via `AcrossAI_Ability_Library_Config::sanitize_key_field()`. |
| §IV Security | PASS | No new attack surface. Registry allowlist (`ALLOWED_ARGS_FIELDS`) preserved; required-field validation retains rejection-with-logging path. REST capability + nonce gating unchanged. |
| §V Integration Resilience / Extensibility | REVIEW | The `acrossai_abilities_api_init` filter contract changes its expected array keys. External add-ons that implement `Ability_Definition` directly (not via this plugin's source) WILL break until they rename their methods. Mitigation: documented in Post-Implementation Notes; in-repo subclass count = 0. |
| §VI DRY | PASS | The vocabulary aligns Library with the Abilities module (which already uses `category` and `slug` per `src/js/abilities/store/index.js`). Removes the parallel `main_key`/`sub_key` terminology. |

---

## Affected Files

### Changed files

| File path | Change type | What changes |
|---|---|---|
| `includes/Modules/Library/Ability_Definition.php` | Modify | Rename four abstract methods; update `push_definition()` to emit `category`/`category_label`/`slug`/`slug_label` keys. |
| `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php` | Modify | Update `REQUIRED_FIELDS`; rename normalized output keys in `validate_and_normalize()`; update inline docblock describing the filter contract. |
| `includes/Modules/Library/AcrossAI_Ability_Library_Config.php` | Modify | Update docblocks and parameter names to reference `category`/`slug`; keep on-disk option shape unchanged (`sub_keys` map retained). Optional alias constant `MAX_SLUGS = MAX_SUB_KEYS`. |
| `includes/Modules/Library/AcrossAI_Ability_Library_Processor.php` | Modify | Read `$definition['category']` and `$definition['slug']` in `is_permitted()`; update docblock references to FR-013…FR-017. |
| `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` | Modify (light) | Verify no docblock/payload references to `main_key`/`sub_key`; update any inline schema docs. |
| `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Rest_Controller.php` | No code change | Confirm namespace `acrossai-abilities-library/v1` unchanged; docblock-only edits if needed. |
| `admin/Partials/LibraryMenu.php` | Verify | Confirm the localized `acrossaiAbilityLibraryData.definitions` payload reflects the renamed Registry output (it should automatically, since it reads from the Registry). No code change expected. |
| `src/js/ability-library/components/LibraryPage.js` | Modify | Update destructure in `groupDefinitions()` to use `category`, `category_label`, `slug`, `slug_label`, `name`. Rename local variables. Pass `name` per sub-entry. |
| `src/js/ability-library/components/LibraryCard.js` | Modify | Rename props (`mainKey` → `category`, `mainKeyLabel` → `categoryLabel`, `subKeys` → `slugs`). Render each row by ability `name` (human-readable) instead of `slug_label`. |
| `src/js/ability-library/api.js` | Modify (docblock only) | Update `@return`/`@param` docblock wording: "keyed by main_key" → "keyed by category". No behaviour change. |
| `docs/memory/DECISIONS.md` | Append | Add a Feature 031 decision entry documenting the rename + display-source swap and noting `name` is now the user-visible identifier. |
| `specs/030-library-fix-addons-rebrand/tasks.md` | No change | Already in-flight; do not touch. Feature 031 will get its own `specs/031-library-category-slug-rebrand/` directory when spec-kit is run by the user. |
| Test fixtures (if any under `tests/`) | Modify | Update any sample definition arrays from `main_key`/`sub_key` to `category`/`slug`. |

---

## Rename Map

The single most important reference for this feature — lists every identifier being renamed.

| Old identifier | New identifier | Location |
|---|---|---|
| `abstract protected function main_key(): string` | `abstract protected function category(): string` | `includes/Modules/Library/Ability_Definition.php` |
| `abstract protected function main_key_label(): string` | `abstract protected function category_label(): string` | `includes/Modules/Library/Ability_Definition.php` |
| `abstract protected function sub_key(): string` | `abstract protected function slug(): string` | `includes/Modules/Library/Ability_Definition.php` |
| `abstract protected function sub_key_label(): string` | `abstract protected function slug_label(): string` | `includes/Modules/Library/Ability_Definition.php` |
| `'main_key' => $this->main_key()` | `'category' => $this->category()` | `Ability_Definition::push_definition()` |
| `'main_key_label' => $this->main_key_label()` | `'category_label' => $this->category_label()` | `Ability_Definition::push_definition()` |
| `'sub_key' => $this->sub_key()` | `'slug' => $this->slug()` | `Ability_Definition::push_definition()` |
| `'sub_key_label' => $this->sub_key_label()` | `'slug_label' => $this->slug_label()` | `Ability_Definition::push_definition()` |
| `REQUIRED_FIELDS = ['main_key','main_key_label','sub_key','sub_key_label','name','args']` | `REQUIRED_FIELDS = ['category','category_label','slug','slug_label','name','args']` | `AcrossAI_Ability_Library_Registry` constant |
| `$item['main_key']` (read in `validate_and_normalize()`) | `$item['category']` | `AcrossAI_Ability_Library_Registry::validate_and_normalize()` |
| `$item['main_key_label']` | `$item['category_label']` | `AcrossAI_Ability_Library_Registry::validate_and_normalize()` |
| `$item['sub_key']` | `$item['slug']` | `AcrossAI_Ability_Library_Registry::validate_and_normalize()` |
| `$item['sub_key_label']` | `$item['slug_label']` | `AcrossAI_Ability_Library_Registry::validate_and_normalize()` |
| Local var `$main_key` | `$category` | `AcrossAI_Ability_Library_Registry::validate_and_normalize()` and `AcrossAI_Ability_Library_Processor::is_permitted()` |
| Local var `$sub_key` | `$slug` | `AcrossAI_Ability_Library_Registry::validate_and_normalize()` and `AcrossAI_Ability_Library_Processor::is_permitted()` |
| Output array key `'main_key' => $main_key` | `'category' => $category` | `Registry::validate_and_normalize()` return array |
| Output array key `'main_key_label' => ...` | `'category_label' => ...` | `Registry::validate_and_normalize()` return array |
| Output array key `'sub_key' => $sub_key` | `'slug' => $slug` | `Registry::validate_and_normalize()` return array |
| Output array key `'sub_key_label' => ...` | `'slug_label' => ...` | `Registry::validate_and_normalize()` return array |
| `$definition['main_key']` | `$definition['category']` | `AcrossAI_Ability_Library_Processor::is_permitted()` |
| `$definition['sub_key']` | `$definition['slug']` | `AcrossAI_Ability_Library_Processor::is_permitted()` |
| Docblock: "keyed by main_key" | "keyed by category" | `Config.php`, `api.js`, REST controller docblocks |
| Docblock: "main_key absent" / "sub_key absent" | "category absent" / "slug absent" | `Processor.php` docblock and FR-013…FR-017 references |
| JS destructure: `main_key: mainKey` | `category` | `src/js/ability-library/components/LibraryPage.js` `groupDefinitions()` |
| JS destructure: `main_key_label: mainKeyLabel` | `category_label: categoryLabel` | `LibraryPage.js` `groupDefinitions()` |
| JS destructure: `sub_key: subKey` | `slug` | `LibraryPage.js` `groupDefinitions()` |
| JS destructure: `sub_key_label: subKeyLabel` | `slug_label: slugLabel` | `LibraryPage.js` `groupDefinitions()` |
| JS group key: `mainKey` (Map key & item id) | `category` | `LibraryPage.js` `groupDefinitions()` |
| JS group field: `subKeys: [...]` (array of sub-entries) | `slugs: [...]` (each entry now includes `name`) | `LibraryPage.js` `groupDefinitions()` |
| JS sub-entry shape: `{ subKey, subKeyLabel }` | `{ slug, slugLabel, name }` | `LibraryPage.js` `groupDefinitions()` |
| JS state callback: `handleChange(mainKey, updatedEntry)` | `handleChange(category, updatedEntry)` | `LibraryPage.js` |
| JS state key: `config[mainKey]` | `config[category]` | `LibraryPage.js` and `LibraryCard.js` |
| LibraryCard prop: `item.mainKey` / `item.mainKeyLabel` | `item.category` / `item.categoryLabel` | `LibraryCard.js` |
| LibraryCard prop: `item.subKeys` | `item.slugs` | `LibraryCard.js` |
| LibraryCard local: `subKeysConfig` | `slugsConfig` | `LibraryCard.js` |
| LibraryCard render: per-row label `subKeyLabel` | per-row label `name` (with optional `slugLabel` as fallback) | `LibraryCard.js` checkbox `label` |
| Sub-entry config object key: `sub_keys` (in REST + state) | **unchanged on disk**; remains `sub_keys` in saved option + REST | (intentionally preserved) |

---

## Implementation Changes

### FILE: `includes/Modules/Library/Ability_Definition.php`
**Change**: Rename the four abstract grouping methods and the `push_definition()` array keys.
**Details**:

Current (lines ~38–48):
```php
/** Library card grouping key (e.g. 'sre-tools'). */
abstract protected function main_key(): string;

/** Human-readable label for the card title (e.g. 'SRE Tools'). */
abstract protected function main_key_label(): string;

/** Sub-key for the per-ability checkbox (e.g. 'transient-flush'). */
abstract protected function sub_key(): string;

/** Human-readable label for the sub-key checkbox (e.g. 'Flush Transients'). */
abstract protected function sub_key_label(): string;
```

Replace with:
```php
/** Library card grouping category (e.g. 'sre-tools'). */
abstract protected function category(): string;

/** Human-readable label for the card title (e.g. 'SRE Tools'). */
abstract protected function category_label(): string;

/** Per-ability slug for the checkbox (e.g. 'transient-flush'). */
abstract protected function slug(): string;

/** Human-readable label for the slug checkbox (e.g. 'Flush Transients'). */
abstract protected function slug_label(): string;
```

Current `push_definition()` body:
```php
$definitions[] = array(
    'main_key'       => $this->main_key(),
    'main_key_label' => $this->main_key_label(),
    'sub_key'        => $this->sub_key(),
    'sub_key_label'  => $this->sub_key_label(),
    'name'           => $spec['name'] ?? '',
    'args'           => $spec['args'] ?? array(),
);
```

Replace with:
```php
$definitions[] = array(
    'category'       => $this->category(),
    'category_label' => $this->category_label(),
    'slug'           => $this->slug(),
    'slug_label'     => $this->slug_label(),
    'name'           => $spec['name'] ?? '',
    'args'           => $spec['args'] ?? array(),
);
```

---

### FILE: `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`
**Change**: Rename `REQUIRED_FIELDS` entries, the sanitized output array keys, and the local working variables. Update docblock contract description. Be explicit that the top-level `category` is a definition-level grouping and is distinct from the `category` key permitted inside `args` (which is the `wp_register_ability()` ability category).
**Details**:

Current `REQUIRED_FIELDS` constant:
```php
private const REQUIRED_FIELDS = array(
    'main_key',
    'main_key_label',
    'sub_key',
    'sub_key_label',
    'name',
    'args',
);
```

Replace with:
```php
private const REQUIRED_FIELDS = array(
    'category',
    'category_label',
    'slug',
    'slug_label',
    'name',
    'args',
);
```

Current filter docblock (above `apply_filters`):
> "Each definition must include main_key, main_key_label, sub_key, sub_key_label, name, and args. Unknown args keys are stripped."

Replace with:
> "Each definition must include category, category_label, slug, slug_label, name, and args. The top-level `category` groups abilities into Library cards; it is distinct from the `category` key permitted inside `args`, which is the WordPress Abilities API ability category. Unknown args keys are stripped."

Current `validate_and_normalize()` sanitization block:
```php
$main_key = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $item['main_key'] );
$sub_key  = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $item['sub_key'] );
// Preserve the namespace/name slash: sanitize_key() strips '/', corrupting names like 'plugin/ability'.
$name = preg_replace( '/[^a-z0-9_\-\/]/', '', strtolower( (string) $item['name'] ) );

if ( '' === $main_key || '' === $sub_key || '' === $name ) {
    $this->log_invalid( $index, 'key or name became empty after sanitization' );
    continue;
}

$valid[] = array(
    'main_key'       => $main_key,
    'main_key_label' => wp_kses_post( (string) $item['main_key_label'] ),
    'sub_key'        => $sub_key,
    'sub_key_label'  => wp_kses_post( (string) $item['sub_key_label'] ),
    'name'           => $name,
    'args'           => $item['args'],
);
```

Replace with:
```php
$category = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $item['category'] );
$slug     = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $item['slug'] );
// Preserve the namespace/name slash: sanitize_key() strips '/', corrupting names like 'plugin/ability'.
$name = preg_replace( '/[^a-z0-9_\-\/]/', '', strtolower( (string) $item['name'] ) );

if ( '' === $category || '' === $slug || '' === $name ) {
    $this->log_invalid( $index, 'category, slug, or name became empty after sanitization' );
    continue;
}

$valid[] = array(
    'category'       => $category,
    'category_label' => wp_kses_post( (string) $item['category_label'] ),
    'slug'           => $slug,
    'slug_label'     => wp_kses_post( (string) $item['slug_label'] ),
    'name'           => $name,
    'args'           => $item['args'],
);
```

---

### FILE: `includes/Modules/Library/AcrossAI_Ability_Library_Config.php`
**Change**: Docblock-only rename of "main_key" / "sub_key" terminology to "category" / "slug". The persisted option shape and method signatures remain unchanged: top-level keys are still per-category, the inner map is still `sub_keys`, and constants `MAX_KEYS` and `MAX_SUB_KEYS` are preserved verbatim.
**Details**:

- Update the docblock on `save_config()` parameter description from "Raw POST payload" (unchanged) but add a clarifying line: "Keys are sanitized category identifiers. Each entry has the shape `{enabled, mode, sub_keys}` (sub_keys retained for on-disk compatibility)."
- Update the docblock on `sanitize_entry()` from "Sanitizes a single main_key entry." to "Sanitizes a single category entry."
- Add an optional class constant `const MAX_SLUGS = self::MAX_SUB_KEYS;` for forward-compatible naming. Keep `MAX_SUB_KEYS` to avoid breaking any external reads.
- Do **not** rename the `sub_keys` array key inside `sanitize_entry()` — this is the on-disk wire format and must remain stable to preserve saved configs (DEC-D6 sparse storage).

---

### FILE: `includes/Modules/Library/AcrossAI_Ability_Library_Processor.php`
**Change**: Read renamed definition keys; rename local working variables; update docblock references.
**Details**:

Current `is_permitted()` head:
```php
private function is_permitted( array $definition, array $config ): bool {
    $main_key = $definition['main_key'];
    $sub_key  = $definition['sub_key'];

    // Main key absent from config → permitted with default all-mode (FR-013).
    if ( ! isset( $config[ $main_key ] ) ) {
        return true;
    }

    $entry   = $config[ $main_key ];
    $enabled = isset( $entry['enabled'] ) ? (bool) $entry['enabled'] : true;
```

Replace with:
```php
private function is_permitted( array $definition, array $config ): bool {
    $category = $definition['category'];
    $slug     = $definition['slug'];

    // Category absent from config → permitted with default all-mode (FR-013).
    if ( ! isset( $config[ $category ] ) ) {
        return true;
    }

    $entry   = $config[ $category ];
    $enabled = isset( $entry['enabled'] ) ? (bool) $entry['enabled'] : true;
```

Current tail:
```php
// Specific mode: sub_key must be explicitly enabled; absent defaults to false (FR-016, FR-017).
return isset( $entry['sub_keys'][ $sub_key ] ) && (bool) $entry['sub_keys'][ $sub_key ];
```

Replace with:
```php
// Specific mode: slug must be explicitly enabled; absent defaults to false (FR-016, FR-017).
return isset( $entry['sub_keys'][ $slug ] ) && (bool) $entry['sub_keys'][ $slug ];
```

(The `sub_keys` map key in the saved option is intentionally preserved for backwards storage compatibility — only the local variable name changes.)

Update the docblock header `FR-013: main_key absent → enabled by default.` → `FR-013: category absent → enabled by default.` and parallel lines for FR-014 (`category disabled`) and FR-017 (`slug absent in Specific mode`).

---

### FILE: `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php`
**Change**: Verify no code-level references to `main_key`/`sub_key` keys remain; update docblock vocabulary if present. The route URL, namespace, and capability check are not modified.
**Details**: Read the file and replace any docblock occurrences of "main_key" with "category" and "sub_key" with "slug". The JSON wire format remains: a top-level object whose keys are category identifiers, each value `{enabled: bool, mode: 'all'|'specific', sub_keys: {<slug>: bool}}`.

---

### FILE: `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Rest_Controller.php`
**Change**: No code change. Verify `REST_NAMESPACE = 'acrossai-abilities-library/v1'` and the `check_permission()` two-gate logic are preserved.
**Details**: No edits unless an embedded docblock references `main_key`/`sub_key`.

---

### FILE: `admin/Partials/LibraryMenu.php`
**Change**: Verify only — the partial reads from `AcrossAI_Ability_Library_Registry::instance()->get_definitions()` and localizes them via `wp_add_inline_script('before')` (per BUG-WP-LOCALIZE-SCRIPT-RENDER). Because the Registry's normalized output is now keyed by `category`/`slug`/`name`, the localized `window.acrossaiAbilityLibraryData.definitions` automatically reflects the rename. No code change expected, but confirm during implementation.
**Details**: Grep the partial for the literal strings `main_key` and `sub_key`; if any appear (e.g., in a comment), update them.

---

### FILE: `src/js/ability-library/components/LibraryPage.js`
**Change**: Update `groupDefinitions()` to destructure the renamed fields and surface `name` per sub-entry; rename local state keys and the `handleChange` parameter.
**Details**:

Current `groupDefinitions()` (lines 8–26):
```js
function groupDefinitions(definitions) {
    const map = new Map();
    for (const def of definitions) {
        const {
            main_key: mainKey,
            main_key_label: mainKeyLabel,
            sub_key: subKey,
            sub_key_label: subKeyLabel,
        } = def;
        if (!map.has(mainKey)) {
            map.set(mainKey, { id: mainKey, mainKey, mainKeyLabel, subKeys: [] });
        }
        const group = map.get(mainKey);
        if (!group.subKeys.some((s) => s.subKey === subKey)) {
            group.subKeys.push({ subKey, subKeyLabel });
        }
    }
    return Array.from(map.values());
}
```

Replace with:
```js
function groupDefinitions(definitions) {
    const map = new Map();
    for (const def of definitions) {
        const {
            category,
            category_label: categoryLabel,
            slug,
            slug_label: slugLabel,
            name,
        } = def;
        if (!map.has(category)) {
            map.set(category, { id: category, category, categoryLabel, slugs: [] });
        }
        const group = map.get(category);
        if (!group.slugs.some((s) => s.slug === slug)) {
            group.slugs.push({ slug, slugLabel, name });
        }
    }
    return Array.from(map.values());
}
```

Current `handleChange` signature:
```js
function handleChange(mainKey, updatedEntry) {
    const next = { ...config, [mainKey]: updatedEntry };
```

Replace with:
```js
function handleChange(category, updatedEntry) {
    const next = { ...config, [category]: updatedEntry };
```

Card render (currently `key={item.mainKey}`) becomes `key={item.category}`.

---

### FILE: `src/js/ability-library/components/LibraryCard.js`
**Change**: Rename destructured props, rename local `subKeysConfig` to `slugsConfig`, and switch the per-row checkbox `label` source from `subKeyLabel` to the ability `name`.
**Details**:

Current head:
```js
export default function LibraryCard({ item, config, onChange }) {
    const { mainKey, mainKeyLabel, subKeys } = item;
    const entry = config[mainKey] ?? { enabled: true, mode: 'all', sub_keys: {} };

    const enabled = entry.enabled ?? true;
    const mode = entry.mode ?? 'all';
    const subKeysConfig = entry.sub_keys ?? {};

    function update(patch) {
        onChange(mainKey, { ...entry, ...patch });
    }
```

Replace with:
```js
export default function LibraryCard({ item, config, onChange }) {
    const { category, categoryLabel, slugs } = item;
    const entry = config[category] ?? { enabled: true, mode: 'all', sub_keys: {} };

    const enabled = entry.enabled ?? true;
    const mode = entry.mode ?? 'all';
    const slugsConfig = entry.sub_keys ?? {};

    function update(patch) {
        onChange(category, { ...entry, ...patch });
    }
```

Current header card title:
```jsx
<ToggleControl
    __nextHasNoMarginBottom
    label={<strong>{mainKeyLabel}</strong>}
    checked={enabled}
    onChange={(value) => update({ enabled: value })}
/>
```

Replace with:
```jsx
<ToggleControl
    __nextHasNoMarginBottom
    label={<strong>{categoryLabel}</strong>}
    checked={enabled}
    onChange={(value) => update({ enabled: value })}
/>
```

Current Specific-mode list:
```jsx
{enabled && mode === 'specific' && subKeys.length > 0 && (
    <div className="acrossai-library-card__sub-keys">
        {subKeys.map(({ subKey, subKeyLabel }) => (
            <CheckboxControl
                __nextHasNoMarginBottom
                key={subKey}
                label={subKeyLabel}
                checked={subKeysConfig[subKey] ?? false}
                onChange={(value) => update({ sub_keys: { ...subKeysConfig, [subKey]: value } })}
            />
        ))}
    </div>
)}
```

Replace with — note `label` now uses the ability `name`, falling back to `slugLabel` if `name` is empty:
```jsx
{enabled && mode === 'specific' && slugs.length > 0 && (
    <div className="acrossai-library-card__slugs">
        {slugs.map(({ slug, slugLabel, name }) => (
            <CheckboxControl
                __nextHasNoMarginBottom
                key={slug}
                label={name || slugLabel}
                checked={slugsConfig[slug] ?? false}
                onChange={(value) => update({ sub_keys: { ...slugsConfig, [slug]: value } })}
            />
        ))}
    </div>
)}
```

(Note: the `update({ sub_keys: ... })` patch intentionally keeps the `sub_keys` key — it is the wire format consumed by the REST controller and saved to the site option. Renaming this key would break backwards compatibility with saved configs and is explicitly out of scope.)

Optional CSS rename: update the BEM modifier `acrossai-library-card__sub-keys` → `acrossai-library-card__slugs`. If a stylesheet selector exists for the old class, either rename it as well or keep both. (Verify with a grep across `src/scss/` during implementation.)

---

### FILE: `src/js/ability-library/api.js`
**Change**: Docblock-only rename. No behaviour change.
**Details**:
- Line 11: change `@return {Promise<Object>} Resolves to the saved config keyed by main_key.` → `@return {Promise<Object>} Resolves to the saved config keyed by category.`
- Line 20: change `@param {Object} config Full config object keyed by main_key.` → `@param {Object} config Full config object keyed by category.`

---

### FILE: `docs/memory/DECISIONS.md`
**Change**: Append a new dated decision entry for Feature 031 capturing the rename + display swap rationale.
**Details**: Add an entry titled `2026-06-11 — Library module rebranded main_key/sub_key to category/slug; ability name is now the visible row label (DEC-LIBRARY-CATEGORY-SLUG-REBRAND)` summarizing the change, listing the renamed methods/keys, and noting that the on-disk option shape is unchanged for forward compatibility.

---

## What Must NOT Change

- The `name` field on each definition — its existence, its position in the definition array, and its sanitization regex (`/[^a-z0-9_\-\/]/`) that preserves the namespace slash. The UI now reads from it; no schema change.
- The `args` field on each definition and the `ALLOWED_ARGS_FIELDS` allowlist (`label`, `description`, `category`, `execute_callback`, `permission_callback`, `input_schema`, `output_schema`, `meta`). The inner `args.category` (Abilities API category) is unrelated to the new top-level `category` grouping key.
- The REST namespace `acrossai-abilities-library/v1` and the `check_permission()` two-gate (capability + nonce) logic in `AcrossAI_Ability_Library_Rest_Controller`.
- The filter hook name `acrossai_abilities_api_init` and its `init` P99 firing priority.
- The site option key `acrossai_library_config` and its on-disk shape — top-level keys (per category), `{enabled, mode, sub_keys}` values, and the inner `sub_keys` map. Renaming the inner `sub_keys` key would invalidate every existing saved config and is out of scope.
- DEC-D6 sparse-storage rule in `AcrossAI_Ability_Library_Config::save_config()` (strip entries where `enabled=true AND mode='all' AND sub_keys=[]`).
- `AcrossAI_Ability_Library_Processor` wiring at `wp_abilities_api_init` priority 5 (must continue to run before the database Processor at P10).
- Constants `MAX_KEY_LENGTH`, `MAX_KEYS`, `MAX_SUB_KEYS`, and `VALID_MODES` in `AcrossAI_Ability_Library_Config`. A new alias `MAX_SLUGS` MAY be added but `MAX_SUB_KEYS` MUST be retained.
- The `is_library_page()` hook-suffix check in `admin/Partials/LibraryMenu.php` (per BUG-LIBRARY-HOOK-SUFFIX).
- The `wp_add_inline_script('before')` localization pattern (per BUG-WP-LOCALIZE-SCRIPT-RENDER).

---

## Validation Checklist

- [ ] `composer dump-autoload` runs cleanly (no class-name changes, so autoloader output is unchanged).
- [ ] PHPStan (`composer run phpstan` or equivalent) produces zero new errors. Pay special attention to the `Registry::validate_and_normalize()` return type — confirm the union/array shape annotations are updated.
- [ ] WordPress Coding Standards check (`composer run phpcs` if configured) passes.
- [ ] JavaScript build (`npm run build`) completes without errors. Confirm `src/js/ability-library/build/` artifacts regenerate.
- [ ] ESLint (`npm run lint:js` if configured) passes.
- [ ] Browser smoke test: load `wp-admin → Abilities Manager → Library`.
  - Cards render with their `categoryLabel` titles.
  - In Specific mode, each checkbox shows the ability `name` (e.g. `acrossai-sre/transient-flush`) instead of the previous `slug_label`.
  - Toggling the master switch persists across reload.
  - Switching to Specific mode and toggling a slug-row checkbox persists across reload (verified via `get_site_option('acrossai_library_config')` in WP-CLI: `wp option get acrossai_library_config --format=json`).
- [ ] Saved configs from before Feature 031 still load correctly (forward compat) — the `sub_keys` on-disk key is intentionally unchanged, so a config written by Feature 027/030 should round-trip without loss.
- [ ] grep the codebase for residual `main_key`, `sub_key`, `mainKey`, `subKey`, `MainKey`, `SubKey`, `main_key_label`, `sub_key_label` strings — confirm only intentional matches remain (e.g., the `sub_keys` storage key in `Config.php` and `Processor.php`).
- [ ] Backwards compatibility with existing `Ability_Definition` subclasses: confirmed there are zero concrete subclasses inside this repository (verified via codebase search). External add-on subclasses will fail PHP's abstract-method contract until they rename their methods — see Post-Implementation Notes.
- [ ] REST contract: a POST to `/wp-json/acrossai-abilities-library/v1/abilities/config` with the pre-rename body shape still succeeds (the wire format is unchanged).

---

## Post-Implementation Notes

- **Breaking change for external add-on authors**: Any add-on that subclasses `\AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition` will fail to load (PHP fatal: abstract methods not implemented) until the subclass renames `main_key()` → `category()`, `main_key_label()` → `category_label()`, `sub_key()` → `slug()`, `sub_key_label()` → `slug_label()`. Communicate this in the plugin changelog and any developer-facing docs. Recommend bumping the plugin version's MINOR segment (or MAJOR if you treat the abstract class as public API) to signal the contract change.
- **Backwards-compat shim (optional, deferred)**: If external add-ons need a transition window, a future patch could add non-abstract default implementations to `Ability_Definition` that proxy to deprecated `main_key()`/`sub_key()` methods via `method_exists($this, 'main_key')` checks, emit `_doing_it_wrong()` notices, and return the legacy value. This is intentionally out of scope for Feature 031 — the in-repo subclass count is zero. Revisit only if external breakage reports arrive.
- **Saved-config forward compatibility**: The on-disk shape (`{enabled, mode, sub_keys}` with the inner `sub_keys` map) is intentionally preserved. No migration script is required. Site admins should see no change in saved state across the upgrade.
- **Vocabulary alignment with the Abilities module**: After this feature, both Library and Abilities modules consistently use `category` and `slug`, removing the parallel `main_key`/`sub_key` terminology. Future features adding Library-side data attributes SHOULD use the new vocabulary.
- **Display-source change**: The Library card's per-row label now sources from the ability `name` (e.g. `acrossai-sre/transient-flush`) with `slug_label` as a fallback. If a category author prefers the friendlier `slug_label`, they can either set the `name` to that value or the team can later flip the fallback order. Document the precedence in the developer guide.
- **Filter contract documentation**: Update any external developer documentation (README, hooks reference, devhub entries) that describes the `acrossai_abilities_api_init` filter contract to reflect the new array keys.
- **Memory capture**: After implementation, run `/speckit-memory-md-capture-from-diff` to record DEC-LIBRARY-CATEGORY-SLUG-REBRAND in `docs/memory/MEMORY.md` and the matching index entry.