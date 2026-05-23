/**
 * Abilities list view — DataViews-powered table (Constitution §III).
 *
 * Uses DataViews for sorting / filtering / pagination per architectural mandate.
 * Custom cell renderers produce the design-spec HTML: source badges, status dots,
 * type pills, MCP indicators.
 *
 * SEC-010-02: Bulk delete requires window.confirm before dispatching.
 *
 * @since 0.2.0
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { DataViews } from '@wordpress/dataviews';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';
import SourceBadge from './cells/SourceBadge';

const SLUG_PREFIX = 'acrossai-abilities/';
const LS_KEY = 'acrossai_abilities_list_view';

// ---------------------------------------------------------------------------
// Default view — persisted layout prefs (type + perPage) to localStorage
// ---------------------------------------------------------------------------
const DEFAULT_VIEW = {
	type: 'table',
	perPage: 20,
	page: 1,
	sort: { field: 'ability_slug', direction: 'asc' },
	filters: [],
	search: '',
	fields: [
		'ability_slug',
		'label',
		'category',
		'source',
		'status',
		'callback_type',
		'show_in_mcp',
		'updated_at',
	],
};

function loadView() {
	try {
		const stored = localStorage.getItem(LS_KEY);
		if (stored) {
			const parsed = JSON.parse(stored);
			return {
				...DEFAULT_VIEW,
				type: parsed.type || DEFAULT_VIEW.type,
				perPage: parsed.perPage || DEFAULT_VIEW.perPage,
			};
		}
	} catch {
		/* ignore */
	}
	return DEFAULT_VIEW;
}

// ---------------------------------------------------------------------------
// Cell renderers
// ---------------------------------------------------------------------------

function SlugCell({ item }) {
	const slug = item.ability_slug || '';
	const hasPrefix = slug.startsWith(SLUG_PREFIX);
	const prefix = hasPrefix ? SLUG_PREFIX : '';
	const suffix = hasPrefix ? slug.slice(SLUG_PREFIX.length) : slug;
	return (
		<>
			{prefix && <span className="slug-dim">{prefix}</span>}
			<span className="slug-name">{suffix}</span>
		</>
	);
}

function LabelCell({ item }) {
	return (
		<>
			<strong>{item.label || '—'}</strong>
			{item.provider && (
				<span className="lbl-by">
					{__('by', 'acrossai-abilities-manager')} {item.provider}
				</span>
			)}
		</>
	);
}

function CategoryCell({ item }) {
	if (!item.category) {
		return <span>—</span>;
	}
	return <span className="cpill">{item.category}</span>;
}

function StatusCell({ item }) {
	const isCustom = 'db' === (item.source || 'db');
	if (isCustom) {
		return 'publish' === item.status ? (
			<span className="sta-on">
				{__('● Enabled', 'acrossai-abilities-manager')}
			</span>
		) : (
			<span className="sta-off">
				{__('○ Disabled', 'acrossai-abilities-manager')}
			</span>
		);
	}
	// Inherited — show site_allowed override badge
	const sa = item.site_allowed;
	if (true === sa || 1 === sa) {
		return (
			<span className="ibadge ib-a">
				{__('Allowed', 'acrossai-abilities-manager')}
			</span>
		);
	}
	if (false === sa || 0 === sa) {
		return (
			<span className="ibadge ib-b">
				{__('Blocked', 'acrossai-abilities-manager')}
			</span>
		);
	}
	return (
		<span className="ibadge ib-d">
			{__('Default', 'acrossai-abilities-manager')}
		</span>
	);
}

const TYPE_MAP = {
	noop: { cls: 'tb-n', label: 'noop' },
	filter_hook: { cls: 'tb-f', label: 'filter_hook' },
	wp_remote_post: { cls: 'tb-r', label: 'wp_remote_post' },
	php_code: { cls: 'tb-p', label: 'php_code' },
};

function TypeCell({ item }) {
	if (!item.callback_type || 'db' !== (item.source || 'db')) {
		return <span>—</span>;
	}
	const { cls, label } = TYPE_MAP[item.callback_type] || TYPE_MAP.noop;
	return <span className={`tbadge ${cls}`}>{label}</span>;
}

