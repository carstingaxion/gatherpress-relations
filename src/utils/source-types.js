/**
 * Source types configuration utilities.
 *
 * HOW this module works:
 * ──────────────────────
 * Provides access to the list of post types that declare
 * `gatherpress-relations-to` support. Reads from the PHP-localised
 * global `window.gatherPressRelationsConfig.sourceTypes` at call
 * time (lazy evaluation), falling back to a hardcoded default for
 * backward compatibility.
 *
 * WHY lazy evaluation?
 * Same reason as departments.js — the global may not be available
 * at module parse time because the sidebar plugin script may load
 * after block scripts.
 *
 * @package GatherPressRelations
 */

/**
 * Fallback source type configuration.
 *
 * Used when the localised config is unavailable (e.g., during tests
 * or if wp_localize_script hasn't fired yet).
 *
 * @type {Array<{slug: string, shadowTaxonomy: string, label: string, singularLabel: string}>}
 */
const FALLBACK_SOURCE_TYPES = [
	{
		slug: 'gatherpress_person',
		shadowTaxonomy: '_gatherpress_person',
		label: 'People',
		singularLabel: 'Person',
	},
];

/**
 * Internal cache — populated on first call.
 *
 * @type {Array<{slug: string, shadowTaxonomy: string, label: string, singularLabel: string}>|null}
 */
let _cachedSourceTypes = null;

/**
 * Returns the list of connectable source post types.
 *
 * Each entry provides the post type slug, its shadow taxonomy name,
 * and display labels. The editor JS uses this to:
 * - Build source type dropdowns in the sidebar.
 * - Derive shadow taxonomy slugs for term sync.
 * - Search the correct REST endpoint for each source type.
 *
 * @return {Array<{slug: string, shadowTaxonomy: string, label: string, singularLabel: string}>}
 */
export function getSourceTypes() {
	if ( _cachedSourceTypes ) {
		return _cachedSourceTypes;
	}

	if ( window.gatherPressRelationsConfig?.sourceTypes?.length > 0 ) {
		_cachedSourceTypes = window.gatherPressRelationsConfig.sourceTypes;
	} else {
		_cachedSourceTypes = FALLBACK_SOURCE_TYPES;
	}

	return _cachedSourceTypes;
}

/**
 * Returns the default (first) source type.
 *
 * WHY a helper?
 * Most code paths still assume a single source type. This provides
 * a clean migration path — callers can use `getDefaultSourceType()`
 * instead of hardcoding `'gatherpress_person'`.
 *
 * @return {{slug: string, shadowTaxonomy: string, label: string, singularLabel: string}}
 */
export function getDefaultSourceType() {
	return getSourceTypes()[ 0 ] || FALLBACK_SOURCE_TYPES[ 0 ];
}

/**
 * Returns the shadow taxonomy slug for a given source post type.
 *
 * @param {string} sourceSlug - The source post type slug.
 * @return {string} The shadow taxonomy slug (e.g., '_gatherpress_person').
 */
export function getShadowTaxonomyForSource( sourceSlug ) {
	const types = getSourceTypes();
	const match = types.find( ( t ) => t.slug === sourceSlug );
	return match ? match.shadowTaxonomy : '_' + sourceSlug;
}
