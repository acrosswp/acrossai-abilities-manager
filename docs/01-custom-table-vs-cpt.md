# Research 01: Custom Table vs CPT vs Options

## Question

Should this plugin keep using a custom database table, switch to a custom post type (CPT), or use some other storage approach?

## Context from this plugin

- The plugin manages **ability overrides**, not editorial content.
- Overrides are keyed by **ability slug**.
- Runtime reads overrides and merges them into the live abilities registry.
- The admin UI is already a **custom list screen + edit screen**.
- Stored fields include typed override values like:
  - `site_allowed`
  - `readonly`
  - `destructive`
  - `idempotent`
  - `show_in_rest`
  - `mcp_public`
  - `mcp_type`
  - `custom_meta`

## Recommendation

**Keep the custom table as the primary store.**

## Why this is the best fit

### Custom table

Best fit because:

- this data is **configuration / governance state**, not content
- one row per ability slug is natural and enforceable
- nullable typed fields work well for tri-state override behavior
- runtime wants **fast slug-based lookup**
- provider counts and filtered queries are straightforward
- the current custom admin UI already matches this model

### CPT

Not a good fit because:

- an override is not really a "post"
- one logical override would become a post plus many `postmeta` rows
- uniqueness by ability slug is less direct
- tri-state metadata is awkward in `postmeta`
- the plugin would still need custom admin behavior, so CPT gives little benefit

### Options API

Not the right primary model because:

- a single serialized option becomes harder to query and manage over time
- multiple options become messy and less coherent than the current table
- the plugin already needs list, filter, merge, and REST-friendly record behavior

## Decision

**Use the custom table. Do not migrate this plugin to CPT storage.**

## Notes

- A hybrid can still make sense:
  - custom table as source of truth
  - REST controller on top of the repository
  - separate audit/history storage later if needed
- There is **high migration cost and low reward** in switching to CPT.
