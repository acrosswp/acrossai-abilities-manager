/**
 * Build Entry Point for Logger Admin UI
 *
 * Imports and registers the LogsTable React component.
 * Compiled via @wordpress/scripts build pipeline.
 *
 * @package acrossai-abilities-manager
 * @since 0.1.0
 */

import LogsTable from './components/LogsTable';
import '../scss/logs-table.scss';

// Export component for use in admin pages
export default LogsTable;
