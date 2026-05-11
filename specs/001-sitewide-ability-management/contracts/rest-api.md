# REST API Contracts: Sitewide Ability Management

**Phase**: 1 | **Date**: 2026-05-11 | **Plan**: [../plan.md](../plan.md)

**Namespace**: `acrossai-abilities-manager/v1`
**Base path**: `/wp-json/acrossai-abilities-manager/v1`
**Authentication**: `X-WP-Nonce` header (value = `wp_create_nonce('wp_rest')`)
**Authorization**: `manage_options` capability required on ALL endpoints

---

## Endpoint Index

| Method | Path | Description |
|---|---|---|
| GET | `/sitewide/abilities` | Paginated, searchable ability list |
| GET | `/sitewide/abilities/{slug}` | Single effective ability |
| POST | `/sitewide/abilities/{slug}` | Save override fields |
| DELETE | `/sitewide/abilities/{slug}` | Reset (delete) override |
| POST | `/sitewide/abilities/{slug}/toggle` | Quick toggle site_allowed |
| POST | `/sitewide/abilities/bulk` | Bulk action on current-page slugs |
| GET | `/sitewide/mcp-servers` | Available MCP server list |

---

## GET /sitewide/abilities

Returns a paginated, merged (registry + override) list of all registered abilities.

### Request Parameters

| Name | Type | Default | Description |
|---|---|---|---|
| `page` | int | 1 | Page number (1-based) |
| `per_page` | int | 20 | Items per page (1–100) |
| `search` | string | '' | Full-text filter on slug, label, provider |
| `orderby` | string | 'slug' | Column to sort: `slug`, `provider`, `source`, `status` |
| `order` | string | 'asc' | Sort direction: `asc`, `desc` |
| `source` | string | '' | Filter by source: `plugin`, `theme`, `core`, `db` |
| `has_override` | bool | – | If `true`, return only abilities with an active override |

### Response `200 OK`

```json
{
  "abilities": [
    {
      "id": "my-plugin/read-posts",
      "slug": "my-plugin/read-posts",
      "provider": "my-plugin",
      "source": "plugin",
      "label": "Read Posts",
      "category": "content",
      "site_allowed": null,
      "readonly": null,
      "destructive": false,
      "idempotent": true,
      "show_in_rest": null,
      "show_in_mcp": null,
      "mcp_type": null,
      "mcp_servers": null,
      "has_override": false,
      "updated_at": null,
      "updated_by": null,
      "_registry": {
        "site_allowed": null,
        "readonly": null,
        "destructive": false,
        "idempotent": true,
        "show_in_rest": null,
        "show_in_mcp": null,
        "mcp_type": null
      }
    }
  ],
  "total": 142,
  "pages": 8
}
```

### Response Headers

```
X-WP-Total: 142
X-WP-TotalPages: 8
```

---

## GET /sitewide/abilities/{slug}

Returns a single effective ability (registry merged with override if any).

### Path Parameter

| Name | Type | Required | Description |
|---|---|---|---|
| `slug` | string | ✅ | URL-encoded ability slug (`my-plugin%2Fread-posts`) |

### Response `200 OK`

```json
{
  "slug": "my-plugin/read-posts",
  "provider": "my-plugin",
  "source": "plugin",
  "label": "Read Posts",
  "category": "content",
  "site_allowed": null,
  "readonly": null,
  "destructive": false,
  "idempotent": true,
  "show_in_rest": null,
  "show_in_mcp": null,
  "mcp_type": null,
  "mcp_servers": null,
  "has_override": false,
  "updated_at": null,
  "updated_by": null,
  "_registry": { }
}
```

### Response `404 Not Found`

```json
{
  "code": "ability_not_found",
  "message": "Ability 'my-plugin/read-posts' is not registered.",
  "data": { "status": 404 }
}
```

---

## POST /sitewide/abilities/{slug}

Save override fields for an ability. Implements override-only storage: only fields that differ
from registry defaults are written. If no fields differ, no record is created/updated and
`unchanged: true` is returned (FR-024, FR-025).

### Path Parameter

| Name | Type | Required |
|---|---|---|
| `slug` | string | ✅ |

### Request Body (JSON)

```json
{
  "site_allowed": null,
  "readonly": null,
  "destructive": false,
  "idempotent": true,
  "show_in_rest": null,
  "show_in_mcp": null,
  "mcp_type": null,
  "mcp_servers": null
}
```

All fields are optional. Send only fields being changed. Omitted fields are not updated.

### Field Constraints

