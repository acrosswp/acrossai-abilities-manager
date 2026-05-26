/**
 * REST API client for the Custom Abilities Manager.
 *
 * All requests use @wordpress/api-fetch with the WP REST nonce set at app init.
 * getAbilities uses parse:false to access X-WP-Total / X-WP-TotalPages headers.
 *
 * @since 0.2.0
 */
import apiFetch from '@wordpress/api-fetch';

const { rest_namespace: restNamespace } = window.acrossaiAbilitiesManager;
const BASE = `${restNamespace}/abilities`;

/**
 * Fetch paginated, filtered abilities list.
 * Returns total + pages from REST response headers.
 *
 * @param {Object} params Query params (page, per_page, search, orderby, order, source, status).
 * @return {Promise<{abilities: Array, total: number, pages: number}>}
 */
export async function getAbilities(params = {}) {
	const qs = new URLSearchParams();
	Object.entries(params).forEach(([key, value]) => {
		if (null !== value && undefined !== value && '' !== value) {
			qs.set(key, String(value));
		}
	});

	const queryString = qs.toString();
	const path = `${BASE}${queryString ? '?' + queryString : ''}`;

	const response = await apiFetch({ path, parse: false });

	if (!response.ok) {
		let message = `Server error: ${response.status} ${response.statusText}`;
		try {
			const errData = await response.clone().json();
			if (errData?.message) {
				message = errData.message;
			}
		} catch {
			// Non-JSON error body — keep the status message.
		}
		throw new Error(message);
	}

	const data = await response.json();

	return {
		abilities: Array.isArray(data) ? data : [],
		total: parseInt(response.headers.get('X-WP-Total') || '0', 10),
		pages: parseInt(response.headers.get('X-WP-TotalPages') || '1', 10),
	};
}

/**
 * Fetch a single ability by slug.
 *
 * @param {string} slug Ability slug.
 * @return {Promise<Object>}
 */
export async function getAbility(slug) {
	return apiFetch({ path: `${BASE}/${encodeURIComponent(slug)}` });
}

/**
 * Create a new custom ability.
 *
 * @param {Object} data Ability fields.
 * @return {Promise<Object>}
 */
export async function createAbility(data) {
	return apiFetch({ path: BASE, method: 'POST', data });
}

/**
 * Sparsely update an existing ability (only send changed fields).
 *
 * @param {string} slug Ability slug.
 * @param {Object} data Changed fields only.
 * @return {Promise<Object>}
 */
export async function updateAbility(slug, data) {
	return apiFetch({ path: `${BASE}/${encodeURIComponent(slug)}`, method: 'POST', data });
}

/**
 * Delete an ability by slug.
 *
 * @param {string} slug Ability slug.
 * @return {Promise<void>}
 */
export async function deleteAbility(slug) {
	return apiFetch({ path: `${BASE}/${encodeURIComponent(slug)}`, method: 'DELETE' });
}

/**
 * Delete the override row for a non-db (plugin/core/theme) ability,
 * restoring it to its registry-declared defaults.
 *
 * @param {string} slug Ability slug.
 * @return {Promise<Object>} Fresh merged ability data (registry values, no overrides).
 */
export async function deleteOverride(slug) {
	return apiFetch({
		path: `${BASE}/${encodeURIComponent(slug)}/override`,
		method: 'DELETE',
	});
}

/**
 * Fetch ability categories.
 *
 * @return {Promise<Array<{slug: string, label: string}>>}
 */
export async function getCategories() {
	return apiFetch({ path: `${BASE}/categories` });
}
