# Memory Index

This is a compact routing map for durable memory. Keep it short. It points to source entries and helps agents decide what to read; it does not replace the source memory files.

## Active Decisions
| ID | Title | Scope | Tags | Status | Source |
|---|---|---|---|---|---|
| DEC-PERM-CB | AC rule-gated permission_callback injection | Sitewide/Override | access-control, ability-args, fail-open | Active | DECISIONS.md |
| ARCH-ADV-001 | boot() conditional hook deviation from Boot Flow Rule | Sitewide/Override | hooks, loader, PATH-A/B | Active | DECISIONS.md |
| DEC-FAIL-OPEN-NOTICE | Fail-open library absence must pair with manage_options admin notice | Plugin-wide | fail-open, admin-notice, library | Active | DECISIONS.md |
| DEC-PROTECTED-SLUGS-PATTERN | Centralized exclusion utility with filter extensibility | REST/Utilities | filtering, REST-API, extensibility | Active | DECISIONS.md |
| DEC-EARLY-404-REST-CHECK | Early 404 checks before database lookups in REST controllers | REST | access-control, fail-fast, security | Active | DECISIONS.md |
| DEC-HOOK-PARAM-EXTRACTION | Hook object parameter extraction via method_exists check | Logger | hook-adaption, objects, defensive | Active | DECISIONS.md |
| DEC-DURATION-CALC-TIMESTAMPS | Duration calculation from start_time/end_time timestamps | Logger | timing, microtime, measurement | Active | DECISIONS.md |
| DEC-VARIADIC-CALLBACK-WRAP | Variadic callback wrapping for forwards-compatible permission callbacks | Logger | callbacks, forwards-compatibility, wrapping | Active | DECISIONS.md |
| DEC-NAMESPACE-CONVENTION | Project uses AcrossAI_Abilities_Manager\Includes\* underscore convention | Plugin-wide | namespace, PSR-4, pattern | Active | DECISIONS.md |
| DEC-UTILITY-STATIC-ONLY | Utility classes are 100% static; only orchestrators use singleton | Plugin-wide | utilities, singleton, stateless | Active | DECISIONS.md |
| DEC-USE-STATEMENT-CONSISTENCY | All use statements must match underscore convention | Plugin-wide | imports, namespace, pr-review | Active | DECISIONS.md |
| DEC-TABLE-SOFT-SINGLETON | BerlinDB Table subclasses stay soft-singleton when activation or tests instantiate them directly | Sitewide/DB | berlinddb, singleton, activator | Active | DECISIONS.md |
| DEC-JSON-SIZE-GUARD | Registry-driven JSON fields get a 64 KB DB-layer guard in save paths | Sitewide/DB | json, berlinddb, size-guard | Active | DECISIONS.md |
| DEC-BY-SOURCE-AUTHZ | Query-layer helpers remain auth-free; callers must gate before exposure | Sitewide/DB | query-layer, authz, separation-of-concerns | Active | DECISIONS.md |
| DEC-DESIGN-OVERRIDES-DATAVIEWS | User design prototype overrides DataViews/DataForm Constitution §III mandate | Abilities/Admin | dataviews, dataform, constitution, design | Active | DECISIONS.md |
| DEC-ABILITIES-DUAL-MODE-LIST | GET /abilities branches source=db→DB query, else→registry merge; format_merged_ability() normalises shape | Abilities REST | rest, registry, merge, formatter | Active | DECISIONS.md |
| DEC-NODE-20-BUILD-REQUIRED | npm run build requires Node ≥ 20; toSorted dependency fails silently on Node 16 | Build | node, nvm, build, toSorted | Active | DECISIONS.md |
| DEC-MENU-HOOK-SUFFIX | Hardcode `toplevel_page_{slug}`; avoid `get_hook_suffix()` coupling | Admin/Enqueue | hook-suffix, enqueue, menu, yoda | Active | DECISIONS.md |

## Architecture Constraints
| ID | Constraint | Scope | Tags | Source |
|---|---|---|---|---|
| AC-HOOKS-MAIN | Only Main.php calls loader->add_action/add_filter; variable-first pattern | Plugin-wide | hooks, loader, main | CONSTITUTION.md §I |
| AC-ENQUEUE-ADMIN | wp_enqueue_script/style ONLY in Admin\Main::enqueue_scripts/styles | Admin | assets, admin-main | CONSTITUTION.md §I |
| AC-REST-SPLIT | REST controller split when >400 lines; orchestrator + sub-controllers in Rest/ | REST | rest, modularization | CONSTITUTION.md §I |
| AC-REGISTRY-QUERY | Filter/sort/paginate via AcrossAI_Ability_Registry_Query::query() only | Sitewide | rest, utilities | plan.md T006b |
| AC-MENU-IN-PLACE | admin/Partials/Menu.php updated in-place; no new menu class | Admin | menu, partials | FR-020 |
| AC-QUERY-LAYER-FILTERING | List filtering in query builder, not REST controller | REST/Utilities | filtering, query-builder, pagination | ARCHITECTURE.md |
| AC-FILE-HEADER-PATTERN | @package AcrossAI_Abilities_Manager, @subpackage full/path, @since 0.1.0 | Plugin-wide | headers, phpcs, standards | ARCHITECTURE.md |
| ARCH-UNIFIED-ABILITIES-STORAGE | Abilities and Sitewide DB wrappers share the unified abilities table; Sitewide override rows are identified by source semantics | Abilities/Sitewide | unified-table, berlinddb, source-boundary | ARCHITECTURE.md |

