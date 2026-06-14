/**
 * Jest tests for Feature 033 CHANGE-B — groupBySubGroupPreservingOrder().
 *
 * Asserts the pure grouping helper named-exported from LibraryCard.js.
 * No React rendering required (PATTERN-NAMED-EXPORT-JEST).
 *
 * @since 0.1.0
 */

// Mock the WordPress packages before importing the component file (its
// module top-level imports them, but we only need the pure named export —
// mocks make the file requireable under jsdom).
jest.mock('@wordpress/components', () => ({
	Button: () => null,
	CheckboxControl: () => null,
	RadioControl: () => null,
	ToggleControl: () => null,
}));
jest.mock('@wordpress/element', () => ({
	Fragment: ({ children }) => children,
	useState: (init) => [init, () => {}],
}));
jest.mock('@wordpress/i18n', () => ({ __: (v) => v }));
jest.mock('@wordpress/icons', () => ({
	chevronDown: null,
	chevronUp: null,
}));

const {
	groupBySubGroupPreservingOrder,
} = require('../../../src/js/ability-library/components/LibraryCard');

describe('groupBySubGroupPreservingOrder', () => {
	test('input with no subGroup returns a single ungrouped bucket', () => {
		const slugs = [
			{
				slug: 'a',
				slugLabel: 'A',
				name: 'a',
				subGroup: '',
				subGroupLabel: '',
			},
			{
				slug: 'b',
				slugLabel: 'B',
				name: 'b',
				subGroup: '',
				subGroupLabel: '',
			},
			{
				slug: 'c',
				slugLabel: 'C',
				name: 'c',
				subGroup: '',
				subGroupLabel: '',
			},
		];

		const result = groupBySubGroupPreservingOrder(slugs);

		expect(result).toHaveLength(1);
		expect(result[0].subGroup).toBe('');
		expect(result[0].items.map((i) => i.slug)).toEqual(['a', 'b', 'c']);
	});

	test('ungrouped items appear before grouped items (first-seen order)', () => {
		const slugs = [
			{
				slug: 'a',
				slugLabel: 'A',
				name: 'a',
				subGroup: '',
				subGroupLabel: '',
			},
			{
				slug: 'b',
				slugLabel: 'B',
				name: 'b',
				subGroup: 'core',
				subGroupLabel: 'Core',
			},
			{
				slug: 'c',
				slugLabel: 'C',
				name: 'c',
				subGroup: 'plugins',
				subGroupLabel: 'Plugins',
			},
		];

		const result = groupBySubGroupPreservingOrder(slugs);

		expect(result.map((g) => g.subGroup)).toEqual(['', 'core', 'plugins']);
	});

	test('duplicate sub-group keys collect into the same bucket and keep first-seen position', () => {
		const slugs = [
			{
				slug: 'a',
				slugLabel: 'A',
				name: 'a',
				subGroup: 'core',
				subGroupLabel: 'Core',
			},
			{
				slug: 'b',
				slugLabel: 'B',
				name: 'b',
				subGroup: 'plugins',
				subGroupLabel: 'Plugins',
			},
			{
				slug: 'c',
				slugLabel: 'C',
				name: 'c',
				subGroup: 'core',
				subGroupLabel: 'Core',
			},
		];

		const result = groupBySubGroupPreservingOrder(slugs);

		expect(result.map((g) => g.subGroup)).toEqual(['core', 'plugins']);
		expect(result[0].items.map((i) => i.slug)).toEqual(['a', 'c']);
		expect(result[1].items.map((i) => i.slug)).toEqual(['b']);
	});

	test('every item under the same sub-group yields one bucket', () => {
		const slugs = [
			{
				slug: 'a',
				slugLabel: 'A',
				name: 'a',
				subGroup: 'core',
				subGroupLabel: 'Core',
			},
			{
				slug: 'b',
				slugLabel: 'B',
				name: 'b',
				subGroup: 'core',
				subGroupLabel: 'Core',
			},
		];

		const result = groupBySubGroupPreservingOrder(slugs);

		expect(result).toHaveLength(1);
		expect(result[0].subGroup).toBe('core');
		expect(result[0].subGroupLabel).toBe('Core');
		expect(result[0].items).toHaveLength(2);
	});

	test('empty input returns empty array', () => {
		expect(groupBySubGroupPreservingOrder([])).toEqual([]);
	});

	test('subGroupLabel is taken from the FIRST item in a group (subsequent labels ignored)', () => {
		const slugs = [
			{
				slug: 'a',
				slugLabel: 'A',
				name: 'a',
				subGroup: 'core',
				subGroupLabel: 'Core (first)',
			},
			{
				slug: 'b',
				slugLabel: 'B',
				name: 'b',
				subGroup: 'core',
				subGroupLabel: 'Core (different)',
			},
		];

		const result = groupBySubGroupPreservingOrder(slugs);

		expect(result[0].subGroupLabel).toBe('Core (first)');
	});

	test('missing subGroup property is treated as ungrouped', () => {
		const slugs = [
			{ slug: 'a', slugLabel: 'A', name: 'a' }, // no subGroup at all
			{
				slug: 'b',
				slugLabel: 'B',
				name: 'b',
				subGroup: 'core',
				subGroupLabel: 'Core',
			},
		];

		const result = groupBySubGroupPreservingOrder(slugs);

		expect(result.map((g) => g.subGroup)).toEqual(['', 'core']);
		expect(result[0].items.map((i) => i.slug)).toEqual(['a']);
	});
});
