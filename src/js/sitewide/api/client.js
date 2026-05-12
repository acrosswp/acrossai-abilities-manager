/**
 * REST API client for the Sitewide Ability Manager.
 *
 * All requests use @wordpress/api-fetch with the WP REST nonce set at app init.
 *
 * @since 0.1.0
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch the paginated, filtered abilities list.
 *
 * @param {Object} params Query params (page, per_page, search, orderby, order, source, has_override).
 * @return {Promise<{abilities: Array, total: number, pages: number}>}
 */
export async function fetchAbilities( params = {} ) {
	const qs = new URLSearchParams();
	Object.entries( params ).forEach( ( [ key, value ] ) => {
		if ( null !== value && undefined !== value && '' !== value ) {
			qs.set( key, String( value ) );
		}
	} );

	const queryString = qs.toString();
	const path = `acrossai-abilities-manager/v1/sitewide/abilities${ queryString ? '?' + queryString : '' }`;

	const response = await apiFetch( { path, parse: false } );

	// Guard: apiFetch with parse:false bypasses automatic error handling.
	// Check response.ok before parsing to produce a clear error instead of a
	// SyntaxError when the server returns HTML (redirect, PHP error, etc.).
	if ( ! response.ok ) {
		let message = `Server error: ${ response.status } ${ response.statusText }`;
		try {
			const errData = await response.clone().json();
			if ( errData && errData.message ) {
				message = errData.message;
			}
		} catch ( _ ) {
			// Non-JSON error body — keep the status message.
		}
		throw new Error( message );
	}

	const contentType = response.headers.get( 'Content-Type' ) || '';
	if ( ! contentType.includes( 'application/json' ) ) {
		throw new Error( `Expected JSON from REST API but received: ${ contentType }` );
	}

	const data = await response.json();

	return {
		abilities: Array.isArray( data ) ? data : [],
		total:     parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 ),
		pages:     parseInt( response.headers.get( 'X-WP-TotalPages' ) || '0', 10 ),
	};
}

/**
 * Fetch a single ability's effective data.
 *
 * @param {string} slug Ability slug.
 * @return {Promise<Object>}
 */
export async function fetchAbility( slug ) {
	return apiFetch( {
		path: `acrossai-abilities-manager/v1/sitewide/abilities/${ slug.split( '/' ).map( encodeURIComponent ).join( '/' ) }`,
	} );
}

/**
 * Save an override for a specific ability.
 *
 * @param {string} slug Ability slug.
 * @param {Object} data Fields to save.
 * @return {Promise<Object>}
 */
export async function saveOverride( slug, data ) {
	return apiFetch( {
		path:   `acrossai-abilities-manager/v1/sitewide/abilities/${ slug.split( '/' ).map( encodeURIComponent ).join( '/' ) }`,
		method: 'POST',
		data,
	} );
}

/**
 * Delete the override for a specific ability.
 *
 * @param {string} slug Ability slug.
 * @return {Promise<Object>}
 */
export async function deleteOverride( slug ) {
	return apiFetch( {
		path:   `acrossai-abilities-manager/v1/sitewide/abilities/${ slug.split( '/' ).map( encodeURIComponent ).join( '/' ) }`,
		method: 'DELETE',
	} );
}

/**
 * Toggle the site_allowed flag for a specific ability.
 *
 * @param {string}  slug        Ability slug.
 * @param {boolean} siteAllowed New value.
 * @return {Promise<Object>}
 */
export async function toggleAbility( slug, siteAllowed ) {
	return apiFetch( {
		path:   `acrossai-abilities-manager/v1/sitewide/abilities/${ slug.split( '/' ).map( encodeURIComponent ).join( '/' ) }/toggle`,
		method: 'POST',
		data:   { site_allowed: siteAllowed },
	} );
}

/**
 * Apply a bulk action to multiple abilities.
 *
 * @param {string[]} slugs  Ability slugs.
 * @param {string}   action 'allow' | 'disallow' | 'reset'.
 * @return {Promise<Object>}
 */
export async function bulkAction( slugs, action ) {
	return apiFetch( {
		path:   'acrossai-abilities-manager/v1/sitewide/abilities/bulk',
		method: 'POST',
		data:   { slugs, action },
	} );
}

/**
 * Fetch the list of available MCP servers.
 *
 * @return {Promise<Array>}
 */
export async function fetchMcpServers() {
	return apiFetch( {
		path: 'acrossai-abilities-manager/v1/sitewide/mcp-servers',
	} );
}
