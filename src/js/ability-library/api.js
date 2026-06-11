import apiFetch from '@wordpress/api-fetch';

const libraryData = window.acrossaiAbilityLibraryData || {};
const { restBase = '', nonce = '' } = libraryData;

apiFetch.use(apiFetch.createNonceMiddleware(nonce));

/**
 * Fetch the current library config from the REST API.
 *
 * @return {Promise<Object>} Resolves to the saved config keyed by category.
 */
export function fetchConfig() {
	return apiFetch({ url: restBase + '/abilities/config' });
}

/**
 * Save the library config via the REST API.
 *
 * @param {Object} config Full config object keyed by category.
 * @return {Promise<Object>} Resolves to the saved config after the POST.
 */
export function saveConfig(config) {
	return apiFetch({
		url: restBase + '/abilities/config',
		method: 'POST',
		data: config,
	});
}
