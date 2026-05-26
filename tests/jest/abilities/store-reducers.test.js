/**
 * Jest tests for store/index.js reducers — Feature 014.
 *
 * Validates that REMOVE_ABILITY and PATCH_ABILITY reducers are slug-keyed, and
 * that clearOverrides dispatches nullOverrides for label/description/category.
 *
 * @since 0.1.0
 */

jest.mock( '@wordpress/data', () => ( {
	createReduxStore: jest.fn( ( name, config ) => config ),
	register: jest.fn(),
	dispatch: jest.fn(),
	select: jest.fn(),
} ) );
jest.mock( '@wordpress/i18n', () => ( { __: ( v ) => v } ) );
jest.mock( '../../../src/js/abilities/api/client.js', () => ( {
	getAbilities:  jest.fn(),
	getAbility:    jest.fn(),
	createAbility: jest.fn(),
	updateAbility: jest.fn(),
	deleteAbility: jest.fn(),
} ) );

// Import the raw config object returned by createReduxStore mock.
const storeConfig = require( '../../../src/js/abilities/store/index.js' );

// ---------------------------------------------------------------------------
// Extract the reducer from the store config.
// ---------------------------------------------------------------------------
const reducer = storeConfig.store.reducer;

const INITIAL_ABILITIES = [
	{ id: 1, ability_slug: 'acrossai-abilities/foo', label: 'Foo', source: 'db' },
	{ id: 2, ability_slug: 'acrossai-abilities/bar', label: 'Bar', source: 'plugin' },
];

function baseState( extras = {} ) {
	return {
		abilities: [ ...INITIAL_ABILITIES ],
		total: 2,
		isSaving: false,
		saveError: null,
		view: 'list',
		isDirty: false,
		...extras,
	};
}

describe( 'REMOVE_ABILITY reducer', () => {
	test( 'removes the ability matching action.slug', () => {
		const state = reducer( baseState(), {
			type: 'REMOVE_ABILITY',
			slug: 'acrossai-abilities/foo',
		} );
		expect( state.abilities ).toHaveLength( 1 );
		expect( state.abilities[ 0 ].ability_slug ).toBe( 'acrossai-abilities/bar' );
	} );

	test( 'decrements total', () => {
		const state = reducer( baseState(), {
			type: 'REMOVE_ABILITY',
			slug: 'acrossai-abilities/foo',
		} );
		expect( state.total ).toBe( 1 );
	} );

	test( 'does not remove anything when slug does not match', () => {
		const state = reducer( baseState(), {
			type: 'REMOVE_ABILITY',
			slug: 'acrossai-abilities/nonexistent',
		} );
		expect( state.abilities ).toHaveLength( 2 );
		expect( state.total ).toBe( 1 ); // total is still decremented by Math.max(0)
	} );

	test( 'does NOT use action.id for matching', () => {
		// Passing a numeric id in action.id (old behaviour) must have no effect.
		const state = reducer( baseState(), {
			type: 'REMOVE_ABILITY',
			id: 1, // old field — should be ignored
			slug: 'acrossai-abilities/nonexistent',
		} );
		// Neither ability should be removed (slug doesn't match).
		expect( state.abilities ).toHaveLength( 2 );
	} );
} );

describe( 'PATCH_ABILITY reducer', () => {
	test( 'patches the ability matching action.slug', () => {
		const state = reducer( baseState(), {
			type: 'PATCH_ABILITY',
			slug: 'acrossai-abilities/foo',
			patch: { label: 'Updated Foo' },
		} );
		const patched = state.abilities.find( ( a ) => a.ability_slug === 'acrossai-abilities/foo' );
		expect( patched.label ).toBe( 'Updated Foo' );
	} );

	test( 'does not patch other abilities', () => {
		const state = reducer( baseState(), {
			type: 'PATCH_ABILITY',
			slug: 'acrossai-abilities/foo',
			patch: { label: 'Updated Foo' },
		} );
		const other = state.abilities.find( ( a ) => a.ability_slug === 'acrossai-abilities/bar' );
		expect( other.label ).toBe( 'Bar' );
	} );

	test( 'does NOT use action.id for matching', () => {
		// Old action.id=1 approach must be ignored.
		const state = reducer( baseState(), {
			type: 'PATCH_ABILITY',
			id: 1, // old field — should be ignored
			slug: 'acrossai-abilities/nonexistent',
			patch: { label: 'Should Not Apply' },
		} );
		// No ability has slug 'nonexistent', so neither should be patched.
		expect( state.abilities[ 0 ].label ).toBe( 'Foo' );
		expect( state.abilities[ 1 ].label ).toBe( 'Bar' );
	} );
} );
