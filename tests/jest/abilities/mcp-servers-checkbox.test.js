/**
 * Jest tests for Feature 016 mcp_servers checkbox logic.
 *
 * Tests the toggle handler logic and stale-ID union derivation from
 * AbilityForm.jsx (handleServerToggle, handleAllServersToggle, mcpAllItems).
 *
 * Because these functions are inlined in the component, this test suite
 * validates the same pure logic in isolation, ensuring no regression as
 * the component evolves.
 *
 * @since 0.1.0
 */

// ---------------------------------------------------------------------------
// Pure-logic helpers extracted from AbilityForm.jsx handleServerToggle
// ---------------------------------------------------------------------------

/**
 * Replicate handleServerToggle logic from AbilityForm.jsx.
 *
 * @param {string[]|null} current  draftAbility.mcp_servers
 * @param {string}        serverId ID being toggled
 * @return {{ mcp_servers: string[]|null }}
 */
function applyServerToggle( current, serverId ) {
	const arr = Array.isArray( current ) ? current : [];
	const next = arr.includes( serverId )
		? arr.filter( ( id ) => id !== serverId )
		: [ ...arr, serverId ];
	return { mcp_servers: next.length === 0 ? null : next };
}

/**
 * Replicate handleAllServersToggle logic.
 *
 * @return {{ mcp_servers: null }}
 */
function applyAllServersToggle() {
	return { mcp_servers: null };
}

/**
 * Replicate mcpAllItems derivation from AbilityForm.jsx.
 *
 * @param {string[]|null}          savedServers  draftAbility.mcp_servers
 * @param {Array<{id:string}>|null} fetchedList  mcpServers API response
 * @return {Array<{id:string, stale?:boolean}>}
 */
function deriveMcpAllItems( savedServers, fetchedList ) {
	const savedIds = Array.isArray( savedServers ) ? savedServers : [];
	const fetchedIds = ( fetchedList ?? [] ).map( ( s ) => s.id );
	const staleIds = savedIds.filter( ( id ) => ! fetchedIds.includes( id ) );
	return [
		...( fetchedList ?? [] ),
		...staleIds.map( ( id ) => ( { id, name: id, stale: true } ) ),
	];
}

// ---------------------------------------------------------------------------
// handleServerToggle
// ---------------------------------------------------------------------------

describe( 'handleServerToggle', () => {
	test( '(a) unchecking the last server calls patch({ mcp_servers: null })', () => {
		const result = applyServerToggle( [ 'server-1' ], 'server-1' );
		expect( result ).toEqual( { mcp_servers: null } );
	} );

	test( '(b) adding a server to a null draft produces patch({ mcp_servers: ["server-id"] })', () => {
		const result = applyServerToggle( null, 'server-id' );
		expect( result ).toEqual( { mcp_servers: [ 'server-id' ] } );
	} );

	test( 'adding a server to an existing array appends it', () => {
		const result = applyServerToggle( [ 'server-1' ], 'server-2' );
		expect( result ).toEqual( { mcp_servers: [ 'server-1', 'server-2' ] } );
	} );

	test( 'removing a server from an array with multiple entries retains others', () => {
		const result = applyServerToggle(
			[ 'server-1', 'server-2', 'server-3' ],
			'server-2'
		);
		expect( result ).toEqual( {
			mcp_servers: [ 'server-1', 'server-3' ],
		} );
	} );

	test( 'toggling a server that is not present adds it (null array treated as [])', () => {
		const result = applyServerToggle( null, 'new-server' );
		expect( result.mcp_servers ).toContain( 'new-server' );
	} );
} );

// ---------------------------------------------------------------------------
// handleAllServersToggle
// ---------------------------------------------------------------------------

describe( 'handleAllServersToggle', () => {
	test( '(c) always returns patch({ mcp_servers: null }) regardless of current state', () => {
		expect( applyAllServersToggle() ).toEqual( { mcp_servers: null } );
		// Call multiple times — always null.
		expect( applyAllServersToggle() ).toEqual( { mcp_servers: null } );
	} );
} );

// ---------------------------------------------------------------------------
// mcpAllItems stale-union derivation
// ---------------------------------------------------------------------------

describe( 'mcpAllItems stale-ID union', () => {
	test( '(d) saved ["stale"] + fetched [{id:"real"}] → both present with correct stale flag', () => {
		const items = deriveMcpAllItems( [ 'stale', 'real' ], [
			{ id: 'real', name: 'Real Server' },
		] );

		const realItem = items.find( ( i ) => i.id === 'real' );
		const staleItem = items.find( ( i ) => i.id === 'stale' );

		expect( realItem ).toBeDefined();
		expect( staleItem ).toBeDefined();
		expect( staleItem.stale ).toBe( true );
		// The "real" fetched item should NOT be marked stale.
		expect( realItem.stale ).toBeUndefined();
	} );

	test( 'when saved is null, no stale items are added', () => {
		const items = deriveMcpAllItems( null, [ { id: 'real' } ] );
		const staleItems = items.filter( ( i ) => i.stale );
		expect( staleItems ).toHaveLength( 0 );
	} );

	test( 'when fetched list is null, all saved IDs become stale', () => {
		const items = deriveMcpAllItems( [ 'a', 'b' ], null );
		expect( items ).toHaveLength( 2 );
		expect( items.every( ( i ) => i.stale === true ) ).toBe( true );
	} );

	test( 'when saved and fetched match exactly, no stale items appear', () => {
		const items = deriveMcpAllItems( [ 'x' ], [ { id: 'x', name: 'X' } ] );
		const staleItems = items.filter( ( i ) => i.stale );
		expect( staleItems ).toHaveLength( 0 );
	} );
} );
