# Implementation Plan: MCP Tools Pass-through (Feature 029)

**Branch**: `029-mcp-tools-passthrough` | **Date**: 2026-06-10 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/029-mcp-tools-passthrough/spec.md`

---

## Summary

Add a per-ability tri-state tinyint column `pass_as_tool` to the `acrossai_abilities` BerlinDB table. Wire it through the existing tri-state plumbing (Row → Sanitizer → Query → Formatter). Create a new singleton module `AcrossAI_Mcp_Tools_Passthrough` that hooks the `mcp_adapter_server_config` filter (priority 10, 2 args) and merges opted-in slugs into `$config['tools']` via `array_unique`. Surface the flag as an inline toggle column ("Pass as Tool") in `AbilitiesList.jsx` that POSTs to the existing sparse-update endpoint.

---

## Technical Context

**Language/Version**: PHP 8.1+ / JavaScript (React/JSX via @wordpress/scripts)
**Primary Dependencies**: BerlinDB v3, @wordpress/dataviews, @wordpress/components
**Storage**: Existing `acrossai_abilities` BerlinDB table (no migration; table recreated manually on activation)
**Testing**: PHPUnit (unit tests for Schema/Row/Query/Sanitizer/Formatter patches); no Jest tests for the new React cell (matches Feature 027 accepted pattern; the cell is a thin dispatch call)
**Target Platform**: WordPress 6.9+ multisite-compatible
**Project Type**: WordPress plugin feature increment
**Performance Goals**: N/A — filter runs once per MCP server init; row count is bounded (tens of abilities)
**Constraints**: No DB migration, no new REST namespace, no new admin page, no `mcp-adapter` hard dependency
**Scale/Scope**: 10 files modified, 1 new PHP file, ~250 lines PHP delta, ~80 lines JSX delta

---

## Constitution Check

*GATE: Must pass before implementation. Re-check after each CHANGE.*

| Principle | Status | Notes |
|---|---|---|
| §I Modular Architecture | PASS | New module `McpToolsPassthrough/` is self-contained, singular purpose. No `includes/Base/`. |
| §II WordPress Standards | PASS | No new SQL, no `eval()`, no deprecated functions. PHPCS + PHPStan + Plugin Check must remain clean. |
| §III User-Centric Design | DEVIATION (accepted) | Inline toggle cell in Abilities list is NOT a DataForm — it matches the existing `status` `<select>` inline pattern. `DEC-DESIGN-OVERRIDES-DATAVIEWS` covers this; the list view is the correct UX context. |
| §IV Security First | PASS | No new input boundaries. Pass-through uses existing sparse-update endpoint (nonce + capability already enforced). Protected slug guard (server-side) rejects writes for `mcp-adapter/*` slugs. |
| §V Extensibility | PASS | `AcrossAI_Mcp_Tools_Passthrough` is a no-op when `mcp_adapter_server_config` filter never fires. No hard dependency on `mcp-adapter`. |
| §VI DRY | PASS | All plumbing (Row cast, Sanitizer, Query write, Formatter) reuses existing tri-state paths. No new utility class. |
| §VII Definition of Done | TRACKED | Quality gates listed in Validation Checklist below. |

**Constitution note on `$instance` vs `$_instance`**: The Constitution §Architecture & UI Standards shows `$_instance` as the singleton property name, but `DEC-SINGLETON-PSR2-PROPERTY` (Feature 022, Active) renamed it to `$instance` across all 21 singleton classes for PSR-2 compliance. The new module uses `$instance`.

---

## Project Structure

### Documentation (this feature)

```text
specs/029-mcp-tools-passthrough/
├── spec.md                  # Feature specification
├── plan.md                  # This file
├── memory-synthesis.md      # Memory context (generated)
├── checklists/
│   └── requirements.md      # Spec quality checklist (all pass)
└── tasks.md                 # Phase 2 output (/speckit-tasks)
```

### Source Code Layout

```text
includes/
├── Modules/
│   ├── Abilities/
│   │   └── Database/
│   │       ├── AcrossAI_Abilities_Schema.php     [MOD]
│   │       ├── AcrossAI_Abilities_Row.php         [MOD]
│   │       └── AcrossAI_Abilities_Query.php       [MOD]
│   └── McpToolsPassthrough/                       [NEW DIR]
│       └── AcrossAI_Mcp_Tools_Passthrough.php     [NEW]
├── Utilities/
│   ├── AcrossAI_Abilities_Sanitizer.php           [MOD]
│   └── AcrossAI_Abilities_Formatter.php           [MOD]
└── Main.php                                       [MOD]
src/
└── js/
    └── abilities/
        └── components/
            └── AbilitiesList.jsx                  [MOD]
docs/memory/
├── DECISIONS.md                                   [MOD]
└── INDEX.md                                       [MOD]
.specify/memory/
└── CONSTITUTION.md                                [MOD — version bump + McpToolsPassthrough module entry]
```

---

## Implementation Changes

### CHANGE-1 — Schema: add `pass_as_tool` column

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`

Insert after the `show_in_mcp` column array (current line ~167):

```php
// Tri-state: pass this ability as a tool to every MCP server.
array(
    'name'       => 'pass_as_tool',
    'type'       => 'tinyint',
    'length'     => '1',
    'allow_null' => true,
    'default'    => null,
),
```

**Guards** (from memory):
- No `'primary' => true` key (BUG-BERLINDB-V3-DOUBLE-PRIMARY).
- No entry in `$indexes` for this column.
- No `'default' => 'CURRENT_TIMESTAMP'` (BUG-BERLINDB-V3-TIMESTAMP-QUOTING; not applicable here but rule reinforced).
- No `$version` bump in `AcrossAI_Abilities_Table.php` — table recreated manually.

**Manual activation flow** after this change: deactivate plugin → drop `wp_acrossai_abilities` table → reactivate.

---

### CHANGE-2 — Row: property, cast, and JSON blocklist

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php`

Four edits mirroring the `site_allowed` pattern:

1. **Docblock** — add to the `@property` block:
   ```php
    * @property bool|null $pass_as_tool
   ```
2. **Property declaration** — alongside `$site_allowed`:
   ```php
   public $pass_as_tool = null;
   ```
3. **`$tri_state_fields` in Row constructor** (current L284) — add `'pass_as_tool'` so the value is cast to `bool|null` via `AcrossAI_Sanitizer::cast_tri_state()`.
4. **`get_json_fields()` blocklist** (current L235) — add `'pass_as_tool'` to prevent the scalar tinyint from being JSON-decoded.

**Guard**: Everywhere this field is read off the row, guard with `null !== $value` only — never `'' !== (string) $value` (BUG-MERGER-BOOL-STRING-CAST).

---

### CHANGE-3 — Sanitizer: write path

**File**: `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`

**Note**: This is `AcrossAI_Abilities_Sanitizer` (the wrapper, which owns tri-state field lists), NOT the base `AcrossAI_Sanitizer` (ARCH-SANITIZER-TWO-CLASS).

Add `'pass_as_tool'` to the `$tri_state_fields` array inside both `sanitize_create_request()` (current ~L296) and `sanitize_update_request()` (current ~L322):

```php
$tri_state_fields = array(
    'site_allowed',
    'readonly',
    'destructive',
    'idempotent',
    'show_in_rest',
    'show_in_mcp',
    'pass_as_tool',
);
```

**Guard**: Do NOT add `'pass_as_tool'` to `AcrossAI_Abilities_Query::PROTECTED_FIELDS` — it is an override-style flag that must remain editable for registry-sourced rows (DEC-DB-WRITE-BOUNDARY-GUARD).

**SEC-001 resolution**: API-level protected-slug enforcement is provided by `AcrossAI_Abilities_Write_Controller::update_ability()` (line 308): `in_array( $slug, AcrossAI_Protected_Abilities::get_protected_slugs(), true )` — slug-level, strict comparison, fires before `sanitize_update_request()` (line 313). Any update payload, including one containing only `pass_as_tool`, is rejected with 403 if the target slug is protected. No new guard needed for this feature.

---

### CHANGE-4 — Formatter: response/exposure/merged blocks

**File**: `includes/Utilities/AcrossAI_Abilities_Formatter.php`

Three insertion points — all next to existing `site_allowed` keys:

1. `format_for_response()` (~L49):
   ```php
   'pass_as_tool' => $row->pass_as_tool,
   ```
2. `format_for_exposure()` (~L90 block):
   ```php
   'pass_as_tool' => $merged['pass_as_tool'] ?? null,
   ```
3. `format_merged_ability()` (~L139) — required by DEC-ABILITIES-DUAL-MODE-LIST:
   ```php
   'pass_as_tool' => $merged['pass_as_tool'] ?? null,
   ```

The formatter array is the sole schema source (no `get_item_schema()`). All three insertion points are required.

---

### CHANGE-5 — Query: write cast + finder method

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`

**Edit 1** — `prepare_fields_for_write()` (~L619): add `'pass_as_tool'` to the `$tri_state` array so PHP `bool true/false` casts cleanly to DB `1`/`0`.

**Edit 2** — new method, placed next to `by_mcp_type()` (~L469):

```php
/**
 * Return slugs of all abilities flagged to be passed as MCP tools.
 *
 * @return string[]
 */
