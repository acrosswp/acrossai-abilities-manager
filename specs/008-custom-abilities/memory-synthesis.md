# Memory Synthesis: Custom Abilities Manager (008)

**Feature Scope**: Database-driven ability registration system with admin UI, REST API, and Abilities API integration  
**Affected Modules**: New module `Custom_Ability`; existing integrations with Abilities API, BerlinDB pattern, REST controllers  
**Retrieval Date**: 2026-05-20  

---

## Relevant Decisions

1. **DEC-NAMESPACE-CONVENTION** (Active)  
   - All plugin code uses `AcrossAI_Abilities_Manager\Includes\*` namespace with underscore separators  
   - Applies to: All PHP classes, use statements  
   - Custom_Ability module MUST follow: `AcrossAI_Abilities_Manager\Includes\Modules\Custom_Ability\*`

2. **DEC-UTILITY-STATIC-ONLY** (Active)  
   - Utility classes are 100% static; only orchestrators use singleton  
   - Applies to: Shared sanitization, field utilities, slug validators  
   - Action: Any extracted helpers go to `includes/Utilities/` as static classes

3. **DEC-PROTECTED-SLUGS-PATTERN** (Active)  
   - Centralized exclusion utility with filter extensibility for slug validation  
   - Applies to: Custom ability slug namespace enforcement  
   - Action: Check if protected-slugs utility already exists before creating new validator

---

## Active Architecture Constraints

1. **AC-HOOKS-MAIN** (Plugin-wide)  
   - Only `Main.php` calls `loader->add_action()` / `loader->add_filter()`  
   - Variable-first pattern: resolve singletons to named variables before passing to `add_action`  
   - Exception: `boot()` method may directly call WordPress API (ARCH-ADV-001)  
   - Implication: Custom_Ability admin and REST hooks MUST be wired in `define_admin_hooks()` or `define_public_hooks()`

2. **AC-ENQUEUE-ADMIN** (Admin UI)  
   - `wp_enqueue_script()` / `wp_enqueue_style()` ONLY in `Admin\Main::enqueue_scripts()` / `enqueue_styles()`  
   - Never in module classes or Partials page classes  
   - Implication: Custom Abilities DataForm/DataViews assets enqueued in Admin\Main

3. **AC-REST-SPLIT** (REST Controllers)  
   - REST controller split when >400 lines; thin orchestrator + sub-controllers  
   - Sub-controllers live in `includes/Modules/<Feature>/Rest/`  
   - Each sub-controller has exactly one handler group (read-only, overrides, bulk, MCP)  
   - Implication: Custom Ability REST will likely decompose into:  
     - Orchestrator: `Custom_Ability_REST_Controller` (register_routes, check_permission)  
     - Sub-controllers: `Custom_Ability_Read_Controller`, `Custom_Ability_Write_Controller`

4. **AC-QUERY-LAYER-FILTERING** (Query/REST)  
   - List filtering, sorting, pagination MUST happen in query builder, NOT in REST controller  
   - BerlinDB Query classes pass all args directly to parent `query()`  
   - Implication: `Custom_Ability_Query` handles filtering; REST calls `query()` with filter args

5. **AC-FILE-HEADER-PATTERN** (Plugin-wide)  
   - `@package AcrossAI_Abilities_Manager`, `@subpackage full/path`, `@since 0.1.0`  
   - All new files MUST include this header  
   - Implication: Every Custom_Ability class file includes PHPCS-compliant header

---

## BerlinDB Implementation Patterns

From **phase-a-berlinddb-patterns.md** (durable, applies to all Query classes):

1. **Query Method Naming** (DEC-BERLINDDB-QUERY-NAMING)  
   - Prefix domain-specific methods: `insert_ability()`, `get_ability_by_id()`, `count_abilities()`  
   - Prevents collision with parent BerlinDB methods (`insert()`, `count()`, `query()`)

2. **Method Selection**  
   - Insert: `add_item()` (returns row ID)  
   - Query: `query(array $args)` (returns array of Row objects)  
   - Single: `query(['id' => $id, 'number' => 1])` (returns first match)  
   - Delete: Direct `wpdb->query()` with `wpdb->prepare()`  
   - Count: Direct `wpdb->get_var()` with `wpdb->prepare()`

3. **Security Pattern**  
   - All direct wpdb queries MUST use `wpdb->prepare()` with placeholders  
   - No raw SQL interpolation permitted

4. **Schema Indentation**  
   - Align schema column keys for PHPCS compliance:  
     ```php
     array(
         'name'     => 'ability_slug',
         'type'     => 'varchar',
         'length'   => '255',
         'null'     => false,
         'sortable' => true,
     ),
     ```

---

## Security Constraints

1. **SEC-01: Slug Sanitization**  
   - `sanitize_ability_slug()` applied at EVERY REST endpoint receiving a slug  
   - Max 255 chars enforced  
   - Pattern: `namespace/ability-name` (alphanumeric, hyphen, forward slash)

2. **SEC-03: Multisite Table Isolation**  
   - Set `$global = false` in `Custom_Ability_Table` registration  
   - Ensures per-site prefix (`wp_X_acrossai_custom_abilities` on site X)

3. **SEC-04: Strict Type Comparison**  
   - All capability/slug array checks MUST use `in_array(..., true)` (strict=true)  
   - Prevents type coercion attacks

---

## Constitution Principles (Non-Negotiable)

1. **Principle III: User-Centric Design**  
   - ALL forms MUST use `DataForm` from `@wordpress/dataviews` (not a separate package)  
   - ALL lists MUST use `DataViews` (column sorting, search, pagination)  
   - No custom form/table rendering

2. **Principle IV: Security First**  
   - Sanitize input at system boundaries  
   - Escape output at render point  
   - Nonce verification on all forms/AJAX  
   - Capability checks (`manage_options` minimum)  
   - `$wpdb->prepare()` on all queries

---

## Implementation Patterns (Reusable)

1. **Singleton Pattern** (Plugin-wide)  
   - Every module class implements:  
     ```php
     protected static $_instance = null;
     public static function instance(): self {
         if (null === self::$_instance) {
             self::$_instance = new self();
         }
         return self::$_instance;
     }
     private function __construct() {}
     ```
   - Dependencies resolved via `OtherClass::instance()` only

2. **Static Utility Pattern**  
   - Validators, sanitizers, formatters are 100% static  
   - Example: `Custom_Ability_Validator::validate_slug()`

3. **Admin Menu Pattern**  
   - Singleton submenu class wired in `Admin\Main::define_admin_hooks()`  
   - Follows existing `LogsMenu` pattern

---

## Conflict Warnings

None identified. Feature scope aligns with:
- Existing BerlinDB module patterns  
- Singleton + Loader hook architecture  
- DataForm/DataViews UI requirements  
- REST controller modularization  
- Security baseline (nonces, capabilities, prepared statements)

---

## Retrieval Notes

- **Index entries considered**: 20/20 (DEC-*, AC-*, SEC-*, PATTERN-*, BUG-* categories)
- **Source sections read**: CONSTITUTION.md (principles I–IV), DECISIONS.md (DEC-NAMESPACE, DEC-UTILITY-STATIC-ONLY, DEC-PROTECTED-SLUGS-PATTERN), ARCHITECTURE.md (module structure, constraints), phase-a-berlinddb-patterns.md (query pattern), security-constraints.md (SEC-01, SEC-03, SEC-04)
- **Synthesis size**: ~850 words (within 900-word budget)
- **Status**: ✅ Ready for planning — no hard conflicts, all constraints mapped to feature scope
