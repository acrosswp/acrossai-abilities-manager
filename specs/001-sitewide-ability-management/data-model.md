# Data Model: Sitewide Ability Management

**Phase**: 1 | **Date**: 2026-05-11 | **Plan**: [plan.md](plan.md)

---

## 1. Database Schema

### Table: `{prefix}acrossai_abilities_overwrite`

```sql
CREATE TABLE {prefix}acrossai_abilities_overwrite (
  id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ability_slug varchar(255)        NOT NULL,
  provider     varchar(100)        DEFAULT NULL,
  source       varchar(50)         DEFAULT NULL,  -- 'plugin'|'theme'|'core'|'db'
  site_allowed tinyint(1)          DEFAULT NULL,  -- 1=allowed, 0=disallowed, NULL=use registry
  readonly     tinyint(1)          DEFAULT NULL,
  destructive  tinyint(1)          DEFAULT NULL,
  idempotent   tinyint(1)          DEFAULT NULL,
  show_in_rest tinyint(1)          DEFAULT NULL,
  show_in_mcp  tinyint(1)          DEFAULT NULL,
  mcp_type     varchar(100)        DEFAULT NULL,  -- 'tool'|'resource'|'prompt'|NULL
  mcp_servers  longtext            DEFAULT NULL,  -- JSON array of server IDs | NULL=all
  created_at   datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by   bigint(20) unsigned DEFAULT NULL,
  updated_by   bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY   ability_slug (ability_slug)
) {$charset_collate};
```

**NULL semantics** (all nullable columns):
| Value | Meaning |
|---|---|
| `NULL` | No override — use registry default |
| `1` (tinyint) | Explicit `true` override |
| `0` (tinyint) | Explicit `false` override |
| `'tool'` etc. | Explicit string override |
| `'[]'` (JSON) | Explicit empty array (used for "disable all servers") |

**MCP visibility encoding**:
| Radio selection | `show_in_mcp` | `mcp_servers` |
|---|---|---|
| Keep as Default | `NULL` | `NULL` |
| Disable for MCP | `0` | `NULL` |
| Allow in all MCP servers | `1` | `NULL` |
| Allow in specific MCP servers | `1` | `["server-id-1","server-id-2"]` |

---

## 2. PHP Class Hierarchy

```
AcrossAI_Abilities_Manager\\Vendor\\BerlinDB\\Database\\Schema
  └── AcrossAI_Sitewide_Schema          (17 column definitions)

AcrossAI_Abilities_Manager\\Vendor\\BerlinDB\\Database\\Table
  └── AcrossAI_Sitewide_Table           ($name, $version, maybe_upgrade())

AcrossAI_Abilities_Manager\\Vendor\\BerlinDB\\Database\\Row
  └── AcrossAI_Sitewide_Row             (typed props, cast_tri_state())

AcrossAI_Abilities_Manager\\Vendor\\BerlinDB\\Database\\Query
  └── AcrossAI_Sitewide_Query           (get_override_by_slug, save_override, delete_override_by_slug)

AcrossAI_Abilities_Manager\\Includes\\Base\\AcrossAI_Module_Base  (abstract)
  └── AcrossAI_Sitewide_Module          (boot(), register_hooks(), get_name())

AcrossAI_Abilities_Manager\\Includes\\Utilities\\AcrossAI_Sanitizer
  (static methods: sanitize_ability_slug, sanitize_tri_state, sanitize_mcp_type, sanitize_mcp_servers_array)

AcrossAI_Abilities_Manager\\Includes\\Utilities\\AcrossAI_Ability_Merger
  (static methods: merge(registry, override): array, is_all_default(payload, registry): bool)

AcrossAI_Abilities_Manager\\Includes\\Modules\\Sitewide\\AcrossAI_Sitewide_Rest_Controller
  (REST_NAMESPACE, register(), get_abilities(), get_ability(), save_override(), delete_override(),
   toggle_ability(), bulk_action(), get_mcp_servers(), check_permission())
```

---

## 3. PHP Entity Shapes

### Registry Ability (from `wp_get_ability()`)

```php
// Array shape returned by WordPress Abilities API
[
  'slug'        => 'my-plugin/read-posts',  // string
  'provider'    => 'my-plugin',             // string — plugin/theme identifier
  'category'    => 'content',               // string|null
  'label'       => 'Read Posts',            // string|null
  'readonly'    => false,                   // bool|null
  'destructive' => false,                   // bool|null
  'idempotent'  => true,                    // bool|null
  'show_in_rest'=> true,                    // bool|null
  'show_in_mcp' => null,                    // bool|null
]
```

