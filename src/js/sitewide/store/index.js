/**
 * Redux store for the Sitewide Ability Manager.
 *
 * @since 0.1.0
 */
import { createReduxStore, register } from '@wordpress/data';
import * as client from '../api/client';

const STORE_NAME = 'acrossai-abilities/sitewide';

// ---------------------------------------------------------------------------
// Action type constants
// ---------------------------------------------------------------------------
const SET_ABILITIES    = 'SET_ABILITIES';
const SET_LOADING      = 'SET_LOADING';
const SET_ERROR        = 'SET_ERROR';
const SET_EDITING_SLUG = 'SET_EDITING_SLUG';
const SET_MCP_SERVERS  = 'SET_MCP_SERVERS';
const SAVE_UNCHANGED   = 'SAVE_UNCHANGED';
const UPDATE_ABILITY   = 'UPDATE_ABILITY';

// ---------------------------------------------------------------------------
// Initial state (per data-model.md §5)
// ---------------------------------------------------------------------------
const DEFAULT_STATE = {
	abilities:   [],
	total:       0,
	pages:       0,
	currentPage: 1,
	isLoading:   false,
	error:       null,
	editingSlug: null,
	mcpServers:  [],
};

// ---------------------------------------------------------------------------
// Reducer
// ---------------------------------------------------------------------------
function reducer( state = DEFAULT_STATE, action ) {
	switch ( action.type ) {
		case SET_ABILITIES:
			return {
				...state,
				abilities:   action.abilities,
				total:       action.total,
				pages:       action.pages,
				currentPage: action.page || state.currentPage,
				isLoading:   false,
				error:       null,
			};

		case SET_LOADING:
			return { ...state, isLoading: action.isLoading };

		case SET_ERROR:
			return { ...state, error: action.error, isLoading: false };

		case SET_EDITING_SLUG:
			return { ...state, editingSlug: action.slug };

		case SET_MCP_SERVERS:
			return { ...state, mcpServers: action.servers };

		case SAVE_UNCHANGED:
			return { ...state, isLoading: false };

		case UPDATE_ABILITY: {
			const updated = state.abilities.map( ( ability ) =>
				ability.slug === action.ability.slug ? { ...ability, ...action.ability } : ability
			);
			return { ...state, abilities: updated };
		}

		default:
			return state;
	}
}

