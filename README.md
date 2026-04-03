# Abilities Editor

Abilities Editor is a standalone WordPress plugin for overriding WordPress Abilities metadata from a classic wp-admin screen.

## Features

- Stores ability overrides in a dedicated custom table.
- Adds Tools -> Ability Overrides with search, filters, stats, and per-ability edit screens.
- Exposes CRUD endpoints at abilities-editor/v1 for programmatic management.
- Applies saved overrides through wp_register_ability_args during Abilities API registration.

## Installation

1. Copy the plugin into wp-content/plugins/abilities-editor.
2. Activate Abilities Editor.
3. Visit Tools -> Ability Overrides as an administrator.

## REST API

- GET /wp-json/abilities-editor/v1/overrides
- GET /wp-json/abilities-editor/v1/overrides/{slug}
- POST /wp-json/abilities-editor/v1/overrides/{slug}
- DELETE /wp-json/abilities-editor/v1/overrides/{slug}
