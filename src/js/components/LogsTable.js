/**
 * LogsTable React component
 *
 * Renders a sortable, filterable, searchable logs table using
 * @wordpress/dataviews DataViews component.
 *
 * @package acrossai-abilities-manager
 * @since 0.1.0
 */

import { DataViews } from '@wordpress/dataviews';
import { useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';

/**
 * LogsTable component
 *
 * @param {Object} props Component props
 * @param {string} props.restEndpoint REST API endpoint URL
 * @returns {JSX.Element} Rendered component
 */
export default function LogsTable( { restEndpoint = '/wp-json/acrossai-abilities/v1/logger/logs' } ) {
	const [ logs, setLogs ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ totalPages, setTotalPages ] = useState( 0 );
	const [ totalLogs, setTotalLogs ] = useState( 0 );

	// Define columns for DataViews
	const columns = useMemo(
		() => [
			{
				id: 'ability_slug',
				label: 'Ability',
				type: 'text',
				isVisible: true,
				enableHiding: false,
				enableSorting: true,
				isSearchable: true,
			},
			{
				id: 'source',
				label: 'Source',
				type: 'enumeration',
				isVisible: true,
				options: [
					{ value: 'mcp', label: 'MCP' },
					{ value: 'rest', label: 'REST' },
					{ value: 'cli', label: 'CLI' },
					{ value: 'cron', label: 'Cron' },
					{ value: 'ajax', label: 'AJAX' },
					{ value: 'direct', label: 'Direct' },
				],
				enableSorting: true,
				enableFiltering: true,
			},
			{
				id: 'status',
				label: 'Status',
				type: 'enumeration',
				isVisible: true,
				options: [
					{ value: 'success', label: 'Success' },
					{ value: 'error', label: 'Error' },
					{ value: 'permission_denied', label: 'Permission Denied' },
				],
				enableSorting: true,
				enableFiltering: true,
			},
			{
				id: 'duration_ms',
				label: 'Duration',
				type: 'integer',
				isVisible: true,
				enableSorting: true,
				render: ( { item } ) => `${ item.duration_ms } ms`,
			},
			{
				id: 'user_id',
				label: 'User',
				type: 'integer',
				isVisible: true,
				enableSorting: true,
				render: ( { item } ) => item.user_id || '—',
			},
			{
				id: 'created_at',
				label: 'Created',
				type: 'date',
				isVisible: true,
				enableSorting: true,
				render: ( { item } ) => {
					const date = new Date( item.created_at );
					return date.toLocaleString();
				},
			},
		],
		[]
	);

	// Handle data view changes (filter, sort, search, pagination)
	const handleChangeView = async ( newView ) => {
		setIsLoading( true );
		setError( null );

		try {
			// Build query params
			const params = new URLSearchParams();

			// Pagination
			if ( newView.page ) {
				params.append( 'page', newView.page );
			}
			if ( newView.perPage ) {
				params.append( 'per_page', newView.perPage );
			}

			// Sort
			if ( newView.sort ) {
				params.append( 'orderby', newView.sort.field );
				params.append( 'order', newView.sort.direction === 'desc' ? 'DESC' : 'ASC' );
			}

			// Search
			if ( newView.search ) {
				params.append( 'search', newView.search );
			}

			// Filters
			if ( newView.filters ) {
				newView.filters.forEach( ( filter ) => {
					if ( filter.value && filter.value.length > 0 ) {
						params.append( filter.field, filter.value.join( ',' ) );
					}
				} );
			}

			// Fetch from REST endpoint with nonce (set up by entry point)
			const response = await apiFetch( {
				path: `${ restEndpoint }?${ params.toString() }`,
			} );

			setLogs( response.logs || [] );
			setTotalLogs( response.total || 0 );
			setTotalPages( response.pages || 0 );
		} catch ( err ) {
			setError( err.message || 'Failed to fetch logs' );
			setLogs( [] );
		} finally {
			setIsLoading( false );
		}
	};

	// Initial load on mount and when restEndpoint changes (useEffect, not useMemo)
	useEffect( () => {
		handleChangeView( {
			page: 1,
			perPage: 20,
			sort: { field: 'created_at', direction: 'desc' },
			filters: [],
			search: '',
		} );
	}, [ restEndpoint ] );

	if ( error ) {
		return (
			<div className="acrossai-logs-error">
				<p>Error loading logs: { error }</p>
			</div>
		);
	}

	if ( isLoading && logs.length === 0 ) {
		return (
			<div className="acrossai-logs-loading">
				<Spinner />
			</div>
		);
	}

	if ( logs.length === 0 ) {
		return (
			<div className="acrossai-logs-empty">
				<p>No logs found</p>
			</div>
		);
	}

	return (
		<div className="acrossai-logs-container">
			<DataViews
				columns={ columns }
				data={ logs }
				isLoading={ isLoading }
				view={ {
					type: 'table',
					perPage: 20,
					page: 1,
				} }
				onChangeView={ handleChangeView }
				paginationInfo={ {
					totalItems: totalLogs,
					totalPages: totalPages,
				} }
			/>
		</div>
	);
}
