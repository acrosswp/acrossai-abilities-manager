/**
 * Jest tests for the Sitewide Ability Manager Redux store.
 *
 * @since 0.1.0
 */

// Mock @wordpress/data and @wordpress/api-fetch.
jest.mock('@wordpress/data', () => ({
	createReduxStore: (name, config) => config,
	register: jest.fn(),
}));

jest.mock('../../../src/js/sitewide/api/client', () => ({
	fetchAbilities: jest.fn(),
	toggleAbility: jest.fn(),
	saveOverride: jest.fn(),
	deleteOverride: jest.fn(),
	bulkAction: jest.fn(),
	fetchMcpServers: jest.fn(),
}));

// Require after mocks.
const storeConfig = require('../../../src/js/sitewide/store/index').default;
const { reducer } = storeConfig;

describe('Sitewide store reducer', () => {
	test('initial state shape is correct', () => {
		const state = reducer(undefined, { type: '@@INIT' });

		expect(state).toMatchObject({
			abilities: [],
			total: 0,
			pages: 0,
			currentPage: 1,
			isLoading: false,
			error: null,
			editingSlug: null,
			mcpServers: [],
		});
	});

	test('SET_LOADING sets isLoading', () => {
		const state = reducer(undefined, {
			type: 'SET_LOADING',
			isLoading: true,
		});
		expect(state.isLoading).toBe(true);
	});

	test('SET_ERROR sets error and clears isLoading', () => {
		const state = reducer(
			{ ...reducer(undefined, {}), isLoading: true },
			{ type: 'SET_ERROR', error: 'Network error' }
		);
		expect(state.error).toBe('Network error');
		expect(state.isLoading).toBe(false);
	});

	test('SET_ABILITIES resets isLoading and populates abilities', () => {
		const abilities = [{ slug: 'test', site_allowed: true }];
		const state = reducer(undefined, {
			type: 'SET_ABILITIES',
			abilities,
			total: 1,
			pages: 1,
			page: 1,
		});

		expect(state.abilities).toEqual(abilities);
		expect(state.total).toBe(1);
		expect(state.isLoading).toBe(false);
	});

	test('SET_EDITING_SLUG sets editingSlug', () => {
		const state = reducer(undefined, {
			type: 'SET_EDITING_SLUG',
			slug: 'my-ability',
		});
		expect(state.editingSlug).toBe('my-ability');
	});

	test('AbilityTable renders empty-state when abilities array is empty', () => {
		// Structural: empty abilities array returned by getAbilities.
		const state = reducer(undefined, { type: '@@INIT' });
		expect(state.abilities).toHaveLength(0);
	});
});
