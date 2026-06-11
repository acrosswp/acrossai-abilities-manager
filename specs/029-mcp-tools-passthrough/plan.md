# Implementation Plan: MCP Tools Pass-through (Feature 029)

**Branch**: `029-mcp-tools-passthrough` | **Date**: 2026-06-10 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `specs/029-mcp-tools-passthrough/spec.md`

---

## Summary

Add a per-ability tri-state tinyint column `pass_as_tool` to the `acrossai_abilities` BerlinDB table. Wire it through the existing tri-state plumbing (Row → Sanitizer → Query → Formatter). Add a `inject_mcp_tools()` static action callback to the existing `AcrossAI_Ability_Override_Processor` and register it in `boot()` on `mcp_adapter_init` priority 20 (ARCH-ADV-001). The method fires after all servers are created and uses PHP Reflection to access the private `McpServer::$component_registry`, then calls `register_tools()` on it — this registers opted-in ability slugs so both `tools/list` and `tools/call` work. `mcp_adapter_server_config` is not used because it does not exist in the installed mcp-adapter version. Respects the per-ability `mcp_servers` allowlist. Surface the flag as an inline toggle column ("Pass as Tool") in `AbilitiesList.jsx` that POSTs to the existing sparse-update endpoint.

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
**Scale/Scope**: 10 files modified, 0 new PHP files, ~250 lines PHP delta, ~80 lines JSX delta

---

## Constitution Check

*GATE: Must pass before implementation. Re-check after each CHANGE.*

| Principle | Status | Notes |
|---|---|---|
| §I Modular Architecture | DEVIATION (accepted) | Injection logic added to `AcrossAI_Ability_Override_Processor` (ARCH-ADV-001). The Override Processor already owns all per-server MCP adapter hooks; `mcp_adapter_init P20` is a natural extension. No standalone module required. No `includes/Base/`. |
| §II WordPress Standards | PASS | No new SQL, no `eval()`, no deprecated functions. PHPCS + PHPStan + Plugin Check must remain clean. |
| §III User-Centric Design | DEVIATION (accepted) | Inline toggle cell in Abilities list is NOT a DataForm — it matches the existing `status` `<select>` inline pattern. `DEC-DESIGN-OVERRIDES-DATAVIEWS` covers this; the list view is the correct UX context. |
| §IV Security First | PASS | No new input boundaries. Pass-through uses existing sparse-update endpoint (nonce + capability already enforced). Protected slug guard (server-side) rejects writes for `mcp-adapter/*` slugs. |
| §V Extensibility | PASS | `inject_mcp_tools()` is a no-op when `mcp_adapter_server_config` filter never fires (e.g. mcp-adapter absent). No hard dependency on `mcp-adapter`. |
| §VI DRY | PASS | All plumbing (Row cast, Sanitizer, Query write, Formatter) reuses existing tri-state paths. No new utility class. |
| §VII Definition of Done | TRACKED | Quality gates listed in Validation Checklist below. |

**Constitution note on `$instance` vs `$_instance`**: The Constitution §Architecture & UI Standards shows `$_instance` as the singleton property name, but `DEC-SINGLETON-PSR2-PROPERTY` (Feature 022, Active) renamed it to `$instance` across all 21 singleton classes for PSR-2 compliance. All existing classes including `AcrossAI_Ability_Override_Processor` use `$instance`.

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
│   └── Abilities/
│       └── AcrossAI_Ability_Override_Processor.php [MOD — inject_mcp_tools() added]
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
└── CONSTITUTION.md                                [no change — no new directory added]
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

### CHANGE-6 — Override Processor: `inject_mcp_tools()` static method

**File**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (modified)

**Architecture decision (Option B)**: Instead of a standalone `McpToolsPassthrough/` module, the injection logic lives in `AcrossAI_Ability_Override_Processor`. This class already owns all per-server MCP adapter hooks and wires them in `boot()` via ARCH-ADV-001. `mcp_adapter_init P20` is natural — runs after all servers are created.

**Why `mcp_adapter_init` + Reflection, NOT `mcp_adapter_server_config` or `mcp_adapter_tools_list`**:
- `mcp_adapter_tools_list` only affects `tools/list` display. Tools added there are NOT callable.
- `mcp_adapter_server_config` does not exist in the installed mcp-adapter version.
- `mcp_adapter_init P20` fires after DefaultServerFactory (P10) and acrossai-mcp-manager (P11) create all servers. We then use PHP Reflection to access private `McpServer::$component_registry` and call `register_tools()` — this makes tools both listable AND callable.

Add to `boot()` hook summary docblock:
```
 *   mcp_adapter_init         P20       — inject_mcp_tools()
```

Add inside `boot()` after the existing `mcp_adapter_pre_tool_call` registration:
```php
// Register opted-in ability slugs into every MCP server's callable tool registry.
// Runs at mcp_adapter_init P20, after all servers are created.
// Required capability for pass_as_tool writes: manage_options (TSEC-T01).
// ARCH-ADV-001: boot() wires this directly; not registered via Main.php Loader.
add_action( 'mcp_adapter_init', array( __CLASS__, 'inject_mcp_tools' ), 20 );
```

