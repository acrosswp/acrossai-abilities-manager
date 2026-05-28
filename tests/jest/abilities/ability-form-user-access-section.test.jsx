/**
 * Jest tests for Feature 018 — Section 5 "User Access" in AbilityForm.jsx.
 *
 * Validates three rendering branches:
 *  (a) create mode       → placeholder paragraph, no AccessControl component
 *  (b) edit mode, access_control_available=false → warning notice, no AccessControl
 *  (c) edit mode, ability slug present, access_control_available=true
 *                        → AccessControl mounted with correct props; no onSave prop
 *
 * @since 0.2.0
 */
import { act, createRoot } from '@wordpress/element';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

// ---------------------------------------------------------------------------
// Mutable test fixtures (prefixed "mock" for babel-jest hoist compatibility)
// ---------------------------------------------------------------------------

let mockSavedAbility = null;

const mockDraftAbility = {
	ability_slug: '',
	label: 'Test Ability',
	description: 'A test ability description.',
	category: 'test-category',
	source: 'db',
	status: 'active',
	callback_type: 'noop',
	callback_config: {},
	show_in_mcp: false,
	show_in_rest: false,
	mcp_type: null,
	mcp_servers: null,
	site_allowed: null,
	readonly: null,
	destructive: null,
	idempotent: null,
	input_schema: null,
	output_schema: null,
};

const mockDispatch = {
	fetchCategories: jest.fn(),
	clearDraft: jest.fn(),
	setSaved: jest.fn(),
	fetchAbility: jest.fn(),
	updateDraft: jest.fn(),
	createAbility: jest.fn(),
	updateAbility: jest.fn(),
	deleteAbility: jest.fn(),
	clearOverrides: jest.fn(),
	setView: jest.fn(),
};

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( fn ) =>
		fn( () => ( {
			getSavedAbility: () => mockSavedAbility,
			getDraftAbility: () => mockDraftAbility,
			getIsDirty: () => false,
			getIsSaving: () => false,
			getSaveError: () => null,
			getCategories: () => [],
		} ) ),
	useDispatch: () => mockDispatch,
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( value ) => value,
} ) );

jest.mock(
	'@wordpress/api-fetch',
	() => jest.fn( () => Promise.resolve( { adapter_available: true, servers: [] } ) ),
	{ virtual: true }
);

jest.mock( '@wordpress/element', () => {
	const actual = jest.requireActual( '@wordpress/element' );
	return {
		...actual,
		// `act` is exported from `react` but not from `@wordpress/element` (v6+).
		act: jest.requireActual( 'react' ).act,
		createPortal: ( element ) => element,
	};
} );

jest.mock( '../../../src/js/abilities/store/index', () => ( {
	STORE_NAME: 'acrossai/abilities',
} ) );

jest.mock(
	'../../../src/js/abilities/components/cells/SourceBadge',
	() => () => null
);

jest.mock(
	'../../../src/js/abilities/components/CallbackConfigField',
	() => () => null
);

// AccessControl stub — renders data attributes for prop inspection.
jest.mock(
	'@wpb/access-control',
	() => {
		const { createElement } = jest.requireActual( '@wordpress/element' );

		return {
			AccessControl: ( props ) =>
				createElement( 'div', {
					'data-testid': 'mock-access-control',
					'data-namespace': props.namespace,
					'data-resource-key': props.resourceKey,
					'data-rest-api-root': props.restApiRoot,
					'data-nonce': props.nonce,
					'data-has-on-save': String( props.onSave !== undefined ),
					'data-hide-header': String( props.hideHeader ),
					'data-hide-save-button': String( props.hideSaveButton ),
				} ),
		};
	},
	{ virtual: true }
);

// ---------------------------------------------------------------------------
// Set window config BEFORE requiring AbilityForm — the module reads
// `window.acrossaiAbilitiesManager` at module evaluation time.
// ---------------------------------------------------------------------------

globalThis.acrossaiAbilitiesManager = {
	nonce: 'nonce-test-018',
	rest_url: 'https://example.com/wp-json',
	rest_namespace: 'acrossai-abilities-manager/v1',
	current_user_id: 1,
	access_control_available: true,
};

const AbilityForm =
	require( '../../../src/js/abilities/components/AbilityForm' ).default;

// ---------------------------------------------------------------------------
// Test suite
// ---------------------------------------------------------------------------

