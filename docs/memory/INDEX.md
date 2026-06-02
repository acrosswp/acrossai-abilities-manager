# Memory Index

This is a compact routing map for durable memory. Keep it short. It points to source entries and helps agents decide what to read; it does not replace the source memory files.

## Active Decisions
| ID | Title | Scope | Tags | Status | Source |
|---|---|---|---|---|---|
| DEC-PERM-CB | AC rule-gated permission_callback injection | Sitewide/Override | access-control, ability-args, fail-open | Active | DECISIONS.md |
| ARCH-ADV-001 | boot() conditional hook deviation from Boot Flow Rule (Override Processor only; Logger boot() removed in Feature 017) | Sitewide/Override | hooks, loader, PATH-A/B | Active | DECISIONS.md |
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
| DEC-DESCRIPTION-VALIDATION-PATTERN | Description: DESCRIPTION_MAX_LENGTH=1000, validate_description(), maxLength={1000} | Abilities/Validator | description, validation, max-length, sec-04 | Active | DECISIONS.md |
| DEC-HACTIONS-BUTTON-DEPTH | AbilityForm.jsx: .hactions button=5-tab, sbox button=9-tab | React/UI | abilityform, button, tabs, str_replace | Active | DECISIONS.md |
| DEC-DB-WRITE-BOUNDARY-GUARD | DB write methods must enforce source-discriminant guards at method level, not via caller ordering | DB/Security | db-write, source, boundary-guard, injection | Active | DECISIONS.md |
| DEC-SAVE-OVERRIDE-RETURN-ROW | save_override() returns Row\|false (not bool); Write Controller uses row directly; PHP 7.4 union via @return docblock only | DB/BerlinDB | save_override, return-type, berlinddb, php74 | Active | DECISIONS.md |
| DEC-MCP-SERVER-SANITIZE | sanitize_mcp_servers_array(): null-passthrough, per-element sanitize+255-substr, array_slice(100), REST args schema as defence-in-depth | Abilities/REST/Sanitizer | mcp-servers, sanitize, rest-args, defence-in-depth | Active | DECISIONS.md |
| DEC-MCP-CAPABILITY-FILTER-WARN | wpb_mcp_servers_list_rest_capability filter wiring point MUST include a manage_options warning comment | Abilities/Main | mcp, capability, filter, warning-comment | Active | DECISIONS.md |
| DEC-SETTINGS-API-DEVIATION | WP Settings API accepted for scalar-field (≤5 fields) settings pages; DataForm required for dynamic UI | Admin/Settings | settings-api, dataform, constitution-deviation, 019 | Active | DECISIONS.md |
| DEC-ABILITIES-LIST-UX-025 | Pagination state driven by REST params; perPage injected from DB via window.acrossaiAbilitiesManager (NOT window.acrossaiAbilities) | Abilities/Admin | pagination, per-page, window, localize-script | Active | DECISIONS.md |
| DEC-COLUMN-VISIBILITY-LOCALSTORAGE | Column prefs in localStorage with merge-over-COLUMN_DEFAULTS; new columns always default visible (FR-025) | Abilities/Admin | column-visibility, localstorage, merge-defaults, fr-025 | Active | DECISIONS.md |

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
| ARCH-UNIFIED-ABILITIES-STORAGE | Abilities module owns the unified abilities table; override rows identified by source semantics (Sitewide classes deleted in Feature 012) | Abilities | unified-table, berlinddb, source-boundary | ARCHITECTURE.md |

