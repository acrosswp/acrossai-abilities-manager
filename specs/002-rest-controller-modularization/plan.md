# Implementation Plan: REST Controller Modularization

**Branch**: `001-sitewide-ability-management` | **Date**: 2026-05-14
**Input**: [spec.md](spec.md) | **Research**: [research.md](research.md) | **Checklist**: [checklists/requirements.md](checklists/requirements.md) | **Related spec**: [../001-sitewide-ability-management/](../001-sitewide-ability-management/)

---

## Summary

`AcrossAI_Sitewide_Rest_Controller` grew to 670 lines and mixed seven responsibilities
(route registration, permission checking, ability reads, override writes, toggle, bulk
actions, MCP listing). This refactor splits it into a thin **orchestrator** plus four
per-domain **sub-controllers**, each owning one cohesive handler group.

The split also codifies the **REST Controller Pattern** (see CONSTITUTION.md v1.4.1 §REST
Controller Pattern) as the canonical decomposition for the four planned sibling feature
modules (`PerUser`, `McpServer`, `CustomAbility`, `Webmcp`).

No REST contract changes. No JS/SCSS changes. No database changes.

---

## Constitution Check

### ✅ PASS — I. Modular Architecture
Each sub-controller owns exactly one handler group. Shared utilities from `includes/Utilities/`
are unchanged. No sibling-module dependencies.

### ✅ PASS — II. WordPress Standards Compliance
All new classes follow WPCS strict + PHPStan L8 constraints. PHP 7.4 compatible (no `match`,
no named args, no `readonly` properties). All files have `defined('ABSPATH') || exit`.

### ✅ PASS — IV. Security First
`check_permission()` stays on the orchestrator and is referenced by every route. Zero
duplication of the nonce + capability check. No route is left without a permission callback.

### ✅ PASS — VI. Reusability & DRY
`check_permission` is not copied — sub-controllers reference the orchestrator singleton.
`REST_NAMESPACE` remains a single constant on the orchestrator.

### ✅ PASS — Boot Flow Rule
`includes/Main.php` wires only the orchestrator using the **variable-first pattern**:
```php
$rest_controller = AcrossAI_Sitewide_Rest_Controller::instance();
$this->loader->add_action( 'rest_api_init', $rest_controller, 'register_routes' );
```
Passing `::instance()` inline as the hook object argument is prohibited (Constitution §Boot Flow Rule).
Sub-controllers do not register any WordPress hooks themselves.

---

## Architecture

```
includes/Modules/Sitewide/
├── AcrossAI_Sitewide_Rest_Controller.php      # Orchestrator: REST_NAMESPACE, register_routes(), check_permission()
├── index.php
├── Database/   …
└── Rest/                                       # NEW
    ├── index.php
    ├── AcrossAI_Sitewide_Abilities_Controller.php   # GET /abilities, GET /abilities/{slug}
    ├── AcrossAI_Sitewide_Override_Controller.php    # POST /abilities/{slug}, DELETE /abilities/{slug}, POST .../toggle
    ├── AcrossAI_Sitewide_Bulk_Controller.php        # POST /abilities/bulk
    └── AcrossAI_Sitewide_Mcp_Controller.php         # GET /mcp-servers
```

### Orchestrator (`AcrossAI_Sitewide_Rest_Controller`) — ~100 lines

```php
public function register_routes(): void {
    AcrossAI_Sitewide_Abilities_Controller::instance()->register_routes();
    AcrossAI_Sitewide_Override_Controller::instance()->register_routes();
    AcrossAI_Sitewide_Bulk_Controller::instance()->register_routes();
    AcrossAI_Sitewide_Mcp_Controller::instance()->register_routes();
}

public function check_permission( \WP_REST_Request $request ) { /* manage_options + nonce */ }
```

### Sub-controller permission callback pattern

Every sub-controller route uses:
```php
'permission_callback' => array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' ),
```

No abstract base class. No permission code duplication. Constitution §VI satisfied.

> **Architecture note (2026-05-16)**: The Constitution Boot Flow Rule ("resolve singleton to named variable before `add_action`") governs `$this->loader->add_action()` calls only. The inline `::instance()` inside a `register_rest_route` `permission_callback` array is a deliberate documented pattern for this module — it is not a Boot Flow Rule violation. `AcrossAI_Sitewide_Override_Controller` uses a `$permission` local variable solely for DRY (3 routes share the same callback); the other controllers use inline because each has only one route.

### Sub-controller responsibility map

| File | Routes | Handlers |
|---|---|---|
| `AcrossAI_Sitewide_Abilities_Controller` | GET /abilities, GET /abilities/{slug} | `get_abilities`, `get_ability` |
| `AcrossAI_Sitewide_Override_Controller` | POST /abilities/{slug}, DELETE /abilities/{slug}, POST .../toggle | `save_override`, `delete_override`, `toggle_ability` |
| `AcrossAI_Sitewide_Bulk_Controller` | POST /abilities/bulk | `bulk_action` |
| `AcrossAI_Sitewide_Mcp_Controller` | GET /mcp-servers | `get_mcp_servers` |

---

## MCP Server Listing — Package Integration

`GET /sitewide/mcp-servers` now uses the `wpboilerplate/wpb-mcp-servers-list` Composer
package instead of calling `McpAdapter` directly. The package encapsulates the timing
complexity (McpAdapter hooks at priority 15; collect must run at priority 20+) and
returns `ServerData[]` objects that implement `JsonSerializable`, eliminating the need
for manual getter mapping.

**Wiring in `Main::define_admin_hooks()`** (variable-first, Constitution §Boot Flow Rule):
```php
$mcp_servers_list = \WPBoilerplate\McpServersList\McpServersList::instance();
$this->loader->add_action( 'rest_api_init', $mcp_servers_list, 'collect', 20 );
```

**Controller** (`AcrossAI_Sitewide_Mcp_Controller::get_mcp_servers()`):
```php
return rest_ensure_response( McpServersList::instance()->get_servers() );
```

The package absorbs all timing and serialization complexity; the controller is a
one-liner.

---

## Files Changed

| File | Change |
|---|---|
| `includes/Modules/Sitewide/AcrossAI_Sitewide_Rest_Controller.php` | Rewritten as thin orchestrator |
| `includes/Modules/Sitewide/Rest/index.php` | New — directory sentinel |
| `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Abilities_Controller.php` | New |
| `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Override_Controller.php` | New |
| `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Bulk_Controller.php` | New |
| `includes/Modules/Sitewide/Rest/AcrossAI_Sitewide_Mcp_Controller.php` | New — uses `wpboilerplate/wpb-mcp-servers-list` |
| `composer.json` / `composer.lock` | Added `wpboilerplate/wpb-mcp-servers-list` |
| `includes/Main.php` | Added `McpServersList::collect` wired at `rest_api_init` priority 20 |
| `.specify/memory/CONSTITUTION.md` | Bumped to v1.4.0 (REST Controller Pattern added), then v1.4.1 (MCP package integration pattern added) |
| `AGENTS.md` | REST controller split pattern documented |
| `specs/001-sitewide-ability-management/plan.md` | Project structure note added |
| `tests/phpunit/sitewide/RestControllerTest.php` | No change (stale pre-existing issue tracked separately) |

---

## Out of Scope

- Fixing `tests/phpunit/sitewide/RestControllerTest.php` (pre-existing stale test that
  instantiates a private singleton constructor — tracked as a follow-up task).
- Any handler logic changes, REST contract changes, JS/SCSS/build changes.
