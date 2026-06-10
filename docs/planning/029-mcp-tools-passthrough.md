# Planning: MCP Tools Pass-through Settings — Tri-State Column + `mcp_adapter_server_config` Bridge (Feature 029)

Add a per-ability setting that lets a site admin promote an ability into the `tools` array of
**every** MCP server registered by the sibling `mcp-adapter` plugin. The flag is stored as a
**tri-state tinyint column** on the existing `acrossai_abilities` BerlinDB table
(`NULL` = default behavior, `1` = always pass as tool). At runtime, a new module hooks the
existing `mcp_adapter_server_config` filter (defined in `mcp-adapter`) and merges the opted-in
ability slugs into `$config['tools']` for each server, deduplicating against whatever the server
already declared.

The admin surface is a new toggle column in the existing React-based Abilities list
(`src/js/abilities/components/AbilitiesList.jsx`). No new admin page, no new REST namespace —
the existing `acrossai-abilities-manager/v1/abilities/{slug}` sparse-update route handles writes
for the new column the same way it already handles every other tri-state column
(`site_allowed`, `show_in_rest`, `show_in_mcp`, `readonly`, `destructive`, `idempotent`).

The main goal is strict:

> If `pass_as_tool === 1` for an ability, that ability's slug MUST appear in every MCP server's
> `$config['tools']` after `mcp_adapter_server_config` fires. If the column is `NULL`, the MCP
> server's own `tools` array stands untouched.

This is an early-stage plugin: schema migration is **not** required. The user will
deactivate the plugin, drop the table manually, and reactivate — BerlinDB will issue a clean
`CREATE TABLE` with the new column on activation.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit-git-feature "029-mcp-tools-passthrough"

# 2. Specify
/speckit-specify "Add a tri-state tinyint column 'pass_as_tool' to the acrossai_abilities table
that mirrors the six-key shape of 'site_allowed' (name, type tinyint, length 1, allow_null true,
default null). Wire the column through the existing tri-state plumbing: Row property + cast,
Sanitizer write path, Query prepare_fields_for_write, Formatter response/exposure/merged blocks.

Create a new module includes/Modules/McpToolsPassthrough/ that hooks the
'mcp_adapter_server_config' filter at priority 10 with two accepted args. The callback queries
AcrossAI_Abilities_Query::get_pass_as_tool_slugs() and merges the result into
\$config['tools'] using array_unique, preserving existing entries.

