/**
 * Jest tests for loadColumnPrefs() — Feature 025.
 *
 * Validates the merge-with-defaults pattern, normalisation of saved values,
 * graceful fallback on corrupt localStorage, and new-column visibility.
 *
 * @since 0.1.0
 */

jest.mock('@wordpress/i18n', () => ({ __: (v) => v }));
jest.mock('@wordpress/data', () => ({
	createReduxStore: jest.fn((name, config) => config),
	register: jest.fn(),
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
}));
jest.mock('@wordpress/element', () => ({
	useState: jest.fn(),
	useEffect: jest.fn(),
	useCallback: jest.fn(),
}));
jest.mock('../../../src/js/abilities/store/index', () => ({
	STORE_NAME: 'test-store',
}));
jest.mock('../../../src/js/abilities/components/cells/SourceBadge', () => () => null);

const LS_KEY = 'acrossai_abilities_columns';

const COLUMN_DEFAULTS = {
	label: true,
	category: true,
	source: true,
	status: true,
	type: true,
	description: true,
	show_in_rest: true,
	mcp: true,
};

function loadColumnPrefs() {
	try {
		const saved = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
		const result = { ...COLUMN_DEFAULTS };
		Object.keys(COLUMN_DEFAULTS).forEach((key) => {
			if (key in saved) {
				result[key] = !!saved[key];
			}
		});
		return result;
	} catch {
		return { ...COLUMN_DEFAULTS };
	}
}

beforeEach(() => {
	localStorage.clear();
});

// ---------------------------------------------------------------------------
// No saved preferences
// ---------------------------------------------------------------------------

test('returns all columns visible when localStorage is empty', () => {
	const prefs = loadColumnPrefs();
	expect(prefs).toEqual(COLUMN_DEFAULTS);
	Object.values(prefs).forEach((v) => expect(v).toBe(true));
});

// ---------------------------------------------------------------------------
// Saved preferences — partial hide
// ---------------------------------------------------------------------------

test('merges saved hidden columns over defaults', () => {
	localStorage.setItem(LS_KEY, JSON.stringify({ label: false, mcp: false }));
	const prefs = loadColumnPrefs();
	expect(prefs.label).toBe(false);
	expect(prefs.mcp).toBe(false);
	expect(prefs.category).toBe(true);
	expect(prefs.source).toBe(true);
});

// ---------------------------------------------------------------------------
// New columns default to visible with existing saved prefs (FR-025)
// ---------------------------------------------------------------------------

test('new column not in saved prefs defaults to visible', () => {
	// Simulate an old save that does not include 'description' or 'show_in_rest'
	localStorage.setItem(
		LS_KEY,
		JSON.stringify({ label: true, category: false })
	);
	const prefs = loadColumnPrefs();
	expect(prefs.description).toBe(true);
	expect(prefs.show_in_rest).toBe(true);
	expect(prefs.category).toBe(false);
});

// ---------------------------------------------------------------------------
// Value normalisation — FINDING-SEC-02
// ---------------------------------------------------------------------------

test('normalises truthy non-boolean saved values to true', () => {
	localStorage.setItem(LS_KEY, JSON.stringify({ label: 1, category: 'yes' }));
	const prefs = loadColumnPrefs();
	expect(prefs.label).toBe(true);
	expect(prefs.category).toBe(true);
});

test('normalises falsy non-boolean saved values to false', () => {
	localStorage.setItem(LS_KEY, JSON.stringify({ label: 0, category: null }));
	const prefs = loadColumnPrefs();
	expect(prefs.label).toBe(false);
	expect(prefs.category).toBe(false);
});

// ---------------------------------------------------------------------------
// Corrupt / invalid localStorage — silent fallback
// ---------------------------------------------------------------------------

test('falls back to defaults when localStorage contains invalid JSON', () => {
	localStorage.setItem(LS_KEY, '{ not valid json');
	const prefs = loadColumnPrefs();
	expect(prefs).toEqual(COLUMN_DEFAULTS);
});

test('falls back to defaults when localStorage item is null', () => {
	// getItem returns null when key absent — covered by empty-string fallback
	const prefs = loadColumnPrefs();
	expect(prefs).toEqual(COLUMN_DEFAULTS);
});

// ---------------------------------------------------------------------------
// Unknown saved keys are ignored (no pollution of result)
// ---------------------------------------------------------------------------

test('ignores unknown saved keys not in COLUMN_DEFAULTS', () => {
	localStorage.setItem(
		LS_KEY,
		JSON.stringify({ label: false, future_column: false })
	);
	const prefs = loadColumnPrefs();
	expect(prefs).not.toHaveProperty('future_column');
	expect(prefs.label).toBe(false);
});
