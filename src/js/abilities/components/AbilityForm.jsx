/**
 * AbilityForm — full-page form for create / edit / override.
 *
 * Variant A (source=db): create + edit modes — editable identity, callback, schema, MCP, annotations.
 * Variant B (source≠db): override mode — locked identity banner + editable override fields only.
 *
 * Layout: unified single `.panel` with numbered `.sect` sections, matching Edit Form Wireframe.
 *
 * Save model (explicit, not auto-save):
 *   - savedAbility: last-fetched server state (null for Add New)
 *   - draftAbility: current form state (merged from store)
 *   - isDirty: JSON.stringify(draft) !== JSON.stringify(saved)
 *   - "● Unsaved changes" indicator shown when isDirty
 *   - beforeunload guard registered in AbilitiesManager when isDirty
 *   - Save triggers POST /abilities (create) or POST /abilities/{id} (update)
 *
 * SC-007 override identity lock: override save payload never includes
 *   ability_slug, label, category, description, callback_type, callback_config,
 *   input_schema, output_schema.
 *
 * @since 0.2.0
 */
import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';
import SourceBadge from './cells/SourceBadge';
import CallbackConfigField from './CallbackConfigField';

const SLUG_PREFIX = 'acrossai-abilities/';
const SLUG_PATTERN = /^[a-z0-9-]+$/;

// ---------------------------------------------------------------------------
// Tri-state helpers (same pattern as AbilityEditPanel)
// ---------------------------------------------------------------------------

function ts2s(v) {
	if (true === v) {
		return 'true';
	}
	if (false === v) {
		return 'false';
	}
	return 'null';
}

function s2ts(s) {
	if ('true' === s) {
		return true;
	}
	if ('false' === s) {
		return false;
	}
	return null;
}

// ---------------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------------

function jsonParseOrNull(str) {
	if (!str) {
		return null;
	}
	try {
		return JSON.parse(str);
	} catch {
		return null;
	}
}

function formatDate(iso) {
	if (!iso) {
		return '—';
	}
	try {
		return new Date(iso).toLocaleString();
	} catch {
		return iso;
	}
}

/**
 * Validate all four required fields for create/edit mode.
 *
 * Returns an error-string object; empty string means no error.
 * This is a pure module-level helper with no side-effects.
 *
 * @param {Object} ability    Current draftAbility store value.
 * @param {string} slugSuffix Current slug suffix local state.
 * @return {Object} Error map for slug_suffix, label, description, category.
 */
function validateRequiredFields(ability, slugSuffix) {
	const required = __('This field is required.', 'acrossai-abilities-manager');
	return {
		slug_suffix: (slugSuffix || '').trim() ? '' : required,
		label: (ability.label || '').trim() ? '' : required,
		description: (ability.description || '').trim() ? '' : required,
		category: (ability.category || '').trim() ? '' : required,
	};
}

// ---------------------------------------------------------------------------
// Locked banner for Variant B (inherited identity)
// Matches wireframe .locked-banner structure
// ---------------------------------------------------------------------------
function LockedCard({ ability }) {
	const providerName =
		ability.provider ||
		__('an external source', 'acrossai-abilities-manager');
	return (
		<div className="locked-banner">
			<div className="lbi">🔒</div>
			<div>
				<div className="lkmeta">
					{__('Registered by plugin:', 'acrossai-abilities-manager')}{' '}
					<strong>{providerName}</strong>
					<SourceBadge source={ability.source} />
				</div>
				<div className="lblock">
					<div className="lf">
						<div className="lk">
							{__('Full Slug', 'acrossai-abilities-manager')}
						</div>
						<div className="lv mono">
							{ability.ability_slug}
						</div>
					</div>
					<div className="lf">
						<div className="lk">
							{__('Label', 'acrossai-abilities-manager')}
						</div>
						<div className="lv">{ability.label || '—'}</div>
					</div>
					<div className="lf">
						<div className="lk">
							{__('Category', 'acrossai-abilities-manager')}
						</div>
						<div className="lv">{ability.category || '—'}</div>
					</div>
					<div className="lf">
						<div className="lk">
							{__('Callback', 'acrossai-abilities-manager')}
						</div>
						<div className="lv">{ability.callback_type || 'noop'}</div>
					</div>
				</div>
			</div>
			{ability.description && (
				<div>
					<div className="lf">
						<div className="lk">
							{__('Description', 'acrossai-abilities-manager')}
						</div>
						<div
							className="lv"
							style={{ fontSize: '12px', color: '#646970', lineHeight: 1.55 }}
						>
							{ability.description}
						</div>
					</div>
				</div>
			)}
		</div>
	);
}

// ---------------------------------------------------------------------------
// Site permission segmented control (TGC) for Variant B
// ---------------------------------------------------------------------------
const PERMISSION_CHIPS = [
	{
		value: 0,
		label: __('Force Block', 'acrossai-abilities-manager'),
	},
	{
		value: null,
		label: __('Inherit', 'acrossai-abilities-manager'),
	},
	{
		value: 1,
		label: __('Force Allow', 'acrossai-abilities-manager'),
	},
];

function SitePermissionTGC({ value, onChange }) {
	// Normalize: true→1, false→0
	let normalized = value;
	if (true === value) {
		normalized = 1;
	} else if (false === value) {
		normalized = 0;
	}
	return (
		<div className="tgc">
			{PERMISSION_CHIPS.map((chip) => {
				let chipNorm = chip.value;
				if (true === chip.value) {
					chipNorm = 1;
				} else if (false === chip.value) {
					chipNorm = 0;
				}
				const isOn = chipNorm === normalized;
				return (
					<button
						key={String(chip.value)}
						type="button"
						className={`tgc-opt${isOn ? ' on' : ''}`}
						onClick={() => onChange(chip.value)}
					>
						{chip.label}
					</button>
				);
			})}
		</div>
	);
}