## Implementation Patterns
| ID | Pattern | Scope | Tags | Source |
|---|---|---|---|---|
| PATTERN-SINGLE-SOURCE-UTILITY | Extract duplication to single utility class | Utilities | DRY, reusability, modularity | ARCHITECTURE.md |
| PATTERN-STAGE-NAMING | Multi-stage data with distinct variable names per transformation stage | Logger | clarity, multi-stage, readability | ARCHITECTURE.md |
| PATTERN-FEATURE-ASSET-SEPARATION | Feature-specific asset separation from main manager assets | Logger/Admin | assets, modularity, decoupling | ARCHITECTURE.md |
| PATTERN-ENQUEUE-PAGE-GUARD | `is_*_page()` helpers + Yoda `===`; no `strpos` variables in enqueue guards | Admin/Enqueue | enqueue, guards, is_page, strpos | ARCHITECTURE.md |
| PATTERN-ASSET-DECOMMISSION-ORDER | Remove PHP `include` first, then webpack entry + source files, then clean build | Admin/Build | decommission, webpack, include, order | ARCHITECTURE.md |

## Bug Patterns
| ID | Pattern | Affected Area | Tags | Source |
|---|---|---|---|---|
| BUG-BERLINDB-UNLIMITED | `number => -1` → absint → LIMIT 1 | BerlinDB queries | berlinddb, unlimited, number | BUGS.md |
| BUG-FLAT-ARGS-PATH | inject_override_args writing top-level $args keys | Ability registration | args-path, merger, annotations | BUGS.md |
| BUG-PARTIAL-HOOK-FIELDS | Partial-save paths fire after_save with incomplete $fields | Sitewide REST | hooks, after_save, partial-save | BUGS.md |
| BUG-UNIMPLEMENTED-HOOK | apply_filters() declared in plan but missing from implementation | Sitewide REST | filter, apply_filters, extensibility | BUGS.md |
| BUG-LOOSE-COMPARISON-BYPASS | Type coercion in loose equality access checks | Access Control | type-safety, security, injection | BUGS.md |
| BUG-SLUG-SUFFIX-MISMATCH | REST create expects slug_suffix (suffix only), not ability_slug (full slug) | Abilities REST | slug, prefix, create, form | BUGS.md |
| BUG-UNCONDITIONAL-ASSET-INCLUDE | `include .asset.php` without `file_exists` guard causes PHP fatal on missing bundle | Admin/Enqueue | asset-include, fatal, build, constructor | BUGS.md |

## Security Constraints
| ID | Constraint | Scope | Tags | Source |
|---|---|---|---|---|
| SEC-01 | `sanitize_ability_slug()` applied at every REST endpoint receiving a slug; max 255 chars | All REST endpoints | slug, sanitize, length | security-constraints.md |
| SEC-02 | `before_save` hook fires on sanitized `$fields` only; re-apply bool→int before BerlinDB | Sitewide REST | hook, cast, berlinddb | security-constraints.md |
| SEC-03 | `AcrossAI_Sitewide_Table::$global = false` — per-site prefix; multisite isolation explicit | Sitewide/DB | multisite, berlinddb, table-prefix | security-constraints.md |
| SEC-04 | Strict type comparison for access control checks | Access Control | type-safety, PHP, security | security-constraints.md |

## Accepted Deviations
| ID | Deviation | Scope | Expiry/Review | Source |
|---|---|---|---|---|
| ARCH-ADV-001 | `boot()` wires hooks directly (bypasses Boot Flow Rule) when PATH-A/B conditional loading required | Sitewide/Override | Review if Boot Flow Rule gains conditional-load support | DECISIONS.md |
| DEV1 | `McpVisibilityControl` uses compound-control pattern instead of DataForm | Sitewide/Admin | Review if DataForm gains compound-control support | memory-synthesis.md |

## Worklog Milestones
| Date | Milestone | Scope | Tags | Source |
|---|---|---|---|---|
| 2026-05-24 | Specs 008-010 delivered: unified table, REST CRUD, React admin UI (custom page live) | Abilities | spec-008, spec-009, spec-010, unified-table | WORKLOG.md |
| 2026-05-20 | Feature 006 logger establishes hook parameter adaptation patterns | Logger | patterns, reusability, hook-adaption | WORKLOG.md |

| DEC-STABLE-UPGRADE-WINDOW | Prioritize first stable releases (v1.0.0, v1.0.1) when upgrading from dev branches | Dependencies | stable-release, upgrade, risk-mitigation | DECISIONS.md |
| DEC-REVALIDATE-SECURITY-POST-UPGRADE | Re-validate security constraints (SEC-04, SEC-03, DEC-PERM-CB, DEC-FAIL-OPEN-NOTICE) after library upgrades | Dependencies, Security | security-constraints, validation, post-upgrade | DECISIONS.md |

## Architecture Patterns (continued)
| ARCH-ZERO-CODE-DEPENDENCY-UPGRADE | Singleton + service locator pattern enables dependency upgrades without plugin code changes | Dependencies | architecture, singleton, service-locator, upgrades | ARCHITECTURE.md |

## Bug Patterns (continued)
| BUG-AC-NULL-RETURN-SILENT-FAIL | Access control permission checks silently fail when library returns null instead of false | Access Control | type-safety, null-return, silent-fail | BUGS.md |

## Worklog Milestones (continued)
| 2026-05-20 | 4-Phase library upgrade workflow validated; zero-code dependency upgrade with 100% test pass rate | Feature 007 | workflow, library-upgrade, zero-code, testing | WORKLOG.md |
