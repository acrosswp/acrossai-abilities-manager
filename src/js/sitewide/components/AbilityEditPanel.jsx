/**
 * Slide-in edit panel for a single ability override.
 *
 * Per-tab save architecture:
 * - Each tab manages independent local state.
 * - Each tab has its own Save + Reset to Default footer buttons.
 * - There is NO panel-level Save button — the header has only a close (✕) button.
 * - The panel stays OPEN after a tab save so the user can edit other tabs.
 * - Escape / backdrop / ✕ close: if any tab has unsaved draft changes a
 *   browser confirm() prompt is shown before discarding.
 *
 * Tri-state encoding:
 * - RadioControl string values 'true'/'false'/'null' ↔ JS true/false/null.
 * - NEVER use Boolean() or !! — collapses null and false into the same falsy bucket.
 *
 * @since 0.1.0
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, TabPanel, RadioControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';
import McpVisibilityControl from './McpVisibilityControl';
import { AccessControl } from '@wpb/access-control';

// ---------------------------------------------------------------------------
// Tri-state helpers — strict, no Boolean() / !!
// ---------------------------------------------------------------------------

/**
 * PHP tri-state value → radio string.
 * @param {*} v  null | true | false
 * @return {'null'|'true'|'false'}
 */
function ts2s(v) {
	if (true === v) return 'true';
	if (false === v) return 'false';
	return 'null';
}

/**
 * Radio string → PHP tri-state value.
 * @param {'null'|'true'|'false'} s
 * @return {null|true|false}
 */
function s2ts(s) {
	if ('true' === s) return true;
	if ('false' === s) return false;
	return null;
}

const TRI_STATE_OPTIONS = [
	{ value: 'null', label: __('Inherit (use ability default)', 'acrossai-abilities-manager') },
	{ value: 'true', label: __('Yes', 'acrossai-abilities-manager') },
	{ value: 'false', label: __('No', 'acrossai-abilities-manager') },
];

const HINT_LABELS = { null: __('Inherit', 'acrossai-abilities-manager'), 'true': __('Yes', 'acrossai-abilities-manager'), 'false': __('No', 'acrossai-abilities-manager') };

function TriStateControl({ label, value, registryValue, onChange }) {
	return (
		<div className="acrossai-tri-state-control">
			<RadioControl
				label={label}
				selected={ts2s(value)}
				options={TRI_STATE_OPTIONS}
				onChange={(s) => onChange(s2ts(s))}
			/>
			{null !== registryValue && undefined !== registryValue && (
				<p className="acrossai-tri-state-control__hint description">
					{__('Ability default:', 'acrossai-abilities-manager')}{' '}
					<strong>{HINT_LABELS[ts2s(registryValue)]}</strong>
				</p>
			)}
		</div>
	);
}

// ---------------------------------------------------------------------------
// Tab initial state builders
// ---------------------------------------------------------------------------

function buildGeneralDraft(src) {
	// Use _override (raw DB row values) NOT the merged effective values.
	// Merged values substitute registry defaults for null fields, which would
	// cause a field with no DB entry to show "Yes"/"No" instead of "Inherit".
	const ov = src?._override ?? {};
	return {
		site_allowed: ov.site_allowed ?? null,
		readonly: ov.readonly ?? null,
		destructive: ov.destructive ?? null,
		idempotent: ov.idempotent ?? null,
		show_in_rest: ov.show_in_rest ?? null,
	};
}

function buildMcpDraft(src) {
	const ov = src?._override ?? {};
	return {
		show_in_mcp: ov.show_in_mcp ?? null,
		mcp_type: ov.mcp_type ?? null,
		mcp_servers: ov.mcp_servers ?? null,
	};
}

function draftsEqual(a, b) {
	return JSON.stringify(a) === JSON.stringify(b);
}

// ---------------------------------------------------------------------------
// TabFooter — Save + Reset buttons with inline notice
// ---------------------------------------------------------------------------

