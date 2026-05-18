/**
 * AcrossAI Logger - Admin UI Build Entry Point
 *
 * Entry point for @wordpress/scripts build pipeline.
 * Imports React component and styles, outputs to build/logger.js and build/logger.css.
 *
 * @package AcrossAI\Abilities\Admin
 * @since   1.0.0
 */

import React from 'react';
import { createRoot } from 'react-dom';
import LogsTable from './components/LogsTable';
import '../scss/logs-table.scss';

// Mount React component when DOM is ready
document.addEventListener( 'DOMContentLoaded', function() {
	const container = document.getElementById( 'acrossai-logs-container' );
	if ( container ) {
		const root = createRoot( container );
		root.render( <LogsTable /> );
	}
} );

export { LogsTable };
