/**
 * AcrossAI Logger - Logs Table Component
 *
 * React component using @wordpress/dataviews for sortable, filterable logs display.
 *
 * @package AcrossAI\Abilities\Admin
 * @since   1.0.0
 */

import React, { useEffect, useState } from 'react';
import { DataViews, useDataViewsState } from '@wordpress/dataviews';
import apiFetch from '@wordpress/api-fetch';

/**
 * Logs Table Component
 *
 * Renders a DataViews table with all log entries, supporting:
 * - Search by ability slug
 * - Filter by source and status
 * - Sort by any column
 * - Pagination
 *
 * @component
 * @param {Object} props Component props
 * @param {string} props.restEndpoint REST endpoint URL (defaults to /wp-json/acrossai-abilities/v1/logger/logs)
 * @returns {React.ReactElement} DataViews table component
 */
export function LogsTable( { restEndpoint = '/wp-json/acrossai-abilities/v1/logger/logs' } ) {
	const [ logs, setLogs ] = useState( [] );
	const [ totalLogs, setTotalLogs ] = useState( 0 );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	const { state, setState } = useDataViewsState();

	// Fetch logs when state changes
	useEffect( () => {
		fetchLogs();
	}, [ state.pagination.pageIndex, state.pagination.pageSize, state.search, state.filters, state.sort ] );

	/**
	 * Fetch logs from REST endpoint
	 *
	 * @returns {Promise<void>}
	 */
	const fetchLogs = async () => {
		setIsLoading( true );
		setError( null );

		try {
			// Build query parameters
			const params = new URLSearchParams();

			// Pagination
			params.append( 'page', state.pagination.pageIndex + 1 );
			params.append( 'per_page', state.pagination.pageSize );

			// Search
			if ( state.search ) {
				params.append( 'search', state.search );
			}

			// Sorting
			if ( state.sort.field ) {
				params.append( 'orderby', state.sort.field );
				params.append( 'order', state.sort.direction === 'asc' ? 'ASC' : 'DESC' );
			}

			// Filters
			if ( state.filters ) {
				state.filters.forEach( ( filter ) => {
					if ( filter.field === 'source' && filter.value.length > 0 ) {
						params.append( 'source', filter.value.join( ',' ) );
					}
					if ( filter.field === 'status' && filter.value.length > 0 ) {
						params.append( 'status', filter.value.join( ',' ) );
					}
				} );
			}

			// Fetch from REST endpoint
			const response = await apiFetch( {
				path: `${ restEndpoint }?${ params.toString() }`,
			} );

			setLogs( response.logs || [] );
			setTotalLogs( response.total || 0 );
		} catch ( err ) {
			setError( err.message || 'Failed to fetch logs' );
			setLogs( [] );
			setTotalLogs( 0 );
		} finally {
			setIsLoading( false );
		}
	};

	// Define columns
	const fields = [
		{
			id: 'ability_slug',
			label: 'Ability Slug',
			type: 'text',
			enableHiding: false,
			elements: [],
		},
		{
			id: 'source',
			label: 'Source',
			type: 'enumeration',
			elements: [
				{ value: 'mcp', label: 'MCP' },
				{ value: 'rest', label: 'REST' },
				{ value: 'cli', label: 'CLI' },
				{ value: 'cron', label: 'Cron' },
				{ value: 'ajax', label: 'AJAX' },
				{ value: 'direct', label: 'Direct' },
			],
		},
		{
			id: 'status',
			label: 'Status',
			type: 'enumeration',
			elements: [
				{ value: 'success', label: 'Success' },
				{ value: 'error', label: 'Error' },
				{ value: 'permission_denied', label: 'Permission Denied' },
			],
		},
		{
			id: 'duration_ms',
			label: 'Duration (ms)',
			type: 'integer',
			width: '120px',
			align: 'right',
		},
		{
			id: 'created_at',
			label: 'Timestamp',
			type: 'datetime',
		},
		{
			id: 'user_id',
			label: 'User ID',
			type: 'integer',
			width: '100px',
		},
	];

	if ( error ) {
		return (
			<div className="acrossai-logs-error">
				<p>Error loading logs: { error }</p>
				<button onClick={ () => fetchLogs() }>Retry</button>
			</div>
		);
	}

	if ( logs.length === 0 && ! isLoading ) {
		return (
			<div className="acrossai-logs-empty">
				<p>No logs found</p>
			</div>
		);
	}

	return (
		<div className="acrossai-logs-container">
			<DataViews
				paginationInfo={ {
					totalItems: totalLogs,
					totalPages: Math.ceil( totalLogs / state.pagination.pageSize ),
				} }
				fields={ fields }
				data={ logs }
				isLoading={ isLoading }
				view={ state }
				onChangeView={ setState }
				supportedLayouts={ [ 'table' ] }
			/>
		</div>
	);
}

export default LogsTable;
