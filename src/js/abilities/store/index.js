/**
 * Redux store for the Custom Abilities Manager.
 *
 * State tracks abilities list, pagination, categories, view routing,
 * and savedAbility/draftAbility for unsaved-changes detection.
 *
 * @since 0.2.0
 */
import { createReduxStore, register } from '@wordpress/data';
import * as api from '../api/client';

export const STORE_NAME = 'acrossai/abilities';

// ---------------------------------------------------------------------------
// Action types
// ---------------------------------------------------------------------------
const SET_ABILITIES = 'SET_ABILITIES';
const SET_LOADING = 'SET_LOADING';
const SET_SAVING = 'SET_SAVING';
const SET_ERROR = 'SET_ERROR';
const SET_SAVE_ERROR = 'SET_SAVE_ERROR';
const CLEAR_ERROR = 'CLEAR_ERROR';
const SET_CATEGORIES = 'SET_CATEGORIES';
const SET_VIEW = 'SET_VIEW';
const SET_SAVED = 'SET_SAVED';
const UPDATE_DRAFT = 'UPDATE_DRAFT';
const CLEAR_DRAFT = 'CLEAR_DRAFT';
const REMOVE_ABILITY = 'REMOVE_ABILITY';
const PATCH_ABILITY = 'PATCH_ABILITY';

