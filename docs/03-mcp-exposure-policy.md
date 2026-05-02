# Research 03: MCP Public Exposure and Multiple MCP Servers

## Question

What should the plugin do when making an ability public causes it to appear in all MCP servers?

## Current behavior

Today the plugin can override:

- `mcp_public`
- `mcp_type`

At runtime, those values are applied onto the shared ability object in the registry. That means:

- `mcp_public = true` becomes a **global registry-level flag**
- every MCP server reading that shared registry sees the same public value

## Core problem

The WordPress abilities registry is shared for the request. It does **not** natively support:

- "public on server A"
- "private on server B"

So a single global `mcp_public` flag cannot safely express per-server visibility.

## Recommendation

**Use a two-layer model.**

### Layer 1: global ability metadata

Keep these as global ability-level facts:

- `mcp_type`
- `mcp_public` as a coarse **MCP-eligible/default** flag

Meaning:

- if `mcp_public` is false, the ability should not be exposed to MCP at all
- if `mcp_public` is true, the ability is eligible for MCP exposure, but final visibility may still depend on server policy

### Layer 2: per-server exposure policy

Add server-specific policy outside the shared registry-level public flag.

That policy should decide:

- which MCP server may expose the ability
- optionally which endpoint kind may expose it

## Best implementation shape

### Keep current runtime model

- metadata overrides still belong in the registry merge path
- site disallow still belongs in the late unregister pass

### Add scoped exposure storage

Recommended minimal shape:

- `ability_slug`
- `server_key`
- `allowed`
- optional `endpoint_kind`

This can live in a separate scoped-policy table or equivalent structured store.

## Why this is best

### Benefits

- prevents leakage across all MCP servers
- preserves the plugin's current diff-based override model
- keeps ability metadata separate from audience/deployment policy
- works for multiple servers with different audiences

### Avoids bad coupling

- do not use provider-specific feature flags for this
- do not try to mutate the shared ability object differently per server
- do not rely on one global `mcp_public` checkbox as the only publish decision when multiple servers exist

## Decision

**Global registry for ability facts, per-server policy for exposure.**

## Practical rule

Final exposure should be:

1. ability exists
2. site allows it
3. ability is MCP-eligible globally
4. current server policy allows it

That is the safest design when multiple MCP servers exist on the same site.