public function get_pass_as_tool_slugs(): array {
    $rows = $this->query( array(
        'pass_as_tool' => 1,
        'fields'       => 'ability_slug',
        'number'       => 0,
    ) );
    return array_values( array_filter( (array) $rows ) );
}
```

**Guard**: `'number' => 0` is intentional — BerlinDB interprets 0 as "no LIMIT". Never use `-1` (BUG-BERLINDB-UNLIMITED: `absint(-1) = 1` → LIMIT 1).

**Guard (ARCH-REFACTOR-001)**: `AcrossAI_Abilities_Query` has a private constructor — it MUST be accessed via `AcrossAI_Abilities_Query::instance()`, never via `new AcrossAI_Abilities_Query()`. The private constructor is enforced at line 130 of the class. All existing callers (e.g. `AcrossAI_Abilities_Write_Controller` L79) use `::instance()`.

---

### CHANGE-6 — New module: `AcrossAI_Mcp_Tools_Passthrough`

**File**: `includes/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough.php` (new)

```php
<?php
/**
 * MCP Tools Pass-through Module
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/McpToolsPassthrough
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Bridges the per-ability pass_as_tool flag into the mcp-adapter server-config filter.
 *
 * @since 0.1.0
 */
class AcrossAI_Mcp_Tools_Passthrough {

