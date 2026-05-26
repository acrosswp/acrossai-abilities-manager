/**
 * McpServersCell — renders the mcp_servers value for a DataViews row.
 *
 * Rendering rules:
 *   - null value AND showInMcp === true  → "All"
 *   - non-empty array                    → comma-joined IDs, max 3, "+N more" chip
 *   - anything else                      → "—"
 *
 * @since 0.1.0
 */
import { __, sprintf } from '@wordpress/i18n';

const MAX_VISIBLE = 3;

/**
 * @param {Object}        props
 * @param {string[]|null} props.value     mcp_servers array or null.
 * @param {boolean|null}  props.showInMcp Effective show_in_mcp value.
 * @return {JSX.Element}
 */
export default function McpServersCell({ value, showInMcp }) {
	// null + show_in_mcp true → allow all servers.
	if ((null === value || undefined === value) && showInMcp) {
		return (
			<span className="acrossai-mcp-servers acrossai-mcp-servers--all">
				{__('All', 'acrossai-abilities-manager')}
			</span>
		);
	}

	if (!Array.isArray(value) || value.length === 0) {
		return (
			<span className="acrossai-mcp-servers acrossai-mcp-servers--none">
				{'—'}
			</span>
		);
	}

	const visible = value.slice(0, MAX_VISIBLE);
	const overflow = value.length - MAX_VISIBLE;

	return (
		<span className="acrossai-mcp-servers">
			{visible.join(', ')}
			{overflow > 0 && (
				<span className="acrossai-mcp-servers__overflow">
					{' ' +
						sprintf(
							/* translators: %d: number of hidden servers */
							__('+%d more', 'acrossai-abilities-manager'),
							overflow
						)}
				</span>
			)}
		</span>
	);
}
