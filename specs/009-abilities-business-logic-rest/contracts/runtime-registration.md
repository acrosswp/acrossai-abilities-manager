# Runtime Registration Contract

## Publication Source

Runtime publication reads from the unified `acrossai_abilities` table and considers only rows where:

- `source = db`
- `status = publish`

## Registration Requirements

An ability is eligible for runtime registration only when:

- `ability_slug` is non-empty
- `label` is present and non-empty
- `category` is present and non-empty

Rows that fail these structural checks are skipped individually and do not abort the full registration pass. `callback_type` and `callback_config` validity is enforced at save time by the validator — malformed execution configs are therefore not expected at registration time but remain guarded by the callable construction path.

## Registry Argument Shape

Runtime registration writes to the nested registry/meta structure rather than flat top-level keys.

Required metadata paths include:

- descriptive fields
- REST/MCP exposure metadata
- annotations such as `readonly`, `destructive`, and `idempotent`
- execution configuration payloads
- source/audit context where required by the runtime consumer

## Runtime Permission Rule

Published database-managed abilities are executable by authenticated users only. This runtime gate is independent from the administrator-only management permission model and must not widen based on exposure type or authoring origin.

Canonical permission callback behavior:

```php
// Exposed as a public method on AcrossAI_Abilities_Processor
public function execution_permission_callback(): bool {
    return is_user_logged_in();
}
// Registered as: $args['permission_callback'] = [ $processor, 'execution_permission_callback' ]
```

Anonymous runtime execution is denied for every published database-managed ability.

## Execution Mapping

Execution mode maps directly from `callback_type`:

- `noop`
- `filter_hook`
- `wp_remote_post`
- `php_code`

Mode-specific callable construction happens during processor registration, not in the REST controller.

## Execution Hardening Rules

### `filter_hook`

- `callback_config` accepts `hook_name` only; unknown keys are rejected during validation.
- Missing or invalid hook names make the row ineligible for runtime registration.

### `wp_remote_post`

- `callback_config.url` must be HTTPS and pass URL validation before persistence and again before callable construction.
- `timeout` is optional, integer-only, and clamped to 30 seconds.
- Redirect following is disabled with `redirection => 0`.
- Request headers, cookies, and caller secrets are never propagated from runtime input.
- Execution failures are isolated per invocation and return a `WP_Error` without aborting subsequent executions.

### `php_code`

- Stored code is treated as trusted administrator-authored code and executes without sandboxing.
- Save-time validation must include `token_get_all()` syntax checking, blocked-function scanning, and a 64 KB size limit.
- Callables are wrapped as a static closure so execution does not capture `$this` or surrounding object state.
- Invocation is wrapped in `try/catch (\Throwable $e)`; failures are logged and returned as `WP_Error` without aborting registry bootstrap or later invocations.
