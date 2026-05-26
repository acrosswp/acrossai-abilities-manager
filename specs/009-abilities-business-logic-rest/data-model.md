# Data Model: Abilities Business Logic and REST API

## Entity: Ability

Canonical stored record for the unified `acrossai_abilities` table.

### Fields

| Field | Type | Notes |
|------|------|-------|
| `id` | bigint unsigned | Primary key |
| `ability_slug` | varchar(255) | Required, unique, namespace/name format |
| `label` | varchar(255) nullable | Required for publish-ready `source = db` rows |
| `description` | longtext nullable | Sanitized descriptive text |
| `category` | varchar(100) nullable | Must map to a currently available ability category for publish/runtime use |
| `status` | varchar(20) | `draft` or `publish` |
| `provider` | varchar(100) nullable | Optional ownership/provider descriptor |
| `source` | varchar(50) | `db` for database-managed rows; other values are inherited/read-only sources |
| `site_allowed` | tinyint nullable | Existing Sitewide wrapper field retained in the unified table |
| `callback_type` | varchar(50) | Execution mode selector |
| `callback_config` | longtext nullable | JSON object validated per callback type |
| `input_schema` | longtext nullable | JSON schema payload |
| `output_schema` | longtext nullable | JSON schema payload |
| `show_in_rest` | tinyint nullable | Visibility metadata |
| `show_in_mcp` | tinyint nullable | Visibility metadata |
| `mcp_type` | varchar(100) nullable | `tool`, `resource`, or `prompt` when MCP-visible |
| `mcp_servers` | longtext nullable | JSON array of allowed server slugs |
| `readonly` | tinyint nullable | Metadata flag |
| `destructive` | tinyint nullable | Metadata flag |
| `idempotent` | tinyint nullable | Metadata flag |
| `created_at` | datetime | Audit timestamp |
| `updated_at` | datetime | Audit timestamp |
| `created_by` | bigint unsigned nullable | Audit user |
| `updated_by` | bigint unsigned nullable | Audit user |

### Validation Rules

- `ability_slug` must be sanitized before validation, limited to 255 chars, and unique for create paths.
- `status` must be one of the supported lifecycle states.
- `source`, `provider`, `created_at`, `updated_at`, `created_by`, and `updated_by` are server-controlled fields.
- `source` is system-controlled for newly created managed rows and defaults to `db`; update payloads never mutate it.
- `category` must be present and registered before a row can move to `publish`.
- `callback_type` and `callback_config` must validate together.
- `show_in_mcp`, `mcp_type`, and `mcp_servers` must validate together.
- `input_schema` and `output_schema` must be valid JSON payloads accepted by the validator.
- For `source != db` rows, the immutable field set is: `ability_slug`, `label`, `description`, `category`, `status`, `provider`, `source`, `callback_type`, `callback_config`, `input_schema`, `output_schema`, `show_in_rest`, `show_in_mcp`, `mcp_type`, and `mcp_servers`.

### State Transitions

- `draft` -> `publish`: allowed only when identity, category, and execution configuration are valid.
- `publish` -> `draft`: allowed for database-managed rows through management APIs.
- Non-`db` rows: partially editable only; protected identity/descriptive/execution fields remain read-only.

## Entity: Ability Category

Runtime-discovered category metadata used to validate and format managed abilities.

### Fields

| Field | Type | Notes |
|------|------|-------|
| `slug` | string | Stable category identifier |
| `label` | string | Human-readable category name |
| `description` | string nullable | Optional descriptive text |

### Rules

- Categories are not created by Spec 009.
- Publish-ready managed abilities must reference an available category slug.
- The categories endpoint is read-only and administrator-gated.

## Entity: Execution Configuration

Structured configuration attached to an ability’s runtime execution mode.

### Fields

| Field | Type | Notes |
|------|------|-------|
| `callback_type` | string | Execution mode selector |
| `callback_config` | object/null | Mode-specific structured payload |

### Supported Modes

- `noop`: documentation or placeholder mode
- `filter_hook`: invokes a registered WordPress filter-based callback path using a validated `hook_name` config with unknown keys rejected
- `wp_remote_post`: remote execution mode with validated request settings, HTTPS-only URLs, no caller header propagation, redirect following disabled, and timeout capped at 30 seconds
- `php_code`: inline code execution mode for trusted administrator-authored code paths, with token-based syntax validation, blocked-function scanning, 64 KB size limit, static-closure wrapping, and per-invocation error isolation

### Rules

- Each mode has a required config shape validated before persistence.
- Runtime registration skips rows whose mode-specific config is incomplete.
- Runtime execution remains authenticated-user only regardless of execution mode.

## Entity: Exposure Profile

Machine-consumable visibility metadata for REST and MCP-style discovery.

### Fields

| Field | Type | Notes |
|------|------|-------|
| `show_in_rest` | bool/null | REST registry visibility metadata |
| `show_in_mcp` | bool/null | MCP visibility metadata |
| `mcp_type` | string/null | `tool`, `resource`, `prompt` |
| `mcp_servers` | array/null | Optional allowlist of server slugs |
| `readonly` | bool/null | Annotation metadata |
| `destructive` | bool/null | Annotation metadata |
| `idempotent` | bool/null | Annotation metadata |

### Rules

- Exposure collections only include published, valid, database-managed abilities matching the requested exposure type.
- `mcp_type` is required when `show_in_mcp` is true.
- Empty `mcp_servers` means no server-specific restriction.