## Implementation Patterns
| ID | Pattern | Scope | Tags | Source |
|---|---|---|---|---|
| PATTERN-SINGLE-SOURCE-UTILITY | Extract duplication to single utility class | Utilities | DRY, reusability, modularity | ARCHITECTURE.md |
| PATTERN-STAGE-NAMING | Multi-stage data with distinct variable names per transformation stage | Logger | clarity, multi-stage, readability | ARCHITECTURE.md |
| PATTERN-FEATURE-ASSET-SEPARATION | Feature-specific asset separation from main manager assets | Logger/Admin | assets, modularity, decoupling | ARCHITECTURE.md |
| PATTERN-ENQUEUE-PAGE-GUARD | `is_*_page()` helpers + Yoda `===`; no `strpos` variables in enqueue guards | Admin/Enqueue | enqueue, guards, is_page, strpos | ARCHITECTURE.md |
| PATTERN-ASSET-DECOMMISSION-ORDER | Remove PHP `include` first, then webpack entry + source files, then clean build | Admin/Build | decommission, webpack, include, order | ARCHITECTURE.md |
| PATTERN-MODULE-DECOMMISSION | 8-step ordered decommission: rename DB → port CRUD → update consumers → delete REST → grep-then-delete | Plugin-wide | decommission, module, berlinddb, cleanup | ARCHITECTURE.md |
| PATTERN-BERLINDDB-QUERY-PORT | BerlinDB Query port only needs $table_schema/$item_shape + use-statement updates; no new Row/Schema classes | BerlinDB | berlinddb, port, rename, query | ARCHITECTURE.md |
| PATTERN-CHECKBOX-SANITIZE | Checkbox sanitize_callback: absent→0, present→1; named public method, not closure | Admin/Settings | checkbox, sanitize, settings-api, absent-field | ARCHITECTURE.md |
| PATTERN-UNINSTALL-DATA-GATE | uninstall.php wraps DROP TABLE in opt-in delete-data gate; config options always removed | Plugin-wide | uninstall, data-gate, destructive, default-safe | ARCHITECTURE.md |
| PATTERN-LOGGER-OPTION-FEED-FILTER | Module reads option → feeds apply_filters() default; schedule guard short-circuits at 0 | Logger | logger, option, filter, retention, schedule | ARCHITECTURE.md |

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
| BUG-PHPCS-DOCBLOCK-CAPITAL | PHPDoc long descriptions starting with function name must be manually prefixed with "The " — phpcbf won't capitalize | PHP/PHPCS | phpcs, docblock, capital, phpcbf | BUGS.md |
| BUG-PHPCBF-TABS | phpcbf converts spaces→tabs; Python str_replace on PHP files must use \t not spaces | PHP/PHPCS | phpcbf, tabs, spaces, str_replace | BUGS.md |
| BUG-STATIC-METHOD-SINGLETON-BYPASS | public static on singleton class (other than instance()) bypasses ::instance() contract | Logger/Query | singleton, static, arch-review | BUGS.md |
| BUG-PHPDOC-STATIC-STALE | @static docblock not removed when static keyword is removed from method | Logger/Query | phpdoc, static, arch-review | BUGS.md |

## Security Constraints
| ID | Constraint | Scope | Tags | Source |
|---|---|---|---|---|
| SEC-01 | `sanitize_ability_slug()` applied at every REST endpoint receiving a slug; max 255 chars | All REST endpoints | slug, sanitize, length | security-constraints.md |
| SEC-02 | `before_save` hook fires on sanitized `$fields` only; re-apply bool→int before BerlinDB | Sitewide REST | hook, cast, berlinddb | security-constraints.md |
| SEC-03 | `AcrossAI_Abilities_Table::$global = false` — per-site prefix; multisite isolation explicit | Abilities/DB | multisite, berlinddb, table-prefix | security-constraints.md |
| SEC-04 | Strict type comparison for access control checks | Access Control | type-safety, PHP, security | security-constraints.md |

## Accepted Deviations
| ID | Deviation | Scope | Expiry/Review | Source |
|---|---|---|---|---|
| ARCH-ADV-001 | `boot()` wires hooks directly (bypasses Boot Flow Rule) when PATH-A/B conditional loading required — **scope: Override Processor only** (Logger boot() removed Feature 017) | Sitewide/Override | Review if Boot Flow Rule gains conditional-load support | DECISIONS.md |
| DEV1 | `McpVisibilityControl` uses compound-control pattern instead of DataForm | Sitewide/Admin | Review if DataForm gains compound-control support | memory-synthesis.md |
| DEC-SETTINGS-API-DEVIATION | WP Settings API instead of DataForm for scalar-field settings pages (≤5 fields); DataForm required for dynamic UI | Admin/Settings | None — permanent exception for scalar settings pages | DECISIONS.md |