### Override Row (`AcrossAI_Sitewide_Row`)

```php
public int     $id;
public string  $ability_slug;
public ?string $provider;
public ?string $source;       // 'plugin'|'theme'|'core'|'db'
public ?bool   $site_allowed; // null=no override; true=allowed; false=disallowed
public ?bool   $readonly;
public ?bool   $destructive;
public ?bool   $idempotent;
public ?bool   $show_in_rest;
public ?bool   $show_in_mcp;
public ?string $mcp_type;     // 'tool'|'resource'|'prompt'|null
public ?string $mcp_servers;  // JSON string or null
public string  $created_at;
public string  $updated_at;
public ?int    $created_by;
public ?int    $updated_by;
```

### Effective Ability (output of `AcrossAI_Ability_Merger::merge()`)

```php
// Merged result returned to REST endpoint
[
  // Registry fields (always present)
  'slug'         => string,
  'provider'     => string,
  'source'       => string,    // detected: 'plugin'|'theme'|'core'|'db'
  'label'        => string|null,
  'category'     => string|null,

  // Override-aware fields (override wins when non-null, else registry default)
  'site_allowed' => bool|null, // null = no override (show registry default + "Default" badge)
  'readonly'     => bool|null,
  'destructive'  => bool|null,
  'idempotent'   => bool|null,
  'show_in_rest' => bool|null,
  'show_in_mcp'  => bool|null,
  'mcp_type'     => string|null,
  'mcp_servers'  => array|null, // decoded JSON or null

  // UI helpers
  'has_override' => bool,       // true if any override row exists for this slug
  'updated_at'   => string|null,
  'updated_by'   => int|null,

  // Registry defaults (for edit panel "before" comparison)
  '_registry'    => [
    'site_allowed' => bool|null,
    'readonly'     => bool|null,
    'destructive'  => bool|null,
    'idempotent'   => bool|null,
    'show_in_rest' => bool|null,
    'show_in_mcp'  => bool|null,
    'mcp_type'     => string|null,
  ],
]
```

---

## 4. Merge Algorithm (`AcrossAI_Ability_Merger::merge`)

```
For each overridable field F in [site_allowed, readonly, destructive, idempotent, show_in_rest, show_in_mcp, mcp_type, mcp_servers]:
  effective[F] = (override[F] !== null) ? override[F] : registry[F]

has_override = (override row exists for this slug)
```

### `is_all_default` Algorithm

```
Given a save payload and the registry ability:
  For each field F in payload:
    if payload[F] !== registry[F]: return false
  return true   // no-op save → do not write to DB
```

---

## 5. JavaScript State Shape

### Redux Store (`acrossai-abilities/sitewide`)

```js
{
  abilities: [],          // Effective ability objects (current page)
  total: 0,              // Total matching abilities across all pages
  pages: 0,              // Total page count
  currentPage: 1,
  isLoading: false,
  error: null,           // string|null
  editingSlug: null,     // string|null — ability currently open in drawer
  mcpServers: [],        // { id, label }[] — from GET /mcp-servers
}
```

### DataViews `view` State

```js
{
  type: 'table',
  search: '',
  page: 1,
  perPage: 20,
  sort: { field: 'slug', direction: 'asc' },
  fields: ['slug', 'provider', 'source', 'status'],  // visible columns
}
```

### Ability Object (JS, mirrors PHP Effective Ability)

```js
{
  id: 'my-plugin/read-posts',    // used as DataViews item id
  slug: 'my-plugin/read-posts',
  provider: 'my-plugin',
  source: 'plugin',
  label: 'Read Posts',
  site_allowed: null,            // null|true|false
  readonly: null,
  destructive: null,
  idempotent: null,
  show_in_rest: null,
  show_in_mcp: null,
  mcp_type: null,
  mcp_servers: null,             // null|string[]
  has_override: false,
  updated_at: null,
  updated_by: null,
  _registry: { /* original values */ },
}
```

---

## 6. Localized Data (`window.acrossaiAbilitiesSitewide`)

```js
{
  nonce:           string,   // wp_create_nonce('wp_rest')
  rest_url:        string,   // get_rest_url() base
  current_user_id: number,   // get_current_user_id()
}
```
