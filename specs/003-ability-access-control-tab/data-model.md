# Data Model: Ability Access Control Tab

**Phase**: 1 | **Date**: 2026-05-16 | **Plan**: [plan.md](plan.md)

## Overview

This feature adds no new entities to the plugin's data model. All persistence is delegated to the
`wpboilerplate/wpb-access-control` vendor library, which owns its own database table and REST API.

---

## Existing Entities (unchanged)

### Ability Override (`AcrossAI_Sitewide_Row`)

Stored in `{prefix}acrossai_abilities`. The `slug` column is the stable identifier passed as
`resourceKey` to the `AccessControl` component.

| Column | Type | Notes |
|---|---|---|
| `slug` | `varchar(255)` | Unique ability identifier; used as `resourceKey` |
| `site_allowed` | `tinyint(1) NULL` | Tri-state override |
| `readonly` | `tinyint(1) NULL` | Tri-state override |
| `destructive` | `tinyint(1) NULL` | Tri-state override |
| `idempotent` | `tinyint(1) NULL` | Tri-state override |
| `show_in_rest` | `tinyint(1) NULL` | Tri-state override |
| `show_in_mcp` | `tinyint(1) NULL` | Tri-state MCP override |
| `mcp_type` | `varchar(50) NULL` | MCP type override |
| `mcp_servers` | `longtext NULL` | JSON-encoded MCP server list |

---

## Vendor-Owned Entity (read via REST, not owned by plugin)

### Access Rule (`wpb_access_control`)

Stored in `{prefix}wpb_access_control`, owned and managed exclusively by the `wpb-access-control`
vendor library. The plugin has no direct ORM access to this table.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint` | Auto-increment primary key |
| `namespace` | `varchar(100)` | Plugin scope; always `"acrossai-abilities"` for this plugin |
| `resource_key` | `varchar(255)` | Ability slug |
| `provider` | `varchar(100)` | Provider type: `"wp_role"`, `"wp_user"`, `"everyone"`, `"no_one"` |
| `provider_value` | `longtext NULL` | JSON-encoded provider config (role slugs, user IDs) |
| `created_at` | `datetime` | Row creation timestamp |
| `updated_at` | `datetime` | Row update timestamp |

**Access pattern**: All reads and writes go through `wpb-ac/v1` REST routes. The plugin plugin never
queries this table directly.

---

## Data Flow

```
User selects rule in AccessControl tab
        │
        ▼
AccessControl component (vendor)
        │  apiFetch PUT/PATCH
        ▼
wpb-ac/v1 REST routes
(registered by AcrossAI_Sitewide_Access_Control::register_rest_api()
 via AccessControlManager, hooked on rest_api_init in Main.php)
        │
        ▼
{prefix}wpb_access_control table
```

The ability `slug` flows from `AbilityEditPanel` props → `resourceKey` prop → REST route parameter.