describe( 'AbilityForm Section 5 (User Access) rendering branches', () => {
	let container;
	let root;

	/**
	 * Returns the Section 5 ".sect" DOM element by matching sect-num "5".
	 * Scopes all assertions to Section 5 only — avoids false matches from
	 * other sections that also render <p className="desc"> (e.g. Section 3).
	 */
	const getSection5 = () =>
		Array.from( container.querySelectorAll( '.sect' ) ).find(
			( sect ) =>
				sect.querySelector( '.sect-num' )?.textContent.trim() === '5'
		);

	const renderForm = async ( mode, savedAbility = null ) => {
		mockSavedAbility = savedAbility;
		await act( async () => {
			root.render(
				<AbilityForm
					mode={ mode }
					slug={ savedAbility?.ability_slug ?? null }
					initialAbility={ savedAbility }
				/>
			);
		} );
	};

	beforeEach( () => {
		// Reset window config to defaults (access_control_available = true).
		globalThis.acrossaiAbilitiesManager.access_control_available = true;

		// Reset dispatch spies.
		Object.values( mockDispatch ).forEach( ( fn ) => fn.mockReset() );

		// Create DOM container.
		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( async () => {
		if ( root ) {
			await act( async () => {
				root.unmount();
			} );
		}
		container.remove();
	} );

	// -----------------------------------------------------------------------
	// Branch (a) — create mode: placeholder paragraph, no AccessControl
	// -----------------------------------------------------------------------
	test( '(a) create mode: renders save-first placeholder, no AccessControl', async () => {
		await renderForm( 'create', null );

		const sect5 = getSection5();
		expect( sect5 ).not.toBeUndefined();

		// Placeholder paragraph must be present within Section 5.
		const placeholder = sect5.querySelector( 'p.desc' );
		expect( placeholder ).not.toBeNull();
		expect( placeholder.textContent ).toContain(
			'Save this ability first to configure user access.'
		);

		// AccessControl must NOT be rendered within Section 5.
		expect(
			sect5.querySelector( '[data-testid="mock-access-control"]' )
		).toBeNull();
	} );

	// -----------------------------------------------------------------------
	// Branch (b) — edit mode, access_control_available=false: warning notice
	// -----------------------------------------------------------------------
	test( '(b) edit mode, library unavailable: renders warning notice, no AccessControl', async () => {
		globalThis.acrossaiAbilitiesManager.access_control_available = false;

		const savedAbility = {
			ability_slug: 'acrossai-abilities/test-ability',
			source: 'db',
			label: 'Test Ability',
			description: 'A test ability.',
			category: 'test',
			status: 'active',
			callback_type: 'noop',
			callback_config: {},
			show_in_mcp: false,
			show_in_rest: false,
			mcp_type: null,
			mcp_servers: null,
			site_allowed: null,
			readonly: null,
			destructive: null,
			idempotent: null,
			input_schema: null,
			output_schema: null,
			created_at: null,
			updated_at: null,
			_override: {},
			_registry: null,
			has_override: false,
		};

		await renderForm( 'edit', savedAbility );

		const sect5 = getSection5();
		expect( sect5 ).not.toBeUndefined();

		// Warning notice must be present within Section 5.
		const warning = sect5.querySelector( 'p.notice-warning' );
		expect( warning ).not.toBeNull();
		expect( warning.textContent ).toContain(
			'User Access is inactive'
		);

		// AccessControl must NOT be rendered within Section 5.
		expect(
			sect5.querySelector( '[data-testid="mock-access-control"]' )
		).toBeNull();
	} );

	// -----------------------------------------------------------------------
	// Branch (c) — edit mode, library available: AccessControl with correct props
	// -----------------------------------------------------------------------
	test( '(c) edit mode, library available: mounts AccessControl with correct props, no onSave', async () => {
		const savedAbility = {
			ability_slug: 'acrossai-abilities/test-ability',
			source: 'db',
			label: 'Test Ability',
			description: 'A test ability.',
			category: 'test',
			status: 'active',
			callback_type: 'noop',
			callback_config: {},
			show_in_mcp: false,
			show_in_rest: false,
			mcp_type: null,
			mcp_servers: null,
			site_allowed: null,
			readonly: null,
			destructive: null,
			idempotent: null,
			input_schema: null,
			output_schema: null,
			created_at: null,
			updated_at: null,
			_override: {},
			_registry: null,
			has_override: false,
		};

		await renderForm( 'edit', savedAbility );

		const sect5 = getSection5();
		expect( sect5 ).not.toBeUndefined();

		const ac = sect5.querySelector( '[data-testid="mock-access-control"]' );

		// AccessControl must be present within Section 5.
		expect( ac ).not.toBeNull();

		// Required props.
		expect( ac.getAttribute( 'data-namespace' ) ).toBe( 'acrossai-abilities' );
		expect( ac.getAttribute( 'data-resource-key' ) ).toBe(
			'acrossai-abilities/test-ability'
		);
		expect( ac.getAttribute( 'data-rest-api-root' ) ).toBe(
			'https://example.com/wp-json'
		);
		expect( ac.getAttribute( 'data-nonce' ) ).toBe( 'nonce-test-018' );

		// hideSaveButton=true and hideHeader=true (FR-003).
		expect( ac.getAttribute( 'data-hide-save-button' ) ).toBe( 'true' );
		expect( ac.getAttribute( 'data-hide-header' ) ).toBe( 'true' );

		// onSave must NOT be passed (FR-003 / FR-010).
		expect( ac.getAttribute( 'data-has-on-save' ) ).toBe( 'false' );

		// No Section-5 placeholder or warning in this branch.
		expect( sect5.querySelector( 'p.desc' ) ).toBeNull();
		expect( sect5.querySelector( 'p.notice-warning' ) ).toBeNull();
	} );
} );
