# Exposure Collections Contract

## Exposure Types

Supported collection types:

- `tool`
- `resource`
- `prompt`

## Authorization

- Exposure collections are administrator-only discovery endpoints protected by `manage_options`.
- Spec 009 does not define a separate runtime, internal, or anonymous discovery tier for these collections.

## Inclusion Rules

An ability is included in an exposure collection only if:

- it is a valid unified-table row
- `source = db`
- `status = publish`
- `show_in_mcp = true`
- `mcp_type` matches the requested collection type
- any server allowlist constraint accepts the current server context
- missing or unknown server context fails closed for server-scoped MCP results

## Output Shape

Each returned item includes enough metadata for machine-consumable clients to identify, describe, and safely classify the ability. Fields follow WordPress REST API snake_case conventions.

Canonical fields:

- `ability_slug` — full `namespace/name` identifier
- `label` — human-readable display name
- `description` — ability description
- `category` — WP Abilities API category slug
- `mcp_type` — `tool` | `resource` | `prompt`
- `mcp_servers` — server allowlist array (null/empty = unrestricted)
- `input_schema` — JSON Schema Draft 7 for inputs (null if not defined)
- `output_schema` — JSON Schema Draft 7 for outputs (null if not defined)
- `readonly` — tri-state annotation (null | 0 | 1)
- `destructive` — tri-state annotation (null | 0 | 1)
- `idempotent` — tri-state annotation (null | 0 | 1)

Execution configuration (`callback_type`, `callback_config`) and audit fields are intentionally excluded from exposure responses to minimise metadata exposure surface.

Because this endpoint is administrator-only, the response may include source/provider discovery metadata required by management clients without creating a broader runtime discovery surface.

## Filtering Rule

Exposure filtering belongs in the Abilities query/business-logic layer rather than in the REST controller so browse responses and collection metadata remain consistent.
