/**
 * Root component for the Custom Abilities Manager.
 *
 * Routes between list view and form views based on the `view` state.
 * Registers the beforeunload guard when the form is dirty.
 * Saves and restores the list scroll position when navigating to/from forms (SC-004).
 *
 * @since 0.2.0
 */
import { useEffect, useRef } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../store/index';
import AbilitiesList from './AbilitiesList';
import AbilityForm from './AbilityForm';

/* global requestAnimationFrame */

const SCROLL_KEY = 'acrossai_abilities_list_scroll';

/**
 * AbilitiesManager root component.
 *
 * @return {JSX.Element}
 */
export default function AbilitiesManager() {
	const view = useSelect((select) => select(STORE_NAME).getView(), []);
	const isDirty = useSelect((select) => select(STORE_NAME).getIsDirty(), []);
	const prevViewRef = useRef(view);

	// beforeunload guard: warn when navigating away with unsaved changes.
	useEffect(() => {
		const handler = (e) => {
			e.preventDefault();
			e.returnValue = '';
		};

		if (isDirty) {
			window.addEventListener('beforeunload', handler);
		}

		return () => {
			window.removeEventListener('beforeunload', handler);
		};
	}, [isDirty]);

	// SC-004: Save scroll position when leaving list view; restore when returning.
	useEffect(() => {
		const prevView = prevViewRef.current;
		const isLeavingList = prevView === 'list' && view !== 'list';
		const isReturningToList = prevView !== 'list' && view === 'list';

		if (isLeavingList) {
			sessionStorage.setItem(SCROLL_KEY, String(window.scrollY));
		}

		if (isReturningToList) {
			const saved = parseInt(
				sessionStorage.getItem(SCROLL_KEY) || '0',
				10
			);
			if (saved > 0) {
				// Defer one frame so the list has time to render before scrolling.
				requestAnimationFrame(() => window.scrollTo(0, saved));
			}
		}

		prevViewRef.current = view;
	}, [view]);

	if (view === 'list') {
		return <AbilitiesList />;
	}

	if (view && view.mode === 'create') {
		return <AbilityForm mode="create" />;
	}

	if (view && view.mode === 'edit') {
		return <AbilityForm mode="edit" id={view.id} />;
	}

	if (view && view.mode === 'override') {
		return <AbilityForm mode="override" id={view.id} />;
	}

	return <AbilitiesList />;
}
