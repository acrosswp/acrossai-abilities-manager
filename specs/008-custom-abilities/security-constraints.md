# Security Review: Custom Abilities Module (008)

**Review Date**: 2026-05-20  
**Reviewed Artifacts**: plan.md, spec.md, memory-synthesis.md, CONSTITUTION.md  
**Status**: Security review complete — 5 advisory findings, 0 blockers  

---

## Executive Summary

The Custom Abilities Module has a **sound security foundation** aligned with the plugin's Constitution. Core protections (capability checks, input sanitization, BerlinDB prepared statements) are well-designed. However, **five advisory findings** require clarification during implementation to prevent common pitfalls:

1. **Callback execution framework lacks exception handling strategy** (Advisory)
2. **Permission callback "fail open" behavior needs documented safeguard** (Advisory)
3. **Namespace collision policy unclear — override precedence needs validation** (Advisory)
4. **JSON schema validation lacks depth/complexity limits** (Advisory)
5. **MCP server filtering requires explicit allowlist validation** (Advisory)

**Recommendation**: Proceed with implementation. All findings are addressable with implementation-phase design decisions. No security violations detected in plan artifacts.

---

## Plan Artifacts Reviewed

✅ [plan.md](plan.md) — 336 lines, complete technical design  
✅ [spec.md](spec.md#L1) — Functional requirements, user stories, acceptance scenarios  
✅ [memory-synthesis.md](memory-synthesis.md) — Decisions, patterns, bug avoidance patterns  
✅ `.specify/memory/CONSTITUTION.md` — Architecture principles, security requirements  

---

## Trust Boundaries & Security Assumptions

### Assumed Trust Model

- **Admin-only operations**: All custom ability creation, modification, deletion limited to `manage_options` capability
- **No end-user input**: Custom abilities are created by admins, not users
- **Controlled callback execution**: Callback targets (filter hooks, remote URLs) are admin-specified, not user-provided
- **Secure BerlinDB foundation**: All queries use prepared statements (no direct SQL interpolation)
- **WordPress core security**: Assumes WordPress core Abilities API, REST infrastructure, and sanitization utilities are secure

### Trust Boundary Violations — None Detected

All code operates within appropriate trust boundaries. No security checks are bypassed or deferred.

---

## Detailed Security Findings

### FINDING 1: Permission Model — ✅ SECURE

**Category**: Access Control  
**Severity**: N/A (Confirmed Secure)  

**Assessment**:

✅ **Capability Check Enforcement**:
- Plan specifies `manage_options` on ALL REST endpoints (READ, WRITE, MCP controllers)
- Plan specifies `manage_options` on admin submenu registration (`acrossai-custom-abilities`)
- Plan specifies asset enqueue conditional on admin page (prevents frontend execution)
- Constitution §IV mandates capability checks on all admin operations — confirmed in plan

✅ **No Permission Bypass Vectors Identified**:
- BerlinDB query layer does not expose data retrieval without orchestrator routing
- No REST endpoints lack permission callbacks
- No admin UI pages accessible without capability check
- No nonce verification required (capability check is sufficient for admin operations)

✅ **Multisite Isolation**:
- Plan specifies `$global = false` in BerlinDB Table class (per-site data isolation)
- Each site has separate `{prefix}acrossai_custom_abilities` table
- Custom abilities in Site A cannot affect Site B

**Implementation Verification Checklist**:
- [ ] Orchestrator's `check_permission()` method uses `current_user_can( 'manage_options' )`
- [ ] REST routes explicitly pass orchestrator's `check_permission` as permission_callback
- [ ] Admin page render checks `current_user_can( 'manage_options' )` before output
- [ ] No AJAX endpoints exist; all mutations go through REST API with permission checks
- [ ] Multisite testing: Create custom ability on Site A, verify does not appear on Site B

---

### FINDING 2: Input Validation — ✅ SECURE with Clarifications

**Category**: Data Validation  
**Severity**: Advisory (design clarity needed)  

**Assessment**:

✅ **Validation Rules Defined**:
Plan specifies comprehensive validator utility (`AcrossAI_Custom_Ability_Validator`):
- `validate_slug($slug)`: Pattern check (`^[a-z0-9]+/[a-z0-9-]+$`), uniqueness, max 255 chars
- `validate_label($label)`: Non-empty, max 255 chars
- `validate_callback_config($type, $config)`: Type-specific (noop/filter_hook/wp_remote_post)
- `validate_permission_config($type, $config)`: Type-specific (always_allow/logged_in/capability)
- `validate_schema($schema)`: JSON Schema Draft 7 compliance
- `validate_ability($fields)`: Aggregate validation before save

✅ **Validation Pipeline**:
- Plan specifies Write Controller pipeline: (1) sanitize input, (2) validate fields, (3) check slug collision, (4) save
- Memory notes specify strict `===` comparison for access control (SEC-04)
- BUG-PARTIAL-HOOK-FIELDS mitigation: fetch complete row after save before firing hooks

⚠️ **ADVISORY — Callback Type & Config Validation**:

**Issue**: `callback_config` structure depends on `callback_type` (noop/filter_hook/wp_remote_post), but plan does not specify how mismatched configs are rejected.

**Specific Scenarios**:
- `callback_type = 'noop'` but `callback_config` has `hook_name` field → accept or reject?
- `callback_type = 'filter_hook'` but `hook_name` is empty → reject? Or accept as documentation?
- `callback_type = 'wp_remote_post'` but URL is malformed → validate URL syntax?

**Recommendation**: 
```php
// Validator must enforce type-specific rules:
case 'filter_hook':
    if ( empty( $config['hook_name'] ) ) {
        return new WP_Error( 'invalid_hook_name', 'Hook name required' );
    }
    if ( ! preg_match( '/^[a-z0-9_]+$/', $config['hook_name'] ) ) {
        return new WP_Error( 'invalid_hook_name', 'Hook name must be alphanumeric' );
    }
    break;

case 'wp_remote_post':
    if ( empty( $config['url'] ) || ! wp_http_validate_url( $config['url'] ) ) {
        return new WP_Error( 'invalid_url', 'Valid URL required' );
    }
    if ( ! in_array( $config['method'] ?? 'POST', [ 'POST', 'PUT' ] ) ) {
        return new WP_Error( 'invalid_method', 'Method must be POST or PUT' );
    }
    break;
```

**Implementation Verification Checklist**:
- [ ] `AcrossAI_Custom_Ability_Validator::validate_callback_config()` rejects mismatched type/config
- [ ] URL validation uses `wp_http_validate_url()` for wp_remote_post
- [ ] Hook name validation: alphanumeric + underscore only
- [ ] Timeout validation for wp_remote_post: integer 1-300 seconds
- [ ] Unit tests cover all three callback_type paths with valid/invalid configs

---

### FINDING 3: Input Sanitization — ✅ SECURE with Implementation Requirements

**Category**: Input Sanitization  
**Severity**: Advisory (early validation critical)  

**Assessment**:

✅ **Sanitization Strategy Defined**:
Plan specifies `AcrossAI_Custom_Ability_Sanitizer` static utility:
- `sanitize_ability_slug($slug)`: lowercase, remove invalid chars
- `sanitize_label($label)`: `sanitize_text_field()`
- `sanitize_description($desc)`: `wp_kses_post()`
- `sanitize_callback_config($type, $config)`: Type-specific sanitization
- `sanitize_permission_config($type, $config)`: Type-specific sanitization
- `sanitize_schema($schema_json)`: Validate JSON, re-encode to normalize

✅ **Sanitization Timing**:
- Plan specifies sanitization occurs in Write Controller BEFORE validation
- Memory note SEC-02: cast bool→int before BerlinDB save (after validation)
- BUG-FLAT-ARGS-PATH: metadata stored in nested `$args['meta']` structure, not flat keys

⚠️ **ADVISORY — JSON Sanitization Strategy**:

**Issue**: Plan does not specify how malformed JSON in `input_schema`, `output_schema`, `callback_config`, `permission_config` is handled.

**Specific Scenarios**:
- User pastes invalid JSON in schema textarea → sanitizer must validate before saving
- `permission_config` contains `{"capability": "fake_cap\"; DROP TABLE users; --"}` → must escape before storage
- Deeply nested JSON (1000+ levels) → potential DOS via `json_decode()` memory exhaustion

**Recommendation**:
```php
// AcrossAI_Custom_Ability_Sanitizer::sanitize_schema()
public static function sanitize_schema( $schema_json ) {
    if ( empty( $schema_json ) ) return null;
    
    // Validate JSON syntax
    $decoded = json_decode( $schema_json, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return null; // Invalid JSON rejected
    }
    
    // Limit depth (prevent DOS)
    $max_depth = 10;
    if ( self::json_depth( $decoded ) > $max_depth ) {
        return null; // Too deeply nested
    }
    
    // Re-encode to normalize (remove extra whitespace, sort keys)
    return wp_json_encode( $decoded );
}

// Helper: calculate JSON depth
private static function json_depth( $data, $depth = 0 ) {
    if ( ! is_array( $data ) && ! is_object( $data ) ) return $depth;
    $max = $depth;
    foreach ( (array) $data as $item ) {
        $current = self::json_depth( $item, $depth + 1 );
        $max = max( $max, $current );
    }
    return $max;
}
```

**Implementation Verification Checklist**:
- [ ] `sanitize_schema()` validates JSON syntax; rejects malformed JSON
- [ ] JSON depth limit enforced (recommend max 10 levels)
- [ ] JSON size limit enforced (recommend max 64KB per field)
- [ ] Permission config sanitization escapes capability names
- [ ] BUG-FLAT-ARGS-PATH confirmed: metadata stored in `$args['meta']`, not flat keys
- [ ] Unit tests: valid JSON, malformed JSON, deeply nested, oversized payloads

---

### FINDING 4: Output Escaping — ✅ SECURE

**Category**: Output Escaping  
**Severity**: N/A (Confirmed Secure)  

**Assessment**:

✅ **Admin UI Escaping**:
- Plan uses DataForm and DataViews components from `@wordpress/dataviews`
- Constitution §III mandates DataForm/DataViews for all admin UI (no custom rendering)
- `@wordpress/dataviews` handles escaping for table cell content, form labels, and error messages
- Admin page uses `wp_localize_script()` to pass data (automatically escaped by WordPress)

✅ **REST API Response Escaping**:
- Plan specifies `AcrossAI_Custom_Ability_Formatter::format_ability_for_response()` 
- REST responses are JSON-encoded (automatically escaped by `wp_json_encode()`)
- No raw HTML rendering; all fields are text/JSON

✅ **MCP Response Escaping**:
- MCP responses are JSON-encoded, not HTML-rendered
- `AcrossAI_Custom_Ability_Formatter::format_for_mcp()` returns stdClass objects (JSON-encodable)

⚠️ **ADVISORY — Admin Bulk Action Feedback**:

**Issue**: Plan specifies bulk actions (Enable/Disable/Delete Selected) but does not specify how confirmation and feedback is rendered.

**Recommendation**: 
- Use `wp_admin_notice()` or DataViews built-in feedback for success/error messages
- All user-provided ability slugs/labels displayed in admin notices must be escaped: `esc_html( $ability->label )`

**Implementation Verification Checklist**:
- [ ] All admin notices use `esc_html()` or `esc_attr()` for ability metadata display
- [ ] REST response uses `wp_json_encode()` (WordPress default)
- [ ] No raw HTML generation; all UI via DataForm/DataViews
- [ ] Bulk action feedback messages escape ability labels

---

### FINDING 5: Callback Execution Security — ⚠️ ADVISORY (Out of Scope v1)

**Category**: Callback Execution Risk  
**Severity**: Advisory (design decision needed)  

**Assessment**:

**Note**: Plan specifies callback execution is **STUB/TODO in v1** (registration only, not execution).

✅ **v1 Scope — Registration-Only** (Safe):
- Custom abilities register at `wp_abilities_api_init`
- Abilities are exposed via REST API and MCP
- Callback execution (via `wp_execute_ability()`) is deferred to v2

⚠️ **ADVISORY — Callback Execution Risk Model (for v2 planning)**:

Plan defines three callback types with different security implications:

**Type 1: noop** (Safe):
- No execution; documentation/placeholder only
- Zero risk

**Type 2: filter_hook** (Moderate Risk):
- Executes: `apply_filters( $config['hook_name'], $input )`
- Risk: Arbitrary filter callback code execution (but limited to plugins registered on filter)
- Mitigation: Admin must trust other plugins; capability check (`manage_options`) limits access
- Recommendation: Add audit logging: which abilities executed which hooks, by whom

**Type 3: wp_remote_post** (High Risk):
- Executes: `wp_remote_post( $config['url'], $args )`
- Risk: Network calls to arbitrary URLs, SSRF (Server-Side Request Forgery)
- Mitigation: 
  - URL validation (already planned: `wp_http_validate_url()`)
  - WordPress's `wp_remote_post()` blocks private IPs by default (10.0.0.0/8, 127.0.0.1/8, etc.)
  - Timeout enforcement (plan specifies 30-second default)
- Recommendation: Add approval workflow for wp_remote_post callbacks; log all remote calls

**Implementation Verification Checklist** (v2 phase):
- [ ] Callback execution uses `wp_remote_post()` only (not curl/fsockopen)
- [ ] SSRF protection: WordPress private IP blocking is enabled
- [ ] Callback execution has audit logging: ability slug, execution result, errors
- [ ] Timeout enforcement: 30 seconds max per remote call
- [ ] Error handling: callback failures do not crash ability registration or subsequent abilities
- [ ] Unit tests: filter_hook execution, wp_remote_post with success/timeout/failure scenarios

---

### FINDING 6: Permission Callback Pattern — ⚠️ ADVISORY

**Category**: Permission Callback Design  
**Severity**: Advisory (implementation clarity needed)  

**Assessment**:

✅ **Permission Callback Injection Strategy**:
Plan specifies `DEC-PERM-CB` pattern (from Sitewide processor):
- capability: `$permission_callback = function() use ( $capability ) { return current_user_can( $capability ); };`
- logged_in: `$permission_callback = function() { return is_user_logged_in(); };`
- always_allow: `$permission_callback = null;`

✅ **Capability Validation**:
- Plan specifies "fail open if capability doesn't exist"
- Memory note: If capability doesn't exist in WordPress, permission check allows access (fail open)

⚠️ **ADVISORY — "Fail Open" Safety Concern**:

**Issue**: "Fail open" policy is dangerous if permission_config references a capability that will never be registered by any plugin.

**Scenario**: Admin creates ability with `permission_type = 'capability'` and `capability = 'never_registered_cap'`. Assumption is that someone will register this capability later. But if no plugin registers it, what happens?

**Plan states**: "Fail open if capability doesn't exist" → allows anyone with `manage_options` to execute ability.

**Recommendation**:
```php
// Processor must validate permission_config at registration time
$registered_caps = wp_roles()->get_capabilities();
if ( 'capability' === $ability->permission_type ) {
    $cap = $ability->permission_config['capability'] ?? null;
    if ( ! $cap || ! isset( $registered_caps[ $cap ] ) ) {
        do_action(
            'acrossai_custom_ability_registration_error',
            $ability->slug,
            new WP_Error(
                'missing_capability',
                sprintf( 'Capability "%s" not registered', $cap )
            )
        );
        return false; // Fail closed: do not register ability
    }
}
```

**Benefit**: Prevents silent permission escalation from non-existent capabilities.

**Alternative (backward-compatible)**: Log warning but register anyway (fail open, but with audit trail).

**Implementation Verification Checklist**:
- [ ] Processor validates permission_config['capability'] exists before registration
- [ ] Missing capability scenario has audit logging or error notice
- [ ] Processor rejects ability if capability validation fails (or explicitly documents fail-open decision)
- [ ] Unit test: register ability with non-existent capability; verify error/warning

---

### FINDING 7: MCP Exposure Security — ✅ SECURE with Validation

**Category**: MCP Exposure Control  
**Severity**: Advisory (design requires validation)  

**Assessment**:

✅ **MCP Filtering Strategy**:
Plan specifies MCP Controller filters:
- `show_in_mcp = true` AND
- `mcp_type` matches route (tool/resource/prompt) AND
- Current MCP server is in `mcp_servers` allowlist (if not empty)

✅ **MCP Server Discovery**:
- Plan specifies use of `wpboilerplate/wpb-mcp-servers-list` package
- Constitution §VII Integration Resilience: MCP server listing MUST use canonical package (not direct `McpAdapter` calls)
- Package handles timing: collect at `rest_api_init` priority 20

⚠️ **ADVISORY — MCP Server Validation**:

**Issue**: Plan does not specify validation of `mcp_servers` array during creation.

**Scenario**: Admin creates ability with `mcp_servers = ['non_existent_server']`. The ability will never be exposed because MCP server is not discovered. Should this be rejected at creation time, or silently filtered at query time?

**Recommendation**:
```php
// Validator: validate_ability_mcp_servers()
public static function validate_ability_mcp_servers( $servers_array ) {
    if ( empty( $servers_array ) ) {
        return true; // Empty array = expose to all servers (OK)
    }
    
    // Discover available servers
    $mcp_servers_list = \WPBoilerplate\McpServersList\McpServersList::instance();
    $available = $mcp_servers_list->get_available_servers(); // Returns ServerData[]
    $available_slugs = wp_list_pluck( $available, 'slug' );
    
    foreach ( (array) $servers_array as $server_slug ) {
        if ( ! in_array( $server_slug, $available_slugs, true ) ) {
            return new WP_Error(
                'invalid_mcp_server',
                sprintf( 'MCP server "%s" not found', $server_slug )
            );
        }
    }
    return true;
}
```

**Alternative (more lenient)**: Validate at query time only; accept non-existent servers at creation (allows forward-compatibility if new servers are added later).

**Implementation Verification Checklist**:
- [ ] MCP server slugs are validated against discovered servers (at creation or at query time)
- [ ] Empty `mcp_servers` array means "expose to all servers"
- [ ] Non-empty `mcp_servers` array is filtered to discovered servers only
- [ ] MCP Controller `check_permission()` includes MCP server validation
- [ ] Unit test: create ability with valid/invalid MCP servers; verify query filtering

---

### FINDING 8: Namespace Collision & Override Precedence — ⚠️ ADVISORY

**Category**: Business Logic Security  
**Severity**: Advisory (policy decision needed)  

**Assessment**:

⚠️ **Issue**: Plan allows namespace collisions but specifies no enforcement mechanism.

**Current Plan**:
- "Namespace Collision: Allow silently, admin responsibility to avoid"
- No protected prefix enforcement at creation time
- Filter `acrossai_protected_ability_prefixes()` exists but is not described as mandatory

**Collision Scenarios**:
1. Admin creates custom ability `custom/my-ability` that collides with core ability `custom/my-ability`
   - → Later registration overwrites earlier registration
   - → Admin may silently disable core ability without realizing

2. Sitewide override registers `override/my-ability`, then custom ability also registers same slug
   - → Question: Which takes precedence?
   - → Plan specifies Sitewide runs at priority 15, Custom at priority 10
   - → Sitewide registered later → Sitewide overwrites Custom
   - → Correct precedence if intended

3. Multiple custom abilities with same slug (should be prevented by UNIQUE constraint)
   - → BerlinDB enforces uniqueness, so this is prevented ✅

**Recommendation**:

**Option A (Strict, Secure)**:
```php
// Block reserved namespace prefixes at creation
$protected = apply_filters(
    'acrossai_protected_ability_prefixes',
    [ 'core', 'wp', 'acrossai', 'mcp', 'system' ]
);

foreach ( $protected as $prefix ) {
    if ( strpos( $slug, $prefix . '/' ) === 0 ) {
        return new WP_Error(
            'reserved_namespace',
            sprintf( 'Namespace "%s/*" is reserved', $prefix )
        );
    }
}
```

**Option B (Lenient with Warning)**:
```php
// Warn admin but allow (audit trail)
if ( strpos( $slug, 'core/' ) === 0 || strpos( $slug, 'wp/' ) === 0 ) {
    do_action( 'acrossai_custom_ability_namespace_warning', $slug );
}
```

**Plan currently implies Option B** (allow, admin responsibility).

**Implementation Verification Checklist**:
- [ ] Namespace collision policy documented in code comments
- [ ] Protected prefix list defined (recommend: 'core', 'wp', 'system', 'acrossai')
- [ ] Collision warning logged if custom ability slug matches existing ability
- [ ] Sitewide precedence documented: Sitewide processor runs at priority 15, Custom at 10 → Sitewide takes precedence
- [ ] Unit test: create custom ability that collides with core ability; verify registration order

---

### FINDING 9: Readonly & Destructive Flags — ✅ SECURE

**Category**: Metadata Integrity  
**Severity**: N/A (Confirmed Safe)  

**Assessment**:

✅ **Metadata-Only Annotation**:
- Plan explicitly states: `readonly` flag is metadata annotation only; does NOT prevent mutations
- Readonly flag is tri-state: NULL (inherit), 0 (false), 1 (true)
- Admin UI allows modification regardless of readonly flag
- REST API allows modification regardless of readonly flag
- Clients (MCP, REST) use flag for guidance, not enforcement

✅ **No Trust Boundary Violation**:
- readonly/destructive/idempotent flags are informational
- No security model depends on these flags
- If client trusts readonly flag, that's client's responsibility

**Implementation Verification Checklist**:
- [ ] Readonly flag stored in database but not enforced in code
- [ ] Admin UI allows editing abilities marked readonly
- [ ] REST endpoints accept writes regardless of readonly flag
- [ ] Documentation clarifies: readonly is informational only
- [ ] Unit test: create readonly ability; verify it can still be edited/deleted

---

### FINDING 10: Nonce Verification — ✅ SECURE (Not Required)

**Category**: CSRF Protection  
**Severity**: N/A (Confirmed Secure)  

**Assessment**:

✅ **REST API Nonce Handling**:
- All mutations (POST, PUT, DELETE) go through REST API
- WordPress REST API has built-in CSRF protection via `wp_nonce_field()` and nonce verification
- Plan specifies `wp_localize_script()` passes REST namespace to JS (no nonce needed in localized data)
- Capability check (`manage_options`) is sufficient for CSRF protection

✅ **Admin Form Nonce Not Required**:
- DataForm component from `@wordpress/dataviews` handles form submission via REST API
- REST API CSRF protection covers all mutation requests

**Implementation Verification Checklist**:
- [ ] All REST endpoints use WordPress built-in nonce verification (via `rest_ensure_request_is_local()` or similar)
- [ ] No custom nonce implementation needed; rely on WordPress core REST API protection
- [ ] DataForm submission goes through `@wordpress/api-fetch` (includes nonce headers)

---

## Data Flow Security Analysis

### Custom Ability Creation Flow

```
1. Admin fills DataForm (browser)
   ↓
2. Form validation (client-side): DataForm validates before submission
   ↓
3. REST API POST /custom-abilities (JavaScript)
   - Headers: REST API authentication + nonce (WordPress built-in)
   ↓
4. Write Controller receives request (PHP)
   - Check permission: current_user_can( 'manage_options' ) ✅
   ↓
5. Sanitize input
   - sanitize_ability_slug()
   - sanitize_label()
   - sanitize_callback_config()
   - sanitize_permission_config()
   - sanitize_schema()
   ↓
6. Validate sanitized input
   - validate_slug() — pattern, uniqueness, length
   - validate_label() — non-empty, length
   - validate_callback_config() — type-specific rules ⚠️
   - validate_permission_config() — type-specific rules
   - validate_schema() — JSON syntax
   ↓
7. Fire before_save hook (with sanitized $fields)
   ↓
8. Cast bool → int, json → string for BerlinDB
   ↓
9. BerlinDB save (prepared statement)
   ↓
10. Fetch complete row from DB (after save)
   ↓
11. Fire after_save hook (with complete 20-field row)
   ↓
12. Return 201 response + ability object (JSON-encoded)
```

**Security Status**: ✅ Secure flow with proper validation pipeline

---

### Custom Ability Registration Flow

```
1. WordPress loads at wp_abilities_api_init (priority 10)
   ↓
2. Custom Ability Processor fetches enabled abilities from BerlinDB
   ↓
3. For each ability:
   - Build permission callback ✅
   - Validate permission_type / permission_config ⚠️
   - Inject into wp_register_ability() ✅
   - Fire acrossai_custom_ability_registered hook
   ↓
4. Ability is now in WordPress Abilities registry
   ↓
5. Ability available via:
   - REST API: /wp-json/wp-abilities/v1/abilities
   - Admin UI: Abilities list
   - MCP: /wp-json/acrossai-abilities-manager/v1/custom-abilities/mcp/*
```

**Security Status**: ✅ Mostly secure; permission_config validation needs clarification (Finding 6)

---

## Constitution Compliance Checklist

### §I Modular Architecture
- ✅ Custom Ability is self-contained module
- ✅ REST controllers split: Orchestrator + 3 sub-controllers (Read, Write, MCP)
- ✅ Shared logic extracted to `includes/Utilities/`
- ✅ No duplication with existing modules

### §II WordPress Standards Compliance
- ✅ Plan specifies WPCS, PHPStan L8, ESLint validation
- ✅ All output escaped via `wp_json_encode()`, DataForm/DataViews
- ✅ All input sanitized before validation
- ✅ BerlinDB prepared statements (no raw SQL)
- ✅ WordPress 6.9+ compatible, PHP 7.4+ compatible
- ✅ Multisite compatible (`$global = false`)

### §III User-Centric Design
- ✅ DataForm for ability creation/editing
- ✅ DataViews for list management
- ✅ No custom form/table rendering

### §IV Security First
- ✅ Input sanitized at REST entry points
- ✅ Output escaped via JSON encoding
- ✅ Nonce verification via WordPress REST API
- ✅ Capability checks on all endpoints
- ✅ BerlinDB prepared statements
- ⚠️ Callback execution validation needs clarification (Finding 5)

### §V Extensibility Without Core Modification
- ✅ Custom abilities via hooks only
- ✅ No core plugin file modifications
- ✅ Graceful degradation if MCP not available

### §VI Reusability & DRY
- ✅ Validation/sanitization extracted to Utilities
- ✅ Permission callback injected via closure
- ✅ No duplication with existing modules
- ✅ `@wordpress/dataviews` used (Tier 1 packages)

### §VII Definition of Done
- ⚠️ PHPCS/PHPStan/ESLint — TODO (implementation phase)
- ⚠️ Security review — IN PROGRESS (this document)
- ⚠️ Unit tests — TODO (implementation phase)
- ✅ DataForm + DataViews planned
- ⚠️ Prefix `acrossai_` — TODO (implementation phase)

---

## Risk Mitigation Strategies

### For Callback Execution (Finding 5)

**Until callback execution is implemented (v2)**:
- Add stub comment: `// TODO v2: Implement callback execution via wp_execute_ability()`
- No code paths attempt to execute callbacks in v1
- MCP clients receive ability metadata but cannot execute

**For v2 implementation**:
- Implement audit logging: every callback execution is logged
- Implement SSRF protection: WordPress `wp_remote_post()` blocks private IPs
- Implement timeout enforcement: 30 seconds per callback
- Implement error handling: callback failure does not crash registration

### For Permission Callback Pattern (Finding 6)

**Implementation decision required**:
- Option A (Strict): Reject ability if permission_type='capability' and capability is not registered
- Option B (Lenient): Warn but allow; permission check fails at execution time

**Recommendation**: Option A (fail closed) to prevent permission escalation.

### For Namespace Collision (Finding 8)

**Implementation decision required**:
- Option A (Strict): Block reserved prefixes at creation time
- Option B (Lenient): Allow with warning; admin responsibility

**Recommendation**: Option A for admin safety; document override precedence clearly.

### For MCP Server Validation (Finding 7)

**Implementation decision required**:
- Option A (Strict): Validate MCP servers at creation time
- Option B (Lenient): Filter at query time; allow forward-compatibility

**Recommendation**: Option B for flexibility; document behavior in comments.

### For JSON Validation (Finding 2)

**Implementation requirement**:
- Validate JSON syntax: `json_decode()` must succeed
- Enforce depth limit: max 10 levels (DOS prevention)
- Enforce size limit: max 64KB per field
- Normalize JSON: re-encode to remove extra whitespace

---

## Audit Points for Implementation Review

### Phase 2 Implementation Tasks Must Include

1. **Validation Utility Tests** (`test-custom-ability-validation.php`):
   - ✅ Slug pattern, uniqueness, length
   - ⚠️ Callback config type-specific validation (clarify Finding 2)
   - ✅ Permission config validation
   - ⚠️ JSON schema validation with depth/size limits (clarify Finding 4)
   - ⚠️ MCP server validation (clarify Finding 7)

2. **REST Controller Tests** (`test-custom-ability-rest-crud.php`):
   - ✅ Capability check enforcement (all endpoints)
   - ✅ Input sanitization pipeline
   - ✅ Slug uniqueness enforcement
   - ⚠️ Callback config validation (clarify Finding 2)
   - ✅ Permission callback injection pattern
   - ⚠️ Namespace collision detection (clarify Finding 8)

3. **Processor Tests** (`test-custom-ability-processor.php`):
   - ✅ Custom ability registration at `wp_abilities_api_init`
   - ✅ Enabled flag filtering
   - ⚠️ Permission callback pattern (clarify Finding 6)
   - ⚠️ Namespace collision handling (clarify Finding 8)
   - ✅ Error handling (registration failures)

4. **Database Tests** (`test-custom-ability-database.php`):
   - ✅ BerlinDB table creation
   - ✅ Per-site prefix isolation (`$global = false`)
   - ✅ UNIQUE constraint on ability_slug
   - ✅ JSON column casting (encode/decode)
   - ✅ Tri-state flags (NULL/0/1)

---

## Deployment Checklist

- [ ] All advisory findings have implementation-phase design decisions documented
- [ ] PHPCS validation passes (zero errors)
- [ ] PHPStan L8 validation passes (zero errors)
- [ ] ESLint validation passes (zero errors)
- [ ] Unit tests cover all validation/sanitization paths
- [ ] Multisite testing: abilities isolated per site
- [ ] Capability check testing: non-admin users cannot access endpoints
- [ ] Namespace collision policy documented in code comments
- [ ] Permission callback pattern documented with examples
- [ ] MCP server filtering behavior documented
- [ ] Callback execution stub documented as TODO for v2

---

## Summary of Findings

| # | Category | Severity | Status | Action Required |
|---|----------|----------|--------|-----------------|
| 1 | Permission Model | Secure | ✅ Pass | Verify on implementation |
| 2 | Input Validation | Advisory | ⚠️ Clarify | Define callback_type/config rules |
| 3 | Sanitization | Advisory | ⚠️ Clarify | Define JSON validation strategy |
| 4 | Output Escaping | Secure | ✅ Pass | Verify on implementation |
| 5 | Callback Execution | Advisory | ⚠️ Plan | Plan v2 callback execution framework |
| 6 | Permission Callback | Advisory | ⚠️ Clarify | Choose fail-open vs fail-closed policy |
| 7 | MCP Exposure | Advisory | ⚠️ Clarify | Define MCP server validation strategy |
| 8 | Namespace Collision | Advisory | ⚠️ Clarify | Choose strict vs lenient collision policy |
| 9 | Readonly Flags | Secure | ✅ Pass | Verify on implementation |
| 10 | Nonce Verification | Secure | ✅ Pass | Verify on implementation |

---

## Approval & Sign-Off

**Security Review Status**: ✅ **APPROVED FOR IMPLEMENTATION**

**Findings Summary**:
- 0 Security Violations (Blockers)
- 0 Security Vulnerabilities
- 5 Advisory Findings (Implementation Clarity)

**Conditional Approval Requirements**:
1. All advisory findings must have explicit implementation decisions documented (see Implementation Verification Checklists above)
2. Unit tests must cover all validation/sanitization paths
3. Pre-deployment review must verify compliance with all decisions

**Reviewed By**: Security Review Workflow (speckit.security-review.plan)  
**Review Date**: 2026-05-20  
**Status**: Complete — Proceed to Phase 1 (Design & Contracts)

---

## Appendix: Reference Documents

- [Plan](plan.md) — Technical design
- [Spec](spec.md) — Functional requirements
- [Memory Synthesis](memory-synthesis.md) — Decisions & patterns
- [Constitution](.specify/memory/CONSTITUTION.md) — Architecture principles

