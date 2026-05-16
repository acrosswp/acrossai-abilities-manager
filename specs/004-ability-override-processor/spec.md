# Feature Specification: Ability Override Processor

**Feature Branch**: `004-ability-override-processor`
**Created**: 2026-05-16
**Status**: Draft

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admin-Saved Overrides Take Effect at Runtime (Priority: P1)

A WordPress site administrator uses the Abilities Manager to disable a specific ability (e.g. `core/get-site-info`) or change its MCP visibility. After saving in the Manager, every subsequent non-Manager request — REST calls from plugins, MCP server execution, frontend checks — sees the overridden values instead of the registration-time defaults.

**Why this priority**: This is the entire value proposition of the feature. Without override injection, the Manager UI is cosmetic — it stores values that have no effect on runtime behaviour. P1 because everything else builds on it.

**Independent Test**: Save a field override (e.g. `readonly = true`) for any registered ability in the Manager. Then call `wp_get_ability('slug')` from WP-CLI or a test plugin on the same request. Verify the returned `readonly` value matches what was saved.

**Acceptance Scenarios**:

1. **Given** a DB override with `readonly = true` exists for `core/example`, **When** `wp_get_ability('core/example')` is called on any non-Manager request, **Then** the returned data contains `readonly = true` regardless of the registration-time value.
2. **Given** a DB override with `show_in_mcp = false` exists for `core/example`, **When** the MCP server queries available abilities, **Then** `core/example` is absent from the MCP response.
3. **Given** no DB override exists for `core/example`, **When** `wp_get_ability('core/example')` is called, **Then** the registration-time defaults are returned unchanged.
4. **Given** a DB override with `mcp_servers = ["server-a"]` exists, **When** the ability is consumed, **Then** `mcp_servers` contains exactly `["server-a"]`.
5. **Given** a DB override sets `readonly = null` (Inherit), **When** the ability is consumed, **Then** the registration-time `readonly` value is preserved, not overwritten with null.

---

### User Story 2 — Disabled Abilities Are Completely Hidden (Priority: P1)

A site administrator sets `site_allowed = false` for an ability in the Manager. That ability is then completely invisible to all consumers: it does not appear in `wp_get_abilities()`, cannot be executed, and is absent from REST and MCP responses — until an admin re-enables it.

**Why this priority**: Equal to US1. A partially-hidden ability (visible in some contexts but not others) would create security and consistency problems. Complete removal from the WP registry is the only correct behaviour.

**Independent Test**: Set `site_allowed = false` for any registered ability in the Manager. On the next request (non-Manager), call `wp_get_ability('slug')`. Verify it returns null or empty. Verify `wp_get_abilities()` does not include it.

**Acceptance Scenarios**:

1. **Given** `site_allowed = false` is saved for `core/example`, **When** any non-Manager REST endpoint or WP function calls `wp_get_ability('core/example')`, **Then** the ability is not found (returns null or absent from the registry).
2. **Given** `site_allowed = false` for `core/example`, **When** the Manager admin page loads, **Then** `core/example` is still visible in the Manager table (Manager bypasses override injection).
3. **Given** `site_allowed = false`, **When** the site admin resets the override to Inherit, **Then** `core/example` becomes available again to all consumers on the next request.
4. **Given** `site_allowed = null` (Inherit), **When** any consumer calls `wp_get_abilities()`, **Then** the ability is present with its registration-time `site_allowed` value.

---

### User Story 3 — Manager UI Is Not Affected by Override Injection (Priority: P1)

The Abilities Manager admin page must always show the pure WP registry values (for `_registry`) alongside the DB overrides (for `_override`). Override injection must never corrupt the `_registry` layer shown in the Manager.

**Why this priority**: If override injection affects the Manager's own REST responses, admins see merged values instead of originals and can no longer distinguish what was set at registration versus what was overridden. This breaks the three-layer data model the UI depends on.

**Independent Test**: Load the Abilities Manager admin page. For any ability with a DB override, verify the `_registry` column shows the original registration-time value and the `_override` column shows the DB value — not the merged result.

