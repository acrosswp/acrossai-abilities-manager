# AcrossAI Abilities Manager

AcrossAI Abilities Manager is a standalone WordPress plugin for reviewing and overriding WordPress Abilities metadata from a classic wp-admin screen.

## Runtime model

The plugin uses two separate runtime paths and they should stay separate:

- Metadata overrides are merged through wp_register_ability_args.
- Site disallow is enforced by unregistering the ability late in wp_abilities_api_init.

That split is intentional. Metadata changes stay lightweight, while site-level disallow behaves like an audit or governance rule at the abilities registry level.

## Admin workflow

- Tools -> Ability Manager shows a unified searchable and sortable list screen featuring:
  - All registered provider abilities alongside their overrides and custom abilities
  - Type column distinguishing between provider overrides and custom user-defined abilities
  - Status column for custom abilities (active/draft/archived)
  - Sortable columns for easy management and discovery
  - Row actions vary by ability type:
    - **For Provider Abilities**: Edit, Allow or Disallow, and Reset when an override exists
    - **For Custom Abilities**: Edit, Duplicate, and Delete
  - Type filter to view All, Override, or Custom abilities
- Ability names link directly to the appropriate edit screen.
- The edit screen for provider abilities exposes the ability slug, provider, category, site allow toggle, metadata flags, REST exposure, and MCP server visibility controls.
  - **MCP visibility**: Radio button group to control MCP exposure (none/all servers/specific servers)
  - When "Specific servers" is selected, a list of available MCP servers appears as checkboxes for granular server selection
- Save actions are Save, Save and Exit, and Reset Override for overrides.
- Tools -> Add New Ability allows site administrators to create custom abilities.
- The add/edit custom ability screen supports all custom ability fields including label, description, schema definitions, callbacks, and metadata flags.
- Save actions for custom abilities are Save and Save and Exit.
- There is no separate view-only screen; edit screens handle inspection and modification.
- The runtime skips mutation on the plugin admin page so the editor can inspect and save data safely in the same request.

## Stored fields

- site_allowed
- readonly
- destructive
- idempotent
- show_in_rest
- mcp_public
- mcp_servers
- mcp_type
- custom_meta

The repository stores only values that differ from the current live ability state. If an override no longer differs from the live defaults, the row should be removed instead of kept as redundant data.

### MCP Server Visibility

The plugin supports granular MCP (Model Context Protocol) server visibility control via the `mcp_public` and `mcp_servers` fields:

- **Disable for MCP**: `mcp_public = null` and `mcp_servers = null` — Ability is not exposed to any MCP server
- **Allow in all MCP servers**: `mcp_public = true` and `mcp_servers = null` — Ability exposed to all servers
- **Allow in specific MCP servers**: `mcp_public = false` and `mcp_servers = ["server-id-1", "server-id-2"]` — Ability exposed only to listed servers

The admin edit form presents these options as radio buttons with a conditional server selector. MCP server discovery is handled via the `acrossai_abilities_manager_get_mcp_servers` action hook, allowing integration with MCP adapter plugins.

## REST API

### Overrides endpoints

- GET /wp-json/acrossai-abilities-manager/v1/overrides
- GET /wp-json/acrossai-abilities-manager/v1/overrides/{slug}
- POST /wp-json/acrossai-abilities-manager/v1/overrides/{slug}
- DELETE /wp-json/acrossai-abilities-manager/v1/overrides/{slug}

Supported writable fields include site_allowed, readonly, destructive, idempotent, show_in_rest, mcp_public, mcp_servers, mcp_type, and custom_meta.

**MCP Server Selection via REST:**
When updating an override via POST/PUT, provide `mcp_servers` as an array of server IDs to restrict MCP exposure:

```json
{
  "mcp_public": false,
  "mcp_servers": ["server-1", "server-2"]
}
```

Pass `mcp_public: true` with empty or omitted `mcp_servers` to expose to all servers. Pass `mcp_public: null` to disable MCP exposure entirely.

