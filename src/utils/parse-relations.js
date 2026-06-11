/**
 * Relation meta parsing and mutation utilities.
 *
 * HOW this module works:
 * ──────────────────────
 * Provides pure functions for parsing the `_gatherpress_relations`
 * JSON string into an array, and for recalculating order
 * after any mutation (add, remove, reorder, department change).
 *
 * WHY pure functions?
 * They are trivially testable — no store, no side-effects, just
 * input → output. Each mutation helper in the sidebar/block
 * composes these to produce the next state.
 *
 * META FIELD SCHEMA:
 * Each entry in the `_gatherpress_relations` JSON array uses these
 * short, generic field names:
 * - `slug`       — shadow term slug (e.g., `_jane-doe`)
 * - `id`         — source post ID (denormalized for fast rendering)
 * - `type`       — source post type slug (e.g., `gatherpress_person`)
 * - `role`       — role name, contextual to this relationship
 * - `department` — grouping category slug
 * - `order`      — sort position within the department
 *
 * @package GatherPressRelations
 */

/**
 * Safely parses a JSON string into an array of relation objects.
 *
 * WHY try/catch?
 * The meta value may be empty, null, or malformed JSON if the post
 * has never had relations assigned. Defaulting to an empty array
 * prevents consumers from crashing.
 *
 * @param {string|null|undefined} jsonString - The raw meta value.
 * @return {Array<Object>} Parsed array of relation entries, or [].
 */
export function parseRelations( jsonString ) {
	if ( ! jsonString ) {
		return [];
	}
	try {
		const parsed = JSON.parse( jsonString );
		return Array.isArray( parsed ) ? parsed : [];
	} catch {
		return [];
	}
}

/**
 * Recalculates order for every entry, grouped by department.
 *
 * HOW this works:
 * ───────────────
 * Iterates the array in order, counting entries per department.
 * Each entry's `order` is set to its 1-based position
 * within its department group. The array order itself represents
 * the user's intended sort — so the first "cast" entry becomes
 * order 1, the second becomes 2, etc.
 *
 * WHY recalculate on every mutation?
 * Add, remove, reorder, and department-change operations all affect
 * the per-department ordering. A full recalculation after each
 * mutation is simpler and less error-prone than incremental updates.
 *
 * @param {Array<Object>} entries - The relation entries array.
 * @return {Array<Object>} A new array with updated order values.
 */
export function recalculateBillingOrder( entries ) {
	const counters = {};
	return entries.map( ( entry ) => {
		const dept = entry.department || 'other';
		if ( ! counters[ dept ] ) {
			counters[ dept ] = 0;
		}
		counters[ dept ] += 1;
		return { ...entry, order: counters[ dept ] };
	} );
}

/**
 * Groups an array of relation entries by department.
 *
 * @param {Array<Object>} entries - The relation entries.
 * @return {Object<string, Array<Object>>} Entries keyed by department slug.
 */
export function groupByDepartment( entries ) {
	const grouped = {};
	entries.forEach( ( entry, idx ) => {
		const dept = entry.department || 'other';
		if ( ! grouped[ dept ] ) {
			grouped[ dept ] = [];
		}
		grouped[ dept ].push( { ...entry, _index: idx } );
	} );
	return grouped;
}
