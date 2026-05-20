/**
 * Build Entry Point for Logger Admin UI
 *
 * Imports, registers, and mounts the LogsTable React component
 * to the admin Logs tab. Compiled via @wordpress/scripts build pipeline.
 *
 * @package acrossai-abilities-manager
 * @since 0.1.0
 */

import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import LogsTable from './components/LogsTable';
import '../scss/logs-table.scss';

// Wait for DOM to be ready
document.addEventListener( 'DOMContentLoaded', () => {
	const rootEl = document.getElementById( 'acrossai-logs-container' );

	if ( ! rootEl ) {
		console.error( 'Logs container element not found' );
		return;
	}

	// Set up nonce middleware for REST API
	if ( window.acrossaiAbilitiesLogger?.nonce ) {
		apiFetch.use( apiFetch.createNonceMiddleware( window.acrossaiAbilitiesLogger.nonce ) );
	}

	// Mount LogsTable component to DOM
	const root = createRoot( rootEl );
	root.render(
		<LogsTable
			restEndpoint={ window.acrossaiAbilitiesLogger?.restEndpoint || '/wp-json/acrossai-abilities/v1/logger/logs' }
		/>
	);
} );