// ---------------------------------------------------------------------------
// Action creators
// ---------------------------------------------------------------------------
const actions = {
	/**
	 * Fetch the ability list with the given view params.
	 *
	 * @param {Object} viewParams Query parameters.
	 * @return {Function} Thunk.
	 */
	fetchAbilities: ( viewParams ) => async ( { dispatch } ) => {
		dispatch( { type: SET_LOADING, isLoading: true } );
		try {
			const result = await client.fetchAbilities( viewParams );
			dispatch( {
				type:      SET_ABILITIES,
				abilities: result.abilities,
				total:     result.total,
				pages:     result.pages,
				page:      viewParams.page || 1,
			} );
		} catch ( error ) {
			dispatch( { type: SET_ERROR, error: error.message || String( error ) } );
		}
	},

	/**
	 * Optimistically toggle site_allowed for an ability.
	 *
	 * @param {string}  slug        Ability slug.
	 * @param {boolean} siteAllowed New value.
	 * @return {Function} Thunk.
	 */
	toggleAllowed: ( slug, siteAllowed ) => async ( { dispatch, select } ) => {
		const prev = select.getAbilities().find( ( a ) => a.slug === slug );

		// Optimistic update.
		dispatch( { type: UPDATE_ABILITY, ability: { slug, site_allowed: siteAllowed } } );

		try {
			const result = await client.toggleAbility( slug, siteAllowed );
			dispatch( {
				type:    UPDATE_ABILITY,
				ability: { slug: result.slug, site_allowed: result.site_allowed, has_override: result.has_override },
			} );
		} catch ( error ) {
			// Rollback.
			if ( prev ) {
				dispatch( { type: UPDATE_ABILITY, ability: prev } );
			}
			dispatch( { type: SET_ERROR, error: error.message || String( error ) } );
		}
	},

	/** Open the edit panel for the given slug. */
	openEditPanel: ( slug ) => ( { dispatch } ) => {
		dispatch( { type: SET_EDITING_SLUG, slug } );
	},

	/** Close the edit panel. */
	closeEditPanel: () => ( { dispatch } ) => {
		dispatch( { type: SET_EDITING_SLUG, slug: null } );
	},

	/**
	 * Save an override with optimistic update.
	 *
	 * @param {string} slug Ability slug.
	 * @param {Object} data Fields to save.
	 * @return {Function} Thunk.
	 */
	saveOverride: ( slug, data ) => async ( { dispatch, select } ) => {
		dispatch( { type: SET_LOADING, isLoading: true } );
		const prev = select.getAbilities().find( ( a ) => a.slug === slug );

		try {
			const result = await client.saveOverride( slug, data );

			if ( result.unchanged ) {
				dispatch( { type: SAVE_UNCHANGED } );
				return { unchanged: true };
			}

			dispatch( { type: UPDATE_ABILITY, ability: result } );
			dispatch( { type: SET_LOADING, isLoading: false } );
			return result;
		} catch ( error ) {
			if ( prev ) {
				dispatch( { type: UPDATE_ABILITY, ability: prev } );
			}
			dispatch( { type: SET_ERROR, error: error.message || String( error ) } );
			throw error;
		}
	},

	/**
	 * Fetch MCP server list (lazy load).
	 *
	 * @return {Function} Thunk.
	 */
	fetchMcpServers: () => async ( { dispatch } ) => {
		try {
			const servers = await client.fetchMcpServers();
			dispatch( { type: SET_MCP_SERVERS, servers } );
		} catch ( error ) {
			// MCP is optional — silently ignore errors.
		}
	},

	/**
	 * Optimistically delete an override.
	 *
	 * @param {string} slug Ability slug.
	 * @return {Function} Thunk.
	 */
	deleteOverride: ( slug ) => async ( { dispatch, select } ) => {
		const prev = select.getAbilities().find( ( a ) => a.slug === slug );

		// Optimistic update — clear all override fields and _override map to null.
		// _override MUST be explicitly cleared here; without it the spread in
		// UPDATE_ABILITY keeps the old _override values, so the edit panel seeds
		// from stale Yes/No data after the user reopens it post-reset.
		const nullOverride = {
			site_allowed: null,
			readonly:     null,
			destructive:  null,
			idempotent:   null,
			show_in_rest: null,
			show_in_mcp:  null,
			mcp_type:     null,
			mcp_servers:  null,
		};
		dispatch( {
			type:    UPDATE_ABILITY,
			ability: {
				slug,
				has_override:  false,
				site_allowed:  prev ? prev._registry?.site_allowed ?? null : null,
				readonly:      prev ? prev._registry?.readonly ?? null : null,
				destructive:   prev ? prev._registry?.destructive ?? null : null,
				idempotent:    prev ? prev._registry?.idempotent ?? null : null,
				show_in_rest:  prev ? prev._registry?.show_in_rest ?? null : null,
				show_in_mcp:   prev ? prev._registry?.show_in_mcp ?? null : null,
				mcp_type:      prev ? prev._registry?.mcp_type ?? null : null,
				mcp_servers:   prev ? prev._registry?.mcp_servers ?? null : null,
				_override:     nullOverride,
			},
		} );

		try {
			await client.deleteOverride( slug );
		} catch ( error ) {
			if ( prev ) {
				dispatch( { type: UPDATE_ABILITY, ability: prev } );
			}
			dispatch( { type: SET_ERROR, error: error.message || String( error ) } );
		}
	},

	/**
	 * Apply a bulk action to multiple slugs.
	 *
	 * @param {string[]} slugs  Ability slugs.
	 * @param {string}   action 'allow' | 'disallow' | 'reset'.
	 * @return {Function} Thunk.
	 */
	bulkAction: ( slugs, action ) => async ( { dispatch } ) => {
		dispatch( { type: SET_LOADING, isLoading: true } );
		try {
			const result = await client.bulkAction( slugs, action );

			// Update store for each returned result.
			result.results.forEach( ( item ) => {
				if ( item.status === 'success' ) {
					if ( 'reset' === action ) {
						dispatch( { type: UPDATE_ABILITY, ability: { slug: item.slug, has_override: false } } );
					} else {
						dispatch( {
							type:    UPDATE_ABILITY,
							ability: { slug: item.slug, site_allowed: 'allow' === action, has_override: true },
						} );
					}
				}
			} );

			if ( result.failed > 0 ) {
				dispatch( {
					type:  SET_ERROR,
					error: `${ result.succeeded } succeeded, ${ result.failed } failed.`,
				} );
			} else {
				dispatch( { type: SET_LOADING, isLoading: false } );
			}

			return result;
		} catch ( error ) {
			dispatch( { type: SET_ERROR, error: error.message || String( error ) } );
			throw error;
		}
	},
};

// ---------------------------------------------------------------------------
// Selectors
// ---------------------------------------------------------------------------
const selectors = {
	getAbilities:   ( state ) => state.abilities,
	getTotal:       ( state ) => state.total,
	getPages:       ( state ) => state.pages,
	getCurrentPage: ( state ) => state.currentPage,
	isLoading:      ( state ) => state.isLoading,
	getError:       ( state ) => state.error,
	getEditingSlug: ( state ) => state.editingSlug,
	getMcpServers:  ( state ) => state.mcpServers,
};

// ---------------------------------------------------------------------------
// Create and register store
// ---------------------------------------------------------------------------
const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
} );

register( store );

export { STORE_NAME };
export default store;
