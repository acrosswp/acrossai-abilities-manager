/**
 * Abilities list view — Classic WP-admin HTML table.
 *
 * Matches "Abilities Manager — Final Design.html" pixel-for-pixel:
 * a .wptable with checkboxes, inline row actions, subsubsub quick-links,
 * tablenav with bulk-actions + source/status filters + search.
 *
 * SEC-010-02: Bulk delete requires window.confirm before dispatching.
 *
 * @since 0.2.0
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';
import SourceBadge from './cells/SourceBadge';

const SLUG_PREFIX = 'acrossai-abilities/';

// ---------------------------------------------------------------------------
// Cell renderers
// ---------------------------------------------------------------------------

function SlugCell({ item }) {
	const slug = item.ability_slug || '';
	const hasPrefix = slug.startsWith(SLUG_PREFIX);
	const dimPart = hasPrefix ? SLUG_PREFIX : '';
	const namePart = hasPrefix ? slug.slice(SLUG_PREFIX.length) : slug;
	return (
		<div className="slug-cell">
			{dimPart && <span className="slug-dim">{dimPart}</span>}
			<span className="slug-name">{namePart}</span>
		</div>
	);
}

function LabelCell({ item }) {
	return (
		<>
			<div style={{ fontSize: '13px', fontWeight: 600 }}>
				{item.label || '—'}
			</div>
			{item.provider && (
				<div className="lbl-by">
					{__('by', 'acrossai-abilities-manager')} {item.provider}
				</div>
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
			<div className="sta sta-on">
				<div className="sta-dot" />
				{__('Enabled', 'acrossai-abilities-manager')}
			</div>
		) : (
			<div className="sta sta-off">
				<div className="sta-dot" />
				{__('Disabled', 'acrossai-abilities-manager')}
			</div>
		);
	}
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
	const type = item.callback_type || item._registry?.callback_type;
	if (!type) {
		return <span>—</span>;
	}
	const { cls, label } = TYPE_MAP[type] || TYPE_MAP.noop;
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

function DescriptionCell({ item }) {
	const desc = item.description || item._registry?.description || '';
	if (!desc) {
		return <span>—</span>;
	}
	const short = desc.length > 80 ? desc.slice(0, 80) + '…' : desc;
	return <span title={desc}>{short}</span>;
}

function ShowInRestCell({ item }) {
	const val = item.show_in_rest ?? item._registry?.show_in_rest ?? false;
	return val ? (
		<span className="mcp-y">
			{__('✓ Yes', 'acrossai-abilities-manager')}
		</span>
	) : (
		<span className="mcp-n">
			{__('○ No', 'acrossai-abilities-manager')}
		</span>
	);
}

// ---------------------------------------------------------------------------
// Column visibility constants
// ---------------------------------------------------------------------------

const COLUMN_DEFAULTS = {
	label: true,
	category: true,
	source: true,
	status: true,
	type: true,
	description: true,
	show_in_rest: true,
	mcp: true,
};

const COLUMN_LABELS = {
	label: __('Label', 'acrossai-abilities-manager'),
	category: __('Category', 'acrossai-abilities-manager'),
	source: __('Source', 'acrossai-abilities-manager'),
	status: __('Status', 'acrossai-abilities-manager'),
	type: __('Type', 'acrossai-abilities-manager'),
	description: __('Description', 'acrossai-abilities-manager'),
	show_in_rest: __('Show in REST', 'acrossai-abilities-manager'),
	mcp: __('MCP', 'acrossai-abilities-manager'),
};

const LS_KEY = 'acrossai_abilities_columns';

function loadColumnPrefs() {
	try {
		const saved = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
		const result = { ...COLUMN_DEFAULTS };
		Object.keys(COLUMN_DEFAULTS).forEach((key) => {
			if (key in saved) {
				result[key] = !!saved[key];
			}
		});
		return result;
	} catch {
		return { ...COLUMN_DEFAULTS };
	}
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

/**
 * AbilitiesList component.
 *
 * @return {import('react').ReactElement} Rendered abilities table.
 */
