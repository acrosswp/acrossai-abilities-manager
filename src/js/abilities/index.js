/**
 * Entry point for the Custom Abilities Manager React app.
 *
 * @since 0.2.0
 */
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Provider } from '@wordpress/data';
import { store } from './store/index';
import AbilitiesManager from './components/AbilitiesManager';

// Register nonce middleware for all apiFetch requests.
const config = window.acrossaiAbilitiesManager || {};
if (config.nonce) {
	apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));
}

// Mount the React app.
const rootEl = document.getElementById('acrossai-abilities-root');
if (rootEl) {
	createRoot(rootEl).render(
		<Provider store={store}>
			<AbilitiesManager />
		</Provider>
	);
}
