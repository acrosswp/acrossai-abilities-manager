=== Abilities Editor ===
Contributors: acrosswp
Tags: abilities, metadata, rest-api, admin-tools, mcp
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A classic WordPress admin plugin for viewing, overriding, and managing WordPress Abilities metadata without editing code.

== Description ==

Abilities Editor is a standalone administrative plugin for sites that use the WordPress Abilities API. It gives site administrators a dedicated screen inside wp-admin to inspect registered abilities, compare current metadata, save selective overrides, and apply those overrides automatically at runtime.

The plugin is designed for teams that need to adjust ability behavior without editing the original provider code. Instead of patching core, plugins, or themes, you can store overrides in a dedicated database table and let Abilities Editor apply them during ability registration.

This plugin focuses on operational control and observability-friendly behavior:

* It keeps overrides separate from source code.
* It applies changes through a core registration filter instead of unregistering and re-registering abilities.
* It stores only override values that differ from the live ability defaults.
* It allows administrators to reset an override cleanly when the stored values are no longer needed.
* It exposes a REST API for programmatic management.

= What the plugin manages =

Abilities Editor currently lets administrators override these metadata fields for each registered ability:

* readonly
* destructive
* idempotent
* show_in_rest
* mcp.public
* mcp.type
* Additional custom meta through the REST API

The plugin does not replace the original ability registration. It layers stored metadata overrides on top of the ability arguments that WordPress receives during registration.

= Key features =

* Classic wp-admin interface under Tools > Ability Overrides.
* Searchable and sortable list of registered abilities.
* Provider tabs for quickly filtering core, plugin, and theme abilities.
* Category display for each ability, including human-readable labels and slugs.
* View and edit screens for individual abilities.
* Screen Options support and configurable items-per-page.
* Reset action for removing a saved override and returning to live defaults.
* Save workflow with Save, Save and Exit, and Reset Override actions.
* Diff-only persistence so default values are not stored unnecessarily.
* Dedicated custom database table for overrides.
* Runtime application through wp_register_ability_args.
* Request guard that avoids mutating registrations while the Abilities Editor admin screen itself is rendering.
* REST API endpoints for listing, reading, saving, and deleting overrides.
* Capability checks based on manage_options.
* Nonce protection for admin save and reset operations.

= Admin experience =

The main list screen gives administrators a practical operational view of all discovered abilities.

* Ability name and description.
* Slug and provider information.
* Category label and category slug.
* Readonly, destructive, idempotent, and REST visibility state.
* MCP visibility state, with MCP type shown inline when relevant.
* Quick links to view, edit, or reset overrides.
* Provider counts displayed in filter tabs.

The edit screen is designed for fast manual administration.

* View the ability slug, provider, and category.
* Override tri-state booleans for readonly, destructive, and idempotent.
* Toggle REST exposure.
* Toggle MCP public visibility.
* Select the MCP type from supported values.
* Save and stay on the same screen.
* Save and return to the main Ability Overrides list.
* Reset the stored override from the same action area.

= How overrides are stored =

Overrides are saved in a dedicated custom table named using your WordPress database prefix plus abilities_editor_overrides.

Stored data includes:

* Ability slug
* Provider
* Nullable boolean override values
* MCP visibility and MCP type
* Custom meta payload
* Created and updated timestamps

The plugin stores only override values that differ from the current live ability metadata. If the saved values match the live defaults again, the plugin can remove the stale override row instead of keeping redundant data.

= Runtime behavior =

Abilities Editor applies overrides during the Abilities API registration flow. It hooks into wp_abilities_api_init, primes a request-local cache of saved overrides, and then filters each ability through wp_register_ability_args.

This approach keeps runtime application lightweight and avoids more fragile patterns such as unregistering and re-registering abilities after the fact.

If a runtime merge fails for a specific ability, the plugin falls back to the original ability arguments and emits an action hook so the failure can be logged or observed.

= REST API =

The plugin exposes a REST namespace for programmatic override management:

* GET /wp-json/abilities-editor/v1/overrides
* GET /wp-json/abilities-editor/v1/overrides/{provider}/{name} via the combined slug route format used by the plugin
* POST /wp-json/abilities-editor/v1/overrides/{provider}/{name}
* DELETE /wp-json/abilities-editor/v1/overrides/{provider}/{name}

The route slug pattern is the full ability slug, such as ai/image-import.

Example endpoint:

/wp-json/abilities-editor/v1/overrides/ai/image-import

Supported writable fields include:

* readonly
* destructive
* idempotent
* show_in_rest
* mcp_public
* mcp_type
* custom_meta

All REST routes require a user who can pass the plugin permission check, which currently maps to manage_options.

= Developer hook =

The plugin emits the following action when runtime application fails:

* abilities_editor_override_error

Arguments passed to the hook:

* Ability slug
* Human-readable error message

This can be used for logging, telemetry, metrics, or debugging integrations.

= Intended use cases =

* Audit and review the metadata attached to registered abilities.
* Change exposed ability metadata without patching the original provider.
* Adjust MCP visibility for abilities in controlled environments.
* Tune REST visibility for registered abilities.
* Provide an internal admin tool for operations, QA, or product teams.
* Script override management from external tooling through the REST API.

== Installation ==

1. Upload the plugin to the /wp-content/plugins/abilities-editor/ directory, or install it with your preferred deployment workflow.
2. Activate the plugin through the Plugins screen in WordPress.
3. Ensure your site is running a version of WordPress that includes the Abilities API.
4. Log in as an administrator or another user with the manage_options capability.
5. Go to Tools > Ability Overrides.
6. Browse abilities, open an item, and save an override as needed.

== Frequently Asked Questions ==

= Who can manage overrides? =

Only users who pass the plugin capability check can manage overrides. The current implementation requires the manage_options capability for both the admin UI and the REST API.

= Does this plugin edit the original ability registration code? =

No. The plugin stores override metadata separately in the database and applies it at runtime while WordPress registers abilities.

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

1. The main Ability Overrides list screen with provider tabs, search, sorting, and saved override indicators.
2. The edit screen with metadata controls, MCP settings, and save actions.
3. The view screen for inspecting an existing ability override before editing.

== Changelog ==

= 0.1.0 =

* Initial release.
* Added a classic admin UI for viewing and editing WordPress Abilities metadata overrides.
* Added provider tabs, search, sorting, category display, and Screen Options support.
* Added diff-only storage in a dedicated custom database table.
* Added REST API endpoints for override CRUD operations.
* Added runtime override application through wp_register_ability_args.
* Added reset actions and edit-screen save workflows.
* Added MCP visibility and MCP type override support.
* Added runtime failure notification hook for diagnostics.

== Upgrade Notice ==

= 0.1.0 =

Initial release of Abilities Editor.
