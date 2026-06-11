/**
 * Shared department configuration utilities.
 *
 * HOW this module works:
 * ──────────────────────
 * Provides a single source of truth for department options used by
 * the Cast & Crew sidebar, cast-crew-list block, and recent-roles
 * block. Reads from the PHP-localised global when available, falls
 * back to hardcoded defaults.
 *
 * WHY lazy evaluation?
 * `wp_localize_script` injects `window.gatherPressRelationsConfig`
 * on the sidebar plugin script handle. Block scripts (cast-crew-list,
 * recent-roles) are separate webpack entries that may execute
 * **before** the sidebar script. If we read the global at module
 * parse time (a top-level `const`), the global may not exist yet,
 * causing a permanent fallback to hardcoded defaults.
 *
 * By wrapping reads in functions (`getDepartmentOptions()`,
 * `getDepartmentOrder()`), the global is read at **call time** —
 * when React components render — by which point all scripts have
 * loaded and the global is available.
 *
 * @package GatherPressRelations
 */

import { __ } from '@wordpress/i18n';

/**
 * Fallback department options used when the localised config is
 * unavailable (e.g., during tests or if wp_localize_script hasn't
 * fired).
 *
 * @type {Array<{label: string, value: string}>}
 */
const FALLBACK_DEPARTMENT_OPTIONS = [
	// { label: __( 'Cast', 'gatherpress-relations' ), value: 'cast' },
	// { label: __( 'Direction', 'gatherpress-relations' ), value: 'direction' },
	// { label: __( 'Design', 'gatherpress-relations' ), value: 'design' },
	// {
	// 	label: __( 'Stage Management', 'gatherpress-relations' ),
	// 	value: 'stage_management',
	// },
	// {
	// 	label: __( 'Musicians', 'gatherpress-relations' ),
	// 	value: 'musicians',
	// },
	// {
	// 	label: __( 'Production', 'gatherpress-relations' ),
	// 	value: 'production',
	// },
	// { label: __( 'Other', 'gatherpress-relations' ), value: 'other' },
	{ label: __( 'Speaker', 'gatherpress-relations' ), value: 'speaker' },
	{ label: __( 'Moderation', 'gatherpress-relations' ), value: 'moderation' },
	{ label: __( 'Host', 'gatherpress-relations' ), value: 'host' }

];

/**
 * Internal cache — populated on first call per post type.
 *
 * The global (post-type-agnostic) departments are cached under the
 * empty string key. Per-type overrides are cached under their post
 * type slug.
 *
 * @type {Object<string, Array<{label: string, value: string}>>}
 */
const _cache = {};

/**
 * Returns department options, optionally scoped to a source post type.
 *
 * HOW source-type-specific departments work:
 * ──────────────────────────────────────────
 * PHP calls the `gatherpress_relations_departments` filter once per
 * source post type (those with `gatherpress-relations-to` support)
 * and localises any overrides into
 * `window.gatherPressRelationsConfig.departmentsByPostType`. When a
 * `sourceType` argument is provided and an override exists for it,
 * that override is returned. Otherwise the global default is returned.
 *
 * WHY source type, not consumer type?
 * Departments describe the connectable entity (person, sponsor), not
 * the consumer (event, production). A sponsor always uses tiers
 * regardless of which consumer it's assigned to.
 *
 * WHY a function instead of a const?
 * The global may not be available at module-parse time because the
 * sidebar plugin script (which carries the `wp_localize_script`
 * payload) may load after the block scripts. Reading at call time
 * (during React render) guarantees the global is present.
 *
 * @param {string} [sourceType=''] - The source post type slug. Pass
 *                                    empty string or omit for the global default.
 * @return {Array<{label: string, value: string}>}
 */
