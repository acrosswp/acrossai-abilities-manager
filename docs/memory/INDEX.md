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

## Implementation Patterns
| ID | Pattern | Scope | Tags | Source |
|---|---|---|---|---|
| PATTERN-SINGLE-SOURCE-UTILITY | Extract duplication to single utility class | Utilities | DRY, reusability, modularity | ARCHITECTURE.md |
| PATTERN-STAGE-NAMING | Multi-stage data with distinct variable names per transformation stage | Logger | clarity, multi-stage, readability | ARCHITECTURE.md |
| PATTERN-FEATURE-ASSET-SEPARATION | Feature-specific asset separation from main manager assets | Logger/Admin | assets, modularity, decoupling | ARCHITECTURE.md |

## Bug Patterns
| ID | Pattern | Affected Area | Tags | Source |
|---|---|---|---|---|
| BUG-BERLINDB-UNLIMITED | `number => -1` → absint → LIMIT 1 | BerlinDB queries | berlinddb, unlimited, number | BUGS.md |
| BUG-FLAT-ARGS-PATH | inject_override_args writing top-level $args keys | Ability registration | args-path, merger, annotations | BUGS.md |
| BUG-PARTIAL-HOOK-FIELDS | Partial-save paths fire after_save with incomplete $fields | Sitewide REST | hooks, after_save, partial-save | BUGS.md |
| BUG-UNIMPLEMENTED-HOOK | apply_filters() declared in plan but missing from implementation | Sitewide REST | filter, apply_filters, extensibility | BUGS.md |
| BUG-LOOSE-COMPARISON-BYPASS | Type coercion in loose equality access checks | Access Control | type-safety, security, injection | BUGS.md |

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
| 2026-05-20 | Feature 006 logger establishes hook parameter adaptation patterns | Logger | patterns, reusability, hook-adaption | WORKLOG.md |

