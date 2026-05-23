/**
 * AbilityForm — full-page form for create / edit / override.
 *
 * Variant A (source=db): create + edit modes — editable identity, callback, schema, MCP, annotations.
 * Variant B (source≠db): override mode — locked identity card + editable override fields only.
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
import { DataForm } from '@wordpress/dataviews';
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

const TRI_STATE_OPTIONS = [
	{
		value: 'null',
		label: __('Inherit (default)', 'acrossai-abilities-manager'),
	},
	{ value: 'true', label: __('Yes', 'acrossai-abilities-manager') },
	{ value: 'false', label: __('No', 'acrossai-abilities-manager') },
];

// DataForm Edit: component for tri-state selects
function TriStateEditField({ data, field, onChange }) {
	return (
		<select
			value={ts2s(data[field.id])}
			onChange={(e) =>
				onChange({ ...data, [field.id]: s2ts(e.target.value) })
			}
			style={{ maxWidth: '160px' }}
		>
			{TRI_STATE_OPTIONS.map((opt) => (
				<option key={opt.value} value={opt.value}>
					{opt.label}
				</option>
			))}
		</select>
	);
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

// ---------------------------------------------------------------------------
// Locked card for Variant B (inherited identity)
// ---------------------------------------------------------------------------
function LockedCard({ ability }) {
	const providerName =
		ability.provider ||
		__('an external source', 'acrossai-abilities-manager');
	return (
		<div className="locked">
			<div className="lhdr">
				<span>
					🔒 {__('Registered by:', 'acrossai-abilities-manager')}{' '}
					<strong>{providerName}</strong>
				</span>
				<SourceBadge source={ability.source} />
			</div>
			<div className="lgrid">
				<div>
					<div className="lgrid-label">
						{__('Full Slug', 'acrossai-abilities-manager')}
					</div>
					<div className="lgrid-value">
						<code>{ability.ability_slug}</code>
					</div>
				</div>
				<div>
					<div className="lgrid-label">
						{__('Label', 'acrossai-abilities-manager')}
					</div>
					<div className="lgrid-value">{ability.label || '—'}</div>
				</div>
				<div>
					<div className="lgrid-label">
						{__('Category', 'acrossai-abilities-manager')}
					</div>
					<div className="lgrid-value">{ability.category || '—'}</div>
				</div>
				<div>
					<div className="lgrid-label">
						{__('Callback Type', 'acrossai-abilities-manager')}
					</div>
					<div className="lgrid-value">
						{ability.callback_type || 'noop'}
					</div>
				</div>
			</div>
			{ability.description && (
				<div style={{ padding: '0 16px 12px' }}>
					<p className="description">{ability.description}</p>
				</div>
			)}
		</div>
	);
}

// ---------------------------------------------------------------------------
// Site permission chips for Variant B
// ---------------------------------------------------------------------------
const PERMISSION_CHIPS = [
	{
		value: 0,
		label: __('Force Block', 'acrossai-abilities-manager'),
		cls: 'tc tc-block',
	},
	{
		value: null,
		label: __('Inherit (plugin default)', 'acrossai-abilities-manager'),
		cls: 'tc',
	},
	{
		value: 1,
		label: __('Force Allow', 'acrossai-abilities-manager'),
		cls: 'tc tc-allow',
	},
];

function SitePermissionChips({ value, onChange }) {
	// Normalize value for comparison (null=inherit, 0=block, 1=allow)
	let normalized = value;
	if (value === true) {
		normalized = 1;
	} else if (value === false) {
		normalized = 0;
	}
	return (
		<div className="tchips">
			{PERMISSION_CHIPS.map((chip) => {
				let chipNorm = chip.value;
				if (chip.value === true) {
					chipNorm = 1;
				} else if (chip.value === false) {
					chipNorm = 0;
				}
				const isOn = chipNorm === normalized;
				return (
					<button
						key={String(chip.value)}
						type="button"
						className={`${chip.cls}${isOn ? ' on' : ''}`}
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
		<div className="tchips">
			{CALLBACK_CHIPS.map((chip) => (
				<button
					key={chip.value}
					type="button"
					className={`tc${chip.value === value ? ' on' : ''}`}
					onClick={() => onChange(chip.value)}
				>
					{chip.label}
				</button>
			))}
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
	// Save handlers
	// ---------------------------------------------------------------------------
	async function handleSave(forceDraft = false) {
		const data = { ...draftAbility };
		if (forceDraft) {
			data.status = 'draft';
		}

		if ('create' === mode) {
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
	// Breadcrumb
	// ---------------------------------------------------------------------------
	const breadcrumbSlug =
		savedAbility?.ability_slug ||
		__('Add New', 'acrossai-abilities-manager');
	const isCreate = 'create' === mode;
	const isEdit = 'edit' === mode;
	const isOverride = 'override' === mode;

	// ---------------------------------------------------------------------------
	// DataForm fields for annotation tri-state (Constitution §III compliance)
	// ---------------------------------------------------------------------------
	const annotationFields = useMemo(
		() => [
			{
				id: 'readonly',
				label: __('Read Only', 'acrossai-abilities-manager'),
				Edit: TriStateEditField,
			},
			{
				id: 'destructive',
				label: __('Destructive', 'acrossai-abilities-manager'),
				Edit: TriStateEditField,
			},
			{
				id: 'idempotent',
				label: __('Idempotent', 'acrossai-abilities-manager'),
				Edit: TriStateEditField,
			},
		],
		[]
	);

	const ANNOTATION_FORM = {
		type: 'regular',
		fields: ['readonly', 'destructive', 'idempotent'],
	};

	// ---------------------------------------------------------------------------
	// Override-mode DataForm fields (Constitution §III compliance)
	// ---------------------------------------------------------------------------
	const overrideAnnotationFields = useMemo(() => {
		const base = annotationFields.map((f) => {
			const declared = savedAbility ? savedAbility[f.id] : null;
			return {
				...f,
				description:
					null !== declared
						? `${__('Declared:', 'acrossai-abilities-manager')} ${ts2s(declared)}`
						: undefined,
			};
		});
		return [
			...base,
			{
				id: 'show_in_rest',
				label: __('Show in REST', 'acrossai-abilities-manager'),
				Edit: TriStateEditField,
			},
		];
	}, [savedAbility, annotationFields]);

	const OVERRIDE_ANNOTATION_FORM = {
		type: 'regular',
		fields: ['readonly', 'destructive', 'idempotent', 'show_in_rest'],
	};

	// ---------------------------------------------------------------------------
	// Derived draft data subset for DataForm (only annotation fields)
	const annotationDraft = useMemo(
		() => ({
			readonly: draftAbility.readonly ?? null,
			destructive: draftAbility.destructive ?? null,
			idempotent: draftAbility.idempotent ?? null,
			show_in_rest: draftAbility.show_in_rest ?? null,
		}),
		[draftAbility]
	);

	function handleAnnotationChange(newData) {
		patch(newData);
	}

	// ---------------------------------------------------------------------------
	// Render
	// ---------------------------------------------------------------------------
	// Compute sticky-bar save button label (avoids no-nested-ternary rule).
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

			{/* Page title */}
			<div className="abilities-page-title">
				<h1>
					{isCreate &&
						__('Add New Ability', 'acrossai-abilities-manager')}
					{isEdit && __('Edit Ability', 'acrossai-abilities-manager')}
					{isOverride &&
						__('Override Ability', 'acrossai-abilities-manager')}
				</h1>
				{savedAbility && (
					<SourceBadge source={savedAbility.source || 'db'} />
				)}
				{isDirty && (
					<span className="unsaved">
						<span className="udot" />
						{__('Unsaved changes', 'acrossai-abilities-manager')}
					</span>
				)}
			</div>

			{isOverride && savedAbility && (
				<p className="abilities-subtitle">
					{__('Identity defined by', 'acrossai-abilities-manager')}{' '}
					<strong>
						{savedAbility.provider ||
							__(
								'an external source',
								'acrossai-abilities-manager'
							)}
					</strong>
					{' — '}
					{__(
						'read-only. You can only override site permission, MCP exposure, and annotations.',
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
					{/* ——— VARIANT B: Locked identity card ——— */}
					{isOverride && savedAbility && (
						<LockedCard ability={savedAbility} />
					)}

					{/* ——— VARIANT A: Identity section ——— */}
					{!isOverride && (
						<div className="postbox">
							<div className="pbhdr">
								<h2>
									{__(
										'Identity',
										'acrossai-abilities-manager'
									)}
								</h2>
								{isCreate && (
									<p className="description">
										{__(
											'Fill in the fields below to create a new custom ability.',
											'acrossai-abilities-manager'
										)}
									</p>
								)}
							</div>
							<div className="pbody">
								{/* Slug */}
								<div className="fr">
									<label htmlFor="ability-slug-suffix">
										{__(
											'Slug',
											'acrossai-abilities-manager'
										)}{' '}
										<span className="required">*</span>
									</label>
									<div>
										<div className="px-wrap">
											<span className="px-txt">
												{SLUG_PREFIX}
											</span>
											<input
												id="ability-slug-suffix"
												type="text"
												className="px-inp"
												value={slugSuffix}
												placeholder={__(
													'my-ability',
													'acrossai-abilities-manager'
												)}
												onChange={handleSlugChange}
												readOnly={isEdit}
											/>
										</div>
										{slugError && (
											<div className="field-error">
												{slugError}
											</div>
										)}
										{isEdit && (
											<p className="description">
												{__(
													'⚠ Changing the slug will break existing integrations.',
													'acrossai-abilities-manager'
												)}
											</p>
										)}
									</div>
								</div>

								{/* Label */}
								<div className="fr">
									<label htmlFor="ability-label">
										{__(
											'Label',
											'acrossai-abilities-manager'
										)}{' '}
										<span className="required">*</span>
									</label>
									<input
										id="ability-label"
										type="text"
										className="regular-text"
										value={draftAbility.label || ''}
										onChange={(e) =>
											patch({ label: e.target.value })
										}
									/>
								</div>

								{/* Category */}
								<div className="fr">
									<label htmlFor="ability-category">
										{__(
											'Category',
											'acrossai-abilities-manager'
										)}{' '}
										<span className="required">*</span>
									</label>
									<select
										id="ability-category"
										style={{ maxWidth: '260px' }}
										value={draftAbility.category || ''}
										onChange={(e) =>
											patch({ category: e.target.value })
										}
									>
										<option value="">
											{__(
												'— Select category —',
												'acrossai-abilities-manager'
											)}
										</option>
										{categories.map((cat) => (
											<option
												key={cat.slug}
												value={cat.slug}
											>
												{cat.label || cat.slug}
											</option>
										))}
									</select>
								</div>

								{/* Description */}
								<div className="fr">
									<label htmlFor="ability-description">
										{__(
											'Description',
											'acrossai-abilities-manager'
										)}
									</label>
									<textarea
										id="ability-description"
										className="large-text"
										rows="3"
										value={draftAbility.description || ''}
										placeholder={__(
											'Shown to end-users and surfaced to AI agents during ability discovery.',
											'acrossai-abilities-manager'
										)}
										onChange={(e) =>
											patch({
												description: e.target.value,
											})
										}
									/>
								</div>

								{/* Auto-register (status) */}
								<div className="fr">
									<label htmlFor="auto-register">
										{__(
											'Auto-register',
											'acrossai-abilities-manager'
										)}
									</label>
									<div className="wptog-wrap">
										<input
											type="checkbox"
											id="auto-register"
											checked={
												'publish' ===
												draftAbility.status
											}
											onChange={(e) =>
												patch({
													status: e.target.checked
														? 'publish'
														: 'draft',
												})
											}
										/>
										<label htmlFor="auto-register">
											{__(
												'Register this ability on every page load',
												'acrossai-abilities-manager'
											)}
										</label>
									</div>
								</div>
							</div>
						</div>
					)}

					{/* ——— VARIANT A: Callback section ——— */}
					{!isOverride && (
						<div className="postbox">
							<div className="pbhdr">
								<h2>
									{__(
										'Callback',
										'acrossai-abilities-manager'
									)}
								</h2>
								<p className="description">
									{__(
										'How this ability executes when called.',
										'acrossai-abilities-manager'
									)}
								</p>
							</div>
							<div className="pbody">
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
					)}

					{/* ——— VARIANT A: Schema section (optional) ——— */}
					{!isOverride && (
						<div className="postbox">
							<div className="pbhdr">
								<h2>
									{__('Schema', 'acrossai-abilities-manager')}
								</h2>
								<p className="description">
									{__(
										'Optional JSON Schema Draft 7 for input and output validation.',
										'acrossai-abilities-manager'
									)}
								</p>
							</div>
							<div className="pbody">
								<div className="fr">
									<label htmlFor="input-schema">
										{__(
											'Input Schema',
											'acrossai-abilities-manager'
										)}
									</label>
									<div>
										<textarea
											id="input-schema"
											className="code-lt"
											value={inputSchemaRaw}
											placeholder={
												'{ "param": { "type": "string" } }'
											}
											onChange={(e) =>
												setInputSchemaRaw(
													e.target.value
												)
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
									<label htmlFor="output-schema">
										{__(
											'Output Schema',
											'acrossai-abilities-manager'
										)}
									</label>
									<div>
										<textarea
											id="output-schema"
											className="code-lt"
											value={outputSchemaRaw}
											placeholder={
												'{ "result": { "type": "string" } }'
											}
											onChange={(e) =>
												setOutputSchemaRaw(
													e.target.value
												)
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
						</div>
					)}

					{/* ——— VARIANT A + B: MCP Exposure section ——— */}
					<div className="postbox">
						<div className="pbhdr">
							<h2>
								{isOverride
									? __(
											'MCP Exposure Override',
											'acrossai-abilities-manager'
										)
									: __(
											'MCP Exposure',
											'acrossai-abilities-manager'
										)}
							</h2>
						</div>
						<div className="pbody">
							<div className="fr">
								<label htmlFor="show-in-mcp">
									{__(
										'Show in MCP',
										'acrossai-abilities-manager'
									)}
								</label>
								<div className="wptog-wrap">
									<input
										type="checkbox"
										id="show-in-mcp"
										checked={!!draftAbility.show_in_mcp}
										onChange={(e) =>
											patch({
												show_in_mcp: e.target.checked
													? true
													: null,
											})
										}
									/>
									<label htmlFor="show-in-mcp">
										{__(
											'Expose this ability to MCP clients',
											'acrossai-abilities-manager'
										)}
									</label>
								</div>
							</div>

							<div className="fr">
								<label htmlFor="mcp-type">
									{__(
										'MCP Type',
										'acrossai-abilities-manager'
									)}
								</label>
								<select
									id="mcp-type"
									style={{ maxWidth: '200px' }}
									value={draftAbility.mcp_type || ''}
									onChange={(e) =>
										patch({
											mcp_type: e.target.value || null,
										})
									}
								>
									{isOverride && (
										<option value="">
											{__(
												'inherit',
												'acrossai-abilities-manager'
											)}
											{savedAbility?.mcp_type
												? ` (${savedAbility.mcp_type})`
												: ''}
										</option>
									)}
									{!isOverride && (
										<option value="">
											{__(
												'— Select —',
												'acrossai-abilities-manager'
											)}
										</option>
									)}
									<option value="tool">tool</option>
									<option value="resource">resource</option>
									<option value="prompt">prompt</option>
								</select>
							</div>

							<div className="fr">
								<label htmlFor="mcp-servers">
									{__(
										'Allowed Servers',
										'acrossai-abilities-manager'
									)}
								</label>
								<div>
									<input
										id="mcp-servers"
										type="text"
										className="regular-text"
										value={
											Array.isArray(
												draftAbility.mcp_servers
											)
												? draftAbility.mcp_servers.join(
														', '
													)
												: draftAbility.mcp_servers ||
													'*'
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
									<p className="description">
										{__(
											'* = all servers. Comma-separate server IDs to restrict.',
											'acrossai-abilities-manager'
										)}
									</p>
								</div>
							</div>
						</div>
					</div>

					{/* ——— VARIANT B: Site Permission Override ——— */}
					{isOverride && (
						<div className="postbox">
							<div className="pbhdr">
								<h2>
									{__(
										'Site Permission Override',
										'acrossai-abilities-manager'
									)}
								</h2>
							</div>
							<div className="pbody">
								<div className="fr">
									<span className="form-label">
										{__(
											'Permission',
											'acrossai-abilities-manager'
										)}
									</span>
									<SitePermissionChips
										value={draftAbility.site_allowed}
										onChange={(v) =>
											patch({ site_allowed: v })
										}
									/>
								</div>
							</div>
						</div>
					)}

					{/* ——— Annotations section (both variants) ——— */}
					<div className="postbox">
						<div className="pbhdr">
							<h2>
								{isOverride
									? __(
											'Annotation Overrides',
											'acrossai-abilities-manager'
										)
									: __(
											'Annotations',
											'acrossai-abilities-manager'
										)}
							</h2>
						</div>
						<div className="pbody">
							{/* DataForm for tri-state annotation fields — Constitution §III */}
							<DataForm
								data={annotationDraft}
								fields={
									isOverride
										? overrideAnnotationFields
										: annotationFields
								}
								form={
									isOverride
										? OVERRIDE_ANNOTATION_FORM
										: ANNOTATION_FORM
								}
								onChange={handleAnnotationChange}
							/>
							{isOverride && savedAbility && (
								<p
									className="description"
									style={{ marginTop: '8px' }}
								>
									{__(
										'Use "Inherit (default)" to restore the plugin\'s declared value.',
										'acrossai-abilities-manager'
									)}
								</p>
							)}
						</div>
					</div>
				</div>
				{/* end .form-main */}

				{/* ===== SIDEBAR COLUMN ===== */}
				<div className="form-side">
					{/* Add New: Publish box */}
					{isCreate && (
						<div className="sidebox">
							<div className="sbhdr">
								{__('Publish', 'acrossai-abilities-manager')}
							</div>
							<div className="sbody">
								<button
									type="button"
									className="button button-primary button-large"
									style={{
										width: '100%',
										marginBottom: '8px',
									}}
									disabled={isSaving}
									onClick={() => handleSave(false)}
								>
									{isSaving
										? __(
												'Saving…',
												'acrossai-abilities-manager'
											)
										: __(
												'✓ Add Ability',
												'acrossai-abilities-manager'
											)}
								</button>
								<button
									type="button"
									className="button"
									style={{ width: '100%' }}
									disabled={isSaving}
									onClick={() => handleSave(true)}
								>
									{__(
										'Save as Draft',
										'acrossai-abilities-manager'
									)}
								</button>
							</div>
						</div>
					)}

					{/* Edit: Update box */}
					{isEdit && (
						<div className="sidebox">
							<div className="sbhdr">
								{__('Update', 'acrossai-abilities-manager')}
							</div>
							<div className="sbody">
								<button
									type="button"
									className="button button-primary button-large"
									style={{
										width: '100%',
										marginBottom: '8px',
									}}
									disabled={isSaving || !isDirty}
									onClick={() => handleSave(false)}
								>
									{isSaving
										? __(
												'Saving…',
												'acrossai-abilities-manager'
											)
										: __(
												'✓ Save Changes',
												'acrossai-abilities-manager'
											)}
								</button>
								<button
									type="button"
									className="button"
									style={{
										width: '100%',
										marginBottom: '8px',
									}}
									disabled={isSaving}
									onClick={() => handleSave(true)}
								>
									{__(
										'Save as Draft',
										'acrossai-abilities-manager'
									)}
								</button>
								<hr className="sbox-divider" />
								<button
									type="button"
									className="link-delete"
									onClick={handleDelete}
								>
									{__(
										'🗑 Delete Ability',
										'acrossai-abilities-manager'
									)}
								</button>
							</div>
						</div>
					)}

					{/* Override: Actions box */}
					{isOverride && (
						<div className="sidebox">
							<div className="sbhdr">
								{__('Actions', 'acrossai-abilities-manager')}
							</div>
							<div className="sbody">
								<button
									type="button"
									className="button button-primary button-large"
									style={{
										width: '100%',
										marginBottom: '8px',
									}}
									disabled={isSaving || !isDirty}
									onClick={() => handleSave(false)}
								>
									{isSaving
										? __(
												'Saving…',
												'acrossai-abilities-manager'
											)
										: __(
												'✓ Save Overrides',
												'acrossai-abilities-manager'
											)}
								</button>
								<button
									type="button"
									className="button-link"
									onClick={handleClearOverrides}
								>
									{__(
										'↩ Clear All Overrides',
										'acrossai-abilities-manager'
									)}
								</button>
							</div>
						</div>
					)}

					{/* Preview box */}
					<div className="sidebox">
						<div className="sbhdr">
							{__('Preview', 'acrossai-abilities-manager')}
						</div>
						<div className="sbody">
							<table style={{ width: '100%', fontSize: '12px' }}>
								<tbody>
									<tr>
										<td style={{ color: '#646970' }}>
											{__(
												'Slug',
												'acrossai-abilities-manager'
											)}
										</td>
										<td>
											{draftAbility.ability_slug ? (
												<code
													style={{ fontSize: '10px' }}
												>
													{draftAbility.ability_slug}
												</code>
											) : (
												<em
													style={{ color: '#646970' }}
												>
													{__(
														'(not set)',
														'acrossai-abilities-manager'
													)}
												</em>
											)}
										</td>
									</tr>
									{!isOverride && (
										<tr>
											<td style={{ color: '#646970' }}>
												{__(
													'Type',
													'acrossai-abilities-manager'
												)}
											</td>
											<td>{callbackType}</td>
										</tr>
									)}
									<tr>
										<td style={{ color: '#646970' }}>
											{__(
												'Source',
												'acrossai-abilities-manager'
											)}
										</td>
										<td>
											<SourceBadge
												source={
													draftAbility.source ||
													savedAbility?.source ||
													'db'
												}
											/>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					{/* Activity box (edit mode only) */}
					{isEdit && savedAbility && (
						<div className="sidebox">
							<div className="sbhdr">
								{__('Activity', 'acrossai-abilities-manager')}
							</div>
							<div className="sbody">
								{savedAbility.updated_at && (
									<div className="activity-item">
										<span className="activity-dot updated" />
										<span>
											{__(
												'Updated',
												'acrossai-abilities-manager'
											)}{' '}
											<time>
												{formatDate(
													savedAbility.updated_at
												)}
											</time>
										</span>
									</div>
								)}
								{savedAbility.created_at && (
									<div className="activity-item">
										<span className="activity-dot created" />
										<span>
											{__(
												'Created',
												'acrossai-abilities-manager'
											)}{' '}
											<time>
												{formatDate(
													savedAbility.created_at
												)}
											</time>
										</span>
									</div>
								)}
							</div>
						</div>
					)}

					{/* Active overrides box (override mode only) */}
					{isOverride && (
						<div className="sidebox">
							<div className="sbhdr">
								{__(
									'Active Overrides',
									'acrossai-abilities-manager'
								)}
							</div>
							<div className="sbody">
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
										<ul
											style={{
												margin: 0,
												padding: '0 0 0 16px',
												fontSize: '12px',
											}}
										>
											{active.map((f) => (
												<li key={f}>
													{f}:{' '}
													<strong>
														{String(
															savedAbility[f]
														)}
													</strong>
												</li>
											))}
										</ul>
									);
								})()}
							</div>
						</div>
					)}
				</div>
				{/* end .form-side */}
			</div>
			{/* end .form-layout */}

			{/* Sticky save bar */}
			<div className="sbar">
				<div className="sbar-note">
					{isDirty && !isCreate && (
						<>
							<span className="udot" />{' '}
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
					{isCreate && !isDirty && (
						<span>
							{__(
								'Fill in required fields (*) before saving.',
								'acrossai-abilities-manager'
							)}
						</span>
					)}
				</div>
				<div className="sbar-actions">
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