**Acceptance Scenarios**:

1. **Given** a DB override with `readonly = true` for `core/example` (registered as `readonly = false`), **When** the Manager page loads and fetches abilities, **Then** `_registry.readonly = false` and `_override.readonly = true` and the top-level `readonly = true`.
2. **Given** the Manager REST API is called, **When** override injection is active globally, **Then** the Manager REST namespace routes skip override injection entirely and return pure registry values for `_registry`.
3. **Given** `site_allowed = false` for `core/example`, **When** the Manager page loads, **Then** `core/example` is listed in the Manager table (not unregistered from the Manager's perspective).

---

### User Story 4 — Override Cache Invalidates When Overrides Change (Priority: P2)

When an admin saves or resets an ability override through the Manager, the runtime override cache is cleared so the next non-Manager request sees the updated values immediately — without requiring a site reload or manual cache flush.

**Why this priority**: Without cache busting, an admin saving a change in the Manager would see no effect for up to 12 hours. The cache is a performance optimisation that must be transparent to users.

**Independent Test**: Save an override. Immediately make a non-Manager request for that ability. Verify the new override value is present. Then reset the override. Make another non-Manager request. Verify the field reverts to the registration default.

**Acceptance Scenarios**:

1. **Given** no DB override for `core/example`, **When** an admin saves `readonly = true` via the Manager, **Then** the very next `wp_get_ability('core/example')` call on any non-Manager context returns `readonly = true`.
2. **Given** an existing override, **When** the admin resets it (deletes from DB), **Then** the next non-Manager `wp_get_ability` call returns the registration-time default.
3. **Given** an override is saved, **When** the same request that saved the override makes a second DB read for a cache miss, **Then** only one DB query is issued (cache was populated from the save).

---

### Edge Cases

- What happens when `AcrossAI_Sitewide_Query` is not yet available at `plugins_loaded` priority 20? — Boot is wired at priority 20, after the query class is registered at priority 10.
- What happens when the WP Abilities API (`wp_abilities_api_init`) is absent (older WP version)? — Override hooks are registered but never fire; no error is thrown.
- What happens when a `wp_unregister_ability()` call is issued for a slug that does not exist in the registry? — WP core handles the graceful no-op; the processor does not check for existence first.
- What happens if `$_SERVER['REQUEST_URI']` is missing (CLI context)? — `is_manager_rest_request()` checks for `WP_CLI` first; CLI always returns false before inspecting `$_SERVER`.
- What happens when `mcp_servers` is stored as a JSON string in the DB? — `AcrossAI_Sitewide_Row::__construct()` decodes the JSON string to `array|null` on construction; by the time `inject_override_args()` reads `$row->mcp_servers`, it is already an array or null. The processor guards with `is_array()`, never calls `json_decode()`, and writes to `$args['meta']['mcp']['servers']`.
- What happens if the transient is corrupted or contains unexpected data? — The cache is rebuilt from DB; corrupted transients are treated as a cache miss.

---

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The processor MUST inject non-null DB override values into ability arguments before each ability is registered, for all non-Manager requests.
- **FR-002**: The processor MUST completely remove abilities with `site_allowed = false` from the WP registry after all abilities are registered, for all non-Manager requests.
- **FR-003**: The processor MUST skip all override injection and unregistration when the incoming request targets the Manager's own REST namespace. The namespace is identified by the `ACROSSAI_MANAGER_REST_NAMESPACE` constant (default: `'acrossai-abilities-manager/v1'`, overridable via `wp-config.php` or the `acrossai_manager_rest_namespace` filter). Detection MUST match the full namespace string exactly — not a shorter prefix — to prevent false positives against third-party namespaces sharing a common prefix.
- **FR-004**: The processor MUST load all DB override rows in a single query per request, cached in a transient with a 12-hour TTL.
- **FR-005**: The cache MUST be cleared (both transient and in-memory) whenever any ability override is saved or reset via the Manager.
- **FR-006**: A null DB override value MUST never overwrite a registration-time argument (Inherit semantics).
- **FR-007**: The processor MUST NOT write to the database directly; all DB access MUST go through `AcrossAI_Sitewide_Query`.
- **FR-008**: The Manager REST detection MUST NOT be used as an access control mechanism; it is a performance/registration optimisation only.
- **FR-009**: `inject_override_args()` MUST write DB override values to the following `$args` paths (null values MUST be skipped per FR-006):

  | DB field | `$args` path | Notes |
  |---|---|---|
  | `site_allowed` | `$args['site_allowed']` | Top-level WP Abilities API field |
  | `readonly` | `$args['meta']['annotations']['readonly']` | WP core annotation |
  | `destructive` | `$args['meta']['annotations']['destructive']` | WP core annotation |
  | `idempotent` | `$args['meta']['annotations']['idempotent']` | WP core annotation |
  | `show_in_rest` | `$args['meta']['show_in_rest']` | WP core meta field |
  | `show_in_mcp` | `$args['meta']['mcp']['public']` | Plugin-specific MCP block |
  | `mcp_type` | `$args['meta']['mcp']['type']` | Plugin-specific MCP block |
  | `mcp_servers` | `$args['meta']['mcp']['servers']` | Plugin-specific MCP block; already decoded to `array\|null` by `AcrossAI_Sitewide_Row::__construct()` — guard with `is_array()`, never call `json_decode()` |
  | `permission_callback` | `$args['permission_callback']` | Runtime AC enforcement; injected only when `RuleQuery::get_rule('acrossai-abilities', $slug)` returns a non-empty key — checked independently of the override row. Closure calls `AcrossAI_Sitewide_Access_Control::instance()->get_manager()->user_has_access(get_current_user_id(), 'acrossai-abilities', $slug)`. Fail-open (returns `true`) when `get_manager()` returns `null`. |

  `$args['meta']['mcp']` is **not** a WordPress core Abilities API field. It is a plugin-specific block consumed exclusively by this plugin's MCP integration layer. Override injection into this block only takes effect if the MCP integration reads `$args['meta']['mcp']` at runtime.
- **FR-010**: The processor MUST be bootable from a single static `boot()` call at `plugins_loaded` priority 20.

### Key Entities

- **Override Row**: A DB record keyed by `ability_slug` containing tri-state (`true`/`false`/`null`) and typed override values for all overridable fields.
- **Override Cache**: A request-scoped in-memory map of slug → override row, populated from a transient-backed single DB query. Lives only for the duration of a request.
- **Manager REST Context**: A request classification (PATH A) where the incoming HTTP request targets the Manager's own REST namespace. Detected heuristically from `$_SERVER` at `plugins_loaded` time.
- **WP Abilities Registry**: WordPress core's in-memory store of registered abilities. The processor modifies it via the `wp_register_ability_args` filter and `wp_unregister_ability()`.

---

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A DB override saved in the Manager takes effect on all non-Manager consumers within the same PHP request (zero propagation delay after cache bust).
- **SC-002**: An ability with `site_allowed = false` is absent from `wp_get_abilities()` on all non-Manager requests within the same PHP request after the override is saved.
- **SC-003**: The Manager admin page's `_registry` values are unaffected by override injection — the Manager always shows registration-time values for the `_registry` layer.
- **SC-004**: The entire set of DB override rows — regardless of count — is fetched in at most one DB query per request (cache hit rate: 100% after first request within TTL window). `get_all_overrides()` uses BerlinDB `number => 0` (no LIMIT) to ensure completeness.
- **SC-005**: `bust_cache()` causes the next request to see updated override values without any manual intervention (zero stale-cache failures).
- **SC-006**: No performance regression on non-ability pages (override boot fires only once; no per-request DB queries outside of cache misses).
- **SC-007 (Deferred)**: Debug-level observability hooks (e.g. `do_action('acrossai_ability_override_applied', $slug, $args)`) are explicitly out of scope for this feature and deferred to a future observability pass.

---

## Assumptions

- The WordPress Abilities API (`wp_register_ability_args` filter and `wp_abilities_api_init` action) is available in the target WordPress version (7.0+). If absent, the processor registers its hooks but they never fire — no error is produced.
- `AcrossAI_Sitewide_Query` is available and initialized by `plugins_loaded` priority 10, before the processor boots at priority 20.
- The Manager REST namespace is `acrossai-abilities-manager/v1` (`AcrossAI_Sitewide_Rest_Controller::REST_NAMESPACE`). PATH A detection uses the `ACROSSAI_MANAGER_REST_NAMESPACE` constant (default `'acrossai-abilities-manager/v1'`), filterable via `acrossai_manager_rest_namespace`. The full namespace string is matched — not a short prefix — to avoid false positives. If the version changes, the constant or filter must be updated accordingly.
- `rest_get_url_prefix()` and `home_url()` are available at `plugins_loaded` time (they are WordPress core functions present from `muplugins_loaded` onwards).
- `wp_doing_ajax()`, `wp_doing_cron()` are available at `plugins_loaded` time (standard WordPress functions).
- The WP Abilities API fires ability registrations inside `wp_abilities_api_init` at priorities 10–999; priority 100001 for unregistration is safely after all registrations.
- DB `site_allowed` values of `'0'` (string) and `0` (int) both represent `false` and should trigger unregistration. `null` means Inherit.
- The existing `_registry` / `_override` / merged three-layer REST response shape produced by the Manager is correct and must not be altered by this feature.
- Multisite support: the override table is per-site (uses `$wpdb->prefix`); no cross-site override bleeding.
- The transient cache key `acrossai_ability_overrides_cache` is unique to this plugin and will not conflict with other plugins.
- `$args['meta']['mcp']` is a plugin-specific block within the WP Abilities API `$args` structure, not a WordPress core field. Override injection into this block (keys `public`, `type`, `servers`) only takes effect if the MCP integration layer of this plugin reads `$args['meta']['mcp']` at runtime. This assumption MUST be revisited if the MCP integration changes its read path.
- The 12-hour transient TTL is a hardcoded safe default. No filter is provided because `bust_cache()` fires on every Manager save and reset, making the TTL relevant only for cold starts and transient evictions — scenarios where a stale window of up to 12 hours is acceptable.
---

## Clarifications

### Session 2026-05-16

- Q: FR-009 and the `mcp_servers` edge case described `inject_override_args()` calling `json_decode()` on a string. Does the spec reflect actual Row behavior? → A: No. `AcrossAI_Sitewide_Row::__construct()` (lines 175–177) already decodes `mcp_servers` from JSON to `array|null`. FR-009 and the edge case entry have been corrected to reflect that decoding is the Row class's responsibility and the processor guards with `is_array()`.
- Q: Should `get_all_overrides()` have a documented ceiling (e.g. 9,999) or fetch all rows unconditionally? → A: Fetch all unconditionally. `number => 9999` replaced with BerlinDB `number => 0` (no LIMIT). SC-004 updated to confirm completeness for any override count.
- Q: PATH A detection used the broad prefix `acrossai-abilities/` — should it match the exact Manager namespace to prevent false positives? → A: Yes. Constant `ACROSSAI_MANAGER_REST_NAMESPACE` (default `'acrossai-abilities-manager/v1'`) introduced; filterable via `acrossai_manager_rest_namespace`. PATH A now matches the full namespace string. FR-003 and the Manager namespace Assumption updated.
- Q: Should the 12-hour transient TTL be filterable? → A: No. `bust_cache()` fires on every Manager save and reset, so the TTL only matters for cold starts and transient evictions. 12 hours hardcoded is sufficient; no filter needed. TTL rationale added to Assumptions.
- Q: Should the processor emit debug-level observability hooks when applying or skipping overrides? → A: No logging requirement for this feature. Debug action hooks (e.g. `acrossai_ability_override_applied`) deferred to a future observability pass. SC-007 added as a deferred item.