### Custom abilities endpoints

- GET /wp-json/acrossai-abilities-manager/v1/custom-abilities
- GET /wp-json/acrossai-abilities-manager/v1/custom-abilities/{slug}
- POST /wp-json/acrossai-abilities-manager/v1/custom-abilities/{slug}
- DELETE /wp-json/acrossai-abilities-manager/v1/custom-abilities/{slug}

Supported writable fields include label, description, category, status, input_schema, output_schema, execute_callback, permission_callback, readonly, destructive, idempotent, show_in_rest, mcp_public, mcp_type, and custom_meta.

List endpoint supports filters:
- status: Filter by status (active/draft/archived)
- category: Filter by category
- search: Search in ability slug and label
- page: Page number (1-based, default 1)
- per_page: Results per page (default 20)
- orderby: Sort by field (ability_slug, label, status, category, created_at)
- order: Sort direction (ASC or DESC)

## Maintenance rules

- Use wp_register_ability_args for metadata overrides.
- Use site_allowed = false plus late wp_unregister_ability() for site-level blocking.
- Do not reintroduce provider-specific filters such as wpai_feature_*_enabled for behavior that belongs at the abilities registry layer.
- Keep the admin flow list-plus-edit only unless the product intentionally adds another screen.
- Update both readmes whenever hooks, routes, or row actions change.

## Action Hooks

### acrossai_abilities_manager_get_mcp_servers

Called to discover available MCP servers for the admin interface.

**Parameters:**
- `$servers` (array, passed by reference) — Array to populate with available servers

**Server Array Format:**
Each server entry must contain:
- `id` (string) — Unique server identifier
- `label` (string) — Human-readable server name for the admin UI

**Example Usage (from mcp-adapter or similar):**

```php
add_action( 'acrossai_abilities_manager_get_mcp_servers', function( &$servers ) {
	// Discover your MCP servers and populate the array
	$available_servers = get_available_mcp_servers(); // Your discovery method
	
	foreach ( $available_servers as $server ) {
		$servers[] = array(
			'id'    => $server['id'],
			'label' => $server['name'],
		);
	}
} );
```

**Important Notes:**
- The servers array is passed by reference, so modifications persist for the caller
- Hook into this action early (priority <= 10) to ensure servers are available for the admin form
- Server IDs will be sanitized with `sanitize_text_field()` before storage
- If this hook is not implemented by any plugin, the "Allow in specific MCP servers" option will display a helpful message indicating no servers are available

### acrossai_abilities_manager_override_error

Emitted when an override fails to apply at runtime.

**Parameters:**
- `$slug` (string) — Ability slug whose override failed
- `$message` (string) — Human-readable error message

## Installation

1. Copy the plugin into wp-content/plugins/acrossai-abilities-manager.
2. Activate AcrossAI Abilities Manager.
3. Visit Tools -> Ability Manager as an administrator.

## Testing

The plugin includes comprehensive end-to-end tests for the custom abilities feature.

### Automated Tests

Run the PHPUnit test suite:

```bash
composer run test
```

This runs 25 automated tests covering:
- Custom ability CRUD operations
- REST API endpoints
- Runtime registration
- Validation and error handling
- Search and filtering
- Permission checks
- Metadata application
- Data integrity

See `tests/README.md` for detailed testing information.

### Manual Tests

For manual testing instructions, see `tests/manual/test-custom-abilities.md` which includes:
- Step-by-step REST API test procedures
- Admin UI testing workflows
- Database verification queries
- Expected results for each test case

### Test Coverage

- ✅ Database operations (create, read, update, delete, list, pagination)
- ✅ REST API endpoints (all CRUD operations)
- ✅ Validation (slug format, label, JSON schemas, status, callbacks)
- ✅ Runtime registration (active/draft abilities, metadata flags)
- ✅ Search and filtering (by slug, status, category, ordering)
- ✅ Permission checks (admin-only access)
- ✅ Data integrity (timestamps, upsert behavior, custom metadata)
