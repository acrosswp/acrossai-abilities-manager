=== AcrossAI Abilities Manager ===
Contributors: acrosswp
Tags: abilities, metadata, rest-api, admin-tools, ui
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A classic WordPress admin plugin for editing, filtering, and governing WordPress Abilities without patching provider code.

== Description ==

AcrossAI Abilities Manager is a standalone administrative plugin for sites that use the WordPress Abilities API. It gives site administrators a dedicated screen inside wp-admin to inspect registered abilities, save selective overrides, and apply those overrides automatically at runtime.

The plugin is designed for teams that need operational control without editing the original provider code. Instead of patching core, plugins, or themes, you can store override rows in a dedicated database table and let AcrossAI Abilities Manager apply them during ability registration.

This plugin uses a two-part runtime model:

* Metadata overrides are applied through the core wp_register_ability_args filter.
* Site disallow is enforced by unregistering the ability late in wp_abilities_api_init.

This split is intentional. It keeps metadata mutation lightweight and makes site-level disallow behave like an explicit governance rule.

= What the plugin manages =

AcrossAI Abilities Manager currently lets administrators manage these override fields for each registered ability:

* site_allowed
* readonly
* destructive
* idempotent
* show_in_rest
* mcp_public
* mcp_servers (for per-MCP-server visibility control)
* mcp_type
* custom_meta through the REST API

The plugin does not replace the original ability registration. It layers stored metadata overrides on top of the arguments WordPress receives during registration, and it can remove a disallowed ability from the live registry when site policy requires it.

= Key features =

* Classic wp-admin interface under Tools > Ability Manager.
* Unified searchable and sortable list of registered abilities, overrides, and custom abilities.
* Type column distinguishing between provider overrides and custom user-defined abilities.
* Provider and Type filters for quickly filtering by origin and ability type.
* Category display for each ability, including human-readable labels and slugs.
* Status column for custom abilities (active, draft, archived).
* Edit screen for individual abilities and custom abilities.
* Screen Options support and configurable items-per-page.
* Context-specific row actions:
  - For provider abilities: Edit, Allow or Disallow, and Reset override.
  - For custom abilities: Edit, Duplicate, and Delete.
* Save workflow with Save, Save and Exit, and Reset Override actions.
* Diff-only persistence so default values are not stored unnecessarily.
* Dedicated custom database tables for overrides and custom abilities.
* Runtime metadata application through wp_register_ability_args.
* Runtime site disallow through a late wp_unregister_ability() pass.
* Request guard that avoids mutating registrations while the AcrossAI Abilities Manager admin screen itself is rendering.
* REST API endpoints for listing, reading, saving, and deleting overrides and custom abilities.
* Capability checks based on manage_options.
* Nonce protection for admin save, toggle, reset, duplicate, and delete operations.
* Custom abilities creation screen under Tools > Add New Ability.
* Full-featured form for creating and managing custom abilities with validation.
* JSON schema editors for input/output definitions.
* REST API endpoints for custom abilities CRUD operations.

= Admin experience =

The main list screen gives administrators a practical operational view of all discovered abilities.

* Ability name and description.
* Slug and provider information.
* Category label and category slug.
* Allowed state for the site.
* Readonly, destructive, idempotent, and REST visibility state.
* MCP visibility state, with MCP type shown inline when relevant.
* Quick actions to edit, allow or disallow, or reset overrides.
* Provider counts displayed in filter tabs.

The edit screen is designed for fast manual administration.

* View the ability slug, provider, and category.
* Toggle whether the ability is allowed on the current site.
* Override tri-state booleans for readonly, destructive, and idempotent.
* Toggle REST exposure.
* Control MCP visibility with granular per-server options:
  - Disable for MCP (no server exposure)
  - Allow in all MCP servers
  - Allow in specific MCP servers (with conditional server selector)
* Select the MCP type from supported values.
* Save and stay on the same screen.
* Save and return to the main Ability Manager list.
* Reset the stored override from the same action area.

The add/edit custom ability screen allows administrators to create and maintain custom abilities.