// ---------------------------------------------------------------------------
// Callback type chips for Variant A
// ---------------------------------------------------------------------------
const CALLBACK_CHIPS = [
	{ value: 'noop', label: 'noop' },
	{ value: 'filter_hook', label: 'filter_hook' },
	{ value: 'wp_remote_post', label: 'wp_remote_post' },
	{ value: 'php_code', label: 'php_code' },
];

function CallbackTypeChips({ value, onChange }) {
	return (
		<div className="chips">
			{CALLBACK_CHIPS.map((chip) => (
				<button
					key={chip.value}
					type="button"
					className={`chip${chip.value === value ? ' on' : ''}`}
					onClick={() => onChange(chip.value)}
				>
					{chip.label}
				</button>
			))}
		</div>
	);
}

// ---------------------------------------------------------------------------
// Tri-state select (replaces DataForm for annotations)
// ---------------------------------------------------------------------------
function TriStateSelect({ id, value, onChange, label, hint }) {
	return (
		<div className="fr">
			<label htmlFor={id} className="fl">
				{label}
			</label>
			<div className="ff">
				<select
					id={id}
					className="rs"
					style={{ maxWidth: '180px' }}
					value={ts2s(value)}
					onChange={(e) => onChange(s2ts(e.target.value))}
				>
					<option value="null">
						{__('inherit', 'acrossai-abilities-manager')}
					</option>
					<option value="true">
						{__('yes', 'acrossai-abilities-manager')}
					</option>
					<option value="false">
						{__('no', 'acrossai-abilities-manager')}
					</option>
				</select>
				{hint && <div className="desc">{hint}</div>}
			</div>
		</div>
	);
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

/**
 * AbilityForm component.
 *
 * @param {Object} props
 * @param {string} props.mode 'create' | 'edit' | 'override'
 * @param {number} [props.id] Ability ID (required for edit/override modes)
 * @return {JSX.Element}
 */
export default function AbilityForm({ mode, id }) {
	const dispatch = useDispatch(STORE_NAME);

	const {
		savedAbility,
		draftAbility,
		isDirty,
		isSaving,
		saveError,
		categories,
	} = useSelect(
		(select) => ({
			savedAbility: select(STORE_NAME).getSavedAbility(),
			draftAbility: select(STORE_NAME).getDraftAbility(),
			isDirty: select(STORE_NAME).getIsDirty(),
			isSaving: select(STORE_NAME).getIsSaving(),
			saveError: select(STORE_NAME).getSaveError(),
			categories: select(STORE_NAME).getCategories(),
		}),
		[]
	);

	// Local state for slug suffix (strips prefix for display)
	const [slugSuffix, setSlugSuffix] = useState('');
	const [slugError, setSlugError] = useState('');
	const [formErrors, setFormErrors] = useState({
		slug_suffix: '',
		label: '',
		description: '',
		category: '',
	});

	// JSON validation states for schema fields
	const [inputSchemaError, setInputSchemaError] = useState('');
	const [outputSchemaError, setOutputSchemaError] = useState('');

	// Raw string values for schema textareas
	const [inputSchemaRaw, setInputSchemaRaw] = useState('');
	const [outputSchemaRaw, setOutputSchemaRaw] = useState('');

	// Callback config as parsed object (kept in sync with draftAbility.callback_config)
	const callbackType = draftAbility.callback_type || 'noop';
	const callbackConfig = draftAbility.callback_config || {};

	// ---------------------------------------------------------------------------
	// On mount: load ability data, fetch categories
	// ---------------------------------------------------------------------------
	useEffect(() => {
		dispatch.fetchCategories();
		if ('create' === mode) {
			dispatch.clearDraft();
			dispatch.setSaved(null);
		} else if (id) {
			dispatch.fetchAbility(id);
		}
	}, [mode, id]); // eslint-disable-line react-hooks/exhaustive-deps

	// Sync slug suffix when savedAbility changes (edit/override: pre-populate)
	useEffect(() => {
		// Reset stale validation errors when ability loads or after a successful save
		// (FR-016, CLARIFY-Q2/B: no errors on page load).
		setFormErrors({ slug_suffix: '', label: '', description: '', category: '' });
		if (savedAbility?.ability_slug) {
			const slug = savedAbility.ability_slug;
			setSlugSuffix(
				slug.startsWith(SLUG_PREFIX)
					? slug.slice(SLUG_PREFIX.length)
					: slug
			);
		} else {
			setSlugSuffix('');
		}
	}, [savedAbility]);

	// Sync schema textarea raw strings from draftAbility
	useEffect(() => {
		const inRaw = draftAbility.input_schema
			? JSON.stringify(draftAbility.input_schema, null, 2)
			: '';
		const outRaw = draftAbility.output_schema
			? JSON.stringify(draftAbility.output_schema, null, 2)
			: '';
		setInputSchemaRaw(inRaw);
		setOutputSchemaRaw(outRaw);
	}, [savedAbility]); // Only reset on ability load, not on every draft change

	// ---------------------------------------------------------------------------
	// Patch helpers
	// ---------------------------------------------------------------------------
	const patch = useCallback(
		(changes) => {
			dispatch.updateDraft(changes);
		},
		[dispatch]
	);

	// ---------------------------------------------------------------------------
	// Slug input handler
	// ---------------------------------------------------------------------------
	function handleSlugChange(e) {
		const raw = e.target.value;
		setSlugSuffix(raw);
		if (raw.trim()) {
			setFormErrors((prev) => ({ ...prev, slug_suffix: '' }));
		}
		if (raw && !SLUG_PATTERN.test(raw)) {
			setSlugError(
				__(
					'Slug may only contain lowercase letters, numbers, and hyphens.',
					'acrossai-abilities-manager'
				)
			);
		} else {
			setSlugError('');
		}
		patch({ ability_slug: raw ? SLUG_PREFIX + raw : '' });
	}

	// ---------------------------------------------------------------------------
	// Schema validation on blur
	// ---------------------------------------------------------------------------
	function handleInputSchemaBlur() {
		if (!inputSchemaRaw) {
			setInputSchemaError('');
			patch({ input_schema: null });
			return;
		}
		const parsed = jsonParseOrNull(inputSchemaRaw);
		if (null === parsed) {
			setInputSchemaError(
				__('Invalid JSON.', 'acrossai-abilities-manager')
			);
		} else {
			setInputSchemaError('');
			patch({ input_schema: parsed });
		}
	}

	function handleOutputSchemaBlur() {
		if (!outputSchemaRaw) {
			setOutputSchemaError('');
			patch({ output_schema: null });
			return;
		}
		const parsed = jsonParseOrNull(outputSchemaRaw);
		if (null === parsed) {
			setOutputSchemaError(
				__('Invalid JSON.', 'acrossai-abilities-manager')
			);
		} else {
			setOutputSchemaError('');
			patch({ output_schema: parsed });
		}
	}

	// ---------------------------------------------------------------------------
	// Required-field blur validators
	// ---------------------------------------------------------------------------
	function handleSlugBlur() {
		if ('create' !== mode && 'edit' !== mode) {
			return;
		}
		setFormErrors((prev) => ({
			...prev,
			slug_suffix: (slugSuffix || '').trim()
				? ''
				: __('This field is required.', 'acrossai-abilities-manager'),
		}));
	}
	function handleLabelBlur() {
		if ('create' !== mode && 'edit' !== mode) {
			return;
		}
		setFormErrors((prev) => ({
			...prev,
			label: (draftAbility.label || '').trim()
				? ''
				: __('This field is required.', 'acrossai-abilities-manager'),
		}));
	}
	function handleDescriptionBlur() {
		if ('create' !== mode && 'edit' !== mode) {
			return;
		}
		setFormErrors((prev) => ({
			...prev,
			description: (draftAbility.description || '').trim()
				? ''
				: __('This field is required.', 'acrossai-abilities-manager'),
		}));
	}
	function handleCategoryBlur() {
		if ('create' !== mode && 'edit' !== mode) {
			return;
		}
		setFormErrors((prev) => ({
			...prev,
			category: (draftAbility.category || '').trim()
				? ''
				: __('This field is required.', 'acrossai-abilities-manager'),
		}));
	}

	// ---------------------------------------------------------------------------
	// Save handlers
	// ---------------------------------------------------------------------------
	async function handleSave(forceDraft = false) {
		// Required-field gate — applies to ALL save paths in create/edit mode,
		// including forceDraft=true (CLARIFY-Q5/A: no bypass).
		if ('create' === mode || 'edit' === mode) {
			const errors = validateRequiredFields(draftAbility, slugSuffix);
			setFormErrors(errors);
			if (Object.values(errors).some(Boolean)) {
				return;
			}
		}

		const data = { ...draftAbility };
		if (forceDraft) {
			data.status = 'draft';
		}

		if ('create' === mode) {
			// REST create endpoint expects slug_suffix (without prefix), not ability_slug.
			const fullSlug = data.ability_slug || '';
			data.slug_suffix = fullSlug.startsWith(SLUG_PREFIX)
				? fullSlug.slice(SLUG_PREFIX.length)
				: fullSlug;
			delete data.ability_slug;
			const ability = await dispatch.createAbility(data);
			if (ability) {
				dispatch.setSaved(ability);
				dispatch.setView({ mode: 'edit', id: ability.id });
			}
			return;
		}

		if ('edit' === mode) {
			await dispatch.updateAbility(id, data);
			return;
		}

		if ('override' === mode) {
			// SC-007: send only override fields — never identity fields
			const overrideData = {
				site_allowed: data.site_allowed,
				show_in_rest: data.show_in_rest,
				show_in_mcp: data.show_in_mcp,
				mcp_type: data.mcp_type,
				mcp_servers: data.mcp_servers,
				readonly: data.readonly,
				destructive: data.destructive,
				idempotent: data.idempotent,
			};
			await dispatch.updateAbility(id, overrideData);
		}
	}

	async function handleDelete() {
		if (
			// eslint-disable-next-line no-alert
			window.confirm(
				__(
					'Delete this ability? This cannot be undone.',
					'acrossai-abilities-manager'
				)
			)
		) {
			await dispatch.deleteAbility(id);
		}
	}

	async function handleClearOverrides() {
		if (
			// eslint-disable-next-line no-alert
			window.confirm(
				__(
					'Clear all overrides? This will restore inherited values.',
					'acrossai-abilities-manager'
				)
			)
		) {
			await dispatch.clearOverrides(id);
		}
	}

	function handleCancel() {
		dispatch.setView('list');
	}

	// ---------------------------------------------------------------------------
	// Derived state
	// ---------------------------------------------------------------------------
	const breadcrumbSlug =
		savedAbility?.ability_slug ||
		__('Add New', 'acrossai-abilities-manager');
	const isCreate = 'create' === mode;
	const isEdit = 'edit' === mode;
	const isOverride = 'override' === mode;

	// True when any required field is missing — used for CSS-only button dimming.
	// Always false in override mode (FR-006: override has no identity fields).
	const hasRequiredErrors =
		('create' === mode || 'edit' === mode)
			? (
				!slugSuffix.trim() ||
				!(draftAbility.label || '').trim() ||
				!(draftAbility.description || '').trim() ||
				!(draftAbility.category || '').trim()
			  )
			: false;

	// Save button label
	let saveBtnLabel;
	if (isSaving) {
		saveBtnLabel = __('Saving…', 'acrossai-abilities-manager');
	} else if (isCreate) {
		saveBtnLabel = __('✓ Add Ability', 'acrossai-abilities-manager');
	} else if (isEdit) {
		saveBtnLabel = __('✓ Save Changes', 'acrossai-abilities-manager');
	} else {
		saveBtnLabel = __('✓ Save Overrides', 'acrossai-abilities-manager');
	}

	// ---------------------------------------------------------------------------
	// Render
	// ---------------------------------------------------------------------------
	return (
		<div className="wrap">
			{/* Breadcrumb */}
			<div className="abilities-breadcrumb">
				<button
					type="button"
					className="button-link"
					onClick={() => dispatch.setView('list')}
				>
					{__('All Abilities', 'acrossai-abilities-manager')}
				</button>
				<span className="bc-sep">›</span>
				<span className="bc-slug">{breadcrumbSlug}</span>
			</div>

			{/* Page title row with inline hactions */}
			<div className="abilities-page-title">
				<div>
					<h1>
						{isCreate &&
							__('Add New Ability', 'acrossai-abilities-manager')}
						{isEdit &&
							__('Edit Ability', 'acrossai-abilities-manager')}
						{isOverride &&
							__('Override Ability', 'acrossai-abilities-manager')}
						{(isEdit || isOverride) && savedAbility && (
							<span className="h1-meta">
								<SourceBadge source={savedAbility.source || 'db'} />
								{isDirty && (
									<span className="unsaved">
										<span className="udot" />
										{__('Unsaved changes', 'acrossai-abilities-manager')}
									</span>
								)}
							</span>
						)}
					</h1>
				</div>
				<div className="hactions">
					<button
						type="button"
						className="button"
						onClick={handleCancel}
					>
						{__('Cancel', 'acrossai-abilities-manager')}
					</button>
					<button
						type="button"
						className="button button-primary"
						style={hasRequiredErrors ? { opacity: 0.5, pointerEvents: 'none' } : undefined}
						aria-disabled={hasRequiredErrors}
						disabled={isSaving || (!isCreate && !isDirty)}
						onClick={() => handleSave(false)}
					>
						{saveBtnLabel}
					</button>
				</div>
			</div>

			{isCreate && (
				<p className="abilities-subtitle">
					{__(
						'Define a new custom ability. Fields marked',
						'acrossai-abilities-manager'
					)}{' '}
					<span style={{ color: '#d63638' }}>*</span>{' '}
					{__('are required.', 'acrossai-abilities-manager')}
				</p>
			)}

			{isOverride && savedAbility && (
				<p className="abilities-subtitle">
					{__('Identity defined by', 'acrossai-abilities-manager')}{' '}
					<strong>
						{savedAbility.provider ||
							__('an external source', 'acrossai-abilities-manager')}
					</strong>
					{' — '}
					{__(
						'read-only. You can override site permission, MCP exposure, annotations, and access control.',
						'acrossai-abilities-manager'
					)}
				</p>
			)}

			{/* Save error notice */}
			{saveError && (
				<div className="notice notice-error">
					<p>{saveError}</p>
				</div>
			)}

			{/* Form layout: main + sticky sidebar */}
			<div className="form-layout">
				{/* ===== MAIN COLUMN ===== */}
				<div className="form-main">
					{/* ——— VARIANT B: Locked identity banner ——— */}
					{isOverride && savedAbility && (
						<LockedCard ability={savedAbility} />
					)}

					{/* ——— Unified panel ——— */}
					<div className="panel">

						{/* ── VARIANT A: Section 1 — Identity ── */}
						{!isOverride && (
							<div className="sect">
								<div className="sect-hdr">
									<div className="sect-title">
										<span className="sect-num">1</span>
										{__('Identity', 'acrossai-abilities-manager')}
									</div>
									<div className="sect-desc">
										{__(
											'What this ability is called and how it\'s looked up across REST routes and MCP manifests.',
											'acrossai-abilities-manager'
										)}
									</div>
								</div>

								{/* Slug */}
								<div className="fr">
									<label htmlFor="ability-slug-suffix" className="fl">
										{__('Slug', 'acrossai-abilities-manager')}
										<span className="req"> *</span>
									</label>
									<div className="ff">
										<div className="pxwrap">
											<div className="pxtxt">
												{SLUG_PREFIX}
											</div>
											<input
												id="ability-slug-suffix"
												type="text"
												className="pxinp"
												value={slugSuffix}
												placeholder={__(
													'my-ability-name',
													'acrossai-abilities-manager'
												)}
												onChange={handleSlugChange}
												onBlur={handleSlugBlur}
												readOnly={isEdit}
											/>
										</div>
										{slugError && (
											<div className="field-error">
												{slugError}
											</div>
										)}
										{('create' === mode || 'edit' === mode) && formErrors.slug_suffix && (
											<div className="field-error" role="alert" aria-live="polite">
												{formErrors.slug_suffix}
											</div>
										)}
										{isEdit ? (
											<div className="desc-warn">
												⚠{' '}
												{__(
													'Changing the slug will break existing integrations.',
													'acrossai-abilities-manager'
												)}
											</div>
										) : (
											<div className="desc">
												{__(
													'Lowercase letters, numbers, and dashes only.',
													'acrossai-abilities-manager'
												)}
											</div>
										)}
									</div>
								</div>

								{/* Label */}
								<div className="fr">
									<label htmlFor="ability-label" className="fl">
										{__('Label', 'acrossai-abilities-manager')}
										<span className="req"> *</span>
									</label>
									<div className="ff">
										<input
											id="ability-label"
											type="text"
											className="ri"
											placeholder={__(
												'e.g. Summarize Post',
												'acrossai-abilities-manager'
											)}
											value={draftAbility.label || ''}
											onChange={(e) => {
												patch({ label: e.target.value });
												if (e.target.value.trim()) setFormErrors((prev) => ({ ...prev, label: '' }));
											}}
											onBlur={handleLabelBlur}
										/>
										{('create' === mode || 'edit' === mode) && formErrors.label && (
											<div className="field-error" role="alert" aria-live="polite">
												{formErrors.label}
											</div>
										)}
									</div>
								</div>

								{/* Category */}
								<div className="fr">
									<label htmlFor="ability-category" className="fl">
										{__('Category', 'acrossai-abilities-manager')}
										<span className="req"> *</span>
									</label>
									<div className="ff">
										<select
											id="ability-category"
											className="rs"
											style={{ maxWidth: '280px' }}
											value={draftAbility.category || ''}
											onChange={(e) => {
												patch({ category: e.target.value });
												if (e.target.value.trim()) setFormErrors((prev) => ({ ...prev, category: '' }));
											}}
											onBlur={handleCategoryBlur}
										>
											<option value="">
												{__('— choose —', 'acrossai-abilities-manager')}
											</option>
											{categories.map((cat) => (
												<option key={cat.slug} value={cat.slug}>
													{cat.label || cat.slug}
												</option>
											))}
										</select>
										{('create' === mode || 'edit' === mode) && formErrors.category && (
											<div className="field-error" role="alert" aria-live="polite">
												{formErrors.category}
											</div>
										)}
									</div>
								</div>

								{/* Description */}
								<div className="fr">
									<label htmlFor="ability-description" className="fl">
										{__('Description', 'acrossai-abilities-manager')}
										<span className="req"> *</span>
									</label>
									<div className="ff">
										<textarea
											id="ability-description"
											className="rt"
											rows="3"
											maxLength={1000}
											placeholder={__(
												'Describe what this ability does. This will appear to AI agents during discovery.',
												'acrossai-abilities-manager'
											)}
											value={draftAbility.description || ''}
											onChange={(e) => {
												patch({ description: e.target.value });
												if (e.target.value.trim()) setFormErrors((prev) => ({ ...prev, description: '' }));
											}}
											onBlur={handleDescriptionBlur}
										/>
										{('create' === mode || 'edit' === mode) && formErrors.description && (
											<div className="field-error" role="alert" aria-live="polite">
												{formErrors.description}
											</div>
										)}
									</div>
								</div>

								{/* Auto-register (status) */}
								<div className="fr">
									<label htmlFor="auto-register" className="fl">
										{__('Auto-register', 'acrossai-abilities-manager')}
									</label>
									<div className="ff">
										<div className="togrow">
											<button
												type="button"
												id="auto-register"
												role="switch"
												aria-checked={
													'publish' === draftAbility.status
														? 'true'
														: 'false'
												}
												className={`wptog${
													'publish' === draftAbility.status
														? ' on'
														: ''
												}`}
												onClick={() =>
													patch({
														status:
															'publish' === draftAbility.status
																? 'draft'
																: 'publish',
													})
												}
											/>
											<span className="toglbl">
												{'publish' === draftAbility.status ? (
													<>
														<strong>
															{__('Enabled', 'acrossai-abilities-manager')}
														</strong>
														{' — '}
														{__(
															'registered on every page load',
															'acrossai-abilities-manager'
														)}
													</>
												) : (
													<>
														<strong>
															{__('Disabled', 'acrossai-abilities-manager')}
														</strong>
														{' — '}
														{__(
															'saved but not registered',
															'acrossai-abilities-manager'
														)}
													</>
												)}
											</span>
										</div>
										<div className="desc">
											{__(
												'When off, the ability is saved but not registered with WordPress on each request.',
												'acrossai-abilities-manager'
											)}
										</div>
									</div>
								</div>
							</div>
						)}

						{/* ── VARIANT A: Section 2 — Callback ── */}
						{!isOverride && (
							<div className="sect">
								<div className="sect-hdr">
									<div className="sect-title">
										<span className="sect-num">2</span>
										{__('Callback', 'acrossai-abilities-manager')}
									</div>
									<div className="sect-desc">
										{__(
											'How this ability resolves at runtime.',
											'acrossai-abilities-manager'
										)}
									</div>
								</div>
								<div className="fr">
									<label className="fl">
										{__('Type', 'acrossai-abilities-manager')}
										<span className="req"> *</span>
									</label>
									<div className="ff">
										<CallbackTypeChips
											value={callbackType}
											onChange={(type) =>
												patch({
													callback_type: type,
													callback_config: {},
												})
											}
										/>
										<CallbackConfigField
											callbackType={callbackType}
											config={callbackConfig}
											onChange={(cfg) =>
												patch({ callback_config: cfg })
											}
										/>
									</div>
								</div>
							</div>
						)}

						{/* ── VARIANT A: Section 3 — Schema (optional) ── */}
						{!isOverride && (
							<div className="sect">
								<div className="sect-hdr">
									<div className="sect-title">
										<span className="sect-num">3</span>
										{__('Schema', 'acrossai-abilities-manager')}
										<span className="sect-opt">
											{__('optional', 'acrossai-abilities-manager')}
										</span>
									</div>
									<div className="sect-desc">
										{__(
											'JSON Schema definitions for input and output. Used for validation and surfaced to MCP clients.',
											'acrossai-abilities-manager'
										)}
									</div>
								</div>

								<div className="fr">
									<label htmlFor="input-schema" className="fl">
										{__('Input Schema', 'acrossai-abilities-manager')}
									</label>
									<div className="ff">
										<textarea
											id="input-schema"
											className="rt code"
											value={inputSchemaRaw}
											placeholder='{ "param": { "type": "string" } }'
											onChange={(e) =>
												setInputSchemaRaw(e.target.value)
											}
											onBlur={handleInputSchemaBlur}
										/>
										{inputSchemaError && (
											<div className="field-error">
												{inputSchemaError}
											</div>
										)}
									</div>
								</div>

								<div className="fr">
									<label htmlFor="output-schema" className="fl">
										{__('Output Schema', 'acrossai-abilities-manager')}
									</label>
									<div className="ff">
										<textarea
											id="output-schema"
											className="rt code"
											value={outputSchemaRaw}
											placeholder='{ "result": { "type": "string" } }'
											onChange={(e) =>
												setOutputSchemaRaw(e.target.value)
											}
											onBlur={handleOutputSchemaBlur}
										/>
										{outputSchemaError && (
											<div className="field-error">
												{outputSchemaError}
											</div>
										)}
									</div>
								</div>
							</div>
						)}

						{/* ── Section 4 (A) / Section 2 (B) — MCP Exposure ── */}
						<div className="sect">
							<div className="sect-hdr">
								<div className="sect-title">
									<span className="sect-num">
										{isOverride ? '2' : '4'}
									</span>
									{isOverride
										? __('MCP Exposure', 'acrossai-abilities-manager')
										: __('MCP Exposure', 'acrossai-abilities-manager')}
								</div>
								<div className="sect-desc">
									{isOverride
										? __(
											'Override how this ability appears to MCP clients. Leave as "inherit" to use the plugin\'s declared values.',
											'acrossai-abilities-manager'
										  )
										: __(
											'How this ability appears to MCP clients.',
											'acrossai-abilities-manager'
										  )}
								</div>
							</div>

							{/* Show in MCP */}
							<div className="fr">
								<label htmlFor="show-in-mcp" className="fl">
									{__('Show in MCP', 'acrossai-abilities-manager')}
								</label>
								<div className="ff">
									<div className="togrow">
										<button
											type="button"
											id="show-in-mcp"
											role="switch"
											aria-checked={
												draftAbility.show_in_mcp ? 'true' : 'false'
											}
											className={`wptog${
												draftAbility.show_in_mcp ? ' on' : ''
											}`}
											onClick={() =>
												patch({
													show_in_mcp: draftAbility.show_in_mcp
														? null
														: true,
												})
											}
										/>
										<span className="toglbl">
											{draftAbility.show_in_mcp ? (
												<>
													<strong>
														{__('Enabled', 'acrossai-abilities-manager')}
													</strong>
													{isOverride && savedAbility?.show_in_mcp !== undefined && (
														<span className="soft-hint">
															{' '}
															{__('(plugin default: yes)', 'acrossai-abilities-manager')}
														</span>
													)}
												</>
											) : (
												<strong>
													{__('Disabled', 'acrossai-abilities-manager')}
												</strong>
											)}
										</span>
									</div>
								</div>
							</div>

							{/* MCP Type */}
							<div className="fr">
								<label htmlFor="mcp-type" className="fl">
									{__('MCP Type', 'acrossai-abilities-manager')}
								</label>
								<div className="ff">
									<select
										id="mcp-type"
										className="rs"
										style={{ maxWidth: '220px' }}
										value={draftAbility.mcp_type || ''}
										onChange={(e) =>
											patch({
												mcp_type: e.target.value || null,
											})
										}
									>
										{isOverride ? (
											<option value="">
												{__('inherit', 'acrossai-abilities-manager')}
												{savedAbility?.mcp_type
													? ` (${savedAbility.mcp_type})`
													: ''}
											</option>
										) : (
											<option value="">
												{__('— Select —', 'acrossai-abilities-manager')}
											</option>
										)}
										<option value="tool">tool</option>
										<option value="resource">resource</option>
										<option value="prompt">prompt</option>
									</select>
									{isOverride && savedAbility?.mcp_type && (
										<div className="desc">
											{__('Plugin declares:', 'acrossai-abilities-manager')}{' '}
											<strong>{savedAbility.mcp_type}</strong>
										</div>
									)}
								</div>
							</div>

							{/* Allowed Servers */}
							<div className="fr">
								<label htmlFor="mcp-servers" className="fl">
									{__('Allowed Servers', 'acrossai-abilities-manager')}
								</label>
								<div className="ff">
									<input
										id="mcp-servers"
										type="text"
										className="ri"
										style={{ maxWidth: '220px' }}
										value={
											Array.isArray(draftAbility.mcp_servers)
												? draftAbility.mcp_servers.join(', ')
												: draftAbility.mcp_servers || '*'
										}
										onChange={(e) => {
											const raw = e.target.value.trim();
											if (!raw || '*' === raw) {
												patch({ mcp_servers: null });
											} else {
												patch({
													mcp_servers: raw
														.split(',')
														.map((s) => s.trim())
														.filter(Boolean),
												});
											}
										}}
									/>
									<div className="desc">
										<code>*</code>
										{' '}
										{__(
											'= all servers. Comma-separate server IDs to restrict.',
											'acrossai-abilities-manager'
										)}
									</div>
								</div>
							</div>
						</div>

						{/* ── VARIANT B: Section 1 — Site Permission ── */}
						{isOverride && (
							<div className="sect">
								<div className="sect-hdr">
									<div className="sect-title">
										<span className="sect-num">1</span>
										{__('Site Permission', 'acrossai-abilities-manager')}
									</div>
									<div className="sect-desc">
										{__(
											'Override whether this ability is allowed or blocked site-wide.',
											'acrossai-abilities-manager'
										)}
									</div>
								</div>
								<div className="fr">
									<label className="fl">
										{__('Site Access', 'acrossai-abilities-manager')}
									</label>
									<div className="ff">
										<SitePermissionTGC
											value={draftAbility.site_allowed}
											onChange={(v) => patch({ site_allowed: v })}
										/>
										<div className="desc">
											<strong>
												{__('Inherit', 'acrossai-abilities-manager')}
											</strong>{' '}
											{__(
												'respects the plugin\'s own setting. Force Allow/Block override regardless.',
												'acrossai-abilities-manager'
											)}
										</div>
									</div>
								</div>
							</div>
						)}

						{/* ── Section 5 (A) / Section 3 (B) — Annotations ── */}
						<div className="sect">
							<div className="sect-hdr">
								<div className="sect-title">
									<span className="sect-num">
										{isOverride ? '3' : '5'}
									</span>
									{isOverride
										? __('Annotation Overrides', 'acrossai-abilities-manager')
										: __('Annotations', 'acrossai-abilities-manager')}
								</div>
								<div className="sect-desc">
									{isOverride
										? __(
											'inherit defers to the plugin\'s declared value; yes/no force the annotation regardless.',
											'acrossai-abilities-manager'
										  )
										: __(
											'Tri-state metadata hints for MCP clients. inherit defers to the callback type\'s default.',
											'acrossai-abilities-manager'
										  )}
								</div>
							</div>

							<TriStateSelect
								id="ann-readonly"
								value={draftAbility.readonly ?? null}
								onChange={(v) => patch({ readonly: v })}
								label={__('Readonly', 'acrossai-abilities-manager')}
								hint={
									isOverride && null !== savedAbility?.readonly
										? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${ts2s(savedAbility.readonly)}`
										: __('Does this ability mutate state?', 'acrossai-abilities-manager')
								}
							/>
							<TriStateSelect
								id="ann-destructive"
								value={draftAbility.destructive ?? null}
								onChange={(v) => patch({ destructive: v })}
								label={__('Destructive', 'acrossai-abilities-manager')}
								hint={
									isOverride && null !== savedAbility?.destructive
										? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${ts2s(savedAbility.destructive)}`
										: __('Can data be permanently lost?', 'acrossai-abilities-manager')
								}
							/>
							<TriStateSelect
								id="ann-idempotent"
								value={draftAbility.idempotent ?? null}
								onChange={(v) => patch({ idempotent: v })}
								label={__('Idempotent', 'acrossai-abilities-manager')}
								hint={
									isOverride && null !== savedAbility?.idempotent
										? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${ts2s(savedAbility.idempotent)}`
										: __('Safe to call multiple times with the same input?', 'acrossai-abilities-manager')
								}
							/>
							{isOverride && (
								<TriStateSelect
									id="ann-show-in-rest"
									value={draftAbility.show_in_rest ?? null}
									onChange={(v) => patch({ show_in_rest: v })}
									label={__('Show in REST', 'acrossai-abilities-manager')}
									hint={
										null !== savedAbility?.show_in_rest
											? `${__('Plugin declares:', 'acrossai-abilities-manager')} ${ts2s(savedAbility?.show_in_rest)}`
											: null
									}
								/>
							)}
						</div>

					</div>
					{/* end .panel */}
				</div>
				{/* end .form-main */}

				{/* ===== SIDEBAR COLUMN ===== */}
				<div className="form-side">
					{/* Add New: Publish box */}
					{isCreate && (
						<div className="sbox">
							<div className="sbhdr">
								<span className="sbhdr-ic">↑</span>
								{__('Publish', 'acrossai-abilities-manager')}
							</div>
							<div className="sbbody">
								<div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
									<button
										type="button"
										className="button button-primary button-large"
										style={hasRequiredErrors ? { width: '100%', justifyContent: 'center', opacity: 0.5, pointerEvents: 'none' } : { width: '100%', justifyContent: 'center' }}
										disabled={isSaving}
										aria-disabled={hasRequiredErrors}
										onClick={() => handleSave(false)}
									>
										{isSaving
											? __('Saving…', 'acrossai-abilities-manager')
											: __('✓ Add Ability', 'acrossai-abilities-manager')}
									</button>
									<button
										type="button"
										className="button"
										style={{ width: '100%', justifyContent: 'center', fontSize: '12px' }}
										disabled={isSaving}
										onClick={() => handleSave(true)}
									>
										{__('Save as Draft', 'acrossai-abilities-manager')}
									</button>
								</div>
							</div>
						</div>
					)}

					{/* Edit: Update box */}
					{isEdit && (
						<div className="sbox">
							<div className="sbhdr">
								<span className="sbhdr-ic">↑</span>
								{__('Update', 'acrossai-abilities-manager')}
							</div>
							<div className="sbbody">
								<div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
									<button
										type="button"
										className="button button-primary button-large"
										style={hasRequiredErrors ? { width: '100%', justifyContent: 'center', opacity: 0.5, pointerEvents: 'none' } : { width: '100%', justifyContent: 'center' }}
										disabled={isSaving || !isDirty}
										aria-disabled={hasRequiredErrors}
										onClick={() => handleSave(false)}
									>
										{isSaving
											? __('Saving…', 'acrossai-abilities-manager')
											: __('✓ Save Changes', 'acrossai-abilities-manager')}
									</button>
									<button
										type="button"
										className="button"
										style={{ width: '100%', justifyContent: 'center', fontSize: '12px' }}
										disabled={isSaving}
										onClick={() => handleSave(true)}
									>
										{__('Save as Draft', 'acrossai-abilities-manager')}
									</button>
								</div>
								<div style={{ borderTop: '1px dashed #ddd', marginTop: '14px', paddingTop: '12px', textAlign: 'center' }}>
									<button
										type="button"
										className="button-link link-delete"
										style={{ fontSize: '12px' }}
										onClick={handleDelete}
									>
										{__('🗑 Delete Ability', 'acrossai-abilities-manager')}
									</button>
								</div>
							</div>
						</div>
					)}

					{/* Override: Actions box */}
					{isOverride && (
						<div className="sbox">
							<div className="sbhdr">
								<span className="sbhdr-ic">↑</span>
								{__('Actions', 'acrossai-abilities-manager')}
							</div>
							<div className="sbbody">
								<div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
									<button
										type="button"
										className="button button-primary button-large"
										style={{ width: '100%', justifyContent: 'center' }}
										disabled={isSaving || !isDirty}
										onClick={() => handleSave(false)}
									>
										{isSaving
											? __('Saving…', 'acrossai-abilities-manager')
											: __('✓ Save Overrides', 'acrossai-abilities-manager')}
									</button>
								</div>
								<div style={{ borderTop: '1px dashed #ddd', marginTop: '14px', paddingTop: '12px', textAlign: 'center' }}>
									<button
										type="button"
										className="button-link"
										style={{ fontSize: '12px' }}
										onClick={handleClearOverrides}
									>
										{__('↩ Clear All Overrides', 'acrossai-abilities-manager')}
									</button>
								</div>
							</div>
						</div>
					)}

					{/* Preview box */}
					<div className="sbox">
						<div className="sbhdr">
							<span className="sbhdr-ic">●</span>
							{__('Preview', 'acrossai-abilities-manager')}
						</div>
						<div className="sbbody">
							<div className="prev-grid">
								<div className="prev-row">
									<div className="prk">
										{__('Slug', 'acrossai-abilities-manager')}
									</div>
									<div className="prv mono">
										{draftAbility.ability_slug ? (
											<>{draftAbility.ability_slug}</>
										) : (
											<em style={{ color: '#646970' }}>
												{__(
													'(not set)',
													'acrossai-abilities-manager'
												)}
											</em>
										)}
									</div>
								</div>
								{(isEdit || isOverride) && savedAbility?.label && (
									<div className="prev-row">
										<div className="prk">
											{__('Label', 'acrossai-abilities-manager')}
										</div>
										<div className="prv">{savedAbility.label}</div>
									</div>
								)}
								<div className="prev-row">
									<div className="prk">
										{__('Source', 'acrossai-abilities-manager')}
									</div>
									<div className="prv">
										<SourceBadge
											source={
												draftAbility.source ||
												savedAbility?.source ||
												'db'
											}
										/>
									</div>
								</div>
								{!isOverride && (
									<div className="prev-row">
										<div className="prk">
											{__('Callback', 'acrossai-abilities-manager')}
										</div>
										<div className="prv">
											{callbackType}
										</div>
									</div>
								)}
							</div>
						</div>
					</div>

					{/* Activity box (edit mode only) */}
					{isEdit && savedAbility && (
						<div className="sbox">
							<div className="sbhdr">
								<span className="sbhdr-ic">⊙</span>
								{__('Activity', 'acrossai-abilities-manager')}
							</div>
							<div className="sbbody">
								{savedAbility.updated_at && (
									<div className="aitem">
										<div className="adot on" />
										<div>
											<div className="at">
												{__('Updated', 'acrossai-abilities-manager')}
											</div>
											<div className="adt">
												{formatDate(savedAbility.updated_at)}
											</div>
										</div>
									</div>
								)}
								{savedAbility.created_at && (
									<div className="aitem">
										<div className="adot off" />
										<div>
											<div className="at muted">
												{__('Created', 'acrossai-abilities-manager')}
											</div>
											<div className="adt">
												{formatDate(savedAbility.created_at)}
											</div>
										</div>
									</div>
								)}
							</div>
						</div>
					)}

					{/* Active overrides box (override mode only) */}
					{isOverride && (
						<div className="sbox">
							<div className="sbhdr">
								<span className="sbhdr-ic">⊙</span>
								{__(
									'Active Overrides',
									'acrossai-abilities-manager'
								)}
							</div>
							<div className="sbbody">
								{(() => {
									const overrideFields = [
										'site_allowed',
										'show_in_rest',
										'show_in_mcp',
										'mcp_type',
										'mcp_servers',
										'readonly',
										'destructive',
										'idempotent',
									];
									const active = overrideFields.filter(
										(f) =>
											null !== savedAbility?.[f] &&
											undefined !== savedAbility?.[f]
									);
									if (0 === active.length) {
										return (
											<p
												style={{
													color: '#646970',
													fontStyle: 'italic',
													fontSize: '12px',
												}}
											>
												{__(
													'No overrides set — all values inherited from plugin.',
													'acrossai-abilities-manager'
												)}
											</p>
										);
									}
									return (
										<ul className="hlist">
											{active.map((f) => (
												<li key={f}>
													{f}:{' '}
													<strong>
														{String(savedAbility[f])}
													</strong>
												</li>
											))}
										</ul>
									);
								})()}
							</div>
						</div>
					)}

					{/* On Save box */}
					<div className="sbox">
						<div className="sbhdr">
							<span className="sbhdr-ic">✓</span>
							{__('On Save', 'acrossai-abilities-manager')}
						</div>
						<div className="sbbody">
							<ul className="hlist">
								{isCreate && (
									<>
										<li>
											{__('Written to', 'acrossai-abilities-manager')}{' '}
											<code>wp_acrossai_abilities</code>
										</li>
										<li>
											{__(
												'Registered every page load while Auto-register is on',
												'acrossai-abilities-manager'
											)}
										</li>
										<li>
											{__(
												'Appears in MCP manifest if enabled',
												'acrossai-abilities-manager'
											)}
										</li>
									</>
								)}
								{isEdit && (
									<>
										<li>
											{__('Updated in', 'acrossai-abilities-manager')}{' '}
											<code>wp_acrossai_abilities</code>
										</li>
										<li>
											{__(
												'MCP manifest refreshes on next request',
												'acrossai-abilities-manager'
											)}
										</li>
										<li>
											{__(
												'Existing integrations continue (slug unchanged)',
												'acrossai-abilities-manager'
											)}
										</li>
									</>
								)}
								{isOverride && (
									<>
										<li>
											{__('Override saved to', 'acrossai-abilities-manager')}{' '}
											<code>wp_acrossai_abilities</code>
										</li>
										<li>
											{__(
												"Plugin's own definition is not modified",
												'acrossai-abilities-manager'
											)}
										</li>
										<li>
											{__(
												'Overrides persist across plugin updates',
												'acrossai-abilities-manager'
											)}
										</li>
									</>
								)}
							</ul>
						</div>
					</div>
				</div>
				{/* end .form-side */}
			</div>
			{/* end .form-layout */}

			{/* Sticky save bar */}
			<div className="sbar">
				<div className="snote">
					{isDirty && !isCreate && (
						<>
							<span className="udot" />
							{isOverride
								? __(
									'Changes affect this site only — the plugin definition is not modified.',
									'acrossai-abilities-manager'
								  )
								: __(
									'Unsaved changes — leaving this page will discard them.',
									'acrossai-abilities-manager'
								  )}
						</>
					)}
					{isCreate && (
						<>
							💾{' '}
							{__(
								'Fill in required fields to enable Save.',
								'acrossai-abilities-manager'
							)}
						</>
					)}
				</div>
				<div className="brow">
					<button
						type="button"
						className="button"
						onClick={handleCancel}
					>
						{__('Cancel', 'acrossai-abilities-manager')}
					</button>
					<button
						type="button"
						className="button button-primary"
						disabled={isSaving || (!isCreate && !isDirty)}
						onClick={() => handleSave(false)}
					>
						{saveBtnLabel}
					</button>
				</div>
			</div>
		</div>
	);
}