Surface the toggle in src/js/abilities/components/AbilitiesList.jsx as a new column 'Pass as Tool'
that flips between null and 1 by POSTing to the existing
acrossai-abilities-manager/v1/abilities/{slug} sparse-update endpoint. No new REST namespace,
no new admin page, no DB migration (table is recreated manually on activation)."
```

---

## Background — what is already done; do NOT redo it

| # | Fact | How to verify |
|---|------|---------------|
| B-1 | The BerlinDB tri-state column shape is locked in `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` L111–118 (`site_allowed`). Same six keys (`name`, `type`, `length`, `allow_null`, `default`) are reused by `show_in_rest` (L154–160), `show_in_mcp` (L161–167), `readonly` (L187–193), `destructive` (L194–200), `idempotent` (L201–207) | read `AcrossAI_Abilities_Schema.php` |
| B-2 | BerlinDB v3 has a double-primary-key bug. `id` MUST NOT carry the `primary` flag; the PRIMARY KEY is declared exclusively through `$indexes` (Schema L254–260). Captured durably in `docs/memory/BUGS.md::BUG-BERLINDB-V3-DOUBLE-PRIMARY` | read `docs/memory/BUGS.md` |
| B-3 | BerlinDB v3 quotes `CURRENT_TIMESTAMP` as a literal string in the column `default`. Timestamp behavior MUST use the BerlinDB `created`/`modified` flags instead. Captured in `BUG-BERLINDB-V3-TIMESTAMP-QUOTING` | read `docs/memory/BUGS.md` |
| B-4 | Tri-state Row casting lives in `AcrossAI_Abilities_Row.php` L284 `$tri_state_fields` array. Casting helper is `AcrossAI_Sanitizer::cast_tri_state()` | read `AcrossAI_Abilities_Row.php` |
| B-5 | Tri-state write sanitization lives in `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` L296 (`sanitize_create_request`) and L322 (`sanitize_update_request`). Helper is `sanitize_tri_state()` | read `AcrossAI_Abilities_Sanitizer.php` |
| B-6 | Tri-state DB-write casting (PHP bool → 1/0) lives in `AcrossAI_Abilities_Query.php` L619 inside `prepare_fields_for_write()`. The Query method `by_mcp_type()` at L469 is the precedent pattern for filtering by a tinyint column | read `AcrossAI_Abilities_Query.php` |
| B-7 | The Formatter is the single source of truth for the REST response shape (no formal `get_item_schema()`). Three insertion points: `format_for_response()` L49, `format_for_exposure()` L90 block, `format_merged_ability()` L139 — all already carry `site_allowed` | read `includes/Utilities/AcrossAI_Abilities_Formatter.php` |
| B-8 | The `mcp_adapter_server_config` filter is fired exactly once in the sibling `mcp-adapter` plugin at `mcp-adapter/includes/Core/McpServer.php:171` with signature `apply_filters( 'mcp_adapter_server_config', array $config, string $server_id )`. `$config['tools']` is a `string[]` of ability names | read `mcp-adapter/includes/Core/McpServer.php` |
| B-9 | No callsite or callback for `mcp_adapter_server_config` currently exists anywhere inside `acrossai-abilities-manager` (or in sibling plugins like `abilities-bridge`, `acrossai-core-abilities`, `acrossai-mcp-manager`, `enable-abilities-for-mcp`). Feature 029 is the first consumer | grep `mcp_adapter_server_config` across plugins |
| B-10 | The Abilities admin list React entry is `src/js/abilities/components/AbilitiesList.jsx`. Default visible columns at L150–159, persisted in localStorage key `acrossai_abilities_columns` (L172). `McpCell` badge column (L112–122) is the closest visual analogue; status `<select>` row action (L823–849) is the closest interactive analogue | read `AbilitiesList.jsx` |
| B-11 | The REST write path is `POST /wp-json/acrossai-abilities-manager/v1/abilities/{slug}` (sparse JSON body, no PATCH). Client at `src/js/abilities/api/client.js:83–89`; route registered at `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php:149`. No new REST namespace is needed | read both files |
| B-12 | Protected abilities (`mcp-adapter/discover-abilities`, `mcp-adapter/get-ability-info`, `mcp-adapter/execute-ability`) are excluded from all writes by `AcrossAI_Protected_Abilities::get_protected_slugs()` (404/403 server-side). No special handling is required for `pass_as_tool` — the protected guard already rejects the entire row | read `AcrossAI_Protected_Abilities.php` |
| B-13 | `AcrossAI_Abilities_Query::PROTECTED_FIELDS` (L113–123) is the column-level immutable list for registry rows. `site_allowed` is intentionally NOT in this list because users must be able to override allow/block on registry abilities. `pass_as_tool` follows the same rule — it is an override-style flag and MUST NOT be added there | read `AcrossAI_Abilities_Query.php` |
| B-14 | The plugin uses a Loader pattern for hooks (`includes/Main.php`). All module hooks are wired via `$this->loader->add_action()` / `->add_filter()` from `Main.php`; modules MUST NOT call `add_action()`/`add_filter()` in their own classes | read `includes/Main.php` |
| B-15 | Constitution v1.4.4 forbids abstract module base classes and `includes/Base/`. New modules follow the singleton + Loader pattern established by `AbilityAPI`, `Abilities`, `Logger` | read `.specify/memory/CONSTITUTION.md` |

---

## Architecture Choice — Schema Column + Filter Bridge (Option A)

Use **Option A: schema column on the abilities table + thin filter bridge module** as the
approved approach.

Why:

- The shape is already proven six times in the codebase (every existing tri-state column).
  Risk surface is near zero: clone `site_allowed` verbatim and follow the same plumbing trail
  through Row, Sanitizer, Query, Formatter.
- Per-ability storage co-locates the flag with the rest of the ability's persistent state
  (status, site_allowed, show_in_mcp, etc.) — admins manage one row, not two surfaces.
- The filter bridge is a single-purpose module with one filter callback and one DB lookup; it
  has no dependencies on `mcp-adapter` and is a no-op when the filter never fires.
- The existing REST sparse-update path already accepts tri-state fields generically through
  the Sanitizer — no new endpoint, no new route schema, no admin page.

Rejected alternatives:

| Option | Reason not selected |
|---|---|
| Standalone option `acrossai_pass_as_tool_slugs` (array) | Bifurcates ability state across the table + an option. Loses per-ability admin UX. No payoff. |
| New table `acrossai_mcp_tool_passthrough` | Migration cost and a JOIN at filter time. Tri-state column on the existing row is strictly cheaper. |
| Hook `mcp_adapter_init_servers` to mutate server registration directly | Filter runs after server registration; the hook is the wrong layer. `mcp_adapter_server_config` is the documented integration point. |
| Per-server allowlist column (e.g. `pass_as_tool_servers longtext` JSON) | YAGNI for v1. Recorded as a future migration path in the DECISIONS entry — current column can be replaced without UI changes if per-server granularity is ever required. |

Future compatibility:

- If per-server granularity is needed later, replace the tinyint with a `longtext` JSON column
  storing `null | true | string[]` and update only the filter callback and the toggle cell. The
  REST shape can stay tri-state-like with a richer payload.
- If `mcp-adapter` ever changes the filter signature, only the new module's `inject_tools()`
  method needs to change.

---

## Module and Namespace

Create a new module:

```text
includes/Modules/McpToolsPassthrough/
```

Use this namespace:

```php
AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough
```

Recommended class names:

| Concern | Class |
|---|---|
| Filter callback + slug merge | `AcrossAI_Mcp_Tools_Passthrough` |

No REST controller, no admin partial, no React entry are added by this module — the existing
Abilities REST controller and the existing React list page absorb the new column.

Schema/Row/Query/Sanitizer/Formatter changes stay inside their owning modules
(`includes/Modules/Abilities/*` and `includes/Utilities/*`).

---

## Data Model

### New column

`pass_as_tool` is a tri-state tinyint on `acrossai_abilities`. Three semantic states:

| Value | Meaning |
|---|---|
| `NULL` | Default — do nothing. The MCP server's own `tools` array stands. |
| `1` | Always pass — slug is injected into every MCP server's `$config['tools']`. |
| `0` | Reserved for an explicit "never pass" future state. Not surfaced in the UI in v1; the toggle only flips between `NULL` ↔ `1`. |

This mirrors `show_in_mcp` exactly. Casting flows through `AcrossAI_Sanitizer::cast_tri_state()`
(PHP `bool|null`) on read and `sanitize_tri_state()` (DB `1|0|NULL`) on write.

### Schema entry (verbatim)

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

Storage rules:

- Do NOT add an index in `$indexes`. The filter scan runs once per MCP-server-init request and
  the row count is bounded by the number of registered abilities (tens, not millions).
- Do NOT add `'primary' => true` on the column. PRIMARY KEY is declared exclusively in
  `$indexes` (B-2).
- Do NOT use `CURRENT_TIMESTAMP` or any literal-string default (B-3).
- Do NOT bump `$version` in `AcrossAI_Abilities_Table.php`. The table is recreated manually on
  activation; `maybe_upgrade()` is not exercised for this feature.

### Query API

New finder method on `AcrossAI_Abilities_Query`:

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

Rules:

- BerlinDB resolves `pass_as_tool => 1` against the column natively — no custom WHERE.
- `number => 0` is intentional; pagination at the filter layer would be wrong.
- `array_filter` drops any empty slugs defensively.

### Filter contract

`mcp_adapter_server_config` signature (defined in `mcp-adapter`):

```php
apply_filters( 'mcp_adapter_server_config', array $config, string $server_id ): array
```

Manager-side callback:

```php
add_filter( 'mcp_adapter_server_config', array( $this, 'inject_tools' ), 10, 2 );
```

Merge rule:

1. Look up opted-in slugs via `AcrossAI_Abilities_Query::get_pass_as_tool_slugs()`.
2. If empty, return `$config` byte-for-byte unchanged.
3. Else: `$config['tools'] = array_values( array_unique( array_merge( $existing, $extra ) ) )`.
4. Never replace, always merge. Never reorder existing entries beyond `array_unique` semantics.

---

## Implementation Changes

### CHANGE-1 — MODIFY `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`

Append a new column array next to `show_in_mcp` (after L167) using the exact six-key shape of
`site_allowed`:

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

Key rules:

- No `primary` flag on the column.
- No new entry in `$indexes`.
- No `CURRENT_TIMESTAMP` or literal-string default.
- No `$version` bump in `AcrossAI_Abilities_Table.php`.

### CHANGE-2 — MODIFY `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php`

Four edits, all mirroring `site_allowed`:

1. Add `@property bool|null $pass_as_tool` to the class docblock (next to existing tri-state
   `@property` lines around L36).
2. Add `public $pass_as_tool = null;` to the property block (alongside L112 `$site_allowed`).
3. Add `'pass_as_tool'` to `$tri_state_fields` in the Row constructor (L284) so the value is
   cast to `bool|null` via `AcrossAI_Sanitizer::cast_tri_state()`.
4. Add `'pass_as_tool'` to the `get_json_fields()` blocklist (L235) so the scalar tinyint is
   never JSON-decoded.

### CHANGE-3 — MODIFY `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`

Add `'pass_as_tool'` to the `$tri_state_fields` array at L296 inside `sanitize_create_request()`
and the matching block in `sanitize_update_request()` (L322 if separate):

```php
$tri_state_fields = array(
    'site_allowed',
    'readonly',
    'destructive',
    'idempotent',
    'show_in_rest',
    'show_in_mcp',
    'pass_as_tool', // NEW: opt-in to mcp_adapter_server_config tools[] injection.
);
```

Key rules:

- Do NOT add `'pass_as_tool'` to `AcrossAI_Abilities_Query::PROTECTED_FIELDS` (B-13).
- Reuse the existing `sanitize_tri_state()` helper — no bespoke sanitization.

### CHANGE-4 — MODIFY `includes/Utilities/AcrossAI_Abilities_Formatter.php`

Three insertion points, all next to the existing `site_allowed` keys:

1. `format_for_response()` L49 — add `'pass_as_tool' => $row->pass_as_tool,`.
2. `format_for_exposure()` L90 block — add `'pass_as_tool' => $merged['pass_as_tool'] ?? null,`.
3. `format_merged_ability()` L139 — add `'pass_as_tool' => $merged['pass_as_tool'] ?? null,`.

Key rules:

- The formatter array IS the schema (no formal `get_item_schema()`).
- All three insertion points are required — exposure and merged outputs feed MCP clients and
  the override-merging path respectively.

### CHANGE-5 — MODIFY `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`

Two edits:

1. `prepare_fields_for_write()` L619 — add `'pass_as_tool'` to the `$tri_state` array so PHP
   `bool true/false` cast cleanly to DB `1`/`0`.
2. Add a new finder method placed next to `by_mcp_type()` at L469:

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

Key rules:

- No custom WHERE — BerlinDB resolves the column predicate natively.
- `number => 0` (no pagination) is intentional.
- Do NOT add `'pass_as_tool'` to `PROTECTED_FIELDS` (B-13).

### CHANGE-6 — NEW `includes/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough.php`

The thin filter bridge:

```php
<?php
namespace AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Bridges the per-ability pass_as_tool flag into the mcp-adapter server-config filter.
 */