    /**
     * Singleton instance.
     *
     * @since 0.1.0
     * @static
     * @var AcrossAI_Mcp_Tools_Passthrough|null
     */
    protected static $instance = null;

    /**
     * Get singleton instance.
     *
     * @since 0.1.0
     * @static
     * @return AcrossAI_Mcp_Tools_Passthrough
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton.
     *
     * @since 0.1.0
     */
    private function __construct() {}

    /**
     * Inject opted-in ability slugs into every MCP server's tools[] array.
     *
     * @since      0.1.0
     * @param array  $config    Server config passed by mcp-adapter.
     * @param string $server_id Server identifier (reserved for future per-server logic).
     * @return array
     */
    public function inject_tools( array $config, string $server_id ): array {
        $extra = AcrossAI_Abilities_Query::instance()->get_pass_as_tool_slugs();
        if ( empty( $extra ) ) {
            return $config;
        }
        $existing        = isset( $config['tools'] ) && is_array( $config['tools'] ) ? $config['tools'] : array();
        $config['tools'] = array_values( array_unique( array_merge( $existing, $extra ) ) );
        return $config;
    }
}
```

**Guards**:
- `defined( 'ABSPATH' ) || exit` per-file even for instantiation-only classes (BUG-ABSPATH-STATIC-CLASS).
- `$instance` not `$_instance` (DEC-SINGLETON-PSR2-PROPERTY).
- Only `instance()` is `public static` (BUG-STATIC-METHOD-SINGLETON-BYPASS).
- `inject_tools` is a regular public method — no `add_filter()` inside this class (AC-HOOKS-MAIN).
- Non-array `$config['tools']`: falls back to empty array (FR-010, clarification Q3).
- `array_unique` prevents duplicate when a server already listed the ability (FR-005).
- `number => 0` in the query for unlimited results (BUG-BERLINDB-UNLIMITED).
- File header uses full `@subpackage` path per AC-FILE-HEADER-PATTERN.

---

### CHANGE-7 — Main.php: wire the filter

**File**: `includes/Main.php`

Inside `define_public_hooks()` (or the appropriate runtime hook method), following the Boot Flow Rule variable-first pattern:

```php
$mcp_tools_passthrough = \AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough\AcrossAI_Mcp_Tools_Passthrough::instance();
$this->loader->add_filter( 'mcp_adapter_server_config', $mcp_tools_passthrough, 'inject_tools', 10, 2 );
```

**Guards**:
- Singleton resolved into named variable `$mcp_tools_passthrough` before the Loader call (Boot Flow Rule — inline `::instance()` as second arg is prohibited).
- Goes through `$this->loader->add_filter()` — not `add_filter()` directly (AC-HOOKS-MAIN).
- Priority 10, `accepted_args = 2` (filter signature passes `$config, $server_id`).
- After `composer dump-autoload`, the new class must be autoloaded.

---

### CHANGE-8 — AbilitiesList.jsx: `PassAsToolCell` toggle column

**File**: `src/js/abilities/components/AbilitiesList.jsx`

Four edits:

**Edit 1** — Default visible columns list (~L150–159):

Add `'pass_as_tool'` to the `COLUMN_DEFAULTS` array so the column starts visible. It merges via the existing `acrossai_abilities_columns` localStorage key (DEC-COLUMN-VISIBILITY-LOCALSTORAGE).

**Edit 2** — Column header (~L638–700):

Add a `"Pass as Tool"` header alongside the existing MCP header.

**Edit 3** — `PassAsToolCell` component (new, placed near `McpCell`):

```jsx
function PassAsToolCell( { item, onToggle, disabled } ) {
    const isOn = item.pass_as_tool === true;
    return (
        <Button
            size="small"
            variant={ isOn ? 'primary' : 'secondary' }
            disabled={ disabled }
            aria-label={
                isOn
                    ? __( 'Remove from MCP tools', 'acrossai-abilities-manager' )
                    : __( 'Pass as MCP tool', 'acrossai-abilities-manager' )
            }
            onClick={ () => onToggle( item, isOn ? null : true ) }
        >
            { isOn ? __( 'On', 'acrossai-abilities-manager' ) : __( 'Off', 'acrossai-abilities-manager' ) }
        </Button>
    );
}
```

**Edit 4** — Cell render and dispatch in the column definition:

```jsx
{
    id: 'pass_as_tool',
    header: __( 'Pass as Tool', 'acrossai-abilities-manager' ),
    render: ( { item } ) => {
        const isProtected = PROTECTED_SLUGS.includes( item.ability_slug );
        return (
            <PassAsToolCell
                item={ item }
                disabled={ isProtected }
                onToggle={ async ( ability, nextValue ) => {
                    try {
                        await dispatch.updateAbility(
                            ability.ability_slug,
                            { pass_as_tool: nextValue }
                        );
                    } catch ( err ) {
                        // Toast + revert (clarification Q2)
                        createErrorNotice(
                            __( 'Could not update pass-as-tool flag.', 'acrossai-abilities-manager' ),
                            { type: 'snackbar' }
                        );
                    }
                } }
            />
        );
    },
    enableHiding: true,
    enableSorting: false,
},
```

**Guards**:
- `PROTECTED_SLUGS` comes from the existing constant/utility (DEC-PROTECTED-SLUGS-PATTERN) — no duplicate list.
- Only `null` and `true` are emitted — no `0` state in v1 UI (spec Assumptions).
- Error: toast (`createErrorNotice` snackbar) + optimistic update not applied — the toggle reverts because `dispatch.updateAbility` throws on failure and the local state is unchanged (clarification Q2).
- All strings use `__( ..., 'acrossai-abilities-manager' )` (§II).
- `disabled` prop covers protected abilities — server-side guard already rejects writes (FR-006, FR-006).
- `npm run build` must be run after this change to regenerate `build/js/abilities.js` and `build/js/abilities.asset.php`.

---

### CHANGE-9 — Durable memory updates

**Files**: `docs/memory/DECISIONS.md`, `docs/memory/INDEX.md`

Append to `docs/memory/DECISIONS.md`:

```markdown
### 2026-06-10 - DEC-MCP-TOOLS-PASSTHROUGH-COLUMN

