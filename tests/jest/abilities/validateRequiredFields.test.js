/**
 * Jest tests for validateRequiredFields — Feature 013.
 *
 * Validates the pure helper that returns an error-string map for the four
 * required fields (slug_suffix, label, description, category) in create/edit
 * mode. Empty string in the map means "no error".
 *
 * @since 0.1.0
 */

jest.mock( '@wordpress/i18n', () => ( {
	__: ( value ) => value,
} ) );

// Heavy component deps — not needed for this pure function.
jest.mock( '@wordpress/data', () => ( {} ) );
jest.mock( '@wordpress/element', () => ( {
	useState: jest.fn(),
	useEffect: jest.fn(),
	useRef: jest.fn(),
	createElement: jest.fn(),
} ) );
jest.mock( '@wordpress/components', () => ( {} ) );
jest.mock( '@wordpress/api-fetch', () => jest.fn() );

const { validateRequiredFields } = require( '../../../src/js/abilities/components/AbilityForm.jsx' );

const REQUIRED = 'This field is required.';
const ALL_FILLED = { label: 'My Label', description: 'A description', category: 'general' };
const VALID_SLUG = 'my-ability';

describe( 'validateRequiredFields', () => {
	// -------------------------------------------------------------------------
	// All fields valid — no errors
	// -------------------------------------------------------------------------

	test( 'returns all empty strings when all four fields are filled', () => {
		const result = validateRequiredFields( ALL_FILLED, VALID_SLUG );
		expect( result.slug_suffix ).toBe( '' );
		expect( result.label ).toBe( '' );
		expect( result.description ).toBe( '' );
		expect( result.category ).toBe( '' );
	} );

	// -------------------------------------------------------------------------
	// slug_suffix
	// -------------------------------------------------------------------------

	test( 'returns REQUIRED for slug_suffix when empty string', () => {
		const result = validateRequiredFields( ALL_FILLED, '' );
		expect( result.slug_suffix ).toBe( REQUIRED );
	} );

	test( 'returns REQUIRED for slug_suffix when whitespace-only', () => {
		const result = validateRequiredFields( ALL_FILLED, '   ' );
		expect( result.slug_suffix ).toBe( REQUIRED );
	} );

	test( 'returns REQUIRED for slug_suffix when undefined', () => {
		const result = validateRequiredFields( ALL_FILLED, undefined );
		expect( result.slug_suffix ).toBe( REQUIRED );
	} );

	test( 'returns empty string for slug_suffix when trimmed value is non-empty', () => {
		const result = validateRequiredFields( ALL_FILLED, '  my-slug  ' );
		expect( result.slug_suffix ).toBe( '' );
	} );

	// -------------------------------------------------------------------------
	// label
	// -------------------------------------------------------------------------

	test( 'returns REQUIRED for label when missing from ability object', () => {
		const result = validateRequiredFields( { ...ALL_FILLED, label: '' }, VALID_SLUG );
		expect( result.label ).toBe( REQUIRED );
	} );

	test( 'returns REQUIRED for label when whitespace-only', () => {
		const result = validateRequiredFields( { ...ALL_FILLED, label: '   ' }, VALID_SLUG );
		expect( result.label ).toBe( REQUIRED );
	} );

	test( 'returns REQUIRED for label when null', () => {
		const result = validateRequiredFields( { ...ALL_FILLED, label: null }, VALID_SLUG );
		expect( result.label ).toBe( REQUIRED );
	} );

	// -------------------------------------------------------------------------
	// description
	// -------------------------------------------------------------------------

	test( 'returns REQUIRED for description when empty string', () => {
		const result = validateRequiredFields( { ...ALL_FILLED, description: '' }, VALID_SLUG );
		expect( result.description ).toBe( REQUIRED );
	} );

	test( 'returns REQUIRED for description when whitespace-only', () => {
		const result = validateRequiredFields( { ...ALL_FILLED, description: '\t' }, VALID_SLUG );
		expect( result.description ).toBe( REQUIRED );
	} );

	test( 'returns REQUIRED for description when undefined', () => {
		const result = validateRequiredFields( { ...ALL_FILLED, description: undefined }, VALID_SLUG );
		expect( result.description ).toBe( REQUIRED );
	} );

	// -------------------------------------------------------------------------
	// category
	// -------------------------------------------------------------------------

	test( 'returns REQUIRED for category when empty string', () => {
		const result = validateRequiredFields( { ...ALL_FILLED, category: '' }, VALID_SLUG );
		expect( result.category ).toBe( REQUIRED );
	} );

	test( 'returns REQUIRED for category when whitespace-only', () => {
		const result = validateRequiredFields( { ...ALL_FILLED, category: '  ' }, VALID_SLUG );
		expect( result.category ).toBe( REQUIRED );
	} );

	// -------------------------------------------------------------------------
	// Multiple fields empty
	// -------------------------------------------------------------------------

	test( 'returns REQUIRED for all four fields when all are empty', () => {
		const result = validateRequiredFields(
			{ label: '', description: '', category: '' },
			''
		);
		expect( result.slug_suffix ).toBe( REQUIRED );
		expect( result.label ).toBe( REQUIRED );
		expect( result.description ).toBe( REQUIRED );
		expect( result.category ).toBe( REQUIRED );
	} );

	test( 'returns errors only for empty fields, not filled ones', () => {
		const result = validateRequiredFields(
			{ label: 'Filled', description: '', category: 'general' },
			''
		);
		expect( result.slug_suffix ).toBe( REQUIRED );
		expect( result.label ).toBe( '' );
		expect( result.description ).toBe( REQUIRED );
		expect( result.category ).toBe( '' );
	} );

	// -------------------------------------------------------------------------
	// Return shape
	// -------------------------------------------------------------------------

	test( 'always returns an object with exactly the four expected keys', () => {
		const result = validateRequiredFields( ALL_FILLED, VALID_SLUG );
		expect( Object.keys( result ).sort() ).toEqual(
			[ 'category', 'description', 'label', 'slug_suffix' ]
		);
	} );
} );