function McpCell({ item }) {
	return item.show_in_mcp ? (
		<span className="mcp-y">
			{__('✓ Yes', 'acrossai-abilities-manager')}
		</span>
	) : (
		<span className="mcp-n">
			{__('○ No', 'acrossai-abilities-manager')}
		</span>
	);
}

function UpdatedCell({ item }) {
	if (!item.updated_at) {
		return <span>—</span>;
	}
	try {
		const d = new Date(item.updated_at);
		return <span title={item.updated_at}>{d.toLocaleDateString()}</span>;
	} catch {
		return <span>{item.updated_at}</span>;
	}
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

/**
 * AbilitiesList component.
 *
 * @return {JSX.Element}
 */
export default function AbilitiesList() {
	const [view, setView] = useState(loadView);

	const { abilities, total, pages, isLoading, error } = useSelect(
		(select) => ({
			abilities: select(STORE_NAME).getAbilities(),
			total: select(STORE_NAME).getTotal(),
			pages: select(STORE_NAME).getPages(),
			isLoading: select(STORE_NAME).getIsLoading(),
			error: select(STORE_NAME).getError(),
		}),
		[]
	);

	const dispatch = useDispatch(STORE_NAME);

	// Build server query params from current view state and fetch.
	useEffect(() => {
		const filterMap = (view.filters || []).reduce((acc, f) => {
			acc[f.field] = f.value;
			return acc;
		}, {});

		dispatch.fetchAbilities({
			page: view.page,
			per_page: view.perPage,
			search: view.search || undefined,
			orderby: view.sort?.field || undefined,
			order: view.sort?.direction || undefined,
			...filterMap,
		});
	}, [
		view.page,
		view.perPage,
		view.search,
		view.sort?.field,
		view.sort?.direction,
		// eslint-disable-next-line react-hooks/exhaustive-deps
		JSON.stringify(view.filters),
	]);

	// Persist only layout prefs (type, perPage) to localStorage — not filters/search.
	const handleViewChange = useCallback((newView) => {
		setView(newView);
		try {
			localStorage.setItem(
				LS_KEY,
				JSON.stringify({
					type: newView.type,
					perPage: newView.perPage,
				})
			);
		} catch {
			/* ignore */
		}
	}, []);

	// ---------------------------------------------------------------------------
	// Fields
	// ---------------------------------------------------------------------------
	const fields = [
		{
			id: 'ability_slug',
			label: __('Slug', 'acrossai-abilities-manager'),
			getValue: ({ item }) => item.ability_slug || '',
			render: ({ item }) => <SlugCell item={item} />,
			enableSorting: true,
			enableSearch: true,
		},
		{
			id: 'label',
			label: __('Label', 'acrossai-abilities-manager'),
			getValue: ({ item }) => item.label || '',
			render: ({ item }) => <LabelCell item={item} />,
			enableSorting: true,
		},
		{
			id: 'category',
			label: __('Category', 'acrossai-abilities-manager'),
			getValue: ({ item }) => item.category || '',
			render: ({ item }) => <CategoryCell item={item} />,
			enableHiding: true,
		},
		{
			id: 'source',
			label: __('Source', 'acrossai-abilities-manager'),
			getValue: ({ item }) => item.source || 'db',
			render: ({ item }) => <SourceBadge source={item.source || 'db'} />,
			elements: [
				{
					value: 'db',
					label: __('Custom', 'acrossai-abilities-manager'),
				},
				{
					value: 'plugin',
					label: __('Plugin', 'acrossai-abilities-manager'),
				},
				{
					value: 'core',
					label: __('Core', 'acrossai-abilities-manager'),
				},
				{
					value: 'theme',
					label: __('Theme', 'acrossai-abilities-manager'),
				},
			],
			enableHiding: true,
		},
		{
			id: 'status',
			label: __('Status', 'acrossai-abilities-manager'),
			getValue: ({ item }) => item.status || '',
			render: ({ item }) => <StatusCell item={item} />,
			elements: [
				{
					value: 'publish',
					label: __('Published', 'acrossai-abilities-manager'),
				},
				{
					value: 'draft',
					label: __('Draft', 'acrossai-abilities-manager'),
				},
			],
			enableHiding: true,
		},
		{
			id: 'callback_type',
			label: __('Type', 'acrossai-abilities-manager'),
			getValue: ({ item }) => item.callback_type || '',
			render: ({ item }) => <TypeCell item={item} />,
			elements: [
				{ value: 'noop', label: 'noop' },
				{ value: 'filter_hook', label: 'filter_hook' },
				{ value: 'wp_remote_post', label: 'wp_remote_post' },
				{ value: 'php_code', label: 'php_code' },
			],
			enableHiding: true,
		},
		{
			id: 'show_in_mcp',
			label: __('MCP', 'acrossai-abilities-manager'),
			getValue: ({ item }) => item.show_in_mcp,
			render: ({ item }) => <McpCell item={item} />,
			enableHiding: true,
		},
		{
			id: 'updated_at',
			label: __('Updated', 'acrossai-abilities-manager'),
			getValue: ({ item }) => item.updated_at || '',
			render: ({ item }) => <UpdatedCell item={item} />,
			enableSorting: true,
			enableHiding: true,
		},
	];

	// ---------------------------------------------------------------------------
	// Actions
	// ---------------------------------------------------------------------------
	const actions = [
		{
			id: 'edit',
			label: __('Edit', 'acrossai-abilities-manager'),
			isPrimary: true,
			callback: (items) => {
				const item = Array.isArray(items) ? items[0] : items;
				dispatch.setView({ mode: 'edit', id: item.id });
			},
		},
		{
			id: 'override',
			label: __('Override', 'acrossai-abilities-manager'),
			isPrimary: false,
			isEligible: (item) => 'db' !== (item.source || 'db'),
			callback: (items) => {
				const item = Array.isArray(items) ? items[0] : items;
				dispatch.setView({ mode: 'override', id: item.id });
			},
		},
		{
			id: 'toggle-status',
			label: __('Toggle Status', 'acrossai-abilities-manager'),
			isEligible: (item) => 'db' === (item.source || 'db'),
			callback: (items) => {
				const item = Array.isArray(items) ? items[0] : items;
				const newStatus =
					'publish' === item.status ? 'draft' : 'publish';
				dispatch.updateAbility(item.id, { status: newStatus });
			},
		},
		{
			id: 'delete',
			label: __('Delete', 'acrossai-abilities-manager'),
			isDestructive: true,
			isEligible: (item) => 'db' === (item.source || 'db'),
			callback: (items) => {
				const item = Array.isArray(items) ? items[0] : items;

				if (
					// eslint-disable-next-line no-alert
					window.confirm(
						__(
							'Delete this ability? This cannot be undone.',
							'acrossai-abilities-manager'
						)
					)
				) {
					dispatch.deleteAbility(item.id);
				}
			},
		},
		// Bulk actions (supportsBulk marks them for multi-select toolbar)
		{
			id: 'bulk-publish',
			label: __('Publish', 'acrossai-abilities-manager'),
			supportsBulk: true,
			isEligible: (item) =>
				'db' === (item.source || 'db') && 'publish' !== item.status,
			callback: (items) => {
				const ids = items
					.filter((i) => 'db' === (i.source || 'db'))
					.map((i) => i.id);
				if (ids.length) {
					dispatch.bulkUpdateStatus(ids, 'publish');
				}
			},
		},
		{
			id: 'bulk-unpublish',
			label: __('Unpublish', 'acrossai-abilities-manager'),
			supportsBulk: true,
			isEligible: (item) =>
				'db' === (item.source || 'db') && 'draft' !== item.status,
			callback: (items) => {
				const ids = items
					.filter((i) => 'db' === (i.source || 'db'))
					.map((i) => i.id);
				if (ids.length) {
					dispatch.bulkUpdateStatus(ids, 'draft');
				}
			},
		},
		{
			id: 'bulk-delete',
			label: __('Delete', 'acrossai-abilities-manager'),
			supportsBulk: true,
			isDestructive: true,
			isEligible: (item) => 'db' === (item.source || 'db'),
			callback: (items) => {
				const dbItems = items.filter(
					(i) => 'db' === (i.source || 'db')
				);
				if (!dbItems.length) {
					return;
				}
				const count = dbItems.length;
				// SEC-010-02: bulk delete requires explicit confirmation.
				if (
					// eslint-disable-next-line no-alert
					window.confirm(
						1 === count
							? __(
									'Delete 1 ability? This cannot be undone.',
									'acrossai-abilities-manager'
								)
							: `${__('Delete', 'acrossai-abilities-manager')} ${count} ${__('abilities? This cannot be undone.', 'acrossai-abilities-manager')}`
					)
				) {
					dispatch.bulkDeleteAbilities(dbItems.map((i) => i.id));
				}
			},
		},
	];

	// ---------------------------------------------------------------------------
	// Quick-link counts (from current loaded page — approximate for sub-counts)
	// ---------------------------------------------------------------------------
	const publishedCount = abilities.filter(
		(a) => 'publish' === a.status
	).length;
	const draftCount = abilities.filter((a) => 'draft' === a.status).length;

	const activeStatusFilter = (view.filters || []).find(
		(f) => 'status' === f.field
	);

	function setStatusFilter(status) {
		const baseFilters = (view.filters || []).filter(
			(f) => 'status' !== f.field
		);
		const newFilters = status
			? [
					...baseFilters,
					{ field: 'status', operator: 'is', value: status },
				]
			: baseFilters;
		handleViewChange({ ...view, page: 1, filters: newFilters });
	}

	return (
		<div className="wrap">
			{/* Error notice */}
			{error && (
				<div className="notice notice-error">
					<p>{error}</p>
					<button
						type="button"
						className="notice-dismiss"
						aria-label={__('Dismiss', 'acrossai-abilities-manager')}
						onClick={() => dispatch.clearError()}
					/>
				</div>
			)}

			{/* Page title row */}
			<div className="abilities-list-header">
				<h1 className="wp-heading-inline">
					{__('Custom Abilities', 'acrossai-abilities-manager')}
				</h1>
				<button
					type="button"
					className="page-title-action"
					onClick={() => dispatch.setView({ mode: 'create' })}
				>
					{__('+ Add New Ability', 'acrossai-abilities-manager')}
				</button>
			</div>

			<p className="abilities-subtitle">
				{__(
					'Manage abilities created on this site and override how plugin, theme and core abilities behave.',
					'acrossai-abilities-manager'
				)}
			</p>

			{/* Quick-links: All | Published | Draft */}
			<ul className="subsubsub">
				<li>
					<a
						href="#all"
						className={!activeStatusFilter ? 'current' : ''}
						onClick={(e) => {
							e.preventDefault();
							setStatusFilter(null);
						}}
					>
						{__('All', 'acrossai-abilities-manager')}{' '}
						<span className="count">({total})</span>
					</a>
				</li>
				<li>
					<a
						href="#published"
						className={
							activeStatusFilter?.value === 'publish'
								? 'current'
								: ''
						}
						onClick={(e) => {
							e.preventDefault();
							setStatusFilter('publish');
						}}
					>
						{__('Published', 'acrossai-abilities-manager')}{' '}
						<span className="count">({publishedCount})</span>
					</a>
				</li>
				<li>
					<a
						href="#draft"
						className={
							activeStatusFilter?.value === 'draft'
								? 'current'
								: ''
						}
						onClick={(e) => {
							e.preventDefault();
							setStatusFilter('draft');
						}}
					>
						{__('Draft', 'acrossai-abilities-manager')}{' '}
						<span className="count">({draftCount})</span>
					</a>
				</li>
			</ul>

			{/* DataViews table (Constitution §III) */}
			<DataViews
				data={abilities}
				fields={fields}
				view={view}
				onChangeView={handleViewChange}
				actions={actions}
				paginationInfo={{ totalItems: total, totalPages: pages }}
				getItemId={(item) => String(item.id)}
				defaultLayouts={{ table: {} }}
				isLoading={isLoading}
			/>

			{/* No results */}
			{!isLoading && !error && 0 === abilities.length && (
				<div className="abilities-no-results">
					{__('No abilities found.', 'acrossai-abilities-manager')}
				</div>
			)}
		</div>
	);
}