export function getDepartmentOptions( sourceType = '' ) {
	const cacheKey = sourceType || '';

	if ( _cache[ cacheKey ] ) {
		return _cache[ cacheKey ];
	}

	/*
	 * Check for a per-source-type override first.
	 */
	if ( sourceType ) {
		const perType =
			window.gatherPressRelationsConfig?.departmentsByPostType?.[ sourceType ];
		if ( perType?.length > 0 ) {
			_cache[ cacheKey ] = perType;
			return perType;
		}
	}

	/*
	 * Fall back to the global departments.
	 */
	if ( ! _cache[ '' ] ) {
		if ( window.gatherPressRelationsConfig?.departments?.length > 0 ) {
			_cache[ '' ] = window.gatherPressRelationsConfig.departments;
		} else {
			_cache[ '' ] = FALLBACK_DEPARTMENT_OPTIONS;
		}
	}

	// If a source-type key was requested but no override exists, alias it
	// to the global so subsequent lookups are instant.
	if ( sourceType ) {
		_cache[ cacheKey ] = _cache[ '' ];
	}

	return _cache[ '' ];
}

/**
 * Backward-compatible static export.
 *
 * WHY keep this?
 * Existing code references `DEPARTMENT_OPTIONS` as a named import.
 * This getter-backed constant evaluates lazily on first access via
 * the function, ensuring the global has been injected by then.
 *
 * NOTE: This always resolves to the GLOBAL (non-post-type-specific)
 * departments. For per-type departments, call
 * `getDepartmentOptions( postType )` directly.
 *
 * @type {Array<{label: string, value: string}>}
 */
export const DEPARTMENT_OPTIONS = new Proxy( [], {
	get( _target, prop ) {
		const resolved = getDepartmentOptions();
		if ( prop === Symbol.iterator ) {
			return resolved[ Symbol.iterator ].bind( resolved );
		}
		return Reflect.get( resolved, prop );
	},
	has( _target, prop ) {
		return Reflect.has( getDepartmentOptions(), prop );
	},
	ownKeys() {
		return Reflect.ownKeys( getDepartmentOptions() );
	},
	getOwnPropertyDescriptor( _target, prop ) {
		return Object.getOwnPropertyDescriptor( getDepartmentOptions(), prop );
	},
} );

/**
 * Returns department display order derived from the resolved options.
 *
 * WHY a function?
 * Same reason as getDepartmentOptions — the order depends on the
 * resolved options, which may not be available at module-parse time.
 *
 * @param {string} [sourceType=''] - The source post type slug. Pass
 *                                    empty string or omit for the global default.
 * @return {string[]}
 */
export function getDepartmentOrder( sourceType = '' ) {
	return getDepartmentOptions( sourceType ).map( ( opt ) => opt.value );
}

/**
 * Backward-compatible static export for DEPARTMENT_ORDER.
 *
 * Same Proxy approach as DEPARTMENT_OPTIONS — defers resolution
 * until the array is actually iterated or indexed.
 *
 * @type {string[]}
 */
export const DEPARTMENT_ORDER = new Proxy( [], {
	get( _target, prop ) {
		const resolved = getDepartmentOrder();
		if ( prop === Symbol.iterator ) {
			return resolved[ Symbol.iterator ].bind( resolved );
		}
		return Reflect.get( resolved, prop );
	},
	has( _target, prop ) {
		return Reflect.has( getDepartmentOrder(), prop );
	},
	ownKeys() {
		return Reflect.ownKeys( getDepartmentOrder() );
	},
	getOwnPropertyDescriptor( _target, prop ) {
		return Object.getOwnPropertyDescriptor( getDepartmentOrder(), prop );
	},
} );

/**
 * Returns a human-readable label for a department slug.
 *
 * @param {string} slug           - The department slug (e.g., 'cast', 'direction').
 * @param {string} [sourceType=''] - The source post type slug for per-type labels.
 * @return {string} The display label, or the slug itself as fallback.
 */
export function getDepartmentLabel( slug, sourceType = '' ) {
	const options = getDepartmentOptions( sourceType );
	const match = options.find( ( opt ) => opt.value === slug );
	return match ? match.label : slug;
}
