/**
 * Page root component for the Sitewide Ability Manager.
 *
 * Manages DataViews view state, persists to localStorage, and renders
 * AbilityTable + AbilityEditPanel.
 *
 * @since 0.1.0
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';
import AbilityTable from './AbilityTable';
import BulkActionToolbar from './BulkActionToolbar';

const LOCAL_STORAGE_KEY_PREFIX = 'acrossai_ability_table_view_';

function getStorageKey() {
	const config  = window.acrossaiAbilitiesSitewide || {};
	const userId  = config.current_user_id || 0;
	return LOCAL_STORAGE_KEY_PREFIX + userId;
}

const DEFAULT_VIEW = {
	type:    'table',
	search:  '',
	page:    1,
	perPage: 20,
	sort:    { field: 'slug', direction: 'asc' },
	filters: [],
	fields:  [ 'slug', 'provider', 'source', 'status', 'show_in_rest', 'show_in_mcp', 'mcp_type', 'mcp_servers', 'destructive', 'updated_at', 'allow_toggle' ],
};

function loadView() {
	try {
		const stored = localStorage.getItem( getStorageKey() );
		if ( stored ) {
			return { ...DEFAULT_VIEW, ...JSON.parse( stored ) };
		}
	} catch ( e ) {
		// Ignore parse errors.
	}
	return DEFAULT_VIEW;
}

/**
 * AbilityManager page root component.
 *
 * @return {JSX.Element}
 */
export default function AbilityManager() {
	const [ view, setView ]               = useState( loadView );
	const [ selectedSlugs, setSelectedSlugs ] = useState( [] );

	const { abilities, total, pages, isLoading, error, editingSlug } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				abilities:   store.getAbilities(),
				total:       store.getTotal(),
				pages:       store.getPages(),
				isLoading:   store.isLoading(),
				error:       store.getError(),
				editingSlug: store.getEditingSlug(),
			};
		},
		[]
	);

	const dispatch = useDispatch( STORE_NAME );

	// Persist view changes to localStorage.
	useEffect( () => {
		try {
			localStorage.setItem( getStorageKey(), JSON.stringify( view ) );
		} catch ( e ) {
			// Ignore storage errors.
		}
	}, [ view ] );

	// Fetch abilities whenever view changes.
	useEffect( () => {
		const params = {
			page:     view.page,
			per_page: view.perPage,
			search:   view.search || '',
			orderby:  view.sort?.field || 'slug',
			order:    view.sort?.direction || 'asc',
		};

		// Apply source/has_override filters from view.filters.
		if ( view.filters ) {
			view.filters.forEach( ( filter ) => {
				if ( filter.field === 'source' && filter.value ) {
					params.source = filter.value;
				}
				if ( filter.field === 'has_override' && filter.value !== undefined ) {
					params.has_override = filter.value;
				}
			} );
		}

		dispatch.fetchAbilities( params );
	}, [ view ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const handleViewChange = useCallback( ( newView ) => {
		setView( ( prev ) => ( { ...prev, ...newView } ) );
	}, [] );

	const handleBulkComplete = useCallback( () => {
		setSelectedSlugs( [] );
	}, [] );

	// Lazy-import AbilityEditPanel only when an ability is being edited.
	const [ EditPanel, setEditPanel ] = useState( null );
	useEffect( () => {
		if ( editingSlug && ! EditPanel ) {
			import( './AbilityEditPanel' ).then( ( mod ) => setEditPanel( () => mod.default ) );
		}
	}, [ editingSlug ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const editingAbility = editingSlug
		? abilities.find( ( a ) => a.slug === editingSlug ) || null
		: null;

	return (
		<div className="acrossai-abilities-manager">
			<h1>{ __( 'Abilities Manager', 'acrossai-abilities-manager' ) }</h1>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			<BulkActionToolbar
				selectedSlugs={ selectedSlugs }
				onComplete={ handleBulkComplete }
			/>

			<AbilityTable
				abilities={ abilities }
				total={ total }
				pages={ pages }
				isLoading={ isLoading }
				view={ view }
				onViewChange={ handleViewChange }
				selectedSlugs={ selectedSlugs }
				onSelectionChange={ setSelectedSlugs }
			/>

			{ editingSlug && EditPanel && (
				<EditPanel
					slug={ editingSlug }
					ability={ editingAbility }
					onClose={ () => dispatch.closeEditPanel() }
				/>
			) }
		</div>
	);
}
