/* global document */
document.addEventListener( 'DOMContentLoaded', function () {
	var mcpPublic  = document.getElementById( 'aam-mcp-public' );
	var mcpTypeRow = document.getElementById( 'aam-mcp-type-row' );

	if ( ! mcpPublic || ! mcpTypeRow ) {
		return;
	}

	function syncMcpTypeVisibility() {
		mcpTypeRow.style.display = mcpPublic.checked ? '' : 'none';
	}

	mcpPublic.addEventListener( 'change', syncMcpTypeVisibility );
	syncMcpTypeVisibility();
} );