* Enter a unique ability slug in namespace/name format.
* Set the label, description, and category for the ability.
* Define input and output JSON schemas for documentation and validation.
* Specify execution and permission callback functions or methods.
* Configure metadata flags: readonly, destructive, idempotent, show_in_rest, mcp_public.
* Select optional MCP type: tools, resources, or prompts.
* Add custom JSON metadata as needed.
* Set the ability status: active, draft, or archived.
* Save the ability and remain on the form or exit to the list.

There is no separate View mode. The plugin uses a list screen, an edit screen for overrides, and an add/edit screen for custom abilities only.

= How data is stored =

The plugin uses two dedicated custom tables in your WordPress database:

1. **Overrides table** (`wp_acrossai_abilities_overwrite`): Stores thin metadata overrides for provider-defined abilities.
2. **Abilities table** (`wp_acrossai_abilities`): Stores user-defined custom abilities with full definitions.

== Overrides storage ==

Overrides are saved in the `wp_acrossai_abilities_overwrite` table and include:

* Ability slug
* Provider
* Nullable boolean override values
* Site allow or disallow state
* MCP visibility and MCP type
* Custom meta payload
* Created and updated timestamps

The plugin stores only override values that differ from the current live ability metadata. If the saved values match the live defaults again, the plugin can remove the stale override row instead of keeping redundant data.

== Custom abilities storage ==

User-defined abilities are saved in the `wp_acrossai_abilities` table with full definitions:

* Ability slug and metadata (label, description, category)
* Input and output JSON schemas
* Execute and permission callback functions
* Status (active/draft/archived)
* Annotations (readonly, destructive, idempotent)
* API configuration (show_in_rest, MCP settings)
* Custom metadata and version tracking

= Runtime behavior =

AcrossAI Abilities Manager applies overrides during the Abilities API registration flow.

1. On wp_abilities_api_init, the plugin boots the runtime override layer.
2. It loads saved override rows into a request-local cache keyed by ability slug.
3. It attaches wp_register_ability_args to merge supported metadata fields into each ability registration.
4. It unregisters any ability whose override row explicitly sets site_allowed to false by running a late wp_unregister_ability() pass in the same wp_abilities_api_init lifecycle.

This is the authoritative site-disallow behavior. It is preferred over provider-specific feature filters because it operates at the abilities registry level.

If a runtime merge fails for a specific ability, the plugin falls back to the original ability arguments and emits an action hook so the failure can be logged or observed.

= REST API =

The plugin exposes two REST namespaces for programmatic management:

== Overrides Endpoints ==

* GET /wp-json/acrossai-abilities-manager/v1/overrides
* GET /wp-json/acrossai-abilities-manager/v1/overrides/{slug}
* POST /wp-json/acrossai-abilities-manager/v1/overrides/{slug}
* DELETE /wp-json/acrossai-abilities-manager/v1/overrides/{slug}

The route slug pattern is the full ability slug, such as ai/image-import.

Supported writable fields include:

* site_allowed
* readonly
* destructive
* idempotent
* show_in_rest
* mcp_public
* mcp_servers (array of server IDs for per-server MCP visibility)
* mcp_type
* custom_meta

When using mcp_servers:
- Set mcp_public: true with empty mcp_servers to expose to all servers
- Set mcp_public: false with mcp_servers array to restrict to specific servers
- Set mcp_public: null to disable MCP exposure entirely

== Custom Abilities Endpoints ==

* GET /wp-json/acrossai-abilities-manager/v1/custom-abilities
* GET /wp-json/acrossai-abilities-manager/v1/custom-abilities/{slug}
* POST /wp-json/acrossai-abilities-manager/v1/custom-abilities/{slug}
* DELETE /wp-json/acrossai-abilities-manager/v1/custom-abilities/{slug}

The route slug pattern is a custom ability slug, such as my-site/custom-processor.

Supported writable fields include:

* label (required for create)
* description
* category
* status (active/draft/archived)
* input_schema
* output_schema
* execute_callback
* permission_callback
* readonly
* destructive
* idempotent
* show_in_rest
* mcp_public
* mcp_type
* custom_meta