class AcrossAI_Mcp_Tools_Passthrough {

    protected static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Inject opted-in ability slugs into every MCP server's tools[] array.
     *
     * @param array  $config    Server config passed by mcp-adapter.
     * @param string $server_id Server identifier (reserved for future per-server logic).
     * @return array
     */
    public function inject_tools( array $config, string $server_id ): array {
        $extra = ( new AcrossAI_Abilities_Query() )->get_pass_as_tool_slugs();
        if ( empty( $extra ) ) {
            return $config;
        }
        $existing        = isset( $config['tools'] ) && is_array( $config['tools'] ) ? $config['tools'] : array();
        $config['tools'] = array_values( array_unique( array_merge( $existing, $extra ) ) );
        return $config;
    }
}
```

Key rules:

- Singleton + Loader pattern (B-14, B-15). No `add_filter()` inside the class.
- Priority MUST be 10 with `accepted_args = 2` (B-8).
- Always merge `$config['tools']`, never replace.
- `array_unique` prevents double-registration when the server already listed the ability.
- Do NOT short-circuit when `mcp-adapter` is missing — `add_filter()` is a no-op when nothing
  fires it.

### CHANGE-7 — MODIFY `includes/Main.php`

Inside the existing public/runtime hook wiring area (next to other Abilities module hooks):

```php
$mcp_tools_passthrough = \AcrossAI_Abilities_Manager\Includes\Modules\McpToolsPassthrough\AcrossAI_Mcp_Tools_Passthrough::instance();
$this->loader->add_filter( 'mcp_adapter_server_config', $mcp_tools_passthrough, 'inject_tools', 10, 2 );
```

Key rules:

- Goes through `$this->loader->add_filter()` (B-14).
- Resolve the singleton into a named variable before the Loader call.
- Do NOT call `add_filter()` directly in `AcrossAI_Mcp_Tools_Passthrough`.

### CHANGE-8 — MODIFY `src/js/abilities/components/AbilitiesList.jsx`

Mirror the `McpCell` badge column (L112–122) but render an interactive toggle.

1. Add `pass_as_tool` to the default visible-columns list (L150–159).
2. Persist its visibility in the existing `acrossai_abilities_columns` localStorage entry
   (L172) — no new key.
3. Add a header label "Pass as Tool" next to the existing "MCP" header (L638–700).
4. Add a `PassAsToolCell` component that toggles between two states (`null` ↔ `1`). On click,
   dispatch:

```js
dispatch.updateAbility(item.ability_slug, { pass_as_tool: nextValue });
```

The existing `updateAbility` path POSTs to `acrossai-abilities-manager/v1/abilities/{slug}`
(`src/js/abilities/api/client.js:83–89`). No client changes required beyond the new cell.

Key rules:

- Do NOT introduce the `0` state in the UI. Only `null` (default) and `1` (opted-in) are
  surfaced in v1.
- Reuse the existing toast / error handling pattern from the status `<select>` dropdown
  (L823–849) for consistency.
- Protected abilities (`mcp-adapter/*`) MUST render the cell in a disabled state; the
  server-side guard (B-12) rejects writes regardless, so the disabled state is purely a UX hint.
- All strings use `__()` with text domain `acrossai-abilities-manager`.

### CHANGE-9 — UPDATE `docs/memory/DECISIONS.md` + `docs/memory/INDEX.md`

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

Add a routing row to `docs/memory/INDEX.md`:

```text
| D | DEC-MCP-TOOLS-PASSTHROUGH-COLUMN | mcp,tools,abilities,filter | DECISIONS.md | active | Per-ability tri-state column wired to mcp_adapter_server_config |
```

### CHANGE-10 — UPDATE `.specify/memory/CONSTITUTION.md`

Bump patch version (e.g. `1.4.4 → 1.4.5`) and add a one-line note that ability ↔ MCP tool
injection is configurable per-ability via `pass_as_tool`. Update the footer version block.

Key rules:

- Follow the existing version-bump pattern from earlier features (no new sections, just a line).
- Do NOT restate the filter implementation in the Constitution; the DECISIONS entry is the
  durable record.

---

## What must NOT change

- Do **not** bump `$version` in `AcrossAI_Abilities_Table.php`. The table is recreated manually
  on activation; `maybe_upgrade()` is not exercised.
- Do **not** add the `'primary'` flag to any column. PRIMARY KEY is declared exclusively in
  `$indexes` (B-2).
- Do **not** add `'pass_as_tool'` to `AcrossAI_Abilities_Query::PROTECTED_FIELDS`. It is an
  override-style flag and must remain editable on registry rows (B-13).
- Do **not** modify `AcrossAI_Protected_Abilities::get_protected_slugs()`. The three
  `mcp-adapter/*` ability slugs stay excluded; the slug-level guard already covers them (B-12).
- Do **not** introduce a new REST namespace. Use the existing
  `acrossai-abilities-manager/v1/abilities/{slug}` sparse update (B-11).
- Do **not** introduce a new admin page. The toggle lives in the existing Abilities list.
- Do **not** introduce an abstract base class or `includes/Base/` directory (B-15).
- Do **not** call `add_filter()` directly inside the new module class; route through the
  Loader from `includes/Main.php` (B-14).
- Do **not** replace `$config['tools']`; always merge with `array_unique`.
- Do **not** add a hard runtime dependency on `mcp-adapter`. The new module is a no-op when
  the filter never fires.
- Do **not** add a JSON schema for the `pass_as_tool` field on the write controller routes —
  the existing tri-state sanitization path is generic (consistent with how `site_allowed` is
  handled today).
- Do **not** introduce the `0` (explicit deny) state in the UI for v1; only `null` ↔ `1`.

---

## Constraints

- New module folder: `includes/Modules/McpToolsPassthrough/`.
- New filter callback wired via the Loader in `includes/Main.php`.
- Schema/Row/Query/Sanitizer/Formatter edits stay inside their existing owning modules.
- Storage uses the existing abilities table; no option, no transient.
- `composer dump-autoload` must be run once after the new PHP file exists.
- `npm run build` must regenerate `build/js/abilities.js` and `build/js/abilities.asset.php`
  after the React change.
- PHPCS, PHPStan, and Plugin Check must remain clean for changed production files.
- Manual table recreation flow: deactivate plugin → drop `wp_acrossai_abilities` → reactivate.

Expected file changes:

- MOD `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`
- MOD `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php`
- MOD `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`
- MOD `includes/Utilities/AcrossAI_Abilities_Sanitizer.php`
- MOD `includes/Utilities/AcrossAI_Abilities_Formatter.php`
- NEW `includes/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough.php`
- MOD `includes/Main.php`
- MOD `src/js/abilities/components/AbilitiesList.jsx`
- MOD `docs/memory/DECISIONS.md`
- MOD `docs/memory/INDEX.md`
- MOD `.specify/memory/CONSTITUTION.md`

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

### Schema and storage

- [ ] After plugin reactivation, `DESCRIBE wp_acrossai_abilities` shows
      `pass_as_tool tinyint(1) DEFAULT NULL`.
- [ ] `SHOW CREATE TABLE wp_acrossai_abilities` confirms PRIMARY KEY is declared exactly once
      (BerlinDB v3 double-primary regression check).
- [ ] No `[09-Jun-2026]` style "Incorrect table definition" errors in `wp-content/debug.log`
      after activation.
- [ ] Inserting a row with `pass_as_tool = NULL` and another with `pass_as_tool = 1` round-trip
      without warnings.
- [ ] `$version` in `AcrossAI_Abilities_Table.php` is unchanged from main.

### REST round-trip

- [ ] `GET /wp-json/acrossai-abilities-manager/v1/abilities/{slug}` returns the `pass_as_tool`
      key.
- [ ] `POST /wp-json/acrossai-abilities-manager/v1/abilities/{slug}` with
      `{ "pass_as_tool": 1 }` persists `1` and the next GET returns `true`.
- [ ] `POST ... { "pass_as_tool": null }` clears the value back to `null`.
- [ ] `POST ... { "pass_as_tool": "garbage" }` is sanitized to `null` (not stored as `1`).
- [ ] Protected slugs (e.g. `mcp-adapter/discover-abilities`) return 404/403 on POST as
      before — no new error path.

### Query plumbing

- [ ] `(new AcrossAI_Abilities_Query())->get_pass_as_tool_slugs()` returns only opted-in
      slugs.
- [ ] With zero opted-in rows, the method returns `array()` (not `null`, not `false`).
- [ ] The returned array contains no empty strings (defensive `array_filter`).
- [ ] `prepare_fields_for_write()` casts PHP `true` → DB `1`, `false` → DB `0`,
      `null` → DB `NULL` for `pass_as_tool`.

### MCP filter integration

- [ ] `apply_filters( 'mcp_adapter_server_config', array( 'tools' => array( 'existing/slug' ) ), 'test-server' )`
      returns the union of existing + opted-in slugs.
- [ ] No duplicate slugs appear in the returned `tools` array even if the server already
      listed an opted-in slug.
- [ ] When no rows are flagged, the filter leaves `$config` byte-for-byte unchanged.
- [ ] When `$config['tools']` is missing entirely, the filter creates it from the opted-in
      slugs without notice/warning.
- [ ] The filter callback runs at priority 10 with `accepted_args = 2`.

### Admin UI

- [ ] The "Pass as Tool" column renders in the Abilities list with the toggle button visible.
- [ ] Clicking the toggle on a non-protected ability flips the value and the UI reflects the
      new state without a full reload.
- [ ] Refreshing the page restores the saved state from the server.
- [ ] Protected abilities (`mcp-adapter/discover-abilities` etc.) render the column in a
      disabled state; clicking it is a no-op.
- [ ] Column visibility persists across reloads (localStorage key
      `acrossai_abilities_columns`).
- [ ] All UI strings use `__()` with text domain `acrossai-abilities-manager`.

### Cross-plugin sanity

- [ ] Flip `pass_as_tool=1` on an ability from `acrossai-core-abilities` (e.g.
      `acrossai-core-abilities/transient-flush`).
- [ ] Register a test MCP server in `mcp-adapter` with an empty `tools` array. Confirm the
      flagged ability appears in the server's runtime tools list.
- [ ] Register a second MCP server. Confirm the same flagged ability appears on the second
      server's tools list (global injection, not per-server).
- [ ] Disable the flag (set back to `NULL`). Confirm the ability no longer appears in either
      server's tools list.

### Quality gates

- [ ] `composer dump-autoload` succeeds.
- [ ] `composer run phpcs` succeeds for changed PHP files.
- [ ] `composer run phpstan` succeeds.
- [ ] `composer phpunit -- tests/phpunit/abilities` green.
- [ ] `npm run build` emits a regenerated `build/js/abilities.js` and
      `build/js/abilities.asset.php`.
- [ ] Plugin Check remains green.
- [ ] `docs/memory/INDEX.md` row added and references the new DECISIONS.md entry by ID.
- [ ] `.specify/memory/CONSTITUTION.md` version footer is bumped consistently.
- [ ] `/speckit.architecture-guard.architecture-review` reports no Constitution violations.