function TabFooter({ draft, savedDraft, onSave, onReset, isSaving, notice, onDismissNotice }) {
	return (
		<div className="acrossai-ability-edit-panel__tab-footer">
			{notice && (
				<Notice
					status={notice.status}
					isDismissible
					onRemove={onDismissNotice}
				>
					{notice.message}
				</Notice>
			)}
			<div className="acrossai-ability-edit-panel__tab-footer-buttons">
				<Button
					variant="primary"
					onClick={onSave}
					disabled={isSaving}
					isBusy={isSaving}
				>
					{isSaving
						? __('Saving\u2026', 'acrossai-abilities-manager')
						: __('Save', 'acrossai-abilities-manager')}
				</Button>
				<Button
					variant="tertiary"
					onClick={onReset}
					disabled={isSaving || draftsEqual(draft, savedDraft)}
				>
					{__('Reset to Default', 'acrossai-abilities-manager')}
				</Button>
			</div>
		</div>
	);
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

/**
 * AbilityEditPanel — slide-in drawer component.
 *
 * @param {Object}   props
 * @param {string}   props.slug      Ability slug.
 * @param {Object}   props.ability   Merged ability data (may be null).
 * @param {Object}   props.registry  Raw registry ability (for default hints).
 * @param {Function} props.onClose   Close handler.
 * @return {JSX.Element|null}
 */
export default function AbilityEditPanel({ slug, ability, registry, onClose }) {
	const dispatch = useDispatch(STORE_NAME);

	// General tab
	const [generalDraft, setGeneralDraft] = useState(() => buildGeneralDraft(ability));
	const [generalSaved, setGeneralSaved] = useState(() => buildGeneralDraft(ability));
	const [generalSaving, setGeneralSaving] = useState(false);
	const [generalNotice, setGeneralNotice] = useState(null);

	// MCP tab
	const [mcpDraft, setMcpDraft] = useState(() => buildMcpDraft(ability));
	const [mcpSaved, setMcpSaved] = useState(() => buildMcpDraft(ability));
	const [mcpSaving, setMcpSaving] = useState(false);
	const [mcpNotice, setMcpNotice] = useState(null);

	// Re-seed both tabs only when the panel opens for a new ability (slug changes).
	// Do NOT depend on `ability` reference — the store dispatches UPDATE_ABILITY after
	// every save, which changes the `ability` reference and would re-seed the draft,
	// silently overwriting the user's confirmed selection back to "Inherit" if the
	// fresh _override value arrives null before the component re-reads it.
	useEffect(() => {
		setGeneralDraft(buildGeneralDraft(ability));
		setGeneralSaved(buildGeneralDraft(ability));
		setMcpDraft(buildMcpDraft(ability));
		setMcpSaved(buildMcpDraft(ability));
	}, [slug]); // eslint-disable-line react-hooks/exhaustive-deps

	// Unsaved check
	const hasUnsaved = !draftsEqual(generalDraft, generalSaved) || !draftsEqual(mcpDraft, mcpSaved);

	const handleClose = useCallback(() => {
		if (hasUnsaved) {
			// eslint-disable-next-line no-alert
			if (!window.confirm(__('You have unsaved changes. Close without saving?', 'acrossai-abilities-manager'))) {
				return;
			}
		}
		onClose();
	}, [hasUnsaved, onClose]);

	// Escape key
	useEffect(() => {
		function onKey(e) {
			if ('Escape' === e.key) handleClose();
		}
		document.addEventListener('keydown', onKey);
		return () => document.removeEventListener('keydown', onKey);
	}, [handleClose]);

	// Save handlers
	const saveGeneral = useCallback(async () => {
		setGeneralSaving(true);
		setGeneralNotice(null);
		try {
			const result = await dispatch.saveOverride(slug, generalDraft);
			const msg = result?.unchanged
				? __('No changes made.', 'acrossai-abilities-manager')
				: __('Settings saved.', 'acrossai-abilities-manager');
			setGeneralNotice({ status: 'success', message: msg });
			setGeneralSaved({ ...generalDraft });
		} catch (err) {
			setGeneralNotice({ status: 'error', message: err.message || String(err) });
		} finally {
			setGeneralSaving(false);
		}
	}, [dispatch, slug, generalDraft]);

	const saveMcp = useCallback(async () => {
		setMcpSaving(true);
		setMcpNotice(null);
		try {
			const result = await dispatch.saveOverride(slug, mcpDraft);
			const msg = result?.unchanged
				? __('No changes made.', 'acrossai-abilities-manager')
				: __('Settings saved.', 'acrossai-abilities-manager');
			setMcpNotice({ status: 'success', message: msg });
			setMcpSaved({ ...mcpDraft });
		} catch (err) {
			setMcpNotice({ status: 'error', message: err.message || String(err) });
		} finally {
			setMcpSaving(false);
		}
	}, [dispatch, slug, mcpDraft]);

	const regVal = (field) => registry?.[field] ?? null;

	// ---------------------------------------------------------------------------
	// Tab content
	// ---------------------------------------------------------------------------

	const generalTab = (
		<div className="acrossai-ability-edit-panel__tab-content">
			<TriStateControl label={__('Site Allowed', 'acrossai-abilities-manager')}
				value={generalDraft.site_allowed} registryValue={regVal('site_allowed')}
				onChange={(v) => setGeneralDraft((p) => ({ ...p, site_allowed: v }))} />
			<TriStateControl label={__('Read Only', 'acrossai-abilities-manager')}
				value={generalDraft.readonly} registryValue={regVal('readonly')}
				onChange={(v) => setGeneralDraft((p) => ({ ...p, readonly: v }))} />
			<TriStateControl label={__('Destructive', 'acrossai-abilities-manager')}
				value={generalDraft.destructive} registryValue={regVal('destructive')}
				onChange={(v) => setGeneralDraft((p) => ({ ...p, destructive: v }))} />
			<TriStateControl label={__('Idempotent', 'acrossai-abilities-manager')}
				value={generalDraft.idempotent} registryValue={regVal('idempotent')}
				onChange={(v) => setGeneralDraft((p) => ({ ...p, idempotent: v }))} />
			<TriStateControl label={__('Show in REST', 'acrossai-abilities-manager')}
				value={generalDraft.show_in_rest} registryValue={regVal('show_in_rest')}
				onChange={(v) => setGeneralDraft((p) => ({ ...p, show_in_rest: v }))} />

			<TabFooter
				draft={generalDraft}
				savedDraft={generalSaved}
				onSave={saveGeneral}
				onReset={() => setGeneralDraft(buildGeneralDraft(ability))}
				isSaving={generalSaving}
				notice={generalNotice}
				onDismissNotice={() => setGeneralNotice(null)}
			/>
		</div>
	);

	const mcpTab = (
		<div className="acrossai-ability-edit-panel__tab-content">
			<McpVisibilityControl
				key={slug}
				showInMcp={mcpDraft.show_in_mcp}
				mcpType={mcpDraft.mcp_type}
				mcpServers={mcpDraft.mcp_servers}
				onChange={(partial) => setMcpDraft((p) => ({ ...p, ...partial }))}
			/>

			<TabFooter
				draft={mcpDraft}
				savedDraft={mcpSaved}
				onSave={saveMcp}
				onReset={() => setMcpDraft(buildMcpDraft(ability))}
				isSaving={mcpSaving}
				notice={mcpNotice}
				onDismissNotice={() => setMcpNotice(null)}
			/>
		</div>
	);

	const sitewideConfig = window.acrossaiAbilitiesSitewide || {};

	const accessControlTab = (
		<div className="acrossai-ability-edit-panel__tab-content">
			<AccessControl
				namespace="acrossai-abilities"
				resourceKey={slug}
				restApiRoot={sitewideConfig.rest_url || '/wp-json'}
				nonce={sitewideConfig.nonce || ''}
			/>
		</div>
	);

	// ---------------------------------------------------------------------------
	// Render
	// ---------------------------------------------------------------------------

	const panel = (
		<>
			<div
				className="acrossai-ability-edit-panel__backdrop"
				role="presentation"
				onClick={handleClose}
			/>
			<div
				className="acrossai-ability-edit-panel"
				role="dialog"
				aria-modal="true"
				aria-label={__('Edit Ability Override', 'acrossai-abilities-manager')}
			>
				<div className="acrossai-ability-edit-panel__header">
					<div className="acrossai-ability-edit-panel__header-text">
						<h2 className="acrossai-ability-edit-panel__title">
							{__('Edit Ability Override', 'acrossai-abilities-manager')}
						</h2>
						<p className="acrossai-ability-edit-panel__slug description">{slug}</p>
					</div>
					<Button
						icon="no-alt"
						label={__('Close', 'acrossai-abilities-manager')}
						className="acrossai-ability-edit-panel__close"
						onClick={handleClose}
					/>
				</div>

				<TabPanel
					className="acrossai-ability-edit-panel__tabs"
					tabs={[
						{ name: 'general', title: __('General', 'acrossai-abilities-manager') },
						{ name: 'mcp', title: __('MCP', 'acrossai-abilities-manager') },
						{ name: 'access-control', title: __('Access Control', 'acrossai-abilities-manager') },
					]}
				>
					{(tab) => {
						if ('general' === tab.name) return generalTab;
						if ('mcp' === tab.name) return mcpTab;
						return accessControlTab;
					}}
				</TabPanel>
			</div>
		</>
	);

	return createPortal(panel, document.body);
}
