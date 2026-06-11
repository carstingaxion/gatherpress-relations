/**
 * Hook: useRelationsMeta
 *
 * Reads and writes the `_gatherpress_relations` post meta for the
 * current post entity.
 *
 * HOW this hook works:
 * ────────────────────
 * Wraps `useEntityProp` for the `meta` property of the current post
 * type, then exposes a parsed `relations` array and a `setRelations`
 * function that serialises back to JSON. The billing_order is
 * automatically recalculated on every write.
 *
 * WHY a shared hook?
 * The cast-crew-list block, the sidebar plugin, and potentially
 * future blocks all need to read/write the same meta. Centralising
 * the parse → mutate → serialise cycle avoids duplication and
 * ensures consistent billing_order recalculation.
 *
 * @package GatherPressRelations
 */

import { useEntityProp } from '@wordpress/core-data';
import { useCallback, useMemo } from '@wordpress/element';
import {
	parseRelations,
	recalculateBillingOrder,
} from '../utils/parse-relations';

/**
 * Provides parsed relations and a setter for the current post.
 *
 * @param {string} postType - The current post type slug.
 * @return {{
 *   relations: Array<Object>,
 *   setRelations: (entries: Array<Object>) => void,
 *   rawMeta: Object,
 *   setRawMeta: Function,
 * }}
 */
export function useRelationsMeta( postType ) {
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta'
	);

	/**
	 * Parse the JSON string once per meta change.
	 *
	 * WHY useMemo?
	 * Avoids re-parsing on every render when meta hasn't changed.
	 * The JSON.parse is cheap but the referential stability of the
	 * returned array prevents unnecessary child re-renders.
	 *
	 * @type {Array<Object>}
	 */
	const relations = useMemo(
		() => parseRelations( meta?._gatherpress_relations ),
		[ meta?._gatherpress_relations ]
	);

	/**
	 * Writes a new relations array to meta after recalculating
	 * order.
	 *
	 * @param {Array<Object>} entries - The updated entries.
	 */
	const setRelations = useCallback(
		( entries ) => {
			const synced = recalculateBillingOrder( entries );
			setMeta( {
				...meta,
				_gatherpress_relations: JSON.stringify( synced ),
			} );
		},
		[ meta, setMeta ]
	);

	return { relations, setRelations, rawMeta: meta, setRawMeta: setMeta };
}
