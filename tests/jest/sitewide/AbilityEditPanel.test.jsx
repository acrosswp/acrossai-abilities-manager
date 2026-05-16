import { act, createRoot } from '@wordpress/element';

const mockDispatch = {
	saveOverride: jest.fn(),
	closeEditPanel: jest.fn(),
};
const mockAccessControlMounts = [];
const mockAccessControlUnmounts = [];

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => mockDispatch,
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( value ) => value,
} ) );

jest.mock( '../../../src/js/sitewide/store/index', () => ( {
	STORE_NAME: 'acrossai/sitewide',
} ) );

jest.mock( '@wordpress/element', () => {
	const actual = jest.requireActual( '@wordpress/element' );
	return {
		...actual,
		createPortal: ( element ) => element,
	};
} );

jest.mock( '@wordpress/components', () => {
	const { createElement, useState } = jest.requireActual( '@wordpress/element' );

	return {
		Button: ( { children, label, onClick, className } ) =>
			createElement( 'button', { type: 'button', onClick, className }, children || label ),
		Notice: ( { children } ) => createElement( 'div', { role: 'status' }, children ),
		RadioControl: ( { label, selected } ) =>
			createElement( 'div', null, `${ label }:${ selected }` ),
		TabPanel: ( { tabs, children } ) => {
			const [ activeTab, setActiveTab ] = useState( tabs[ 0 ] );
			return createElement(
				'div',
				null,
				tabs.map( ( tab ) =>
					createElement(
						'button',
						{
							key: tab.name,
							type: 'button',
							onClick: () => setActiveTab( tab ),
						},
						tab.title
					)
				),
				children( activeTab )
			);
		},
	};
} );

jest.mock( '../../../src/js/sitewide/components/McpVisibilityControl', () => () => <div>MCP Control</div> );

jest.mock(
	'@wpb/access-control',
	() => {
		const { createElement, useEffect } = jest.requireActual( '@wordpress/element' );

		return function MockAccessControl( props ) {
			useEffect( () => {
				mockAccessControlMounts.push( props.resourceKey );
				return () => mockAccessControlUnmounts.push( props.resourceKey );
			}, [] );

			return createElement(
				'div',
				{
					'data-testid': 'mock-access-control',
				},
				`${ props.namespace }|${ props.resourceKey }|${ props.restApiRoot }|${ props.nonce }`
			);
		};
	},
	{ virtual: true }
);

const AbilityEditPanel = require( '../../../src/js/sitewide/components/AbilityEditPanel' ).default;

const baseAbility = {
	_override: {},
	site_allowed: null,
	readonly: null,
	destructive: null,
	idempotent: null,
	show_in_rest: null,
	show_in_mcp: null,
	mcp_type: null,
	mcp_servers: null,
};

function getButtonByText( text ) {
	return Array.from( document.querySelectorAll( 'button' ) ).find(
		( button ) => button.textContent === text
	);
}

describe( 'AbilityEditPanel access-control tab', () => {
	let container;
	let root;

	const renderPanel = ( slug ) => {
		act( () => {
			root.render( <AbilityEditPanel slug={ slug } ability={ baseAbility } onClose={ jest.fn() } /> );
		} );
	};

	beforeEach( () => {
		window.acrossaiAbilitiesSitewide = {
			nonce: 'nonce-123',
			restApiRoot: 'https://example.com/wp-json',
			current_user_id: 7,
		};

		mockDispatch.saveOverride.mockReset();
		mockDispatch.closeEditPanel.mockReset();
		mockAccessControlMounts.length = 0;
		mockAccessControlUnmounts.length = 0;

		container = document.createElement( 'div' );
		document.body.appendChild( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		if ( root ) {
			act( () => {
				root.unmount();
			} );
		}
		container.remove();
	} );

	test( 'renders the Access Control tab and passes the expected props', () => {
		renderPanel( 'alpha/ability' );

		act( () => {
			getButtonByText( 'Access Control' ).click();
		} );

		expect( document.querySelector( '[data-testid="mock-access-control"]' ).textContent ).toBe(
			'acrossai-abilities|alpha/ability|https://example.com/wp-json|nonce-123'
		);
	} );

	test( 'remounts the access-control component when the edited slug changes', () => {
		renderPanel( 'alpha' );

		act( () => {
			getButtonByText( 'Access Control' ).click();
		} );
		expect( mockAccessControlMounts ).toEqual( [ 'alpha' ] );

		renderPanel( 'beta' );

		expect( mockAccessControlUnmounts ).toEqual( [ 'alpha' ] );
		expect( mockAccessControlMounts ).toEqual( [ 'alpha', 'beta' ] );
		expect( document.querySelector( '[data-testid="mock-access-control"]' ).textContent ).toBe(
			'acrossai-abilities|beta|https://example.com/wp-json|nonce-123'
		);
	} );

	test( 'mounts a fresh access-control instance after closing and reopening the drawer', () => {
		renderPanel( 'gamma' );

		act( () => {
			getButtonByText( 'Access Control' ).click();
		} );
		act( () => {
			root.unmount();
		} );
		root = null;

		root = createRoot( container );
		renderPanel( 'gamma' );
		act( () => {
			getButtonByText( 'Access Control' ).click();
		} );

		expect( mockAccessControlMounts ).toEqual( [ 'gamma', 'gamma' ] );
		expect( mockAccessControlUnmounts ).toEqual( [ 'gamma' ] );
	} );
} );
