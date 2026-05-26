/**
 * Entry point for the Sitewide Ability Manager React app.
 *
 * @since 0.1.0
 */
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import './store/index';
import AbilityManager from './components/AbilityManager';

// Register nonce middleware for all apiFetch requests.
const config = window.acrossaiAbilitiesSitewide || {};
if (config.nonce) {
	apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));
}

// Mount the React app.
const rootEl = document.getElementById('acrossai-abilities-manager-root');
if (rootEl) {
	createRoot(rootEl).render(<AbilityManager />);
}
