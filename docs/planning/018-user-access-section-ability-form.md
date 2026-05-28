# Planning: User Access Section — Ability Edit / Add Form (Feature 018)

Add a "User Access" section to the ability create and edit forms that renders the
`AccessControl` component from the already-vendored `wpboilerplate/wpb-access-control`
library (v1.0.2), placed immediately after "Annotation Overrides" (Section 4).

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "018-user-access-section-ability-form"

# 2. Specify
/speckit.specify "Add 'User Access' section (new Section 5) to AbilityForm.jsx
immediately after 'Annotation Overrides' (Section 4). Renders the AccessControl
component from @wpb/access-control (v1.0.2). In create mode show a save-first
placeholder. In edit/override mode render the component only when savedAbility
has a slug and the library is available, or show a disabled notice when it is not.
Renumber existing Section 5 (Callback) → 6, Section 6 (Schema) → 7.
Five files change: composer.json (version pin), webpack.config.js (alias update),
src/scss/abilities/admin.scss (SCSS import), admin/Main.php (flag),
and src/js/abilities/components/AbilityForm.jsx (new section)."
```

### Detailed Description for `/speckit.specify`

> **Before writing a single line of code, read and internalize all three of
> these governing documents in full:**
>
> 1. `.agents/skills/wp-plugin-development/SKILL.md` — and every file under
>    `.agents/skills/wp-plugin-development/references/` (boot-flow, security,
>    rest-api, structure, hooks).
> 2. `.specify/memory/CONSTITUTION.md` — pay special attention to §I Modular
>    Architecture, §II WordPress Standards, §IV Security First, §VI DRY,
>    §VII Definition of Done, and the Boot Flow Rule, Module Contract, and
>    REST Controller Pattern.
> 3. `AGENTS.md` — the singleton pattern block, hook registration rules, and
>    the Before Commit Checklist.
>
> Every decision — file location, namespace, hook registration, sanitization,
> singleton usage — must be justified against one of those three documents.
>
> Do not write code that would fail any Definition-of-Done gate:
> PHPStan level 8, PHPCS, ESLint, security review, all `__()` calls using
> `'acrossai-abilities-manager'`, and `npm run validate-packages`.
>
> ---
>
> ### Background — what is already done; do NOT redo it
>
> The following groundwork is complete. Verify each item before touching the
> related file. If any item is absent, stop and flag it instead of working around it.
>
> | # | Fact | How to verify |
> |---|------|---------------|
> | B-1 | `wpboilerplate/wpb-access-control` is in `composer.json` `require` | `grep "wpb-access-control" composer.json` |
> | B-2 | `@wpb/access-control` webpack alias currently resolves to `vendor/wpboilerplate/wpb-access-control/js/index.js` — **CHANGE-2 will update this** | `grep -n "@wpb/access-control" webpack.config.js` |
> | B-3 | `AcrossAI_Abilities_Access_Control` singleton exists at `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php` with public methods `is_available()`, `register_rest_api()`, `get_manager()` | read the file |
> | B-4 | `rest_api_init → $abilities_ac->register_rest_api()` is already hooked in `includes/Main.php` | `grep -n "abilities_ac" includes/Main.php` |
> | B-5 | The nonce middleware `apiFetch.createNonceMiddleware(config.nonce)` is already registered once in `src/js/abilities/index.js` on app boot | read the file |
> | B-6 | `window.acrossaiAbilitiesManager` already exposes `nonce` and `rest_url` (the full REST root URL) | `grep -n "acrossaiAbilitiesManager" admin/Main.php` |
> | B-7 | Library v1.0.2 ships two fixes: (a) `useEffect` now guards on a non-empty `resourceKey` and re-runs when `restApiRoot`/`encodedNs`/`resourceKey` change; (b) `AccessControl.scss` is no longer imported by `AccessControl.js` — it is imported by `index.js` only, so embedded consumers must import the SCSS themselves. After `composer update`, verify both changed files are present in `vendor/`. | `grep -n "if ( ! resourceKey )" vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` |
>
> ---
>
> ### CHANGE-1 — Update composer to v1.0.2
>
> Run:
> ```bash
> composer require wpboilerplate/wpb-access-control:^1.0.2
> composer update wpboilerplate/wpb-access-control
> composer dump-autoload
> ```
>
> After this:
> - `vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` must NOT
>   contain `import './AccessControl.scss'`
> - `vendor/wpboilerplate/wpb-access-control/js/index.js` must contain
>   `import './AccessControl.scss'`
> - `vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` must export
>   `export function AccessControl` (named) AND `export default AccessControl`
>
> If any of the above is not true, stop and flag — do not continue.
>
> ---
>
> ### CHANGE-2 — Update the `@wpb/access-control` webpack alias
>
> **File**: `webpack.config.js`
>
> The alias currently points to `index.js`. In v1.0.2 `index.js` imports
> `AccessControl.scss`, so if the alias stays on `index.js` the SCSS will
> still be extracted into `build/js/abilities.css` (wrong CSS file). Point
> the alias directly at `AccessControl.js` instead:
>
> ```js
> // Change:
> 'vendor/wpboilerplate/wpb-access-control/js/index.js'
> // To:
> 'vendor/wpboilerplate/wpb-access-control/js/AccessControl.js'
> ```
>
> Only this one string changes inside the `alias` block. No other part of
> `webpack.config.js` changes.
>
> ---
>
> ### CHANGE-3 — Import `AccessControl.scss` from the abilities stylesheet
>
> **File**: `src/scss/abilities/admin.scss`
>
> Because the alias now points at `AccessControl.js` (which does not import
> its own SCSS), the consumer must pull in the styles explicitly. Add this
> import to the abilities admin stylesheet so the styles land in
> `build/css/abilities.css`, which is already enqueued by `admin/Main.php`:
>
> ```scss
> @import '../../../vendor/wpboilerplate/wpb-access-control/js/AccessControl';
> ```
>
> Add it at the end of the file, after existing imports. Do not add it
> anywhere in the JS bundle — only in the SCSS entry. Do not enqueue a new
> CSS file in PHP.
>
> ---
>
> ### CHANGE-4 — Add `access_control_available` to `window.acrossaiAbilitiesManager`
>
> **File**: `admin/Main.php`
>
> Read `CONSTITUTION.md §IV Security First` before touching this file.
>
> The `wp_add_inline_script` call in `enqueue_scripts()` that writes
> `window.acrossaiAbilitiesManager` currently exposes `nonce`, `rest_url`,
> `rest_namespace`, and `current_user_id`. Add a fifth key:
>
> ```php
> 'access_control_available' => \AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control::instance()->is_available(),
> ```
>
> - Call `::instance()->is_available()` — never instantiate the class directly.
> - `is_available()` returns `bool` — `wp_json_encode` will serialize it as
>   JSON `true` or `false`, which is correct.
> - Do not change any other key in the inline script payload.
> - PHPCS must pass after this change (correct use statement or FQCN).
> - PHPStan level 8 must pass after this change.
>
> ---
>
> ### CHANGE-5 — Add "User Access" section to `AbilityForm.jsx`
>
> **File**: `src/js/abilities/components/AbilityForm.jsx`
>
> Read `CONSTITUTION.md §VI DRY` and `AGENTS.md §Before Commit Checklist`
> before touching this file.
>
> #### Step A — Import
>
> Add the following import at the top of the file, after the existing imports
> and before the first `const` declaration:
>
> ```js
> import { AccessControl } from '@wpb/access-control';
> ```
>
> The webpack alias (updated in CHANGE-2) now resolves this to
> `vendor/wpboilerplate/wpb-access-control/js/AccessControl.js`. Do not
> import from a relative path. Do not import `default` — use the named export.
>
> #### Step B — Read the window config once at module top
>
> At the module level (outside the component function), add:
>
> ```js
> const abilitiesConfig = window.acrossaiAbilitiesManager || {};
> ```
>
> Do not declare this inside the component function body — it is stable for
> the page lifetime. `nonce` and `rest_url` are already read from this object
> in `src/js/abilities/api/client.js`; follow the same pattern.
>
> #### Step C — Insert Section 5 "User Access"
>
> Locate the closing `</div>` of Section 4 (Annotations / Annotation
> Overrides) and the opening `<div className="sect">` of the current Section 5
> (Callback). Between them, insert the new section exactly as follows:
>
> ```jsx
> {/* ── Section 5 — User Access ── */}
> <div className="sect">
>     <div className="sect-hdr">
>         <div className="sect-title">
>             <span className="sect-num">5</span>
>             {__('User Access', 'acrossai-abilities-manager')}
>         </div>
>         <div className="sect-desc">
>             {__('Who can use this ability.', 'acrossai-abilities-manager')}
>         </div>
>     </div>
>
>     {isCreate && (
>         <p className="desc">
>             {__(
>                 'Save this ability first to configure user access.',
>                 'acrossai-abilities-manager'
>             )}
>         </p>
>     )}
>
>     {!isCreate && !abilitiesConfig.access_control_available && (
>         <p className="notice notice-warning inline-notice">
>             {__(
>                 'User Access is inactive — the wpb-access-control library is not loaded.',
>                 'acrossai-abilities-manager'
>             )}
>         </p>
>     )}
>
>     {!isCreate && savedAbility?.ability_slug && abilitiesConfig.access_control_available && (
>         <AccessControl
>             namespace="acrossai-abilities"
>             resourceKey={savedAbility.ability_slug}
>             restApiRoot={abilitiesConfig.rest_url || '/wp-json'}
>             nonce={abilitiesConfig.nonce || ''}
>             title={__('User Access', 'acrossai-abilities-manager')}
>             description={__(
>                 'Who can use this ability.',
>                 'acrossai-abilities-manager'
>             )}
>         />
>     )}
> </div>
> ```
>
> **Critical constraints for this section:**
>
> - `namespace` MUST be the hardcoded string `"acrossai-abilities"` — this is
>   the plugin's registered namespace in the `wpb_access_control` table.
> - The component gate is `!isCreate && savedAbility?.ability_slug && abilitiesConfig.access_control_available`.
>   All three conditions are required:
>   - `!isCreate` — no slug exists yet in create mode
>   - `savedAbility?.ability_slug` — mounts the component only after the store
>     has been seeded; prevents mounting with an empty `resourceKey` on the
>     first render before the async pre-seed completes. The library (v1.0.2)
>     also guards against an empty `resourceKey` internally, but gating here
>     is the correct consumer-side pattern and avoids an unnecessary mount/unmount cycle.
>   - `abilitiesConfig.access_control_available` — only render when the PHP
>     class confirms the library is loaded
> - `resourceKey` is `savedAbility.ability_slug` (no fallback needed — the
>   gate above guarantees it is non-empty).
> - `restApiRoot` maps to `abilitiesConfig.rest_url` — the full WP REST root
>   URL already serialized by PHP (e.g. `"https://site.com/wp-json"`).
>   Do NOT use `restApiRoot={abilitiesConfig.rest_namespace}` — that is the
>   relative namespace path, not the root.
> - `nonce` maps to `abilitiesConfig.nonce` — the nonce middleware is already
>   registered globally (B-5), but the `AccessControl` component also uses the
>   nonce directly in its `apiFetch` calls, so it must be passed explicitly.
> - Do NOT pass an `onSave` prop — this feature does not need to react to the
>   access-control save event.
> - The `AccessControl` component has its own internal Save button and manages
>   its own save lifecycle. It is completely independent of the form's `isDirty`
>   / `isSaving` state. Do NOT add this section to `isDirty` tracking, do NOT
>   include it in `handleSave()`, and do NOT wrap it in the form's save flow.
> - The section is visible in BOTH `isEdit` (db) and `isNonDb` (override) modes
>   — there is no mode gate beyond `isCreate`.
> - Do NOT import or enqueue `AccessControl.scss` anywhere in JS — that is
>   handled by CHANGE-3 (the SCSS entry).
>
> #### Step D — Renumber existing sections
>
> After inserting Section 5, the existing numbering is off by one for two
> sections. Apply these and only these `sect-num` changes:
>
> | Current number string | New number string | Section title |
> |-----------------------|-------------------|---------------|
> | `5` | `6` | Callback |
> | `6` | `7` | Schema |
>
> Change the `<span className="sect-num">5</span>` inside the Callback section
> to `6`, and the `<span className="sect-num">6</span>` inside the Schema
> section to `7`. Do not touch any other section numbers.
>
> #### Step E — ESLint must pass
>
> Run `npm run lint:js` after this change. Fix any ESLint errors before
> reporting the task complete. Common issues to watch:
> - Unused import — `AccessControl` is conditionally rendered but ESLint
>   still counts it as used.
> - `react/react-in-jsx-scope` — this project uses `@wordpress/element`; if
>   this rule fires, check that the existing import pattern is followed.
> - Prop type warnings — the vendor component does not ship PropTypes; ignore
>   warnings that originate inside `node_modules` or `vendor/`.
>
> ---
>
> ### What must NOT change
>
> - Do not modify `includes/Main.php` — the REST hook is already wired (B-4).
> - Do not modify `src/js/abilities/index.js` — nonce middleware is already
>   registered (B-5).
> - Do not add a new JS entry point or PHP file.
> - Do not change any REST endpoint, DB schema, or REST response shape.
> - Do not change the form save flow (`handleSave`, `isDirty`, `isSaving`).
> - Do not change the section numbers for Sections 1, 2, 3, or 4.
> - Do not add `admin_notices` logic — that already exists in
>   `AcrossAI_Abilities_Access_Control::maybe_show_library_notice()` and is
>   hooked separately.
> - Do not enqueue a new CSS file in `admin/Main.php` — the SCSS import in
>   CHANGE-3 lands styles in `build/css/abilities.css`, which is already
>   enqueued.
>
> ---
>
> ### CONSTRAINTS
>
> - Exactly five files change: `composer.json` (version pin), `webpack.config.js`
>   (alias), `src/scss/abilities/admin.scss` (SCSS import), `admin/Main.php`
>   (flag), `src/js/abilities/components/AbilityForm.jsx` (new section).
> - Every `__()` call must use `'acrossai-abilities-manager'` as the text domain.
> - PHPStan level 8 must pass with zero errors after CHANGE-4.
> - PHPCS must pass with zero errors after CHANGE-4.
> - ESLint must pass with zero errors after CHANGE-5.
> - `npm run validate-packages` must pass after CHANGE-5.
> - Apply changes in order (CHANGE-1 through CHANGE-5) and verify quality
>   gates for PHP changes before moving to JS changes.

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer require wpboilerplate/wpb-access-control:^1.0.2
composer update wpboilerplate/wpb-access-control
composer dump-autoload
composer run phpcs
composer run phpstan
npm run lint:js
npm run validate-packages

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

### CHANGE-1 — composer v1.0.2

- [ ] `vendor/wpboilerplate/wpb-access-control/js/AccessControl.js` does NOT
      contain `import './AccessControl.scss'`.
- [ ] `vendor/wpboilerplate/wpb-access-control/js/index.js` contains
      `import './AccessControl.scss'`.
- [ ] `AccessControl.js` exports `export function AccessControl` (named) AND
      `export default AccessControl`.

### CHANGE-2 — webpack alias

- [ ] `webpack.config.js` alias points to `js/AccessControl.js`, not `js/index.js`.
- [ ] `npm run build` completes without errors.
- [ ] `build/js/abilities.css` does NOT exist (no CSS extracted from JS bundle).

### CHANGE-3 — SCSS import

- [ ] `src/scss/abilities/admin.scss` contains the `@import` for `AccessControl`.
- [ ] `build/css/abilities.css` contains `.wpb-ac` rules after rebuild.

### CHANGE-4 — `access_control_available` flag

- [ ] `window.acrossaiAbilitiesManager.access_control_available` is `true` when
      `wpb-access-control` library is loaded (open browser console on the abilities page).
- [ ] The flag is `false` when the library class is not present.
- [ ] No other keys in `window.acrossaiAbilitiesManager` changed.

### CHANGE-5 — "User Access" section in `AbilityForm.jsx`

- [ ] Opening the **Add New** form: Section 5 shows the "Save this ability first"
      placeholder. No JS errors in console. No REST calls to `wpb-ac/v1` routes.
- [ ] Opening an **existing db ability** (edit mode): Section 5 renders the
      `AccessControl` component UI with the correct `resourceKey` (ability slug).
      Provider dropdown loads. Choosing a provider and clicking **Save Access Control**
      sends a `PUT` to `{rest_root}/wpb-ac/v1/rules/acrossai-abilities/{slug}` — completely
      independent of the main form. Clicking **Save Changes** sends a `PUT` to
      `{rest_root}/acrossai-abilities-manager/v1/abilities/{encoded-slug}` — does not
      touch access control. The two save flows never interact.
- [ ] Opening an **override ability** (non-db source): Section 5 also renders the
      `AccessControl` component with the correct `resourceKey`. Behaviour is
      identical to the edit-mode check above.
- [ ] With `access_control_available = false` in the window config (simulate by
      temporarily setting it to `false` in browser DevTools before React hydrates):
      Section 5 shows the "User Access is inactive" warning notice.
- [ ] Section numbers visible in the UI:
      1 Identity, (2 Site Permission for non-db), 3 MCP Exposure, 4 Annotations,
      5 User Access, 6 Callback, 7 Schema.
- [ ] Changing a value in the `AccessControl` component (e.g., selecting a role)
      does NOT trigger the "Unsaved changes" dot in the page title or the sticky
      save bar — the form's `isDirty` state is unaffected.
- [ ] Saving the main form (e.g., toggling Show in MCP) does NOT interact with or
      reset the `AccessControl` component state.
- [ ] `AccessControl` styles render correctly (dropdown, checkboxes, save button
      all styled) — confirms CHANGE-3 landed styles in the right CSS file.

### Quality gates

- [ ] `composer run phpstan` — zero errors.
- [ ] `composer run phpcs` — zero errors.
- [ ] `npm run lint:js` — zero errors.
- [ ] `npm run validate-packages` — passes.
- [ ] Exactly five files modified (`git diff --name-only`).