Add the action callback method — see `AcrossAI_Ability_Override_Processor.php` for full implementation. Signature: `public static function inject_mcp_tools( $adapter ): void`.

**Guards**:
- `method_exists($adapter, 'get_servers')` guard: no-op when mcp-adapter is absent (FR-009).
- `self::load_overrides_cache()` reuses the existing override cache — no extra DB query.
- `mcp_servers` allowlist check mirrors `is_ability_allowed_on_server()` semantics.
- Early-return when no pass_as_tool rows (FR-004 — zero-impact).
- Reflection try/catch silently skips servers where `$component_registry` is absent.
- PATH B only (ARCH-ADV-001): action fires on non-Manager REST requests only.

---

### CHANGE-7 — Main.php: leave unchanged

**File**: `includes/Main.php`

No hook registration in `Main.php`. The `mcp_adapter_init` action is registered in `AcrossAI_Ability_Override_Processor::boot()` per ARCH-ADV-001.

Leave the comment in `define_public_hooks()` that reads:
```php
// Note: mcp_adapter_init P20 (pass_as_tool injection) is registered inside
// AcrossAI_Ability_Override_Processor::boot() — PATH B only, per ARCH-ADV-001.
// Runs after all servers are created (P10 default, P11 database servers).
// Uses Reflection on McpServer::$component_registry because mcp_adapter_server_config
// does not exist in the installed mcp-adapter version.
```

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

Update `docs/memory/DECISIONS.md` entry `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` to read:

```markdown
### 2026-06-11 — DEC-MCP-TOOLS-PASSTHROUGH-COLUMN

**Status**
Active

**Why this is durable**
Establishes the contract between the plugin's abilities table and `mcp-adapter`'s
`mcp_adapter_server_config` filter. Future code must keep the column and the filter
callback aligned.

**Decision**
Per-ability MCP tool pass-through is a tri-state tinyint column (`pass_as_tool`)
on `acrossai_abilities`. NULL = default (server's own tools unchanged); 1 = inject
the ability as a Tool DTO into every MCP server's tools list via `mcp_adapter_server_config`
priority 10 (PATH B only). The filter bridge lives in
`AcrossAI_Ability_Override_Processor::inject_mcp_tools()` (static), registered in
`boot()` per ARCH-ADV-001. Value 0 (explicit deny) is stored by the sanitizer
but excluded from injection — reserved for future per-server deny semantics.
Protected slugs are rejected at the API layer by
`AcrossAI_Abilities_Write_Controller::update_ability()` L308 (slug-level,
strict comparison, fires before sanitize). `mcp_servers` allowlist is respected:
null = all servers; [] = blocked; [...] = inject only if current server ID matches.

**Tradeoffs**
- Gained: single toggle, no per-server config UI; matches the shape of existing
  tri-state columns (site_allowed, show_in_mcp). Allowlist check reuses existing
  mcp_servers column — no new storage.
- Reconsider: if finer-grained per-server control is needed beyond the existing
  mcp_servers allowlist, revisit inject_mcp_tools() logic only.

**Where to look next**
`includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` (inject_mcp_tools),
`includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`
(`get_pass_as_tool_slugs()`), `src/js/abilities/components/AbilitiesList.jsx`
(`PassAsToolCell`).
```

Update `docs/memory/INDEX.md` active-decisions table row to:

```
| DEC-MCP-TOOLS-PASSTHROUGH-COLUMN | Per-ability MCP tool pass-through column + filter bridge (pass_as_tool tinyint, AcrossAI_Ability_Override_Processor::inject_mcp_tools, mcp_adapter_server_config P10) | Abilities/DB | mcp, tools, filter, abilities, berlindb | Active | DECISIONS.md |
```

---

### CHANGE-10 — Constitution: no change required

**File**: `.specify/memory/CONSTITUTION.md`

No change. Feature 029 (Option B) does not introduce a new module directory — the injection logic lives in the existing `Abilities/` module per ARCH-ADV-001. The Directory Layout does not change. The constitution version bump `1.4.5 → 1.4.6` is deferred; it will be applied when the next section-level change occurs.

T026 in tasks.md is updated to reflect this: no constitution edit needed for this feature.

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

- [ ] `AcrossAI_Abilities_Query::instance()->get_pass_as_tool_slugs()` returns only opted-in slugs.
- [ ] `apply_filters( 'mcp_adapter_server_config', array( 'tools' => array( 'existing/slug' ) ), 'test-server' )` returns a config where `tools` contains both `'existing/slug'` and opted-in ability slugs, with no duplicates.
- [ ] When no rows are flagged, the filter returns `$config` byte-for-byte unchanged.
- [ ] Non-array `$config['tools']` is treated as empty — result `tools` contains only the opted-in slugs.

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
- [ ] `composer dump-autoload` run to clean autoload map after deleted `AcrossAI_Mcp_Tools_Passthrough.php`.

---

## Post-implementation steps

1. Run `composer dump-autoload` to clean the autoload map after deleting `AcrossAI_Mcp_Tools_Passthrough.php`.
2. Manually deactivate plugin → drop `wp_acrossai_abilities` → reactivate to get the new column.
3. Run `npm run build` after editing `AbilitiesList.jsx`.
4. Run full quality gate: `composer phpcs && composer phpstan && npm run build`.