## Worklog Milestones
| Date | Milestone | Scope | Tags | Source |
|---|---|---|---|---|
| 2026-05-24 | Specs 008-010 delivered: unified table, REST CRUD, React admin UI (custom page live) | Abilities | spec-008, spec-009, spec-010, unified-table | WORKLOG.md |
| 2026-05-20 | Feature 006 logger establishes hook parameter adaptation patterns | Logger | patterns, reusability, hook-adaption | WORKLOG.md |
| 2026-05-25 | Feature 012: Sitewide module decommissioned; Abilities module is sole override owner (T001-T030 complete) | Abilities | feature-012, decommission, berlinddb, phpcs | WORKLOG.md |
| 2026-05-25 | Feature 013: Four-field required validation complete (slug/label/description/category) | Abilities | feature-013, validation, sec-04, react | WORKLOG.md |
| 2026-05-29 | Feature 019: Settings page (WP Settings API, uninstall gate, Logger option guard); DEC-SETTINGS-API-DEVIATION added | Admin/Settings/Logger | feature-019, settings-api, uninstall-gate, logger, deviation | WORKLOG.md |

| DEC-STABLE-UPGRADE-WINDOW | Prioritize first stable releases (v1.0.0, v1.0.1) when upgrading from dev branches | Dependencies | stable-release, upgrade, risk-mitigation | DECISIONS.md |
| DEC-AC-RENDERING-GATE | access_control_available is a rendering gate only; server auth enforced by wpb-ac/v1 REST endpoints | Abilities/Admin | access-control, rendering-gate, client-side, auth | DECISIONS.md |
| DEC-AC-SAVE-FLOW-PATTERN | acSaveOk flag: reset dirty state only on confirmed AC save success; failure never blocks ability save | Abilities/React | ac, dirty-state, save-flow, acSaveOk, rt-ar-001 | DECISIONS.md |
| DEC-ACINITIAL-REF-BASELINE | acInitialRef.current set on first onChange (initial data load), not on mount | Abilities/React | ac, dirty-tracking, useRef, onChange, baseline | DECISIONS.md |
| DEC-REVALIDATE-SECURITY-POST-UPGRADE | Re-validate security constraints (SEC-04, SEC-03, DEC-PERM-CB, DEC-FAIL-OPEN-NOTICE) after library upgrades | Dependencies, Security | security-constraints, validation, post-upgrade | DECISIONS.md |