export default function AbilitiesList() {
	// ---- filter / sort / search state ----
	const [search, setSearch] = useState('');
	const [sourceFilter, setSourceFilter] = useState('');
	const [statusFilter, setStatusFilter] = useState('');
	const [sortDir, setSortDir] = useState('asc');
	const [page, setPage] = useState(1);
	const perPage = Math.min(
		200,
		Math.max(
			1,
			parseInt(window.acrossaiAbilitiesManager?.perPage, 10) || 20
		)
	);

	// ---- checkbox state ----
	const [selected, setSelected] = useState(new Set());
	const [bulkAction, setBulkAction] = useState('');

	// ---- column visibility state ----
	const [visibleColumns, setVisibleColumns] = useState(loadColumnPrefs);
	const [columnsOpen, setColumnsOpen] = useState(false);

	const { abilities, total, isLoading, error } = useSelect(
		(select) => ({
			abilities: select(STORE_NAME).getAbilities(),
			total: select(STORE_NAME).getTotal(),
			isLoading: select(STORE_NAME).getIsLoading(),
			error: select(STORE_NAME).getError(),
		}),
		[]
	);

	const dispatch = useDispatch(STORE_NAME);

	// ---- derived pagination values ----
	const totalPages = Math.ceil(total / perPage) || 1;

	// ---- column visibility helpers ----
	function toggleColumn(key) {
		setVisibleColumns((prev) => {
			const next = { ...prev, [key]: !prev[key] };
			try {
				localStorage.setItem(LS_KEY, JSON.stringify(next));
			} catch {
				// localStorage unavailable — silent fallback
			}
			return next;
		});
	}

	const visibleCount = Object.values(visibleColumns).filter(Boolean).length;
	const tableColSpan = visibleCount + 3; // +1 checkbox, +1 Slug, +1 Actions

	// Fetch whenever filters change.
	useEffect(() => {
		dispatch.fetchAbilities({
			page,
			per_page: perPage,
			search: search || undefined,
			orderby: 'ability_slug',
			order: sortDir,
			source: sourceFilter || undefined,
			status: statusFilter || undefined,
		});
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [page, search, sourceFilter, statusFilter, sortDir]);

	// Reset to page 1 whenever filters/search/sort change.
	useEffect(() => {
		setPage(1);
	}, [search, sourceFilter, statusFilter, sortDir]);

	// Close columns panel when clicking outside.
	useEffect(() => {
		if (!columnsOpen) {
			return;
		}
		const handler = (e) => {
			if (!e.target.closest('.columns-toggle')) {
				setColumnsOpen(false);
			}
		};
		document.addEventListener('mousedown', handler);
		return () => document.removeEventListener('mousedown', handler);
	}, [columnsOpen]);

	// ---- counts from current page (approximate) ----
	const publishedCount = abilities.filter(
		(a) => 'publish' === a.status
	).length;
	const draftCount = abilities.filter((a) => 'draft' === a.status).length;

	// ---- checkbox helpers ----
	const dbAbilities = abilities.filter((a) => 'db' === (a.source || 'db'));
	const allDbSlugs = new Set(dbAbilities.map((a) => a.ability_slug));
	const allChecked =
		allDbSlugs.size > 0 && [...allDbSlugs].every((s) => selected.has(s));

	const toggleAll = useCallback(() => {
		if (allChecked) {
			setSelected(new Set());
		} else {
			setSelected(new Set(allDbSlugs));
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [allChecked, JSON.stringify([...allDbSlugs])]);

	const toggleOne = useCallback((slug) => {
		setSelected((prev) => {
			const next = new Set(prev);
			if (next.has(slug)) {
				next.delete(slug);
			} else {
				next.add(slug);
			}
			return next;
		});
	}, []);

	// ---- inline status dropdown ----
	function handleStatusDropdown(item, value) {
		const newStatus = 'e' === value ? 'publish' : 'draft';
		dispatch.updateAbility(item.ability_slug, { status: newStatus });
	}

	// ---- bulk apply ----
	function handleBulkApply() {
		if (!bulkAction || !selected.size) {
			return;
		}
		const slugs = [...selected];

		if ('publish' === bulkAction) {
			dispatch.bulkUpdateStatus(slugs, 'publish');
			setSelected(new Set());
		} else if ('unpublish' === bulkAction) {
			dispatch.bulkUpdateStatus(slugs, 'draft');
			setSelected(new Set());
		} else if ('delete' === bulkAction) {
			// Block mixed-source bulk delete: only db-source abilities may be deleted.
			const nonDbInSelection = slugs.filter((s) => !allDbSlugs.has(s));
			if (nonDbInSelection.length > 0) {
				// eslint-disable-next-line no-alert
				window.alert(
					__(
						'Bulk delete is only available for custom (db) abilities. Deselect non-custom abilities and try again.',
						'acrossai-abilities-manager'
					)
				);
				return;
			}
			const count = slugs.length;
			// SEC-010-02: require explicit confirmation.
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
				dispatch.bulkDeleteAbilities(slugs);
				setSelected(new Set());
			}
		}
		setBulkAction('');
	}

	// ---- sort toggle ----
	function toggleSort() {
		setSortDir((d) => ('asc' === d ? 'desc' : 'asc'));
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

			{/* Page title */}
			<div className="pg-title">
				<h1 className="wp-heading-inline">
					{__('Custom Abilities', 'acrossai-abilities-manager')}
				</h1>
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
						className={`ssl${'' === statusFilter ? ' on' : ''}`}
						onClick={(e) => {
							e.preventDefault();
							setStatusFilter('');
						}}
					>
						{__('All', 'acrossai-abilities-manager')}{' '}
						<span className="ct">({total})</span>
					</a>
					<span className="ssp">|</span>
				</li>
				<li>
					<a
						href="#published"
						className={`ssl${'publish' === statusFilter ? ' on' : ''}`}
						onClick={(e) => {
							e.preventDefault();
							setStatusFilter('publish');
						}}
					>
						{__('Published', 'acrossai-abilities-manager')}{' '}
						<span className="ct">({publishedCount})</span>
					</a>
					<span className="ssp">|</span>
				</li>
				<li>
					<a
						href="#draft"
						className={`ssl${'draft' === statusFilter ? ' on' : ''}`}
						onClick={(e) => {
							e.preventDefault();
							setStatusFilter('draft');
						}}
					>
						{__('Draft', 'acrossai-abilities-manager')}{' '}
						<span className="ct">({draftCount})</span>
					</a>
				</li>
			</ul>

			{/* Tablenav */}
			<div className="tablenav">
				<div className="bulk-row">
					<select
						value={bulkAction}
						onChange={(e) => setBulkAction(e.target.value)}
						aria-label={__(
							'Bulk actions',
							'acrossai-abilities-manager'
						)}
					>
						<option value="">
							{__('Bulk Actions', 'acrossai-abilities-manager')}
						</option>
						<option value="publish">
							{__('Publish', 'acrossai-abilities-manager')}
						</option>
						<option value="unpublish">
							{__('Unpublish', 'acrossai-abilities-manager')}
						</option>
						<option value="delete">
							{__('Delete', 'acrossai-abilities-manager')}
						</option>
					</select>
					<button
						type="button"
						className="button"
						onClick={handleBulkApply}
					>
						{__('Apply', 'acrossai-abilities-manager')}
					</button>
				</div>

				<select
					value={sourceFilter}
					onChange={(e) => setSourceFilter(e.target.value)}
					aria-label={__(
						'Filter by source',
						'acrossai-abilities-manager'
					)}
				>
					<option value="">
						{__('All Sources', 'acrossai-abilities-manager')}
					</option>
					<option value="db">
						{__('Custom', 'acrossai-abilities-manager')}
					</option>
					<option value="plugin">
						{__('Plugin', 'acrossai-abilities-manager')}
					</option>
					<option value="core">
						{__('Core', 'acrossai-abilities-manager')}
					</option>
					<option value="theme">
						{__('Theme', 'acrossai-abilities-manager')}
					</option>
				</select>

				<select
					value={statusFilter}
					onChange={(e) => setStatusFilter(e.target.value)}
					aria-label={__(
						'Filter by status',
						'acrossai-abilities-manager'
					)}
				>
					<option value="">
						{__('All Statuses', 'acrossai-abilities-manager')}
					</option>
					<option value="publish">
						{__('Published', 'acrossai-abilities-manager')}
					</option>
					<option value="draft">
						{__('Draft', 'acrossai-abilities-manager')}
					</option>
				</select>

				<div className="tablenav-search">
					<span className="search-icon" aria-hidden="true">
						🔍
					</span>
					<input
						type="text"
						value={search}
						placeholder={__(
							'Search abilities…',
							'acrossai-abilities-manager'
						)}
						onChange={(e) => setSearch(e.target.value)}
						aria-label={__(
							'Search abilities',
							'acrossai-abilities-manager'
						)}
					/>
				</div>

				<div
					className="columns-toggle"
					style={{ position: 'relative' }}
				>
					<button
						type="button"
						className="button"
						onClick={() => setColumnsOpen((o) => !o)}
					>
						{__('Columns', 'acrossai-abilities-manager')}{' '}
						{columnsOpen ? '▴' : '▾'}
					</button>
					{columnsOpen && (
						<div className="columns-panel">
							{Object.keys(COLUMN_DEFAULTS).map((key) => (
								<label
									key={key}
									htmlFor={`col-toggle-${key}`}
									className="columns-panel-item"
								>
									<input
										id={`col-toggle-${key}`}
										type="checkbox"
										checked={!!visibleColumns[key]}
										onChange={() => toggleColumn(key)}
									/>{' '}
									{COLUMN_LABELS[key]}
								</label>
							))}
						</div>
					)}
				</div>

				<div className="tn-pages">
					{isLoading
						? __('Loading…', 'acrossai-abilities-manager')
						: `${abilities.length} ${__('of', 'acrossai-abilities-manager')} ${total} ${__('items', 'acrossai-abilities-manager')}`}
				</div>

				<div className="tablenav-pages">
					<span className="displaying-num">
						{total} {__('items', 'acrossai-abilities-manager')}
					</span>
					<span className="pagination-links">
						<button
							className="button"
							disabled={1 === page}
							onClick={() => setPage(1)}
						>
							«
						</button>
						<button
							className="button"
							disabled={1 === page}
							onClick={() => setPage((p) => p - 1)}
						>
							‹
						</button>
						<span className="paging-input">
							{page} {__('of', 'acrossai-abilities-manager')}{' '}
							{totalPages}
						</span>
						<button
							className="button"
							disabled={page >= totalPages}
							onClick={() => setPage((p) => p + 1)}
						>
							›
						</button>
						<button
							className="button"
							disabled={page >= totalPages}
							onClick={() => setPage(totalPages)}
						>
							»
						</button>
					</span>
				</div>
			</div>

			{/* WP-style table */}
			<table className="wptable">
				<colgroup>
					<col style={{ width: '32px' }} />
					<col className="col-slug" />
					{!!visibleColumns.label && <col className="col-lbl" />}
					{!!visibleColumns.category && <col className="col-cat" />}
					{!!visibleColumns.source && <col className="col-src" />}
					{!!visibleColumns.status && <col className="col-sta" />}
					{!!visibleColumns.type && <col className="col-typ" />}
					{!!visibleColumns.description && (
						<col className="col-desc" />
					)}
					{!!visibleColumns.show_in_rest && (
						<col className="col-rest" />
					)}
					{!!visibleColumns.mcp && <col className="col-mcp" />}
					<col className="col-act" />
				</colgroup>
				<thead>
					<tr>
						<th className="chk-col">
							<input
								type="checkbox"
								checked={allChecked}
								onChange={toggleAll}
								aria-label={__(
									'Select all',
									'acrossai-abilities-manager'
								)}
							/>
						</th>
						<th
							className="sorted"
							style={{ cursor: 'pointer' }}
							onClick={toggleSort}
						>
							{__('Slug', 'acrossai-abilities-manager')}{' '}
							{'asc' === sortDir ? '↑' : '↓'}
						</th>
						{!!visibleColumns.label && (
							<th>{__('Label', 'acrossai-abilities-manager')}</th>
						)}
						{!!visibleColumns.category && (
							<th>
								{__('Category', 'acrossai-abilities-manager')}
							</th>
						)}
						{!!visibleColumns.source && (
							<th>
								{__('Source', 'acrossai-abilities-manager')}
							</th>
						)}
						{!!visibleColumns.status && (
							<th>
								{__('Status', 'acrossai-abilities-manager')}
							</th>
						)}
						{!!visibleColumns.type && (
							<th>{__('Type', 'acrossai-abilities-manager')}</th>
						)}
						{!!visibleColumns.description && (
							<th>
								{__(
									'Description',
									'acrossai-abilities-manager'
								)}
							</th>
						)}
						{!!visibleColumns.show_in_rest && (
							<th>
								{__(
									'Show in REST',
									'acrossai-abilities-manager'
								)}
							</th>
						)}
						{!!visibleColumns.mcp && (
							<th>{__('MCP', 'acrossai-abilities-manager')}</th>
						)}
						<th>{__('Actions', 'acrossai-abilities-manager')}</th>
					</tr>
				</thead>
				<tbody>
					{isLoading && (
						<tr>
							<td
								colSpan={tableColSpan}
								style={{
									textAlign: 'center',
									padding: '20px',
									color: '#646970',
								}}
							>
								{__('Loading…', 'acrossai-abilities-manager')}
							</td>
						</tr>
					)}
					{!isLoading && 0 === abilities.length && (
						<tr>
							<td
								colSpan={tableColSpan}
								style={{
									textAlign: 'center',
									padding: '20px',
									color: '#646970',
								}}
							>
								{__(
									'No abilities found.',
									'acrossai-abilities-manager'
								)}
							</td>
						</tr>
					)}
					{abilities.map((item) => {
						const isCustom = 'db' === (item.source || 'db');
						const itemSlug = item.ability_slug;
						const isChecked = selected.has(itemSlug);
						const statusCls = 'publish' === item.status ? 'e' : 'd';

						return (
							<tr
								key={item.ability_slug}
								className={isCustom ? '' : 'inh-row'}
							>
								<td className="chk-col">
									{isCustom && (
										<input
											type="checkbox"
											checked={isChecked}
											onChange={() => toggleOne(itemSlug)}
											aria-label={`${__('Select', 'acrossai-abilities-manager')} ${item.ability_slug}`}
										/>
									)}
								</td>
								<td>
									<SlugCell item={item} />
								</td>
								{!!visibleColumns.label && (
									<td>
										<LabelCell item={item} />
									</td>
								)}
								{!!visibleColumns.category && (
									<td>
										<CategoryCell item={item} />
									</td>
								)}
								{!!visibleColumns.source && (
									<td>
										<SourceBadge
											source={item.source || 'db'}
										/>
									</td>
								)}
								{!!visibleColumns.status && (
									<td>
										<StatusCell item={item} />
									</td>
								)}
								{!!visibleColumns.type && (
									<td>
										<TypeCell item={item} />
									</td>
								)}
								{!!visibleColumns.description && (
									<td>
										<DescriptionCell item={item} />
									</td>
								)}
								{!!visibleColumns.show_in_rest && (
									<td>
										<ShowInRestCell item={item} />
									</td>
								)}
								{!!visibleColumns.mcp && (
									<td>
										<McpCell item={item} />
									</td>
								)}
								<td>
									<div className="racts">
										{isCustom ? (
											<>
												<button
													type="button"
													className="ra"
													onClick={() =>
														dispatch.setView({
															mode: 'edit',
															slug: item.ability_slug,
															ability: item,
														})
													}
												>
													{__(
														'Edit',
														'acrossai-abilities-manager'
													)}
												</button>
												<span className="ra-sep">
													|
												</span>
												<select
													className={`sdd ${statusCls}`}
													value={statusCls}
													onChange={(e) =>
														handleStatusDropdown(
															item,
															e.target.value
														)
													}
													aria-label={__(
														'Change status',
														'acrossai-abilities-manager'
													)}
												>
													<option value="e">
														{__(
															'Enabled',
															'acrossai-abilities-manager'
														)}
													</option>
													<option value="d">
														{__(
															'Disabled',
															'acrossai-abilities-manager'
														)}
													</option>
												</select>
												<span className="ra-sep">
													|
												</span>
												<button
													type="button"
													className="ra del"
													onClick={() => {
														if (
															// eslint-disable-next-line no-alert
															window.confirm(
																__(
																	'Delete this ability? This cannot be undone.',
																	'acrossai-abilities-manager'
																)
															)
														) {
															dispatch.deleteAbility(
																item.ability_slug
															);
														}
													}}
												>
													{__(
														'Delete',
														'acrossai-abilities-manager'
													)}
												</button>
											</>
										) : (
											<>
												<button
													type="button"
													className="ra"
													onClick={() =>
														dispatch.setView({
															mode: 'edit',
															slug: item.ability_slug,
															ability: item,
														})
													}
												>
													{__(
														'Edit',
														'acrossai-abilities-manager'
													)}
												</button>
												{item.has_override && (
													<>
														<span className="ra-sep">
															|
														</span>
														<button
															type="button"
															className="ra"
															onClick={() => {
																if (
																	// eslint-disable-next-line no-alert
																	window.confirm(
																		__(
																			'Clear all overrides for this ability? This cannot be undone.',
																			'acrossai-abilities-manager'
																		)
																	)
																) {
																	dispatch.clearOverrides(
																		item.ability_slug
																	);
																}
															}}
														>
															{__(
																'Clear All Overrides',
																'acrossai-abilities-manager'
															)}
														</button>
													</>
												)}
											</>
										)}
									</div>
								</td>
							</tr>
						);
					})}
				</tbody>
			</table>

			<div className="tablenav-pages tablenav-pages-below">
				<span className="displaying-num">
					{total} {__('items', 'acrossai-abilities-manager')}
				</span>
				<span className="pagination-links">
					<button
						className="button"
						disabled={1 === page}
						onClick={() => setPage(1)}
					>
						«
					</button>
					<button
						className="button"
						disabled={1 === page}
						onClick={() => setPage((p) => p - 1)}
					>
						‹
					</button>
					<span className="paging-input">
						{page} {__('of', 'acrossai-abilities-manager')}{' '}
						{totalPages}
					</span>
					<button
						className="button"
						disabled={page >= totalPages}
						onClick={() => setPage((p) => p + 1)}
					>
						›
					</button>
					<button
						className="button"
						disabled={page >= totalPages}
						onClick={() => setPage(totalPages)}
					>
						»
					</button>
				</span>
			</div>
		</div>
	);
}