List endpoint supports filters:

* status: Filter by status (active/draft/archived)
* category: Filter by category
* search: Search in ability slug and label
* page: Page number (1-based, default 1)
* per_page: Results per page (default 20)
* orderby: Sort by field (ability_slug, label, status, category, created_at)
* order: Sort direction (ASC or DESC)

All REST routes require a user who can pass the plugin permission check, which currently maps to manage_options.

= Developer hook =

The plugin emits the following action when runtime application fails:

* acrossai_abilities_manager_override_error

Arguments passed to the hook:

* Ability slug
* Human-readable error message

This can be used for logging, telemetry, metrics, or debugging integrations.

= Guidance for maintainers =

When changing plugin behavior, keep these rules intact unless you are intentionally redesigning the runtime model:

* Use wp_register_ability_args for metadata overrides.
* Use site_allowed = false plus late unregister for site-level blocking.
* Do not reintroduce provider-specific filters such as wpai_feature_*_enabled for behavior that belongs at the abilities registry layer.
* Keep the admin flow list-plus-edit only unless a separate read-only screen is intentionally restored.
* Update both readmes if runtime hooks, REST routes, or admin actions change.

== Installation ==

1. Upload the plugin to the /wp-content/plugins/acrossai-abilities-manager/ directory, or install it with your preferred deployment workflow.
2. Activate the plugin through the Plugins screen in WordPress.
3. Ensure your site is running a version of WordPress that includes the Abilities API.
4. Log in as an administrator or another user with the manage_options capability.
5. Go to Tools > Ability Manager.
6. Browse abilities, open an item, and save or toggle an override as needed.

== Frequently Asked Questions ==

= Who can manage overrides? =

Only users who pass the plugin capability check can manage overrides. The current implementation requires the manage_options capability for both the admin UI and the REST API.

= Does this plugin edit the original ability registration code? =

No. The plugin stores override metadata separately in the database and applies it at runtime while WordPress registers abilities.

= What happens when I disallow an ability on the site? =

The override row is stored with site_allowed set to false, and the plugin unregisters that ability late in wp_abilities_api_init so it no longer exists in the live abilities registry for that request.

= What happens when I reset an override? =

The saved row for that ability is removed from the custom table. The ability then falls back to the live metadata provided by the original source.

= Does the plugin save every field for every ability? =

No. It is designed to save only values that differ from the current defaults so the stored override set stays focused and maintainable.

= Can I automate this from code or external tools? =

Yes. The plugin includes a REST API namespace for listing, reading, saving, and deleting overrides.

= Does the plugin run on the front end? =

The admin interface is loaded only in wp-admin. Runtime override application still runs where abilities are registered so saved metadata can take effect consistently.

= Does this plugin support custom metadata? =

Yes. The REST API accepts a custom_meta payload, which is merged into the normalized meta structure after the standard override fields are applied.

== Screenshots ==

1. The main Ability Manager list screen with provider tabs, search, sorting, allowed-state indicators, and saved override markers.
2. The edit screen with metadata controls, allow or disallow controls, MCP settings, and save actions.

== Changelog ==

= 0.1.0 =

* Initial release.
* Added a classic admin UI for editing WordPress Abilities metadata overrides.
* Added provider tabs, search, sorting, category display, and Screen Options support.
* Added site allow and disallow controls in both the list screen and edit screen.
* Added diff-only storage in a dedicated custom database table.
* Added REST API endpoints for override CRUD operations.
* Added runtime metadata override application through wp_register_ability_args.
* Added runtime site disallow through a late unregister pass.
* Added reset actions and edit-screen save workflows.
* Added granular MCP server visibility control with radio button UI (all servers, none, or specific servers).
* Added runtime helper method to check ability exposure to specific MCP servers.
* Added discovery hook for MCP server integration: acrossai_abilities_manager_get_mcp_servers.
* Added runtime failure notification hook for diagnostics.

== Upgrade Notice ==

= 0.1.0 =

Initial release of AcrossAI Abilities Manager.