## Architecture Patterns (continued)
| ARCH-ZERO-CODE-DEPENDENCY-UPGRADE | Singleton + service locator pattern enables dependency upgrades without plugin code changes | Dependencies | architecture, singleton, service-locator, upgrades | ARCHITECTURE.md |
| PATTERN-NAMED-EXPORT-JEST | Named export of pure helper from JSX component enables Jest unit tests without rendering | React/JS | jest, named-export, pure-helper, testability | ARCHITECTURE.md |
| ARCH-SANITIZER-TWO-CLASS | AcrossAI_Sanitizer (base, owns sanitize_mcp_servers_array) ≠ AcrossAI_Abilities_Sanitizer (wrapper). PHPUnit tests must target base class FQCN. | Utilities | sanitizer, two-class, phpunit, fqcn | ARCHITECTURE.md |
| ARCH-ABILITYFORM-SECTION-ORDER | AbilityForm section order is 1–7: Identity → SitePermissions → MCP → Annotations → UserAccess → Callback → Schema (updated Feature 018) | Abilities/React | section-order, abilityform, user-access | ARCHITECTURE.md |
| PATTERN-AC-COMPONENT-INTEGRATION | Named import + AccessControl.js alias + SCSS + three-branch rendering + module-level abilitiesConfig + no onSave | Abilities/React | access-control, wpb-ac, webpack, named-import, three-branch | ARCHITECTURE.md |
| PATTERN-JEST-SECTION-SCOPE | Scope test assertions to correct .sect via sect-num to avoid false matches from sibling sections | Testing/Jest | jest, section, selector, dom, abilityform | ARCHITECTURE.md |
| PATTERN-WORDPRESS-PEER-DEPENDENCIES | @wordpress/* globals go in peerDependencies (not devDependencies) to satisfy import/no-extraneous-dependencies | Build | package.json, peer-dependencies, eslint, wordpress-globals | ARCHITECTURE.md |
| PATTERN-JESTENV-WPSCRIPTS | Browser-API tests (localStorage) must use npx wp-scripts test-unit-js, not plain npx jest | Testing/Jest | jest, wp-scripts, jsdom, localstorage, test-environment | ARCHITECTURE.md |
| ARCH-PHPUNIT-BOOTSTRAP | ABSPATH define MUST precede autoloader; phpunit.xml.dist must exclude BerlinDB-loading test files | Testing | phpunit, bootstrap, abspath, berlinddb | ARCHITECTURE.md |

## Bug Patterns (continued)
| BUG-AC-NULL-RETURN-SILENT-FAIL | Access control permission checks silently fail when library returns null instead of false | Access Control | type-safety, null-return, silent-fail | BUGS.md |
| BUG-PYTHON-STRREPLACE-PARTIAL-WRITE | Python str_replace scripts: write per-step, not once at end | Build/Tooling | python, str_replace, write, partial | BUGS.md |
| BUG-ABILITYFORM-JSX-MIXED-DEPTHS | AbilityForm.jsx: inconsistent tab depths by element — verify before str_replace | React/UI | jsx, tabs, str_replace, abilityform | BUGS.md |
| BUG-SEC04-EMPTY-AUDIT-MISS | Adding SEC-04 guard: audit same method for pre-existing empty() violations | Plugin-wide | sec-04, empty, trim, strict | BUGS.md |
| BUG-PHPSTAN-SILENT-PASS | PHPStan: exit 0 + no output = clean pass; exit 1 = failure | PHP/Tooling | phpstan, exit-code, silent, pass | BUGS.md |
| BUG-RAWURLDECODE-CONSECUTIVE-SLASHES | rawurldecode + allowlist regex needs consecutive-slash normalization | Utilities/Security | slug, sanitize, rawurldecode, consecutive-slash | BUGS.md |
| BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD | WP REST API: literal-segment routes must register before wildcard `[^/]+` routes | REST/Routing | rest, route-order, literal, wildcard, shadowing | BUGS.md |
| BUG-BERLINDB-STALE-SLUG-CACHE | After INSERT, get_override_by_slug() hits stale slug cache → null; re-read via ID inside save_override() | DB/BerlinDB | berlinddb, cache, save_override, INSERT | BUGS.md |
| BUG-MCP-PUBLIC-KEY-MAPPING | meta.mcp.public → show_in_mcp (canonical); mcp_public is a stray key the Merger never reads | Abilities/Merger | mcp, show_in_mcp, normalize_registry, registry | BUGS.md |
| BUG-WP-ELEMENT-ACT-MISSING | @wordpress/element v6+ omits act; inject via jest.requireActual('react').act in mock | Testing/Jest | wordpress, element, act, jest, v6 | BUGS.md |
| BUG-MODULE-LEVEL-WINDOW-READ | Module-level window.* reads happen at require() time; set globalThis.* before require() in tests | Testing/Jest | jest, window, module-level, require, globalThis | BUGS.md |
| BUG-JEST-ASYNC-USEEFFECT-FLUSH | React 18 useEffect with resolved promises needs await act(async()=>{}) to flush microtasks | Testing/Jest | jest, react18, useEffect, act, async, promises | BUGS.md |
| BUG-WP-API-FETCH-VIRTUAL | @wordpress/api-fetch is a WP external; always mock with { virtual: true } in Jest | Testing/Jest | jest, api-fetch, virtual, wordpress, external | BUGS.md |
| BUG-DRAFT-SEEDED-FROM-MERGED | SET_SAVED must seed draftAbility from _override[field] (null=inherit), not merged top-level | React/Store | redux, set-saved, trichip, override, draftAbility | BUGS.md |
| BUG-PHPUNIT-ABSPATH-SILENT-EXIT | ABSPATH define must precede autoloader; wrong order silently produces 0 tests with no errors | Testing | phpunit, abspath, silent-exit, bootstrap | BUGS.md |
| BUG-PHPUNIT-BERLINDDB-SCOPE | phpunit.xml.dist must be narrowly scoped; BerlinDB Table constructors fatal under stub bootstrap | Testing | phpunit, berlinddb, scope, fatal | BUGS.md |
| BUG-ABILITIES-STRIP-PROTECTED-PREEXISTING | Pre-existing test/code mismatch: strip_protected_fields test expects broader stripping than implementation provides (line 470) | Testing | phpunit, preexisting, strip-protected, mismatch | BUGS.md |
| BUG-ESLINT-DISABLE-LINE-EXACT | eslint-disable-next-line covers exactly one line; must be directly before the offending call, not before a wrapping if() | JS/ESLint | eslint, no-alert, disable-next-line, position | BUGS.md |
| BUG-PHP-ABSINT-NEGATIVE-RANGE | absint(-5)=5, valid in [1,200]; only large negatives (absint > max) fall back to default — test both cases | PHP | absint, negative, sanitize, range-check | BUGS.md |
| BUG-PHPUNIT-TYPED-PROPERTY-SETUP | WP_UnitTestCase typed class property uninitialized if set_up() is used — call singleton inline per test instead | Testing/PHPUnit | phpunit, typed-property, wp_unittestcase, singleton | BUGS.md |

## Worklog Milestones (continued)
| 2026-05-20 | 4-Phase library upgrade workflow validated; zero-code dependency upgrade with 100% test pass rate | Feature 007 | workflow, library-upgrade, zero-code, testing | WORKLOG.md |
| 2026-05-26 | Feature 014: edit+override routing unified, REST split pattern validated, SEC-001/002/003 hardening | Feature 014 | feature-014, override, rest-split, security | WORKLOG.md |
| 2026-05-27 | Feature 015: override layer hardened; BerlinDB cache bypass, mcp.public mapping, SET_SAVED seeding — 4 new patterns | Feature 015 | feature-015, override, berlinddb, mcp, redux | WORKLOG.md |
| 2026-05-27 | Feature 016: allowed-servers checkbox list, PHPUnit bootstrap established, MCP server sanitizer constants + REST args schema (T019, T020) | Feature 016 | feature-016, phpunit, mcp-servers, sanitizer, react | WORKLOG.md |
| BUG-ABILITYFORM-PANEL-PREMATURE-CLOSE | Script-based JSX edits can misplace .panel closing div; verify tab-5 </div> position after every AbilityForm.jsx edit | React/JSX | abilityform, panel, div-nesting, script-edit | BUGS.md |
| BUG-ABILITYFORM-REBASE-SECTION-SCRAMBLE | Rebase onto main silently scrambles AbilityForm.jsx .sect order; grep section markers after every rebase | React/JSX | abilityform, rebase, section-order, scramble | BUGS.md |
| ARCH-ABILITYFORM-SECTION-ORDER | Canonical AbilityForm.jsx section order 1-6 (Identity→SitePerm→MCP→Annotations→Callback→Schema); all inside single .panel | Architecture | abilityform, section-order, panel, jsx | ARCHITECTURE.md |

| 2026-05-29 | Feature 018: User Access section + AC integration pattern + 4 Jest gotchas | Abilities | feature-018, access-control, jest, react18 | WORKLOG.md |
| DEC-EVAL-PHP-CODE | eval() in php_code ability type: risk accepted, CI suppressed — **Superseded by DEC-PLUGIN-CHECK-PRODUCTION-SURFACE** | Plugin-wide | eval, php-code, owasp-a03, injection | Superseded | DECISIONS.md |
| DEC-PLUGIN-CHECK-PRODUCTION-SURFACE | Plugin Check CI scans production surface only; `%i` for SQL identifiers; forbidden functions removed/replaced; local exact suppressions only; PHPCS baseline caveat | Plugin-wide | plugin-check, sql, forbidden-functions, phpcs, ci-surface | Active | DECISIONS.md |
| PATTERN-WP-DEBUG-LOG-GUARD | Wrap error_log() in WP_DEBUG_LOG guard; phpcs:ignore inside guard; identical pattern across all call sites | PHP | error_log, WP_DEBUG_LOG, plugin-check, compliance | ARCHITECTURE.md |
| PATTERN-CI-WORKFLOW-HARDENING | GitHub Actions: SHA-pin all uses:, permissions: {} at workflow level, timeout-minutes per job | CI/GitHub | github-actions, sha-pin, permissions, timeout, security | ARCHITECTURE.md |
| PATTERN-CONSTITUTION-SYNC-REPORT | Every CONSTITUTION.md version bump must update the SYNC IMPACT REPORT HTML comment at the top | Plugin-wide | constitution, sync-impact, version-bump | ARCHITECTURE.md |
| BUG-PHPCS-ELSE-IF | else { if() } nesting fails PHPCS; rewrite as elseif — phpcbf will not auto-fix | PHP/PHPCS | phpcs, else-if, elseif, admin-main | BUGS.md |
| 2026-05-30 | Feature 020: Plugin Check CI gate, 12 error_log() guards, CONSTITUTION v1.4.3, 4 new patterns | Feature 020 | feature-020, plugin-check, error_log, ci, constitution | WORKLOG.md |
| BUG-PLUGIN-CHECK-ACTION-NODE24 | plugin-check-action@v1 silently exits 0 on Node 24.16 ubuntu-latest (≥2026-05-25); use wp-env+WP-CLI directly | CI | plugin-check, wp-env, node24, silent-exit, github-actions | BUGS.md |
| PATTERN-PLUGIN-CHECK-WP-ENV-DIRECT | Canonical Plugin Check CI: @wordpress/env + WP-CLI post-boot; never use plugin-check-action@v1 directly | CI | plugin-check, wp-env, wpcli, node24, workaround | ARCHITECTURE.md |
| 2026-05-30 | Feature 020 CI fix: 3 commits to work around plugin-check-action#579 Node 24.16 silent-exit bug | Feature 020 | ci-fix, plugin-check, node24, wp-env | WORKLOG.md |
| BUG-EVAL-NOT-SUPPRESSIBLE | eval() Plugin Check finding must be removed/replaced — workflow ignore-codes weakens the gate for all future code | Abilities/CI | eval, plugin-check, ignore-codes, forbidden | BUGS.md |
| PATTERN-REGISTERED-CALLBACK-TRUST | Replace eval/DB-stored-PHP: DB stores sanitize_key() key; apply_filters allow-list of version-controlled callables; isset+is_callable guard; WP_Error fail-closed | Abilities | eval, registered-callback, trust-model, fail-closed | ARCHITECTURE.md |
| 2026-05-31 | Feature 021: Plugin Check cleanup complete — eval() removed, registered-callback model, `%i` SQL, CI scan surface, CONSTITUTION v1.4.4 | Abilities/CI | feature-021, plugin-check, eval, registered-callback, constitution | WORKLOG.md |
| DEC-SINGLETON-PSR2-PROPERTY | $_instance renamed to $instance (PSR2 compliance) across 21 singleton classes; AGENTS.md code example is now stale | Plugin-wide | singleton, psr2, phpcs, underscore, instance | Active | DECISIONS.md |
| PATTERN-CI-QUALITY-GATE-SPLIT | Three dedicated CI jobs: phpcs.yml (WPCS), phpstan.yml (level 8), phpcompat.yml (PHPCompat, production dirs only); SHA-pinned, permissions:{} | CI | phpcs, phpstan, phpcompat, ci-split, sha-pin | ARCHITECTURE.md |
| 2026-05-31 | Feature 022: PHPCS baseline clean (0 errors, 49 files), 3 CI workflows, $instance PSR2 rename (21 classes), PHPCompat split | CI/PHP | feature-022, phpcs, phpstan, phpcompat, psr2, ci-split | WORKLOG.md |
| DEC-WPDB-PREPARE-SPREAD | $wpdb->prepare(): always spread `...$params`; array arg form triggers PHPCS/Plugin Check noise | DB/Logger | wpdb, prepare, spread, phpcs | Active | DECISIONS.md |
| BUG-PUBLIC-NAMESPACE-RESERVED | `namespace \Public` is invalid PHP (reserved keyword); rename to safe alternative (`\Front`); never add CI `--ignore` as workaround | Namespace/CI | reserved-keyword, phpcompat, phpcs, namespace | BUGS.md |
| BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE | uninstall.php option deletions must be inside $acrossai_delete_data gate; unconditional placement wipes settings on every uninstall | Plugin-wide | uninstall, data-gate, delete_option | BUGS.md |
| 2026-05-31 | Feature 023: WPBoilerplate→AcrossWP rebrand, uninstall gate fix, `\Public`→`\Front` namespace, plugin-check.yml removed (Node 24.16 wp-env bug) | Plugin-wide | feature-023, rebrand, namespace, uninstall, ci | WORKLOG.md |
| BUG-MERGER-BOOL-STRING-CAST | `(string) false === ''` — never use string-cast guard on tri-state bool fields; use `null !== $value` only | Abilities/Merger | merger, tri-state, boolean, cast, site_allowed, force-block | BUGS.md |
| BUG-INJECT-MISSING-TOP-LEVEL-FIELDS | `inject_override_args()` top-level fields (label/desc/cat) were missing; always verify `$args` write path against FR-009 field-path table | Abilities | inject_override_args, label, description, category, top-level, field-path | BUGS.md |
| BUG-NORMALIZE-REGISTRY-SOURCE-DEFAULT | `normalize_registry()` source default must be `null` not `'plugin'` — string cast prevented `empty()` guard from firing Source Detector | Abilities/Merger | normalize_registry, source, source-detector, core, plugin | BUGS.md |
| DEC-TYPECELL-REGISTRY-FALLBACK | DataViews cell renderers: read `item.field` first, fall back to `item._registry?.field` — non-db abilities have null top-level fields | JS/DataViews | typecell, _registry, non-db, callback_type, dataviews | Active | DECISIONS.md |
| DEC-FORM-HINT-REGISTRY-PATH | "Plugin declares" hints must use `savedAbility._registry.field` — merged value is the admin's own override, not the registry default | JS/Form | form-hint, _registry, plugin-declares, non-db, savedAbility | Active | DECISIONS.md |
| 2026-06-02 | Feature 024: source badge, Type badge, Plugin-declares hints, Callback read-only, inject label/desc/cat, Force Block merge fix | Abilities/JS | feature-024, merger, force-block, site_allowed, inject, source-badge | WORKLOG.md |
| 2026-06-03 | Feature 025: Pagination, per-page Settings API option, CSS tab hide, Clear All Overrides row action, Description/Show-in-REST columns, column visibility toggle — 34 tasks, 0 security findings | Abilities/Admin | feature-025, pagination, column-visibility, localstorage, settings-api, security-pass | WORKLOG.md |
