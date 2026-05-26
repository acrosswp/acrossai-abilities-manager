/**
 * LogsTable React component
 *
 * Renders a sortable, filterable, searchable logs table using
 * @wordpress/dataviews DataViews component.
 *
 * @package
 * @since 0.1.0
 */

import { DataViews } from '@wordpress/dataviews';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';

const DEFAULT_VIEW = {
	type: 'table',
	search: '',
	filters: [],
	page: 1,
	perPage: 20,
	sort: { field: 'created_at', direction: 'desc' },
	fields: [
		'ability_slug',
		'source',
		'status',
		'duration_ms',
		'user_id',
		'created_at',
	],
};

const FIELDS = [
	{
		id: 'ability_slug',
		label: 'Ability',
		enableSorting: true,
		enableGlobalSearch: true,
	},
	{
		id: 'source',
		label: 'Source',
		enableSorting: true,
		elements: [
			{ value: 'mcp', label: 'MCP' },
			{ value: 'rest', label: 'REST' },
			{ value: 'cli', label: 'CLI' },
			{ value: 'cron', label: 'Cron' },
			{ value: 'ajax', label: 'AJAX' },
			{ value: 'direct', label: 'Direct' },
		],
		filterBy: { operators: ['is'] },
	},
	{
		id: 'status',
		label: 'Status',
		enableSorting: true,
		elements: [
			{ value: 'success', label: 'Success' },
			{ value: 'error', label: 'Error' },
			{ value: 'permission_denied', label: 'Permission Denied' },
		],
		filterBy: { operators: ['is'] },
	},
	{
		id: 'duration_ms',
		label: 'Duration (ms)',
		enableSorting: true,
		render: ({ item }) => `${item.duration_ms} ms`,
	},
	{
		id: 'user_id',
		label: 'User ID',
		enableSorting: true,
		render: ({ item }) => item.user_id || '—',
	},
	{
		id: 'created_at',
		label: 'Created',
		enableSorting: true,
		render: ({ item }) => new Date(item.created_at).toLocaleString(),
	},
];

/**
 * LogsTable component
 *
 * @param {Object} props              Component props
 * @param {string} props.restEndpoint REST API endpoint URL
 * @return {JSX.Element} Rendered component
 */
export default function LogsTable({
	restEndpoint = '/wp-json/acrossai-abilities/v1/logger/logs',
}) {
	const [logs, setLogs] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState(null);
	const [totalItems, setTotalItems] = useState(0);
	const [view, setView] = useState(DEFAULT_VIEW);

	const fetchLogs = async (currentView) => {
		setIsLoading(true);
		setError(null);

		try {
			const params = new URLSearchParams();

			params.append('page', currentView.page || 1);
			params.append('per_page', currentView.perPage || 20);

			if (currentView.sort) {
				params.append('orderby', currentView.sort.field);
				params.append(
					'order',
					'desc' === currentView.sort.direction ? 'DESC' : 'ASC'
				);
			}

			if (currentView.search) {
				params.append('search', currentView.search);
			}

			if (currentView.filters) {
				currentView.filters.forEach((filter) => {
					if (filter.value && filter.value.length > 0) {
						params.append(filter.field, filter.value.join(','));
					}
				});
			}

			const response = await apiFetch({
				url: `${restEndpoint}?${params.toString()}`,
			});

			setLogs(response.logs || []);
			setTotalItems(response.total || 0);
		} catch (err) {
			setError(err.message || 'Failed to fetch logs');
			setLogs([]);
		} finally {
			setIsLoading(false);
		}
	};

	useEffect(() => {
		fetchLogs(view);
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	const handleChangeView = (newView) => {
		setView(newView);
		fetchLogs(newView);
	};

	if (error) {
		return (
			<div className="acrossai-logs-error">
				<p>Error loading logs: {error}</p>
			</div>
		);
	}

	if (isLoading && logs.length === 0) {
		return (
			<div className="acrossai-logs-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<DataViews
			data={logs}
			fields={FIELDS}
			view={view}
			onChangeView={handleChangeView}
			getItemId={(item) => String(item.id)}
			isLoading={isLoading}
			paginationInfo={{
				totalItems,
				totalPages: Math.ceil(totalItems / (view.perPage || 20)),
			}}
			defaultLayouts={{ table: {} }}
		/>
	);
}
