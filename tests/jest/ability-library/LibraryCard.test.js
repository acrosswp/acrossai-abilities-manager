/**
 * Jest tests for Feature 033 CHANGE-A — Library card visibility contract.
 *
 * Replicates the pure-logic predicates from LibraryCard.js (the slug panel
 * visibility guard, the panel-row mode predicate, and the sub_keys reset on
 * mode change) so they can be asserted without rendering React. The JSX in
 * src/js/ability-library/components/LibraryCard.js must use the identical
 * boolean expressions.
 *
 * @since 0.1.0
 */

/**
 * The slug panel render predicate (revised in turn 3 of Feature 033).
 *
 * Mirrors the JSX guard at LibraryCard.js:
 *   {enabled && slugs.length > 0 && expanded && (...)}
 *
 * Panel renders for BOTH modes (mode === 'all' shows read-only rows;
 * mode === 'specific' shows interactive checkboxes), AND only when the
 * per-card disclosure button has the expanded state. Default for a freshly
 * mounted card is expanded=true.
 *
 * @param {boolean} enabled  Card master toggle.
 * @param {number}  slugsLen Number of registered slugs in the category.
 * @param {boolean} expanded Per-card disclosure state.
 * @return {boolean}
 */
function shouldRenderSlugPanel(enabled, slugsLen, expanded) {
	return enabled && slugsLen > 0 && expanded;
}

/**
 * The disclosure button visibility predicate.
 *
 * Mirrors the JSX guard at LibraryCard.js:
 *   {canExpand && <Button … />}
 *   where canExpand = enabled && slugs.length > 0
 *
 * @param {boolean} enabled  Card master toggle.
 * @param {number}  slugsLen Number of registered slugs.
 * @return {boolean}
 */
function shouldShowDisclosureButton(enabled, slugsLen) {
	return enabled && slugsLen > 0;
}

/**
 * The per-row interactive-vs-readonly mode predicate.
 *
 * Mirrors the JSX ternary at LibraryCard.js:
 *   mode === 'specific' ? <CheckboxControl /> : <div className="…__slug-readonly" />
 *
 * @param {string} mode 'all' or 'specific'.
 * @return {boolean} true when the row should render as an interactive checkbox.
 */
function shouldRenderInteractiveRows(mode) {
	return mode === 'specific';
}

/**
 * The sub_keys reset rule on radio change.
 *
 * Mirrors the JSX onChange at LibraryCard.js:
 *   sub_keys: value === 'all' ? {} : slugsConfig
 *
 * @param {string} newMode     The mode the user just selected.
 * @param {Object} prevSubKeys The existing sub_keys map.
 * @return {Object}
 */
function nextSubKeysOnModeChange(newMode, prevSubKeys) {
	return newMode === 'all' ? {} : prevSubKeys;
}

describe('shouldRenderSlugPanel — Feature 033 visibility contract (turn 3)', () => {
	test('panel renders when enabled + slugs > 0 + expanded', () => {
		expect(shouldRenderSlugPanel(true, 5, true)).toBe(true);
	});

	test('panel does NOT render when the disclosure is collapsed', () => {
		// Turn 3 — per-card disclosure adds the expanded gate.
		expect(shouldRenderSlugPanel(true, 5, false)).toBe(false);
	});

	test('panel does NOT render when slugs is 0 (empty category)', () => {
		expect(shouldRenderSlugPanel(true, 0, true)).toBe(false);
	});

	test('panel does NOT render when enabled is false (whole card body collapses)', () => {
		expect(shouldRenderSlugPanel(false, 5, true)).toBe(false);
		expect(shouldRenderSlugPanel(false, 0, true)).toBe(false);
	});
});

describe('shouldShowDisclosureButton — chevron presence predicate', () => {
	test('button shown when enabled + slugs > 0', () => {
		expect(shouldShowDisclosureButton(true, 5)).toBe(true);
	});

	test('button hidden when no slugs registered (no content to disclose)', () => {
		expect(shouldShowDisclosureButton(true, 0)).toBe(false);
	});

	test('button hidden when card is disabled', () => {
		expect(shouldShowDisclosureButton(false, 5)).toBe(false);
	});
});

describe('shouldRenderInteractiveRows — per-row mode predicate', () => {
	test('mode "specific" → interactive CheckboxControl rows', () => {
		expect(shouldRenderInteractiveRows('specific')).toBe(true);
	});

	test('mode "all" → read-only label rows (no checkboxes)', () => {
		expect(shouldRenderInteractiveRows('all')).toBe(false);
	});

	test('unexpected mode falls back to read-only', () => {
		expect(shouldRenderInteractiveRows('something-else')).toBe(false);
	});
});

describe('nextSubKeysOnModeChange — sub_keys reset on radio switch', () => {
	test('switching to "all" clears the existing selection map', () => {
		const prev = { 'plugin-a/ability-1': true, 'plugin-a/ability-2': true };
		expect(nextSubKeysOnModeChange('all', prev)).toEqual({});
	});

	test('switching to "specific" preserves the existing selection map', () => {
		const prev = { 'plugin-a/ability-1': true };
		expect(nextSubKeysOnModeChange('specific', prev)).toBe(prev);
	});

	test('switching to "all" from an already-empty map stays {}', () => {
		expect(nextSubKeysOnModeChange('all', {})).toEqual({});
	});
});
