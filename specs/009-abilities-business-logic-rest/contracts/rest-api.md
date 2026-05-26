# REST API Contract

Namespace: `acrossai-abilities-manager/v1`

## Management Endpoints

### `GET /abilities`

Administrator-only paginated browse endpoint for unified abilities.

Query parameters:

- `search`
- `page`
- `per_page` (1-100)
- `orderby`
- `order`
- `source`
- `status`
- `category`
- `editable`

Response body:

A flat JSON array of ability items. Pagination metadata is delivered via response headers only (standard WordPress REST API convention).

```json
[
  {
    "id": 123,
    "ability_slug": "acrossai-abilities/example-ability",
    "label": "Example Ability",
    "description": "Example description.",
    "category": "custom",
    "status": "draft",
    "provider": null,
    "source": "db",
    "callback_type": "noop",
    "callback_config": {},
    "input_schema": null,
    "output_schema": null,
    "show_in_rest": true,
    "show_in_mcp": false,
    "mcp_type": null,
    "mcp_servers": [],
    "readonly": null,
    "destructive": null,
    "idempotent": null,
    "editable": true,
    "created_at": "2026-05-22T12:00:00Z",
    "updated_at": "2026-05-22T12:00:00Z",
    "created_by": 1,
    "updated_by": 1
  }
]
```

Headers:

- `X-WP-Total` — total number of matching abilities
- `X-WP-TotalPages` — total number of pages at the requested `per_page` size

### `GET /abilities/{id}`

Administrator-only single-item read endpoint. Returns the formatted full record or a not-found response.

### `POST /abilities`

Administrator-only create endpoint.

Rules:

- Accepts a `slug_suffix` field; the Write controller prepends `acrossai-abilities/` to form the stored `ability_slug`
- Sets `source = db`
- Defaults `status = draft` unless a valid explicit status is provided
- Returns the full persisted row (with `ability_slug` showing the full prefixed value)

### `POST /abilities/{id}`

Administrator-only sparse update endpoint.

Rules:

- Only submitted fields change
- `source`, `provider`, `created_at`, `updated_at`, `created_by`, and `updated_by` are server-controlled in every write path
- Non-database rows reject changes to identity and execution fields: `ability_slug`, `slug_suffix`, `label`, `description`, `category`, `status`, `source`, `callback_type`, `callback_config`, `input_schema`, and `output_schema`
- Non-database rows accept changes to exposure and annotation fields: `show_in_rest`, `show_in_mcp`, `mcp_type`, `mcp_servers`, `site_allowed`, `readonly`, `destructive`, and `idempotent`
- Response returns the full saved row, not the sparse request body

### `DELETE /abilities/{id}`

Administrator-only delete endpoint.

Rules:

- Allowed only for `source = db` rows
- Rejects delete attempts for inherited/non-database rows

## Discovery Endpoints

### `GET /abilities/categories`

Administrator-only read endpoint returning currently available ability categories.

> **Note**: Route is `/abilities/categories` (sub-resource of `/abilities`), not the earlier `/ability-categories` path used in some planning docs. The contract definition here takes precedence.

### `GET /abilities/exposures/{type}`

Administrator-only discovery endpoint for `tool`, `resource`, or `prompt` abilities.

Rules:

- Uses the same `manage_options` permission gate as all other Spec 009 endpoints
- Returns administrative discovery metadata only to authorized administrators; this feature does not define a broader runtime or internal permission tier for exposure collections
- Includes only valid published database-managed abilities matching the requested exposure type
- Applies server allowlist filtering when relevant
- Missing or unknown MCP server context fails closed for server-scoped results

## Error Contract

- `400`: validation or malformed query parameters
- `401` or `403`: unauthorized/forbidden based on caller context
- `404`: record not found
- `409`: duplicate slug or write conflict
- `500`: unexpected internal failure