| Field | Type | Allowed Values |
|---|---|---|
| `site_allowed` | bool\|null | `true`, `false`, `null` |
| `readonly` | bool\|null | `true`, `false`, `null` |
| `destructive` | bool\|null | `true`, `false`, `null` |
| `idempotent` | bool\|null | `true`, `false`, `null` |
| `show_in_rest` | bool\|null | `true`, `false`, `null` |
| `show_in_mcp` | bool\|null | `true`, `false`, `null` |
| `mcp_type` | string\|null | `"tool"`, `"resource"`, `"prompt"`, `null` |
| `mcp_servers` | string[]\|null | Array of server ID strings, or `null` |

### Response `200 OK` (fields saved)

```json
{
  "slug": "my-plugin/read-posts",
  "has_override": true,
  "site_allowed": false,
  "show_in_mcp": null,
  "mcp_servers": null,
  "unchanged": false
}
```

### Response `200 OK` (no changes — FR-025)

```json
{
  "slug": "my-plugin/read-posts",
  "unchanged": true,
  "message": "No changes detected. Ability already matches registry defaults."
}
```

### Response `404 Not Found`

```json
{
  "code": "ability_not_found",
  "message": "Ability 'my-plugin/read-posts' is not registered.",
  "data": { "status": 404 }
}
```

---

## DELETE /sitewide/abilities/{slug}

Resets (removes) all override fields for an ability — deletes the DB row. After deletion the
ability will reflect its registry defaults.

### Path Parameter

| Name | Type | Required |
|---|---|---|
| `slug` | string | ✅ |

### Response `200 OK`

```json
{
  "slug": "my-plugin/read-posts",
  "deleted": true
}
```

### Response `200 OK` (no override existed)

```json
{
  "slug": "my-plugin/read-posts",
  "deleted": false,
  "message": "No override existed for this ability."
}
```

---

## POST /sitewide/abilities/{slug}/toggle

Quick toggle: flip `site_allowed` between `true` and `false`. Does NOT touch other override fields.

### Path Parameter

| Name | Type | Required |
|---|---|---|
| `slug` | string | ✅ |

### Request Body (JSON)

```json
{
  "site_allowed": true
}
```

### Response `200 OK`

```json
{
  "slug": "my-plugin/read-posts",
  "site_allowed": true,
  "has_override": true
}
```

---

## POST /sitewide/abilities/bulk

Apply a bulk action to multiple abilities (current page only — FR-012).

### Request Body (JSON)

```json
{
  "slugs": ["my-plugin/read-posts", "my-plugin/write-posts"],
  "action": "allow"
}
```

### Action Values

| Value | Effect |
|---|---|
| `allow` | Set `site_allowed = true` for all slugs |
| `disallow` | Set `site_allowed = false` for all slugs |
| `reset` | Delete override row for all slugs |

### Constraints

- Maximum 100 slugs per request (matches `per_page` max)
- All slugs validated against registered abilities; unknown slugs returned in `skipped`

### Response `200 OK`

```json
{
  "processed": 2,
  "skipped": [],
  "errors": []
}
```

### Response `400 Bad Request` (invalid action)

```json
{
  "code": "invalid_action",
  "message": "Action must be one of: allow, disallow, reset.",
  "data": { "status": 400 }
}
```

---

## GET /sitewide/mcp-servers

Returns available MCP servers for the server multi-select in the edit panel.
Returns `[]` if `WP\MCP\Core\McpAdapter` is not available (graceful degradation).

### Response `200 OK` (MCP adapter present)

```json
{
  "servers": [
    { "id": "server-1", "label": "My MCP Server" },
    { "id": "server-2", "label": "Another Server" }
  ]
}
```

### Response `200 OK` (MCP adapter absent)

```json
{
  "servers": []
}
```

---

## Error Response Format

All error responses follow WordPress REST API convention:

```json
{
  "code": "machine_readable_code",
  "message": "Human-readable message.",
  "data": { "status": 400 }
}
```

## Security Requirements

- `X-WP-Nonce` header MUST be present; verified by WordPress REST authentication
- `permission_callback` MUST call `current_user_can('manage_options')`; return `false` = `403`
- All input strings sanitized via `sanitize_text_field()`, `sanitize_key()`, or `absint()`
- Tri-state booleans cast with `rest_sanitize_boolean()` or `null` when JSON null
- `mcp_servers` array: each element validated as non-empty string, max 100 items
- SQL: all queries via BerlinDB Query API (wraps `$wpdb->prepare()` internally)