// ---------------------------------------------------------------------------
// Initial state
// ---------------------------------------------------------------------------
const DEFAULT_STATE = {
	abilities: [],
	total: 0,
	pages: 1,
	categories: [],
	isLoading: false,
	isSaving: false,
	error: null,
	saveError: null,
	view: 'list',
	savedAbility: null,
	draftAbility: {},
	isDirty: false,
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function computeIsDirty(draft, saved) {
	return JSON.stringify(draft) !== JSON.stringify(saved);
}

// ---------------------------------------------------------------------------
// Reducer
// ---------------------------------------------------------------------------
function reducer(state = DEFAULT_STATE, action) {
	switch (action.type) {
		case SET_ABILITIES:
			return {
				...state,
				abilities: action.abilities,
				total: action.total,
				pages: action.pages,
				isLoading: false,
				error: null,
			};

		case SET_LOADING:
			return { ...state, isLoading: action.isLoading };

		case SET_SAVING:
			return { ...state, isSaving: action.isSaving };

		case SET_ERROR:
			return { ...state, error: action.error, isLoading: false };

		case SET_SAVE_ERROR:
			return { ...state, saveError: action.error, isSaving: false };

		case CLEAR_ERROR:
			return { ...state, error: null, saveError: null };

		case SET_CATEGORIES:
			return { ...state, categories: action.categories };

		case SET_VIEW:
			return { ...state, view: action.view };

		case SET_SAVED: {
			const saved = action.ability;
			return {
				...state,
				savedAbility: saved,
				draftAbility: saved ? { ...saved } : {},
				isDirty: false,
				isSaving: false,
				saveError: null,
			};
		}

		case UPDATE_DRAFT: {
			const newDraft = { ...state.draftAbility, ...action.patch };
			return {
				...state,
				draftAbility: newDraft,
				isDirty: computeIsDirty(newDraft, state.savedAbility),
			};
		}

		case CLEAR_DRAFT:
			return {
				...state,
				savedAbility: null,
				draftAbility: {},
				isDirty: false,
				saveError: null,
			};

		case REMOVE_ABILITY:
			return {
				...state,
				abilities: state.abilities.filter(
					(a) => a.ability_slug !== action.slug
				),
				total: Math.max(0, state.total - 1),
				isSaving: false,
			};

		case PATCH_ABILITY:
			return {
				...state,
				abilities: state.abilities.map((a) =>
					a.ability_slug === action.slug
						? { ...a, ...action.patch }
						: a
				),
				isSaving: false,
			};

		default:
			return state;
	}
}

// ---------------------------------------------------------------------------
// Action creators
// ---------------------------------------------------------------------------
const actions = {
	setView: (view) => ({ type: SET_VIEW, view }),
	setSaved: (ability) => ({ type: SET_SAVED, ability }),
	updateDraft: (patch) => ({ type: UPDATE_DRAFT, patch }),
	clearDraft: () => ({ type: CLEAR_DRAFT }),
	clearError: () => ({ type: CLEAR_ERROR }),

	// Thunks
	fetchAbilities(params = {}) {
		return async ({ dispatch }) => {
			dispatch({ type: SET_LOADING, isLoading: true });
			try {
				const { abilities, total, pages } =
					await api.getAbilities(params);
				dispatch({ type: SET_ABILITIES, abilities, total, pages });
			} catch (err) {
				// FR-036: keep last-loaded data, show error notice
				dispatch({ type: SET_ERROR, error: err.message });
			}
		};
	},

	fetchAbility(slug) {
		return async ({ dispatch }) => {
			dispatch({ type: SET_LOADING, isLoading: true });
			try {
				const ability = await api.getAbility(slug);
				dispatch({ type: SET_LOADING, isLoading: false });
				dispatch({ type: SET_SAVED, ability });
			} catch (err) {
				dispatch({ type: SET_ERROR, error: err.message });
			}
		};
	},

	createAbility(data) {
		return async ({ dispatch }) => {
			dispatch({ type: SET_SAVING, isSaving: true });
			try {
				const ability = await api.createAbility(data);
				dispatch({ type: SET_SAVING, isSaving: false });
				return ability;
			} catch (err) {
				// FR-037: form stays open, save button re-enabled
				dispatch({ type: SET_SAVE_ERROR, error: err.message });
				return null;
			}
		};
	},

	updateAbility(slug, data) {
		return async ({ dispatch }) => {
			dispatch({ type: SET_SAVING, isSaving: true });
			try {
				const ability = await api.updateAbility(slug, data);
				dispatch({ type: SET_SAVED, ability });
				dispatch({
					type: PATCH_ABILITY,
					slug: ability.ability_slug,
					patch: ability,
				});
				return ability;
			} catch (err) {
				// FR-037: form stays open, isDirty stays true
				dispatch({ type: SET_SAVE_ERROR, error: err.message });
				return null;
			}
		};
	},

	deleteAbility(slug) {
		return async ({ dispatch }) => {
			dispatch({ type: SET_SAVING, isSaving: true });
			try {
				await api.deleteAbility(slug);
				// Optimistic: remove from list
				dispatch({ type: REMOVE_ABILITY, slug });
				dispatch({ type: SET_VIEW, view: 'list' });
			} catch (err) {
				dispatch({ type: SET_SAVE_ERROR, error: err.message });
			}
		};
	},

	bulkDeleteAbilities(slugs) {
		return async ({ dispatch }) => {
			dispatch({ type: SET_SAVING, isSaving: true });
			try {
				await Promise.all(slugs.map((slug) => api.deleteAbility(slug)));
				// Re-fetch list after bulk delete
				dispatch(actions.fetchAbilities());
			} catch (err) {
				dispatch({ type: SET_SAVE_ERROR, error: err.message });
			} finally {
				dispatch({ type: SET_SAVING, isSaving: false });
			}
		};
	},

	bulkUpdateStatus(slugs, status) {
		return async ({ dispatch }) => {
			dispatch({ type: SET_SAVING, isSaving: true });
			try {
				await Promise.all(
					slugs.map((slug) => api.updateAbility(slug, { status }))
				);
				// Re-fetch list after bulk status update
				dispatch(actions.fetchAbilities());
			} catch (err) {
				dispatch({ type: SET_SAVE_ERROR, error: err.message });
			} finally {
				dispatch({ type: SET_SAVING, isSaving: false });
			}
		};
	},

	clearOverrides(slug) {
		return async ({ dispatch }) => {
			dispatch({ type: SET_SAVING, isSaving: true });
			try {
				const ability = await api.deleteOverride(slug);
				dispatch({ type: SET_SAVED, ability });
				dispatch({
					type: PATCH_ABILITY,
					slug: ability.ability_slug,
					patch: ability,
				});
			} catch (err) {
				dispatch({ type: SET_SAVE_ERROR, error: err.message });
			} finally {
				dispatch({ type: SET_SAVING, isSaving: false });
			}
		};
	},

	fetchCategories() {
		return async ({ dispatch }) => {
			try {
				const categories = await api.getCategories();
				dispatch({
					type: SET_CATEGORIES,
					categories: Array.isArray(categories) ? categories : [],
				});
			} catch {
				// Non-fatal — category dropdown shows placeholder only
				dispatch({ type: SET_CATEGORIES, categories: [] });
			}
		};
	},
};

// ---------------------------------------------------------------------------
// Selectors
// ---------------------------------------------------------------------------
const selectors = {
	getAbilities: (state) => state.abilities,
	getTotal: (state) => state.total,
	getPages: (state) => state.pages,
	getCategories: (state) => state.categories,
	getIsLoading: (state) => state.isLoading,
	getIsSaving: (state) => state.isSaving,
	getError: (state) => state.error,
	getSaveError: (state) => state.saveError,
	getView: (state) => state.view,
	getSavedAbility: (state) => state.savedAbility,
	getDraftAbility: (state) => state.draftAbility,
	getIsDirty: (state) => state.isDirty,
};

// ---------------------------------------------------------------------------
// Register store
// ---------------------------------------------------------------------------
export const store = createReduxStore(STORE_NAME, {
	reducer,
	actions,
	selectors,
});

register(store);