**Status**
Active

**Why this is durable**
Establishes the contract between this plugin's abilities table and `mcp-adapter`'s
`mcp_adapter_server_config` filter. Future code must keep the column and the filter callback
aligned.

**Decision / Finding**
Per-ability MCP tool pass-through is a tri-state tinyint column (`pass_as_tool`) on
`acrossai_abilities`. NULL is the default (server's own tools[] stands); 1 injects the slug into
every MCP server's tools[] via `mcp_adapter_server_config` priority 10. Slug reads happen
through `AcrossAI_Abilities_Query::get_pass_as_tool_slugs()`.

**Tradeoffs / Prevention**
- Gained: single toggle, no per-server config UI; matches the shape of existing tri-state
  columns (`site_allowed`, `show_in_mcp`).
- Reconsider: if per-server allowlists are ever needed, replace the tinyint with a
  `pass_as_tool_servers longtext` JSON column. Only the filter callback and the toggle cell
  need to change.
```

Add to `docs/memory/INDEX.md` active-decisions table:

```
| DEC-MCP-TOOLS-PASSTHROUGH-COLUMN | Per-ability MCP tool pass-through column + filter bridge | Abilities/DB | mcp,tools,filter,abilities | Active | DECISIONS.md |
```

---

### CHANGE-10 — Constitution version bump

**File**: `.specify/memory/CONSTITUTION.md`

1. Bump version: `1.4.5 → 1.4.6` in the footer.
2. Add `McpToolsPassthrough/` to the Directory Layout module list under `includes/Modules/`.
3. Update the SYNC IMPACT REPORT HTML comment at the top (PATTERN-CONSTITUTION-SYNC-REPORT):

```html
<!--
SYNC IMPACT REPORT
Version change: 1.4.5 → 1.4.6
Modified sections: §I Modular Architecture — added McpToolsPassthrough/ as seventh active module directory
Rationale: Feature 029 introduces includes/Modules/McpToolsPassthrough/ as a self-contained filter bridge
for the mcp_adapter_server_config hook. Added to Directory Layout to match implementation reality.
Templates reviewed:
  - .specify/templates/plan-template.md ✅ reviewed — no outdated references
  - .specify/templates/spec-template.md ✅ reviewed — no outdated references
  - .specify/templates/tasks-template.md ✅ reviewed — no outdated references
  - .specify/templates/checklist-template.md ✅ reviewed — no outdated references
Deferred TODOs: None
```

---

## What Must NOT Change

- `AcrossAI_Abilities_Table.php` — no `$version` bump.
- `AcrossAI_Abilities_Schema.php` `$indexes` — no new index entry for `pass_as_tool`.
- Any column's `'primary' => true` flag — must remain absent for all columns.
- `AcrossAI_Abilities_Query::PROTECTED_FIELDS` — `pass_as_tool` must NOT be added here.
- `AcrossAI_Protected_Abilities::get_protected_slugs()` — protected slug list unchanged.
- The `mcp_adapter_server_config` filter signature in `mcp-adapter` — we are a consumer only.
- No new REST namespace, no new REST routes, no new admin page.

---

## Validation Checklist

### Schema and storage

- [ ] After plugin reactivation, `DESCRIBE wp_acrossai_abilities` shows `pass_as_tool tinyint(1) DEFAULT NULL`.
- [ ] `SHOW CREATE TABLE wp_acrossai_abilities` confirms PRIMARY KEY declared exactly once.
- [ ] A row with `pass_as_tool = NULL` and one with `pass_as_tool = 1` both round-trip without warnings.

### REST round-trip

- [ ] `GET /wp-json/acrossai-abilities-manager/v1/abilities/{slug}` returns the `pass_as_tool` key.
- [ ] `POST ... { "pass_as_tool": true }` persists `1` and the next GET returns `true`.
- [ ] `POST ... { "pass_as_tool": null }` clears the value back to `null`.
- [ ] Attempting to update a protected `mcp-adapter/*` slug returns a 403/404.

### Query and filter integration

- [ ] `(new AcrossAI_Abilities_Query())->get_pass_as_tool_slugs()` returns only opted-in slugs.
- [ ] `apply_filters( 'mcp_adapter_server_config', array( 'tools' => array( 'existing/slug' ) ), 'test-server' )` returns the union of existing + opted-in slugs, with no duplicates.
- [ ] When no rows are flagged, the filter returns `$config` byte-for-byte unchanged.
- [ ] When `$config['tools']` is `null` (non-array), the filter returns a flat array of opted-in slugs.

### Admin UI

- [ ] The "Pass as Tool" column is visible by default in the Abilities list.
- [ ] Clicking the toggle on a non-protected ability saves the new state without a page reload.
- [ ] Clicking the toggle back sets `pass_as_tool` to `null`.
- [ ] Protected abilities render the toggle in a disabled state.
- [ ] API failure during toggle: toast error appears and toggle reverts to its previous visual state.
- [ ] Column visibility can be hidden/shown via the column toggle and persists in localStorage.

### Quality gates

- [ ] `composer phpcs` clean for all touched PHP files.
- [ ] `composer phpstan` level 8 clean for all touched PHP files.
- [ ] `npm run build` succeeds; `build/js/abilities.asset.php` is regenerated.
- [ ] ESLint clean for `AbilitiesList.jsx`.
- [ ] Plugin Check clean on the production surface.
- [ ] `composer dump-autoload` run after new PHP file added.

---

## Post-implementation steps

1. Run `composer dump-autoload` after creating `AcrossAI_Mcp_Tools_Passthrough.php`.
2. Manually deactivate plugin → drop `wp_acrossai_abilities` → reactivate to get the new column.
3. Run `npm run build` after editing `AbilitiesList.jsx`.
4. Run full quality gate: `composer phpcs && composer phpstan && npm run build`.
