/**
 * MCP Visibility Control — RadioControl with 4 options + conditional server list.
 *
 * Payload encoding (explicit — avoids "Invalid parameter(s)" from WP REST API):
 *   option 1 "Keep as Default"          → show_in_mcp: null,  mcp_type: null, mcp_servers: null
 *   option 2 "Disable for MCP"          → show_in_mcp: false, mcp_type: null, mcp_servers: null
 *   option 3 "Allow in all MCP servers" → show_in_mcp: true,  mcp_type: <str|null>, mcp_servers: null
 *   option 4 "Allow in specific servers"→ show_in_mcp: true,  mcp_type: <str|null>, mcp_servers: string[]
 *
 * mcp_type  MUST be 'tool'|'resource'|'prompt'|null — never the string "null" or undefined.
 * mcp_servers MUST be string[]|null — never undefined or [] (send null when empty).
 *
 * @since 0.1.0
 */
import { RadioControl, SelectControl, CheckboxControl, Notice } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';

const RADIO_OPTION_DEFAULT  = 'default';
const RADIO_OPTION_DISABLE  = 'disable';
const RADIO_OPTION_ALL      = 'all';
const RADIO_OPTION_SPECIFIC = 'specific';

const RADIO_OPTIONS = [
	{ value: RADIO_OPTION_DEFAULT,  label: __( 'Keep as Default', 'acrossai-abilities-manager' ) },
	{ value: RADIO_OPTION_DISABLE,  label: __( 'Disable for MCP', 'acrossai-abilities-manager' ) },
	{ value: RADIO_OPTION_ALL,      label: __( 'Allow in all MCP servers', 'acrossai-abilities-manager' ) },
	{ value: RADIO_OPTION_SPECIFIC, label: __( 'Allow in specific MCP servers', 'acrossai-abilities-manager' ) },
];

const MCP_TYPE_OPTIONS = [
	{ value: '',         label: __( '— (Inherit)', 'acrossai-abilities-manager' ) },
	{ value: 'tool',     label: __( 'Tool', 'acrossai-abilities-manager' ) },
	{ value: 'resource', label: __( 'Resource', 'acrossai-abilities-manager' ) },
	{ value: 'prompt',   label: __( 'Prompt', 'acrossai-abilities-manager' ) },
];

/**
 * Derive which radio option is active given the three stored field values.
 *
 * @param {boolean|null} showInMcp
 * @param {Array|null}   mcpServers
 * @return {string} One of the RADIO_OPTION_* constants.
 */
function toRadioOption( showInMcp, mcpServers ) {
	if ( null === showInMcp ) return RADIO_OPTION_DEFAULT;
	if ( false === showInMcp ) return RADIO_OPTION_DISABLE;
	// showInMcp === true
	if ( Array.isArray( mcpServers ) && mcpServers.length > 0 ) return RADIO_OPTION_SPECIFIC;
	return RADIO_OPTION_ALL;
}

/**
 * McpVisibilityControl component.
 *
 * @param {Object}       props
 * @param {boolean|null} props.showInMcp   Tri-state value for show_in_mcp.
 * @param {string|null}  props.mcpType     MCP type value.
 * @param {Array|null}   props.mcpServers  Currently selected server IDs (string[]) or null.
 * @param {Function}     props.onChange    Called with partial { show_in_mcp?, mcp_type?, mcp_servers? }.
 * @return {JSX.Element}
 */
export default function McpVisibilityControl( { showInMcp, mcpType, mcpServers, onChange } ) {
	const availableServers = useSelect( ( select ) => select( STORE_NAME ).getMcpServers(), [] );

	// T030 FIX: Use useState to maintain stable radio selection state.
	// Prevents radio from snapping back when user selects "Allow in specific MCP servers"
	// because onChange sends null for mcp_servers initially (no servers chosen yet).
	const [ radioSelection, setRadioSelection ] = useState( () => toRadioOption( showInMcp, mcpServers ) );

	// T030 FIX: useEffect watches for external changes (when panel opens for different ability)
	// and re-syncs radioSelection to match the new ability's values.
	useEffect( () => {
		setRadioSelection( toRadioOption( showInMcp, mcpServers ) );
	}, [ showInMcp, mcpServers ] );

	function handleRadioChange( newOption ) {
		// T030 FIX: Update local state BEFORE calling onChange so the radio doesn't snap back.
		setRadioSelection( newOption );

		switch ( newOption ) {
			case RADIO_OPTION_DEFAULT:
				onChange( { show_in_mcp: null, mcp_type: null, mcp_servers: null } );
				break;
			case RADIO_OPTION_DISABLE:
				onChange( { show_in_mcp: false, mcp_type: null, mcp_servers: null } );
				break;
			case RADIO_OPTION_ALL:
				// Keep mcp_type selection, clear servers.
				onChange( { show_in_mcp: true, mcp_type: mcpType ?? null, mcp_servers: null } );
				break;
			case RADIO_OPTION_SPECIFIC:
				// Keep mcp_type selection; keep servers if already set, else leave null (user will pick).
				onChange( {
					show_in_mcp: true,
					mcp_type:    mcpType ?? null,
					mcp_servers: Array.isArray( mcpServers ) && mcpServers.length > 0 ? mcpServers : null,
				} );
				break;
		}
	}

	const showTypeAndServers = true === showInMcp;
	// T030 FIX: Use radioSelection (local state) instead of derived radioValue to prevent snap-back.
	const showSpecificServers = RADIO_OPTION_SPECIFIC === radioSelection;

	return (
		<div className="acrossai-mcp-visibility">
			<RadioControl
				label={ __( 'MCP Visibility', 'acrossai-abilities-manager' ) }
				selected={ radioSelection }
				options={ RADIO_OPTIONS }
				onChange={ handleRadioChange }
			/>

			{ showTypeAndServers && (
				<>
					<SelectControl
						label={ __( 'MCP Type', 'acrossai-abilities-manager' ) }
						value={ mcpType || '' }
						options={ MCP_TYPE_OPTIONS }
						onChange={ ( value ) => {
							// '' means "inherit" → send null, never the string "null".
							onChange( { mcp_type: value || null } );
						} }
					/>

					{ showSpecificServers && (
						<div className="acrossai-mcp-visibility__servers">
							{ availableServers.length === 0 ? (
								<Notice status="warning" isDismissible={ false }>
									{ __( 'No MCP servers configured.', 'acrossai-abilities-manager' ) }
								</Notice>
							) : (
								<fieldset>
									<legend className="components-base-control__label">
										{ __( 'MCP Servers', 'acrossai-abilities-manager' ) }
									</legend>
									{ availableServers.map( ( server ) => {
										const selectedIds = Array.isArray( mcpServers ) ? mcpServers : [];
										return (
											<CheckboxControl
												key={ server.id }
												label={ server.name || server.id }
												checked={ selectedIds.includes( server.id ) }
												onChange={ ( checked ) => {
													const next = checked
														? [ ...selectedIds, server.id ]
														: selectedIds.filter( ( id ) => id !== server.id );
													// Send null when empty — never send [] to satisfy sanitize_mcp_servers_array.
													onChange( { mcp_servers: next.length > 0 ? next : null } );
												} }
											/>
										);
									} ) }
								</fieldset>
							) }
						</div>
					) }
				</>
			) }
		</div>
	);
}
