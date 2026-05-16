# Contract: wpb-ac/v1 REST API

**Phase**: 1 | **Date**: 2026-05-16 | **Plan**: [../plan.md](../plan.md)

The `wpb-ac/v1` REST namespace is registered by the `wpboilerplate/wpb-access-control` vendor library
via `AccessControlManager::register_rest_api()`. The plugin exposes these routes by wiring
`AcrossAI_Sitewide_Access_Control::register_rest_api()` to the WordPress `rest_api_init` hook.

The plugin does **not** own these routes and does not define their schemas. This document captures
the external interface that `AbilityEditPanel`'s Access Control tab consumes.

---

## Authentication

All routes require a valid WordPress nonce (`wp_rest`) passed via the `X-WP-Nonce` HTTP header.
The nonce middleware is registered globally in `src/js/sitewide/index.js`.

Capability requirement: `manage_options` (administrator only), enforced by `AccessControlManager`.

---

## Routes

### GET /wp-json/wpb-ac/v1/providers

Returns the list of available access-control provider types for a namespace.

**Query params**:
| Param | Type | Required | Notes |
|---|---|---|---|
| `namespace` | `string` | Yes | `"acrossai-abilities"` |

**Response** `200 OK`:
```json
[
    { "key": "everyone",  "label": "Everyone" },
    { "key": "no_one",    "label": "No One" },
    { "key": "wp_role",   "label": "Specific Roles" },
    { "key": "wp_user",   "label": "Specific Users" }
]
```

---

### GET /wp-json/wpb-ac/v1/rules/{namespace}/{resourceKey}

Returns the current access rule for a specific resource.

**Path params**:
| Param | Type | Notes |
|---|---|---|
| `namespace` | `string` | `"acrossai-abilities"` |
| `resourceKey` | `string` | Ability slug, e.g. `"read-file"` |

**Response** `200 OK` (rule exists):
```json
{
    "provider": "wp_role",
    "provider_value": ["editor", "author"]
}
```

**Response** `200 OK` (no rule set):
```json
{ "provider": "", "provider_value": null }
```

---

### PUT /wp-json/wpb-ac/v1/rules/{namespace}/{resourceKey}

Creates or updates the access rule for a specific resource.

**Path params**: Same as GET above.

**Request body** (`application/json`):
```json
{
    "provider": "wp_role",
    "provider_value": ["editor"]
}
```

**Response** `200 OK`:
```json
{
    "provider": "wp_role",
    "provider_value": ["editor"]
}
```

---

## Consuming Plugin Configuration

The plugin registers the `"acrossai-abilities"` namespace when wiring the `AccessControlManager`.
The `PROVIDERS_FILTER` constant (`acrossai_abilities_access_control_providers`) allows other plugins
to extend the available provider list via a WordPress filter, though this feature only uses the
library defaults (`wp_role`, `wp_user`, `everyone`, `no_one`).
