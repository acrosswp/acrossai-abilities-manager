# Research 02: Sparse Overrides vs Full Ability Snapshots

## Question

Should the plugin store only edited override values in the database, or persist every discovered ability as a full database snapshot?

## Current behavior

The plugin currently:

- reads live abilities from WordPress
- stores rows only when a user changes an ability
- merges stored values on top of the live ability
- deletes redundant rows when they no longer differ from live defaults

## Recommendation

**Keep the current sparse diff-based model.**

## Best model

1. WordPress ability registry is the **source of truth**
2. The custom table stores only **site-specific deviations**
3. Admin UI merges live values with stored overrides
4. Runtime applies only stored differences

## Why sparse overrides are better

### Correctness

- Provider defaults can change over time.
- If the admin did not override a field, the plugin should automatically pick up the provider's latest live value.
- Sparse storage preserves that behavior.

### Lower stale-data risk

- Full snapshots tend to freeze old provider defaults.
- Sparse rows only record intentional changes, so there is less stale data to reconcile.

### Better runtime behavior

- Runtime only needs the small set of changed rows.
- This keeps lookups and in-memory merge logic simple.

### Cleaner UX

- A saved row means: **this ability has an intentional override**
- Reset actions stay meaningful
- The list screen can clearly distinguish default behavior from modified behavior

### Lower maintenance cost

- No full-registry sync job
- No snapshot reconciliation logic
- No extra migration or garbage-collection layer

## Why full snapshots are worse here

- they can block provider updates from flowing through
- they create more stale records when providers change or disappear
- they increase query volume and table size
- they blur the meaning of "override"

## Decision

**Do not persist every discovered ability. Keep storing only edited differences.**

## Practical rule

Store a row only when the saved value differs from the current live ability state.  
Delete the row when it no longer differs.
